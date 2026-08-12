<?php
$page_title = 'Dashboard';
$extra_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

// Render the real role, or the Super Admin's validated read-only preview role.
$user_role = getEffectiveUserRole();
$role_query = getRolePreviewQuery();
$businessId = $_SESSION['active_business_id'] ?? 0;
$dashboardMembershipId = $_SESSION['membership_id'] ?? null;
$canViewBusinessApprovals = hasPermission($conn, $dashboardMembershipId, $businessId, 'platform.businesses.view');
$canViewSales = hasPermission($conn, $dashboardMembershipId, $businessId, 'sales.view');
$canCreateSales = hasPermission($conn, $dashboardMembershipId, $businessId, 'sales.create');
$canViewPurchases = hasPermission($conn, $dashboardMembershipId, $businessId, 'purchases.view');
$canCreatePurchases = hasPermission($conn, $dashboardMembershipId, $businessId, 'purchases.create');
$canViewEmployees = hasPermission($conn, $dashboardMembershipId, $businessId, 'employees.view');
$canViewLeave = hasPermission($conn, $dashboardMembershipId, $businessId, 'leave.self.view')
    || hasPermission($conn, $dashboardMembershipId, $businessId, 'leave.team.view');
$canSubmitLeave = hasPermission($conn, $dashboardMembershipId, $businessId, 'leave.self.create');
$canViewReports = hasPermission($conn, $dashboardMembershipId, $businessId, 'reports.view');
$canViewAudit = hasPermission($conn, $dashboardMembershipId, $businessId, 'audit.view');
?>

<div class="dashboard-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
  <div>
    <h1 style="font-size: 20px; font-weight: 600; color: var(--text); margin-bottom: 4px;">
      Welcome back, <?php echo e($_SESSION['first_name'] ?? 'User'); ?>!
    </h1>
    <p style="font-size: 12px; color: var(--text3);">
      Here is a summary of your <?php echo ($user_role === 'super_admin') ? 'platform administration' : 'business'; ?> activities.
    </p>
  </div>
  
  <div style="display: flex; gap: 10px;">
    <span class="status-pill pill-green">Live database data</span>
  </div>
</div>

<!-- ==========================================
     ROLE: SUPER ADMIN DASHBOARD
     ========================================== -->
<?php if ($user_role === 'super_admin'): ?>
  
  <!-- Pending Business Banner notification -->
  <?php
  // Query pending businesses count
  $pCountQuery = "SELECT COUNT(*) as pending FROM businesses WHERE approval_status = 'PENDING'";
  $pCountResult = mysqli_query($conn, $pCountQuery);
  $pCount = mysqli_fetch_assoc($pCountResult)['pending'] ?? 0;
  ?>
  <?php if ($canViewBusinessApprovals && $pCount > 0): ?>
    <div class="alert-msg warning" id="notice-banner" style="margin-bottom: 24px;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>There are <strong><?php echo (int)$pCount; ?> pending business registrations</strong> waiting for approval. <a href="../business_approval/index.php<?php echo $role_query; ?>" style="font-weight: 600; color: inherit; text-decoration: underline;">Manage approvals now</a></span>
    </div>
  <?php endif; ?>

  <?php
  // Total platform registrations
  $totQuery = "
      SELECT 
          COUNT(*) as total_biz,
          (SELECT COUNT(*) FROM users) as total_users,
          (SELECT COUNT(*) FROM audit_logs) as total_logs
      FROM businesses
  ";
  $totResult = mysqli_query($conn, $totQuery);
  $totStats = mysqli_fetch_assoc($totResult);
  ?>
  <div class="stats-grid-8" style="margin-bottom: 24px;">
    <div class="stat-card">
      <div class="stat-top"><span class="stat-trend trend-blue">Businesses</span></div>
      <div class="stat-val"><?php echo (int)$totStats['total_biz']; ?></div>
      <div class="stat-card-desc">Total registered entities</div>
    </div>
    <div class="stat-card">
      <div class="stat-top"><span class="stat-trend trend-warn">Pending Approvals</span></div>
      <div class="stat-val"><?php echo (int)$pCount; ?></div>
      <div class="stat-card-desc">Registrations in queue</div>
    </div>
    <div class="stat-card">
      <div class="stat-top"><span class="stat-trend trend-up">Platform Users</span></div>
      <div class="stat-val"><?php echo (int)$totStats['total_users']; ?></div>
      <div class="stat-card-desc">Active credentials</div>
    </div>
    <div class="stat-card">
      <div class="stat-top"><span class="stat-trend trend-blue">Audit Events</span></div>
      <div class="stat-val"><?php echo (int)$totStats['total_logs']; ?></div>
      <div class="stat-card-desc">Security logs recorded</div>
    </div>
  </div>

  <!-- Recent Platform Activity Logs -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Recent Platform Actions (Audit Logs)</div>
      <a class="btn-sm" style="text-decoration:none;" href="../audit/index.php<?php echo $role_query; ?>">View All Activity Log</a>
    </div>
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Actor Email</th>
          <th>Action</th>
          <th>Resource Type</th>
          <th>Target ID</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $saLogQuery = "
            SELECT a.*, u.email as actor_email 
            FROM audit_logs a
            LEFT JOIN users u ON a.actor_user_id = u.id
            ORDER BY a.created_at DESC 
            LIMIT 5
        ";
        $saLogResult = mysqli_query($conn, $saLogQuery);
        while ($lRow = mysqli_fetch_assoc($saLogResult)):
            $ip = $lRow['ip_address'] ? inet_ntop($lRow['ip_address']) : 'N/A';
        ?>
          <tr>
            <td><?php echo formatDate($lRow['created_at']); ?></td>
            <td class="td-name"><?php echo e($lRow['actor_email'] ?? 'System/Public'); ?></td>
            <td><span class="code-badge"><?php echo e($lRow['action']); ?></span></td>
            <td><?php echo e($lRow['entity_type']); ?></td>
            <td><?php echo e($lRow['entity_id']); ?></td>
            <td><code><?php echo e($ip); ?></code></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

<!-- ==========================================
     ROLE: BUSINESS OWNER DASHBOARD
     ========================================== -->
<?php elseif ($user_role === 'owner'): ?>
  
  <?php
  // Fetch real summary stats from business records
  // 1. Sales
  $salesValQuery = "SELECT SUM(total_amount) as total FROM sales WHERE business_id = ? AND status = 'COMPLETED'";
  $sStmt = mysqli_prepare($conn, $salesValQuery);
  mysqli_stmt_bind_param($sStmt, 'i', $businessId);
  mysqli_stmt_execute($sStmt);
  $totalSales = mysqli_fetch_assoc(mysqli_stmt_get_result($sStmt))['total'] ?? 0;

  // 2. Purchases
  $purchValQuery = "SELECT SUM(total_amount) as total FROM purchases WHERE business_id = ? AND status = 'RECEIVED'";
  $pStmt = mysqli_prepare($conn, $purchValQuery);
  mysqli_stmt_bind_param($pStmt, 'i', $businessId);
  mysqli_stmt_execute($pStmt);
  $totalPurch = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt))['total'] ?? 0;

  // 3. Expenses
  $expValQuery = "SELECT SUM(total_amount) as total FROM expenses WHERE business_id = ? AND status = 'POSTED'";
  $eStmt = mysqli_prepare($conn, $expValQuery);
  mysqli_stmt_bind_param($eStmt, 'i', $businessId);
  mysqli_stmt_execute($eStmt);
  $totalExpenses = mysqli_fetch_assoc(mysqli_stmt_get_result($eStmt))['total'] ?? 0;

  // 4. Products & Stock Counts
  $stockCountQuery = "SELECT SUM(quantity_on_hand) as total FROM inventory_balances WHERE business_id = ?";
  $stkStmt = mysqli_prepare($conn, $stockCountQuery);
  mysqli_stmt_bind_param($stkStmt, 'i', $businessId);
  mysqli_stmt_execute($stkStmt);
  $totalStock = mysqli_fetch_assoc(mysqli_stmt_get_result($stkStmt))['total'] ?? 0;

  // Fetch currency code from settings
  $bizCurQuery = "SELECT currency_code,timezone FROM businesses WHERE id = ? LIMIT 1";
  $bcStmt = mysqli_prepare($conn, $bizCurQuery);
  mysqli_stmt_bind_param($bcStmt, 'i', $businessId);
  mysqli_stmt_execute($bcStmt);
  $businessSettings = mysqli_fetch_assoc(mysqli_stmt_get_result($bcStmt)) ?: [];
  $bizCur = $businessSettings['currency_code'] ?? 'RWF';
  try { $dashboardTimezone = new DateTimeZone($businessSettings['timezone'] ?? 'UTC'); }
  catch (Throwable $error) { $dashboardTimezone = new DateTimeZone('UTC'); }

  // Use the recorded cost on completed sale lines instead of a percentage estimate.
  $returnedQtySql = "COALESCE((SELECT SUM(sri.quantity) FROM sale_return_items sri JOIN sale_returns sr ON sr.id=sri.sale_return_id WHERE sri.sale_item_id=si.id AND sr.status='COMPLETED'),0)";
  $cogsStmt = mysqli_prepare($conn, "SELECT COALESCE(SUM((si.quantity-$returnedQtySql)*si.unit_cost_at_sale),0) total FROM sale_items si JOIN sales s ON s.id=si.sale_id AND s.business_id=si.business_id WHERE si.business_id=? AND s.status='COMPLETED'");
  mysqli_stmt_bind_param($cogsStmt, 'i', $businessId);
  mysqli_stmt_execute($cogsStmt);
  $actualCOGS = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($cogsStmt))['total'] ?? 0);

  // Build a live six-month series in the company's timezone.
  $chartCurrentMonth = (new DateTimeImmutable('now', $dashboardTimezone))->modify('first day of this month')->setTime(0, 0);
  $chartFirstMonth = $chartCurrentMonth->modify('-5 months');
  $chartAfterLastMonth = $chartCurrentMonth->modify('+1 month');
  $chartMonths = [];
  for ($monthIndex = 0; $monthIndex < 6; $monthIndex++) {
      $month = $chartFirstMonth->modify('+' . $monthIndex . ' months');
      $chartMonths[$month->format('Y-m')] = ['label'=>$month->format('M'),'sales'=>0.0,'purchases'=>0.0];
  }
  $chartStartUtc = $chartFirstMonth->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  $chartEndUtc = $chartAfterLastMonth->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  $salesTrendStmt = mysqli_prepare($conn, "SELECT sold_at activity_at,total_amount FROM sales WHERE business_id=? AND status='COMPLETED' AND sold_at>=? AND sold_at<?");
  mysqli_stmt_bind_param($salesTrendStmt, 'iss', $businessId, $chartStartUtc, $chartEndUtc);
  mysqli_stmt_execute($salesTrendStmt);
  $salesTrendResult = mysqli_stmt_get_result($salesTrendStmt);
  while ($trendRow = mysqli_fetch_assoc($salesTrendResult)) {
      $monthKey = (new DateTimeImmutable($trendRow['activity_at'], new DateTimeZone('UTC')))->setTimezone($dashboardTimezone)->format('Y-m');
      if (isset($chartMonths[$monthKey])) $chartMonths[$monthKey]['sales'] += (float)$trendRow['total_amount'];
  }
  $purchaseTrendStmt = mysqli_prepare($conn, "SELECT COALESCE(received_at,purchase_date) activity_at,total_amount FROM purchases WHERE business_id=? AND status='RECEIVED' AND COALESCE(received_at,purchase_date)>=? AND COALESCE(received_at,purchase_date)<?");
  mysqli_stmt_bind_param($purchaseTrendStmt, 'iss', $businessId, $chartStartUtc, $chartEndUtc);
  mysqli_stmt_execute($purchaseTrendStmt);
  $purchaseTrendResult = mysqli_stmt_get_result($purchaseTrendStmt);
  while ($trendRow = mysqli_fetch_assoc($purchaseTrendResult)) {
      $monthKey = (new DateTimeImmutable($trendRow['activity_at'], new DateTimeZone('UTC')))->setTimezone($dashboardTimezone)->format('Y-m');
      if (isset($chartMonths[$monthKey])) $chartMonths[$monthKey]['purchases'] += (float)$trendRow['total_amount'];
  }
  $chartMaxValue = 0.0;
  foreach ($chartMonths as $chartMonth) $chartMaxValue = max($chartMaxValue, $chartMonth['sales'], $chartMonth['purchases']);
  $chartTick = $chartMaxValue > 0 ? max(1, ceil($chartMaxValue / 4)) : 1;
  $chartAxisMax = $chartTick * 4;
  $compactChartNumber = static function (float $value): string {
      if ($value >= 1000000000) return rtrim(rtrim(number_format($value / 1000000000, 1, '.', ''), '0'), '.') . 'B';
      if ($value >= 1000000) return rtrim(rtrim(number_format($value / 1000000, 1, '.', ''), '0'), '.') . 'M';
      if ($value >= 1000) return rtrim(rtrim(number_format($value / 1000, 1, '.', ''), '0'), '.') . 'K';
      return number_format($value, 0);
  };
  $currentMonthTrend = end($chartMonths);
  $currentMonthSales = (float)$currentMonthTrend['sales'];
  $currentMonthPurchases = (float)$currentMonthTrend['purchases'];
  $currentMonthDifference = $currentMonthSales - $currentMonthPurchases;
  ?>

  <!-- 8 summary cards -->
  <div class="stats-grid-8">
    <div class="stat-card" id="stat-sales">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--blue-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--blue)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <span class="stat-trend trend-blue">Sales</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($totalSales, $bizCur); ?></div>
      <div class="stat-card-desc">Total sales revenue</div>
    </div>
    
    <div class="stat-card" id="stat-purchases">
      <div class="stat-top">
        <div class="stat-icon" style="background:#FBF0EB">
          <svg viewBox="0 0 24 24" style="stroke:var(--orange)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <span class="stat-trend trend-warn">Purchases</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($totalPurch, $bizCur); ?></div>
      <div class="stat-card-desc">Received supply spending</div>
    </div>
    
    <div class="stat-card" id="stat-expenses">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--red-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--red)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <span class="stat-trend trend-down">Expenses</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($totalExpenses, $bizCur); ?></div>
      <div class="stat-card-desc">Admin expenses total</div>
    </div>
    
    <div class="stat-card" id="stat-revenue">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--green-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--green)"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
        </div>
        <span class="stat-trend trend-up">Revenue</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($totalSales, $bizCur); ?></div>
      <div class="stat-card-desc">Cash collected</div>
    </div>

    <!-- Profit / Loss / Stock counts -->
    <?php
    $netProfit = $totalSales - $actualCOGS - $totalExpenses;
    $lossVal = ($netProfit < 0) ? abs($netProfit) : 0;
    $profitVal = ($netProfit > 0) ? $netProfit : 0;
    ?>
    <div class="stat-card" id="stat-profit">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--green-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--green)"><path d="M23 6l-9.5 9.5-5-5L1 18"/></svg>
        </div>
        <span class="stat-trend trend-up">Profit</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($profitVal, $bizCur); ?></div>
      <div class="stat-card-desc">Net profit from recorded costs</div>
    </div>
    
    <div class="stat-card" id="stat-loss">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--red-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--red)"><path d="M23 18l-9.5-9.5-5 5L1 6"/></svg>
        </div>
        <span class="stat-trend trend-down">Loss</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($lossVal, $bizCur); ?></div>
      <div class="stat-card-desc">Net loss from recorded costs</div>
    </div>
    
    <div class="stat-card" id="stat-stock">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--blue-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--blue)"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </div>
        <span class="stat-trend trend-blue">Stock On Hand</span>
      </div>
      <div class="stat-val"><?php echo number_format($totalStock, 1); ?> units</div>
      <div class="stat-card-desc">Total quantities in store</div>
    </div>

    <?php
    $productCountStmt = mysqli_prepare($conn, 'SELECT COUNT(*) total FROM products WHERE business_id=? AND is_active=1');
    mysqli_stmt_bind_param($productCountStmt, 'i', $businessId);
    mysqli_stmt_execute($productCountStmt);
    $activeProductCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($productCountStmt))['total'] ?? 0);
    ?>
    <div class="stat-card" id="stat-products">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--amber-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </div>
        <span class="stat-trend trend-warn">Products</span>
      </div>
      <div class="stat-val"><?php echo $activeProductCount; ?> Items</div>
      <div class="stat-card-desc">Active registered products</div>
    </div>
  </div>

  <div class="dashboard-split-grid">
    <!-- Visual Comparison Chart -->
    <div class="card" id="sales-trends">
      <div class="card-header">
        <div class="card-title">Sales &amp; Purchases Trends — Last 6 Months</div>
        <div style="display: flex; gap: 12px; font-size: 11px;">
          <span style="display: flex; align-items: center; gap: 4px;"><span style="width:8px; height:8px; background:var(--blue); border-radius:50%;"></span> Sales</span>
          <span style="display: flex; align-items: center; gap: 4px;"><span style="width:8px; height:8px; background:var(--orange); border-radius:50%;"></span> Purchases</span>
        </div>
      </div>
      
      <div style="display: flex; justify-content: space-between; font-size: 11.5px; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 16px;">
        <div>
          <div style="color: var(--text3);">Sales (Current Month)</div>
          <div style="font-size: 16px; font-weight:600; color: var(--blue);"><?php echo formatCurrency($currentMonthSales, $bizCur); ?></div>
        </div>
        <div>
          <div style="color: var(--text3);">Purchases (Current Month)</div>
          <div style="font-size: 16px; font-weight:600; color: var(--orange);"><?php echo formatCurrency($currentMonthPurchases, $bizCur); ?></div>
        </div>
        <div>
          <div style="color: var(--text3);">Difference</div>
          <div style="font-size: 16px; font-weight:600; color: <?php echo $currentMonthDifference >= 0 ? 'var(--green)' : 'var(--red)'; ?>;"><?php echo ($currentMonthDifference >= 0 ? '+' : '-') . formatCurrency(abs($currentMonthDifference), $bizCur); ?></div>
        </div>
      </div>

      <div class="chart-container">
        <svg viewBox="0 0 600 240" class="bm-svg-chart" role="img" aria-label="Sales and purchases totals for the last six months">
          <?php for ($tickIndex = 0; $tickIndex <= 4; $tickIndex++): $tickY = 200 - ($tickIndex * 42.5); $tickValue = $chartTick * $tickIndex; ?>
            <line x1="55" y1="<?php echo e(number_format($tickY, 1, '.', '')); ?>" x2="570" y2="<?php echo e(number_format($tickY, 1, '.', '')); ?>" stroke="var(--border)" <?php echo $tickIndex > 0 ? 'stroke-dasharray="4"' : ''; ?> />
            <text x="47" y="<?php echo e(number_format($tickY + 4, 1, '.', '')); ?>" fill="var(--text3)" font-size="10" text-anchor="end"><?php echo e($compactChartNumber((float)$tickValue)); ?></text>
          <?php endfor; ?>
          <?php $chartIndex = 0; foreach ($chartMonths as $chartMonth):
              $groupX = 98 + ($chartIndex * 86);
              $salesHeight = ($chartMonth['sales'] / $chartAxisMax) * 170;
              $purchaseHeight = ($chartMonth['purchases'] / $chartAxisMax) * 170;
          ?>
            <rect x="<?php echo $groupX - 18; ?>" y="<?php echo e(number_format(200 - $salesHeight, 2, '.', '')); ?>" width="16" height="<?php echo e(number_format($salesHeight, 2, '.', '')); ?>" rx="2" fill="var(--blue)"><title><?php echo e($chartMonth['label'] . ' sales: ' . formatCurrency($chartMonth['sales'], $bizCur)); ?></title></rect>
            <rect x="<?php echo $groupX + 2; ?>" y="<?php echo e(number_format(200 - $purchaseHeight, 2, '.', '')); ?>" width="16" height="<?php echo e(number_format($purchaseHeight, 2, '.', '')); ?>" rx="2" fill="var(--orange)"><title><?php echo e($chartMonth['label'] . ' purchases: ' . formatCurrency($chartMonth['purchases'], $bizCur)); ?></title></rect>
            <text x="<?php echo $groupX; ?>" y="224" fill="var(--text3)" font-size="10" text-anchor="middle"><?php echo e($chartMonth['label']); ?></text>
          <?php $chartIndex++; endforeach; ?>
        </svg>
      </div>
    </div>

    <!-- Profit & Loss Summary Card -->
    <div class="card" id="pnl-overview">
      <div class="card-header">
        <div class="card-title">Profit &amp; Loss Overview</div>
      </div>
      <div class="pnl-list">
        <div class="pnl-row">
          <span class="pnl-label">Total Revenue</span>
          <span class="pnl-val" style="color: var(--green);"><?php echo formatCurrency($totalSales, $bizCur); ?></span>
        </div>
        <div class="pnl-row">
          <span class="pnl-label">Cost of Goods Sold (COGS)</span>
          <span class="pnl-val" style="color: var(--text);"><?php echo formatCurrency($actualCOGS, $bizCur); ?></span>
        </div>
        <div class="pnl-row">
          <span class="pnl-label">Total Expenses</span>
          <span class="pnl-val" style="color: var(--amber);"><?php echo formatCurrency($totalExpenses, $bizCur); ?></span>
        </div>
        <div style="border-top: 1px solid var(--border); margin: 8px 0; padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <span style="font-size: 11px; color: var(--text3); text-transform: uppercase; font-weight: 600;">Net Balance</span>
            <div style="font-size: 20px; font-weight: 700; color: <?php echo ($netProfit > 0) ? 'var(--green)' : 'var(--red)'; ?>;">
              <?php echo ($netProfit > 0 ? '+' : '') . formatCurrency($netProfit, $bizCur); ?>
            </div>
          </div>
          <span class="status-pill <?php echo ($netProfit > 0) ? 'pill-green' : 'pill-red'; ?>">
            <?php echo ($netProfit > 0) ? 'Profitable' : 'Deficit'; ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Sales & Purchases side by side -->
  <div class="recent-activities-grid">
    <!-- Recent Sales -->
    <div class="card" id="recent-sales">
      <div class="card-header">
        <div class="card-title">Recent Sales</div>
        <?php if ($canViewSales): ?>
          <a class="btn-sm" style="text-decoration:none;" href="../sale/index.php<?php echo $role_query; ?>">View Sales</a>
        <?php endif; ?>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Order Ref</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $recSalesQuery = "
              SELECT s.*, c.name as customer_name 
              FROM sales s
              LEFT JOIN customers c ON s.customer_id = c.id
              WHERE s.business_id = ?
              ORDER BY s.created_at DESC 
              LIMIT 3
          ";
          $rsStmt = mysqli_prepare($conn, $recSalesQuery);
          mysqli_stmt_bind_param($rsStmt, 'i', $businessId);
          mysqli_stmt_execute($rsStmt);
          $rsResult = mysqli_stmt_get_result($rsStmt);
          while ($sRow = mysqli_fetch_assoc($rsResult)):
          ?>
            <tr>
              <td><span class="code-badge"><?php echo e($sRow['sale_number']); ?></span></td>
              <td class="td-name"><?php echo e($sRow['customer_name'] ?? 'Walk-In Customer'); ?></td>
              <td class="td-bold"><?php echo formatCurrency($sRow['total_amount'], $bizCur); ?></td>
              <td>
                <span class="status-pill <?php echo ($sRow['status'] === 'COMPLETED') ? 'pill-green' : 'pill-red'; ?>">
                  <?php echo e($sRow['status']); ?>
                </span>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- Recent Purchases -->
    <div class="card" id="recent-purchases">
      <div class="card-header">
        <div class="card-title">Recent Purchases</div>
        <?php if ($canViewPurchases): ?>
          <a class="btn-sm" style="text-decoration:none;" href="../purchase/index.php<?php echo $role_query; ?>">View Purchases</a>
        <?php endif; ?>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>PO Number</th>
            <th>Supplier</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $recPurchQuery = "
              SELECT p.*, s.name as supplier_name 
              FROM purchases p
              LEFT JOIN suppliers s ON p.supplier_id = s.id
              WHERE p.business_id = ?
              ORDER BY p.created_at DESC 
              LIMIT 3
          ";
          $rpStmt = mysqli_prepare($conn, $recPurchQuery);
          mysqli_stmt_bind_param($rpStmt, 'i', $businessId);
          mysqli_stmt_execute($rpStmt);
          $rpResult = mysqli_stmt_get_result($rpStmt);
          while ($pRow = mysqli_fetch_assoc($rpResult)):
          ?>
            <tr>
              <td><span class="code-badge"><?php echo e($pRow['purchase_number']); ?></span></td>
              <td class="td-name"><?php echo e($pRow['supplier_name']); ?></td>
              <td class="td-bold"><?php echo formatCurrency($pRow['total_amount'], $bizCur); ?></td>
              <td>
                <span class="status-pill <?php echo ($pRow['status'] === 'RECEIVED') ? 'pill-green' : 'pill-amber'; ?>">
                  <?php echo e($pRow['status']); ?>
                </span>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Leave requests & Reports summaries -->
  <div class="leave-reports-grid">
    <div class="card" id="leave-requests">
      <div class="card-header">
        <div class="card-title">Pending Leave Requests</div>
        <?php if ($canViewLeave): ?>
          <a class="btn-sm" style="text-decoration:none;" href="../leave/index.php<?php echo $role_query; ?>">Manage Leaves</a>
        <?php endif; ?>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Employee</th>
            <th>Dates</th>
            <th>Days</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $leavesQuery = "
              SELECT lr.*, u.first_name, u.last_name, lt.name as leave_type_name 
              FROM leave_requests lr
              JOIN business_memberships bm ON lr.membership_id = bm.id
              JOIN users u ON bm.user_id = u.id
              JOIN leave_types lt ON lr.leave_type_id = lt.id
              WHERE lr.business_id = ? AND lr.status = 'PENDING'
              ORDER BY lr.submitted_at DESC
              LIMIT 2
          ";
          $lStmt = mysqli_prepare($conn, $leavesQuery);
          mysqli_stmt_bind_param($lStmt, 'i', $businessId);
          mysqli_stmt_execute($lStmt);
          $lResult = mysqli_stmt_get_result($lStmt);
          if (mysqli_num_rows($lResult) === 0):
          ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text3); padding: 15px;">No pending leave requests.</td>
            </tr>
          <?php else: ?>
            <?php while ($lRow = mysqli_fetch_assoc($lResult)): ?>
              <tr>
                <td class="td-name"><?php echo e($lRow['first_name'] . ' ' . $lRow['last_name']); ?></td>
                <td><?php echo e($lRow['start_date'] . ' to ' . $lRow['end_date']); ?></td>
                <td><?php echo e((float)$lRow['requested_days']); ?></td>
                <td><span class="status-pill pill-amber">Pending</span></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card" id="reports">
      <div class="card-header"><div class="card-title">Business Reports Center</div></div>
      <div style="display:flex; flex-direction:column; gap:12px; padding: 10px 0;">
        <p style="font-size:12px; color:var(--text3);">Select an app card in the APPS menu grid (shortcut ESC) to access and generate ledger journals, PDF invoices, and P&L sheets.</p>
        <?php if ($canViewReports): ?>
          <a class="btn-primary" style="text-align:center; padding:10px; text-decoration:none;" href="../report/index.php<?php echo $role_query; ?>">Go to Reports module</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

<!-- ==========================================
     ROLE: EMPLOYEE DASHBOARD
     ========================================== -->
<?php elseif ($user_role === 'employee'): ?>
  <?php $empMembershipId = $_SESSION['membership_id'] ?? 0; ?>
  <?php if ($canViewSales): ?>
  <div class="stats-grid-8" style="margin-bottom: 24px;">
    <?php
    // Logged sales count
    $salesCountQ = "SELECT COUNT(*) as scount, SUM(total_amount) as stotal FROM sales WHERE business_id = ? AND cashier_membership_id = ?";
    $scStmt = mysqli_prepare($conn, $salesCountQ);
    mysqli_stmt_bind_param($scStmt, 'ii', $businessId, $empMembershipId);
    mysqli_stmt_execute($scStmt);
    $empSales = mysqli_fetch_assoc(mysqli_stmt_get_result($scStmt));
    ?>
    <div class="stat-card">
      <div class="stat-top"><span class="stat-trend trend-blue">My Recorded Sales</span></div>
      <div class="stat-val"><?php echo (int)$empSales['scount']; ?></div>
      <div class="stat-card-desc">Total sales logged by me</div>
    </div>
    <div class="stat-card">
      <div class="stat-top"><span class="stat-trend trend-up">My Sales Volume</span></div>
      <div class="stat-val"><?php echo formatCurrency($empSales['stotal'] ?? 0, $bizCur ?? 'RWF'); ?></div>
      <div class="stat-card-desc">Total revenue logged</div>
    </div>
  </div>
  <?php endif; ?>

  <div class="dashboard-split-grid">
    <?php if ($canCreateSales || $canCreatePurchases || $canSubmitLeave): ?>
    <div class="card">
      <div class="card-header"><div class="card-title">Quick Operations Tasks</div></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; padding: 12px 0;">
        <?php if ($canCreateSales): ?>
          <a class="btn-primary" style="text-decoration:none; text-align:center; padding:10px;" href="../sale/index.php<?php echo $role_query; ?>">Record a Customer Sale</a>
        <?php endif; ?>
        <?php if ($canCreatePurchases): ?>
          <a class="btn-primary" style="text-decoration:none; text-align:center; padding:10px; background:var(--green);" href="../purchase/index.php<?php echo $role_query; ?>">Log Purchase Lot Recpt</a>
        <?php endif; ?>
        <?php if ($canSubmitLeave): ?>
          <a class="btn-sm" style="text-decoration:none; text-align:center; padding:10px; background:var(--bg); border:1px solid var(--border); color:var(--text);" href="../leave/index.php<?php echo $role_query; ?>">Submit Leave Request</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($canViewAudit): ?>
    <div class="card">
      <div class="card-header"><div class="card-title">My Recent Activity Log</div></div>
      <table class="data-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Action</th>
            <th>Resource</th>
            <th>ID</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $myLogsQ = "
              SELECT * FROM audit_logs 
              WHERE business_id = ? AND actor_membership_id = ? 
              ORDER BY created_at DESC 
              LIMIT 3
          ";
          $mlStmt = mysqli_prepare($conn, $myLogsQ);
          mysqli_stmt_bind_param($mlStmt, 'ii', $businessId, $empMembershipId);
          mysqli_stmt_execute($mlStmt);
          $mlResult = mysqli_stmt_get_result($mlStmt);
          if (mysqli_num_rows($mlResult) === 0):
          ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text3); padding:15px;">No activity logged yet today.</td>
            </tr>
          <?php else: ?>
            <?php while ($log = mysqli_fetch_assoc($mlResult)): ?>
              <tr>
                <td><?php echo formatDate($log['created_at']); ?></td>
                <td><span class="code-badge"><?php echo e($log['action']); ?></span></td>
                <td><?php echo e($log['entity_type']); ?></td>
                <td><?php echo e($log['entity_id']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
