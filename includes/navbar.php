<?php
$isNotificationEndpoint = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if ($isNotificationEndpoint) {
    require_once __DIR__ . '/../config/session.php';
    require_once __DIR__ . '/../config/database.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit();
    }

    $notificationAction = $_GET['notification_action'] ?? '';
    $notificationUserId = (int)$_SESSION['user_id'];

    if ($notificationAction === 'list' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $listQuery = "
            SELECT id, title, message, type, link_url, read_at, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 30
        ";
        $listStmt = mysqli_prepare($conn, $listQuery);
        mysqli_stmt_bind_param($listStmt, 'i', $notificationUserId);
        mysqli_stmt_execute($listStmt);
        $listResult = mysqli_stmt_get_result($listStmt);
        $items = [];
        while ($notification = mysqli_fetch_assoc($listResult)) {
            $items[] = [
                'id' => (int)$notification['id'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'type' => strtolower($notification['type']),
                'link_url' => $notification['link_url'],
                'unread' => $notification['read_at'] === null,
                'created_at' => $notification['created_at']
            ];
        }

        $countQuery = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND read_at IS NULL";
        $countStmt = mysqli_prepare($conn, $countQuery);
        mysqli_stmt_bind_param($countStmt, 'i', $notificationUserId);
        mysqli_stmt_execute($countStmt);
        $unreadCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['unread_count'] ?? 0);

        echo json_encode(['success' => true, 'notifications' => $items, 'unread_count' => $unreadCount]);
        exit();
    }

    if (in_array($notificationAction, ['mark_read', 'mark_all_read'], true)
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $submittedToken = $_POST['csrf_token'] ?? '';
        if ($sessionToken === '' || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid request token.']);
            exit();
        }

        if ($notificationAction === 'mark_read') {
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            if ($notificationId <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Invalid notification.']);
                exit();
            }

            // The authenticated user id is part of the UPDATE predicate. A user
            // can never mark another user's notification as read.
            $readQuery = "UPDATE notifications SET read_at = COALESCE(read_at, NOW(6)) WHERE id = ? AND user_id = ?";
            $readStmt = mysqli_prepare($conn, $readQuery);
            mysqli_stmt_bind_param($readStmt, 'ii', $notificationId, $notificationUserId);
            mysqli_stmt_execute($readStmt);
        } else {
            $readAllQuery = "UPDATE notifications SET read_at = NOW(6) WHERE user_id = ? AND read_at IS NULL";
            $readAllStmt = mysqli_prepare($conn, $readAllQuery);
            mysqli_stmt_bind_param($readAllStmt, 'i', $notificationUserId);
            mysqli_stmt_execute($readAllStmt);
        }

        $countQuery = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND read_at IS NULL";
        $countStmt = mysqli_prepare($conn, $countQuery);
        mysqli_stmt_bind_param($countStmt, 'i', $notificationUserId);
        mysqli_stmt_execute($countStmt);
        $unreadCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['unread_count'] ?? 0);

        echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Unsupported notification request.']);
    exit();
}

$navbarMembershipId = $_SESSION['membership_id'] ?? null;
$navbarBusinessId = $_SESSION['active_business_id'] ?? null;
$navbarCanViewDashboard = hasPermission($conn, $navbarMembershipId, $navbarBusinessId, 'dashboard.view');
$navbarCanViewSettings = hasPermission($conn, $navbarMembershipId, $navbarBusinessId, 'settings.view');
$navbarCompanyName = 'BM System';
$navbarCompanyLogoUrl = null;
if (!empty($navbarBusinessId)) {
    if (isSuperAdmin()) {
        $companyQuery = "SELECT business_name, company_logo_path FROM businesses WHERE id = ? LIMIT 1";
        $companyStmt = mysqli_prepare($conn, $companyQuery);
        mysqli_stmt_bind_param($companyStmt, 'i', $navbarBusinessId);
    } else {
        $navbarUserId = (int)($_SESSION['user_id'] ?? 0);
        $companyQuery = "
            SELECT b.business_name, b.company_logo_path
            FROM businesses b
            JOIN business_memberships m ON m.business_id = b.id
            WHERE b.id = ? AND m.id = ? AND m.user_id = ? AND m.status = 'ACTIVE'
            LIMIT 1
        ";
        $companyStmt = mysqli_prepare($conn, $companyQuery);
        mysqli_stmt_bind_param($companyStmt, 'iii', $navbarBusinessId, $navbarMembershipId, $navbarUserId);
    }
    mysqli_stmt_execute($companyStmt);
    $navbarCompany = mysqli_fetch_assoc(mysqli_stmt_get_result($companyStmt));
    if ($navbarCompany) {
        $navbarCompanyName = $navbarCompany['business_name'];
        $navbarCompanyLogoUrl = getCompanyLogoUrl($navbarCompany['company_logo_path'] ?? null, $root_prefix);
    }
}
$notificationCsrfToken = generateCsrfToken();
$notificationEndpoint = $root_prefix . 'includes/navbar.php';
?>
<!-- TOPBAR / NAVBAR -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="logo-badge">
      <?php if ($navbarCompanyLogoUrl): ?>
        <img src="<?php echo e($navbarCompanyLogoUrl); ?>" alt="<?php echo e($navbarCompanyName); ?> logo">
      <?php else: ?>
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      <?php endif; ?>
    </div>
    <div class="logo-info">
      <div class="logo-text" title="<?php echo e($navbarCompanyName); ?>"><?php echo e($navbarCompanyName); ?></div>
      <div class="logo-sub">Business Workspace</div>
    </div>
  </div>

  <div class="topbar-divider"></div>

  <div class="topbar-title-area">
    <div class="topbar-page-title"><?php echo e($active_page_title); ?></div>
    <div class="breadcrumb">
      <?php if ($navbarCanViewDashboard): ?>
      <a href="<?php echo $root_prefix; ?>pages/dashboard/index.php<?php echo e(getRolePreviewQuery()); ?>">Home</a>
      <span class="sep">/</span>
      <?php endif; ?>
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
    <div class="notif-wrap" id="notificationCenter" data-endpoint="<?php echo e($notificationEndpoint); ?>" data-csrf-token="<?php echo e($notificationCsrfToken); ?>" style="position: relative;">
      <button class="icon-btn" title="Notifications" id="notifBtn" onclick="toggleNotifDropdown()" aria-label="Notifications" aria-expanded="false">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="notif-count" id="notifCount" hidden>0</span>
      </button>
      <div class="dropdown" id="notifDropdown" role="dialog" aria-label="Notifications">
        <div class="notif-header">
          <div>
            <div class="dropdown-name">Notifications</div>
            <div class="notif-subtitle" id="notifSubtitle">Loading your notifications...</div>
          </div>
          <button class="notif-mark-all" id="notifMarkAllBtn" type="button">Mark all read</button>
        </div>
        <div class="notif-list" id="notifList" aria-live="polite">
          <div class="notif-empty">Loading notifications...</div>
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
            <?php echo e($_SESSION['email'] ?: ($_SESSION['phone'] ?? '')); ?>
          </div>
        </div>
        <?php if ($navbarCanViewSettings): ?>
        <a href="<?php echo $root_prefix; ?>pages/settings/index.php<?php echo e(getRolePreviewQuery()); ?>" class="dropdown-item" style="text-decoration: none; color: inherit; display: flex; align-items: center; width: 100%; gap: 10px;">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
          Settings
        </a>
        <div class="dropdown-divider"></div>
        <?php endif; ?>
        <a href="<?php echo $root_prefix; ?>logout.php" class="dropdown-item danger" style="text-decoration: none; color: inherit; display: flex; align-items: center; width: 100%; gap: 10px;">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
          Logout
        </a>
      </div>
    </div>
  </div>
</header>
