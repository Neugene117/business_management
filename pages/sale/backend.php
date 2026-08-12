<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
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
$redirect = static function (string $message, string $type = 'error', string $suffix = '') use ($roleQuery): void {
    setFlashMessage($type, $message);
    $separator = $roleQuery !== '' && $suffix !== '' ? '&' : '';
    header('Location: index.php' . $roleQuery . $separator . $suffix);
    exit;
};

try {
    if ($action === 'create') {
        requirePermission($conn, $membershipId, $businessId, $permissions['create']);

        $saleNumber = trim((string)($_POST['sale_number'] ?? ''));
        $soldAtInput = trim((string)($_POST['sold_at'] ?? ''));
        $locationId = (int)($_POST['location_id'] ?? 0);
        $customerId = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        $productIds = is_array($_POST['product_ids'] ?? null) ? $_POST['product_ids'] : [];
        $quantities = is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [];
        $unitPrices = is_array($_POST['unit_prices'] ?? null) ? $_POST['unit_prices'] : [];
        $batchIds = is_array($_POST['batch_ids'] ?? null) ? $_POST['batch_ids'] : [];
        $paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH')));
        $amountPaidInput = trim((string)($_POST['amount_paid'] ?? ''));
        $paymentReference = trim((string)($_POST['payment_reference'] ?? '')) ?: null;
        $paidAtInput = trim((string)($_POST['paid_at'] ?? $soldAtInput));
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));

        if ($saleNumber === '' || $soldAtInput === '' || $locationId <= 0 || !$productIds) {
            throw new InvalidArgumentException('Sale number, date, source location, and at least one item are required.');
        }
        if (!in_array($paymentMethod, ['CASH','CARD','BANK_TRANSFER','MOBILE_MONEY','CHEQUE','CREDIT','OTHER'], true)) {
            throw new InvalidArgumentException('Select a valid payment method.');
        }

        $config = getBusinessInventoryConfig($conn, $businessId);
        $soldAt = businessLocalDateTimeToUtc($soldAtInput, $config['timezone']);
        $paidAt = businessLocalDateTimeToUtc($paidAtInput, $config['timezone']);
        $saleLocalDate = substr($soldAtInput, 0, 10);
        $canOverridePrice = hasPermission($conn, $membershipId, $businessId, 'products.update');

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'SALE_COMPLETION', [
                'sale_number'=>$saleNumber,'sold_at'=>$soldAtInput,'location_id'=>$locationId,
                'customer_id'=>$customerId,'products'=>$productIds,'quantities'=>$quantities,
                'prices'=>$unitPrices,'batches'=>$batchIds,'payment_method'=>$paymentMethod
            ]);

            $locationStmt = mysqli_prepare($conn, 'SELECT id FROM business_locations WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
            mysqli_stmt_bind_param($locationStmt, 'ii', $locationId, $businessId);
            mysqli_stmt_execute($locationStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($locationStmt))) throw new RuntimeException('Select a valid active stock location.');

            if ($customerId !== null) {
                $customerStmt = mysqli_prepare($conn, 'SELECT id FROM customers WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
                mysqli_stmt_bind_param($customerStmt, 'ii', $customerId, $businessId);
                mysqli_stmt_execute($customerStmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($customerStmt))) throw new RuntimeException('Select a valid active customer.');
            }

            $duplicate = mysqli_prepare($conn, 'SELECT id FROM sales WHERE business_id=? AND sale_number=? LIMIT 1 FOR UPDATE');
            mysqli_stmt_bind_param($duplicate, 'is', $businessId, $saleNumber);
            mysqli_stmt_execute($duplicate);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('A sale with this number already exists.');

            $activeTax = getActiveBusinessTax($conn, $businessId, true);
            $taxId = $activeTax['id'] ?? null;
            $taxName = $activeTax['name'] ?? null;
            $taxType = $activeTax['tax_type'] ?? null;
            $taxValue = $activeTax['tax_value'] ?? null;
            $status = 'COMPLETED';
            $saleStmt = mysqli_prepare($conn, 'INSERT INTO sales (business_id,location_id,customer_id,sale_number,status,sold_at,notes,cashier_membership_id,tax_id,tax_name,tax_type,tax_value,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
            mysqli_stmt_bind_param($saleStmt, 'iiissssiissd', $businessId, $locationId, $customerId, $saleNumber, $status, $soldAt, $notes, $membershipId, $taxId, $taxName, $taxType, $taxValue);
            if (!mysqli_stmt_execute($saleStmt)) throw new RuntimeException('The sale could not be created.');
            $saleId = mysqli_insert_id($conn);

            $subtotal = 0.0;
            $totalTax = 0.0;
            $totalCogs = 0.0;
            $validItems = 0;
            $overrideAudits = [];
            $saleLines = [];

            foreach ($productIds as $index => $rawProductId) {
                $productId = (int)$rawProductId;
                $quantity = (float)($quantities[$index] ?? 0);
                $submittedPrice = (float)($unitPrices[$index] ?? 0);
                $batchId = !empty($batchIds[$index]) ? (int)$batchIds[$index] : null;
                if ($productId <= 0 || $quantity <= 0) continue;

                $productStmt = mysqli_prepare($conn, 'SELECT id,name,sku,sale_price,cost_price,track_batches,track_expiry FROM products WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
                mysqli_stmt_bind_param($productStmt, 'ii', $productId, $businessId);
                mysqli_stmt_execute($productStmt);
                $product = mysqli_fetch_assoc(mysqli_stmt_get_result($productStmt));
                if (!$product) throw new RuntimeException('One of the selected products is unavailable.');

                $defaultPrice = (float)$product['sale_price'];
                $price = $submittedPrice;
                if ($price < 0) throw new InvalidArgumentException('Selling price cannot be negative.');
                if (abs($price - $defaultPrice) > 0.00005) {
                    if (!$canOverridePrice) {
                        throw new RuntimeException('You do not have permission to override the selling price for ' . $product['name'] . '.');
                    }
                    $overrideAudits[] = ['product_id'=>$productId,'product'=>$product['name'],'default_price'=>$defaultPrice,'overridden_price'=>$price];
                }

                if ((int)$product['track_batches'] === 1) {
                    if ($batchId === null) throw new RuntimeException('Select a batch/lot for ' . $product['name'] . '.');
                    $batchStmt = mysqli_prepare($conn, 'SELECT id,lot_number,expires_at FROM product_batches WHERE id=? AND business_id=? AND product_id=? LIMIT 1');
                    mysqli_stmt_bind_param($batchStmt, 'iii', $batchId, $businessId, $productId);
                    mysqli_stmt_execute($batchStmt);
                    $batch = mysqli_fetch_assoc(mysqli_stmt_get_result($batchStmt));
                    if (!$batch) throw new RuntimeException('The selected batch is invalid for ' . $product['name'] . '.');
                    if ((int)$product['track_expiry'] === 1 && $batch['expires_at'] !== null && $batch['expires_at'] < $saleLocalDate) {
                        throw new RuntimeException('Expired batch ' . $batch['lot_number'] . ' cannot be sold.');
                    }
                } else {
                    $batchId = null;
                }

                $balance = lockInventoryBalance($conn, $businessId, $locationId, $productId);
                if (!(int)$config['allow_negative_stock'] && (float)$balance['available_quantity'] + 0.00005 < $quantity) {
                    throw new RuntimeException('Insufficient stock for ' . $product['name'] . '. Requested: ' . number_format($quantity, 4, '.', '') . '; Available: ' . number_format((float)$balance['available_quantity'], 4, '.', ''));
                }

                $fifo = null;
                if ($config['inventory_valuation_method'] === 'FIFO') {
                    $fifo = consumeFifoCost($conn, $businessId, $locationId, $productId, $quantity, $batchId);
                    $unitCost = $fifo['unit_cost'];
                    $cogs = $fifo['total_cost'];
                } else {
                    $unitCost = (float)$balance['average_unit_cost'];
                    if ($unitCost <= 0) $unitCost = (float)$product['cost_price'];
                    $cogs = $quantity * $unitCost;
                }

                $netSales = $quantity * $price;
                $lineTax = $activeTax && $activeTax['tax_type'] === 'PERCENTAGE'
                    ? calculateSaleTax($netSales, $activeTax)
                    : 0.0;
                $lineTotal = $netSales + $lineTax;
                $itemStmt = mysqli_prepare($conn, 'INSERT INTO sale_items (business_id,sale_id,product_id,batch_id,quantity,unit_price,discount_amount,tax_amount,net_sales_amount,line_total,unit_cost_at_sale,cogs_total,created_at) VALUES (?,?,?,?,?,?,0,?,?,?,?,?,UTC_TIMESTAMP(6))');
                mysqli_stmt_bind_param($itemStmt, 'iiiiddddddd', $businessId, $saleId, $productId, $batchId, $quantity, $price, $lineTax, $netSales, $lineTotal, $unitCost, $cogs);
                if (!mysqli_stmt_execute($itemStmt)) throw new RuntimeException('A sale item could not be saved.');
                $saleItemId = mysqli_insert_id($conn);
                $saleLines[] = ['id'=>$saleItemId, 'net_sales'=>$netSales];

                if ($fifo !== null) {
                    foreach ($fifo['allocations'] as $allocation) {
                        $allocationStmt = mysqli_prepare($conn, 'INSERT INTO inventory_cost_allocations (business_id,sale_item_id,cost_layer_id,quantity,unit_cost,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(6))');
                        mysqli_stmt_bind_param($allocationStmt, 'iiidd', $businessId, $saleItemId, $allocation['layer_id'], $allocation['quantity'], $allocation['unit_cost']);
                        if (!mysqli_stmt_execute($allocationStmt)) throw new RuntimeException('FIFO cost allocation could not be saved.');
                    }
                }

                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$locationId,'product_id'=>$productId,'batch_id'=>$batchId,
                    'movement_type'=>'SALE','quantity_delta'=>-$quantity,'unit_cost'=>$unitCost,'occurred_at'=>$soldAt,
                    'sale_item_id'=>$saleItemId,'created_by_membership_id'=>$membershipId,'notes'=>$saleNumber,'config'=>$config
                ]);
                $subtotal += $netSales;
                $totalTax += $lineTax;
                $totalCogs += $cogs;
                $validItems++;
            }

            if ($validItems === 0) throw new InvalidArgumentException('Add at least one valid sale item.');
            if ($activeTax && $activeTax['tax_type'] === 'FIXED') {
                $totalTax = calculateSaleTax($subtotal, $activeTax);
                $lineAmounts = array_column($saleLines, 'net_sales');
                $taxAllocations = allocateSaleTax($lineAmounts, $totalTax);
                $updateLineTax = mysqli_prepare($conn, 'UPDATE sale_items SET tax_amount=?,line_total=net_sales_amount+? WHERE id=? AND business_id=?');
                foreach ($saleLines as $lineIndex => $saleLine) {
                    $allocatedTax = (float)($taxAllocations[$lineIndex] ?? 0.0);
                    mysqli_stmt_bind_param($updateLineTax, 'ddii', $allocatedTax, $allocatedTax, $saleLine['id'], $businessId);
                    if (!mysqli_stmt_execute($updateLineTax)) throw new RuntimeException('Fixed tax could not be allocated to sale items.');
                }
            }
            $totalAmount = $subtotal + $totalTax;
            $amountPaid = $amountPaidInput === '' ? ($paymentMethod === 'CREDIT' ? 0.0 : $totalAmount) : (float)$amountPaidInput;
            if ($amountPaid < 0 || $amountPaid > $totalAmount + 0.00005) throw new InvalidArgumentException('Amount paid must be between zero and the sale total.');

            if ($amountPaid > 0) {
                $paymentStmt = mysqli_prepare($conn, 'INSERT INTO sale_payments (business_id,sale_id,amount,payment_method,reference_number,paid_at,recorded_by_membership_id,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(6))');
                mysqli_stmt_bind_param($paymentStmt, 'iidsssi', $businessId, $saleId, $amountPaid, $paymentMethod, $paymentReference, $paidAt, $membershipId);
                if (!mysqli_stmt_execute($paymentStmt)) throw new RuntimeException('The sale payment could not be recorded.');
            }

            $totalsStmt = mysqli_prepare($conn, 'UPDATE sales SET subtotal=?,tax_amount=?,total_amount=?,total_cogs=?,amount_paid=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($totalsStmt, 'dddddii', $subtotal, $totalTax, $totalAmount, $totalCogs, $amountPaid, $saleId, $businessId);
            if (!mysqli_stmt_execute($totalsStmt)) throw new RuntimeException('Sale totals could not be finalized.');

            foreach ($overrideAudits as $override) {
                $override['sale_id'] = $saleId;
                writeAuditLog($conn, $businessId, 'SALE_PRICE_OVERRIDDEN', 'sale', $saleId, $override);
            }
            writeAuditLog($conn, $businessId, 'SALE_COMPLETED', 'sale', $saleId, [
                'sale_number'=>$saleNumber,'items'=>$validItems,'total_amount'=>$totalAmount,
                'total_cogs'=>$totalCogs,'amount_paid'=>$amountPaid,'outstanding'=>max(0, $totalAmount - $amountPaid),
                'tax'=>$activeTax ? ['id'=>$activeTax['id'],'name'=>$activeTax['name'],'type'=>$activeTax['tax_type'],'value'=>$activeTax['tax_value'],'amount'=>$totalTax] : null
            ]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['sale_id'=>$saleId,'sale_number'=>$saleNumber]);
            mysqli_commit($conn);
            $redirect('Sale completed and inventory updated.', 'success', 'sale_completed=1');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'return_sale') {
        requirePermission($conn, $membershipId, $businessId, $permissions['refund']);
        $saleId = (int)($_POST['sale_id'] ?? 0);
        $returnNumber = trim((string)($_POST['return_number'] ?? ''));
        $returnedAtInput = trim((string)($_POST['returned_at'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? '')) ?: null;
        $itemIds = is_array($_POST['sale_item_ids'] ?? null) ? $_POST['sale_item_ids'] : [];
        $returnQuantities = is_array($_POST['return_quantities'] ?? null) ? $_POST['return_quantities'] : [];
        $dispositions = is_array($_POST['dispositions'] ?? null) ? $_POST['dispositions'] : [];
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($saleId <= 0 || $returnNumber === '' || $returnedAtInput === '' || !$itemIds) throw new InvalidArgumentException('Return number, date, sale, and returned items are required.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        $returnedAt = businessLocalDateTimeToUtc($returnedAtInput, $config['timezone']);

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'SALE_RETURN', ['sale_id'=>$saleId,'return_number'=>$returnNumber,'items'=>$itemIds,'quantities'=>$returnQuantities]);
            $saleStmt = mysqli_prepare($conn, "SELECT id,location_id,sale_number,status FROM sales WHERE id=? AND business_id=? FOR UPDATE");
            mysqli_stmt_bind_param($saleStmt, 'ii', $saleId, $businessId);
            mysqli_stmt_execute($saleStmt);
            $sale = mysqli_fetch_assoc(mysqli_stmt_get_result($saleStmt));
            if (!$sale || !in_array($sale['status'], ['COMPLETED','PARTIALLY_REFUNDED'], true)) throw new RuntimeException('Only completed sales can receive a return.');

            $returnStmt = mysqli_prepare($conn, "INSERT INTO sale_returns (business_id,sale_id,return_number,returned_at,reason,status,refund_amount,created_by_membership_id,created_at) VALUES (?,?,?,?,?,'COMPLETED',0,?,UTC_TIMESTAMP(6))");
            mysqli_stmt_bind_param($returnStmt, 'iisssi', $businessId, $saleId, $returnNumber, $returnedAt, $reason, $membershipId);
            if (!mysqli_stmt_execute($returnStmt)) throw new RuntimeException('The sale return could not be created.');
            $returnId = mysqli_insert_id($conn);
            $refundAmount = 0.0;
            $returnedCount = 0;
            $restockedCount = 0;
            $refundOnlyCount = 0;

            foreach ($itemIds as $index => $rawItemId) {
                $saleItemId = (int)$rawItemId;
                $quantity = (float)($returnQuantities[$index] ?? 0);
                $restock = ($dispositions[$index] ?? 'RESTOCK') === 'RESTOCK';
                if ($saleItemId <= 0 || $quantity <= 0) continue;
                $itemStmt = mysqli_prepare($conn, "SELECT si.product_id,si.batch_id,si.quantity,si.unit_price,si.tax_amount,si.unit_cost_at_sale,COALESCE((SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'),0) returned_quantity FROM sale_items si WHERE si.id=? AND si.sale_id=? AND si.business_id=? FOR UPDATE");
                mysqli_stmt_bind_param($itemStmt, 'iii', $saleItemId, $saleId, $businessId);
                mysqli_stmt_execute($itemStmt);
                $item = mysqli_fetch_assoc(mysqli_stmt_get_result($itemStmt));
                if (!$item) throw new RuntimeException('A selected sale item is invalid.');
                $remainingReturnable = (float)$item['quantity'] - (float)$item['returned_quantity'];
                if ($quantity > $remainingReturnable + 0.00005) throw new RuntimeException('Returned quantity exceeds the quantity still returnable.');
                $lineTaxRefund = (float)$item['quantity'] > 0
                    ? ($quantity / (float)$item['quantity']) * (float)$item['tax_amount']
                    : 0.0;
                $lineTotal = ($quantity * (float)$item['unit_price']) + $lineTaxRefund;
                $returnItemStmt = mysqli_prepare($conn, 'INSERT INTO sale_return_items (business_id,sale_return_id,sale_item_id,product_id,batch_id,quantity,unit_price,unit_cost_at_sale,line_total) VALUES (?,?,?,?,?,?,?,?,?)');
                mysqli_stmt_bind_param($returnItemStmt, 'iiiiidddd', $businessId, $returnId, $saleItemId, $item['product_id'], $item['batch_id'], $quantity, $item['unit_price'], $item['unit_cost_at_sale'], $lineTotal);
                if (!mysqli_stmt_execute($returnItemStmt)) throw new RuntimeException('A returned item could not be saved.');
                $returnItemId = mysqli_insert_id($conn);
                if ($restock) {
                    applyInventoryMovement($conn, [
                        'business_id'=>$businessId,'location_id'=>$sale['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                        'movement_type'=>'SALE_RETURN','quantity_delta'=>$quantity,'unit_cost'=>$item['unit_cost_at_sale'],'occurred_at'=>$returnedAt,
                        'sale_return_item_id'=>$returnItemId,'created_by_membership_id'=>$membershipId,'notes'=>$returnNumber . ' / ' . $sale['sale_number'],'config'=>$config
                    ]);
                    $restockedCount++;
                } else {
                    $refundOnlyCount++;
                }
                $refundAmount += $lineTotal;
                $returnedCount++;
            }
            if ($returnedCount === 0) throw new InvalidArgumentException('Enter at least one returned quantity.');
            $updateReturn = mysqli_prepare($conn, 'UPDATE sale_returns SET refund_amount=? WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($updateReturn, 'dii', $refundAmount, $returnId, $businessId);
            mysqli_stmt_execute($updateReturn);

            $statusStmt = mysqli_prepare($conn, "SELECT COUNT(*) incomplete FROM sale_items si WHERE si.sale_id=? AND si.business_id=? AND si.quantity > COALESCE((SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'),0)");
            mysqli_stmt_bind_param($statusStmt, 'ii', $saleId, $businessId);
            mysqli_stmt_execute($statusStmt);
            $newStatus = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($statusStmt))['incomplete'] === 0 ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
            $saleUpdate = mysqli_prepare($conn, 'UPDATE sales SET status=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($saleUpdate, 'sii', $newStatus, $saleId, $businessId);
            mysqli_stmt_execute($saleUpdate);
            writeAuditLog($conn, $businessId, 'SALE_RETURNED', 'sale_return', $returnId, ['sale_id'=>$saleId,'return_number'=>$returnNumber,'refund_amount'=>$refundAmount,'sale_status'=>$newStatus,'restocked_lines'=>$restockedCount,'refund_only_lines'=>$refundOnlyCount]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['sale_return_id'=>$returnId]);
            mysqli_commit($conn);
            $redirect('Sale return completed. Only items marked usable were restored to stock.', 'success', 'view=history');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'void_sale') {
        requirePermission($conn, $membershipId, $businessId, $permissions['void']);
        $saleId = (int)($_POST['sale_id'] ?? 0);
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($saleId <= 0) throw new InvalidArgumentException('Invalid sale reference.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        $occurredAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'SALE_VOID', ['sale_id'=>$saleId]);
            $saleStmt = mysqli_prepare($conn, 'SELECT id,location_id,sale_number,status,amount_paid FROM sales WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($saleStmt, 'ii', $saleId, $businessId);
            mysqli_stmt_execute($saleStmt);
            $sale = mysqli_fetch_assoc(mysqli_stmt_get_result($saleStmt));
            if (!$sale) throw new RuntimeException('Sale record not found.');
            if ($sale['status'] !== 'COMPLETED') throw new RuntimeException('Only an unreturned completed sale can be voided.');

            $itemsStmt = mysqli_prepare($conn, 'SELECT id,product_id,batch_id,quantity,unit_cost_at_sale FROM sale_items WHERE sale_id=? AND business_id=?');
            mysqli_stmt_bind_param($itemsStmt, 'ii', $saleId, $businessId);
            mysqli_stmt_execute($itemsStmt);
            $items = mysqli_stmt_get_result($itemsStmt);
            while ($item = mysqli_fetch_assoc($items)) {
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$sale['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                    'movement_type'=>'CORRECTION_IN','quantity_delta'=>$item['quantity'],'unit_cost'=>$item['unit_cost_at_sale'],'occurred_at'=>$occurredAt,
                    'created_by_membership_id'=>$membershipId,'notes'=>'Void reversal for ' . $sale['sale_number'],'config'=>$config
                ]);
            }
            $update = mysqli_prepare($conn, "UPDATE sales SET status='VOIDED',updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?");
            mysqli_stmt_bind_param($update, 'ii', $saleId, $businessId);
            mysqli_stmt_execute($update);
            writeAuditLog($conn, $businessId, 'SALE_VOIDED', 'sale', $saleId, ['sale_number'=>$sale['sale_number'],'stock_reversal'=>'CORRECTION_IN','historical_payments_preserved'=>(float)$sale['amount_paid']]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 200, ['sale_id'=>$saleId,'status'=>'VOIDED']);
            mysqli_commit($conn);
            $redirect('Sale voided. Stock was reversed and the financial history was preserved.', 'success', 'view=history');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    throw new InvalidArgumentException('Invalid action.');
} catch (Throwable $error) {
    error_log('Sale operation failed: ' . $error->getMessage());
    $redirect($error->getMessage());
}
?>
