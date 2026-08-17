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
    header('Location: index');
    exit;
}
validateCsrfToken($_POST['csrf_token'] ?? '');

$action = (string)($_POST['action'] ?? '');
$businessId = (int)$_SESSION['active_business_id'];
$membershipId = (int)$_SESSION['membership_id'];
$roleQuery = isset($_GET['role']) ? '?role=' . rawurlencode((string)$_GET['role']) : '';
$requestedReturn = (string)($_POST['return_to'] ?? '');
if ($requestedReturn === 'receiving') {
    $returnPath = '../receiving/index';
} elseif ($requestedReturn === 'purchase_create') {
    $returnPath = 'create';
} elseif (preg_match('/^receiving_view:(\d+)$/', $requestedReturn, $receivingMatch)) {
    $returnPath = '../receiving/view?id=' . (int)$receivingMatch[1];
    $roleQuery = isset($_GET['role']) ? '&role=' . rawurlencode((string)$_GET['role']) : '';
} elseif (preg_match('/^purchase_view:(\d+)$/', $requestedReturn, $returnMatch)) {
    $returnPath = 'view?id=' . (int)$returnMatch[1];
    $roleQuery = isset($_GET['role']) ? '&role=' . rawurlencode((string)$_GET['role']) : '';
} else {
    $returnPath = 'index';
}
$finish = static function (string $message, string $type = 'error') use ($roleQuery, $returnPath): void {
    setFlashMessage($type, $message);
    header('Location: ' . $returnPath . $roleQuery);
    exit;
};

try {
    if ($action === 'create') {
        requirePermission($conn, $membershipId, $businessId, $permissions['create']);
        $purchaseNumber = trim((string)($_POST['purchase_number'] ?? ''));
        $purchaseType = strtoupper(trim((string)($_POST['purchase_type'] ?? '')));
        $purchaseDateInput = trim((string)($_POST['purchase_date'] ?? ''));
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $locationId = (int)($_POST['location_id'] ?? 0);
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        $productIds = is_array($_POST['product_ids'] ?? null) ? $_POST['product_ids'] : [];
        $purchaseUomIds = is_array($_POST['purchase_uom_ids'] ?? null) ? $_POST['purchase_uom_ids'] : [];
        $quantities = is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [];
        $unitCosts = is_array($_POST['unit_costs'] ?? null) ? $_POST['unit_costs'] : [];
        $salesAmounts = is_array($_POST['sales_amounts'] ?? null) ? $_POST['sales_amounts'] : [];
        $expiryDates = is_array($_POST['expiry_dates'] ?? null) ? $_POST['expiry_dates'] : [];
        $settlementType = strtoupper(trim((string)($_POST['settlement_type'] ?? 'DEBT')));
        $paymentAmountInput = trim((string)($_POST['payment_amount'] ?? ''));
        $paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH')));
        $paymentPhone = trim((string)($_POST['payment_phone'] ?? '')) ?: null;
        $bankName = trim((string)($_POST['bank_name'] ?? '')) ?: null;
        $bankAccountNumber = trim((string)($_POST['bank_account_number'] ?? '')) ?: null;
        $paymentReference = trim((string)($_POST['payment_reference'] ?? '')) ?: null;
        $paymentNotes = trim((string)($_POST['payment_notes'] ?? '')) ?: null;
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($purchaseNumber === '' || $purchaseDateInput === '' || $supplierId <= 0 || $locationId <= 0 || !$productIds) {
            throw new InvalidArgumentException('Purchase number, date, supplier, target location, and at least one item are required.');
        }
        if (!in_array($purchaseType, ['DIRECT','PURCHASE_ORDER'], true)) throw new InvalidArgumentException('Choose Direct Purchase or Purchase Order.');
        if ($purchaseType === 'DIRECT') requirePermission($conn, $membershipId, $businessId, $permissions['receive']);
        if (!in_array($settlementType, ['PAID','DEBT'], true)) throw new InvalidArgumentException('Select whether this purchase was paid or recorded as debt.');
        $allowedPaymentMethods = ['CASH','MOBILE_MONEY','BANK_TRANSFER'];
        if ($settlementType === 'PAID' && !in_array($paymentMethod, $allowedPaymentMethods, true)) throw new InvalidArgumentException('Select Cash, Phone, or Bank as the payment method.');
        if ($settlementType === 'PAID' && $paymentMethod === 'MOBILE_MONEY' && $paymentPhone === null) throw new InvalidArgumentException('Enter the telephone number used for the phone payment.');
        if ($settlementType === 'PAID' && $paymentMethod === 'BANK_TRANSFER' && ($bankName === null || $bankAccountNumber === null)) throw new InvalidArgumentException('Enter the bank name and bank account number.');
        if ($settlementType === 'DEBT' || $paymentMethod === 'CASH') {
            $paymentPhone = $bankName = $bankAccountNumber = null;
        } elseif ($paymentMethod === 'MOBILE_MONEY') {
            $bankName = $bankAccountNumber = null;
        } elseif ($paymentMethod === 'BANK_TRANSFER') {
            $paymentPhone = null;
        }
        $config = getBusinessInventoryConfig($conn, $businessId);
        $purchaseDate = businessLocalDateTimeToUtc($purchaseDateInput, $config['timezone']);

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'PURCHASE_CREATE', ['purchase_number'=>$purchaseNumber,'purchase_type'=>$purchaseType,'date'=>$purchaseDateInput,'supplier_id'=>$supplierId,'location_id'=>$locationId,'products'=>$productIds,'purchase_uom_ids'=>$purchaseUomIds,'quantities'=>$quantities,'costs'=>$unitCosts,'sales_amounts'=>$salesAmounts,'expiry_dates'=>$expiryDates,'settlement_type'=>$settlementType,'payment_method'=>$settlementType==='PAID'?$paymentMethod:null,'payment_phone'=>$paymentPhone,'bank_name'=>$bankName,'bank_account_number'=>$bankAccountNumber]);
            $supplierStmt = mysqli_prepare($conn, 'SELECT id FROM suppliers WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
            mysqli_stmt_bind_param($supplierStmt, 'ii', $supplierId, $businessId);
            mysqli_stmt_execute($supplierStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($supplierStmt))) throw new RuntimeException('Select a valid active supplier.');
            $locationStmt = mysqli_prepare($conn, 'SELECT id FROM business_locations WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
            mysqli_stmt_bind_param($locationStmt, 'ii', $locationId, $businessId);
            mysqli_stmt_execute($locationStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($locationStmt))) throw new RuntimeException('Select a valid active location.');
            $businessStmt = mysqli_prepare($conn, 'SELECT business_name,currency_code FROM businesses WHERE id=? LIMIT 1');
            mysqli_stmt_bind_param($businessStmt, 'i', $businessId);
            mysqli_stmt_execute($businessStmt);
            $business = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt));
            if (!$business) throw new RuntimeException('The active company could not be found.');
            $duplicate = mysqli_prepare($conn, 'SELECT id FROM purchases WHERE business_id=? AND purchase_number=? FOR UPDATE');
            mysqli_stmt_bind_param($duplicate, 'is', $businessId, $purchaseNumber);
            mysqli_stmt_execute($duplicate);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('A purchase with this number already exists.');

            $initialStatus = $purchaseType === 'DIRECT' ? 'ORDERED' : 'DRAFT';
            $approvedByMembershipId = $purchaseType === 'DIRECT' ? $membershipId : null;
            $purchaseStmt = mysqli_prepare($conn, "INSERT INTO purchases (business_id,location_id,supplier_id,purchase_number,purchase_type,status,purchase_date,payment_status,notes,created_by_membership_id,approved_by_membership_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
            mysqli_stmt_bind_param($purchaseStmt, 'iiissssssii', $businessId, $locationId, $supplierId, $purchaseNumber, $purchaseType, $initialStatus, $purchaseDate, $settlementType, $notes, $membershipId, $approvedByMembershipId);
            if (!mysqli_stmt_execute($purchaseStmt)) throw new RuntimeException('The purchase could not be created.');
            $purchaseId = mysqli_insert_id($conn);
            $subtotal = 0.0;
            $validItems = 0;
            $transactionUnits = [];

            foreach ($productIds as $index => $rawProductId) {
                $productId = (int)$rawProductId;
                $purchaseUomId = (int)($purchaseUomIds[$index] ?? 0);
                $purchaseQuantity = normalizeInventoryDecimal($quantities[$index] ?? 0, 'Purchase quantity');
                $purchaseUnitCost = normalizeInventoryDecimal($unitCosts[$index] ?? 0, 'Purchase unit cost');
                if ($productId <= 0 || $purchaseQuantity <= 0) continue;
                if ($purchaseUnitCost < 0) throw new InvalidArgumentException('Purchase price cannot be negative.');
                $productStmt = mysqli_prepare($conn, 'SELECT id,name,uom_id,uom,sale_price,package_uom_id,units_per_package,package_sale_price,track_batches,track_expiry FROM products WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
                mysqli_stmt_bind_param($productStmt, 'ii', $productId, $businessId);
                mysqli_stmt_execute($productStmt);
                $product = mysqli_fetch_assoc(mysqli_stmt_get_result($productStmt));
                if (!$product) throw new RuntimeException('One selected product is unavailable.');
                $transactionUom = resolveProductTransactionUom($conn, $businessId, $product, $purchaseUomId);
                $conversionFactor = (float)$transactionUom['factor'];
                $baseQuantity = convertTransactionQuantityToBase($purchaseQuantity, $conversionFactor);
                $baseUnitCost = convertTransactionUnitPriceToBase($purchaseUnitCost, $conversionFactor, 'Purchase unit cost');
                $salesAmountInput = trim((string)($salesAmounts[$index] ?? ''));
                if ($salesAmountInput === '') {
                    throw new InvalidArgumentException('Enter the sales amount for one ' . $transactionUom['code'] . ' of ' . $product['name'] . '.');
                }
                $salesAmount = normalizeInventoryDecimal($salesAmountInput, 'Sales amount');
                if ($salesAmount < 0) throw new InvalidArgumentException('Sales amount cannot be negative.');
                $packageSellingPrice = $product['package_sale_price'] === null ? null : (float)$product['package_sale_price'];
                $unitSellingPrice = $salesAmount;
                if ($transactionUom['is_package']) {
                    $packageSellingPrice = $salesAmount;
                    $unitSellingPrice = convertTransactionUnitPriceToBase($salesAmount, $conversionFactor, 'Sales amount');
                }

                $expiryDate = trim((string)($expiryDates[$index] ?? '')) ?: null;
                if ($expiryDate !== null) {
                    $dateParts = array_map('intval', explode('-', $expiryDate));
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiryDate) || count($dateParts) !== 3 || !checkdate($dateParts[1], $dateParts[2], $dateParts[0])) {
                        throw new InvalidArgumentException('Enter a valid expiry date for ' . $product['name'] . '.');
                    }
                }

                $lotNumber = generateUniqueCompanyBatchNumber($conn, $businessId, (string)$business['business_name']);
                $batchInsert = mysqli_prepare($conn, 'INSERT INTO product_batches (business_id,product_id,lot_number,expires_at,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP(6))');
                mysqli_stmt_bind_param($batchInsert, 'iiss', $businessId, $productId, $lotNumber, $expiryDate);
                if (!mysqli_stmt_execute($batchInsert)) throw new RuntimeException('The automatic product batch could not be created.');
                $batchId = mysqli_insert_id($conn);

                $lineTotal = normalizeInventoryDecimal($purchaseQuantity * $purchaseUnitCost, 'Purchase line total');
                $itemStmt = mysqli_prepare($conn, 'INSERT INTO purchase_items (business_id,purchase_id,product_id,batch_id,purchase_uom_id,purchase_quantity,conversion_factor_to_base,purchase_unit_cost,ordered_quantity,received_quantity,unit_cost,unit_selling_price,package_selling_price,discount_amount,tax_amount,line_total,expiry_date,created_at) VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,0,0,?,?,UTC_TIMESTAMP(6))');
                mysqli_stmt_bind_param($itemStmt, 'iiiiidddddddds', $businessId, $purchaseId, $productId, $batchId, $purchaseUomId, $purchaseQuantity, $conversionFactor, $purchaseUnitCost, $baseQuantity, $baseUnitCost, $unitSellingPrice, $packageSellingPrice, $lineTotal, $expiryDate);
                if (!mysqli_stmt_execute($itemStmt)) throw new RuntimeException('A purchase item could not be saved.');
                $purchaseItemId = mysqli_insert_id($conn);
                if ($purchaseType === 'DIRECT') {
                    $receiveItem = mysqli_prepare($conn, 'UPDATE purchase_items SET received_quantity=ordered_quantity WHERE id=? AND purchase_id=? AND business_id=?');
                    mysqli_stmt_bind_param($receiveItem, 'iii', $purchaseItemId, $purchaseId, $businessId);
                    if (!mysqli_stmt_execute($receiveItem)) throw new RuntimeException('The direct purchase line could not be marked received.');
                    applyInventoryMovement($conn, [
                        'business_id'=>$businessId,
                        'location_id'=>$locationId,
                        'product_id'=>$productId,
                        'batch_id'=>$batchId,
                        'movement_type'=>'PURCHASE_RECEIPT',
                        'quantity_delta'=>$baseQuantity,
                        'unit_cost'=>$baseUnitCost,
                        'occurred_at'=>$purchaseDate,
                        'purchase_item_id'=>$purchaseItemId,
                        'created_by_membership_id'=>$membershipId,
                        'notes'=>$purchaseNumber . ' automatic direct-purchase receipt',
                        'config'=>$config
                    ]);
                    $priceStmt = mysqli_prepare($conn, 'UPDATE products SET sale_price=?,package_sale_price=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
                    mysqli_stmt_bind_param($priceStmt, 'ddii', $unitSellingPrice, $packageSellingPrice, $productId, $businessId);
                    if (!mysqli_stmt_execute($priceStmt)) throw new RuntimeException('The product selling price could not be updated.');
                }
                $transactionUnits[] = ['product_id'=>$productId,'selected_uom_id'=>$purchaseUomId,'selected_uom_code'=>$transactionUom['code'],'entered_quantity'=>$purchaseQuantity,'conversion_factor_to_base'=>$conversionFactor,'base_quantity'=>$baseQuantity,'entered_unit_cost'=>$purchaseUnitCost,'normalized_base_cost'=>$baseUnitCost,'entered_sales_amount'=>$salesAmount,'normalized_base_selling_price'=>$unitSellingPrice,'package_selling_price'=>$packageSellingPrice,'expiry_date'=>$expiryDate];
                $subtotal += $lineTotal;
                $validItems++;
            }
            if ($validItems === 0) throw new InvalidArgumentException('Add at least one valid purchase item.');
            $amountPaid = 0.0;
            $paymentStatus = 'DEBT';
            if ($settlementType === 'PAID') {
                $amountPaid = $paymentAmountInput === '' ? $subtotal : (float)$paymentAmountInput;
                if ($amountPaid <= 0) throw new InvalidArgumentException('Payment Amount must be greater than zero.');
                if ($amountPaid > $subtotal + 0.00005) throw new InvalidArgumentException('Payment Amount cannot be greater than the purchase total of ' . formatCurrency($subtotal, $business['currency_code'] ?? 'RWF') . '.');
                $paymentStatus = $amountPaid + 0.00005 >= $subtotal ? 'PAID' : 'PARTIALLY_PAID';
            }
            $totals = mysqli_prepare($conn, 'UPDATE purchases SET subtotal=?,total_amount=?,amount_paid=?,payment_status=? WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($totals, 'dddsii', $subtotal, $subtotal, $amountPaid, $paymentStatus, $purchaseId, $businessId);
            mysqli_stmt_execute($totals);
            if ($settlementType === 'PAID') {
                $paymentStmt = mysqli_prepare($conn, 'INSERT INTO purchase_payments (business_id,purchase_id,amount,payment_method,reference_number,phone_number,bank_name,bank_account_number,paid_at,recorded_by_membership_id,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))');
                mysqli_stmt_bind_param($paymentStmt, 'iidssssssis', $businessId, $purchaseId, $amountPaid, $paymentMethod, $paymentReference, $paymentPhone, $bankName, $bankAccountNumber, $purchaseDate, $membershipId, $paymentNotes);
                if (!mysqli_stmt_execute($paymentStmt)) throw new RuntimeException('The purchase payment could not be recorded.');
            }
            if ($purchaseType === 'DIRECT') {
                $receivePurchase = mysqli_prepare($conn, "UPDATE purchases SET status='RECEIVED',received_at=?,received_by_membership_id=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?");
                mysqli_stmt_bind_param($receivePurchase, 'siii', $purchaseDate, $membershipId, $purchaseId, $businessId);
                if (!mysqli_stmt_execute($receivePurchase)) throw new RuntimeException('The direct purchase could not be completed as received.');
            }
            writeAuditLog($conn, $businessId, $purchaseType === 'DIRECT' ? 'DIRECT_PURCHASE_CREATED_AND_RECEIVED' : 'PURCHASE_ORDER_CREATED', 'purchase', $purchaseId, ['purchase_number'=>$purchaseNumber,'purchase_type'=>$purchaseType,'initial_status'=>$purchaseType==='DIRECT'?'RECEIVED':$initialStatus,'total_amount'=>$subtotal,'items'=>$validItems,'transaction_units'=>$transactionUnits,'payment_status'=>$paymentStatus,'amount_paid'=>$amountPaid,'remaining_balance'=>max(0,$subtotal-$amountPaid),'payment_method'=>$settlementType==='PAID'?$paymentMethod:null]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['purchase_id'=>$purchaseId]);
            mysqli_commit($conn);
            if ($purchaseType === 'DIRECT') {
                setFlashMessage('success', $paymentStatus === 'PAID' ? 'Direct purchase recorded, paid, and received. Stock is now available.' : ($paymentStatus === 'PARTIALLY_PAID' ? 'Direct purchase recorded and received. The partial payment was saved.' : 'Direct purchase recorded as supplier debt and received. Stock is now available.'));
                $viewRole = isset($_GET['role']) ? '&role=' . rawurlencode((string)$_GET['role']) : '';
                header('Location: view?id=' . $purchaseId . $viewRole);
                exit;
            }
            if ($requestedReturn === 'purchase_create') {
                $viewRole = isset($_GET['role']) ? '&role=' . rawurlencode((string)$_GET['role']) : '';
                setFlashMessage('success', $paymentStatus === 'PAID' ? 'Purchase order and payment recorded successfully.' : ($paymentStatus === 'PARTIALLY_PAID' ? 'Purchase order and partial payment recorded successfully.' : 'Purchase order recorded as supplier debt.'));
                header('Location: view?id=' . $purchaseId . $viewRole);
                exit;
            }
            $finish('Purchase order created in Draft.', 'success');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'update_purchase') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $purchaseNumber = trim((string)($_POST['purchase_number'] ?? ''));
        $purchaseDateInput = trim((string)($_POST['purchase_date'] ?? ''));
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $locationId = (int)($_POST['location_id'] ?? 0);
        $supplierInvoice = trim((string)($_POST['supplier_invoice_number'] ?? '')) ?: null;
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
        if ($purchaseId <= 0 || $purchaseNumber === '' || $purchaseDateInput === '' || $supplierId <= 0 || $locationId <= 0) {
            throw new InvalidArgumentException('Purchase number, date, supplier, and location are required.');
        }
        $config = getBusinessInventoryConfig($conn, $businessId);
        $purchaseDate = businessLocalDateTimeToUtc($purchaseDateInput, $config['timezone']);
        mysqli_begin_transaction($conn);
        try {
            $currentStmt = mysqli_prepare($conn, 'SELECT p.*,COALESCE(SUM(pi.received_quantity),0) received_quantity FROM purchases p LEFT JOIN purchase_items pi ON pi.purchase_id=p.id AND pi.business_id=p.business_id WHERE p.id=? AND p.business_id=? GROUP BY p.id FOR UPDATE');
            mysqli_stmt_bind_param($currentStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($currentStmt);
            $current = mysqli_fetch_assoc(mysqli_stmt_get_result($currentStmt));
            if (!$current) throw new RuntimeException('The purchase could not be found.');
            if ((float)$current['received_quantity'] > 0.00005 && $locationId !== (int)$current['location_id']) {
                throw new RuntimeException('The receiving location cannot be changed after stock has been received.');
            }
            $supplierStmt = mysqli_prepare($conn, 'SELECT id FROM suppliers WHERE id=? AND business_id=? LIMIT 1');
            mysqli_stmt_bind_param($supplierStmt, 'ii', $supplierId, $businessId);
            mysqli_stmt_execute($supplierStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($supplierStmt))) throw new RuntimeException('Select a valid supplier.');
            $locationStmt = mysqli_prepare($conn, 'SELECT id FROM business_locations WHERE id=? AND business_id=? LIMIT 1');
            mysqli_stmt_bind_param($locationStmt, 'ii', $locationId, $businessId);
            mysqli_stmt_execute($locationStmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($locationStmt))) throw new RuntimeException('Select a valid location.');
            $duplicate = mysqli_prepare($conn, 'SELECT id FROM purchases WHERE business_id=? AND purchase_number=? AND id<>? LIMIT 1');
            mysqli_stmt_bind_param($duplicate, 'isi', $businessId, $purchaseNumber, $purchaseId);
            mysqli_stmt_execute($duplicate);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('A purchase with this number already exists.');
            $update = mysqli_prepare($conn, 'UPDATE purchases SET purchase_number=?,purchase_date=?,supplier_id=?,location_id=?,supplier_invoice_number=?,notes=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($update, 'ssiissii', $purchaseNumber, $purchaseDate, $supplierId, $locationId, $supplierInvoice, $notes, $purchaseId, $businessId);
            if (!mysqli_stmt_execute($update)) throw new RuntimeException('The purchase details could not be updated.');
            writeAuditLog($conn, $businessId, 'PURCHASE_UPDATED', 'purchase', $purchaseId, [
                'purchase_number'=>$purchaseNumber,
                'supplier_id'=>$supplierId,
                'location_id'=>$locationId,
                'supplier_invoice_number'=>$supplierInvoice
            ]);
            mysqli_commit($conn);
            $finish('Purchase details updated successfully.', 'success');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'add_payment') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $amount = (float)($_POST['payment_amount'] ?? 0);
        $paymentMethod = strtoupper(trim((string)($_POST['payment_method'] ?? 'CASH')));
        $paymentDateInput = trim((string)($_POST['paid_at'] ?? ''));
        $paymentPhone = trim((string)($_POST['payment_phone'] ?? '')) ?: null;
        $bankName = trim((string)($_POST['bank_name'] ?? '')) ?: null;
        $bankAccountNumber = trim((string)($_POST['bank_account_number'] ?? '')) ?: null;
        $paymentReference = trim((string)($_POST['payment_reference'] ?? '')) ?: null;
        $paymentNotes = trim((string)($_POST['payment_notes'] ?? '')) ?: null;
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($purchaseId <= 0 || $amount <= 0 || $paymentDateInput === '') throw new InvalidArgumentException('Payment amount and payment date are required.');
        if (!in_array($paymentMethod, ['CASH','MOBILE_MONEY','BANK_TRANSFER'], true)) throw new InvalidArgumentException('Select Cash, Phone, or Bank as the payment method.');
        if ($paymentMethod === 'MOBILE_MONEY' && $paymentPhone === null) throw new InvalidArgumentException('Enter the telephone number used for the phone payment.');
        if ($paymentMethod === 'BANK_TRANSFER' && ($bankName === null || $bankAccountNumber === null)) throw new InvalidArgumentException('Enter the bank name and bank account number.');
        if ($paymentMethod === 'CASH') $paymentPhone = $bankName = $bankAccountNumber = null;
        if ($paymentMethod === 'MOBILE_MONEY') $bankName = $bankAccountNumber = null;
        if ($paymentMethod === 'BANK_TRANSFER') $paymentPhone = null;
        $config = getBusinessInventoryConfig($conn, $businessId);
        $paidAt = businessLocalDateTimeToUtc($paymentDateInput, $config['timezone']);
        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'PURCHASE_PAYMENT', ['purchase_id'=>$purchaseId,'amount'=>$amount,'payment_method'=>$paymentMethod,'paid_at'=>$paymentDateInput,'reference'=>$paymentReference]);
            $purchaseStmt = mysqli_prepare($conn, 'SELECT p.id,p.purchase_number,p.total_amount,p.amount_paid,b.currency_code FROM purchases p JOIN businesses b ON b.id=p.business_id WHERE p.id=? AND p.business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($purchaseStmt);
            $purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));
            if (!$purchase) throw new RuntimeException('The purchase could not be found.');
            $balance = max(0.0, (float)$purchase['total_amount'] - (float)$purchase['amount_paid']);
            if ($balance <= 0.00005) throw new RuntimeException('This purchase is already paid.');
            if ($amount > $balance + 0.00005) throw new InvalidArgumentException('Payment Amount cannot be greater than the outstanding balance of ' . formatCurrency($balance, $purchase['currency_code'] ?? 'RWF') . '.');
            $paymentStmt = mysqli_prepare($conn, 'INSERT INTO purchase_payments (business_id,purchase_id,amount,payment_method,reference_number,phone_number,bank_name,bank_account_number,paid_at,recorded_by_membership_id,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))');
            mysqli_stmt_bind_param($paymentStmt, 'iidssssssis', $businessId, $purchaseId, $amount, $paymentMethod, $paymentReference, $paymentPhone, $bankName, $bankAccountNumber, $paidAt, $membershipId, $paymentNotes);
            if (!mysqli_stmt_execute($paymentStmt)) throw new RuntimeException('The payment could not be recorded.');
            $newPaid = (float)$purchase['amount_paid'] + $amount;
            $remainingBalance = max(0.0, (float)$purchase['total_amount'] - $newPaid);
            $paymentStatus = $newPaid + 0.00005 >= (float)$purchase['total_amount'] ? 'PAID' : 'PARTIALLY_PAID';
            $update = mysqli_prepare($conn, 'UPDATE purchases SET amount_paid=?,payment_status=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($update, 'dsii', $newPaid, $paymentStatus, $purchaseId, $businessId);
            if (!mysqli_stmt_execute($update)) throw new RuntimeException('The purchase payment total could not be updated.');
            writeAuditLog($conn, $businessId, 'PURCHASE_PAYMENT_RECORDED', 'purchase', $purchaseId, ['amount'=>$amount,'payment_method'=>$paymentMethod,'payment_status'=>$paymentStatus,'remaining_balance'=>$remainingBalance]);
            completeIdempotencyKey($conn, $businessId, $idempotencyKey, 201, ['purchase_id'=>$purchaseId,'payment_status'=>$paymentStatus,'remaining_balance'=>$remainingBalance]);
            mysqli_commit($conn);
            $finish($paymentStatus === 'PAID' ? 'Payment recorded. The purchase is now Paid.' : 'Partial payment recorded. Remaining balance: ' . formatCurrency($remainingBalance, $purchase['currency_code'] ?? 'RWF') . '.', 'success');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
    }

    if ($action === 'delete_purchase') {
        requirePermission($conn, $membershipId, $businessId, $permissions['delete']);
        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        if ($purchaseId <= 0) throw new InvalidArgumentException('Select a valid purchase to delete.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        $deletedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        mysqli_begin_transaction($conn);
        try {
            $purchaseStmt = mysqli_prepare($conn, 'SELECT id,purchase_number,location_id,status,total_amount FROM purchases WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($purchaseStmt);
            $purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));
            if (!$purchase) throw new RuntimeException('The purchase could not be found.');
            $returnStmt = mysqli_prepare($conn, 'SELECT COUNT(*) return_count FROM purchase_returns WHERE purchase_id=? AND business_id=?');
            mysqli_stmt_bind_param($returnStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($returnStmt);
            if ((int)mysqli_fetch_assoc(mysqli_stmt_get_result($returnStmt))['return_count'] > 0) {
                throw new RuntimeException('This purchase has stock returns and cannot be deleted. Keep it as part of the inventory audit history.');
            }
            $items = [];
            $itemStmt = mysqli_prepare($conn, 'SELECT id,product_id,batch_id,received_quantity,unit_cost FROM purchase_items WHERE purchase_id=? AND business_id=? ORDER BY id FOR UPDATE');
            mysqli_stmt_bind_param($itemStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($itemStmt);
            $itemResult = mysqli_stmt_get_result($itemStmt);
            while ($item = mysqli_fetch_assoc($itemResult)) $items[] = $item;
            foreach ($items as $item) {
                $receivedQuantity = (float)$item['received_quantity'];
                if ($receivedQuantity <= 0.00005) continue;
                $unitCost = (float)$item['unit_cost'];
                if (($config['inventory_valuation_method'] ?? 'WEIGHTED_AVERAGE') === 'FIFO') {
                    $fifo = consumeFifoCost($conn, $businessId, (int)$purchase['location_id'], (int)$item['product_id'], $receivedQuantity, !empty($item['batch_id']) ? (int)$item['batch_id'] : null);
                    $unitCost = (float)$fifo['unit_cost'];
                }
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,
                    'location_id'=>(int)$purchase['location_id'],
                    'product_id'=>(int)$item['product_id'],
                    'batch_id'=>!empty($item['batch_id']) ? (int)$item['batch_id'] : null,
                    'movement_type'=>'CORRECTION_OUT',
                    'quantity_delta'=>-$receivedQuantity,
                    'unit_cost'=>$unitCost,
                    'occurred_at'=>$deletedAt,
                    'created_by_membership_id'=>$membershipId,
                    'notes'=>'Stock reversal for deleted purchase ' . $purchase['purchase_number'],
                    'config'=>$config
                ]);
            }
            $detachMovements = mysqli_prepare($conn, 'UPDATE inventory_movements im JOIN purchase_items pi ON pi.id=im.purchase_item_id AND pi.business_id=im.business_id SET im.purchase_item_id=NULL WHERE pi.purchase_id=? AND pi.business_id=?');
            mysqli_stmt_bind_param($detachMovements, 'ii', $purchaseId, $businessId);
            if (!mysqli_stmt_execute($detachMovements)) throw new RuntimeException('The purchase inventory history could not be preserved.');
            $detachLayers = mysqli_prepare($conn, 'UPDATE inventory_cost_layers icl JOIN purchase_items pi ON pi.id=icl.purchase_item_id AND pi.business_id=icl.business_id SET icl.purchase_item_id=NULL WHERE pi.purchase_id=? AND pi.business_id=?');
            mysqli_stmt_bind_param($detachLayers, 'ii', $purchaseId, $businessId);
            if (!mysqli_stmt_execute($detachLayers)) throw new RuntimeException('The purchase costing history could not be preserved.');
            $deletePayments = mysqli_prepare($conn, 'DELETE FROM purchase_payments WHERE purchase_id=? AND business_id=?');
            mysqli_stmt_bind_param($deletePayments, 'ii', $purchaseId, $businessId);
            if (!mysqli_stmt_execute($deletePayments)) throw new RuntimeException('Purchase payments could not be removed.');
            writeAuditLog($conn, $businessId, 'PURCHASE_DELETED', 'purchase', $purchaseId, ['purchase_number'=>$purchase['purchase_number'],'previous_status'=>$purchase['status'],'total_amount'=>$purchase['total_amount'],'stock_reversed'=>true]);
            $deletePurchase = mysqli_prepare($conn, 'DELETE FROM purchases WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($deletePurchase, 'ii', $purchaseId, $businessId);
            if (!mysqli_stmt_execute($deletePurchase) || mysqli_stmt_affected_rows($deletePurchase) !== 1) throw new RuntimeException('The purchase could not be deleted.');
            mysqli_commit($conn);
            setFlashMessage('success', 'Purchase deleted successfully. Any received stock was reversed safely.');
            header('Location: index' . (isset($_GET['role']) ? '?role=' . rawurlencode((string)$_GET['role']) : ''));
            exit;
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
        $requestedReceiptStatus = strtoupper(trim((string)($_POST['receipt_status'] ?? 'PENDING')));
        $idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));
        if ($purchaseId <= 0 || !$itemIds) throw new InvalidArgumentException('Select a purchase and at least one receipt line.');
        if (!in_array($requestedReceiptStatus, ['PENDING','RECEIVED'], true)) throw new InvalidArgumentException('Select a valid receiving status.');
        $config = getBusinessInventoryConfig($conn, $businessId);
        $receivedAt = $receivedAtInput !== '' ? businessLocalDateTimeToUtc($receivedAtInput, $config['timezone']) : (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

        mysqli_begin_transaction($conn);
        try {
            claimIdempotencyKey($conn, $businessId, $idempotencyKey, 'PURCHASE_RECEIPT', ['purchase_id'=>$purchaseId,'items'=>$itemIds,'quantities'=>$receivedQuantities,'supplier_invoice'=>$supplierInvoice,'receipt_status'=>$requestedReceiptStatus]);
            $purchaseStmt = mysqli_prepare($conn, 'SELECT id,location_id,purchase_number,status FROM purchases WHERE id=? AND business_id=? FOR UPDATE');
            mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($purchaseStmt);
            $purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));
            if (!$purchase || !in_array($purchase['status'], ['ORDERED','PARTIALLY_RECEIVED'], true)) throw new RuntimeException('Only purchases ready for receiving or partially received purchases can be received.');

            $receivedLines = 0;
            $receivedValue = 0.0;
            $receiptUnits = [];
            foreach ($itemIds as $index => $rawItemId) {
                $itemId = (int)$rawItemId;
                if ($itemId <= 0) continue;
                $itemStmt = mysqli_prepare($conn, 'SELECT pi.product_id,pi.batch_id,pi.purchase_uom_id,pi.conversion_factor_to_base,pi.ordered_quantity,pi.received_quantity,pi.unit_cost,pi.unit_selling_price,pi.package_selling_price,p.name,u.code purchase_uom FROM purchase_items pi JOIN products p ON p.id=pi.product_id AND p.business_id=pi.business_id JOIN units_of_measure u ON u.id=pi.purchase_uom_id WHERE pi.id=? AND pi.purchase_id=? AND pi.business_id=? FOR UPDATE');
                mysqli_stmt_bind_param($itemStmt, 'iii', $itemId, $purchaseId, $businessId);
                mysqli_stmt_execute($itemStmt);
                $item = mysqli_fetch_assoc(mysqli_stmt_get_result($itemStmt));
                if (!$item) throw new RuntimeException('A purchase line is invalid.');
                $remaining = (float)$item['ordered_quantity'] - (float)$item['received_quantity'];
                if ($remaining <= 0.00005) continue;
                $factor = normalizeInventoryDecimal($item['conversion_factor_to_base'], 'Purchase conversion factor');
                $enteredReceiveQuantity = $requestedReceiptStatus === 'RECEIVED'
                    ? convertBaseQuantityToTransaction($remaining, $factor)
                    : normalizeInventoryDecimal($receivedQuantities[$index] ?? 0, 'Received quantity');
                if ($enteredReceiveQuantity < 0) throw new InvalidArgumentException('Received quantity cannot be negative.');
                if ($enteredReceiveQuantity == 0.0) continue;
                $receiveNowBase = $requestedReceiptStatus === 'RECEIVED' ? $remaining : convertTransactionQuantityToBase($enteredReceiveQuantity, $factor);
                if ($receiveNowBase > $remaining + 0.00005) throw new RuntimeException('Receipt for ' . $item['name'] . ' exceeds the remaining ordered quantity of ' . formatInventoryDecimal(convertBaseQuantityToTransaction($remaining, $factor)) . ' ' . $item['purchase_uom'] . '.');
                $newReceived = normalizeInventoryDecimal((float)$item['received_quantity'] + $receiveNowBase, 'Received base quantity');
                $updateItem = mysqli_prepare($conn, 'UPDATE purchase_items SET received_quantity=? WHERE id=? AND purchase_id=? AND business_id=?');
                mysqli_stmt_bind_param($updateItem, 'diii', $newReceived, $itemId, $purchaseId, $businessId);
                mysqli_stmt_execute($updateItem);
                applyInventoryMovement($conn, [
                    'business_id'=>$businessId,'location_id'=>$purchase['location_id'],'product_id'=>$item['product_id'],'batch_id'=>$item['batch_id'],
                    'movement_type'=>'PURCHASE_RECEIPT','quantity_delta'=>$receiveNowBase,'unit_cost'=>$item['unit_cost'],'occurred_at'=>$receivedAt,
                    'purchase_item_id'=>$itemId,'created_by_membership_id'=>$membershipId,'notes'=>$purchase['purchase_number'] . ' goods receipt','config'=>$config
                ]);
                $priceStmt = mysqli_prepare($conn, 'UPDATE products SET sale_price=?,package_sale_price=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
                mysqli_stmt_bind_param($priceStmt, 'ddii', $item['unit_selling_price'], $item['package_selling_price'], $item['product_id'], $businessId);
                if (!mysqli_stmt_execute($priceStmt)) throw new RuntimeException('The product selling price could not be updated.');
                $receivedValue += $receiveNowBase * (float)$item['unit_cost'];
                $receiptUnits[] = ['product_id'=>(int)$item['product_id'],'selected_uom_id'=>(int)$item['purchase_uom_id'],'selected_uom_code'=>$item['purchase_uom'],'entered_quantity'=>$enteredReceiveQuantity,'conversion_factor_to_base'=>$factor,'base_quantity'=>$receiveNowBase,'normalized_base_cost'=>(float)$item['unit_cost']];
                $receivedLines++;
            }
            if ($receivedLines === 0) throw new InvalidArgumentException('Enter a received quantity for at least one item.');

            $remainingStmt = mysqli_prepare($conn, 'SELECT COUNT(*) remaining_lines FROM purchase_items WHERE purchase_id=? AND business_id=? AND received_quantity < ordered_quantity');
            mysqli_stmt_bind_param($remainingStmt, 'ii', $purchaseId, $businessId);
            mysqli_stmt_execute($remainingStmt);
            $remainingLines = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($remainingStmt))['remaining_lines'];
            if ($requestedReceiptStatus === 'RECEIVED' && $remainingLines > 0) throw new RuntimeException('All remaining purchase lines must be included before marking the purchase received.');
            $newStatus = $remainingLines === 0 ? 'RECEIVED' : 'PARTIALLY_RECEIVED';
            if ($newStatus === 'RECEIVED') {
                $updatePurchase = mysqli_prepare($conn, 'UPDATE purchases SET status=?,received_at=?,received_by_membership_id=?,supplier_invoice_number=COALESCE(?,supplier_invoice_number),updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
                mysqli_stmt_bind_param($updatePurchase, 'ssisii', $newStatus, $receivedAt, $membershipId, $supplierInvoice, $purchaseId, $businessId);
            } else {
                $updatePurchase = mysqli_prepare($conn, 'UPDATE purchases SET status=?,received_by_membership_id=?,supplier_invoice_number=COALESCE(?,supplier_invoice_number),updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
                mysqli_stmt_bind_param($updatePurchase, 'sisii', $newStatus, $membershipId, $supplierInvoice, $purchaseId, $businessId);
            }
            if (!mysqli_stmt_execute($updatePurchase)) throw new RuntimeException('Purchase receipt status could not be updated.');
            writeAuditLog($conn, $businessId, 'PURCHASE_RECEIVED', 'purchase', $purchaseId, ['purchase_number'=>$purchase['purchase_number'],'receipt_value'=>$receivedValue,'lines'=>$receivedLines,'transaction_units'=>$receiptUnits,'requested_status'=>$requestedReceiptStatus,'status'=>$newStatus]);
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
            $returnUnits = [];
            foreach ($itemIds as $index => $rawItemId) {
                $itemId = (int)$rawItemId;
                $enteredQuantity = normalizeInventoryDecimal($quantities[$index] ?? 0, 'Purchase return quantity');
                if ($itemId <= 0 || $enteredQuantity <= 0) continue;
                $itemStmt = mysqli_prepare($conn, "SELECT pi.product_id,pi.batch_id,pi.purchase_uom_id,pi.conversion_factor_to_base,pi.received_quantity,pi.unit_cost,p.name,u.code purchase_uom,COALESCE((SELECT SUM(pri.quantity) FROM purchase_return_items pri JOIN purchase_returns pr ON pr.id=pri.purchase_return_id WHERE pri.purchase_item_id=pi.id AND pr.status='COMPLETED'),0) returned_quantity FROM purchase_items pi JOIN products p ON p.id=pi.product_id AND p.business_id=pi.business_id JOIN units_of_measure u ON u.id=pi.purchase_uom_id WHERE pi.id=? AND pi.purchase_id=? AND pi.business_id=? FOR UPDATE");
                mysqli_stmt_bind_param($itemStmt, 'iii', $itemId, $purchaseId, $businessId);
                mysqli_stmt_execute($itemStmt);
                $item = mysqli_fetch_assoc(mysqli_stmt_get_result($itemStmt));
                if (!$item) throw new RuntimeException('A purchase return item is invalid.');
                $factor = normalizeInventoryDecimal($item['conversion_factor_to_base'], 'Purchase conversion factor');
                $quantity = convertTransactionQuantityToBase($enteredQuantity, $factor);
                $returnable = (float)$item['received_quantity'] - (float)$item['returned_quantity'];
                if ($quantity > $returnable + .00005) throw new RuntimeException('Return for ' . $item['name'] . ' exceeds the ' . formatInventoryDecimal(convertBaseQuantityToTransaction($returnable, $factor)) . ' ' . $item['purchase_uom'] . ' still returnable.');
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
                $returnUnits[] = ['product_id'=>(int)$item['product_id'],'selected_uom_id'=>(int)$item['purchase_uom_id'],'selected_uom_code'=>$item['purchase_uom'],'entered_quantity'=>$enteredQuantity,'conversion_factor_to_base'=>$factor,'base_quantity'=>$quantity,'normalized_base_cost'=>$unitCost];
                $returnedLines++;
            }
            if ($returnedLines === 0) throw new InvalidArgumentException('Enter at least one purchase return quantity.');
            writeAuditLog($conn, $businessId, 'PURCHASE_RETURNED', 'purchase_return', $purchaseReturnId, ['purchase_id'=>$purchaseId,'return_number'=>$returnNumber,'return_value'=>$returnValue,'lines'=>$returnedLines,'transaction_units'=>$returnUnits]);
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
