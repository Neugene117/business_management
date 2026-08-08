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

        $sale_number = trim($_POST['sale_number'] ?? '');
        $sold_at = trim($_POST['sold_at'] ?? '');
        $customer_id = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : NULL;
        $location_id = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;
        $notes = trim($_POST['notes'] ?? NULL);

        $product_ids = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $unit_prices = $_POST['unit_prices'] ?? [];

        if (empty($sale_number) || empty($sold_at) || empty($location_id) || empty($product_ids)) {
            setFlashMessage('error', 'Sale number, date, source location, and at least one item are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch settings: default tax rate and negative stock toggle
        $settQ = "SELECT default_tax_rate, allow_negative_stock FROM business_accounting_settings WHERE business_id = ? LIMIT 1";
        $sStmt = mysqli_prepare($conn, $settQ);
        mysqli_stmt_bind_param($sStmt, 'i', $businessId);
        mysqli_stmt_execute($sStmt);
        $settings = mysqli_fetch_assoc(mysqli_stmt_get_result($sStmt));
        $default_tax_rate = $settings['default_tax_rate'] ?? 0.0;
        $allow_negative = $settings['allow_negative_stock'] ?? 0;

        mysqli_begin_transaction($conn);
        try {
            // Validate sale reference uniqueness
            $chkQ = "SELECT id FROM sales WHERE business_id = ? AND sale_number = ? LIMIT 1 FOR UPDATE";
            $cStmt = mysqli_prepare($conn, $chkQ);
            mysqli_stmt_bind_param($cStmt, 'is', $businessId, $sale_number);
            mysqli_stmt_execute($cStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($cStmt)) > 0) {
                throw new Exception("A Sale invoice with this reference number already exists.");
            }

            // 1. Insert parent Sale Order
            $status = 'COMPLETED';
            $insSale = "
                INSERT INTO sales (
                    business_id, location_id, customer_id, sale_number, 
                    status, sold_at, notes, cashier_membership_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(6), NOW(6))
            ";
            $pStmt = mysqli_prepare($conn, $insSale);
            mysqli_stmt_bind_param(
                $pStmt,
                'iiissssi',
                $businessId, $location_id, $customer_id, $sale_number,
                $status, $sold_at, $notes, $_SESSION['membership_id']
            );
            mysqli_stmt_execute($pStmt);
            $saleId = mysqli_insert_id($conn);

            // 2. Insert items and process stock reduction + COGS
            $insItem = "
                INSERT INTO sale_items (
                    business_id, sale_id, product_id, quantity, 
                    unit_price, discount_amount, tax_amount, net_sales_amount, 
                    line_total, unit_cost_at_sale, cogs_total, created_at
                ) VALUES (?, ?, ?, ?, ?, 0.0000, 0.0000, ?, ?, ?, ?, NOW(6))
            ";
            $iStmt = mysqli_prepare($conn, $insItem);

            $insMov = "
                INSERT INTO inventory_movements (
                    business_id, location_id, product_id, movement_type, 
                    quantity_delta, unit_cost, occurred_at, sale_item_id, created_by_membership_id, notes, created_at
                ) VALUES (
                    ?, ?, ?, 'SALE', 
                    ?, ?, NOW(6), ?, ?, 'Customer sale invoice entry', NOW(6)
                )
            ";
            $imStmt = mysqli_prepare($conn, $insMov);

            $subtotal = 0.0;
            $total_cogs = 0.0;

            for ($j = 0; $j < count($product_ids); $j++) {
                $pId = (int)$product_ids[$j];
                $qty = (float)$quantities[$j];
                $price = (float)$unit_prices[$j];

                if ($pId <= 0 || $qty <= 0 || $price < 0) {
                    continue;
                }

                // Check stock level FOR UPDATE
                $balQ = "SELECT id, quantity_on_hand, average_unit_cost FROM inventory_balances WHERE business_id = ? AND location_id = ? AND product_id = ? LIMIT 1 FOR UPDATE";
                $bStmt = mysqli_prepare($conn, $balQ);
                mysqli_stmt_bind_param($bStmt, 'iii', $businessId, $location_id, $pId);
                mysqli_stmt_execute($bStmt);
                $bal = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt));

                $currentQty = $bal ? (float)$bal['quantity_on_hand'] : 0.0;
                $costAtSale = $bal ? (float)$bal['average_unit_cost'] : 0.0;

                // If balance record doesn't exist, try to default unit cost from product cost price
                if (!$bal) {
                    $prodCostQ = "SELECT cost_price FROM products WHERE id = ? LIMIT 1";
                    $pcStmt = mysqli_prepare($conn, $prodCostQ);
                    mysqli_stmt_bind_param($pcStmt, 'i', $pId);
                    mysqli_stmt_execute($pcStmt);
                    $costAtSale = mysqli_fetch_assoc(mysqli_stmt_get_result($pcStmt))['cost_price'] ?? 0.0;
                }

                // Enforce negative stock blocker
                if (!$allow_negative && $currentQty < $qty) {
                    // Fetch product name for detail error
                    $pNameQ = "SELECT name FROM products WHERE id = ? LIMIT 1";
                    $pnStmt = mysqli_prepare($conn, $pNameQ);
                    mysqli_stmt_bind_param($pnStmt, 'i', $pId);
                    mysqli_stmt_execute($pnStmt);
                    $pName = mysqli_fetch_assoc(mysqli_stmt_get_result($pnStmt))['name'] ?? 'Product';
                    
                    throw new Exception("Sale rejected. Insufficient stock for product: '{$pName}'. Requested: {$qty}, Available: {$currentQty}");
                }

                $line_total = $qty * $price;
                $cogs_total = $qty * $costAtSale;
                
                $subtotal += $line_total;
                $total_cogs += $cogs_total;

                // Insert sale item
                mysqli_stmt_bind_param(
                    $iStmt,
                    'iiidddddd',
                    $businessId, $saleId, $pId, $qty,
                    $price, $line_total, $line_total, $costAtSale, $cogs_total
                );
                mysqli_stmt_execute($iStmt);
                $saleItemId = mysqli_insert_id($conn);

                // Update inventory balance quantity
                $newQty = $currentQty - $qty;
                if ($bal) {
                    $updBal = "UPDATE inventory_balances SET quantity_on_hand = ?, last_calculated_at = NOW(6), updated_at = NOW(6) WHERE id = ?";
                    $ubStmt = mysqli_prepare($conn, $updBal);
                    mysqli_stmt_bind_param($ubStmt, 'di', $newQty, $bal['id']);
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
                    mysqli_stmt_bind_param($ibStmt, 'iiidd', $businessId, $location_id, $pId, $newQty, $costAtSale);
                    mysqli_stmt_execute($ibStmt);
                }

                // Insert movement log
                $qtyDeltaNeg = -$qty;
                mysqli_stmt_bind_param($imStmt, 'iiidddi', $businessId, $location_id, $pId, $qtyDeltaNeg, $costAtSale, $saleItemId, $_SESSION['membership_id']);
                mysqli_stmt_execute($imStmt);
            }

            // Calculate tax and totals
            $tax_amount = $subtotal * $default_tax_rate;
            $total_amount = $subtotal + $tax_amount;

            // 3. Update totals in parent Sale
            $updSale = "
                UPDATE sales 
                SET subtotal = ?, tax_amount = ?, total_amount = ?, total_cogs = ?, amount_paid = ? 
                WHERE id = ?
            ";
            $usStmt = mysqli_prepare($conn, $updSale);
            mysqli_stmt_bind_param($usStmt, 'dddddi', $subtotal, $tax_amount, $total_amount, $total_cogs, $total_amount, $saleId);
            mysqli_stmt_execute($usStmt);

            // 4. Audit Log
            writeAuditLog($conn, $businessId, 'SALES_ORDER_COMPLETED', 'sale', $saleId, [
                'sale_number' => $sale_number,
                'total_amount' => $total_amount,
                'total_cogs' => $total_cogs
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'POS Cash Sale completed successfully. Receipts generated.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Failed to process sale order: " . $e->getMessage());
            setFlashMessage('error', $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'void_sale':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['void']);

        $saleId = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
        if (empty($saleId)) {
            setFlashMessage('error', 'Invalid sale reference.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // Lock parent sale FOR UPDATE
            $saleQ = "SELECT id, location_id, sale_number, status FROM sales WHERE id = ? AND business_id = ? LIMIT 1 FOR UPDATE";
            $sStmt = mysqli_prepare($conn, $saleQ);
            mysqli_stmt_bind_param($sStmt, 'ii', $saleId, $businessId);
            mysqli_stmt_execute($sStmt);
            $sale = mysqli_fetch_assoc(mysqli_stmt_get_result($sStmt));

            if (!$sale) {
                throw new Exception("Sale record not found.");
            }
            if ($sale['status'] !== 'COMPLETED') {
                throw new Exception("Only COMPLETED sales can be voided.");
            }

            $locationId = $sale['location_id'];

            // Fetch items to reverse stock
            $itemsQ = "SELECT id, product_id, quantity, unit_cost_at_sale FROM sale_items WHERE sale_id = ?";
            $iStmt = mysqli_prepare($conn, $itemsQ);
            mysqli_stmt_bind_param($iStmt, 'i', $saleId);
            mysqli_stmt_execute($iStmt);
            $itemsResult = mysqli_stmt_get_result($iStmt);

            $updBal = "UPDATE inventory_balances SET quantity_on_hand = quantity_on_hand + ?, last_calculated_at = NOW(6), updated_at = NOW(6) WHERE id = ?";
            $ubStmt = mysqli_prepare($conn, $updBal);

            $insMov = "
                INSERT INTO inventory_movements (
                    business_id, location_id, product_id, movement_type, 
                    quantity_delta, unit_cost, occurred_at, sale_return_item_id, created_by_membership_id, notes, created_at
                ) VALUES (
                    ?, ?, ?, 'SALE_RETURN', 
                    ?, ?, NOW(6), ?, ?, 'Voided sale replenishment', NOW(6)
                )
            ";
            $imStmt = mysqli_prepare($conn, $insMov);

            while ($it = mysqli_fetch_assoc($itemsResult)) {
                $productId = $it['product_id'];
                $qty = (float)$it['quantity'];
                $cost = (float)$it['unit_cost_at_sale'];

                // Fetch balance row to revert quantity
                $balQ = "SELECT id FROM inventory_balances WHERE business_id = ? AND location_id = ? AND product_id = ? LIMIT 1 FOR UPDATE";
                $bStmt = mysqli_prepare($conn, $balQ);
                mysqli_stmt_bind_param($bStmt, 'iii', $businessId, $locationId, $productId);
                mysqli_stmt_execute($bStmt);
                $bal = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt));

                if ($bal) {
                    mysqli_stmt_bind_param($ubStmt, 'di', $qty, $bal['id']);
                    mysqli_stmt_execute($ubStmt);
                } else {
                    // Create balance row since it was fully cleared
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
                    mysqli_stmt_bind_param($ibStmt, 'iiidd', $businessId, $locationId, $productId, $qty, $cost);
                    mysqli_stmt_execute($ibStmt);
                }

                // Log replenishment movement
                mysqli_stmt_bind_param($imStmt, 'iiidddi', $businessId, $locationId, $productId, $qty, $cost, $it['id'], $_SESSION['membership_id']);
                mysqli_stmt_execute($imStmt);
            }

            // Update parent sale status to VOIDED
            $updSale = "UPDATE sales SET status = 'VOIDED', updated_at = NOW(6) WHERE id = ?";
            $usStmt = mysqli_prepare($conn, $updSale);
            mysqli_stmt_bind_param($usStmt, 'i', $saleId);
            mysqli_stmt_execute($usStmt);

            // Audit Log
            writeAuditLog($conn, $businessId, 'SALES_ORDER_VOIDED', 'sale', $saleId, ['status' => 'VOIDED']);

            mysqli_commit($conn);
            setFlashMessage('success', 'Customer sale invoice voided. Inventory stocks replenished.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Failed to void sale: " . $e->getMessage());
            setFlashMessage('error', 'Failed to void sale invoice: ' . $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
