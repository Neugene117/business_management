<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/tenant.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';

requireLogin();
requireActiveBusiness($conn);

$permissions = require __DIR__ . '/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

validateCsrfToken($_POST['csrf_token'] ?? '');

$action = isset($_POST['action']) ? $_POST['action'] : '';
$businessId = $_SESSION['active_business_id'];
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';

switch ($action) {
    case 'adjust':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['adjust']);

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $locationId = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;
        $movement_type = trim($_POST['movement_type'] ?? '');
        $quantity = (float)($_POST['quantity'] ?? 0.0);
        $unit_cost = (float)($_POST['unit_cost'] ?? 0.0);
        $notes = trim($_POST['notes'] ?? '');

        if (empty($productId) || empty($locationId) || empty($movement_type) || $quantity <= 0 || empty($notes)) {
            setFlashMessage('error', 'All fields are required. Quantity must be greater than zero.');
            header("Location: index.php?tab=adjust" . $role_query);
            exit();
        }

        // Validate product
        $prodQ = "SELECT id, name, sku FROM products WHERE id = ? AND business_id = ? LIMIT 1";
        $pStmt = mysqli_prepare($conn, $prodQ);
        mysqli_stmt_bind_param($pStmt, 'ii', $productId, $businessId);
        mysqli_stmt_execute($pStmt);
        $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));
        if (!$prod) {
            setFlashMessage('error', 'Invalid product selected.');
            header("Location: index.php?tab=adjust" . $role_query);
            exit();
        }

        // Validate location
        $locQ = "SELECT id FROM business_locations WHERE id = ? AND business_id = ? LIMIT 1";
        $lStmt = mysqli_prepare($conn, $locQ);
        mysqli_stmt_bind_param($lStmt, 'ii', $locationId, $businessId);
        mysqli_stmt_execute($lStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($lStmt)) === 0) {
            setFlashMessage('error', 'Invalid location selected.');
            header("Location: index.php?tab=adjust" . $role_query);
            exit();
        }

        // Check if movement type is IN or OUT
        $in_types = ['MANUAL_IN', 'STOCKTAKE_GAIN', 'CORRECTION_IN'];
        $out_types = ['MANUAL_OUT', 'STOCKTAKE_LOSS', 'DAMAGE', 'EXPIRY', 'CORRECTION_OUT'];

        if (in_array($movement_type, $in_types)) {
            $qty_delta = $quantity;
        } elseif (in_array($movement_type, $out_types)) {
            $qty_delta = -$quantity;
        } else {
            setFlashMessage('error', 'Invalid adjustment type selected.');
            header("Location: index.php?tab=adjust" . $role_query);
            exit();
        }

        // Fetch negative stock rules
        $ruleQ = "SELECT allow_negative_stock FROM business_accounting_settings WHERE business_id = ? LIMIT 1";
        $rStmt = mysqli_prepare($conn, $ruleQ);
        mysqli_stmt_bind_param($rStmt, 'i', $businessId);
        mysqli_stmt_execute($rStmt);
        $allow_negative = mysqli_fetch_assoc(mysqli_stmt_get_result($rStmt))['allow_negative_stock'] ?? 0;

        mysqli_begin_transaction($conn);
        try {
            // Lock active balance row for update
            $balQ = "SELECT id, quantity_on_hand, average_unit_cost FROM inventory_balances WHERE business_id = ? AND location_id = ? AND product_id = ? LIMIT 1 FOR UPDATE";
            $bStmt = mysqli_prepare($conn, $balQ);
            mysqli_stmt_bind_param($bStmt, 'iii', $businessId, $locationId, $productId);
            mysqli_stmt_execute($bStmt);
            $bal = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt));

            if ($bal) {
                $new_qty = $bal['quantity_on_hand'] + $qty_delta;
                
                // Enforce negative stock blocker
                if (!$allow_negative && $new_qty < 0) {
                    throw new Exception("Stock adjustment rejected. Available quantity on hand is: " . (float)$bal['quantity_on_hand'] . ", which is insufficient to decrease by " . $quantity);
                }

                // Costing update: Weighted average calculations (only on stock gains)
                if ($qty_delta > 0) {
                    $old_total_cost = $bal['quantity_on_hand'] * $bal['average_unit_cost'];
                    $add_total_cost = $qty_delta * $unit_cost;
                    if ($new_qty > 0) {
                        $new_avg = ($old_total_cost + $add_total_cost) / $new_qty;
                    } else {
                        $new_avg = 0.0;
                    }
                } else {
                    $new_avg = $bal['average_unit_cost'];
                }

                $updBQuery = "
                    UPDATE inventory_balances 
                    SET quantity_on_hand = ?, average_unit_cost = ?, last_calculated_at = NOW(6), updated_at = NOW(6) 
                    WHERE id = ?
                ";
                $ubStmt = mysqli_prepare($conn, $updBQuery);
                mysqli_stmt_bind_param($ubStmt, 'ddi', $new_qty, $new_avg, $bal['id']);
                mysqli_stmt_execute($ubStmt);

            } else {
                $new_qty = $qty_delta;
                
                if (!$allow_negative && $new_qty < 0) {
                    throw new Exception("Stock adjustment rejected. No stock exists at this location to decrease.");
                }

                $new_avg = ($qty_delta > 0) ? $unit_cost : 0.0;

                $insBQuery = "
                    INSERT INTO inventory_balances (
                        business_id, location_id, product_id, quantity_on_hand, 
                        average_unit_cost, last_calculated_at, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, NOW(6), NOW(6), NOW(6)
                    )
                ";
                $ibStmt = mysqli_prepare($conn, $insBQuery);
                mysqli_stmt_bind_param($ibStmt, 'iiidd', $businessId, $locationId, $productId, $new_qty, $new_avg);
                mysqli_stmt_execute($ibStmt);
            }

            // Insert into movements log
            $insMQuery = "
                INSERT INTO inventory_movements (
                    business_id, location_id, product_id, movement_type, 
                    quantity_delta, unit_cost, occurred_at, created_by_membership_id, notes, created_at
                ) VALUES (
                    ?, ?, ?, ?, 
                    ?, ?, NOW(6), ?, ?, NOW(6)
                )
            ";
            $imStmt = mysqli_prepare($conn, $insMQuery);
            mysqli_stmt_bind_param($imStmt, 'iiissdis', $businessId, $locationId, $productId, $movement_type, $qty_delta, $unit_cost, $_SESSION['membership_id'], $notes);
            mysqli_stmt_execute($imStmt);
            $movementId = mysqli_insert_id($conn);

            // Write audit trail
            writeAuditLog($conn, $businessId, 'INVENTORY_ADJUSTED', 'inventory_movement', $movementId, [
                'sku' => $prod['sku'],
                'movement_type' => $movement_type,
                'quantity_delta' => $qty_delta,
                'unit_cost' => $unit_cost
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Stock adjustment recorded successfully.');
            header("Location: index.php?tab=balances" . $role_query);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Failed to adjust inventory: " . $e->getMessage());
            setFlashMessage('error', $e->getMessage());
            header("Location: index.php?tab=adjust" . $role_query);
            exit();
        }

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
