<?php
$page_title = 'Edit Purchase';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['update']);

$purchaseId = (int)($_GET['id'] ?? 0);
$config = getBusinessInventoryConfig($conn, $businessId);
$role = getPreviewRole();
$roleOnlyQuery = $role ? '?role=' . rawurlencode($role) : '';
$roleAppend = $role ? '&role=' . rawurlencode($role) : '';
$csrfToken = generateCsrfToken();

$purchaseStmt = mysqli_prepare($conn, "SELECT p.*,s.name supplier_name,l.name location_name,l.code location_code,COALESCE(SUM(pi.received_quantity),0) received_quantity FROM purchases p JOIN suppliers s ON s.id=p.supplier_id AND s.business_id=p.business_id JOIN business_locations l ON l.id=p.location_id AND l.business_id=p.business_id LEFT JOIN purchase_items pi ON pi.purchase_id=p.id AND pi.business_id=p.business_id WHERE p.id=? AND p.business_id=? GROUP BY p.id LIMIT 1");
mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
mysqli_stmt_execute($purchaseStmt);
$purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));
if (!$purchase) {
    http_response_code(404);
    ?><main class="purchase-edit-page"><section class="purchase-edit-card purchase-edit-missing"><h1>Purchase not found</h1><p>The requested purchase is not available in this business.</p><a class="purchase-edit-button primary" href="index<?php echo e($roleOnlyQuery); ?>">Back to Purchases</a></section></main><?php
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

$suppliers = [];
$supplierStmt = mysqli_prepare($conn, 'SELECT id,name,supplier_code,is_active FROM suppliers WHERE business_id=? ORDER BY is_active DESC,name');
mysqli_stmt_bind_param($supplierStmt, 'i', $businessId);mysqli_stmt_execute($supplierStmt);
$supplierResult = mysqli_stmt_get_result($supplierStmt);while ($row=mysqli_fetch_assoc($supplierResult)) $suppliers[]=$row;
$locations = [];
$locationStmt = mysqli_prepare($conn, 'SELECT id,name,code,is_active FROM business_locations WHERE business_id=? ORDER BY is_active DESC,name');
mysqli_stmt_bind_param($locationStmt, 'i', $businessId);mysqli_stmt_execute($locationStmt);
$locationResult = mysqli_stmt_get_result($locationStmt);while ($row=mysqli_fetch_assoc($locationResult)) $locations[]=$row;

$currencyStmt = mysqli_prepare($conn, 'SELECT currency_code FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($currencyStmt, 'i', $businessId);mysqli_stmt_execute($currencyStmt);
$currency = mysqli_fetch_assoc(mysqli_stmt_get_result($currencyStmt))['currency_code'] ?? 'RWF';
$localPurchaseDate = (new DateTimeImmutable($purchase['purchase_date'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($config['timezone']))->format('Y-m-d\TH:i');
$localNow = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
$balanceDue = max(0, (float)$purchase['total_amount'] - (float)$purchase['amount_paid']);
$locationLocked = (float)$purchase['received_quantity'] > 0.00005;
$typeLabel = ($purchase['purchase_type'] ?? 'PURCHASE_ORDER') === 'DIRECT' ? 'Direct Purchase' : 'Purchase Order';
$paymentLabel = $purchase['payment_status'] === 'PAID' ? 'Paid' : ($purchase['payment_status'] === 'PARTIALLY_PAID' ? 'Partially Paid' : 'Debt');
?>

<main class="purchase-edit-page">
  <nav class="purchase-edit-breadcrumb"><a href="index<?php echo e($roleOnlyQuery); ?>">Purchases</a><span>/</span><a href="view?id=<?php echo $purchaseId . e($roleAppend); ?>"><?php echo e($purchase['purchase_number']); ?></a><span>/</span><span>Edit</span></nav>
  <header class="purchase-edit-header"><div><span><?php echo e($typeLabel); ?></span><h1>Edit <?php echo e($purchase['purchase_number']); ?></h1><p>Update the purchase record or add another payment without changing its audit history.</p></div><a class="purchase-edit-button secondary" href="view?id=<?php echo $purchaseId . e($roleAppend); ?>">Cancel and Return</a></header>

  <section class="purchase-edit-summary">
    <article><span>Purchase Total</span><strong><?php echo formatCurrency($purchase['total_amount'], $currency); ?></strong></article>
    <article><span>Amount Paid</span><strong><?php echo formatCurrency($purchase['amount_paid'], $currency); ?></strong></article>
    <article><span>Balance Due</span><strong><?php echo formatCurrency($balanceDue, $currency); ?></strong></article>
    <article><span>Payment Status</span><strong><?php echo e($paymentLabel); ?></strong></article>
  </section>

  <div class="purchase-edit-layout">
    <section class="purchase-edit-card">
      <div class="purchase-edit-section-head"><div><h2>Purchase Details</h2><p>Product lines and received quantities remain unchanged to protect inventory accuracy.</p></div><span>Editable</span></div>
      <form action="backend<?php echo e($roleOnlyQuery); ?>" method="POST" class="purchase-edit-form">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="update_purchase"><input type="hidden" name="purchase_id" value="<?php echo $purchaseId; ?>"><input type="hidden" name="return_to" value="purchase_view:<?php echo $purchaseId; ?>">
        <div class="purchase-edit-grid">
          <label>Purchase Number<input name="purchase_number" maxlength="64" value="<?php echo e($purchase['purchase_number']); ?>" required></label>
          <label>Purchase Date<input type="datetime-local" name="purchase_date" value="<?php echo e($localPurchaseDate); ?>" required></label>
          <label>Supplier<select name="supplier_id" required><?php foreach($suppliers as $supplier):?><option value="<?php echo (int)$supplier['id']; ?>" <?php echo (int)$supplier['id']===(int)$purchase['supplier_id']?'selected':''; ?>><?php echo e($supplier['name'].' · '.$supplier['supplier_code'].((int)$supplier['is_active']===1?'':' · Inactive')); ?></option><?php endforeach;?></select></label>
          <label>Receiving Location<?php if($locationLocked):?><input type="hidden" name="location_id" value="<?php echo (int)$purchase['location_id']; ?>"><?php endif;?><select name="location_id" <?php echo $locationLocked?'disabled':''; ?> required><?php foreach($locations as $location):?><option value="<?php echo (int)$location['id']; ?>" <?php echo (int)$location['id']===(int)$purchase['location_id']?'selected':''; ?>><?php echo e($location['name'].' · '.$location['code'].((int)$location['is_active']===1?'':' · Inactive')); ?></option><?php endforeach;?></select><?php if($locationLocked):?><small>Locked because this purchase has already affected stock at this location.</small><?php endif;?></label>
          <label class="wide">Supplier Invoice Number <span>Optional</span><input name="supplier_invoice_number" maxlength="100" value="<?php echo e($purchase['supplier_invoice_number']); ?>" placeholder="Supplier invoice or delivery reference"></label>
          <label class="wide">Purchase Notes <span>Optional</span><textarea name="notes" rows="5" maxlength="2000" placeholder="Notes about this purchase"><?php echo e($purchase['notes']); ?></textarea></label>
        </div>
        <div class="purchase-edit-actions"><a class="purchase-edit-button secondary" href="view?id=<?php echo $purchaseId . e($roleAppend); ?>">Cancel Changes</a><button class="purchase-edit-button primary">Save Purchase Changes</button></div>
      </form>
    </section>

    <aside class="purchase-edit-card payment-panel" id="record-payment">
      <div class="purchase-edit-section-head"><div><h2>Record Payment</h2><p>Add a full or partial payment to the history.</p></div><span><?php echo formatCurrency($balanceDue,$currency); ?> due</span></div>
      <?php if($balanceDue <= 0.00005):?>
        <div class="purchase-paid-state"><strong>Paid</strong><p>No balance remains on this purchase.</p></div>
      <?php else:?>
      <form action="backend<?php echo e($roleOnlyQuery); ?>" method="POST" class="purchase-payment-form" id="additionalPaymentForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="idempotency_key" value="<?php echo e(createIdempotencyToken()); ?>"><input type="hidden" name="action" value="add_payment"><input type="hidden" name="purchase_id" value="<?php echo $purchaseId; ?>"><input type="hidden" name="return_to" value="purchase_view:<?php echo $purchaseId; ?>">
        <label>Payment Amount <span>Defaults to full balance</span><input type="number" name="payment_amount" id="additionalPaymentAmount" min=".0001" max="<?php echo e(number_format($balanceDue,4,'.','')); ?>" step=".0001" value="<?php echo e(number_format($balanceDue,2,'.','')); ?>" oninput="updateAdditionalPaymentBalance()" required></label>
        <div class="additional-payment-preview" id="additionalPaymentPreview"><span>Balance after this payment</span><strong id="additionalPaymentRemaining"><?php echo formatCurrency(0,$currency); ?></strong><small id="additionalPaymentWarning" hidden>Payment amount cannot be greater than the outstanding balance of <?php echo formatCurrency($balanceDue,$currency); ?>.</small></div>
        <label>Payment Date<input type="datetime-local" name="paid_at" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>" required></label>
        <label>Payment Method<select name="payment_method" id="editPaymentMethod" onchange="syncEditPaymentMethod()"><option value="CASH">Cash</option><option value="MOBILE_MONEY">Phone / Mobile Money</option><option value="BANK_TRANSFER">Bank Transfer</option></select></label>
        <label id="editPhoneField" hidden>Telephone Number<input type="tel" name="payment_phone" id="editPaymentPhone" maxlength="32" placeholder="Telephone number used"></label>
        <div class="edit-bank-fields" id="editBankFields" hidden><label>Bank Name<input name="bank_name" id="editBankName" maxlength="150" placeholder="Bank name"></label><label>Bank Account Number<input name="bank_account_number" id="editBankAccount" maxlength="120" placeholder="Account number"></label></div>
        <label>Reference <span>Optional</span><input name="payment_reference" maxlength="120" placeholder="Receipt or transaction reference"></label>
        <label>Payment Notes <span>Optional</span><textarea name="payment_notes" rows="3" maxlength="500"></textarea></label>
        <button class="purchase-edit-button payment-button" id="recordPaymentButton">Record Payment</button>
      </form>
      <?php endif;?>
    </aside>
  </div>
</main>

<style>
.purchase-edit-page{max-width:1380px;margin:0 auto}.purchase-edit-page [hidden]{display:none!important}.purchase-edit-breadcrumb{display:flex;gap:7px;margin-bottom:10px;color:var(--text3);font-size:9.5px}.purchase-edit-breadcrumb a{color:var(--blue);text-decoration:none}.purchase-edit-header{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 20px;border:1px solid var(--border);border-radius:13px;background:var(--card)}.purchase-edit-header>div>span{color:var(--blue);font-size:8px;font-weight:750;text-transform:uppercase;letter-spacing:.07em}.purchase-edit-header h1{margin:5px 0 0;color:var(--text);font-size:20px}.purchase-edit-header p{margin:5px 0 0;color:var(--text3);font-size:9.5px}.purchase-edit-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin:11px 0}.purchase-edit-summary article{padding:13px 15px;border:1px solid var(--border);border-radius:10px;background:var(--card)}.purchase-edit-summary span{display:block;color:var(--text3);font-size:8px;text-transform:uppercase}.purchase-edit-summary strong{display:block;margin-top:6px;color:var(--text);font-size:14px}.purchase-edit-layout{display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:11px;align-items:start}.purchase-edit-card{border:1px solid var(--border);border-radius:12px;background:var(--card);overflow:hidden}.purchase-edit-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border)}.purchase-edit-section-head h2{margin:0;color:var(--text);font-size:11px}.purchase-edit-section-head p{margin:4px 0 0;color:var(--text3);font-size:8.5px}.purchase-edit-section-head>span{color:var(--blue);font-size:8.5px;font-weight:700}.purchase-edit-form{padding:16px}.purchase-edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.purchase-edit-grid label,.purchase-payment-form label{display:flex;flex-direction:column;gap:6px;color:var(--text2);font-size:9px;font-weight:650}.purchase-edit-grid label>span,.purchase-payment-form label>span{color:var(--text3);font-size:8px;font-weight:400}.purchase-edit-grid input,.purchase-edit-grid select,.purchase-edit-grid textarea,.purchase-payment-form input,.purchase-payment-form select,.purchase-payment-form textarea{width:100%;min-height:39px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--text);font:inherit;font-size:9.5px}.purchase-edit-grid select:disabled{opacity:.72;background:var(--bg)}.purchase-edit-grid textarea,.purchase-payment-form textarea{resize:vertical}.purchase-edit-grid .wide{grid-column:1/-1}.purchase-edit-grid small{color:var(--orange);font-size:8px;font-weight:400;line-height:1.4}.purchase-edit-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:15px;border-top:1px solid var(--border)}.purchase-edit-button{display:inline-flex;align-items:center;justify-content:center;min-height:39px;padding:8px 14px;border:1px solid transparent;border-radius:8px;font:inherit;font-size:9.5px;font-weight:700;text-decoration:none;cursor:pointer}.purchase-edit-button.primary{background:var(--blue);border-color:var(--blue);color:#fff}.purchase-edit-button.secondary{background:var(--card);border-color:var(--border);color:var(--text2)}.purchase-payment-form{display:grid;gap:12px;padding:15px}.additional-payment-preview{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px;border:1px solid var(--border);border-radius:8px;background:var(--bg)}.additional-payment-preview span{color:var(--text3);font-size:8.5px}.additional-payment-preview strong{color:var(--orange);font-size:10.5px}.additional-payment-preview small{grid-column:1/-1;color:var(--red);font-size:8px;line-height:1.4}.additional-payment-preview.is-error{display:grid;grid-template-columns:1fr auto;border-color:var(--red);background:var(--red-bg)}.edit-bank-fields{display:grid;gap:12px}.payment-button{width:100%;margin-top:2px;background:var(--green);border-color:var(--green);color:#fff}.purchase-paid-state{padding:28px 18px;text-align:center}.purchase-paid-state strong{display:inline-flex;padding:6px 10px;border-radius:999px;background:var(--green-bg);color:var(--green);font-size:10px}.purchase-paid-state p{margin:8px 0 0;color:var(--text3);font-size:9px}.purchase-edit-missing{max-width:560px;margin:70px auto;padding:26px;text-align:center}.purchase-edit-missing p{color:var(--text3)}
@media(max-width:980px){.purchase-edit-layout{grid-template-columns:1fr}.payment-panel{max-width:none}}@media(max-width:680px){.purchase-edit-header{align-items:flex-start;flex-direction:column}.purchase-edit-summary,.purchase-edit-grid{grid-template-columns:1fr 1fr}.purchase-edit-header .purchase-edit-button{width:100%}}@media(max-width:480px){.purchase-edit-summary,.purchase-edit-grid{grid-template-columns:1fr}.purchase-edit-grid .wide{grid-column:auto}.purchase-edit-actions{display:grid}.purchase-edit-button{width:100%}}
</style>
<script>
const additionalPaymentDue=<?php echo json_encode($balanceDue); ?>;
const additionalPaymentCurrency=<?php echo json_encode($currency); ?>;
function updateAdditionalPaymentBalance(){const input=document.getElementById('additionalPaymentAmount');if(!input)return;const amount=parseFloat(input.value)||0;const overpaid=amount>additionalPaymentDue+.00005;const remaining=Math.max(0,additionalPaymentDue-amount);input.setCustomValidity(overpaid?'Payment amount cannot be greater than the outstanding balance.':'');document.getElementById('additionalPaymentRemaining').textContent=remaining.toFixed(2)+' '+additionalPaymentCurrency;document.getElementById('additionalPaymentWarning').hidden=!overpaid;document.getElementById('additionalPaymentPreview').classList.toggle('is-error',overpaid);}
function syncEditPaymentMethod(){const method=document.getElementById('editPaymentMethod')?.value||'CASH';const phone=document.getElementById('editPhoneField');const bank=document.getElementById('editBankFields');if(!phone||!bank)return;phone.hidden=method!=='MOBILE_MONEY';bank.hidden=method!=='BANK_TRANSFER';document.getElementById('editPaymentPhone').required=method==='MOBILE_MONEY';document.getElementById('editBankName').required=method==='BANK_TRANSFER';document.getElementById('editBankAccount').required=method==='BANK_TRANSFER';}
document.getElementById('additionalPaymentForm')?.addEventListener('submit',function(){const button=document.getElementById('recordPaymentButton');button.disabled=true;button.textContent='Recording Payment...';});
syncEditPaymentMethod();updateAdditionalPaymentBalance();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
