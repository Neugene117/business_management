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
    header('Location: index.php');
    exit;
}
validateCsrfToken($_POST['csrf_token'] ?? '');

$action = (string)($_POST['action'] ?? '');
$businessId = (int)$_SESSION['active_business_id'];
$membershipId = (int)$_SESSION['membership_id'];
$roleQuery = isset($_GET['role']) ? '?role=' . rawurlencode((string)$_GET['role']) : '';
$finish = static function (string $message, string $type = 'error') use ($roleQuery): void {
    setFlashMessage($type, $message);
    header('Location: index.php' . $roleQuery);
    exit;
};

try {
    if ($action === 'create') {
        requirePermission($conn, $membershipId, $businessId, $permissions['create']);
        $purchaseNumber = trim((string)($_POST['purchase_number'] ?? ''));
        $purchaseDateInput = trim((string)($_POST['purchase_date'] ?? ''));
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $locationId = (int)($_POST['location_id'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        $productIds = is_array($_POST['product_ids'] ?? null) ? $_POST['product_ids'] : [];
        $quantities = is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [];
        $unitCosts = is_array($_POST['unit_costs'] ?? null) ? $_POST['unit_costs'] : [];
        $expiryDates = is_array($_POST['expiry_dates'] ?? null) ? $_POST['expiry_dates'] : [];
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($purchaseNumber === '' || $purchaseDateInput === '' || $supplierId <= 0 || $locationId <= 0 || !$productIds) {
            throw new InvalidArgumentException('PO number, date, supplier, target location, and at least one item are required.');
        }
        $config = getBusinessInventoryConfig($conn, $businessId);
        $purchaseDate = businessLocalDateTimeToUtc($purchaseDateInput, $config['timezone']);

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'PURCHASE_CREATE', ['purchase_number'=>$purchaseNumber,'date'=>$purchaseDateInput,'supplier_id'=>$supplierId,'location_id'=>$locationId,'products'=>$productIds,'quantities'=>$quantities,'costs'=>$unitCosts]);
            $supplierStmt = mysqli_prepare($conn, 'SELECT id FROM suppliers WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
            mysqli_stmt_bind_param($supplierStmt, 'ii', $supplierId, $businessId);
            mysqli_stmt_execute($supplierStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($supplierStmt))) throw new RuntimeException('Select a valid active supplier.');
            $locationStmt = mysqli_prepare($conn, 'SELECT id FROM business_locations WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
            mysqli_stmt_bind_param($locationStmt, 'ii', $locationId, $businessId);
            mysqli_stmt_execute($locationStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($locationStmt))) throw new RuntimeException('Select a valid active location.');
            $duplicate = mysqli_prepare($conn, 'SELECT id FROM purchases WHERE business_id=? AND purchase_number=? FOR UPDATE');
            mysqli_stmt_bind_param($duplicate, 'is', $businessId, $purchaseNumber);
            mysqli_stmt_execute($duplicate);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('A purchase order with this number already exists.');

            $purchaseStmt = mysqli_prepare($conn, "INSERT INTO purchases (business_id,location_id,supplier_id,purchase_number,status,purchase_date,notes,created_by_membership_id,created_at,updated_at) VALUES (?,?,?,?,'DRAFT',?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
            mysqli_stmt_bind_param($purchaseStmt, 'iiisssi', $businessId, $locationId, $supplierId, $purchaseNumber, $purchaseDate, $notes, $membershipId);
            if (!mysqli_stmt_execute($purchaseStmt)) throw new RuntimeException('The purchase order could not be created.');
            $purchaseId = mysqli_insert_id($conn);
            $subtotal = 0.0;
            $validItems = 0;

            foreach ($productIds as $index => $rawProductId) {
                $productId = (int)$rawProductId;
                $quantity = (float)($quantities[$index] ?? 0);
                $unitCost = (float)($unitCosts[$index] ?? 0);
                if ($productId <= 0 || $quantity <= 0 || $unitCost < 0) continue;
                $productStmt = mysqli_prepare($conn, 'SELECT id,name,track_batches,track_expiry FROM products WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
                mysqli_stmt_bind_param($productStmt, 'ii', $productId, $businessId);
                mysqli_stmt_execute($productStmt);
                $product = mysqli_fetch_assoc(mysqli_stmt_get_result($productStmt));
                if (!$product) throw new RuntimeException('One selected product is unavailable.');

                $batchId = null;
                if ((int)$product['track_batches'] === 1) {
                    $expiryDate = trim((string)($expiryDates[$index] ?? '')) ?: null;
                    if ((int)$product['track_expiry'] === 1 && $expiryDate === null) throw new RuntimeException('Enter an expiry date for ' . $product['name'] . '.');

                    // Batch numbers are server-generated and cannot be supplied or
                    // overridden by the browser. Purchase ID + line position keeps
                    // the number stable, readable, and unique for this product.
                    $lotNumber = sprintf('LOT-%d-%d-%d-%d', $businessId, $productId, $purchaseId, $index + 1);
                    $batchInsert = mysqli_prepare($conn, 'INSERT INTO product_batches (business_id,product_id,lot_number,expires_at,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP(6))');
                    mysqli_stmt_bind_param($batchInsert, 'iiss', $businessId, $productId, $lotNumber, $expiryDate);
                    if (!mysqli_stmt_execute($batchInsert)) throw new RuntimeException('The automatic product batch could not be created.');
                    $batchId = mysqli_insert_id($conn);
                }

                $lineTotal = $quantity * $unitCost;
                $itemStmt = mysqli_prepare($conn, 'INSERT INTO purchase_items (business_id,purchase_id,product_id,batch_id,ordered_quantity,received_quantity,unit_cost,discount_amount,tax_amount,line_total,created_at) VALUES (?,?,?,?,?,0,?,0,0,?,UTC_TIMESTAMP(6))');
                mysqli_stmt_bind_param($itemStmt, 'iiiiddd', $businessId, $purchaseId, $productId, $batchId, $quantity, $unitCost, $lineTotal);
                if (!mysqli_stmt_execute($itemStmt)) throw new RuntimeException('A purchase item could not be saved.');
                $subtotal += $lineTotal;
                $validItems++;
            }
            if ($validItems === 0) throw new InvalidArgumentException('Add at least one valid purchase item.');
            $totals = mysqli_prepare($conn, 'UPDATE purchases SET subtotal=?,total_amount=? WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($totals, 'ddii', $subtotal, $subtotal, $purchaseId, $businessId);
            mysqli_stmt_execute($totals);
            writeAuditLog($conn, $businessId, 'PURCHASE_ORDER_CREATED', 'purchase', $purchaseId, ['purchase_number'=>$purchaseNumber,'total_amount'=>$subtotal,'items'=>$validItems]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['purchase_id'=>$purchaseId]);
            mysqli_commit($conn);
            $finish('Purchase order created in Draft.', 'success');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'mark_ordered') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE purchases SET status='ORDERED',approved_by_membership_id=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=? AND status='DRAFT'");
        mysqli_stmt_bind_param($stmt, 'iii', $membershipId, $purchaseId, $businessId);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('Only a Draft purchase order can be marked Ordered.');
        writeAuditLog($conn, $businessId, 'PURCHASE_ORDER_SENT', 'purchase', $purchaseId, ['status'=>'ORDERED']);
        $finish('Purchase order marked Ordered.', 'success');
    }

    if ($action === 'receive_po') {
        requirePermission($conn, $membershipId, $businessId, $permissions['receive']);
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $supplierInvoice = trim((string)($_POST['supplier_invoice_number'] ?? '')) ?: null;
        $receivedAtInput = trim((string)($_POST['received_at'] ?? ''));
        $itemIds = is_array($_POST['item_ids'] ?? null) ? $_POST['item_ids'] : [];
        $receivedQuantities = is_array($_POST['received_quantities'] ?? null) ? $_POST['received_quantities'] : [];
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($purchaseId <= 0 || !$itemIds) throw new InvalidArgumentException('Select a purchase and at least one receipt line.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        $receivedAt = $receivedAtInput !== '' ? businessLocalDateTimeToUtc($receivedAtInput, $config['timezone']) : (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'PURCHASE_RECEIPT', ['purchase_id'=>$purchaseId,'items'=>$itemIds,'quantities'=>$receivedQuantities,'supplier_invoice'=>$supplierInvoice]);
            $purchaseStmt = mysqli_prepare($conn, 'SELECT id,location_id,purchase_number,status FROM purchases WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($purchaseStmt);
            $purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));
            if (!$purchase || !in_array($purchase['status'], ['ORDERED','PARTIALLY_RECEIVED'], true)) throw new RuntimeException('Only Ordered or Partially Received purchase orders can be received.');

            $receivedLines = 0;
            $receivedValue = 0.0;
            foreach ($itemIds as $index => $rawItemId) {
                $itemId = (int)$rawItemId;
                $receiveNow = (float)($receivedQuantities[$index] ?? 0);
                if ($receiveNow < 0) throw new InvalidArgumentException('Received quantity cannot be negative.');
                if ($receiveNow == 0.0) continue;
                $itemStmt = mysqli_prepare($conn, 'SELECT pi.product_id,pi.batch_id,pi.ordered_quantity,pi.received_quantity,pi.unit_cost,p.name FROM purchase_items pi JOIN products p ON p.id=pi.product_id AND p.business_id=pi.business_id WHERE pi.id=? AND pi.purchase_id=? AND pi.business_id=? FOR UPDATE');
                mysqli_stmt_bind_param($itemStmt, 'iii', $itemId, $purchaseId, $businessId);
                mysqli_stmt_execute($itemStmt);
                $item = mysqli_fetch_assoc(mysqli_stmt_get_result($itemStmt));
                if (!$item) throw new RuntimeException('A purchase line is invalid.');
                $remaining = (float)$item['ordered_quantity'] - (float)$item['received_quantity'];
                if ($receiveNow > $remaining + 0.00005) throw new RuntimeException('Receipt for ' . $item['name'] . ' exceeds the remaining ordered quantity of ' . number_format($remaining, 4, '.', '') . '.');
                $newReceived = (float)$item['received_quantity'] + $receiveNow;
                $updateItem = mysqli_prepare($conn, 'UPDATE purchase_items SET received_quantity=? WHERE id=? AND purchase_id=? AND business_id=?');
                mysqli_stmt_bind_param($updateItem, 'diii', $newReceived, $itemId, $purchaseId, $businessId);
                mysqli_stmt_execute($updateItem);
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$purchase['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                    'movement_type'=>'PURCHASE_RECEIPT','quantity_delta'=>$receiveNow,'unit_cost'=>$item['unit_cost'],'occurred_at'=>$receivedAt,
                    'purchase_item_id'=>$itemId,'created_by_membership_id'=>$membershipId,'notes'=>$purchase['purchase_number'] . ' goods receipt','config'=>$config
                ]);
                $receivedValue += $receiveNow * (float)$item['unit_cost'];
                $receivedLines++;
            }
            if ($receivedLines === 0) throw new InvalidArgumentException('Enter a received quantity for at least one item.');

            $remainingStmt = mysqli_prepare($conn, 'SELECT COUNT(*) remaining_lines FROM purchase_items WHERE purchase_id=? AND business_id=? AND received_quantity < ordered_quantity');
            mysqli_stmt_bind_param($remainingStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($remainingStmt);
            $remainingLines = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($remainingStmt))['remaining_lines'];
            $newStatus = $remainingLines === 0 ? 'RECEIVED' : 'PARTIALLY_RECEIVED';
            if ($newStatus === 'RECEIVED') {
                $updatePurchase = mysqli_prepare($conn, 'UPDATE purchases SET status=?,received_at=?,received_by_membership_id=?,supplier_invoice_number=COALESCE(?,supplier_invoice_number),updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
                mysqli_stmt_bind_param($updatePurchase, 'ssisii', $newStatus, $receivedAt, $membershipId, $supplierInvoice, $purchaseId, $businessId);
            } else {
                $updatePurchase = mysqli_prepare($conn, 'UPDATE purchases SET status=?,received_by_membership_id=?,supplier_invoice_number=COALESCE(?,supplier_invoice_number),updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
                mysqli_stmt_bind_param($updatePurchase, 'sisii', $newStatus, $membershipId, $supplierInvoice, $purchaseId, $businessId);
            }
            if (!mysqli_stmt_execute($updatePurchase)) throw new RuntimeException('Purchase receipt status could not be updated.');
            writeAuditLog($conn, $businessId, 'PURCHASE_RECEIVED', 'purchase', $purchaseId, ['purchase_number'=>$purchase['purchase_number'],'receipt_value'=>$receivedValue,'lines'=>$receivedLines,'status'=>$newStatus]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 200, ['purchase_id'=>$purchaseId,'status'=>$newStatus]);
            mysqli_commit($conn);
            $finish($newStatus === 'RECEIVED' ? 'Purchase fully received and stock updated.' : 'Partial receipt posted. Remaining quantities are still open.', 'success');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'return_purchase') {
        requirePermission($conn, $membershipId, $businessId, $permissions['receive']);
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $returnNumber = trim((string)($_POST['return_number'] ?? ''));
        $returnDateInput = trim((string)($_POST['return_date'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? '')) ?: null;
        $itemIds = is_array($_POST['item_ids'] ?? null) ? $_POST['item_ids'] : [];
        $quantities = is_array($_POST['return_quantities'] ?? null) ? $_POST['return_quantities'] : [];
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($purchaseId <= 0 || $returnNumber === '' || $returnDateInput === '' || !$itemIds) throw new InvalidArgumentException('Purchase, return number, return date, and at least one item are required.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        $returnDate = businessLocalDateTimeToUtc($returnDateInput, $config['timezone']);
        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'PURCHASE_RETURN', ['purchase_id'=>$purchaseId,'return_number'=>$returnNumber,'items'=>$itemIds,'quantities'=>$quantities]);
            $purchaseStmt = mysqli_prepare($conn, "SELECT id,location_id,purchase_number,status FROM purchases WHERE id=? AND business_id=? AND status IN ('PARTIALLY_RECEIVED','RECEIVED') FOR UPDATE");
            mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($purchaseStmt);
            $purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));
            if (!$purchase) throw new RuntimeException('Only received purchase stock can be returned.');
            $returnStmt = mysqli_prepare($conn, "INSERT INTO purchase_returns (business_id,purchase_id,return_number,return_date,reason,status,created_by_membership_id,created_at) VALUES (?,?,?,?,?,'COMPLETED',?,UTC_TIMESTAMP(6))");
            mysqli_stmt_bind_param($returnStmt, 'iisssi', $businessId, $purchaseId, $returnNumber, $returnDate, $reason, $membershipId);
            if (!mysqli_stmt_execute($returnStmt)) throw new RuntimeException('The purchase return could not be created.');
            $purchaseReturnId = mysqli_insert_id($conn);
            $returnValue = 0.0;
            $returnedLines = 0;
            foreach ($itemIds as $index => $rawItemId) {
                $itemId = (int)$rawItemId;
                $quantity = (float)($quantities[$index] ?? 0);
                if ($itemId <= 0 || $quantity <= 0) continue;
                $itemStmt = mysqli_prepare($conn, "SELECT pi.product_id,pi.batch_id,pi.received_quantity,pi.unit_cost,p.name,COALESCE((SELECT SUM(pri.quantity) FROM purchase_return_items pri JOIN purchase_returns pr ON pr.id=pri.purchase_return_id WHERE pri.purchase_item_id=pi.id AND pr.status='COMPLETED'),0) returned_quantity FROM purchase_items pi JOIN products p ON p.id=pi.product_id AND p.business_id=pi.business_id WHERE pi.id=? AND pi.purchase_id=? AND pi.business_id=? FOR UPDATE");
                mysqli_stmt_bind_param($itemStmt, 'iii', $itemId, $purchaseId, $businessId);
                mysqli_stmt_execute($itemStmt);
                $item = mysqli_fetch_assoc(mysqli_stmt_get_result($itemStmt));
                if (!$item) throw new RuntimeException('A purchase return item is invalid.');
                $returnable = (float)$item['received_quantity'] - (float)$item['returned_quantity'];
                if ($quantity > $returnable + .00005) throw new RuntimeException('Return for ' . $item['name'] . ' exceeds the received quantity still returnable.');
                $unitCost = (float)$item['unit_cost'];
                if ($config['inventory_valuation_method'] === 'FIFO') {
                    $fifo = consumeFifoCost($conn, $businessId, (int)$purchase['location_id'], (int)$item['product_id'], $quantity, !empty($item['batch_id']) ? (int)$item['batch_id'] : null);
                    $unitCost = $fifo['unit_cost'];
                }
                $lineTotal = $quantity * $unitCost;
                $returnItemStmt = mysqli_prepare($conn, 'INSERT INTO purchase_return_items (business_id,purchase_return_id,purchase_item_id,product_id,batch_id,quantity,unit_cost,line_total) VALUES (?,?,?,?,?,?,?,?)');
                mysqli_stmt_bind_param($returnItemStmt, 'iiiiiddd', $businessId, $purchaseReturnId, $itemId, $item['product_id'], $item['batch_id'], $quantity, $unitCost, $lineTotal);
                if (!mysqli_stmt_execute($returnItemStmt)) throw new RuntimeException('A purchase return line could not be saved.');
                $returnItemId = mysqli_insert_id($conn);
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$purchase['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                    'movement_type'=>'PURCHASE_RETURN','quantity_delta'=>-$quantity,'unit_cost'=>$unitCost,'occurred_at'=>$returnDate,
                    'purchase_return_item_id'=>$returnItemId,'created_by_membership_id'=>$membershipId,'notes'=>$returnNumber . ' / ' . $purchase['purchase_number'],'config'=>$config
                ]);
                $returnValue += $lineTotal;
                $returnedLines++;
            }
            if ($returnedLines === 0) throw new InvalidArgumentException('Enter at least one purchase return quantity.');
            writeAuditLog($conn, $businessId, 'PURCHASE_RETURNED', 'purchase_return', $purchaseReturnId, ['purchase_id'=>$purchaseId,'return_number'=>$returnNumber,'return_value'=>$returnValue,'lines'=>$returnedLines]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['purchase_return_id'=>$purchaseReturnId]);
            mysqli_commit($conn);
            $finish('Purchase return completed and stock reduced.', 'success');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    throw new InvalidArgumentException('Invalid action.');
} catch (Throwable $error) {
    error_log('Purchase operation failed: ' . $error->getMessage());
    $finish($error->getMessage());
}
?>
