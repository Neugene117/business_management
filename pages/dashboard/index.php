<?php
$page_title = 'Dashboard';
$extra_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

// Render the real role, or the Super Admin's validated read-only preview role.
$user_role = getEffectiveUserRole();
$role_query = getRolePreviewQuery();
$businessId = $_SESSION['active_business_id'] ?? 0;
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
    <select id="dateRange" style="padding: 6px 12px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--card); color: var(--text); font-size:12px;">
      <option value="today">Today</option>
      <option value="week">This Week</option>
      <option value="month" selected>This Month</option>
      <option value="year">This Year</option>
    </select>
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
  <?php if ($pCount > 0): ?>
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
  $bizCurQuery = "SELECT currency_code FROM businesses WHERE id = ? LIMIT 1";
  $bcStmt = mysqli_prepare($conn, $bizCurQuery);
  mysqli_stmt_bind_param($bcStmt, 'i', $businessId);
  mysqli_stmt_execute($bcStmt);
  $bizCur = mysqli_fetch_assoc(mysqli_stmt_get_result($bcStmt))['currency_code'] ?? 'RWF';
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
    // Basic Net Profit estimation = Sales Revenue - Cost of Goods Sold - Expenses
    // For demo purposes: Net = Sales - (Sales * 0.6) - Expenses
    $estimatedCOGS = $totalSales * 0.65;
    $netProfit = $totalSales - $estimatedCOGS - $totalExpenses;
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
      <div class="stat-card-desc">Estimated net profit</div>
    </div>
    
    <div class="stat-card" id="stat-loss">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--red-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--red)"><path d="M23 18l-9.5-9.5-5 5L1 6"/></svg>
        </div>
        <span class="stat-trend trend-down">Loss</span>
      </div>
      <div class="stat-val"><?php echo formatCurrency($lossVal, $bizCur); ?></div>
      <div class="stat-card-desc">Estimated loss</div>
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
    // Check low stock products count
    $lowStockQuery = "
        SELECT COUNT(*) as low_count 
        FROM inventory_balances ib
        JOIN products p ON ib.product_id = p.id
        WHERE ib.business_id = ? AND ib.quantity_on_hand <= p.reorder_level
    ";
    $lsStmt = mysqli_prepare($conn, $lowStockQuery);
    mysqli_stmt_bind_param($lsStmt, 'i', $businessId);
    mysqli_stmt_execute($lsStmt);
    $lowCount = mysqli_fetch_assoc(mysqli_stmt_get_result($lsStmt))['low_count'] ?? 0;
    ?>
    <div class="stat-card" id="stat-low-stock">
      <div class="stat-top">
        <div class="stat-icon" style="background:var(--amber-bg)">
          <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <span class="stat-trend trend-warn">Low Stock Alerts</span>
      </div>
      <div class="stat-val"><?php echo (int)$lowCount; ?> Items</div>
      <div class="stat-card-desc">Reorder threshold hit</div>
    </div>
  </div>

  <div class="dashboard-split-grid">
    <!-- Visual Comparison Chart -->
    <div class="card" id="sales-trends">
      <div class="card-header">
        <div class="card-title">Sales &amp; Purchases Trends</div>
        <div style="display: flex; gap: 12px; font-size: 11px;">
          <span style="display: flex; align-items: center; gap: 4px;"><span style="width:8px; height:8px; background:var(--blue); border-radius:50%;"></span> Sales</span>
          <span style="display: flex; align-items: center; gap: 4px;"><span style="width:8px; height:8px; background:var(--orange); border-radius:50%;"></span> Purchases</span>
        </div>
      </div>
      
      <div style="display: flex; justify-content: space-between; font-size: 11.5px; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 16px;">
        <div>
          <div style="color: var(--text3);">Sales (Month)</div>
          <div style="font-size: 16px; font-weight:600; color: var(--blue);"><?php echo formatCurrency($totalSales, $bizCur); ?></div>
        </div>
        <div>
          <div style="color: var(--text3);">Purchases (Month)</div>
          <div style="font-size: 16px; font-weight:600; color: var(--orange);"><?php echo formatCurrency($totalPurch, $bizCur); ?></div>
        </div>
        <div>
          <div style="color: var(--text3);">Difference</div>
          <div style="font-size: 16px; font-weight:600; color: var(--green);">+<?php echo formatCurrency(max(0, $totalSales - $totalPurch), $bizCur); ?></div>
        </div>
      </div>

      <div class="chart-container">
        <!-- SVG bar chart visualization -->
        <svg viewBox="0 0 600 240" class="bm-svg-chart">
          <line x1="50" y1="30" x2="570" y2="30" stroke="var(--border)" stroke-dasharray="4" />
          <line x1="50" y1="80" x2="570" y2="80" stroke="var(--border)" stroke-dasharray="4" />
          <line x1="50" y1="130" x2="570" y2="130" stroke="var(--border)" stroke-dasharray="4" />
          <line x1="50" y1="180" x2="570" y2="180" stroke="var(--border)" stroke-dasharray="4" />
          <line x1="50" y1="210" x2="570" y2="210" stroke="var(--border)" />
          
          <rect x="90" y="120" width="14" height="90" rx="2" fill="var(--blue)" />
          <rect x="106" y="150" width="14" height="60" rx="2" fill="var(--orange)" />
          <rect x="170" y="90" width="14" height="120" rx="2" fill="var(--blue)" />
          <rect x="186" y="130" width="14" height="80" rx="2" fill="var(--orange)" />
          <rect x="250" y="70" width="14" height="140" rx="2" fill="var(--blue)" />
          <rect x="266" y="120" width="14" height="90" rx="2" fill="var(--orange)" />
          <rect x="330" y="95" width="14" height="115" rx="2" fill="var(--blue)" />
          <rect x="346" y="140" width="14" height="70" rx="2" fill="var(--orange)" />
          <rect x="410" y="60" width="14" height="150" rx="2" fill="var(--blue)" />
          <rect x="426" y="100" width="14" height="110" rx="2" fill="var(--orange)" />
          <rect x="490" y="45" width="14" height="165" rx="2" fill="var(--blue)" />
          <rect x="506" y="90" width="14" height="120" rx="2" fill="var(--orange)" />

          <text x="105" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Jan</text>
          <text x="185" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Feb</text>
          <text x="265" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Mar</text>
          <text x="345" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Apr</text>
          <text x="425" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">May</text>
          <text x="505" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Jun</text>

          <text x="40" y="34" fill="var(--text3)" font-size="10" text-anchor="end">30k</text>
          <text x="40" y="84" fill="var(--text3)" font-size="10" text-anchor="end">20k</text>
          <text x="40" y="134" fill="var(--text3)" font-size="10" text-anchor="end">10k</text>
          <text x="40" y="184" fill="var(--text3)" font-size="10" text-anchor="end">5k</text>
          <text x="40" y="214" fill="var(--text3)" font-size="10" text-anchor="end">0</text>
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
          <span class="pnl-val" style="color: var(--text);"><?php echo formatCurrency($estimatedCOGS, $bizCur); ?></span>
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
        <a class="btn-sm" style="text-decoration:none;" href="../sale/index.php<?php echo $role_query; ?>">View Sales</a>
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
        <a class="btn-sm" style="text-decoration:none;" href="../purchase/index.php<?php echo $role_query; ?>">View Purchases</a>
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
        <a class="btn-sm" style="text-decoration:none;" href="../leave/index.php<?php echo $role_query; ?>">Manage Leaves</a>
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
        <a class="btn-primary" style="text-align:center; padding:10px; text-decoration:none;" href="../report/index.php<?php echo $role_query; ?>">Go to Reports module</a>
      </div>
    </div>
  </div>

<!-- ==========================================
     ROLE: EMPLOYEE DASHBOARD
     ========================================== -->
<?php elseif ($user_role === 'employee'): ?>
  
  <div class="stats-grid-8" style="margin-bottom: 24px;">
    <?php
    $empMembershipId = $_SESSION['membership_id'] ?? 0;
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

  <div class="dashboard-split-grid">
    <div class="card">
      <div class="card-header"><div class="card-title">Quick Operations Tasks</div></div>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; padding: 12px 0;">
        <a class="btn-primary" style="text-decoration:none; text-align:center; padding:10px;" href="../sale/index.php<?php echo $role_query; ?>">Record a Customer Sale</a>
        <a class="btn-primary" style="text-decoration:none; text-align:center; padding:10px; background:var(--green);" href="../purchase/index.php<?php echo $role_query; ?>">Log Purchase Lot Recpt</a>
        <a class="btn-sm" style="text-decoration:none; text-align:center; padding:10px; background:var(--bg); border:1px solid var(--border); color:var(--text);" href="../leave/index.php<?php echo $role_query; ?>">Submit Leave Request</a>
      </div>
    </div>
    
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
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
