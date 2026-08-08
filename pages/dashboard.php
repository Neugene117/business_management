<?php
require_once 'login/auth.php';

// Detect user role from session, fallback to owner.
$user_role = 'owner';
if (isset($_SESSION['roles'])) {
    if (in_array('super_admin', $_SESSION['roles'])) {
        $user_role = 'super_admin';
    } elseif (in_array('employee', $_SESSION['roles'])) {
        $user_role = 'employee';
    } elseif (in_array('admin', $_SESSION['roles'])) {
        $user_role = 'owner'; // Map database admin role to Business Owner
    }
}

// Allow switching via URL parameter for testing/review purposes
if (isset($_GET['role'])) {
    $role_override = strtolower(trim($_GET['role']));
    if (in_array($role_override, ['super_admin', 'owner', 'employee'])) {
        $user_role = $role_override;
    }
}

$user_full_name = htmlspecialchars(($_SESSION['first_name'] ?? 'User') . ' ' . ($_SESSION['last_name'] ?? ''));
$page_title = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Business Management — Dashboard</title>
<meta name="description" content="Business Management consolidated admin dashboard.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../src/css/dashboard.css">
<link rel="stylesheet" href="../src/css/sidebar.css">
<link rel="stylesheet" href="../src/css/navbar.css">
<script>
  (function() {
    var savedTheme = localStorage.getItem('theme');
    var currentTheme = savedTheme || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', currentTheme);
  })();
</script>
</head>
<body>

<?php include './include/sidebar.php'; ?>

<!-- ========== MAIN ========== -->
<div class="main">

  <?php include './include/navbar.php'; ?>

  <!-- ========== CONTENT ========== -->
  <div class="content" id="dashboardContent">

    <!-- Welcome Header & Controls -->
    <div class="page-header" style="flex-wrap: wrap; gap: 14px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
      <div>
        <h1 class="page-title" style="font-size: 20px; font-weight: 600;">
          Welcome back, <?php echo $user_full_name; ?>
        </h1>
        <div class="page-sub" style="font-size: 13px; color: var(--text3); margin-top: 4px;">
          Here is a summary of activities as of <?php echo date('F j, Y'); ?>.
        </div>
      </div>
      
      <!-- Date Filter & Quick Switcher Controls -->
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <!-- Role switcher for testing -->
        <div class="role-switcher">
          <span class="role-switcher-title">View As:</span>
          <a href="?role=super_admin" class="role-btn<?php echo ($user_role === 'super_admin') ? ' active' : ''; ?>">Super Admin</a>
          <a href="?role=owner" class="role-btn<?php echo ($user_role === 'owner') ? ' active' : ''; ?>">Owner</a>
          <a href="?role=employee" class="role-btn<?php echo ($user_role === 'employee') ? ' active' : ''; ?>">Employee</a>
        </div>
        
        <?php if ($user_role !== 'super_admin'): ?>
        <!-- Date filter bar -->
        <div class="filter-bar">
          <svg viewBox="0 0 24 24" style="width:14px; height:14px; stroke:var(--text2); fill:none; stroke-width:2; vertical-align:middle; margin-right:4px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <select class="filter-select" id="dateFilter" onchange="alert('Date filter applied: ' + this.value);">
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month" selected>This Month</option>
            <option value="year">This Year</option>
            <option value="custom">Custom Date Range</option>
          </select>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ==========================================
         ROLE: SUPER ADMIN DASHBOARD
         ========================================== -->
    <?php if ($user_role === 'super_admin'): ?>
      
      <!-- Registration notification banner -->
      <div class="notice-banner">
        <div class="notice-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span class="notice-text"><strong>Action Required:</strong> 5 new Business Owners are waiting for system registration approval.</span>
        </div>
        <button class="btn-sm btn-primary" onclick="alert('Opening registrations review manager.');">Review Applications</button>
      </div>

      <!-- Super Admin stats grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--blue-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--blue)"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span class="stat-trend trend-blue">Platform</span>
          </div>
          <div class="stat-val">15</div>
          <div class="stat-label">Total Businesses</div>
        </div>

        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--amber-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/></svg>
            </div>
            <span class="stat-trend trend-warn">Review</span>
          </div>
          <div class="stat-val">5</div>
          <div class="stat-label">Pending Approvals</div>
        </div>

        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--green-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--green)"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span class="stat-trend trend-up">Active</span>
          </div>
          <div class="stat-val">10</div>
          <div class="stat-label">Approved Businesses</div>
        </div>

        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:#FBF0EB">
              <svg viewBox="0 0 24 24" style="stroke:var(--orange)"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
            </div>
            <span class="stat-trend trend-warn">Users</span>
          </div>
          <div class="stat-val">45</div>
          <div class="stat-label">Total platform Users</div>
        </div>
      </div>

      <!-- Pending registrations table -->
      <div class="card" style="margin-top: 20px;">
        <div class="card-header">
          <div class="card-title">Pending Business Registrations</div>
          <div class="card-actions">
            <button class="btn-sm" onclick="alert('Viewing archive registrations.');">View History</button>
          </div>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Business Name</th>
              <th>Owner</th>
              <th>Phone Number</th>
              <th>Registration Date</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="td-name">Alpha Mining Co</td>
              <td>Darius Bimenyimana</td>
              <td>+250 788 123 456</td>
              <td>Aug 07, 2026</td>
              <td><span class="status-pill pill-amber">Pending</span></td>
              <td style="text-align: right; display: flex; justify-content: flex-end; gap: 6px;">
                <button class="btn-action approve" onclick="alert('Alpha Mining Co approved.');">Approve</button>
                <button class="btn-action reject" onclick="alert('Alpha Mining Co rejected.');">Reject</button>
              </td>
            </tr>
            <tr>
              <td class="td-name">Rubavu Trade Ltd</td>
              <td>Paulin Murego</td>
              <td>+250 788 654 321</td>
              <td>Aug 06, 2026</td>
              <td><span class="status-pill pill-amber">Pending</span></td>
              <td style="text-align: right; display: flex; justify-content: flex-end; gap: 6px;">
                <button class="btn-action approve" onclick="alert('Rubavu Trade Ltd approved.');">Approve</button>
                <button class="btn-action reject" onclick="alert('Rubavu Trade Ltd rejected.');">Reject</button>
              </td>
            </tr>
            <tr>
              <td class="td-name">Kivu Logistics</td>
              <td>Richard Akayezu</td>
              <td>+250 788 987 654</td>
              <td>Aug 05, 2026</td>
              <td><span class="status-pill pill-amber">Pending</span></td>
              <td style="text-align: right; display: flex; justify-content: flex-end; gap: 6px;">
                <button class="btn-action approve" onclick="alert('Kivu Logistics approved.');">Approve</button>
                <button class="btn-action reject" onclick="alert('Kivu Logistics rejected.');">Reject</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

    <!-- ==========================================
         ROLE: BUSINESS OWNER DASHBOARD
         ========================================== -->
    <?php elseif ($user_role === 'owner'): ?>
      
      <!-- 8 summary cards -->
      <div class="stats-grid-8">
        <!-- Sales -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--blue-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--blue)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-trend trend-blue">Sales</span>
          </div>
          <div class="stat-val">$25,450</div>
          <div class="stat-card-desc">This month's sales</div>
        </div>
        <!-- Purchases -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:#FBF0EB">
              <svg viewBox="0 0 24 24" style="stroke:var(--orange)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-trend trend-warn">Purchases</span>
          </div>
          <div class="stat-val">$12,300</div>
          <div class="stat-card-desc">This month's purchases</div>
        </div>
        <!-- Expenses -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--red-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--red)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <span class="stat-trend trend-down">Expenses</span>
          </div>
          <div class="stat-val">$4,250</div>
          <div class="stat-card-desc">This month's expenses</div>
        </div>
        <!-- Revenue -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--green-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--green)"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
            </div>
            <span class="stat-trend trend-up">Revenue</span>
          </div>
          <div class="stat-val">$38,900</div>
          <div class="stat-card-desc">Total collected revenue</div>
        </div>
        <!-- Profit -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--green-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--green)"><path d="M23 6l-9.5 9.5-5-5L1 18"/></svg>
            </div>
            <span class="stat-trend trend-up">Profit</span>
          </div>
          <div class="stat-val">$8,900</div>
          <div class="stat-card-desc">Current estimated profit</div>
        </div>
        <!-- Loss -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--red-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--red)"><path d="M23 18l-9.5-9.5-5 5L1 6"/></svg>
            </div>
            <span class="stat-trend trend-down">Loss</span>
          </div>
          <div class="stat-val">$0</div>
          <div class="stat-card-desc">Current estimated loss</div>
        </div>
        <!-- Current Stock -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--blue-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--blue)"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
            </div>
            <span class="stat-trend trend-blue">Stock</span>
          </div>
          <div class="stat-val">1,592 kg</div>
          <div class="stat-card-desc">Total mineral stock</div>
        </div>
        <!-- Low Stock Items -->
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--amber-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span class="stat-trend trend-warn">Alert</span>
          </div>
          <div class="stat-val">2 items</div>
          <div class="stat-card-desc">Tantalum, Tin under min limit</div>
        </div>
      </div>

      <!-- Sales & Purchases Visual Chart and Profit & Loss overview -->
      <div class="dashboard-split-grid">
        <!-- Visual Comparison Chart -->
        <div class="card">
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
              <div style="font-size: 16px; font-weight:600; color: var(--blue);">$25,450</div>
            </div>
            <div>
              <div style="color: var(--text3);">Purchases (Month)</div>
              <div style="font-size: 16px; font-weight:600; color: var(--orange);">$12,300</div>
            </div>
            <div>
              <div style="color: var(--text3);">Active Balance</div>
              <div style="font-size: 16px; font-weight:600; color: var(--green);">+$13,150</div>
            </div>
          </div>

          <div class="chart-container">
            <svg viewBox="0 0 600 240" class="bm-svg-chart">
              <!-- Grid lines -->
              <line x1="50" y1="30" x2="570" y2="30" stroke="var(--border)" stroke-dasharray="4" />
              <line x1="50" y1="80" x2="570" y2="80" stroke="var(--border)" stroke-dasharray="4" />
              <line x1="50" y1="130" x2="570" y2="130" stroke="var(--border)" stroke-dasharray="4" />
              <line x1="50" y1="180" x2="570" y2="180" stroke="var(--border)" stroke-dasharray="4" />
              <line x1="50" y1="210" x2="570" y2="210" stroke="var(--border)" />
              
              <!-- Jan -->
              <rect x="90" y="120" width="14" height="90" rx="2" fill="var(--blue)" />
              <rect x="106" y="150" width="14" height="60" rx="2" fill="var(--orange)" />
              <!-- Feb -->
              <rect x="170" y="90" width="14" height="120" rx="2" fill="var(--blue)" />
              <rect x="186" y="130" width="14" height="80" rx="2" fill="var(--orange)" />
              <!-- Mar -->
              <rect x="250" y="70" width="14" height="140" rx="2" fill="var(--blue)" />
              <rect x="266" y="120" width="14" height="90" rx="2" fill="var(--orange)" />
              <!-- Apr -->
              <rect x="330" y="95" width="14" height="115" rx="2" fill="var(--blue)" />
              <rect x="346" y="140" width="14" height="70" rx="2" fill="var(--orange)" />
              <!-- May -->
              <rect x="410" y="60" width="14" height="150" rx="2" fill="var(--blue)" />
              <rect x="426" y="100" width="14" height="110" rx="2" fill="var(--orange)" />
              <!-- Jun (Selected month values) -->
              <rect x="490" y="45" width="14" height="165" rx="2" fill="var(--blue)" />
              <rect x="506" y="90" width="14" height="120" rx="2" fill="var(--orange)" />

              <!-- Labels -->
              <text x="105" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Jan</text>
              <text x="185" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Feb</text>
              <text x="265" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Mar</text>
              <text x="345" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Apr</text>
              <text x="425" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">May</text>
              <text x="505" y="230" fill="var(--text3)" font-size="10" text-anchor="middle">Jun</text>

              <!-- Y Axis Labels -->
              <text x="40" y="34" fill="var(--text3)" font-size="10" text-anchor="end">$30k</text>
              <text x="40" y="84" fill="var(--text3)" font-size="10" text-anchor="end">$20k</text>
              <text x="40" y="134" fill="var(--text3)" font-size="10" text-anchor="end">$10k</text>
              <text x="40" y="184" fill="var(--text3)" font-size="10" text-anchor="end">$5k</text>
              <text x="40" y="214" fill="var(--text3)" font-size="10" text-anchor="end">0</text>
            </svg>
          </div>
        </div>

        <!-- Profit & Loss Summary Card -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Profit &amp; Loss Overview</div>
          </div>
          <div class="pnl-list">
            <div class="pnl-row">
              <span class="pnl-label">
                <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                Total Revenue
              </span>
              <span class="pnl-val" style="color: var(--green);">$38,900</span>
            </div>
            <div class="pnl-row">
              <span class="pnl-label">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                Total Cost
              </span>
              <span class="pnl-val" style="color: var(--text);">$25,750</span>
            </div>
            <div class="pnl-row">
              <span class="pnl-label">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Total Expenses
              </span>
              <span class="pnl-val" style="color: var(--amber);">$4,250</span>
            </div>
            <div class="pnl-row">
              <span class="pnl-label">
                <svg viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Stock Loss
              </span>
              <span class="pnl-val" style="color: var(--red);">$0</span>
            </div>
            <div style="border-top: 1px solid var(--border); margin: 8px 0; padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <span style="font-size: 11px; color: var(--text3); text-transform: uppercase; font-weight: 600;">Net Balance</span>
                <div style="font-size: 20px; font-weight: 700; color: var(--green);">+$8,900</div>
              </div>
              <span class="status-pill pill-green">Profitable</span>
            </div>
          </div>
          
          <div class="visual-bar-container">
            <div class="visual-bar-info">
              <span>Expenses ratio to Revenue</span>
              <span>11%</span>
            </div>
            <div class="visual-bar-track">
              <div class="visual-bar-fill" style="width: 11%; background: var(--amber);"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stock Overview Section -->
      <div class="section-heading">
        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        Stock Overview
      </div>
      <div class="stock-grid">
        <div class="stock-card">
          <div class="stock-card-icon" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
          </div>
          <div class="stock-card-info">
            <span class="stock-card-val">1,592.2 kg</span>
            <span class="stock-card-label">Total Stock</span>
          </div>
        </div>
        <div class="stock-card">
          <div class="stock-card-icon" style="background: var(--green-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--green);"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          </div>
          <div class="stock-card-info">
            <span class="stock-card-val">1,800.0 kg</span>
            <span class="stock-card-label">Stock In</span>
          </div>
        </div>
        <div class="stock-card">
          <div class="stock-card-icon" style="background: var(--red-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--red);"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
          </div>
          <div class="stock-card-info">
            <span class="stock-card-val">207.8 kg</span>
            <span class="stock-card-label">Stock Out</span>
          </div>
        </div>
        <div class="stock-card">
          <div class="stock-card-icon" style="background: var(--amber-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          </div>
          <div class="stock-card-info">
            <span class="stock-card-val">2 Items</span>
            <span class="stock-card-label">Low Stock items</span>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div class="card-title">Stock Processing Allocation</div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
          <div>
            <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom: 4px;">
              <span>Tin (Sn)</span>
              <strong>1,326.5 kg (83%)</strong>
            </div>
            <div class="visual-bar-track"><div class="visual-bar-fill" style="width:83%; background:var(--orange);"></div></div>
          </div>
          <div>
            <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom: 4px;">
              <span>Tantalum (Ta)</span>
              <strong>265.7 kg (17%)</strong>
            </div>
            <div class="visual-bar-track"><div class="visual-bar-fill" style="width:17%; background:var(--blue);"></div></div>
          </div>
        </div>
      </div>

      <!-- Recent Sales & Recent Purchases Side-by-Side -->
      <div class="recent-activities-grid">
        <!-- Recent Sales -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Sales</div>
            <button class="btn-sm" onclick="alert('Viewing all sales records.');">View All Sales</button>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="code-badge">S-1042</span></td>
                <td class="td-name">Tin (Sn)</td>
                <td>250 kg</td>
                <td class="td-bold">$5,000</td>
                <td><span class="status-pill pill-green">Delivered</span></td>
              </tr>
              <tr>
                <td><span class="code-badge">S-1041</span></td>
                <td class="td-name">Tantalum (Ta)</td>
                <td>80 kg</td>
                <td class="td-bold">$6,400</td>
                <td><span class="status-pill pill-green">Delivered</span></td>
              </tr>
              <tr>
                <td><span class="code-badge">S-1040</span></td>
                <td class="td-name">Tin (Sn)</td>
                <td>120 kg</td>
                <td class="td-bold">$2,400</td>
                <td><span class="status-pill pill-amber">Shipped</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Recent Purchases -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Purchases</div>
            <button class="btn-sm" onclick="alert('Viewing all purchases records.');">View All Purchases</button>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Supplier</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><span class="code-badge">P-382</span></td>
                <td class="td-name">Tin-Lot 03</td>
                <td>Paulin Murego</td>
                <td class="td-bold">$65,053</td>
                <td><span class="status-pill pill-red">Unpaid</span></td>
              </tr>
              <tr>
                <td><span class="code-badge">P-381</span></td>
                <td class="td-name">Tin-Lot 03</td>
                <td>Darius B.</td>
                <td class="td-bold">$28,200</td>
                <td><span class="status-pill pill-red">Unpaid</span></td>
              </tr>
              <tr>
                <td><span class="code-badge">P-380</span></td>
                <td class="td-name">Ta-Lot 04</td>
                <td>Eprocomi</td>
                <td class="td-bold">$64,670</td>
                <td><span class="status-pill pill-amber">Partial</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Expense Overview & Employee Overview -->
      <div class="recent-activities-grid">
        <!-- Expense Overview -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Expense Summary</div>
            <span style="font-size: 11px; color: var(--text3);">Month total: <strong>$4,250</strong></span>
          </div>
          <div class="category-progress-list">
            <div class="category-progress-item">
              <div class="category-progress-label">
                <span>Salaries</span>
                <strong>$2,000 (47%)</strong>
              </div>
              <div class="category-progress-track"><div class="category-progress-fill" style="width: 47%; background: var(--blue);"></div></div>
            </div>
            <div class="category-progress-item">
              <div class="category-progress-label">
                <span>Rent</span>
                <strong>$1,500 (35%)</strong>
              </div>
              <div class="category-progress-track"><div class="category-progress-fill" style="width: 35%; background: var(--amber);"></div></div>
            </div>
            <div class="category-progress-item">
              <div class="category-progress-label">
                <span>Transport</span>
                <strong>$450 (11%)</strong>
              </div>
              <div class="category-progress-track"><div class="category-progress-fill" style="width: 11%; background: var(--orange);"></div></div>
            </div>
            <div class="category-progress-item">
              <div class="category-progress-label">
                <span>Utilities</span>
                <strong>$200 (5%)</strong>
              </div>
              <div class="category-progress-track"><div class="category-progress-fill" style="width: 5%; background: var(--green);"></div></div>
            </div>
          </div>
        </div>

        <!-- Employee Overview -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Employees Overview</div>
            <button class="btn-sm" onclick="alert('Redirecting to employee accounts management page.');">Manage Employees</button>
          </div>
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px;">
            <div style="border:1px solid var(--border); padding: 10px; border-radius: 8px; text-align: center;">
              <div style="font-size: 20px; font-weight: 700; color: var(--blue);">8</div>
              <div style="font-size:11px; color: var(--text3);">Total Employees</div>
            </div>
            <div style="border:1px solid var(--border); padding: 10px; border-radius: 8px; text-align: center;">
              <div style="font-size: 20px; font-weight: 700; color: var(--green);">7</div>
              <div style="font-size:11px; color: var(--text3);">Active Staff</div>
            </div>
          </div>
          <div class="pnl-list">
            <div class="pnl-row">
              <span class="pnl-label">Employees currently on leave</span>
              <span class="pnl-val">1 employee</span>
            </div>
            <div class="pnl-row">
              <span class="pnl-label">Pending leave requests</span>
              <span class="pnl-val" style="color: var(--amber); font-weight: 600;">1 request</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Leave Requests & Reports -->
      <div class="leave-reports-grid">
        <!-- Leave Requests table -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Pending Leave Requests</div>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Dates</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="td-name">John Doe</td>
                <td>Sick Leave</td>
                <td>Aug 10 - Aug 12</td>
                <td><span class="status-pill pill-amber">Pending</span></td>
                <td style="text-align: right; display: flex; gap: 4px; justify-content: flex-end;">
                  <button class="btn-action approve" onclick="alert('Approved John Doe leave request.');">Approve</button>
                  <button class="btn-action reject" onclick="alert('Rejected John Doe leave request.');">Reject</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Reports Quick Access -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Business Reports</div>
          </div>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--table-border);">
              <span style="font-weight: 500;">Daily Ledger Summary</span>
              <button class="btn-sm" onclick="alert('Downloading daily ledger report PDF.');">View Report</button>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--table-border);">
              <span style="font-weight: 500;">Weekly Sales Summary</span>
              <button class="btn-sm" onclick="alert('Downloading weekly sales report PDF.');">View Report</button>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0;">
              <span style="font-weight: 500;">Monthly Profit &amp; Loss</span>
              <button class="btn-sm" onclick="alert('Downloading monthly profit and loss PDF.');">View Report</button>
            </div>
            
            <button class="btn-primary" style="width: 100%; border: none; padding: 8px; border-radius: var(--radius); font-weight: 500; font-size:12px; cursor: pointer; text-align: center; margin-top: 10px;" onclick="alert('Preparing request to generate a new report. UI Only.');">
              Generate Custom Report
            </button>
          </div>
        </div>
      </div>

    <!-- ==========================================
         ROLE: EMPLOYEE DASHBOARD
         ========================================== -->
    <?php elseif ($user_role === 'employee'): ?>
      
      <!-- Employee stats grid -->
      <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--blue-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--blue)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-trend trend-blue">Sales</span>
          </div>
          <div class="stat-val">12</div>
          <div class="stat-label">Sales Recorded by Me</div>
        </div>

        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:#FBF0EB">
              <svg viewBox="0 0 24 24" style="stroke:var(--orange)"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <span class="stat-trend trend-warn">Purchases</span>
          </div>
          <div class="stat-val">8</div>
          <div class="stat-label">Purchases Recorded by Me</div>
        </div>

        <div class="stat-card">
          <div class="stat-top">
            <div class="stat-icon" style="background:var(--amber-bg)">
              <svg viewBox="0 0 24 24" style="stroke:var(--amber)"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <span class="stat-trend trend-warn">Leave</span>
          </div>
          <div class="stat-val">Pending</div>
          <div class="stat-label">My Leave Request Status</div>
        </div>
      </div>

      <!-- Quick Tasks & Tasks List -->
      <div class="recent-activities-grid">
        <!-- Quick Tasks -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Quick Actions</div>
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <button class="btn-sm" style="padding: 14px; text-align: center; border: 1px solid var(--border); border-radius: 8px;" onclick="alert('Add sale task entry.');">Record a Sale</button>
            <button class="btn-sm" style="padding: 14px; text-align: center; border: 1px solid var(--border); border-radius: 8px;" onclick="alert('Add purchase transaction entry.');">Record a Purchase</button>
            <button class="btn-sm" style="padding: 14px; text-align: center; border: 1px solid var(--border); border-radius: 8px;" onclick="alert('Log minor petty cash expense.');">Log Daily Expense</button>
            <button class="btn-sm" style="padding: 14px; text-align: center; border: 1px solid var(--border); border-radius: 8px;" onclick="alert('Submit leave request form.');">Request Leave</button>
          </div>
        </div>

        <!-- Current Stock Info -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">Available Warehouse Stock</div>
          </div>
          <div class="pnl-list">
            <div class="pnl-row">
              <span class="pnl-label">Tin (Sn) available</span>
              <span class="pnl-val">1,326.5 kg</span>
            </div>
            <div class="pnl-row">
              <span class="pnl-label">Tantalum (Ta) available</span>
              <span class="pnl-val">265.7 kg</span>
            </div>
            <div class="pnl-row" style="border-top: 1px solid var(--border); margin-top: 4px; padding-top: 10px;">
              <span class="pnl-label"><strong>Total combined</strong></span>
              <span class="pnl-val"><strong>1,592.2 kg</strong></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent My Submissions -->
      <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div class="card-title">My Recent Activity Logs</div>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Reference ID</th>
              <th>Product / Detail</th>
              <th>Amount / Value</th>
              <th>Date Entered</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="status-pill pill-blue">Sale Log</span></td>
              <td><span class="code-badge">S-1042</span></td>
              <td class="td-name">Tin (Sn) - 250 kg</td>
              <td class="td-bold">$5,000</td>
              <td>Today, 10:45 AM</td>
            </tr>
            <tr>
              <td><span class="status-pill pill-green">Purchase Log</span></td>
              <td><span class="code-badge">P-382</span></td>
              <td class="td-name">Tin-Lot 03 - Paulin Murego</td>
              <td class="td-bold">$65,053</td>
              <td>Yesterday, 4:12 PM</td>
            </tr>
            <tr>
              <td><span class="status-pill pill-blue">Sale Log</span></td>
              <td><span class="code-badge">S-1041</span></td>
              <td class="td-name">Tantalum (Ta) - 80 kg</td>
              <td class="td-bold">$6,400</td>
              <td>Aug 06, 2026</td>
            </tr>
          </tbody>
        </table>
      </div>

    <?php endif; ?>

    <div class="bottom-spacer"></div>
  </div>
</div>

<script src="../src/js/navbar.js"></script>
<script src="../src/js/sidebar.js"></script>
<script src="../src/js/dashboard.js"></script>
</body>
</html>
