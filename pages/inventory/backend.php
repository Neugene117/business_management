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
$role = isset($_GET['role']) ? '&role=' . rawurlencode((string)$_GET['role']) : '';
$finish = static function (string $message, string $type = 'error', string $tab = 'adjust') use ($role): void {
    setFlashMessage($type, $message);
    header('Location: index.php?tab=' . rawurlencode($tab) . $role);
    exit;
};
$movementMap = [
    'STOCK_IN'=>'MANUAL_IN','STOCK_OUT'=>'MANUAL_OUT','GAIN'=>'STOCKTAKE_GAIN','LOSS'=>'STOCKTAKE_LOSS',
    'DAMAGED'=>'DAMAGE','EXPIRED'=>'EXPIRY','OPENING'=>'OPENING','CORRECTION_IN'=>'CORRECTION_IN','CORRECTION_OUT'=>'CORRECTION_OUT'
];
$legacyTypeMap = [
    'MANUAL_IN'=>'STOCK_IN','MANUAL_OUT'=>'STOCK_OUT','STOCKTAKE_GAIN'=>'GAIN','STOCKTAKE_LOSS'=>'LOSS',
    'DAMAGE'=>'DAMAGED','EXPIRY'=>'EXPIRED','OPENING'=>'OPENING','CORRECTION_IN'=>'CORRECTION_IN','CORRECTION_OUT'=>'CORRECTION_OUT'
];
$outboundAdjustments = ['STOCK_OUT','LOSS','DAMAGED','EXPIRED','CORRECTION_OUT'];

try {
    if ($action === 'adjust' || $action === 'create_adjustment') {
        requirePermission($conn, $membershipId, $businessId, $permissions['adjust']);
        $productId = (int)($_POST['product_id'] ?? 0);
        $locationId = (int)($_POST['location_id'] ?? 0);
        $batchId = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        $submittedType = strtoupper(trim((string)($_POST['adjustment_type'] ?? $_POST['movement_type'] ?? '')));
        $adjustmentType = $legacyTypeMap[$submittedType] ?? $submittedType;
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? 0);
        $reason = trim((string)($_POST['notes'] ?? $_POST['reason'] ?? ''));
        $number = trim((string)($_POST['adjustment_number'] ?? '')) ?: 'ADJ-' . gmdate('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $occurredInput = trim((string)($_POST['occurred_at'] ?? ''));
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($productId <= 0 || $locationId <= 0 || !isset($movementMap[$adjustmentType]) || $quantity <= 0 || $reason === '') {
            throw new InvalidArgumentException('Product, location, adjustment type, positive quantity, and reason are required.');
        }
        $config = getBusinessInventoryConfig($conn, $businessId);
        $occurredAt = $occurredInput !== '' ? businessLocalDateTimeToUtc($occurredInput, $config['timezone']) : (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'STOCK_ADJUSTMENT_CREATE', ['number'=>$number,'product_id'=>$productId,'location_id'=>$locationId,'batch_id'=>$batchId,'type'=>$adjustmentType,'quantity'=>$quantity]);
            $check = mysqli_prepare($conn, 'SELECT p.id,p.name,p.track_batches FROM products p JOIN business_locations l ON l.id=? AND l.business_id=p.business_id AND l.is_active=1 WHERE p.id=? AND p.business_id=? AND p.is_active=1 LIMIT 1');
            mysqli_stmt_bind_param($check, 'iii', $locationId, $productId, $businessId);
            mysqli_stmt_execute($check);
            $product = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            if (!$product) throw new RuntimeException('Select a valid product and active location.');
            if ((int)$product['track_batches'] === 1 && $batchId === null) throw new RuntimeException('Select a batch/lot for ' . $product['name'] . '.');
            if ($batchId !== null) {
                $batchCheck = mysqli_prepare($conn, 'SELECT id FROM product_batches WHERE id=? AND business_id=? AND product_id=? LIMIT 1');
                mysqli_stmt_bind_param($batchCheck, 'iii', $batchId, $businessId, $productId);
                mysqli_stmt_execute($batchCheck);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($batchCheck))) throw new RuntimeException('The selected batch is invalid.');
            }
            $duplicate = mysqli_prepare($conn, 'SELECT id FROM stock_adjustments WHERE business_id=? AND adjustment_number=? FOR UPDATE');
            mysqli_stmt_bind_param($duplicate, 'is', $businessId, $number);
            mysqli_stmt_execute($duplicate);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('An adjustment with this number already exists.');
            $adjustmentStmt = mysqli_prepare($conn, "INSERT INTO stock_adjustments (business_id,location_id,adjustment_number,adjustment_type,occurred_at,reason,status,created_by_membership_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'DRAFT',?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
            mysqli_stmt_bind_param($adjustmentStmt, 'iissssi', $businessId, $locationId, $number, $adjustmentType, $occurredAt, $reason, $membershipId);
            if (!mysqli_stmt_execute($adjustmentStmt)) throw new RuntimeException('The stock adjustment could not be created.');
            $adjustmentId = mysqli_insert_id($conn);
            $itemStmt = mysqli_prepare($conn, 'INSERT INTO stock_adjustment_items (business_id,stock_adjustment_id,product_id,batch_id,quantity,unit_cost,notes) VALUES (?,?,?,?,?,?,?)');
            mysqli_stmt_bind_param($itemStmt, 'iiiidds', $businessId, $adjustmentId, $productId, $batchId, $quantity, $unitCost, $reason);
            if (!mysqli_stmt_execute($itemStmt)) throw new RuntimeException('The adjustment item could not be saved.');
            writeAuditLog($conn, $businessId, 'STOCK_ADJUSTMENT_CREATED', 'stock_adjustment', $adjustmentId, ['adjustment_number'=>$number,'type'=>$adjustmentType,'quantity'=>$quantity,'status'=>'DRAFT']);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['stock_adjustment_id'=>$adjustmentId]);
            mysqli_commit($conn);
            $finish('Stock adjustment saved as Draft. Stock has not changed until an approver posts it.', 'success', 'adjust');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'post_adjustment') {
        requirePermission($conn, $membershipId, $businessId, $permissions['approve']);
        $adjustmentId = (int)($_POST['adjustment_id'] ?? 0);
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($adjustmentId <= 0) throw new InvalidArgumentException('Invalid adjustment reference.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'STOCK_ADJUSTMENT_POST', ['adjustment_id'=>$adjustmentId]);
            $adjustmentStmt = mysqli_prepare($conn, 'SELECT id,location_id,adjustment_number,adjustment_type,occurred_at,status FROM stock_adjustments WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($adjustmentStmt, 'ii', $adjustmentId, $businessId);
            mysqli_stmt_execute($adjustmentStmt);
            $adjustment = mysqli_fetch_assoc(mysqli_stmt_get_result($adjustmentStmt));
            if (!$adjustment || $adjustment['status'] !== 'DRAFT') throw new RuntimeException('Only a Draft adjustment can be posted.');
            $itemsStmt = mysqli_prepare($conn, 'SELECT sai.id,sai.product_id,sai.batch_id,sai.quantity,sai.unit_cost,sai.notes,p.name,p.cost_price FROM stock_adjustment_items sai JOIN products p ON p.id=sai.product_id AND p.business_id=sai.business_id WHERE sai.stock_adjustment_id=? AND sai.business_id=?');
            mysqli_stmt_bind_param($itemsStmt, 'ii', $adjustmentId, $businessId);
            mysqli_stmt_execute($itemsStmt);
            $items = mysqli_stmt_get_result($itemsStmt);
            $count = 0;
            while ($item = mysqli_fetch_assoc($items)) {
                $isOutbound = in_array($adjustment['adjustment_type'], $outboundAdjustments, true);
                $quantityDelta = $isOutbound ? -(float)$item['quantity'] : (float)$item['quantity'];
                $balance = lockInventoryBalance($conn, $businessId, (int)$adjustment['location_id'], (int)$item['product_id']);
                if ($adjustment['adjustment_type'] === 'OPENING' && abs((float)$balance['quantity_on_hand']) > 0.00005) {
                    throw new RuntimeException('Opening stock can only establish an initial zero balance. Use a Gain or Correction for later changes.');
                }
                $unitCost = (float)$item['unit_cost'];
                if ($isOutbound) {
                    if ($config['inventory_valuation_method'] === 'FIFO') {
                        $fifo = consumeFifoCost($conn, $businessId, (int)$adjustment['location_id'], (int)$item['product_id'], abs($quantityDelta), !empty($item['batch_id']) ? (int)$item['batch_id'] : null);
                        $unitCost = $fifo['unit_cost'];
                    } else {
                        $unitCost = (float)$balance['average_unit_cost'] ?: (float)$item['cost_price'];
                    }
                }
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$adjustment['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                    'movement_type'=>$movementMap[$adjustment['adjustment_type']],'quantity_delta'=>$quantityDelta,'unit_cost'=>$unitCost,'occurred_at'=>$adjustment['occurred_at'],
                    'stock_adjustment_item_id'=>$item['id'],'created_by_membership_id'=>$membershipId,'notes'=>$adjustment['adjustment_number'] . ': ' . $item['notes'],'config'=>$config
                ]);
                $count++;
            }
            if ($count === 0) throw new RuntimeException('The adjustment contains no items.');
            $update = mysqli_prepare($conn, "UPDATE stock_adjustments SET status='POSTED',approved_by_membership_id=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=? AND status='DRAFT'");
            mysqli_stmt_bind_param($update, 'iii', $membershipId, $adjustmentId, $businessId);
            mysqli_stmt_execute($update);
            writeAuditLog($conn, $businessId, 'STOCK_ADJUSTMENT_POSTED', 'stock_adjustment', $adjustmentId, ['adjustment_number'=>$adjustment['adjustment_number'],'items'=>$count]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 200, ['stock_adjustment_id'=>$adjustmentId,'status'=>'POSTED']);
            mysqli_commit($conn);
            $finish('Stock adjustment posted exactly once and inventory updated.', 'success', 'adjust');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'void_adjustment') {
        requirePermission($conn, $membershipId, $businessId, $permissions['approve']);
        $adjustmentId = (int)($_POST['adjustment_id'] ?? 0);
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        $config = getBusinessInventoryConfig($conn, $businessId);
        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'STOCK_ADJUSTMENT_VOID', ['adjustment_id'=>$adjustmentId]);
            $stmt = mysqli_prepare($conn, 'SELECT id,location_id,adjustment_number,status FROM stock_adjustments WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'ii', $adjustmentId, $businessId);
            mysqli_stmt_execute($stmt);
            $adjustment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if (!$adjustment || $adjustment['status'] !== 'POSTED') throw new RuntimeException('Only a Posted adjustment can be voided.');
            $movementStmt = mysqli_prepare($conn, 'SELECT im.product_id,im.batch_id,im.quantity_delta,im.unit_cost FROM inventory_movements im JOIN stock_adjustment_items sai ON sai.id=im.stock_adjustment_item_id WHERE sai.stock_adjustment_id=? AND im.business_id=? ORDER BY im.id');
            mysqli_stmt_bind_param($movementStmt, 'ii', $adjustmentId, $businessId);
            mysqli_stmt_execute($movementStmt);
            $movements = mysqli_stmt_get_result($movementStmt);
            $occurredAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            while ($movement = mysqli_fetch_assoc($movements)) {
                $delta = -(float)$movement['quantity_delta'];
                if ($delta < 0 && $config['inventory_valuation_method'] === 'FIFO') {
                    consumeFifoCost($conn, $businessId, (int)$adjustment['location_id'], (int)$movement['product_id'], abs($delta), !empty($movement['batch_id']) ? (int)$movement['batch_id'] : null);
                }
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$adjustment['location_id'],'product_id'=>$movement['product_id'],'batch_id'=>$movement['batch_id'],
                    'movement_type'=>$delta > 0 ? 'CORRECTION_IN' : 'CORRECTION_OUT','quantity_delta'=>$delta,'unit_cost'=>$movement['unit_cost'],'occurred_at'=>$occurredAt,
                    'created_by_membership_id'=>$membershipId,'notes'=>'Void reversal for ' . $adjustment['adjustment_number'],'config'=>$config
                ]);
            }
            $update = mysqli_prepare($conn, "UPDATE stock_adjustments SET status='VOIDED',updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?");
            mysqli_stmt_bind_param($update, 'ii', $adjustmentId, $businessId);
            mysqli_stmt_execute($update);
            writeAuditLog($conn, $businessId, 'STOCK_ADJUSTMENT_VOIDED', 'stock_adjustment', $adjustmentId, ['adjustment_number'=>$adjustment['adjustment_number']]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 200, ['stock_adjustment_id'=>$adjustmentId,'status'=>'VOIDED']);
            mysqli_commit($conn);
            $finish('Posted adjustment voided through controlled reversal movements.', 'success', 'adjust');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'create_stock_take') {
        requirePermission($conn, $membershipId, $businessId, $permissions['stocktake']);
        $locationId = (int)($_POST['location_id'] ?? 0);
        $number = trim((string)($_POST['stock_take_number'] ?? '')) ?: 'ST-' . gmdate('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        $productIds = is_array($_POST['product_ids'] ?? null) ? $_POST['product_ids'] : [];
        $countedQuantities = is_array($_POST['counted_quantities'] ?? null) ? $_POST['counted_quantities'] : [];
        $batchIds = is_array($_POST['batch_ids'] ?? null) ? $_POST['batch_ids'] : [];
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($locationId <= 0 || !$productIds) throw new InvalidArgumentException('Location and at least one counted product are required.');
        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'STOCK_TAKE_CREATE', ['number'=>$number,'location_id'=>$locationId,'products'=>$productIds,'counted'=>$countedQuantities,'batches'=>$batchIds]);
            $location = mysqli_prepare($conn, 'SELECT id FROM business_locations WHERE id=? AND business_id=? AND is_active=1');
            mysqli_stmt_bind_param($location, 'ii', $locationId, $businessId);
            mysqli_stmt_execute($location);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($location))) throw new RuntimeException('Select a valid active location.');
            $takeStmt = mysqli_prepare($conn, "INSERT INTO stock_takes (business_id,location_id,stock_take_number,status,started_at,created_by_membership_id,notes,created_at,updated_at) VALUES (?,?,?,'DRAFT',UTC_TIMESTAMP(6),?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
            mysqli_stmt_bind_param($takeStmt, 'iisis', $businessId, $locationId, $number, $membershipId, $notes);
            if (!mysqli_stmt_execute($takeStmt)) throw new RuntimeException('The stock take could not be created.');
            $stockTakeId = mysqli_insert_id($conn);
            $count = 0;
            foreach ($productIds as $index => $rawProductId) {
                $productId = (int)$rawProductId;
                $counted = (float)($countedQuantities[$index] ?? -1);
                $batchId = !empty($batchIds[$index]) ? (int)$batchIds[$index] : null;
                if ($productId <= 0 || $counted < 0) continue;
                $productStmt = mysqli_prepare($conn, 'SELECT id,name,track_batches,cost_price FROM products WHERE id=? AND business_id=? AND is_active=1');
                mysqli_stmt_bind_param($productStmt, 'ii', $productId, $businessId);
                mysqli_stmt_execute($productStmt);
                $product = mysqli_fetch_assoc(mysqli_stmt_get_result($productStmt));
                if (!$product) throw new RuntimeException('A counted product is invalid.');
                if ((int)$product['track_batches'] === 1 && $batchId === null) throw new RuntimeException('Select the counted batch for ' . $product['name'] . '.');
                $balance = lockInventoryBalance($conn, $businessId, $locationId, $productId);
                $systemQuantity = (float)$balance['quantity_on_hand'];
                if ($batchId !== null) {
                    $batchStmt = mysqli_prepare($conn, 'SELECT bib.quantity_on_hand FROM batch_inventory_balances bib JOIN product_batches pb ON pb.id=bib.batch_id AND pb.business_id=bib.business_id WHERE bib.business_id=? AND bib.location_id=? AND bib.batch_id=? AND pb.product_id=? FOR UPDATE');
                    mysqli_stmt_bind_param($batchStmt, 'iiii', $businessId, $locationId, $batchId, $productId);
                    mysqli_stmt_execute($batchStmt);
                    $batch = mysqli_fetch_assoc(mysqli_stmt_get_result($batchStmt));
                    if (!$batch) throw new RuntimeException('The counted batch has no balance at this location.');
                    $systemQuantity = (float)$batch['quantity_on_hand'];
                }
                $unitCost = (float)$balance['average_unit_cost'] ?: (float)$product['cost_price'];
                $itemStmt = mysqli_prepare($conn, 'INSERT INTO stock_take_items (business_id,stock_take_id,product_id,batch_id,system_quantity,counted_quantity,unit_cost,notes) VALUES (?,?,?,?,?,?,?,?)');
                mysqli_stmt_bind_param($itemStmt, 'iiiiddds', $businessId, $stockTakeId, $productId, $batchId, $systemQuantity, $counted, $unitCost, $notes);
                mysqli_stmt_execute($itemStmt);
                $count++;
            }
            if ($count === 0) throw new InvalidArgumentException('Enter at least one valid counted quantity.');
            writeAuditLog($conn, $businessId, 'STOCK_TAKE_CREATED', 'stock_take', $stockTakeId, ['stock_take_number'=>$number,'items'=>$count]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['stock_take_id'=>$stockTakeId]);
            mysqli_commit($conn);
            $finish('Stock take saved as Draft. Physical stock remains unchanged.', 'success', 'stocktake');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'complete_stock_take') {
        requirePermission($conn, $membershipId, $businessId, $permissions['approve']);
        $stockTakeId = (int)($_POST['stock_take_id'] ?? 0);
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        $config = getBusinessInventoryConfig($conn, $businessId);
        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'STOCK_TAKE_COMPLETE', ['stock_take_id'=>$stockTakeId]);
            $takeStmt = mysqli_prepare($conn, 'SELECT id,location_id,stock_take_number,status FROM stock_takes WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($takeStmt, 'ii', $stockTakeId, $businessId);
            mysqli_stmt_execute($takeStmt);
            $take = mysqli_fetch_assoc(mysqli_stmt_get_result($takeStmt));
            if (!$take || $take['status'] !== 'DRAFT') throw new RuntimeException('Only a Draft stock take can be completed.');
            $itemsStmt = mysqli_prepare($conn, 'SELECT id,product_id,batch_id,system_quantity,counted_quantity,difference_quantity,unit_cost FROM stock_take_items WHERE stock_take_id=? AND business_id=?');
            mysqli_stmt_bind_param($itemsStmt, 'ii', $stockTakeId, $businessId);
            mysqli_stmt_execute($itemsStmt);
            $items = mysqli_stmt_get_result($itemsStmt);
            $count = 0;
            $occurredAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            while ($item = mysqli_fetch_assoc($items)) {
                $balance = lockInventoryBalance($conn, $businessId, (int)$take['location_id'], (int)$item['product_id']);
                $current = (float)$balance['quantity_on_hand'];
                if (!empty($item['batch_id'])) {
                    $batchStmt = mysqli_prepare($conn, 'SELECT quantity_on_hand FROM batch_inventory_balances WHERE business_id=? AND location_id=? AND batch_id=? FOR UPDATE');
                    mysqli_stmt_bind_param($batchStmt, 'iii', $businessId, $take['location_id'], $item['batch_id']);
                    mysqli_stmt_execute($batchStmt);
                    $batch = mysqli_fetch_assoc(mysqli_stmt_get_result($batchStmt));
                    $current = (float)($batch['quantity_on_hand'] ?? 0);
                }
                if (abs($current - (float)$item['system_quantity']) > 0.00005) throw new RuntimeException('Stock changed after this count was captured. Create a new stock take to avoid overwriting later transactions.');
                $delta = (float)$item['difference_quantity'];
                if (abs($delta) > 0.00005) {
                    if ($delta < 0 && $config['inventory_valuation_method'] === 'FIFO') {
                        $fifo = consumeFifoCost($conn, $businessId, (int)$take['location_id'], (int)$item['product_id'], abs($delta), !empty($item['batch_id']) ? (int)$item['batch_id'] : null);
                        $item['unit_cost'] = $fifo['unit_cost'];
                    }
                    applyInventoryMovement($conn, [
                        'business_id'=>$businessId,'location_id'=>$take['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                        'movement_type'=>$delta > 0 ? 'STOCKTAKE_GAIN' : 'STOCKTAKE_LOSS','quantity_delta'=>$delta,'unit_cost'=>$item['unit_cost'],'occurred_at'=>$occurredAt,
                        'stock_take_item_id'=>$item['id'],'created_by_membership_id'=>$membershipId,'notes'=>$take['stock_take_number'] . ' completed count','config'=>$config
                    ]);
                }
                $count++;
            }
            $update = mysqli_prepare($conn, "UPDATE stock_takes SET status='COMPLETED',completed_at=?,approved_by_membership_id=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?");
            mysqli_stmt_bind_param($update, 'siii', $occurredAt, $membershipId, $stockTakeId, $businessId);
            mysqli_stmt_execute($update);
            writeAuditLog($conn, $businessId, 'STOCK_TAKE_COMPLETED', 'stock_take', $stockTakeId, ['stock_take_number'=>$take['stock_take_number'],'items'=>$count]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 200, ['stock_take_id'=>$stockTakeId,'status'=>'COMPLETED']);
            mysqli_commit($conn);
            $finish('Stock take completed and differences posted exactly once.', 'success', 'stocktake');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    throw new InvalidArgumentException('Invalid action.');
} catch (Throwable $error) {
    error_log('Inventory operation failed: ' . $error->getMessage());
    $finish($error->getMessage());
}
?>
