<!-- TOPBAR / NAVBAR -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="logo-badge">
      <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div class="logo-info">
      <div class="logo-text">Business Management</div>
      <div class="logo-sub">Financial Suite</div>
    </div>
  </div>

  <div class="topbar-divider"></div>

  <div class="topbar-title-area">
    <div class="topbar-page-title"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Cash Lead Schedule'; ?></div>
    <div class="breadcrumb">
      <a href="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/profile/') !== false) ? '../dashboard' : 'dashboard'; ?>">Home</a>
      <span class="sep">/</span>
      <span class="cur"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Cash Lead Schedule'; ?></span>
    </div>
  </div>

  <div class="topbar-search">
    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="Search anything…" id="searchInput">
  </div>
  <div class="topbar-right">
    <button class="icon-btn" title="Notifications" id="notifBtn">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      <span class="notif-dot"></span>
    </button>
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
          $prefix = (strpos($_SERVER['SCRIPT_NAME'], '/profile/') !== false) ? '../../' : '../';
          $avatarGlob = glob($prefix . "src/images/profile/user_" . $userId . ".*");
          if (!empty($avatarGlob)) {
              echo '<img src="' . htmlspecialchars($avatarGlob[0]) . '" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">';
          } else {
              $initials = '';
              if (isset($_SESSION['first_name'])) {
                  $initials .= strtoupper(substr($_SESSION['first_name'], 0, 1));
              }
              if (isset($_SESSION['last_name'])) {
                  $initials .= strtoupper(substr($_SESSION['last_name'], 0, 1));
              }
              echo !empty($initials) ? $initials : 'U';
          }
          ?>
        </div>
        <div class="profile-info">
          <div class="profile-name">
            <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
          </div>
          <div class="profile-role">Administrator</div>
        </div>
        <svg class="profile-caret" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="dropdown" id="profileDropdown">
        <div class="dropdown-header">
          <div class="dropdown-name">
            <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
          </div>
          <div class="dropdown-email">
            <?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>
          </div>
        </div>
        <div class="dropdown-item">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
          Settings
        </div>
        <a href="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/profile/') !== false) ? './my_profile' : '../pages/profile/my_profile'; ?>" class="dropdown-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; width: 100%; gap: 10px;">
          <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          My Profile
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/profile/') !== false) ? '../../pages/login/logout' : '../pages/login/logout'; ?>" class="dropdown-item danger" style="text-decoration: none; color: inherit; display: flex; align-items: center; width: 100%; gap: 10px;">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>
