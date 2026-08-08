<?php
$page_title = 'Profit & Loss Statement';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;

// Set default dates to current month
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-t');

// 1. Calculate Revenue (completed sales total minus tax)
$revQuery = "
    SELECT COALESCE(SUM(total_amount - tax_amount), 0.0) as revenue
    FROM sales
    WHERE business_id = ? AND status = 'COMPLETED' AND DATE(sold_at) BETWEEN ? AND ?
";
$rStmt = mysqli_prepare($conn, $revQuery);
mysqli_stmt_bind_param($rStmt, 'iss', $businessId, $start_date, $end_date);
mysqli_stmt_execute($rStmt);
$revenue = mysqli_fetch_assoc(mysqli_stmt_get_result($rStmt))['revenue'] ?? 0.0;

// 2. Calculate COGS
$cogsQuery = "
    SELECT COALESCE(SUM(total_cogs), 0.0) as cogs
    FROM sales
    WHERE business_id = ? AND status = 'COMPLETED' AND DATE(sold_at) BETWEEN ? AND ?
";
$cStmt = mysqli_prepare($conn, $cogsQuery);
mysqli_stmt_bind_param($cStmt, 'iss', $businessId, $start_date, $end_date);
mysqli_stmt_execute($cStmt);
$cogs = mysqli_fetch_assoc(mysqli_stmt_get_result($cStmt))['cogs'] ?? 0.0;

// 3. Gross Profit
$gross_profit = $revenue - $cogs;
$gp_margin = ($revenue > 0) ? ($gross_profit / $revenue) * 100 : 0.0;

// 4. Calculate Expenses
$expQuery = "
    SELECT COALESCE(SUM(total_amount), 0.0) as expenses
    FROM expenses
    WHERE business_id = ? AND status = 'POSTED' AND DATE(expense_date) BETWEEN ? AND ?
";
$eStmt = mysqli_prepare($conn, $expQuery);
mysqli_stmt_bind_param($eStmt, 'iss', $businessId, $start_date, $end_date);
mysqli_stmt_execute($eStmt);
$expenses = mysqli_fetch_assoc(mysqli_stmt_get_result($eStmt))['expenses'] ?? 0.0;

// 5. Net Operating Income
$net_income = $gross_profit - $expenses;

// Fetch business currency
$bizQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt))['currency_code'] ?? 'RWF';

// Fetch product sales breakdown
$prodBreakdownQuery = "
    SELECT pr.name, pr.sku, SUM(si.quantity) as total_qty, SUM(si.line_total) as total_sales, SUM(si.cogs_total) as total_cogs
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products pr ON si.product_id = pr.id
    WHERE s.business_id = ? AND s.status = 'COMPLETED' AND DATE(s.sold_at) BETWEEN ? AND ?
    GROUP BY pr.id
    ORDER BY total_sales DESC
";
$pbStmt = mysqli_prepare($conn, $prodBreakdownQuery);
mysqli_stmt_bind_param($pbStmt, 'iss', $businessId, $start_date, $end_date);
mysqli_stmt_execute($pbStmt);
$pbResult = mysqli_stmt_get_result($pbStmt);

// Fetch expenses breakdown by category
$expBreakdownQuery = "
    SELECT c.name as category_name, COUNT(e.id) as tickets_count, SUM(e.total_amount) as total_spent
    FROM expenses e
    JOIN expense_categories c ON e.expense_category_id = c.id
    WHERE e.business_id = ? AND e.status = 'POSTED' AND DATE(e.expense_date) BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY total_spent DESC
";
$ebStmt = mysqli_prepare($conn, $expBreakdownQuery);
mysqli_stmt_bind_param($ebStmt, 'iss', $businessId, $start_date, $end_date);
mysqli_stmt_execute($ebStmt);
$ebResult = mysqli_stmt_get_result($ebStmt);
?>

<!-- Filter header -->
<div class="card" style="margin-bottom: 24px;">
  <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
    <div class="card-title">Select Reporting Period</div>
    
    <form method="GET" action="index.php" style="display: flex; gap: 10px; align-items: center;">
      <?php if (isset($_GET['role'])): ?>
        <input type="hidden" name="role" value="<?php echo e($_GET['role']); ?>">
      <?php endif; ?>
      <label style="font-size:11px; font-weight:500;">Start:</label>
      <input type="date" name="start_date" value="<?php echo e($start_date); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
      
      <label style="font-size:11px; font-weight:500;">End:</label>
      <input type="date" name="end_date" value="<?php echo e($end_date); ?>" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
      
      <button class="btn-sm" type="submit">Generate Report</button>
      <button class="btn-sm" type="button" onclick="window.print()" style="background:var(--bg); border:1px solid var(--border); color:var(--text)">Print Statement</button>
    </form>
  </div>
</div>

<!-- Statement Header Details -->
<div style="text-align: center; margin-bottom: 30px;" class="print-only-header">
  <h2 style="font-size:22px; font-weight:700; margin:0;"><?php echo e($_SESSION['first_name'] ?? ''); ?> Inc.</h2>
  <h3 style="font-size:14px; font-weight:600; text-transform:uppercase; color:var(--text3); margin-top:4px; letter-spacing:0.5px;">Profit &amp; Loss Statement</h3>
  <div style="font-size:12px; color:var(--text3); margin-top:2px;">For the period: <?php echo e($start_date); ?> to <?php echo e($end_date); ?></div>
</div>

<!-- Financial Summary Cards -->
<div class="stats-grid-8" style="margin-bottom: 24px;" id="pnl-overview">
  <div class="stat-card">
    <div class="stat-top"><span class="stat-trend trend-up">Revenue</span></div>
    <div class="stat-val"><?php echo formatCurrency($revenue, $bizCur); ?></div>
    <div class="stat-card-desc">Net Revenue Sales</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-trend trend-warn">COGS</span></div>
    <div class="stat-val"><?php echo formatCurrency($cogs, $bizCur); ?></div>
    <div class="stat-card-desc">Cost of Goods Sold (WAVG)</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-trend trend-blue">Gross Profit</span></div>
    <div class="stat-val"><?php echo formatCurrency($gross_profit, $bizCur); ?></div>
    <div class="stat-card-desc">Margin: <?php echo number_format($gp_margin, 2); ?>%</div>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span class="stat-trend trend-down">Expenses</span></div>
    <div class="stat-val"><?php echo formatCurrency($expenses, $bizCur); ?></div>
    <div class="stat-card-desc">Total Admin Expenditures</div>
  </div>
</div>

<!-- Net balance banner -->
<div class="alert-msg <?php echo ($net_income >= 0) ? 'success' : 'error'; ?>" style="margin-bottom: 24px; padding: 16px;">
  <span style="font-size: 14px;">
    Net operating income balance: 
    <strong><?php echo ($net_income >= 0 ? '+' : '') . formatCurrency($net_income, $bizCur); ?></strong> 
    (<?php echo ($net_income >= 0) ? 'Operating Profit' : 'Operating Loss'; ?>)
  </span>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start;">
  
  <!-- Left Side: Product Sales breakdown -->
  <div class="card">
    <div class="card-header"><div class="card-title">Revenue Breakdown by Product</div></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>SKU Code</th>
          <th>Product Name</th>
          <th>Qty Sold</th>
          <th>COGS Valuation</th>
          <th>Sales Revenue</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($pbResult) === 0): ?>
          <tr>
            <td colspan="5" style="text-align: center; color: var(--text3); padding: 20px;">No product sales recorded in this period.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($pbResult)): ?>
            <tr>
              <td><span class="code-badge"><?php echo e($row['sku']); ?></span></td>
              <td class="td-name"><?php echo e($row['name']); ?></td>
              <td><?php echo (float)$row['total_qty']; ?></td>
              <td><?php echo formatCurrency($row['total_cogs'], $bizCur); ?></td>
              <td class="td-bold" style="color:var(--green);"><?php echo formatCurrency($row['total_sales'], $bizCur); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Right Side: Expenses breakdown -->
  <div class="card">
    <div class="card-header"><div class="card-title">Expense Breakdown by Category</div></div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Category Name</th>
          <th>Tickets Count</th>
          <th>Total Spent</th>
        </tr>
      </thead>
      <tbody>
        <?php if (mysqli_num_rows($ebResult) === 0): ?>
          <tr>
            <td colspan="3" style="text-align: center; color: var(--text3); padding: 20px;">No posted expenses recorded in this period.</td>
          </tr>
        <?php else: ?>
          <?php while ($row = mysqli_fetch_assoc($ebResult)): ?>
            <tr>
              <td class="td-name"><?php echo e($row['category_name']); ?></td>
              <td><?php echo (int)$row['tickets_count']; ?></td>
              <td class="td-bold" style="color:var(--red);"><?php echo formatCurrency($row['total_spent'], $bizCur); ?></td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
@media print {
  body {
    background: #fff;
    color: #000;
  }
  .topbar, .app-grid-panel, .card-header select, .card-header input, .card-header button, .btn-sm, .view-as-widget {
    display: none !important;
  }
  .content {
    margin: 0 !important;
    padding: 0 !important;
  }
  .card {
    border: none !important;
    box-shadow: none !important;
    background: #fff !important;
  }
  .data-table th, .data-table td {
    border: 1px solid #ddd !important;
  }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
