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
    case 'create':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        $purchase_number = trim($_POST['purchase_number'] ?? '');
        $purchase_date = trim($_POST['purchase_date'] ?? '');
        $supplier_id = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
        $location_id = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;
        $notes = trim($_POST['notes'] ?? NULL);

        $product_ids = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $unit_costs = $_POST['unit_costs'] ?? [];

        if (empty($purchase_number) || empty($purchase_date) || empty($supplier_id) || empty($location_id) || empty($product_ids)) {
            setFlashMessage('error', 'PO number, date, supplier, target location, and at least one item are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // Check PO uniqueness
            $chkQ = "SELECT id FROM purchases WHERE business_id = ? AND purchase_number = ? LIMIT 1 FOR UPDATE";
            $cStmt = mysqli_prepare($conn, $chkQ);
            mysqli_stmt_bind_param($cStmt, 'is', $businessId, $purchase_number);
            mysqli_stmt_execute($cStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($cStmt)) > 0) {
                throw new Exception("A Purchase Order with this reference number already exists.");
            }

            // 1. Insert parent Purchase Order
            $status = 'DRAFT';
            $insPO = "
                INSERT INTO purchases (
                    business_id, location_id, supplier_id, purchase_number, 
                    status, purchase_date, notes, created_by_membership_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(6), NOW(6))
            ";
            $pStmt = mysqli_prepare($conn, $insPO);
            mysqli_stmt_bind_param(
                $pStmt,
                'iiissssi',
                $businessId, $location_id, $supplier_id, $purchase_number,
                $status, $purchase_date, $notes, $_SESSION['membership_id']
            );
            mysqli_stmt_execute($pStmt);
            $purchaseId = mysqli_insert_id($conn);

            // 2. Insert line items
            $insItem = "
                INSERT INTO purchase_items (
                    business_id, purchase_id, product_id, ordered_quantity, 
                    received_quantity, unit_cost, discount_amount, tax_amount, line_total, created_at
                ) VALUES (?, ?, ?, ?, 0.0000, ?, 0.0000, 0.0000, ?, NOW(6))
            ";
            $iStmt = mysqli_prepare($conn, $insItem);

            $subtotal = 0.0;
            for ($k = 0; $k < count($product_ids); $k++) {
                $pId = (int)$product_ids[$k];
                $qty = (float)$quantities[$k];
                $cost = (float)$unit_costs[$k];
                
                if ($pId <= 0 || $qty <= 0 || $cost < 0) {
                    continue;
                }

                $line_total = $qty * $cost;
                $subtotal += $line_total;

                mysqli_stmt_bind_param($iStmt, 'iiiddd', $businessId, $purchaseId, $pId, $qty, $cost, $line_total);
                mysqli_stmt_execute($iStmt);
            }

            // 3. Update totals in parent PO
            $updPO = "UPDATE purchases SET subtotal = ?, total_amount = ? WHERE id = ?";
            $uStmt = mysqli_prepare($conn, $updPO);
            mysqli_stmt_bind_param($uStmt, 'ddi', $subtotal, $subtotal, $purchaseId);
            mysqli_stmt_execute($uStmt);

            // 4. Audit Log
            writeAuditLog($conn, $businessId, 'PURCHASE_ORDER_CREATED', 'purchase', $purchaseId, [
                'purchase_number' => $purchase_number,
                'total_amount' => $subtotal
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Purchase Order created in Draft successfully.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Failed to create PO: " . $e->getMessage());
            setFlashMessage('error', $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'mark_ordered':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $purchaseId = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        if (empty($purchaseId)) {
            setFlashMessage('error', 'Invalid PO reference.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "UPDATE purchases SET status = 'ORDERED', updated_at = NOW(6) WHERE id = ? AND business_id = ? AND status = 'DRAFT'";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $purchaseId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'PURCHASE_ORDER_SENT', 'purchase', $purchaseId, ['status' => 'ORDERED']);
            setFlashMessage('success', 'Purchase Order status changed to Ordered.');
        } else {
            setFlashMessage('error', 'Failed to update PO status.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'receive_po':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['receive']);

        $purchaseId = isset($_POST['purchase_id']) ? (int)$_POST['purchase_id'] : 0;
        $supplier_invoice_number = trim($_POST['supplier_invoice_number'] ?? NULL);
        $item_ids = $_POST['item_ids'] ?? [];
        $received_quantities = $_POST['received_quantities'] ?? [];

        if (empty($purchaseId) || empty($item_ids)) {
            setFlashMessage('error', 'Invalid request parameters.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // Lock parent PO for update
            $poQ = "SELECT id, location_id, purchase_number, status FROM purchases WHERE id = ? AND business_id = ? LIMIT 1 FOR UPDATE";
            $poStmt = mysqli_prepare($conn, $poQ);
            mysqli_stmt_bind_param($poStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($poStmt);
            $po = mysqli_fetch_assoc(mysqli_stmt_get_result($poStmt));

            if (!$po) {
                throw new Exception("Purchase Order not found.");
            }
            if ($po['status'] !== 'ORDERED') {
                throw new Exception("Only ORDERED purchase orders can be received.");
            }

            $locationId = $po['location_id'];
            $new_subtotal = 0.0;

            // Loop items and update received quantities + stock WAVG costing
            $updItem = "
                UPDATE purchase_items 
                SET received_quantity = ?, line_total = ? 
                WHERE id = ? AND purchase_id = ?
            ";
            $uiStmt = mysqli_prepare($conn, $updItem);

            $insMov = "
                INSERT INTO inventory_movements (
                    business_id, location_id, product_id, movement_type, 
                    quantity_delta, unit_cost, occurred_at, purchase_item_id, created_by_membership_id, notes, created_at
                ) VALUES (
                    ?, ?, ?, 'PURCHASE_RECEIPT', 
                    ?, ?, NOW(6), ?, ?, 'Goods Receipt note entry', NOW(6)
                )
            ";
            $imStmt = mysqli_prepare($conn, $insMov);

            for ($idx = 0; $idx < count($item_ids); $idx++) {
                $itemId = (int)$item_ids[$idx];
                $recvQty = (float)$received_quantities[$idx];

                if ($recvQty < 0) {
                    throw new Exception("Received quantity cannot be negative.");
                }

                // Fetch current item details (unit cost and product ID)
                $detQ = "SELECT product_id, unit_cost FROM purchase_items WHERE id = ? AND purchase_id = ? LIMIT 1";
                $dStmt = mysqli_prepare($conn, $detQ);
                mysqli_stmt_bind_param($dStmt, 'ii', $itemId, $purchaseId);
                mysqli_stmt_execute($dStmt);
                $itemDet = mysqli_fetch_assoc(mysqli_stmt_get_result($dStmt));

                if (!$itemDet) {
                    throw new Exception("Line item not found.");
                }

                $productId = $itemDet['product_id'];
                $unitCost = $itemDet['unit_cost'];
                $line_total = $recvQty * $unitCost;
                $new_subtotal += $line_total;

                // Update purchase item quantity
                mysqli_stmt_bind_param($uiStmt, 'ddii', $recvQty, $line_total, $itemId, $purchaseId);
                mysqli_stmt_execute($uiStmt);

                // Update inventory balance WAVG
                if ($recvQty > 0) {
                    $balQ = "SELECT id, quantity_on_hand, average_unit_cost FROM inventory_balances WHERE business_id = ? AND location_id = ? AND product_id = ? LIMIT 1 FOR UPDATE";
                    $bStmt = mysqli_prepare($conn, $balQ);
                    mysqli_stmt_bind_param($bStmt, 'iii', $businessId, $locationId, $productId);
                    mysqli_stmt_execute($bStmt);
                    $bal = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt));

                    if ($bal) {
                        $newQty = $bal['quantity_on_hand'] + $recvQty;
                        $oldVal = $bal['quantity_on_hand'] * $bal['average_unit_cost'];
                        $newVal = $recvQty * $unitCost;
                        
                        $newAvg = ($newQty > 0) ? ($oldVal + $newVal) / $newQty : 0.0;

                        $updBal = "UPDATE inventory_balances SET quantity_on_hand = ?, average_unit_cost = ?, last_calculated_at = NOW(6), updated_at = NOW(6) WHERE id = ?";
                        $ubStmt = mysqli_prepare($conn, $updBal);
                        mysqli_stmt_bind_param($ubStmt, 'ddi', $newQty, $newAvg, $bal['id']);
                        mysqli_stmt_execute($ubStmt);
                    } else {
                        $insBal = "
                            INSERT INTO inventory_balances (
                                business_id, location_id, product_id, quantity_on_hand, 
                                average_unit_cost, last_calculated_at, created_at, updated_at
                            ) VALUES (
                                ?, ?, ?, ?, 
                                ?, NOW(6), NOW(6), NOW(6)
                            )
                        ";
                        $ibStmt = mysqli_prepare($conn, $insBal);
                        mysqli_stmt_bind_param($ibStmt, 'iiidd', $businessId, $locationId, $productId, $recvQty, $unitCost);
                        mysqli_stmt_execute($ibStmt);
                    }

                    // Insert movement log
                    mysqli_stmt_bind_param($imStmt, 'iiidddi', $businessId, $locationId, $productId, $recvQty, $unitCost, $itemId, $_SESSION['membership_id']);
                    mysqli_stmt_execute($imStmt);
                }
            }

            // Update parent PO status to RECEIVED
            $updPO = "
                UPDATE purchases 
                SET status = 'RECEIVED', received_at = NOW(6), received_by_membership_id = ?, 
                    subtotal = ?, total_amount = ?, supplier_invoice_number = ?, updated_at = NOW(6) 
                WHERE id = ?
            ";
            $upStmt = mysqli_prepare($conn, $updPO);
            mysqli_stmt_bind_param($upStmt, 'iddsi', $_SESSION['membership_id'], $new_subtotal, $new_subtotal, $supplier_invoice_number, $purchaseId);
            mysqli_stmt_execute($upStmt);

            // Audit Log
            writeAuditLog($conn, $businessId, 'PURCHASE_RECEIPT_POSTED', 'purchase', $purchaseId, [
                'purchase_number' => $po['purchase_number'],
                'total_amount' => $new_subtotal
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Goods Receipt Note logged. Inventory stocks and costing recalculated.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Failed to receive PO: " . $e->getMessage());
            setFlashMessage('error', 'Error posting goods receipt: ' . $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
