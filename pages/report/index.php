<?php
$page_title = 'Reports';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = $_SESSION['membership_id'] ?? null;
requirePermission($conn, $membershipId, $businessId, $permissions['view']);

$canGenerateReport = hasPermission($conn, $membershipId, $businessId, $permissions['generate']);
$canExportReport = hasPermission($conn, $membershipId, $businessId, $permissions['export']);
$canManageSchedule = hasPermission($conn, $membershipId, $businessId, $permissions['schedule'])
    && (isBusinessOwner() || getEffectiveUserRole() === 'owner');
if ((isset($_GET['start_date']) || isset($_GET['end_date'])) && !$canGenerateReport) {
    requirePermission($conn, $membershipId, $businessId, $permissions['generate']);
}

$startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$endDate = trim((string)($_GET['end_date'] ?? date('Y-m-t')));
$isValidDate = static function (string $date): bool {
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
};
if (!$isValidDate($startDate) || !$isValidDate($endDate) || $startDate > $endDate) {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}

function fetchReportValue(mysqli $conn, string $sql, int $businessId, string $startDate, string $endDate): float {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iss', $businessId, $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return (float)($row['total'] ?? 0);
}

$returnedQuantitySql = "COALESCE((SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'),0)";
$revenue = fetchReportValue($conn, "SELECT COALESCE(SUM((si.quantity-$returnedQuantitySql)*si.unit_price),0) total FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id WHERE si.business_id=? AND s.status<>'VOIDED' AND DATE(s.sold_at) BETWEEN ? AND ?", $businessId, $startDate, $endDate);
$cogs = fetchReportValue($conn, "SELECT COALESCE(SUM((si.quantity-$returnedQuantitySql)*si.unit_cost_at_sale),0) total FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id WHERE si.business_id=? AND s.status<>'VOIDED' AND DATE(s.sold_at) BETWEEN ? AND ?", $businessId, $startDate, $endDate);
$expenses = fetchReportValue($conn, "SELECT COALESCE(SUM(total_amount),0) total FROM expenses WHERE business_id=? AND status='POSTED' AND DATE(expense_date) BETWEEN ? AND ?", $businessId, $startDate, $endDate);
$grossProfit = $revenue - $cogs;
$netIncome = $grossProfit - $expenses;
$grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;

$businessStmt = mysqli_prepare($conn, 'SELECT business_name,currency_code,timezone FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($businessStmt, 'i', $businessId);
mysqli_stmt_execute($businessStmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt)) ?: ['business_name' => 'Business', 'currency_code' => 'RWF', 'timezone' => 'Africa/Kigali'];
$currency = $business['currency_code'];

$productStmt = mysqli_prepare($conn, "SELECT pr.name,pr.sku,SUM(si.quantity-$returnedQuantitySql) total_qty,SUM((si.quantity-$returnedQuantitySql)*si.unit_price) total_sales,SUM((si.quantity-$returnedQuantitySql)*si.unit_cost_at_sale) total_cogs
    FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id JOIN products pr ON pr.id=si.product_id AND pr.business_id=si.business_id
    WHERE s.business_id=? AND s.status<>'VOIDED' AND DATE(s.sold_at) BETWEEN ? AND ? GROUP BY pr.id,pr.name,pr.sku HAVING total_qty>0 ORDER BY total_sales DESC");
mysqli_stmt_bind_param($productStmt, 'iss', $businessId, $startDate, $endDate);
mysqli_stmt_execute($productStmt);
$productResult = mysqli_stmt_get_result($productStmt);

$expenseStmt = mysqli_prepare($conn, "SELECT c.name category_name,COUNT(e.id) tickets_count,SUM(e.total_amount) total_spent
    FROM expenses e JOIN expense_categories c ON c.id=e.expense_category_id AND c.business_id=e.business_id
    WHERE e.business_id=? AND e.status='POSTED' AND DATE(e.expense_date) BETWEEN ? AND ? GROUP BY c.id,c.name ORDER BY total_spent DESC");
mysqli_stmt_bind_param($expenseStmt, 'iss', $businessId, $startDate, $endDate);
mysqli_stmt_execute($expenseStmt);
$expenseResult = mysqli_stmt_get_result($expenseStmt);

$generatedReports = [];
$historyStmt = mysqli_prepare($conn, "SELECT gr.id,gr.report_type,gr.period_start,gr.period_end,gr.status,gr.net_profit_loss,gr.generated_at,rs.name schedule_name,
    SUM(CASE WHEN rd.status='SENT' THEN 1 ELSE 0 END) sent_count,SUM(CASE WHEN rd.status='FAILED' THEN 1 ELSE 0 END) failed_count,COUNT(rd.id) delivery_count
    FROM generated_reports gr LEFT JOIN report_schedules rs ON rs.id=gr.report_schedule_id AND rs.business_id=gr.business_id
    LEFT JOIN report_deliveries rd ON rd.generated_report_id=gr.id AND rd.business_id=gr.business_id
    WHERE gr.business_id=? GROUP BY gr.id,gr.report_type,gr.period_start,gr.period_end,gr.status,gr.net_profit_loss,gr.generated_at,rs.name
    ORDER BY gr.created_at DESC LIMIT 20");
mysqli_stmt_bind_param($historyStmt, 'i', $businessId);
mysqli_stmt_execute($historyStmt);
$historyResult = mysqli_stmt_get_result($historyStmt);
while ($report = mysqli_fetch_assoc($historyResult)) $generatedReports[] = $report;
?>

<div class="reports-page-header">
  <div>
    <h1>Financial Reports</h1>
    <p>Review performance, compare revenue and costs, and monitor generated reports.</p>
  </div>
  <?php if ($canManageSchedule): ?>
    <a class="btn-sm reports-settings-link" href="../settings/index.php<?php echo e(getRolePreviewQuery()); ?>#report-settings">Report settings</a>
  <?php endif; ?>
</div>

<div class="card report-toolbar">
  <div>
    <strong>Profit &amp; Loss</strong>
    <span><?php echo e($business['business_name']); ?></span>
  </div>
  <?php if ($canGenerateReport || $canExportReport): ?>
    <form method="GET" action="index.php" class="report-filter-form">
      <?php if (isset($_GET['role'])): ?><input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>"><?php endif; ?>
      <?php if ($canGenerateReport): ?>
        <label>From <input type="date" name="start_date" value="<?php echo e($startDate); ?>"></label>
        <label>To <input type="date" name="end_date" value="<?php echo e($endDate); ?>"></label>
        <button class="btn-sm" type="submit">Apply period</button>
      <?php endif; ?>
      <?php if ($canExportReport): ?><button class="btn-sm reports-print" type="button" onclick="window.print()">Print</button><?php endif; ?>
    </form>
  <?php endif; ?>
</div>

<section class="report-summary" id="pnl-overview" aria-label="Financial summary">
  <div class="card report-metric"><span>Revenue</span><strong><?php echo formatCurrency($revenue, $currency); ?></strong><small>Sales excluding tax</small></div>
  <div class="card report-metric"><span>Cost of goods</span><strong><?php echo formatCurrency($cogs, $currency); ?></strong><small>Recorded cost of sales</small></div>
  <div class="card report-metric"><span>Gross profit</span><strong><?php echo formatCurrency($grossProfit, $currency); ?></strong><small><?php echo number_format($grossMargin, 1); ?>% gross margin</small></div>
  <div class="card report-metric"><span>Operating expenses</span><strong><?php echo formatCurrency($expenses, $currency); ?></strong><small>Posted expenses</small></div>
</section>

<div class="report-result <?php echo $netIncome >= 0 ? 'positive' : 'negative'; ?>">
  <div><span>Net operating result</span><small><?php echo e($startDate); ?> to <?php echo e($endDate); ?></small></div>
  <strong><?php echo ($netIncome >= 0 ? '+' : '') . formatCurrency($netIncome, $currency); ?></strong>
</div>

<div class="report-breakdown-grid">
  <section class="card report-table-card">
    <div class="card-header"><div><div class="card-title">Revenue by product</div><div class="report-card-subtitle">Highest sales contribution first</div></div></div>
    <div class="report-table-scroll">
      <table class="data-table">
        <thead><tr><th>SKU</th><th>Product</th><th>Quantity</th><th>COGS</th><th>Revenue</th></tr></thead>
        <tbody>
          <?php if (mysqli_num_rows($productResult) === 0): ?><tr><td colspan="5" class="report-empty">No completed product sales in this period.</td></tr><?php endif; ?>
          <?php while ($row = mysqli_fetch_assoc($productResult)): ?><tr><td><span class="code-badge"><?php echo e($row['sku']); ?></span></td><td class="td-name"><?php echo e($row['name']); ?></td><td><?php echo e((float)$row['total_qty']); ?></td><td><?php echo formatCurrency($row['total_cogs'], $currency); ?></td><td class="td-bold"><?php echo formatCurrency($row['total_sales'], $currency); ?></td></tr><?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card report-table-card">
    <div class="card-header"><div><div class="card-title">Expenses by category</div><div class="report-card-subtitle">Posted operating expenses</div></div></div>
    <div class="report-table-scroll">
      <table class="data-table">
        <thead><tr><th>Category</th><th>Entries</th><th>Total spent</th></tr></thead>
        <tbody>
          <?php if (mysqli_num_rows($expenseResult) === 0): ?><tr><td colspan="3" class="report-empty">No posted expenses in this period.</td></tr><?php endif; ?>
          <?php while ($row = mysqli_fetch_assoc($expenseResult)): ?><tr><td class="td-name"><?php echo e($row['category_name']); ?></td><td><?php echo (int)$row['tickets_count']; ?></td><td class="td-bold"><?php echo formatCurrency($row['total_spent'], $currency); ?></td></tr><?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<section class="card generated-reports-card">
  <div class="card-header"><div><div class="card-title">Generated report history</div><div class="report-card-subtitle">Latest scheduled report runs and delivery outcomes</div></div></div>
  <div class="report-table-scroll history-scroll">
    <table class="data-table generated-reports-table">
      <thead><tr><th>Report</th><th>Period</th><th>Net result</th><th>Delivery</th><th>Generated</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$generatedReports): ?><tr><td colspan="6" class="report-empty">No generated reports are available yet.</td></tr><?php endif; ?>
        <?php foreach ($generatedReports as $report): ?>
          <?php $deliveryLabel = (int)$report['delivery_count'] === 0 ? 'No delivery' : ((int)$report['sent_count'] . ' sent' . ((int)$report['failed_count'] ? ', ' . (int)$report['failed_count'] . ' failed' : '')); ?>
          <tr><td class="td-name"><?php echo e($report['schedule_name'] ?: ucwords(strtolower(str_replace('_', ' ', $report['report_type'])))); ?></td><td><?php echo e(substr($report['period_start'],0,10) . ' – ' . substr($report['period_end'],0,10)); ?></td><td class="td-bold"><?php echo formatCurrency($report['net_profit_loss'], $currency); ?></td><td><?php echo e($deliveryLabel); ?></td><td><?php echo e(formatDate($report['generated_at'], $business['timezone'], 'd M Y, H:i')); ?></td><td><span class="report-status status-<?php echo e(strtolower($report['status'])); ?>"><?php echo e(ucfirst(strtolower($report['status']))); ?></span></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<style>
.reports-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.reports-page-header h1{margin:0;color:var(--text);font-size:20px}.reports-page-header p,.report-card-subtitle{margin:4px 0 0;color:var(--text3);font-size:11px;line-height:1.5}.reports-settings-link,.reports-print{border:1px solid var(--border)!important;background:var(--card)!important;color:var(--text2)!important;text-decoration:none}.report-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;margin-bottom:14px}.report-toolbar>div{display:flex;flex-direction:column;gap:3px}.report-toolbar>div span{color:var(--text3);font-size:10px}.report-filter-form{display:flex;align-items:flex-end;gap:9px;flex-wrap:wrap}.report-filter-form label{display:flex;flex-direction:column;gap:5px;color:var(--text3);font-size:9px;font-weight:600}.report-filter-form input{min-height:34px;padding:6px 9px;border:1px solid var(--border);border-radius:var(--radius);background:var(--card);color:var(--text);font:inherit}.report-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}.report-metric{padding:15px;box-shadow:none}.report-metric span,.report-metric small{display:block;color:var(--text3);font-size:10px}.report-metric strong{display:block;margin:9px 0 6px;color:var(--text);font-size:18px;overflow-wrap:anywhere}.report-result{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:15px 17px;margin-bottom:14px;border-radius:var(--radius-lg);background:var(--card)}.report-result span,.report-result small{display:block}.report-result span{color:var(--text2);font-size:11px;font-weight:600}.report-result small{margin-top:3px;color:var(--text3);font-size:9px}.report-result strong{font-size:18px}.report-result.positive strong{color:var(--green)}.report-result.negative strong{color:var(--red)}.report-breakdown-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:14px;align-items:start}.report-table-card,.generated-reports-card{overflow:hidden;box-shadow:none}.report-table-scroll{max-height:380px;overflow:auto}.report-table-scroll .data-table{min-width:620px}.report-breakdown-grid>section:last-child .data-table{min-width:420px}.report-empty{text-align:center!important;color:var(--text3)!important;padding:26px!important}.generated-reports-card{margin-top:14px}.history-scroll{max-height:340px}.generated-reports-table{min-width:850px!important}.report-status{display:inline-flex;padding:5px 8px;border-radius:999px;background:var(--bg);color:var(--text2);font-size:9px;font-weight:650}.status-ready{background:var(--green-bg);color:var(--green)}.status-failed{background:var(--red-bg);color:var(--red)}@media(max-width:1000px){.report-summary{grid-template-columns:repeat(2,1fr)}.report-breakdown-grid{grid-template-columns:1fr}}@media(max-width:680px){.reports-page-header,.report-toolbar{align-items:stretch;flex-direction:column}.report-filter-form{display:grid;grid-template-columns:1fr 1fr}.report-filter-form button{align-self:end}.report-summary{grid-template-columns:1fr 1fr}.report-result{align-items:flex-start;flex-direction:column}.report-result strong{font-size:17px}}@media(max-width:420px){.report-filter-form,.report-summary{grid-template-columns:1fr}}@media print{body{background:#fff;color:#000}.topbar,.app-grid-panel,.view-as-widget,.reports-page-header,.report-filter-form,.generated-reports-card{display:none!important}.content{margin:0!important;padding:0!important}.card{border:0!important;box-shadow:none!important;background:#fff!important}.report-table-scroll{max-height:none;overflow:visible}.data-table th,.data-table td{border:1px solid #ddd!important}}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
