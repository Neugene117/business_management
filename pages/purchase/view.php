<?php
$page_title = 'Purchase Details';
$extra_css = ['purchase-view.css'];
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
$itemStmt = mysqli_prepare($conn, "SELECT pi.*,pr.name product_name,pr.sku,pr.barcode,pr.uom,u.code purchase_uom,pb.lot_number,COALESCE(pi.expiry_date,pb.expires_at) expires_at,COALESCE((SELECT SUM(pri.quantity) FROM purchase_return_items pri JOIN purchase_returns prr ON prr.id=pri.purchase_return_id AND prr.business_id=pri.business_id WHERE pri.purchase_item_id=pi.id AND prr.status='COMPLETED'),0) returned_quantity FROM purchase_items pi JOIN products pr ON pr.id=pi.product_id AND pr.business_id=pi.business_id JOIN units_of_measure u ON u.id=pi.purchase_uom_id LEFT JOIN product_batches pb ON pb.id=pi.batch_id AND pb.business_id=pi.business_id WHERE pi.purchase_id=? AND pi.business_id=? ORDER BY pi.id");
mysqli_stmt_bind_param($itemStmt, 'ii', $purchaseId, $businessId);
mysqli_stmt_execute($itemStmt);
$itemResult = mysqli_stmt_get_result($itemStmt);
while ($row = mysqli_fetch_assoc($itemResult)) $items[] = $row;

$payments = [];
$paymentStmt = mysqli_prepare($conn, "SELECT pp.*,CONCAT(u.first_name,' ',u.last_name) recorded_by FROM purchase_payments pp LEFT JOIN business_memberships bm ON bm.id=pp.recorded_by_membership_id AND bm.business_id=pp.business_id LEFT JOIN users u ON u.id=bm.user_id WHERE pp.purchase_id=? AND pp.business_id=? ORDER BY pp.paid_at ASC,pp.id ASC");
mysqli_stmt_bind_param($paymentStmt, 'ii', $purchaseId, $businessId);
mysqli_stmt_execute($paymentStmt);
$paymentResult = mysqli_stmt_get_result($paymentStmt);
$runningPaid = 0.0;
$paymentSequence = 0;
while ($row = mysqli_fetch_assoc($paymentResult)) {
    $runningPaid += (float)$row['amount'];
    $paymentSequence++;
    $row['payment_sequence'] = $paymentSequence;
    $row['remaining_balance'] = max(0, (float)$purchase['total_amount'] - $runningPaid);
    $payments[] = $row;
}
$payments = array_reverse($payments);

$returns = [];
$returnStmt = mysqli_prepare($conn, "SELECT pr.return_number,pr.return_date,pr.reason,pr.status,pri.quantity,pri.line_total,p.name product_name,pi.conversion_factor_to_base,tu.code transaction_uom,CONCAT(u.first_name,' ',u.last_name) created_by FROM purchase_returns pr JOIN purchase_return_items pri ON pri.purchase_return_id=pr.id AND pri.business_id=pr.business_id JOIN purchase_items pi ON pi.id=pri.purchase_item_id AND pi.business_id=pri.business_id JOIN products p ON p.id=pri.product_id AND p.business_id=pri.business_id JOIN units_of_measure tu ON tu.id=pi.purchase_uom_id LEFT JOIN business_memberships bm ON bm.id=pr.created_by_membership_id AND bm.business_id=pr.business_id LEFT JOIN users u ON u.id=bm.user_id WHERE pr.purchase_id=? AND pr.business_id=? ORDER BY pr.return_date DESC,pr.id DESC,pri.id");
mysqli_stmt_bind_param($returnStmt, 'ii', $purchaseId, $businessId);
mysqli_stmt_execute($returnStmt);
$returnResult = mysqli_stmt_get_result($returnStmt);
while ($row = mysqli_fetch_assoc($returnResult)) $returns[] = $row;

$businessStmt = mysqli_prepare($conn, 'SELECT currency_code FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($businessStmt, 'i', $businessId);
mysqli_stmt_execute($businessStmt);
$currency = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt))['currency_code'] ?? 'RWF';

$statusMeta = match ($purchase['status']) {
    'RECEIVED' => ['Fully Received', 'pill-green'],
    'PARTIALLY_RECEIVED' => ['Partially Received', 'pill-amber'],
    'ORDERED' => ['Ordered / Pending', 'pill-blue'],
    'DRAFT' => ['Draft', 'pill-amber'],
    default => ['Cancelled', 'pill-red'],
};
$receivedValue = 0.0;
$remainingValue = 0.0;
$returnedValue = 0.0;
$orderedQuantity = 0.0;
$receivedQuantity = 0.0;
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
$paymentStatusLabel = ucwords(strtolower(str_replace('_', ' ', $purchase['payment_status'] ?? ($balanceDue > 0 ? 'DEBT' : 'PAID'))));
?>

<main class="purchase-record-page">
  <nav class="record-breadcrumb" aria-label="Breadcrumb">
    <a href="<?php echo e($purchaseListUrl); ?>">Purchases</a><span aria-hidden="true">&rsaquo;</span><span><?php echo e($purchase['purchase_number']); ?></span>
  </nav>

  <header class="record-header">
    <div class="record-header-copy">
      <div class="record-title-meta"><span class="record-eyebrow"><?php echo e($purchaseTypeLabel); ?></span><span class="status-pill <?php echo $statusMeta[1]; ?>"><?php echo e($statusMeta[0]); ?></span></div>
      <h1><?php echo e($purchase['purchase_number']); ?></h1>
      <p><strong><?php echo e($purchase['supplier_name']); ?></strong><span>&middot;</span><?php echo e(formatDate($purchase['purchase_date'], $config['timezone'], 'd M Y, H:i')); ?><span>&middot;</span><?php echo e($purchase['location_name']); ?></p>
    </div>
    <div class="record-header-actions">
      <a class="record-button record-button-secondary" href="<?php echo e($purchaseListUrl); ?>">Back</a>
      <?php if ($canUpdatePurchase): ?><a class="record-button record-button-secondary" href="edit?id=<?php echo $purchaseId . $roleQuery; ?>">Edit Purchase</a><?php endif; ?>
      <?php if ($canUpdatePurchase && $balanceDue > 0.00005): ?><a class="record-button record-button-payment" href="edit?id=<?php echo $purchaseId . $roleQuery; ?>#record-payment">Pay Balance</a><?php endif; ?>
      <?php if ($purchase['status'] === 'DRAFT' && $canUpdatePurchase): ?>
        <form action="backend<?php echo e($roleOnlyQuery); ?>" method="POST" onsubmit="return confirm('Mark this purchase order as ordered?');"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="mark_ordered"><input type="hidden" name="purchase_id" value="<?php echo $purchaseId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>"><button class="record-button record-button-primary">Mark as Ordered</button></form>
      <?php elseif (in_array($purchase['status'], ['ORDERED','PARTIALLY_RECEIVED'], true) && $canReceivePurchase): ?>
        <a class="record-button record-button-primary" href="../receiving/view?id=<?php echo $purchaseId . $roleQuery; ?>">Receive Purchase</a>
      <?php endif; ?>
      <?php if ((in_array($purchase['status'], ['PARTIALLY_RECEIVED','RECEIVED'], true) && $canReceivePurchase) || $canDeletePurchase): ?>
        <details class="record-action-menu">
          <summary class="record-button record-button-secondary">More actions</summary>
          <div class="record-action-menu-panel">
            <?php if (in_array($purchase['status'], ['PARTIALLY_RECEIVED','RECEIVED'], true) && $canReceivePurchase): ?><a href="index?return_id=<?php echo $purchaseId; ?>&return_to=view<?php echo e($roleQuery); ?>">Return purchased stock</a><?php endif; ?>
            <?php if ($canDeletePurchase): ?><form action="backend<?php echo e($roleOnlyQuery); ?>" method="POST" onsubmit="return confirm('Delete <?php echo e($purchase['purchase_number']); ?>? Any received stock will be reversed. This action cannot be undone.');"><input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="delete_purchase"><input type="hidden" name="purchase_id" value="<?php echo $purchaseId; ?>"><input type="hidden" name="return_to" value="<?php echo e($returnTo); ?>"><button class="record-menu-danger">Delete purchase</button></form><?php endif; ?>
          </div>
        </details>
      <?php endif; ?>
    </div>
  </header>

  <section class="record-summary" aria-label="Purchase summary">
    <article class="record-summary-total"><span>Purchase total</span><strong><?php echo formatCurrency($purchase['total_amount'], $currency); ?></strong><small><?php echo count($items); ?> product line(s)</small></article>
    <article class="record-summary-received"><span>Stock received</span><strong><?php echo formatCurrency($receivedValue, $currency); ?></strong><small><?php echo e(number_format($progress, 1)); ?>% of ordered quantity</small><div class="record-summary-progress"><i style="width:<?php echo e(number_format($progress, 2, '.', '')); ?>%"></i></div></article>
    <article class="record-summary-payment"><span>Payment status</span><strong><?php echo e($paymentStatusLabel); ?></strong><small><?php echo formatCurrency($purchase['amount_paid'], $currency); ?> paid &middot; <?php echo formatCurrency($balanceDue, $currency); ?> due</small></article>
    <article class="record-summary-pending"><span>Pending receipt</span><strong><?php echo formatCurrency($remainingValue, $currency); ?></strong><small><?php echo formatCurrency($returnedValue, $currency); ?> returned</small></article>
  </section>

  <section class="record-section record-overview">
    <div class="record-section-heading"><div><h2>Purchase overview</h2><p>Supplier, delivery, and workflow information in one place.</p></div></div>
    <div class="record-overview-grid">
      <article class="record-info-card">
        <h3>Supplier information</h3>
        <dl><div><dt>Name</dt><dd><?php echo e($purchase['supplier_name']); ?></dd></div><div><dt>Supplier code</dt><dd><?php echo e($purchase['supplier_code']); ?></dd></div><div><dt>Contact person</dt><dd><?php echo e($purchase['supplier_contact']?:'Not recorded'); ?></dd></div><div><dt>Phone</dt><dd><?php echo e($purchase['supplier_phone']?:'Not recorded'); ?></dd></div><div><dt>Email</dt><dd><?php echo e($purchase['supplier_email']?:'Not recorded'); ?></dd></div><div><dt>Tax number</dt><dd><?php echo e($purchase['supplier_tax_number']?:'Not recorded'); ?></dd></div><div class="record-info-wide"><dt>Address</dt><dd><?php echo e($purchase['supplier_address']?:'Not recorded'); ?></dd></div></dl>
      </article>
      <article class="record-info-card">
        <h3>Purchase &amp; delivery</h3>
        <dl><div><dt>Purchase method</dt><dd><?php echo e($purchaseTypeLabel); ?></dd></div><div><dt>Supplier invoice</dt><dd><?php echo e($purchase['supplier_invoice_number']?:'Not recorded'); ?></dd></div><div><dt>Deliver to</dt><dd><?php echo e($purchase['location_name'].' ('.$purchase['location_code'].')'); ?></dd></div><div><dt>Location type</dt><dd><?php echo e(ucwords(strtolower($purchase['location_type']))); ?></dd></div><div><dt>Expected date</dt><dd><?php echo $purchase['expected_date']?e(date('d M Y',strtotime($purchase['expected_date']))):'Not specified'; ?></dd></div><div><dt>Received at</dt><dd><?php echo $purchase['received_at']?e(formatDate($purchase['received_at'],$config['timezone'],'d M Y, H:i')):'Not completed'; ?></dd></div><div class="record-info-wide"><dt>Delivery address</dt><dd><?php echo e($purchase['location_address']?:'Not recorded'); ?></dd></div></dl>
      </article>
      <article class="record-info-card">
        <h3>Workflow</h3>
        <dl><div><dt>Created by</dt><dd><?php echo e(trim((string)$purchase['created_by'])?:'System'); ?></dd></div><div><dt>Approved by</dt><dd><?php echo e(trim((string)$purchase['approved_by'])?:'Not approved'); ?></dd></div><div><dt>Received by</dt><dd><?php echo e(trim((string)$purchase['received_by'])?:'Not received'); ?></dd></div><div><dt>Created</dt><dd><?php echo e(formatDate($purchase['created_at'],$config['timezone'],'d M Y, H:i')); ?></dd></div><div class="record-info-wide"><dt>Last updated</dt><dd><?php echo e(formatDate($purchase['updated_at'],$config['timezone'],'d M Y, H:i')); ?></dd></div></dl>
      </article>
    </div>
  </section>

  <section class="record-section record-financial">
    <div class="record-section-heading"><div><h2>Financial breakdown</h2><p>A clear view of the order value and settlement.</p></div></div>
    <div class="record-financial-grid">
      <div><span>Subtotal</span><strong><?php echo formatCurrency($purchase['subtotal'],$currency); ?></strong></div>
      <div><span>Discount</span><strong><?php echo formatCurrency($purchase['discount_amount'],$currency); ?></strong></div>
      <div><span>Tax</span><strong><?php echo formatCurrency($purchase['tax_amount'],$currency); ?></strong></div>
      <div><span>Shipping</span><strong><?php echo formatCurrency($purchase['shipping_amount'],$currency); ?></strong></div>
      <div class="record-financial-total"><span>Order total</span><strong><?php echo formatCurrency($purchase['total_amount'],$currency); ?></strong></div>
      <div class="record-financial-paid"><span>Amount paid</span><strong><?php echo formatCurrency($purchase['amount_paid'],$currency); ?></strong></div>
      <div class="record-financial-due"><span>Balance due</span><strong><?php echo formatCurrency($balanceDue,$currency); ?></strong></div>
    </div>
  </section>

  <section class="record-section record-items-section">
    <div class="record-section-heading"><div><h2>Purchased items</h2><p>Ordered units, receiving progress, batch tracking, and pricing for every product.</p></div><span><?php echo count($items); ?> lines</span></div>
    <div class="record-progress"><div><span style="width:<?php echo e(number_format($progress, 2, '.', '')); ?>%"></span></div><small><strong><?php echo e(number_format($progress, 1)); ?>%</strong> received</small></div>
    <div class="record-table-scroll">
      <table class="data-table record-items-table">
        <thead><tr><th>Product</th><th>Batch / expiry</th><th>Ordered</th><th>Received</th><th>Returned</th><th>Pending</th><th>Unit pricing</th><th>Line total</th></tr></thead>
        <tbody><?php foreach ($items as $item): $pending=max(0,(float)$item['ordered_quantity']-(float)$item['received_quantity']);$factor=(float)$item['conversion_factor_to_base']; ?><tr>
          <td class="record-product-cell"><strong><?php echo e($item['product_name']); ?></strong><small>SKU: <?php echo e($item['sku']); ?></small><?php if($item['barcode']):?><small>Barcode: <?php echo e($item['barcode']); ?></small><?php endif;?></td>
          <td><strong><?php echo e($item['lot_number'] ?: 'No batch'); ?></strong><?php if($item['expires_at']):?><small>Expires <?php echo e(date('d M Y', strtotime($item['expires_at']))); ?></small><?php else:?><small>No expiry date</small><?php endif;?></td>
          <td><strong><?php echo formatInventoryDecimal($item['purchase_quantity']); ?> <?php echo e($item['purchase_uom']); ?></strong><small><?php echo formatInventoryDecimal($item['ordered_quantity']); ?> <?php echo e($item['uom']); ?> base</small></td>
          <td class="record-positive"><strong><?php echo formatInventoryDecimal(convertBaseQuantityToTransaction($item['received_quantity'],$factor)); ?> <?php echo e($item['purchase_uom']); ?></strong></td>
          <td><strong><?php echo formatInventoryDecimal(convertBaseQuantityToTransaction($item['returned_quantity'],$factor)); ?> <?php echo e($item['purchase_uom']); ?></strong></td>
          <td class="record-warning"><strong><?php echo formatInventoryDecimal(convertBaseQuantityToTransaction($pending,$factor)); ?> <?php echo e($item['purchase_uom']); ?></strong></td>
          <td class="record-pricing-cell"><strong><?php echo formatCurrency($item['purchase_unit_cost'],$currency); ?> / <?php echo e($item['purchase_uom']); ?></strong><small>Base cost: <?php echo formatCurrency($item['unit_cost'],$currency); ?> / <?php echo e($item['uom']); ?></small><small>Base sale: <?php echo formatCurrency($item['unit_selling_price'],$currency); ?></small><?php if($item['package_selling_price']!==null):?><small>Package sale: <?php echo formatCurrency($item['package_selling_price'],$currency); ?></small><?php endif;?></td>
          <td class="record-total"><strong><?php echo formatCurrency($item['line_total'],$currency); ?></strong><small>Discount: <?php echo formatCurrency($item['discount_amount'],$currency); ?></small><small>Tax: <?php echo formatCurrency($item['tax_amount'],$currency); ?></small></td>
        </tr><?php endforeach;?></tbody>
      </table>
    </div>
  </section>

  <div class="record-activity-grid">
    <section class="record-section"><div class="record-section-heading"><div><h2>Payment history</h2><p>Payment method, date, and remaining balance.</p></div><span><?php echo count($payments); ?> records</span></div><?php if(!$payments):?><div class="record-empty"><strong>Recorded as supplier debt</strong><p>No payment has been recorded. The outstanding balance is <?php echo formatCurrency($balanceDue,$currency); ?>.</p><?php if($canUpdatePurchase):?><a class="record-button record-button-payment record-empty-action" href="edit?id=<?php echo $purchaseId . $roleQuery; ?>#record-payment">Record first payment</a><?php endif;?></div><?php else:?><div class="record-list"><?php foreach($payments as $payment):$methodLabel=$payment['payment_method']==='MOBILE_MONEY'?'Phone / Mobile Money':($payment['payment_method']==='BANK_TRANSFER'?'Bank Transfer':ucwords(strtolower(str_replace('_',' ',$payment['payment_method']))));?><article><div><strong>Payment #<?php echo (int)$payment['payment_sequence']; ?> &middot; <?php echo e($methodLabel); ?></strong><small><?php echo e(formatDate($payment['paid_at'],$config['timezone'],'d M Y, H:i')); ?> &middot; <?php echo e(trim((string)$payment['recorded_by'])?:'System'); ?></small><p><?php if($payment['payment_method']==='MOBILE_MONEY'):?>Telephone: <?php echo e($payment['phone_number']?:'Not recorded'); ?><?php elseif($payment['payment_method']==='BANK_TRANSFER'):?>Bank: <?php echo e($payment['bank_name']?:'Not recorded'); ?> &middot; Account: <?php echo e($payment['bank_account_number']?:'Not recorded'); ?><?php else:?>Cash payment<?php endif;?><?php if($payment['reference_number']):?> &middot; Reference: <?php echo e($payment['reference_number']); ?><?php endif;?><?php if($payment['notes']):?> &middot; <?php echo e($payment['notes']); ?><?php endif;?></p></div><div class="payment-ledger-values"><span>Amount paid</span><b><?php echo formatCurrency($payment['amount'],$currency); ?></b><small>Balance: <?php echo formatCurrency($payment['remaining_balance'],$currency); ?></small></div></article><?php endforeach;?></div><?php endif;?></section>
    <section class="record-section"><div class="record-section-heading"><div><h2>Return history</h2><p>Stock returned against this purchase.</p></div><span><?php echo count($returns); ?> lines</span></div><?php if(!$returns):?><div class="record-empty"><strong>No returns recorded</strong><p>No purchased stock has been returned.</p></div><?php else:?><div class="record-list"><?php foreach($returns as $return):?><article><div><strong><?php echo e($return['return_number'].' · '.$return['product_name']); ?></strong><small><?php echo e(formatDate($return['return_date'],$config['timezone'],'d M Y, H:i')); ?> &middot; <?php echo formatInventoryDecimal(convertBaseQuantityToTransaction($return['quantity'],$return['conversion_factor_to_base'])).' '.e($return['transaction_uom']); ?></small><p><?php echo e($return['reason']?:'No return reason'); ?> &middot; <?php echo e(trim((string)$return['created_by'])?:'System'); ?></p></div><b><?php echo formatCurrency($return['line_total'],$currency); ?></b></article><?php endforeach;?></div><?php endif;?></section>
  </div>

  <section class="record-section record-notes">
    <div class="record-section-heading"><div><h2>Notes</h2><p>Purchase instructions and supplier information.</p></div></div>
    <div class="record-notes-grid"><article><h3>Purchase notes</h3><p><?php echo nl2br(e($purchase['notes']?:'No purchase notes recorded.')); ?></p></article><article><h3>Supplier notes</h3><p><?php echo nl2br(e($purchase['supplier_notes']?:'No supplier notes recorded.')); ?></p></article></div>
  </section>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
