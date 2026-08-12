<?php
$page_title = 'Purchase Details';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['view']);

$canUpdatePurchase = hasPermission($conn, $membershipId, $businessId, $permissions['update']);
$canDeletePurchase = hasPermission($conn, $membershipId, $businessId, $permissions['delete']);
$canReceivePurchase = hasPermission($conn, $membershipId, $businessId, $permissions['receive']);
$config = getBusinessInventoryConfig($conn, $businessId);
$role = getPreviewRole();
$roleQuery = $role ? '&role=' . rawurlencode($role) : '';
$roleOnlyQuery = $role ? '?role=' . rawurlencode($role) : '';
$csrfToken = generateCsrfToken();
$purchaseId = (int)($_GET['id'] ?? 0);

$returnParams = [];
foreach (['search', 'status', 'type', 'page'] as $key) {
    if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') $returnParams[$key] = (string)$_GET[$key];
}
if ($role) $returnParams['role'] = $role;
$purchaseListUrl = 'index' . ($returnParams ? '?' . http_build_query($returnParams) : '');

$purchaseStmt = mysqli_prepare($conn, "SELECT p.*,s.supplier_code,s.name supplier_name,s.contact_person supplier_contact,s.phone supplier_phone,s.email supplier_email,s.tax_number supplier_tax_number,s.address supplier_address,s.notes supplier_notes,l.name location_name,l.code location_code,l.location_type,l.address location_address,CONCAT(cu.first_name,' ',cu.last_name) created_by,CONCAT(au.first_name,' ',au.last_name) approved_by,CONCAT(ru.first_name,' ',ru.last_name) received_by FROM purchases p JOIN suppliers s ON s.id=p.supplier_id AND s.business_id=p.business_id JOIN business_locations l ON l.id=p.location_id AND l.business_id=p.business_id LEFT JOIN business_memberships cbm ON cbm.id=p.created_by_membership_id AND cbm.business_id=p.business_id LEFT JOIN users cu ON cu.id=cbm.user_id LEFT JOIN business_memberships abm ON abm.id=p.approved_by_membership_id AND abm.business_id=p.business_id LEFT JOIN users au ON au.id=abm.user_id LEFT JOIN business_memberships rbm ON rbm.id=p.received_by_membership_id AND rbm.business_id=p.business_id LEFT JOIN users ru ON ru.id=rbm.user_id WHERE p.id=? AND p.business_id=? LIMIT 1");
mysqli_stmt_bind_param($purchaseStmt, 'ii', $purchaseId, $businessId);
mysqli_stmt_execute($purchaseStmt);
$purchase = mysqli_fetch_assoc(mysqli_stmt_get_result($purchaseStmt));

if (!$purchase) {
    http_response_code(404);
    ?>
    <main class="purchase-record-page"><section class="card purchase-record-missing"><h1>Purchase not found</h1><p>The requested purchase does not exist or is not available in this business.</p><a class="record-button record-button-primary" href="<?php echo e($purchaseListUrl); ?>">Back to Purchases</a></section></main>
    <?php require_once __DIR__ . '/../../includes/footer.php'; exit; ?>
<?php }

$items = [];
$itemStmt = mysqli_prepare($conn, "SELECT pi.*,pr.name product_name,pr.sku,pr.barcode,pr.uom,pb.lot_number,pb.expires_at,COALESCE((SELECT SUM(pri.quantity) FROM purchase_return_items pri JOIN purchase_returns prr ON prr.id=pri.purchase_return_id AND prr.business_id=pri.business_id WHERE pri.purchase_item_id=pi.id AND prr.status='COMPLETED'),0) returned_quantity FROM purchase_items pi JOIN products pr ON pr.id=pi.product_id AND pr.business_id=pi.business_id LEFT JOIN product_batches pb ON pb.id=pi.batch_id AND pb.business_id=pi.business_id WHERE pi.purchase_id=? AND pi.business_id=? ORDER BY pi.id");
mysqli_stmt_bind_param($itemStmt, 'ii', $purchaseId, $businessId);mysqli_stmt_execute($itemStmt);
$itemResult = mysqli_stmt_get_result($itemStmt);while ($row = mysqli_fetch_assoc($itemResult)) $items[] = $row;

$payments = [];
$paymentStmt = mysqli_prepare($conn, "SELECT pp.*,CONCAT(u.first_name,' ',u.last_name) recorded_by FROM purchase_payments pp LEFT JOIN business_memberships bm ON bm.id=pp.recorded_by_membership_id AND bm.business_id=pp.business_id LEFT JOIN users u ON u.id=bm.user_id WHERE pp.purchase_id=? AND pp.business_id=? ORDER BY pp.paid_at ASC,pp.id ASC");
mysqli_stmt_bind_param($paymentStmt, 'ii', $purchaseId, $businessId);mysqli_stmt_execute($paymentStmt);
$paymentResult = mysqli_stmt_get_result($paymentStmt);$runningPaid=0.0;$paymentSequence=0;while ($row = mysqli_fetch_assoc($paymentResult)) {$runningPaid+=(float)$row['amount'];$paymentSequence++;$row['payment_sequence']=$paymentSequence;$row['remaining_balance']=max(0,(float)$purchase['total_amount']-$runningPaid);$payments[]=$row;}$payments=array_reverse($payments);

$returns = [];
$returnStmt = mysqli_prepare($conn, "SELECT pr.return_number,pr.return_date,pr.reason,pr.status,pri.quantity,pri.line_total,p.name product_name,p.uom,CONCAT(u.first_name,' ',u.last_name) created_by FROM purchase_returns pr JOIN purchase_return_items pri ON pri.purchase_return_id=pr.id AND pri.business_id=pr.business_id JOIN products p ON p.id=pri.product_id AND p.business_id=pri.business_id LEFT JOIN business_memberships bm ON bm.id=pr.created_by_membership_id AND bm.business_id=pr.business_id LEFT JOIN users u ON u.id=bm.user_id WHERE pr.purchase_id=? AND pr.business_id=? ORDER BY pr.return_date DESC,pr.id DESC,pri.id");
mysqli_stmt_bind_param($returnStmt, 'ii', $purchaseId, $businessId);mysqli_stmt_execute($returnStmt);
$returnResult = mysqli_stmt_get_result($returnStmt);while ($row = mysqli_fetch_assoc($returnResult)) $returns[] = $row;

$businessStmt = mysqli_prepare($conn, 'SELECT currency_code FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($businessStmt, 'i', $businessId);mysqli_stmt_execute($businessStmt);
$currency = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt))['currency_code'] ?? 'RWF';

$statusMeta = match ($purchase['status']) {
    'RECEIVED' => ['Fully Received', 'pill-green'],
    'PARTIALLY_RECEIVED' => ['Partially Received', 'pill-amber'],
    'ORDERED' => ['Ordered / Pending', 'pill-blue'],
    'DRAFT' => ['Draft', 'pill-amber'],
    default => ['Cancelled', 'pill-red'],
};
$receivedValue = 0.0;$remainingValue = 0.0;$returnedValue = 0.0;$orderedQuantity = 0.0;$receivedQuantity = 0.0;
foreach ($items as $item) {
    $receivedValue += (float)$item['received_quantity'] * (float)$item['unit_cost'];
    $remainingValue += max(0, (float)$item['ordered_quantity'] - (float)$item['received_quantity']) * (float)$item['unit_cost'];
    $returnedValue += (float)$item['returned_quantity'] * (float)$item['unit_cost'];
    $orderedQuantity += (float)$item['ordered_quantity'];
    $receivedQuantity += (float)$item['received_quantity'];
}
$progress = $orderedQuantity > 0 ? min(100, ($receivedQuantity / $orderedQuantity) * 100) : 0;
$balanceDue = max(0, (float)$purchase['total_amount'] - (float)$purchase['amount_paid']);
$returnTo = 'purchase_view:' . $purchaseId;
$isDirectPurchase = ($purchase['purchase_type'] ?? 'PURCHASE_ORDER') === 'DIRECT';
$purchaseTypeLabel = $isDirectPurchase ? 'Direct Purchase' : 'Purchase Order';
?>

<main class="purchase-record-page">
  <nav class="record-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo e($purchaseListUrl); ?>">Purchases</a><span>/</span><span><?php echo e($purchase['purchase_number']); ?></span></nav>

  <header class="record-header">
    <div><span class="record-eyebrow"><?php echo e($purchaseTypeLabel); ?></span><h1><?php echo e($purchase['purchase_number']); ?></h1><p><?php echo e($purchase['supplier_name']); ?> &middot; Created <?php echo e(formatDate($purchase['purchase_date'], $config['timezone'], 'd M Y, H:i')); ?></p></div>
    <div class="record-header-actions">
      <span class="status-pill <?php echo $statusMeta[1]; ?>"><?php echo e($statusMeta[0]); ?></span>
      <a class="record-button record-button-secondary" href="<?php echo e($purchaseListUrl); ?>">Back to Purchases</a>
      <?php if ($canUpdatePurchase): ?><a class="record-button record-button-secondary" href="edit?id=<?php echo $purchaseId . $roleQuery; ?>">Edit Purchase</a><?php endif; ?>
      <?php if ($canUpdatePurchase && $balanceDue > 0.00005): ?><a class="record-button record-button-payment" href="edit?id=<?php echo $purchaseId . $roleQuery; ?>#record-payment">Pay Remaining Balance</a><?php endif; ?>
      <?php if ($purchase['status'] === 'DRAFT' && $canUpdatePurchase): ?>
        <form action="backend<?php echo e($roleOnlyQuery); ?>" method="POST" onsubmit="return confirm('Mark this purchase order as ordered?');"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="mark_ordered"><input type="hidden" name="purchase_id" value="<?php echo $purchaseId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>"><button class="record-button record-button-primary">Mark as Ordered</button></form>
      <?php elseif (in_array($purchase['status'], ['ORDERED','PARTIALLY_RECEIVED'], true) && $canReceivePurchase): ?>
        <a class="record-button record-button-primary" href="../receiving/view?id=<?php echo $purchaseId . $roleQuery; ?>">Receive Purchase</a>
      <?php endif; ?>
      <?php if (in_array($purchase['status'], ['PARTIALLY_RECEIVED','RECEIVED'], true) && $canReceivePurchase): ?><a class="record-button record-button-secondary" href="index?return_id=<?php echo $purchaseId; ?>&return_to=view<?php echo e($roleQuery); ?>">Return Purchased Stock</a><?php endif; ?>
      <?php if ($canDeletePurchase): ?><form action="backend<?php echo e($roleOnlyQuery); ?>" method="POST" onsubmit="return confirm('Delete <?php echo e($purchase['purchase_number']); ?>? Any received stock will be reversed. This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="delete_purchase"><input type="hidden" name="purchase_id" value="<?php echo $purchaseId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>"><button class="record-button record-button-danger">Delete Purchase</button></form><?php endif; ?>
    </div>
  </header>

  <section class="record-summary" aria-label="Purchase summary">
    <article><span>Purchase Total</span><strong><?php echo formatCurrency($purchase['total_amount'], $currency); ?></strong><small><?php echo e($purchaseTypeLabel); ?> &middot; <?php echo count($items); ?> line(s)</small></article>
    <article><span>Received</span><strong><?php echo formatCurrency($receivedValue, $currency); ?></strong><small><?php echo e(number_format($progress, 1)); ?>% of ordered quantity</small></article>
    <article><span>Payment Status</span><strong><?php echo e(ucwords(strtolower(str_replace('_',' ',$purchase['payment_status']??($balanceDue>0?'DEBT':'PAID'))))); ?></strong><small><?php echo formatCurrency($purchase['amount_paid'], $currency); ?> paid &middot; <?php echo formatCurrency($balanceDue, $currency); ?> due</small></article>
    <article><span>Pending Receipt</span><strong><?php echo formatCurrency($remainingValue, $currency); ?></strong><small><?php echo formatCurrency($returnedValue, $currency); ?> returned</small></article>
  </section>

  <div class="record-layout">
    <div class="record-main">
      <section class="record-section"><div class="record-section-heading"><div><h2>Purchased Items</h2><p>Products, receiving progress, batches, and pricing.</p></div><span><?php echo count($items); ?> lines</span></div><div class="record-progress"><div><span style="width:<?php echo e(number_format($progress, 2, '.', '')); ?>%"></span></div><small><?php echo e(number_format($progress, 1)); ?>% received</small></div><div class="record-table-scroll"><table class="data-table record-items-table"><thead><tr><th>Product</th><th>Batch / Expiry</th><th>Quantities</th><th>Pricing</th><th>Adjustments</th><th>Line Total</th></tr></thead><tbody><?php foreach ($items as $item): $pending=max(0,(float)$item['ordered_quantity']-(float)$item['received_quantity']); ?><tr><td><strong><?php echo e($item['product_name']); ?></strong><small>SKU <?php echo e($item['sku']); ?><?php if($item['barcode']):?> &middot; Barcode <?php echo e($item['barcode']); ?><?php endif;?></small></td><td><strong><?php echo e($item['lot_number'] ?: 'No batch'); ?></strong><?php if($item['expires_at']):?><small>Expires <?php echo e(date('d M Y', strtotime($item['expires_at']))); ?></small><?php else:?><small>No expiry date</small><?php endif;?></td><td class="record-quantity-cell"><small>Ordered <b><?php echo number_format($item['ordered_quantity'],4); ?> <?php echo e($item['uom']); ?></b></small><small class="record-positive">Received <b><?php echo number_format($item['received_quantity'],4); ?></b></small><small>Returned <b><?php echo number_format($item['returned_quantity'],4); ?></b></small><small class="record-warning">Pending <b><?php echo number_format($pending,4); ?></b></small></td><td><small>Unit cost <b><?php echo formatCurrency($item['unit_cost'],$currency); ?></b></small><small>Sale price <b><?php echo formatCurrency($item['unit_selling_price'],$currency); ?></b></small></td><td><small>Discount <b><?php echo formatCurrency($item['discount_amount'],$currency); ?></b></small><small>Tax <b><?php echo formatCurrency($item['tax_amount'],$currency); ?></b></small></td><td class="record-total"><?php echo formatCurrency($item['line_total'],$currency); ?></td></tr><?php endforeach;?></tbody></table></div></section>

      <div class="record-activity-grid">
        <section class="record-section"><div class="record-section-heading"><div><h2>Payment History</h2><p>Every payment with its method, date, and balance remaining afterward.</p></div><span><?php echo count($payments); ?> records</span></div><?php if(!$payments):?><div class="record-empty"><strong>Recorded as supplier debt</strong><p>No payment has been recorded. The outstanding balance is <?php echo formatCurrency($balanceDue,$currency); ?>.</p><?php if($canUpdatePurchase):?><a class="record-button record-button-payment record-empty-action" href="edit?id=<?php echo $purchaseId . $roleQuery; ?>#record-payment">Record First Payment</a><?php endif;?></div><?php else:?><div class="record-list"><?php foreach($payments as $payment):$methodLabel=$payment['payment_method']==='MOBILE_MONEY'?'Phone / Mobile Money':($payment['payment_method']==='BANK_TRANSFER'?'Bank Transfer':ucwords(strtolower(str_replace('_',' ',$payment['payment_method']))));?><article><div><strong>Payment #<?php echo (int)$payment['payment_sequence']; ?> &middot; <?php echo e($methodLabel); ?></strong><small><?php echo e(formatDate($payment['paid_at'],$config['timezone'],'d M Y, H:i')); ?> &middot; <?php echo e(trim((string)$payment['recorded_by'])?:'System'); ?></small><p><?php if($payment['payment_method']==='MOBILE_MONEY'):?>Telephone: <?php echo e($payment['phone_number']?:'Not recorded'); ?><?php elseif($payment['payment_method']==='BANK_TRANSFER'):?>Bank: <?php echo e($payment['bank_name']?:'Not recorded'); ?> &middot; Account: <?php echo e($payment['bank_account_number']?:'Not recorded'); ?><?php else:?>Cash payment<?php endif;?><?php if($payment['reference_number']):?> &middot; Reference: <?php echo e($payment['reference_number']); ?><?php endif;?><?php if($payment['notes']):?> &middot; <?php echo e($payment['notes']); ?><?php endif;?></p></div><div class="payment-ledger-values"><span>Amount paid</span><b><?php echo formatCurrency($payment['amount'],$currency); ?></b><small>Balance: <?php echo formatCurrency($payment['remaining_balance'],$currency); ?></small></div></article><?php endforeach;?></div><?php endif;?></section>
        <section class="record-section"><div class="record-section-heading"><div><h2>Return History</h2><p>Stock returned against this order.</p></div><span><?php echo count($returns); ?> lines</span></div><?php if(!$returns):?><div class="record-empty">No purchased stock has been returned.</div><?php else:?><div class="record-list"><?php foreach($returns as $return):?><article><div><strong><?php echo e($return['return_number'].' · '.$return['product_name']); ?></strong><small><?php echo e(formatDate($return['return_date'],$config['timezone'],'d M Y, H:i')); ?> &middot; <?php echo number_format($return['quantity'],4).' '.e($return['uom']); ?></small><p><?php echo e($return['reason']?:'No return reason'); ?> &middot; <?php echo e(trim((string)$return['created_by'])?:'System'); ?></p></div><b><?php echo formatCurrency($return['line_total'],$currency); ?></b></article><?php endforeach;?></div><?php endif;?></section>
      </div>
    </div>

    <aside class="record-sidebar">
      <section class="record-section record-details"><h2>Supplier</h2><dl><div><dt>Name</dt><dd><?php echo e($purchase['supplier_name']); ?></dd></div><div><dt>Code</dt><dd><?php echo e($purchase['supplier_code']); ?></dd></div><div><dt>Contact</dt><dd><?php echo e($purchase['supplier_contact']?:'Not recorded'); ?></dd></div><div><dt>Phone</dt><dd><?php echo e($purchase['supplier_phone']?:'Not recorded'); ?></dd></div><div><dt>Email</dt><dd><?php echo e($purchase['supplier_email']?:'Not recorded'); ?></dd></div><div><dt>Tax number</dt><dd><?php echo e($purchase['supplier_tax_number']?:'Not recorded'); ?></dd></div><div><dt>Address</dt><dd><?php echo e($purchase['supplier_address']?:'Not recorded'); ?></dd></div></dl></section>
      <section class="record-section record-details"><h2>Purchase &amp; Delivery</h2><dl><div><dt>Method</dt><dd><?php echo e($purchaseTypeLabel); ?></dd></div><div><dt>Supplier invoice</dt><dd><?php echo e($purchase['supplier_invoice_number']?:'Not recorded'); ?></dd></div><div><dt>Deliver to</dt><dd><?php echo e($purchase['location_name'].' ('.$purchase['location_code'].')'); ?></dd></div><div><dt>Location type</dt><dd><?php echo e(ucwords(strtolower($purchase['location_type']))); ?></dd></div><div><dt>Expected date</dt><dd><?php echo $purchase['expected_date']?e(date('d M Y',strtotime($purchase['expected_date']))):'Not specified'; ?></dd></div><div><dt>Received at</dt><dd><?php echo $purchase['received_at']?e(formatDate($purchase['received_at'],$config['timezone'],'d M Y, H:i')):'Not completed'; ?></dd></div><div><dt>Delivery address</dt><dd><?php echo e($purchase['location_address']?:'Not recorded'); ?></dd></div></dl></section>
      <section class="record-section record-details"><h2>Financial Breakdown</h2><dl class="record-money-list"><div><dt>Subtotal</dt><dd><?php echo formatCurrency($purchase['subtotal'],$currency); ?></dd></div><div><dt>Discount</dt><dd><?php echo formatCurrency($purchase['discount_amount'],$currency); ?></dd></div><div><dt>Tax</dt><dd><?php echo formatCurrency($purchase['tax_amount'],$currency); ?></dd></div><div><dt>Shipping</dt><dd><?php echo formatCurrency($purchase['shipping_amount'],$currency); ?></dd></div><div class="record-money-total"><dt>Order total</dt><dd><?php echo formatCurrency($purchase['total_amount'],$currency); ?></dd></div><div><dt>Amount paid</dt><dd><?php echo formatCurrency($purchase['amount_paid'],$currency); ?></dd></div><div><dt>Balance due</dt><dd><?php echo formatCurrency($balanceDue,$currency); ?></dd></div></dl></section>
      <section class="record-section record-details"><h2>Workflow</h2><dl><div><dt>Created by</dt><dd><?php echo e(trim((string)$purchase['created_by'])?:'System'); ?></dd></div><div><dt>Approved by</dt><dd><?php echo e(trim((string)$purchase['approved_by'])?:'Not approved'); ?></dd></div><div><dt>Received by</dt><dd><?php echo e(trim((string)$purchase['received_by'])?:'Not received'); ?></dd></div><div><dt>Created</dt><dd><?php echo e(formatDate($purchase['created_at'],$config['timezone'],'d M Y, H:i')); ?></dd></div><div><dt>Last updated</dt><dd><?php echo e(formatDate($purchase['updated_at'],$config['timezone'],'d M Y, H:i')); ?></dd></div></dl></section>
      <section class="record-section record-notes"><h2>Notes</h2><h3>Purchase notes</h3><p><?php echo nl2br(e($purchase['notes']?:'No purchase notes recorded.')); ?></p><h3>Supplier notes</h3><p><?php echo nl2br(e($purchase['supplier_notes']?:'No supplier notes recorded.')); ?></p></section>
    </aside>
  </div>
</main>

<style>
.purchase-record-page{max-width:1560px;margin:0 auto}.record-breadcrumb{display:flex;align-items:center;gap:7px;margin-bottom:10px;color:var(--text3);font-size:9.5px}.record-breadcrumb a{color:var(--blue);text-decoration:none}.record-header{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 20px;border:1px solid var(--border);border-radius:13px;background:var(--card)}.record-eyebrow{display:block;color:var(--text3);font-size:8.5px;text-transform:uppercase;letter-spacing:.06em}.record-header h1{margin:5px 0 0;color:var(--text);font-size:21px}.record-header p{margin:5px 0 0;color:var(--text3);font-size:10px}.record-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}.record-header-actions form{margin:0}.record-button{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border:1px solid transparent;border-radius:8px;font:inherit;font-size:9.5px;font-weight:650;text-decoration:none;cursor:pointer;transition:background .15s,border-color .15s}.record-button-primary{background:var(--blue);border-color:var(--blue);color:#fff}.record-button-primary:hover{filter:brightness(.94)}.record-button-secondary{background:var(--card);border-color:var(--border);color:var(--text2)}.record-button-secondary:hover{background:var(--bg);border-color:var(--border-hover);color:var(--text)}.record-button-payment{background:var(--green);border-color:var(--green);color:#fff}.record-button-payment:hover{filter:brightness(.94)}.record-button-danger{background:var(--card);border-color:color-mix(in srgb,var(--red) 45%,var(--border));color:var(--red)}.record-button-danger:hover{background:var(--red-bg)}.record-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin:11px 0}.record-summary article{padding:13px 15px;border:1px solid var(--border);border-radius:10px;background:var(--card)}.record-summary span,.record-summary small{display:block;color:var(--text3);font-size:8.5px}.record-summary span{text-transform:uppercase;letter-spacing:.04em}.record-summary strong{display:block;margin:6px 0 3px;color:var(--text);font-size:15px}.record-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:11px;align-items:start}.record-main,.record-sidebar{display:flex;flex-direction:column;gap:11px;min-width:0}.record-section{border:1px solid var(--border);border-radius:11px;background:var(--card);overflow:hidden}.record-section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border-bottom:1px solid var(--border)}.record-section-heading h2,.record-details h2,.record-notes h2{margin:0;color:var(--text);font-size:11px}.record-section-heading p{margin:3px 0 0;color:var(--text3);font-size:8.5px}.record-section-heading>span{padding:4px 7px;border-radius:999px;background:var(--bg);color:var(--text3);font-size:8px}.record-progress{display:flex;align-items:center;gap:9px;padding:9px 15px;background:var(--bg)}.record-progress>div{width:190px;height:6px;border-radius:99px;background:var(--card);overflow:hidden}.record-progress>div span{display:block;height:100%;border-radius:99px;background:var(--green)}.record-progress small{color:var(--text3);font-size:8.5px}.record-table-scroll{max-height:55vh;overflow:auto}.record-items-table{min-width:880px}.record-items-table th{position:sticky;top:0;z-index:2;background:var(--card);font-size:8px!important}.record-items-table td{vertical-align:middle}.record-items-table td strong,.record-items-table td small{display:block}.record-items-table td small{margin-top:4px;color:var(--text3);font-size:8px}.record-items-table td small b{display:inline;color:var(--text2);font-weight:650}.record-quantity-cell{min-width:155px}.record-positive{color:var(--green)!important;font-weight:650}.record-warning{color:var(--orange)!important;font-weight:650}.record-total{color:var(--text)!important;font-weight:700}.record-activity-grid{display:grid;grid-template-columns:1fr 1fr;gap:11px}.record-empty{padding:24px 15px;color:var(--text3);font-size:9.5px;text-align:center}.record-empty-action{margin-top:10px}.record-list{display:flex;flex-direction:column}.record-list article{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:5px 10px;padding:11px 14px;border-bottom:1px solid var(--border)}.record-list article:last-child{border-bottom:0}.record-list strong,.record-list small{display:block}.record-list strong{font-size:9.5px}.record-list small,.record-list p{color:var(--text3);font-size:8.5px}.record-list small{margin-top:3px}.record-list p{margin:4px 0 0}.record-list b{font-size:10px}.payment-ledger-values{min-width:125px;text-align:right}.payment-ledger-values span{display:block;color:var(--text3);font-size:7.5px;text-transform:uppercase}.payment-ledger-values b{display:block;margin-top:3px;color:var(--text);font-size:10px}.payment-ledger-values small{margin-top:4px;color:var(--orange);font-weight:650}.record-details,.record-notes{padding:14px}.record-details h2,.record-notes h2{padding-bottom:10px;border-bottom:1px solid var(--border)}.record-details dl{display:grid;gap:0;margin:2px 0 0}.record-details dl>div{display:grid;grid-template-columns:105px minmax(0,1fr);gap:10px;padding:8px 0;border-bottom:1px solid var(--table-border)}.record-details dl>div:last-child{border-bottom:0}.record-details dt{color:var(--text3);font-size:8.5px}.record-details dd{margin:0;color:var(--text2);font-size:9px;font-weight:550;text-align:right;overflow-wrap:anywhere}.record-money-list .record-money-total{margin:2px -5px;padding:9px 5px;background:var(--blue-bg);border-radius:7px}.record-money-total dd{color:var(--blue);font-weight:750}.record-notes h3{margin:11px 0 0;color:var(--text3);font-size:8px;text-transform:uppercase}.record-notes p{margin:5px 0 0;color:var(--text2);font-size:9px;line-height:1.55}.purchase-record-missing{max-width:600px;margin:70px auto;padding:28px;text-align:center}.purchase-record-missing h1{margin:0}.purchase-record-missing p{margin:8px 0 18px;color:var(--text3)}
@media(max-width:1120px){.record-layout{grid-template-columns:1fr}.record-sidebar{display:grid;grid-template-columns:1fr 1fr}.record-notes{grid-column:1/-1}}
@media(max-width:760px){.record-header{align-items:flex-start;flex-direction:column}.record-header-actions{justify-content:flex-start;width:100%}.record-summary,.record-sidebar,.record-activity-grid{grid-template-columns:1fr 1fr}.record-layout{grid-template-columns:1fr}.record-button{flex:1}.record-notes{grid-column:1/-1}}
@media(max-width:520px){.record-summary,.record-sidebar,.record-activity-grid{grid-template-columns:1fr}.record-notes{grid-column:auto}.record-header-actions{align-items:stretch;flex-direction:column}.record-header-actions .status-pill{align-self:flex-start}.record-button{width:100%}}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
