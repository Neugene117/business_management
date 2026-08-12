<?php
$page_title = 'Sales Orders';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$hasBusinessContext = $businessId > 0;
$config = [
    'timezone' => 'Africa/Kigali',
    'inventory_valuation_method' => 'WEIGHTED_AVERAGE',
    'default_tax_rate' => 0.0,
    'allow_negative_stock' => 0
];
if ($hasBusinessContext) {
    try {
        $config = getBusinessInventoryConfig($conn, $businessId);
    } catch (RuntimeException $error) {
        // A normal business user is already tenant-validated by the header.
        // Only a platform Super Admin may legitimately have no business context.
        if (!isSuperAdmin()) {
            throw $error;
        }
        $hasBusinessContext = false;
        $businessId = 0;
    }
}
$localNow = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
$viewFullHistory = ($_GET['view'] ?? '') === 'history';
$requestedSalesSection = strtolower(trim((string)($_GET['section'] ?? 'history')));
$activeSalesSection = $viewFullHistory || $requestedSalesSection !== 'flow' ? 'history' : 'flow';
$search = trim((string)($_GET['search'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? ''));
$customerFilter = (int)($_GET['customer_id'] ?? 0);
$productFilter = (int)($_GET['product_id'] ?? 0);
$locationFilter = (int)($_GET['location_id'] ?? 0);
$cashierFilter = (int)($_GET['cashier_id'] ?? 0);
$paymentFilter = trim((string)($_GET['payment_method'] ?? ''));
$salesFrom = trim((string)($_GET['from'] ?? $localNow->format('Y-m-01')));
$salesTo = trim((string)($_GET['to'] ?? $localNow->format('Y-m-d')));
try {
    $period = getBusinessPeriodBounds($salesFrom, $salesTo, $config['timezone']);
} catch (Throwable $error) {
    $salesFrom = $localNow->format('Y-m-01');
    $salesTo = $localNow->format('Y-m-d');
    $period = getBusinessPeriodBounds($salesFrom, $salesTo, $config['timezone']);
}
$salesPeriodStart = $period['start_utc'];
$salesPeriodEnd = $period['end_utc'];

// Server side pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where_clause = " WHERE s.business_id = ? AND s.sold_at >= ? AND s.sold_at < ?";
$params = [$businessId, $salesPeriodStart, $salesPeriodEnd];
$types = 'iss';

if (!empty($search)) {
    $where_clause .= " AND (s.sale_number LIKE ? OR c.name LIKE ? OR EXISTS (SELECT 1 FROM sale_items ss JOIN products ps ON ps.id=ss.product_id AND ps.business_id=ss.business_id WHERE ss.sale_id=s.id AND (ps.name LIKE ? OR ps.sku LIKE ?)))";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if (!empty($status_filter)) {
    $where_clause .= " AND s.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if ($customerFilter > 0) { $where_clause .= ' AND s.customer_id = ?'; $params[] = $customerFilter; $types .= 'i'; }
if ($locationFilter > 0) { $where_clause .= ' AND s.location_id = ?'; $params[] = $locationFilter; $types .= 'i'; }
if ($cashierFilter > 0) { $where_clause .= ' AND s.cashier_membership_id = ?'; $params[] = $cashierFilter; $types .= 'i'; }
if ($productFilter > 0) { $where_clause .= ' AND EXISTS (SELECT 1 FROM sale_items sf WHERE sf.sale_id=s.id AND sf.business_id=s.business_id AND sf.product_id=?)'; $params[] = $productFilter; $types .= 'i'; }
if ($paymentFilter !== '') { $where_clause .= ' AND EXISTS (SELECT 1 FROM sale_payments spf WHERE spf.sale_id=s.id AND spf.business_id=s.business_id AND spf.payment_method=?)'; $params[] = $paymentFilter; $types .= 's'; }

// Count total
$count_query = "
    SELECT COUNT(*) as total 
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    $where_clause
";
$cStmt = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($cStmt, $types, ...$params);
mysqli_stmt_execute($cStmt);
$total_rows = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT s.*, c.name as customer_name, l.name as location_name, l.code as location_code,
           u.first_name, u.last_name,
           COALESCE((SELECT SUM(sri.quantity * ori.unit_price) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id AND sr.status='COMPLETED' JOIN sale_items ori ON ori.id=sri.sale_item_id WHERE sr.sale_id=s.id),0) returned_revenue,
           COALESCE((SELECT SUM(sri.quantity * ori.unit_cost_at_sale) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id AND sr.status='COMPLETED' JOIN sale_items ori ON ori.id=sri.sale_item_id WHERE sr.sale_id=s.id),0) returned_cogs,
           (SELECT GROUP_CONCAT(CONCAT(pr.name, ' | ', CAST(si.quantity AS CHAR), ' ', pr.uom, ' @ ', CAST(si.unit_price AS CHAR)) ORDER BY si.id SEPARATOR '\n')
              FROM sale_items si
              JOIN products pr ON pr.business_id = si.business_id AND pr.id = si.product_id
             WHERE si.business_id = s.business_id AND si.sale_id = s.id) AS items_sold
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    JOIN business_locations l ON s.location_id = l.id
    LEFT JOIN business_memberships bm ON s.cashier_membership_id = bm.id
    LEFT JOIN users u ON bm.user_id = u.id
    $where_clause
    ORDER BY s.sold_at DESC
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $query);
$types_limit = $types . 'ii';
$params_limit = array_merge($params, [$limit, $offset]);
mysqli_stmt_bind_param($stmt, $types_limit, ...$params_limit);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch active customers
$custQuery = "SELECT id, name FROM customers WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$custStmt = mysqli_prepare($conn, $custQuery);
mysqli_stmt_bind_param($custStmt, 'i', $businessId);
mysqli_stmt_execute($custStmt);
$customers_list = [];
$cResult = mysqli_stmt_get_result($custStmt);
while ($cRow = mysqli_fetch_assoc($cResult)) {
    $customers_list[] = $cRow;
}

// Fetch active locations
$locQuery = "SELECT id, name, code FROM business_locations WHERE business_id = ? AND is_active = 1 ORDER BY name ASC";
$lStmt = mysqli_prepare($conn, $locQuery);
mysqli_stmt_bind_param($lStmt, 'i', $businessId);
mysqli_stmt_execute($lStmt);
$locations_list = [];
$lResult = mysqli_stmt_get_result($lStmt);
while ($lRow = mysqli_fetch_assoc($lResult)) {
    $locations_list[] = $lRow;
}

// Fetch active products
$prodQuery = "SELECT id,name,sku,uom,sale_price,track_batches,track_expiry FROM products WHERE business_id=? AND is_active=1 ORDER BY name";
$pStmt = mysqli_prepare($conn, $prodQuery);
mysqli_stmt_bind_param($pStmt, 'i', $businessId);
mysqli_stmt_execute($pStmt);
$products_list = [];
$pResult = mysqli_stmt_get_result($pStmt);
while ($pRow = mysqli_fetch_assoc($pResult)) {
    $products_list[] = $pRow;
}

$batchQuery = "SELECT pb.id,pb.product_id,pb.lot_number,pb.expires_at,bib.location_id,bib.available_quantity FROM product_batches pb LEFT JOIN batch_inventory_balances bib ON bib.business_id=pb.business_id AND bib.batch_id=pb.id WHERE pb.business_id=? ORDER BY pb.product_id,pb.expires_at,pb.lot_number";
$batchStmt = mysqli_prepare($conn, $batchQuery);
mysqli_stmt_bind_param($batchStmt, 'i', $businessId);
mysqli_stmt_execute($batchStmt);
$batches_list = [];
$batchResult = mysqli_stmt_get_result($batchStmt);
while ($batchRow = mysqli_fetch_assoc($batchResult)) $batches_list[] = $batchRow;

$stockQuery = 'SELECT product_id,location_id,available_quantity FROM inventory_balances WHERE business_id=?';
$stockStmt = mysqli_prepare($conn, $stockQuery);
mysqli_stmt_bind_param($stockStmt, 'i', $businessId);
mysqli_stmt_execute($stockStmt);
$stock_list = [];
$stockResult = mysqli_stmt_get_result($stockStmt);
while ($stockRow = mysqli_fetch_assoc($stockResult)) $stock_list[] = $stockRow;

$cashierQuery = "SELECT bm.id,CONCAT(u.first_name,' ',u.last_name) name FROM business_memberships bm JOIN users u ON u.id=bm.user_id WHERE bm.business_id=? AND bm.status='ACTIVE' ORDER BY u.first_name,u.last_name";
$cashierStmt = mysqli_prepare($conn, $cashierQuery);
mysqli_stmt_bind_param($cashierStmt, 'i', $businessId);
mysqli_stmt_execute($cashierStmt);
$cashiers_list = [];
$cashierResult = mysqli_stmt_get_result($cashierStmt);
while ($cashierRow = mysqli_fetch_assoc($cashierResult)) $cashiers_list[] = $cashierRow;

// Fetch business tax settings
$acctQ = "SELECT default_tax_rate FROM business_accounting_settings WHERE business_id = ? LIMIT 1";
$aStmt = mysqli_prepare($conn, $acctQ);
mysqli_stmt_bind_param($aStmt, 'i', $businessId);
mysqli_stmt_execute($aStmt);
$default_tax_rate = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt))['default_tax_rate'] ?? 0.0;

// Fetch business currency
$bizQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt))['currency_code'] ?? 'RWF';

// Opening, stock out, and closing are calculated from the same ledger used by purchases and adjustments.
$flowQuery = "
    SELECT p.id, p.sku, p.name, p.uom,
           COALESCE(SUM(CASE WHEN m.occurred_at < ? THEN m.quantity_delta ELSE 0 END), 0) AS opening_stock,
           COALESCE(SUM(CASE WHEN m.occurred_at >= ? AND m.occurred_at < ? AND m.movement_type = 'SALE' AND EXISTS (SELECT 1 FROM sale_items vsi JOIN sales vs ON vs.id=vsi.sale_id AND vs.business_id=vsi.business_id WHERE vsi.id=m.sale_item_id AND vsi.business_id=m.business_id AND vs.status<>'VOIDED') THEN ABS(m.quantity_delta) ELSE 0 END), 0) AS stock_out,
           COALESCE(SUM(CASE WHEN m.occurred_at < ? THEN m.quantity_delta ELSE 0 END), 0) AS closing_stock
      FROM products p
      LEFT JOIN inventory_movements m ON m.business_id = p.business_id AND m.product_id = p.id
     WHERE p.business_id = ? AND p.deleted_at IS NULL
     GROUP BY p.id, p.sku, p.name, p.uom
     HAVING opening_stock <> 0 OR stock_out <> 0 OR closing_stock <> 0
     ORDER BY p.name
";
$flowStmt = mysqli_prepare($conn, $flowQuery);
mysqli_stmt_bind_param($flowStmt, 'ssssi', $salesPeriodStart, $salesPeriodStart, $salesPeriodEnd, $salesPeriodEnd, $businessId);
mysqli_stmt_execute($flowStmt);
$flowResult = mysqli_stmt_get_result($flowStmt);

$csrfToken = generateCsrfToken();
$role_query = getRolePreviewQuery();
$canCreateSale = $hasBusinessContext && hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['create']);
$canVoidSale = $hasBusinessContext && hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['void']);
$canRefundSale = $hasBusinessContext && hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['refund']);
$canOverridePrice = $hasBusinessContext && hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, 'products.update');
$sectionQueryBase = ['from'=>$salesFrom, 'to'=>$salesTo, 'role'=>getPreviewRole()];
$sectionQueryBase = array_filter($sectionQueryBase, static fn($value) => $value !== null && $value !== '');
$flowSectionUrl = 'index.php?' . http_build_query(array_merge($sectionQueryBase, ['section'=>'flow']));
$historySectionUrl = 'index.php?' . http_build_query(array_merge($sectionQueryBase, ['section'=>'history']));
$paginationParams = [
    'view'=>$viewFullHistory ? 'history' : null,'section'=>$viewFullHistory ? null : 'history','status'=>$status_filter ?: null,'search'=>$search ?: null,
    'from'=>$salesFrom,'to'=>$salesTo,'customer_id'=>$customerFilter ?: null,'product_id'=>$productFilter ?: null,
    'location_id'=>$locationFilter ?: null,'cashier_id'=>$cashierFilter ?: null,'payment_method'=>$paymentFilter ?: null,
    'role'=>getPreviewRole()
];
$paginationParams = array_filter($paginationParams, static fn($value) => $value !== null && $value !== '');
?>
<div class="sales-workspace">
  <?php if (!$hasBusinessContext): ?>
    <div class="sales-context-notice" role="status">
      <strong>Sales preview</strong>
      <span>No active business is attached to this platform session, so business sales data and transaction actions are unavailable.</span>
    </div>
  <?php endif; ?>
  <div class="sales-page-toolbar">
    <div>
      <div class="sales-page-kicker">Sales workspace</div>
      <h2>Sales Management</h2>
      <p>Review stock flow or manage sales records from one focused workspace.</p>
    </div>
    <?php if ($canCreateSale): ?>
      <button class="btn-primary sales-add-primary" type="button" onclick="openAddModal()">
        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Add Sales
      </button>
    <?php endif; ?>
  </div>

  <nav class="sales-section-switcher" aria-label="Sales sections">
    <a href="<?php echo e($flowSectionUrl); ?>" class="sales-section-link <?php echo $activeSalesSection === 'flow' ? 'active' : ''; ?>" <?php echo $activeSalesSection === 'flow' ? 'aria-current="page"' : ''; ?>>
      <span class="sales-section-icon"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/></svg></span>
      <span><strong>Sales Stock Flow</strong><small>Opening, stock out, and closing balances</small></span>
    </a>
    <a href="<?php echo e($historySectionUrl); ?>" class="sales-section-link <?php echo $activeSalesSection === 'history' ? 'active' : ''; ?>" <?php echo $activeSalesSection === 'history' ? 'aria-current="page"' : ''; ?>>
      <span class="sales-section-icon"><svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5"/></svg></span>
      <span><strong>Complete Sales History</strong><small>Invoices, customers, payments, and returns</small></span>
    </a>
  </nav>

  <?php if ($activeSalesSection === 'flow'): ?>
  <div class="card sales-flow-card">
    <div class="card-header sales-card-header">
      <div><div class="card-title">Sales Stock Flow</div><p>Opening, sales stock out, and closing inventory for the selected period. Returns are reflected in closing stock.</p></div>
      <form method="GET" action="index.php" class="sales-period-filter">
        <input type="hidden" name="section" value="flow">
        <?php if (getPreviewRole() !== null): ?><input type="hidden" name="role" value="<?php echo e(getPreviewRole()); ?>"><?php endif; ?>
        <label>From <input type="date" name="from" value="<?php echo e($salesFrom); ?>"></label>
        <label>To <input type="date" name="to" value="<?php echo e($salesTo); ?>"></label>
        <button class="btn-sm" type="submit">Apply</button>
      </form>
    </div>
    <div class="sales-table-scroll sales-flow-scroll"><table class="data-table">
      <thead><tr><th>SKU</th><th>Product</th><th>Opening</th><th>Stock Out</th><th>Closing</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($flowResult) === 0): ?>
        <tr><td colspan="5" class="sales-empty">No stock activity was found for this period.</td></tr>
      <?php else: while ($flow = mysqli_fetch_assoc($flowResult)): ?>
        <tr>
          <td><a class="code-badge history-link" href="../inventory/index.php?tab=product_history&product_id=<?php echo (int)$flow['id']; ?><?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>"><?php echo e($flow['sku']); ?></a></td>
          <td class="td-name"><a class="history-link" href="../inventory/index.php?tab=product_history&product_id=<?php echo (int)$flow['id']; ?><?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>"><?php echo e($flow['name']); ?></a></td>
          <td class="td-bold"><?php echo (float)$flow['opening_stock']; ?> <?php echo e($flow['uom']); ?></td>
          <td class="sales-stock-out">-<?php echo (float)$flow['stock_out']; ?> <?php echo e($flow['uom']); ?></td>
          <td class="td-bold"><?php echo (float)$flow['closing_stock']; ?> <?php echo e($flow['uom']); ?></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table></div>
  </div>
  <?php endif; ?>

  <?php if ($activeSalesSection === 'history'): ?>
  <div class="card">
    <div class="card-header sales-history-header">
      <div class="sales-history-heading">
        <div><div class="card-title"><?php echo $viewFullHistory ? 'Full Sales History' : 'Complete Sales History'; ?></div><p class="sales-history-help"><?php echo $viewFullHistory ? 'Search invoices using detailed transaction filters.' : 'Recent invoices with customer, payment, return, and profit information.'; ?></p></div>
        <?php if (!$viewFullHistory): ?><a class="btn-sm" href="index.php?view=history&from=<?php echo e($salesFrom); ?>&to=<?php echo e($salesTo); ?><?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>">Advanced History</a><?php else: ?><a class="btn-sm" href="<?php echo e($historySectionUrl); ?>">Simple View</a><?php endif; ?>
      </div>
      <form method="GET" action="index.php" class="sales-history-filters">
        <?php if ($viewFullHistory): ?><input type="hidden" name="view" value="history"><?php endif; ?>
        <?php if (!$viewFullHistory): ?><input type="hidden" name="section" value="history"><?php endif; ?>
        <?php if (isset($_GET['role'])): ?>
          <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
        <?php endif; ?>
        <?php if ($viewFullHistory): ?>
          <label>From<input type="date" name="from" value="<?php echo e($salesFrom); ?>"></label>
          <label>To<input type="date" name="to" value="<?php echo e($salesTo); ?>"></label>
          <select name="customer_id"><option value="">All customers</option><?php foreach ($customers_list as $customer): ?><option value="<?php echo (int)$customer['id']; ?>" <?php echo $customerFilter === (int)$customer['id'] ? 'selected' : ''; ?>><?php echo e($customer['name']); ?></option><?php endforeach; ?></select>
          <select name="product_id"><option value="">All products / SKUs</option><?php foreach ($products_list as $product): ?><option value="<?php echo (int)$product['id']; ?>" <?php echo $productFilter === (int)$product['id'] ? 'selected' : ''; ?>><?php echo e($product['name'] . ' (' . $product['sku'] . ')'); ?></option><?php endforeach; ?></select>
          <select name="location_id"><option value="">All locations</option><?php foreach ($locations_list as $location): ?><option value="<?php echo (int)$location['id']; ?>" <?php echo $locationFilter === (int)$location['id'] ? 'selected' : ''; ?>><?php echo e($location['name']); ?></option><?php endforeach; ?></select>
          <select name="cashier_id"><option value="">All cashiers</option><?php foreach ($cashiers_list as $cashier): ?><option value="<?php echo (int)$cashier['id']; ?>" <?php echo $cashierFilter === (int)$cashier['id'] ? 'selected' : ''; ?>><?php echo e($cashier['name']); ?></option><?php endforeach; ?></select>
          <select name="payment_method"><option value="">All payments</option><?php foreach (['CASH','CARD','BANK_TRANSFER','MOBILE_MONEY','CHEQUE','CREDIT','OTHER'] as $method): ?><option value="<?php echo $method; ?>" <?php echo $paymentFilter === $method ? 'selected' : ''; ?>><?php echo e(str_replace('_', ' ', $method)); ?></option><?php endforeach; ?></select>
        <?php else: ?>
          <input type="hidden" name="from" value="<?php echo e($salesFrom); ?>">
          <input type="hidden" name="to" value="<?php echo e($salesTo); ?>">
        <?php endif; ?>
        <select name="status" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
          <option value="">All Statuses</option>
          <option value="COMPLETED" <?php echo ($status_filter === 'COMPLETED') ? 'selected' : ''; ?>>Completed</option>
          <option value="PARTIALLY_REFUNDED" <?php echo ($status_filter === 'PARTIALLY_REFUNDED') ? 'selected' : ''; ?>>Partially Refunded</option>
          <option value="REFUNDED" <?php echo ($status_filter === 'REFUNDED') ? 'selected' : ''; ?>>Refunded</option>
          <option value="VOIDED" <?php echo ($status_filter === 'VOIDED') ? 'selected' : ''; ?>>Voided</option>
        </select>
        <input type="text" name="search" placeholder="Search Sale Ref..." value="<?php echo e($search); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px; min-width: 140px;">
        <button class="btn-sm" type="submit">Filter</button>
        <?php if ($viewFullHistory): ?><a class="btn-sm" href="index.php?view=history<?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>">Clear</a><?php endif; ?>
      </form>
    </div>

    <div class="sales-table-scroll sales-history-scroll"><table class="data-table">
      <thead>
        <tr>
          <th>Sale Number</th>
          <th>Sold At</th>
          <th>Customer</th>
          <th>What Was Sold / Quantity / Selling Price</th>
          <th>Location</th>
          <th>Tax / Total</th>
          <th>Profit / Loss</th>
          <th>Status</th>
          <th style="text-align: right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($result) === 0): ?>
          <tr>
            <td colspan="9" style="text-align: center; color: var(--text3); padding: 30px;">
              No sales orders logged.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($result)): $displayProfit = ((float)$row['subtotal'] - (float)$row['returned_revenue']) - ((float)$row['total_cogs'] - (float)$row['returned_cogs']); ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['sale_number']); ?></span></td>
              <td><?php echo e(formatDate($row['sold_at'], $config['timezone'])); ?></td>
              <td class="td-name"><?php echo e($row['customer_name'] ?? 'Walk-In Customer'); ?></td>
              <td class="sales-items-cell"><?php echo nl2br(e($row['items_sold'] ?? 'No line items')); ?></td>
              <td><span class="code-badge" style="background:var(--bg); border:1px solid var(--border);"><?php echo e($row['location_code']); ?></span></td>
              <td>
                <div style="font-size:10px; color:var(--text3);">Tax: <?php echo formatCurrency($row['tax_amount'], $bizCur); ?></div>
                <div class="td-bold" style="color:var(--green);"><?php echo formatCurrency($row['total_amount'], $bizCur); ?></div>
              </td>
              <td class="td-bold" style="color:<?php echo $row['status'] === 'VOIDED' ? 'var(--text3)' : ($displayProfit >= 0 ? 'var(--green)' : 'var(--red)'); ?>;"><?php echo $row['status'] === 'VOIDED' ? 'Reversed' : formatCurrency(abs($displayProfit), $bizCur) . ($displayProfit >= 0 ? ' profit' : ' loss'); ?></td>
              <td>
                <?php if ($row['status'] === 'COMPLETED'): ?>
                  <span class="status-pill pill-green">Completed</span>
                <?php elseif ($row['status'] === 'PARTIALLY_REFUNDED'): ?>
                  <span class="status-pill pill-orange">Partially Refunded</span>
                <?php elseif ($row['status'] === 'REFUNDED'): ?>
                  <span class="status-pill pill-orange">Refunded</span>
                <?php else: ?>
                  <span class="status-pill pill-red" style="opacity: 0.6;">Voided</span>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 4px;">
                  <button class="btn-sm" onclick="viewDetails(<?php echo (int)$row['id']; ?>)">Invoice</button>
                  <?php if (in_array($row['status'], ['COMPLETED','PARTIALLY_REFUNDED'], true) && $canRefundSale): ?>
                    <a class="btn-sm" href="?view=history&return_id=<?php echo (int)$row['id']; ?><?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>">Return</a>
                  <?php endif; ?>
                  
                  <?php if ($row['status'] === 'COMPLETED' && $canVoidSale): ?>
                    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:inline;" onsubmit="return confirm('VOID this sale? Stocks will be added back and GP reversed. This cannot be undone.');">
                      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                      <input type="hidden" name="action" value="void_sale">
                      <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                      <input type="hidden" name="idempotency_key" value="<?php echo e(createIdempotencyToken()); ?>">
                      <button type="submit" class="btn-action reject" style="font-size:10px; padding:3px 6px;">Void</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table></div>

    <!-- Pagination links -->
    <?php if ($total_pages > 1): ?>
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px; border-top: 1px solid var(--table-border);">
        <span style="font-size:12px; color: var(--text3);">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_rows; ?> entries)</span>
        <div style="display: flex; gap: 4px;">
          <?php if ($page > 1): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?<?php echo e(http_build_query(array_merge($paginationParams, ['page'=>$page - 1]))); ?>">Previous</a>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a class="btn-sm <?php echo ($i === $page) ? 'active' : ''; ?>" style="text-decoration:none;" href="index.php?<?php echo e(http_build_query(array_merge($paginationParams, ['page'=>$i]))); ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
          <?php if ($page < $total_pages): ?>
            <a class="btn-sm" style="text-decoration:none;" href="index.php?<?php echo e(http_build_query(array_merge($paginationParams, ['page'=>$page + 1]))); ?>">Next</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ==========================================
     MODAL: LOG POS CUSTOMER SALE
     ========================================== -->
<?php if ($canCreateSale): ?>
<div class="modal-overlay" id="addModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
        Record POS Customer Sale
      </div>
      <button type="button" class="modal-close-btn" onclick="closeAddModal()">✕</button>
    </div>
    
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="display:flex; flex-direction:column; flex:1;" id="addSaleForm">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="idempotency_key" value="<?php echo e(createIdempotencyToken()); ?>">

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_num">Sale Number <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="text" name="sale_number" id="s_num" value="SAL-<?php echo date('YmdHis'); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_date">Sold At <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <input type="datetime-local" name="sold_at" id="s_date" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>" required>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_cust">Customer Account</label>
          <div class="field-wrap">
            <select name="customer_id" id="s_cust">
              <option value="">-- Walk-In Customer --</option>
              <?php foreach ($customers_list as $cust): ?>
                <option value="<?php echo $cust['id']; ?>"><?php echo e($cust['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_loc">Source Location <span style="color:var(--red);">*</span></label>
          <div class="field-wrap">
            <select name="location_id" id="s_loc" required>
              <option value="">-- Choose Location --</option>
              <?php foreach ($locations_list as $loc): ?>
                <option value="<?php echo $loc['id']; ?>"><?php echo e($loc['name']); ?> [<?php echo e($loc['code']); ?>]</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field" style="margin-bottom: 12px;">
          <label for="s_notes">Sale Notes / Remarks</label>
          <div class="field-wrap">
            <input type="text" name="notes" id="s_notes" placeholder="e.g. cash reconciliation note">
          </div>
        </div>

        <!-- Line Items Section -->
        <div style="border-top:1px solid var(--border); padding-top:12px; margin-top:12px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <h4 style="font-size:11.5px; font-weight:600; text-transform:uppercase; color:var(--text3);">Sales Invoice Items</h4>
            <button type="button" class="btn-sm" onclick="addItemRow()">+ Add Item</button>
          </div>
          <div class="sale-item-headings" aria-hidden="true"><span>Product / available</span><span>Batch / lot</span><span>Quantity</span><span>Selling price<?php echo $canOverridePrice ? ' (editable)' : ''; ?></span><span></span></div>
          
          <div id="itemsContainer" style="display:flex; flex-direction:column; gap:8px; max-height: 250px; overflow-y:auto; padding-right:4px;">
            <!-- Dynamically added rows -->
          </div>

          <div style="border-top:1px dashed var(--border); margin-top:12px; padding-top:10px; font-size:12px; display:flex; flex-direction:column; gap:4px;">
            <div style="display:flex; justify-content:space-between;">
              <span>Subtotal:</span>
              <span id="saleSubtotal">0.00 <?php echo e($bizCur); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span>Tax (<?php echo (float)($default_tax_rate * 100); ?>%):</span>
              <span id="saleTax">0.00 <?php echo e($bizCur); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-weight:700; font-size:13.5px; border-top:1px solid var(--border); padding-top:6px; margin-top:4px;">
              <span>Total Payable:</span>
              <span id="saleTotal">0.00 <?php echo e($bizCur); ?></span>
            </div>
          </div>
        </div>

        <div class="payment-grid">
          <div class="field"><label>Payment Method</label><div class="field-wrap"><select name="payment_method" id="paymentMethod" onchange="paymentMethodChanged()"><option>CASH</option><option>CARD</option><option>BANK_TRANSFER</option><option>MOBILE_MONEY</option><option>CHEQUE</option><option>CREDIT</option><option>OTHER</option></select></div></div>
          <div class="field"><label>Amount Paid</label><div class="field-wrap"><input type="number" min="0" step="0.0001" name="amount_paid" id="amountPaid" placeholder="Defaults to total"></div></div>
          <div class="field"><label>Reference Number</label><div class="field-wrap"><input type="text" name="payment_reference" maxlength="120" placeholder="Optional"></div></div>
          <div class="field"><label>Paid At</label><div class="field-wrap"><input type="datetime-local" name="paid_at" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>"></div></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-sm" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn-primary" id="submitBtn">Log POS Cash Sale</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ==========================================
     MODAL: VIEW INVOICE
     ========================================== -->
<div class="modal-overlay" id="detailModalOverlay">
  <div class="modal-content-card modal-lg">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Tax Invoice Receipt: <span id="dt_sale_num" style="color:var(--orange);"></span>
      </div>
      <div style="display:inline-flex; gap:6px; align-items:center;">
        <button class="btn-sm" onclick="window.print()">Print</button>
        <button type="button" class="modal-close-btn" onclick="closeDetails()">✕</button>
      </div>
    </div>
    <div class="modal-body" style="font-size:12.5px;" id="detailContent">
      <!-- Populated by JS -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-sm" onclick="closeDetails()">Close</button>
    </div>
  </div>
</div>

<script>
var productsList = <?php echo json_encode($products_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
var batchesList = <?php echo json_encode($batches_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
var stockList = <?php echo json_encode($stock_list, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
var defaultTaxRate = <?php echo (float)$default_tax_rate; ?>;
var bizCurCode = "<?php echo e($bizCur); ?>";
var canOverridePrice = <?php echo $canOverridePrice ? 'true' : 'false'; ?>;
var saleLocalDate = "<?php echo e($localNow->format('Y-m-d')); ?>";

// Add initial row only when the permission-protected form is rendered.
if (document.getElementById('itemsContainer')) addItemRow();

function openAddModal() {
  document.getElementById('addModalOverlay').style.display = 'flex';
}

function closeAddModal() {
  document.getElementById('addModalOverlay').style.display = 'none';
}

function addItemRow() {
  const container = document.getElementById('itemsContainer');
  const index = container.children.length;
  
  const div = document.createElement('div');
  div.className = 'item-row';
  div.style.display = 'grid';
  div.style.gridTemplateColumns = 'minmax(190px,1.4fr) minmax(130px,1fr) 80px 110px 30px';
  div.style.gap = '6px';
  div.style.alignItems = 'center';
  div.id = 'row-' + index;

  let optionsHtml = '<option value="">-- Product --</option>';
  productsList.forEach(p => {
    optionsHtml += `<option value="${p.id}" data-price="${p.sale_price}" data-batches="${p.track_batches}" data-uom="${p.uom}">${p.name} (${p.sku})</option>`;
  });

  div.innerHTML = `
    <div><select class="sale-product" name="product_ids[]" required onchange="rowProductChanged(${index})" style="font-size:11.5px; padding:6px;width:100%">${optionsHtml}</select><small class="stock-hint">Select a location and product</small></div>
    <select class="sale-batch" name="batch_ids[]" aria-label="Batch or lot" style="font-size:11.5px;padding:6px"><option value="">Not required</option></select>
    <input type="number" name="quantities[]" min="0.0001" step="0.0001" placeholder="Qty" required oninput="recalcTotals()" style="font-size:11.5px; padding:6px;">
    <input type="number" name="unit_prices[]" min="0" step="0.0001" placeholder="Selling price" title="Defaults to the product selling price" aria-label="Selling price per unit" required oninput="recalcTotals()" style="font-size:11.5px; padding:6px;" ${canOverridePrice ? '' : 'readonly'}>
    <button type="button" class="btn-action reject" onclick="removeItemRow(${index})" style="padding:4px; font-size:11px; text-align:center;">X</button>
  `;
  
  container.appendChild(div);
}

function removeItemRow(index) {
  const container = document.getElementById('itemsContainer');
  if (container.children.length > 1) {
    const row = document.getElementById('row-' + index);
    if (row) row.remove();
    recalcTotals();
  } else {
    alert("Invoice must contain at least one sale item.");
  }
}

function rowProductChanged(index) {
  const row = document.getElementById('row-' + index);
  const select = row.querySelector('.sale-product');
  const priceInput = row.querySelector('input[name="unit_prices[]"]');
  const selectedOpt = select.options[select.selectedIndex];
  const price = selectedOpt.getAttribute('data-price');
  if (price) {
    priceInput.value = parseFloat(price).toFixed(4);
  }
  const productId = parseInt(select.value || '0', 10);
  const locationId = parseInt(document.getElementById('s_loc').value || '0', 10);
  const product = productsList.find(p => parseInt(p.id, 10) === productId);
  const stock = stockList.find(s => parseInt(s.product_id, 10) === productId && parseInt(s.location_id, 10) === locationId);
  row.querySelector('.stock-hint').textContent = `Available: ${stock ? parseFloat(stock.available_quantity).toFixed(4) : '0.0000'} ${product ? product.uom : ''}`;
  const batchSelect = row.querySelector('.sale-batch');
  batchSelect.innerHTML = '<option value="">' + (product && parseInt(product.track_batches, 10) === 1 ? '-- Select batch --' : 'Not required') + '</option>';
  if (product && parseInt(product.track_batches, 10) === 1) {
    batchSelect.required = true;
    batchesList.filter(b => parseInt(b.product_id, 10) === productId && parseInt(b.location_id || '0', 10) === locationId && parseFloat(b.available_quantity || '0') > 0 && (!b.expires_at || b.expires_at >= saleLocalDate)).forEach(b => {
      batchSelect.insertAdjacentHTML('beforeend', `<option value="${b.id}">${b.lot_number} · ${parseFloat(b.available_quantity).toFixed(4)} available${b.expires_at ? ' · exp ' + b.expires_at : ''}</option>`);
    });
  } else {
    batchSelect.required = false;
  }
  recalcTotals();
}

document.getElementById('s_loc')?.addEventListener('change', function() {
  document.querySelectorAll('#itemsContainer .item-row').forEach(row => rowProductChanged(parseInt(row.id.replace('row-', ''), 10)));
});

function recalcTotals() {
  let subtotal = 0;
  const container = document.getElementById('itemsContainer');
  const rows = container.querySelectorAll('.item-row');
  rows.forEach(r => {
    const qty = parseFloat(r.querySelector('input[name="quantities[]"]').value) || 0;
    const price = parseFloat(r.querySelector('input[name="unit_prices[]"]').value) || 0;
    subtotal += (qty * price);
  });
  
  const tax = subtotal * defaultTaxRate;
  const total = subtotal + tax;

  document.getElementById('saleSubtotal').textContent = subtotal.toFixed(2) + " " + bizCurCode;
  document.getElementById('saleTax').textContent = tax.toFixed(2) + " " + bizCurCode;
  document.getElementById('saleTotal').textContent = total.toFixed(2) + " " + bizCurCode;
  document.getElementById('addSaleForm').dataset.total = total.toFixed(4);
}

function paymentMethodChanged() {
  const method = document.getElementById('paymentMethod').value;
  const paid = document.getElementById('amountPaid');
  if (method === 'CREDIT') paid.value = '0.0000';
  else if (parseFloat(paid.value || '0') === 0) paid.value = document.getElementById('addSaleForm').dataset.total || '';
}

// Close modals when clicking outside
document.getElementById('addModalOverlay')?.addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('detailModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeDetails();
});

function viewDetails(saleId) {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.set('view_id', saleId);
  window.location.search = urlParams.toString();
}

function closeDetails() {
  const urlParams = new URLSearchParams(window.location.search);
  urlParams.delete('view_id');
  window.location.search = urlParams.toString();
}

// Safeguard double submissions client-side
document.getElementById('addSaleForm')?.addEventListener('submit', function() {
  document.getElementById('submitBtn').disabled = true;
  document.getElementById('submitBtn').style.opacity = '0.7';
  document.getElementById('submitBtn').textContent = 'Logging sale...';
});
</script>

<style>
.sales-workspace{display:grid;gap:14px}.sales-context-notice{display:flex;align-items:center;gap:9px;padding:11px 14px;background:var(--card);border-radius:var(--radius);color:var(--text2);font-size:10.5px}.sales-context-notice strong{color:var(--text);white-space:nowrap}.sales-page-toolbar{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:18px 20px;background:var(--card);border-radius:var(--radius)}.sales-page-toolbar h2{margin:2px 0 4px;font-size:20px;line-height:1.2;color:var(--text)}.sales-page-toolbar p{margin:0;color:var(--text3);font-size:11px}.sales-page-kicker{color:var(--green);font-size:9px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}.sales-add-primary{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:40px;padding:9px 18px;white-space:nowrap;box-shadow:0 8px 20px rgba(0,0,0,.12)}.sales-section-switcher{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sales-section-link{display:flex;align-items:center;gap:12px;min-height:72px;padding:14px 16px;background:var(--card);border-radius:var(--radius);color:var(--text);text-decoration:none;box-shadow:0 2px 10px rgba(0,0,0,.04);transition:transform .18s ease,box-shadow .18s ease,background .18s ease}.sales-section-link:hover{transform:translateY(-1px);box-shadow:0 7px 18px rgba(0,0,0,.08)}.sales-section-link.active{background:var(--green);color:#fff;box-shadow:0 8px 20px rgba(0,0,0,.12)}.sales-section-link>span:last-child{display:grid;gap:3px}.sales-section-link strong{font-size:12.5px}.sales-section-link small{font-size:9.5px;color:var(--text3)}.sales-section-link.active small{color:rgba(255,255,255,.8)}.sales-section-icon{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:10px;background:var(--bg)}.sales-section-link.active .sales-section-icon{background:rgba(255,255,255,.16)}.sales-workspace .sales-flow-card{margin-bottom:0}.sales-history-header{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap}.sales-history-heading{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.sales-history-heading .btn-sm{text-decoration:none}
.sales-flow-card{margin-bottom:14px;overflow:hidden}.sales-card-header{display:flex;justify-content:space-between;align-items:end;gap:14px;flex-wrap:wrap}.sales-card-header p,.sales-history-help{margin:5px 0 0;color:var(--text3);font-size:10px}.sales-period-filter,.sales-history-filters{display:flex;align-items:end;gap:8px;flex-wrap:wrap}.sales-period-filter label,.sales-history-filters label{display:flex;flex-direction:column;gap:4px;color:var(--text3);font-size:9px}.sales-period-filter input,.sales-history-filters input,.sales-history-filters select{min-height:32px;padding:6px 8px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font-size:11px}.sales-history-filters{max-width:100%;justify-content:flex-end}.sales-table-scroll{overflow:auto}.sales-flow-scroll{max-height:330px}.sales-history-scroll{max-height:58vh}.sales-table-scroll table{min-width:680px}.sales-history-scroll table{min-width:1080px}.sales-table-scroll thead{position:sticky;top:0;z-index:2;background:var(--card)}.sales-stock-out{color:var(--red);font-weight:650}.sales-empty{padding:26px!important;text-align:center;color:var(--text3)}.sales-items-cell{min-width:250px;max-width:390px;white-space:normal;line-height:1.65;color:var(--text2)}.sale-item-headings{display:grid;grid-template-columns:minmax(190px,1.4fr) minmax(130px,1fr) 80px 110px 30px;gap:6px;margin:0 0 5px;color:var(--text3);font-size:9px;font-weight:600}.stock-hint{display:block;color:var(--text3);font-size:9px;margin-top:3px}.history-link{color:inherit;text-decoration:none}.history-link:hover{color:var(--green)}.payment-grid,.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}.page-mode-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px;padding:12px 14px;background:var(--card);border-radius:var(--radius)}.page-mode-bar strong,.page-mode-bar span{display:block}.page-mode-bar span{color:var(--text3);font-size:10px;margin-top:3px}.modal-form-scroll{display:flex;flex-direction:column;min-height:0;max-height:80vh}.modal-form-scroll .modal-body{overflow-y:auto}@media(max-width:760px){.sale-item-headings{display:none}.item-row{grid-template-columns:1fr 1fr!important}.item-row>div{grid-column:1/-1}.payment-grid,.form-grid{grid-template-columns:1fr}.sales-history-filters{justify-content:flex-start}}
@media(max-width:760px){.sales-page-toolbar{align-items:flex-start;padding:15px;flex-direction:column}.sales-add-primary{width:100%}.sales-section-switcher{grid-template-columns:1fr}.sales-section-link{min-height:64px}.sales-history-header{align-items:flex-start}.sales-history-filters{width:100%}}
</style>

<?php
// Handle Invoice Details load in PHP
if (isset($_GET['view_id'])):
    $viewId = (int)$_GET['view_id'];
    $vQuery = "
        SELECT s.*, c.name as customer_name, c.phone as customer_phone,c.email customer_email,c.address customer_address,c.tax_number as customer_tax,
               l.name as location_name, l.address as location_address
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        JOIN business_locations l ON s.location_id = l.id
        WHERE s.id = ? AND s.business_id = ?
        LIMIT 1
    ";
    $vStmt = mysqli_prepare($conn, $vQuery);
    mysqli_stmt_bind_param($vStmt, 'ii', $viewId, $businessId);
    mysqli_stmt_execute($vStmt);
    $sale = mysqli_fetch_assoc(mysqli_stmt_get_result($vStmt));

    if ($sale):
        // Fetch invoice line items
        $iQuery = "
            SELECT si.*,pr.name product_name,pr.sku,pr.uom,pb.lot_number
            FROM sale_items si
            JOIN products pr ON si.product_id=pr.id AND pr.business_id=si.business_id
            LEFT JOIN product_batches pb ON pb.id=si.batch_id AND pb.business_id=si.business_id
            WHERE si.sale_id=? AND si.business_id=?
        ";
        $iStmt = mysqli_prepare($conn, $iQuery);
        mysqli_stmt_bind_param($iStmt, 'ii', $viewId, $businessId);
        mysqli_stmt_execute($iStmt);
        $itemsRes = mysqli_stmt_get_result($iStmt);
        $paymentQuery = "SELECT payment_method,amount,reference_number,paid_at FROM sale_payments WHERE sale_id=? AND business_id=? ORDER BY paid_at,id";
        $paymentViewStmt = mysqli_prepare($conn, $paymentQuery);
        mysqli_stmt_bind_param($paymentViewStmt, 'ii', $viewId, $businessId);
        mysqli_stmt_execute($paymentViewStmt);
        $paymentViewResult = mysqli_stmt_get_result($paymentViewStmt);
        $paymentLines = [];
        while ($payment = mysqli_fetch_assoc($paymentViewResult)) {
            $paymentLines[] = str_replace('_', ' ', $payment['payment_method']) . ': ' . formatCurrency($payment['amount'], $bizCur) . ($payment['reference_number'] ? ' (' . $payment['reference_number'] . ')' : '');
        }
?>
<script>
  (function() {
    const detailsDiv = document.getElementById('detailContent');
    document.getElementById('dt_sale_num').textContent = "<?php echo e($sale['sale_number']); ?>";
    
    let itemsHtml = '';
    <?php while ($it = mysqli_fetch_assoc($itemsRes)): ?>
      itemsHtml += `
        <tr>
          <td><span class="code-badge"><?php echo e($it['sku']); ?></span></td>
          <td class="td-name"><?php echo e($it['product_name']); ?><?php if ($it['lot_number']): ?><small style="display:block;color:var(--text3)">Lot <?php echo e($it['lot_number']); ?></small><?php endif; ?></td>
          <td><?php echo (float)$it['quantity']; ?> (${"<?php echo e($it['uom']); ?>"})</td>
          <td><?php echo formatCurrency($it['unit_price'], $bizCur); ?></td>
          <td class="td-bold" style="color:var(--green);">${"<?php echo formatCurrency($it['line_total'], $bizCur); ?>"}</td>
        </tr>
      `;
    <?php endwhile; ?>

    detailsDiv.innerHTML = `
      <!-- Invoice Print Header Layout -->
      <div style="border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
          <h4 style="font-size:14px; font-weight:700; margin:0;"><?php echo e($_SESSION['first_name'] ?? ''); ?> Inc.</h4>
          <div style="font-size:10px; color:var(--text3);"><?php echo e($sale['location_name']); ?><br><?php echo e($sale['location_address'] ?? ''); ?></div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:10px; color:var(--text3); text-transform:uppercase;">Invoice Date</div>
          <div style="font-weight:500;"><?php echo formatDate($sale['sold_at'], $config['timezone']); ?></div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 16px;">
        <div>
          <strong style="color:var(--text3); text-transform:uppercase; font-size:10px;">Billed To (Customer)</strong>
          <div><?php echo e($sale['customer_name'] ?? 'Walk-In Customer'); ?></div>
          <div style="font-size:10.5px; color:var(--text3);"><?php echo e($sale['customer_phone'] ? ('Phone: '.$sale['customer_phone']) : ''); ?></div>
          <div style="font-size:10.5px; color:var(--text3);"><?php echo e($sale['customer_email'] ? ('Email: '.$sale['customer_email']) : ''); ?></div>
          <div style="font-size:10.5px; color:var(--text3);"><?php echo e($sale['customer_address'] ?? ''); ?></div>
          <div style="font-size:10.5px; color:var(--text3);"><?php echo e($sale['customer_tax'] ? ('TIN: '.$sale['customer_tax']) : ''); ?></div>
        </div>
      </div>
      
      <table class="data-table" style="margin-bottom:12px;">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Product Description</th>
            <th>Qty</th>
            <th>Unit Price</th>
            <th>Line Total</th>
          </tr>
        </thead>
        <tbody>
          \${itemsHtml}
        </tbody>
      </table>

      <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; font-size:12px; border-top:1px solid var(--border); padding-top:10px; font-weight:500;">
        <div style="display:flex; justify-content:space-between; width:220px;">
          <span style="color:var(--text3);">Subtotal:</span>
          <span>${"<?php echo formatCurrency($sale['subtotal'], $bizCur); ?>"}</span>
        </div>
        <div style="display:flex; justify-content:space-between; width:220px;">
          <span style="color:var(--text3);">VAT (Tax):</span>
          <span>${"<?php echo formatCurrency($sale['tax_amount'], $bizCur); ?>"}</span>
        </div>
        <div style="display:flex; justify-content:space-between; width:220px; font-weight:700; font-size:14px; border-top:1px solid var(--border); padding-top:6px; margin-top:4px; color:var(--green);">
          <span>Total:</span>
          <span>${"<?php echo formatCurrency($sale['total_amount'], $bizCur); ?>"}</span>
        </div>
        <div style="display:flex;justify-content:space-between;width:220px"><span>Paid:</span><span>${"<?php echo formatCurrency($sale['amount_paid'], $bizCur); ?>"}</span></div>
        <div style="display:flex;justify-content:space-between;width:220px"><span>Outstanding:</span><span>${"<?php echo formatCurrency(max(0,(float)$sale['total_amount']-(float)$sale['amount_paid']), $bizCur); ?>"}</span></div>
        <div style="width:220px;color:var(--text3);font-size:10px"><?php echo e($paymentLines ? implode(' · ', $paymentLines) : 'No payment recorded'); ?></div>
      </div>
    `;

    document.getElementById('detailModalOverlay').style.display = 'flex';
  })();
</script>
<?php
    endif;
endif;
?>

<?php
if ($canRefundSale && isset($_GET['return_id'])):
    $returnSaleId = (int)$_GET['return_id'];
    $returnSaleStmt = mysqli_prepare($conn, "SELECT id,sale_number,status FROM sales WHERE id=? AND business_id=? AND status IN ('COMPLETED','PARTIALLY_REFUNDED') LIMIT 1");
    mysqli_stmt_bind_param($returnSaleStmt, 'ii', $returnSaleId, $businessId);
    mysqli_stmt_execute($returnSaleStmt);
    $returnSale = mysqli_fetch_assoc(mysqli_stmt_get_result($returnSaleStmt));
    if ($returnSale):
        $returnItemsStmt = mysqli_prepare($conn, "SELECT si.id,si.quantity,si.unit_price,p.name,p.sku,p.uom,COALESCE((SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'),0) returned_quantity FROM sale_items si JOIN products p ON p.id=si.product_id AND p.business_id=si.business_id WHERE si.sale_id=? AND si.business_id=? ORDER BY si.id");
        mysqli_stmt_bind_param($returnItemsStmt, 'ii', $returnSaleId, $businessId);
        mysqli_stmt_execute($returnItemsStmt);
        $returnItems = mysqli_stmt_get_result($returnItemsStmt);
?>
<div class="modal-overlay" id="returnModalOverlay" style="display:flex">
  <div class="modal-content-card modal-lg">
    <div class="modal-header"><div class="modal-title">Return items from <?php echo e($returnSale['sale_number']); ?></div><a class="modal-close-btn" href="index.php?view=history<?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>">×</a></div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" class="modal-form-scroll" onsubmit="this.querySelector('button[type=submit]').disabled=true">
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
        <input type="hidden" name="idempotency_key" value="<?php echo e(createIdempotencyToken()); ?>">
        <input type="hidden" name="action" value="return_sale"><input type="hidden" name="sale_id" value="<?php echo $returnSaleId; ?>">
        <div class="form-grid"><div class="field"><label>Return Number</label><div class="field-wrap"><input name="return_number" value="RET-<?php echo e($localNow->format('YmdHis')); ?>" required></div></div><div class="field"><label>Returned At</label><div class="field-wrap"><input type="datetime-local" name="returned_at" value="<?php echo e($localNow->format('Y-m-d\TH:i')); ?>" required></div></div></div>
        <div class="sales-table-scroll"><table class="data-table"><thead><tr><th>Product</th><th>Sold</th><th>Already Returned</th><th>Return Now</th><th>Disposition</th><th>Unit Price</th></tr></thead><tbody>
        <?php while ($returnItem = mysqli_fetch_assoc($returnItems)): $returnable = max(0, (float)$returnItem['quantity'] - (float)$returnItem['returned_quantity']); ?>
          <tr><td><span class="code-badge"><?php echo e($returnItem['sku']); ?></span> <?php echo e($returnItem['name']); ?></td><td><?php echo number_format((float)$returnItem['quantity'], 4); ?> <?php echo e($returnItem['uom']); ?></td><td><?php echo number_format((float)$returnItem['returned_quantity'], 4); ?></td><td><input type="hidden" name="sale_item_ids[]" value="<?php echo (int)$returnItem['id']; ?>"><input type="number" name="return_quantities[]" min="0" max="<?php echo e(number_format($returnable, 4, '.', '')); ?>" step="0.0001" value="0" <?php echo $returnable <= 0 ? 'readonly' : ''; ?>></td><td><select name="dispositions[]"><option value="RESTOCK">Usable - return to stock</option><option value="NO_RESTOCK">Refund only - do not restock</option></select></td><td><?php echo formatCurrency($returnItem['unit_price'], $bizCur); ?></td></tr>
        <?php endwhile; ?>
        </tbody></table></div>
        <div class="field"><label>Reason</label><div class="field-wrap"><textarea name="reason" rows="3" required placeholder="Reason and condition of returned goods"></textarea></div></div>
      </div>
      <div class="modal-footer"><a class="btn-sm" href="index.php?view=history<?php echo $role_query !== '' ? '&role=' . e(getPreviewRole()) : ''; ?>">Cancel</a><button type="submit" class="btn-primary">Complete Return</button></div>
    </form>
  </div>
</div>
<?php endif; endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
