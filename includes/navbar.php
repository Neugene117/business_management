<!-- TOPBAR / NAVBAR -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="logo-badge">
      <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div class="logo-info">
      <div class="logo-text">BM System</div>
      <div class="logo-sub">Financial Suite</div>
    </div>
  </div>

  <div class="topbar-divider"></div>

  <div class="topbar-title-area">
    <div class="topbar-page-title"><?php echo e($active_page_title); ?></div>
    <div class="breadcrumb">
      <a href="<?php echo $root_prefix; ?>pages/dashboard/index.php<?php echo e(getRolePreviewQuery()); ?>">Home</a>
      <span class="sep">/</span>
      <span class="cur"><?php echo e($active_page_title); ?></span>
    </div>
  </div>

  <div class="topbar-search">
    <form method="GET" action="" style="display:flex; align-items:center; width:100%;">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="search" placeholder="Search records…" value="<?php echo e($_GET['search'] ?? ''); ?>">
    </form>
  </div>
  
  <div class="topbar-right">
    <?php if (isSuperAdmin()): ?>
      <?php $previewRole = getPreviewRole(); ?>
      <nav class="role-switcher" aria-label="Testing Widget">
        <span class="role-switcher-title">Testing Widget:</span>
        <a class="role-btn<?php echo $previewRole === 'super_admin' ? ' active' : ''; ?>" href="<?php echo e(getRolePreviewUrl('super_admin')); ?>">Super Admin</a>
        <a class="role-btn<?php echo $previewRole === 'owner' ? ' active' : ''; ?>" href="<?php echo e(getRolePreviewUrl('owner')); ?>">Business Owner</a>
        <a class="role-btn<?php echo $previewRole === 'employee' ? ' active' : ''; ?>" href="<?php echo e(getRolePreviewUrl('employee')); ?>">Employee</a>
      </nav>
    <?php endif; ?>
    <div class="notif-wrap" style="position: relative;">
      <button class="icon-btn" title="Notifications" id="notifBtn" onclick="toggleNotifDropdown()">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="notif-dot" id="notifDot"></span>
      </button>
      <div class="dropdown" id="notifDropdown" style="min-width: 320px; max-height: 400px; display: none; flex-direction: column;">
        <div class="dropdown-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding: 12px 14px;">
          <div class="dropdown-name" style="font-size: 13px; font-weight: 600;">Notifications</div>
          <button class="btn-sm" style="font-size: 10px; padding: 2px 6px;" onclick="clearAllNotifications(event)">Clear all</button>
        </div>
        <div class="notif-list" id="notifList" style="overflow-y: auto; max-height: 340px;">
          <!-- Populated by JS based on user role -->
        </div>
      </div>
    </div>
    
    <button class="icon-btn" title="Open application menu" id="gridBtn" aria-label="Open application menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="4" height="4" rx="1" />
        <rect x="10" y="3" width="4" height="4" rx="1" />
        <rect x="17" y="3" width="4" height="4" rx="1" />
        <rect x="3" y="10" width="4" height="4" rx="1" />
        <rect x="10" y="10" width="4" height="4" rx="1" />
        <rect x="17" y="10" width="4" height="4" rx="1" />
        <rect x="3" y="17" width="4" height="4" rx="1" />
        <rect x="10" y="17" width="4" height="4" rx="1" />
        <rect x="17" y="17" width="4" height="4" rx="1" />
      </svg>
    </button>
    
    <button class="icon-btn" title="Toggle theme" id="themeToggleBtn">
      <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
    </button>
    
    <div class="profile-wrap">
      <button class="profile-btn" onclick="toggleDropdown()" id="profileBtn">
        <div class="avatar">
          <?php 
          $userId = $_SESSION['user_id'] ?? 0;
          $avatarGlob = glob($root_prefix . "src/images/profile/user_" . $userId . ".*");
          if (!empty($avatarGlob)) {
              echo '<img src="' . e($root_prefix . basename($avatarGlob[0])) . '" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">';
          } else {
              $initials = '';
              if (isset($_SESSION['first_name'])) {
                  $initials .= strtoupper(substr($_SESSION['first_name'], 0, 1));
              }
              if (isset($_SESSION['last_name'])) {
                  $initials .= strtoupper(substr($_SESSION['last_name'], 0, 1));
              }
              echo e(!empty($initials) ? $initials : 'U');
          }
          ?>
        </div>
        <div class="profile-info">
          <div class="profile-name">
            <?php echo e(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
          </div>
          <div class="profile-role">
            <?php 
            if (isSuperAdmin()) {
                echo 'Super Admin';
            } else {
                echo 'Business User';
            }
            ?>
          </div>
        </div>
        <svg class="profile-caret" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      
      <div class="dropdown" id="profileDropdown">
        <div class="dropdown-header">
          <div class="dropdown-name">
            <?php echo e(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
          </div>
          <div class="dropdown-email">
            <?php echo e($_SESSION['email'] ?? ''); ?>
          </div>
        </div>
        <a href="<?php echo $root_prefix; ?>pages/settings/index.php<?php echo e(getRolePreviewQuery()); ?>" class="dropdown-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; width: 100%; gap: 10px;">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
          Settings
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?php echo $root_prefix; ?>logout.php" class="dropdown-item danger" style="text-decoration: none; color: inherit; display: flex; align-items: center; width: 100%; gap: 10px;">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>
