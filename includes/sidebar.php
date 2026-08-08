<?php
// Detect user role from session, fallback to owner.
$user_role = 'owner';
if (isset($_SESSION['platform_context']) && $_SESSION['platform_context'] === 'SUPER_ADMIN') {
    $user_role = 'super_admin';
} elseif (isset($_SESSION['roles'])) {
    if (in_array('employee', $_SESSION['roles'])) {
        $user_role = 'employee';
    }
}

// Allow switching via URL parameter for testing/review comfort
if (isset($_GET['role'])) {
    $role_override = strtolower(trim($_GET['role']));
    if (in_array($role_override, ['super_admin', 'owner', 'employee'])) {
        $user_role = $role_override;
    }
}

$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';
$dash_link = $root_prefix . 'pages/dashboard/index.php' . $role_query;
$settings_link = $root_prefix . 'pages/settings/index.php' . $role_query;
?>
<!-- ========== FLOATING APPS PANEL ========== -->
<div class="app-grid-overlay" id="appGridOverlay"></div>
<div class="app-grid-panel" id="appGridPanel">
  <div class="app-grid-header">
    <span class="app-grid-title">APPS</span>
    <span class="app-grid-esc">ESC</span>
  </div>
  
  <div class="app-grid-body">

    <!-- ==========================================
         APPS FOR: SUPER ADMIN
         ========================================== -->
    <?php if ($user_role === 'super_admin'): ?>
      
      <div class="app-grid-section-title">Core Platform</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Dashboard'); ?>" href="<?php echo $dash_link; ?>" id="nav-main">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Dashboard</div>
            <div class="app-grid-desc">System main overview</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Business Approvals'); ?>" href="<?php echo $root_prefix; ?>pages/business_approval/index.php<?php echo $role_query; ?>" id="nav-businesses">
          <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
            <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Businesses</div>
            <div class="app-grid-desc">Registered companies list</div>
          </div>
        </a>
      </div>

      <div class="app-grid-section-title">Access &amp; Security</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Platform Users'); ?>" href="<?php echo $root_prefix; ?>pages/employee/index.php<?php echo $role_query; ?>" id="nav-users">
          <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--green);"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Users</div>
            <div class="app-grid-desc">Platform login credentials</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Roles & Permissions'); ?>" href="<?php echo $root_prefix; ?>pages/role/index.php<?php echo $role_query; ?>" id="nav-permissions">
          <div class="app-grid-icon-wrap" style="background: var(--red-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--red);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Roles &amp; Permissions</div>
            <div class="app-grid-desc">Access authorization setup</div>
          </div>
        </a>
        <a class="app-grid-item" href="<?php echo $settings_link; ?>" id="nav-settings">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Settings</div>
            <div class="app-grid-desc">System configuration</div>
          </div>
        </a>
      </div>

    <!-- ==========================================
         APPS FOR: BUSINESS OWNER
         ========================================== -->
    <?php elseif ($user_role === 'owner'): ?>
      
      <div class="app-grid-section-title">Core &amp; Overview</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Dashboard'); ?>" href="<?php echo $dash_link; ?>" id="nav-main">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Dashboard</div>
            <div class="app-grid-desc">Overview &amp; statistics</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Profit & Loss'); ?>" href="<?php echo $root_prefix; ?>pages/report/index.php<?php echo $role_query; ?>#pnl-overview" id="nav-totals">
          <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--green);"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Profit &amp; Loss</div>
            <div class="app-grid-desc">Net profit margin check</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Reports'); ?>" href="<?php echo $root_prefix; ?>pages/report/index.php<?php echo $role_query; ?>" id="nav-reports">
          <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Reports</div>
            <div class="app-grid-desc">Generated spreadsheets</div>
          </div>
        </a>
      </div>

      <div class="app-grid-section-title">Operations &amp; Finance</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Sales'); ?>" href="<?php echo $root_prefix; ?>pages/sale/index.php<?php echo $role_query; ?>" id="nav-sales">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Sales</div>
            <div class="app-grid-desc">Track customer orders</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Purchases'); ?>" href="<?php echo $root_prefix; ?>pages/purchase/index.php<?php echo $role_query; ?>" id="nav-purchases">
          <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
            <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v10a2 2 0 01-2 2h-4M12 7v10M9 12h6"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Purchases</div>
            <div class="app-grid-desc">Track supplier lots</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Expenses'); ?>" href="<?php echo $root_prefix; ?>pages/expense/index.php<?php echo $role_query; ?>" id="nav-expenses">
          <div class="app-grid-icon-wrap" style="background: var(--red-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--red);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Expenses</div>
            <div class="app-grid-desc">Administrative spending</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Inventory'); ?>" href="<?php echo $root_prefix; ?>pages/inventory/index.php<?php echo $role_query; ?>" id="nav-stock">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Stock Management</div>
            <div class="app-grid-desc">Available warehouse stock</div>
          </div>
        </a>
      </div>

      <div class="app-grid-section-title">Relationships &amp; Items</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Products'); ?>" href="<?php echo $root_prefix; ?>pages/product/index.php<?php echo $role_query; ?>" id="nav-products">
          <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--green);"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Products</div>
            <div class="app-grid-desc">Catalog item parameters</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Suppliers'); ?>" href="<?php echo $root_prefix; ?>pages/supplier/index.php<?php echo $role_query; ?>" id="nav-suppliers">
          <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Suppliers</div>
            <div class="app-grid-desc">Payables directory</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Customers'); ?>" href="<?php echo $root_prefix; ?>pages/customer/index.php<?php echo $role_query; ?>" id="nav-customers">
          <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
            <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Customers</div>
            <div class="app-grid-desc">Buyer records list</div>
          </div>
        </a>
      </div>

      <div class="app-grid-section-title">Team &amp; Security</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Employees'); ?>" href="<?php echo $root_prefix; ?>pages/employee/index.php<?php echo $role_query; ?>" id="nav-employees">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Employees</div>
            <div class="app-grid-desc">Staff rosters directory</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Leave Management'); ?>" href="<?php echo $root_prefix; ?>pages/leave/index.php<?php echo $role_query; ?>" id="nav-leave">
          <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Leave Management</div>
            <div class="app-grid-desc">Time off logs &amp; approvals</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Platform Users'); ?>" href="<?php echo $root_prefix; ?>pages/employee/index.php<?php echo $role_query; ?>" id="nav-users">
          <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--green);"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Users</div>
            <div class="app-grid-desc">Registered access logins</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Roles & Permissions'); ?>" href="<?php echo $root_prefix; ?>pages/role/index.php<?php echo $role_query; ?>" id="nav-permissions">
          <div class="app-grid-icon-wrap" style="background: var(--red-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--red);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Roles &amp; Permissions</div>
            <div class="app-grid-desc">Platform privileges configuration</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Settings'); ?>" href="<?php echo $settings_link; ?>" id="nav-settings">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Settings</div>
            <div class="app-grid-desc">Profile &amp; configurations</div>
          </div>
        </a>
      </div>

    <!-- ==========================================
         APPS FOR: EMPLOYEE
         ========================================== -->
    <?php elseif ($user_role === 'employee'): ?>
      
      <div class="app-grid-section-title">Overview</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Dashboard'); ?>" href="<?php echo $dash_link; ?>" id="nav-main">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Dashboard</div>
            <div class="app-grid-desc">Overview &amp; task reminders</div>
          </div>
        </a>
      </div>

      <div class="app-grid-section-title">My Tasks</div>
      <div class="app-grid-group">
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Sales'); ?>" href="<?php echo $root_prefix; ?>pages/sale/index.php<?php echo $role_query; ?>" id="nav-sales">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Sales</div>
            <div class="app-grid-desc">My recorded customer orders</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Purchases'); ?>" href="<?php echo $root_prefix; ?>pages/purchase/index.php<?php echo $role_query; ?>" id="nav-purchases">
          <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
            <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v10a2 2 0 01-2 2h-4M12 7v10M9 12h6"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Purchases</div>
            <div class="app-grid-desc">My recorded supplier lots</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Inventory'); ?>" href="<?php echo $root_prefix; ?>pages/inventory/index.php<?php echo $role_query; ?>" id="nav-stock">
          <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--green);"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Stock Management</div>
            <div class="app-grid-desc">Current available warehouse stock</div>
          </div>
        </a>
        <a class="app-grid-item<?php echo isPageActive($active_page_title, 'Leave Management'); ?>" href="<?php echo $root_prefix; ?>pages/leave/index.php<?php echo $role_query; ?>" id="nav-leave">
          <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Leave Management</div>
            <div class="app-grid-desc">My leave logs &amp; submission</div>
          </div>
        </a>
      </div>

      <div class="app-grid-section-title">System</div>
      <div class="app-grid-group">
        <a class="app-grid-item" href="<?php echo $settings_link; ?>" id="nav-settings">
          <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
            <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
          </div>
          <div class="app-grid-info">
            <div class="app-grid-name">Settings</div>
            <div class="app-grid-desc">Profile &amp; credentials</div>
          </div>
        </a>
      </div>

    <?php endif; ?>
  </div>
</div>
