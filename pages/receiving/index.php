<?php
$page_title = 'Receiving';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = (int)($_SESSION['membership_id'] ?? 0);
requirePermission($conn, $membershipId, $businessId, $permissions['view']);
$canReceive = hasPermission($conn, $membershipId, $businessId, $permissions['receive']);
$csrfToken = generateCsrfToken();
$roleQuery = getRolePreviewQuery();
$config = getBusinessInventoryConfig($conn, $businessId);
$localNow = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));

$businessStmt = mysqli_prepare($conn, 'SELECT currency_code FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($businessStmt, 'i', $businessId);
mysqli_stmt_execute($businessStmt);
$currency = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt))['currency_code'] ?? 'RWF';

$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
if (!in_array($statusFilter, ['all','pending','received'], true)) $statusFilter = 'pending';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$where = " WHERE p.business_id=? AND p.status IN ('ORDERED','PARTIALLY_RECEIVED','RECEIVED')";
$params = [$businessId];
$types = 'i';
if ($statusFilter === 'pending') $where .= " AND p.status IN ('ORDERED','PARTIALLY_RECEIVED')";
if ($statusFilter === 'received') $where .= " AND p.status='RECEIVED'";
if ($search !== '') {
    $where .= ' AND (p.purchase_number LIKE ? OR s.name LIKE ? OR COALESCE(p.supplier_invoice_number,\'\') LIKE ?)';
    $searchLike = '%' . $search . '%';
    array_push($params, $searchLike, $searchLike, $searchLike);
    $types .= 'sss';
}

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM purchases p JOIN suppliers s ON s.id=p.supplier_id AND s.business_id=p.business_id $where");
mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$totalRows = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] ?? 0);
$totalPages = (int)ceil($totalRows / $limit);

$listSql = "SELECT p.id,p.purchase_number,p.supplier_invoice_number,p.status,p.purchase_date,p.received_at,
        s.name supplier_name,l.name location_name,l.code location_code,
        COUNT(pi.id) line_count,
        COALESCE(SUM(pi.ordered_quantity*pi.unit_cost),0) ordered_value,
        COALESCE(SUM(pi.received_quantity*pi.unit_cost),0) received_value,
        COALESCE(SUM((pi.ordered_quantity-pi.received_quantity)*pi.unit_cost),0) remaining_value
    FROM purchases p
    JOIN suppliers s ON s.id=p.supplier_id AND s.business_id=p.business_id
    JOIN business_locations l ON l.id=p.location_id AND l.business_id=p.business_id
    JOIN purchase_items pi ON pi.purchase_id=p.id AND pi.business_id=p.business_id
    $where
    GROUP BY p.id,p.purchase_number,p.supplier_invoice_number,p.status,p.purchase_date,p.received_at,s.name,l.name,l.code
    ORDER BY FIELD(p.status,'PARTIALLY_RECEIVED','ORDERED','RECEIVED'),p.purchase_date DESC
    LIMIT ? OFFSET ?";
$listStmt = mysqli_prepare($conn, $listSql);
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$limit,$offset]);
mysqli_stmt_bind_param($listStmt, $listTypes, ...$listParams);
mysqli_stmt_execute($listStmt);
$purchases = mysqli_stmt_get_result($listStmt);

$summaryStmt = mysqli_prepare($conn, "SELECT
        COUNT(DISTINCT CASE WHEN p.status IN ('ORDERED','PARTIALLY_RECEIVED') THEN p.id END) pending_count,
        COUNT(DISTINCT CASE WHEN p.status='RECEIVED' THEN p.id END) received_count,
        COALESCE(SUM(pi.received_quantity*pi.unit_cost),0) received_value,
        COALESCE(SUM((pi.ordered_quantity-pi.received_quantity)*pi.unit_cost),0) remaining_value
    FROM purchases p JOIN purchase_items pi ON pi.purchase_id=p.id AND pi.business_id=p.business_id
    WHERE p.business_id=? AND p.status IN ('ORDERED','PARTIALLY_RECEIVED','RECEIVED')");
mysqli_stmt_bind_param($summaryStmt, 'i', $businessId);
mysqli_stmt_execute($summaryStmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($summaryStmt)) ?: [];

$selectedPurchase = null;
$selectedLines = null;
$purchaseId = (int)($_GET['purchase_id'] ?? 0);
if ($purchaseId > 0) {
    $selectedStmt = mysqli_prepare($conn, "SELECT p.*,s.name supplier_name,l.name location_name,l.code location_code,
            COALESCE(SUM(pi.ordered_quantity*pi.unit_cost),0) ordered_value,
            COALESCE(SUM(pi.received_quantity*pi.unit_cost),0) received_value,
            COALESCE(SUM((pi.ordered_quantity-pi.received_quantity)*pi.unit_cost),0) remaining_value
        FROM purchases p
        JOIN suppliers s ON s.id=p.supplier_id AND s.business_id=p.business_id
        JOIN business_locations l ON l.id=p.location_id AND l.business_id=p.business_id
        JOIN purchase_items pi ON pi.purchase_id=p.id AND pi.business_id=p.business_id
        WHERE p.id=? AND p.business_id=? AND p.status IN ('ORDERED','PARTIALLY_RECEIVED','RECEIVED')
        GROUP BY p.id,s.name,l.name,l.code LIMIT 1");
    mysqli_stmt_bind_param($selectedStmt, 'ii', $purchaseId, $businessId);
    mysqli_stmt_execute($selectedStmt);
    $selectedPurchase = mysqli_fetch_assoc(mysqli_stmt_get_result($selectedStmt));
    if ($selectedPurchase) {
        $lineStmt = mysqli_prepare($conn, 'SELECT pi.id,pi.ordered_quantity,pi.received_quantity,pi.unit_cost,pi.unit_selling_price,pi.line_total,
                pr.name product_name,pr.sku,pr.uom,pb.lot_number,pb.expires_at
            FROM purchase_items pi
            JOIN products pr ON pr.id=pi.product_id AND pr.business_id=pi.business_id
            LEFT JOIN product_batches pb ON pb.id=pi.batch_id AND pb.business_id=pi.business_id
            WHERE pi.purchase_id=? AND pi.business_id=? ORDER BY pi.id');
        mysqli_stmt_bind_param($lineStmt, 'ii', $purchaseId, $businessId);
        mysqli_stmt_execute($lineStmt);
        $selectedLines = mysqli_stmt_get_result($lineStmt);
    }
}
$queryBase = array_filter(['status'=>$statusFilter,'search'=>$search,'role'=>getPreviewRole()], static fn($value) => $value !== null && $value !== '');
?>

<div class="receiving-page-head">
  <div><h1>Purchase Receiving</h1><p>Track ordered stock, post partial receipts, and complete purchase orders.</p></div>
  <a class="btn-sm" href="../purchase/index.php<?php echo e($roleQuery); ?>">View Purchase Orders</a>
</div>

<div class="receiving-summary">
  <div class="card"><span>Pending Purchases</span><strong><?php echo (int)($summary['pending_count'] ?? 0); ?></strong><small>Waiting for stock</small></div>
  <div class="card"><span>Fully Received</span><strong><?php echo (int)($summary['received_count'] ?? 0); ?></strong><small>Completed purchases</small></div>
  <div class="card"><span>Received Value</span><strong><?php echo formatCurrency($summary['received_value'] ?? 0, $currency); ?></strong><small>Stock received to date</small></div>
  <div class="card"><span>Remaining Value</span><strong><?php echo formatCurrency($summary['remaining_value'] ?? 0, $currency); ?></strong><small>Stock still outstanding</small></div>
</div>

<section class="card receiving-list-card">
  <div class="receiving-toolbar">
    <div><div class="card-title">Receiving Queue</div><p>Open a purchase to review every product and record the physical quantities received.</p></div>
    <form method="GET" class="receiving-filter">
      <?php if (getPreviewRole()): ?><input type="hidden" name="role" value="<?php echo e(getPreviewRole()); ?>"><?php endif; ?>
      <select name="status"><option value="pending" <?php echo $statusFilter==='pending'?'selected':''; ?>>Pending</option><option value="received" <?php echo $statusFilter==='received'?'selected':''; ?>>Fully Received</option><option value="all" <?php echo $statusFilter==='all'?'selected':''; ?>>All Purchases</option></select>
      <input name="search" value="<?php echo e($search); ?>" placeholder="PO, supplier, or invoice">
      <button class="btn-sm">Filter</button>
    </form>
  </div>
  <div class="receiving-table-wrap"><table class="data-table receiving-table">
    <thead><tr><th>Purchase Order</th><th>Supplier / Location</th><th>Receipt Progress</th><th>Received Value</th><th>Remaining Value</th><th>Status</th><th class="receipt-actions">Action</th></tr></thead>
    <tbody>
      <?php if (mysqli_num_rows($purchases) === 0): ?><tr><td colspan="7" class="receiving-empty">No purchases match the selected receiving status.</td></tr>
      <?php else: while ($purchase = mysqli_fetch_assoc($purchases)): $orderedValue=(float)$purchase['ordered_value'];$receivedValue=(float)$purchase['received_value'];$progress=$orderedValue>0?min(100,($receivedValue/$orderedValue)*100):100;$completed=$purchase['status']==='RECEIVED'; ?>
        <tr>
          <td><strong><?php echo e($purchase['purchase_number']); ?></strong><small><?php echo e(formatDate($purchase['purchase_date'],$config['timezone'],'d M Y')); ?> &middot; <?php echo (int)$purchase['line_count']; ?> product line(s)</small></td>
          <td><strong><?php echo e($purchase['supplier_name']); ?></strong><small><?php echo e($purchase['location_code'].' · '.$purchase['location_name']); ?></small></td>
          <td><div class="receipt-progress"><div style="width:<?php echo e(number_format($progress,2,'.','')); ?>%"></div></div><small><?php echo number_format($progress,1); ?>% of value received</small></td>
          <td class="value-received"><?php echo formatCurrency($purchase['received_value'],$currency); ?></td>
          <td class="value-remaining"><?php echo formatCurrency($purchase['remaining_value'],$currency); ?></td>
          <td><span class="status-pill <?php echo $completed?'pill-green':'pill-amber'; ?>"><?php echo $completed?'Fully Received / Completed':'Pending'; ?></span><?php if(!$completed):?><small><?php echo $purchase['status']==='PARTIALLY_RECEIVED'?'Partially received':'Not received'; ?></small><?php endif;?></td>
          <td class="receipt-actions"><a class="btn-sm <?php echo !$completed&&$canReceive?'receive-action':''; ?>" href="?<?php echo e(http_build_query(array_merge($queryBase,['purchase_id'=>(int)$purchase['id']]))); ?>"><?php echo $completed?'View':'Receive'; ?></a></td>
        </tr>
      <?php endwhile; endif; ?>
    </tbody>
  </table></div>
  <?php if ($totalPages > 1): ?><div class="receiving-pagination"><span>Page <?php echo $page; ?> of <?php echo $totalPages; ?> &middot; <?php echo $totalRows; ?> purchases</span><div><?php if($page>1):?><a class="btn-sm" href="?<?php echo e(http_build_query(array_merge($queryBase,['page'=>$page-1]))); ?>">Previous</a><?php endif;?><?php if($page<$totalPages):?><a class="btn-sm" href="?<?php echo e(http_build_query(array_merge($queryBase,['page'=>$page+1]))); ?>">Next</a><?php endif;?></div></div><?php endif; ?>
</section>

<?php if ($selectedPurchase): $isCompleted=$selectedPurchase['status']==='RECEIVED'; ?>
<div class="modal-overlay" id="receivingModal" style="display:flex" aria-hidden="false">
  <div class="modal-content-card receiving-modal-card" role="dialog" aria-modal="true" aria-labelledby="receivingModalTitle">
    <div class="modal-header"><div><div class="modal-title" id="receivingModalTitle"><?php echo $isCompleted?'Receiving Details':'Receive Purchase'; ?> &middot; <?php echo e($selectedPurchase['purchase_number']); ?></div><p><?php echo e($selectedPurchase['supplier_name'].' · '.$selectedPurchase['location_name']); ?></p></div><a class="modal-close-btn" href="index.php?<?php echo e(http_build_query($queryBase)); ?>" aria-label="Close">&times;</a></div>
    <div class="receipt-meta-grid">
      <div><span>Order Date</span><strong><?php echo e(formatDate($selectedPurchase['purchase_date'],$config['timezone'],'d M Y, H:i')); ?></strong></div>
      <div><span>Supplier Invoice</span><strong><?php echo e($selectedPurchase['supplier_invoice_number'] ?: 'Not recorded'); ?></strong></div>
      <div><span>Received Value</span><strong class="value-received"><?php echo formatCurrency($selectedPurchase['received_value'],$currency); ?></strong></div>
      <div><span>Remaining Value</span><strong class="value-remaining"><?php echo formatCurrency($selectedPurchase['remaining_value'],$currency); ?></strong></div>
    </div>
    <?php if (!$isCompleted && $canReceive): ?><form action="../purchase/backend.php<?php echo e($roleQuery); ?>" method="POST" id="receivingForm"><?php endif; ?>
      <div class="modal-body receiving-modal-body">
        <?php if (!$isCompleted && $canReceive): ?>
          <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="idempotency_key" value="<?php echo e(createIdempotencyToken()); ?>"><input type="hidden" name="action" value="receive_po"><input type="hidden" name="return_to" value="receiving"><input type="hidden" name="purchase_id" value="<?php echo (int)$selectedPurchase['id']; ?>">
          <div class="receipt-control-grid"><label>Receiving Status<select name="receipt_status" id="receiptStatus"><option value="PENDING">Pending / Partial receipt</option><option value="RECEIVED">Received / receive all remaining</option></select><small>Choosing Received fills every remaining quantity and completes the purchase.</small></label><label>Supplier Invoice / Delivery Note<input name="supplier_invoice_number" value="<?php echo e($selectedPurchase['supplier_invoice_number']); ?>" placeholder="e.g. INV-92837"></label><label>Received At<input type="datetime-local" name="received_at" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>" required></label></div>
        <?php endif; ?>
        <div class="receiving-lines-wrap"><table class="data-table receiving-lines"><thead><tr><th>Product</th><th>Ordered</th><th>Already Received</th><th>Remaining</th><th>Received Value</th><th>Remaining Value</th><?php if(!$isCompleted&&$canReceive):?><th>Receive Now</th><?php endif;?></tr></thead><tbody>
          <?php while ($line=mysqli_fetch_assoc($selectedLines)): $remaining=max(0,(float)$line['ordered_quantity']-(float)$line['received_quantity']);$receivedValue=(float)$line['received_quantity']*(float)$line['unit_cost'];$remainingValue=$remaining*(float)$line['unit_cost']; ?>
            <tr><td><strong><?php echo e($line['product_name']); ?></strong><small><?php echo e($line['sku']); ?><?php echo $line['lot_number']?' · Lot '.e($line['lot_number']):''; ?></small></td><td><?php echo number_format($line['ordered_quantity'],4); ?> <?php echo e($line['uom']); ?></td><td class="value-received"><?php echo number_format($line['received_quantity'],4); ?></td><td class="value-remaining"><?php echo number_format($remaining,4); ?></td><td><?php echo formatCurrency($receivedValue,$currency); ?></td><td><?php echo formatCurrency($remainingValue,$currency); ?></td><?php if(!$isCompleted&&$canReceive):?><td><input type="hidden" name="item_ids[]" value="<?php echo (int)$line['id']; ?>"><input class="receive-quantity" type="number" name="received_quantities[]" min="0" max="<?php echo e(number_format($remaining,4,'.','')); ?>" step=".0001" value="0" data-remaining="<?php echo e(number_format($remaining,4,'.','')); ?>" <?php echo $remaining<=0?'readonly':''; ?>></td><?php endif;?></tr>
          <?php endwhile; ?>
        </tbody></table></div>
        <?php if(!$isCompleted&&!$canReceive):?><div class="receiving-permission-note">You can view receiving information, but you do not have permission to post stock receipts.</div><?php endif;?>
      </div>
      <div class="modal-footer"><a class="btn-sm" href="index.php?<?php echo e(http_build_query($queryBase)); ?>">Close</a><?php if(!$isCompleted&&$canReceive):?><button class="btn-primary" id="postReceiptButton">Post Stock Receipt</button><?php endif;?></div>
    <?php if (!$isCompleted && $canReceive): ?></form><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<style>
.receiving-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:16px}.receiving-page-head h1{margin:0;color:var(--text);font-size:22px}.receiving-page-head p,.receiving-toolbar p,.modal-header p{margin:5px 0 0;color:var(--text3);font-size:11px}.receiving-page-head>a{text-decoration:none}.receiving-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.receiving-summary .card{padding:14px 16px;box-shadow:none}.receiving-summary span,.receipt-meta-grid span{display:block;color:var(--text3);font-size:9px;text-transform:uppercase;letter-spacing:.04em}.receiving-summary strong{display:block;margin-top:7px;color:var(--text);font-size:17px}.receiving-summary small{display:block;margin-top:5px;color:var(--text3);font-size:9px}.receiving-list-card{overflow:hidden;box-shadow:none}.receiving-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;padding:16px;border-bottom:1px solid var(--table-border)}.receiving-filter{display:flex;align-items:center;gap:7px}.receiving-filter select,.receiving-filter input{min-height:35px;padding:7px 10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font:inherit;font-size:11px}.receiving-filter input{min-width:200px}.receiving-table-wrap,.receiving-lines-wrap{overflow:auto}.receiving-table{min-width:1040px}.receiving-table td{vertical-align:middle}.receiving-table td strong,.receiving-table td small,.receiving-lines td strong,.receiving-lines td small{display:block}.receiving-table td small,.receiving-lines td small{margin-top:4px;color:var(--text3);font-size:9px}.receipt-progress{width:150px;height:7px;overflow:hidden;border-radius:999px;background:var(--bg)}.receipt-progress>div{height:100%;border-radius:999px;background:var(--green)}.value-received{color:var(--green)!important;font-weight:650}.value-remaining{color:var(--orange)!important;font-weight:650}.receipt-actions{text-align:right!important}.receipt-actions a{text-decoration:none}.receive-action{border-color:var(--green)!important;background:var(--green)!important;color:#fff!important}.receiving-empty{text-align:center!important;padding:38px!important;color:var(--text3)!important}.receiving-pagination{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-top:1px solid var(--table-border);color:var(--text3);font-size:10px}.receiving-pagination>div{display:flex;gap:6px}.receiving-pagination a{text-decoration:none}.receiving-modal-card{width:min(1180px,calc(100vw - 28px));max-height:92vh}.receipt-meta-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:14px 18px;border-bottom:1px solid var(--table-border);background:var(--bg)}.receipt-meta-grid>div{padding:10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card)}.receipt-meta-grid strong{display:block;margin-top:7px;color:var(--text);font-size:11px}.receiving-modal-body{overflow:auto}.receipt-control-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:12px;margin-bottom:16px;padding:14px;border:1px solid var(--border);border-radius:var(--radius-lg);background:var(--bg)}.receipt-control-grid label{display:flex;flex-direction:column;gap:6px;color:var(--text2);font-size:10px;font-weight:650}.receipt-control-grid input,.receipt-control-grid select,.receive-quantity{min-height:38px;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font:inherit}.receipt-control-grid small{color:var(--text3);font-size:9px;font-weight:400}.receiving-lines{min-width:960px}.receive-quantity{width:120px}.receiving-permission-note{margin-top:12px;padding:11px;border-radius:var(--radius);background:var(--bg);color:var(--text3);font-size:10px}@media(max-width:980px){.receiving-summary{grid-template-columns:1fr 1fr}.receiving-toolbar{align-items:stretch;flex-direction:column}.receiving-filter{justify-content:flex-start}.receipt-meta-grid{grid-template-columns:1fr 1fr}.receipt-control-grid{grid-template-columns:1fr}}@media(max-width:620px){.receiving-page-head{align-items:stretch;flex-direction:column}.receiving-summary,.receipt-meta-grid{grid-template-columns:1fr}.receiving-filter{display:grid;grid-template-columns:1fr auto}.receiving-filter input{min-width:0;grid-column:1/-1}}
</style>

<script>
const receiptStatus = document.getElementById('receiptStatus');
function syncReceiptQuantities() {
  const complete = receiptStatus?.value === 'RECEIVED';
  document.querySelectorAll('.receive-quantity').forEach(input => {
    if (complete) input.value = input.dataset.remaining;
  });
  const button = document.getElementById('postReceiptButton');
  if (button) button.textContent = complete ? 'Receive All & Complete Purchase' : 'Post Stock Receipt';
}
receiptStatus?.addEventListener('change', syncReceiptQuantities);
document.getElementById('receivingForm')?.addEventListener('submit', function () { const button=document.getElementById('postReceiptButton');button.disabled=true;button.textContent='Posting Receipt...'; });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
