<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/flash.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard/index.php");
    exit();
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    validateCsrfToken($_POST['csrf_token'] ?? '');

    $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter your phone number or email address and password.';
    } else {
        // Query users by either email address or phone number.
        $query = "
            SELECT u.*
            FROM users u
            WHERE LOWER(u.email) = LOWER(?) OR u.phone = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Verify password hash
            if (password_verify($password, $row['password_hash'])) {
                $userId = $row['id'];
                
                // 1. Check if user is Super Admin
                $saQuery = "
                    SELECT upr.platform_role_id, pr.code 
                    FROM user_platform_roles upr
                    JOIN platform_roles pr ON upr.platform_role_id = pr.id
                    WHERE upr.user_id = ? AND pr.code = 'SUPER_ADMIN'
                    LIMIT 1
                ";
                $saStmt = mysqli_prepare($conn, $saQuery);
                mysqli_stmt_bind_param($saStmt, 'i', $userId);
                mysqli_stmt_execute($saStmt);
                $saResult = mysqli_stmt_get_result($saStmt);

                if (mysqli_num_rows($saResult) > 0) {
                    // Super Admin Login
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['phone'] = $row['phone'];
                    $_SESSION['first_name'] = $row['first_name'];
                    $_SESSION['last_name'] = $row['last_name'];
                    $_SESSION['platform_context'] = 'SUPER_ADMIN';
                    
                    // Log audit event
                    writeAuditLog($conn, null, 'PLATFORM_LOGIN_SUCCESS', 'user', $userId, ['login_identifier_type' => str_contains($identifier, '@') ? 'EMAIL' : 'PHONE']);
                    
                    // Redirect to dashboard with role override if wanted or normal
                    header("Location: pages/dashboard/index.php");
                    exit();
                }

                // 2. Check if normal user accounts status is ACTIVE
                if ($row['status'] !== 'ACTIVE') {
                    if ($row['status'] === 'PENDING_APPROVAL') {
                        $error = 'Your user registration is pending administrator approval.';
                    } else {
                        $error = 'Your account has been deactivated (' . $row['status'] . '). Please contact support.';
                    }
                } else {
                    // 3. Query their business memberships
                    $mQuery = "
                        SELECT m.*, b.business_name, b.approval_status 
                        FROM business_memberships m
                        JOIN businesses b ON m.business_id = b.id
                        WHERE m.user_id = ?
                        ORDER BY m.id ASC 
                        LIMIT 1
                    ";
                    $mStmt = mysqli_prepare($conn, $mQuery);
                    mysqli_stmt_bind_param($mStmt, 'i', $userId);
                    mysqli_stmt_execute($mStmt);
                    $mResult = mysqli_stmt_get_result($mStmt);

                    if ($membership = mysqli_fetch_assoc($mResult)) {
                        // Check business approval status
                        if ($membership['approval_status'] === 'PENDING') {
                            $error = 'Your business account is awaiting approval.';
                        } elseif ($membership['approval_status'] === 'REJECTED') {
                            $error = 'Your business registration was rejected.';
                        } elseif ($membership['approval_status'] === 'SUSPENDED') {
                            $error = 'Your business account has been suspended.';
                        } elseif ($membership['status'] !== 'ACTIVE') {
                            $error = 'Your membership in the business is currently ' . $membership['status'] . '.';
                        } else {
                            // Fetch business roles for session roles array
                            $rolesQuery = "
                                SELECT br.code 
                                FROM membership_roles mr
                                JOIN business_roles br ON mr.business_role_id = br.id
                                WHERE mr.membership_id = ?
                            ";
                            $rolesStmt = mysqli_prepare($conn, $rolesQuery);
                            mysqli_stmt_bind_param($rolesStmt, 'i', $membership['id']);
                            mysqli_stmt_execute($rolesStmt);
                            $rolesResult = mysqli_stmt_get_result($rolesStmt);
                            $roles = [];
                            while ($roleRow = mysqli_fetch_assoc($rolesResult)) {
                                $roles[] = strtolower($roleRow['code']);
                            }

                            // Successful Business login
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $userId;
                            $_SESSION['email'] = $row['email'];
                            $_SESSION['phone'] = $row['phone'];
                            $_SESSION['first_name'] = $row['first_name'];
                            $_SESSION['last_name'] = $row['last_name'];
                            $_SESSION['active_business_id'] = $membership['business_id'];
                            $_SESSION['membership_id'] = $membership['id'];
                            $_SESSION['member_type'] = $membership['member_type'];
                            $_SESSION['roles'] = $roles;

                            // Update last login
                            $updateLogin = "UPDATE users SET last_login_at = NOW(6) WHERE id = ?";
                            $ulStmt = mysqli_prepare($conn, $updateLogin);
                            mysqli_stmt_bind_param($ulStmt, 'i', $userId);
                            mysqli_stmt_execute($ulStmt);

                            // Log audit event
                            writeAuditLog($conn, $membership['business_id'], 'BUSINESS_LOGIN_SUCCESS', 'user', $userId, ['login_identifier_type' => str_contains($identifier, '@') ? 'EMAIL' : 'PHONE']);

                            header("Location: pages/dashboard/index.php");
                            exit();
                        }
                    } else {
                        $error = 'You are not assigned to any business membership.';
                    }
                }
            } else {
                $error = 'Invalid phone number/email or password.';
            }
        } else {
            $error = 'Invalid phone number/email or password.';
        }
    }
    
    if (!empty($error)) {
        setFlashMessage('error', $error);
        // Fall through to render
    }
}

// Generate CSRF token for the login form
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Business Management — Sign In</title>
<meta name="description" content="Sign in to your Business Management Financial Suite account.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="src/css/login.css">
</head>
<body>

<div class="login-page">
  <div class="login-card">
    <div class="form-card">

      <div class="brand">
        <div>
          <div class="brand-text">Business Management</div>
          <div class="brand-sub">Financial Suite</div>
        </div>
      </div>

      <div class="form-title">Welcome back</div>
      <div class="form-desc">Sign in to access your account</div>

      <?php displayFlashMessage(); ?>

      <form action="login.php" method="POST" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

        <div class="field">
          <label for="identifier">Phone number or email address</label>
          <div class="field-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <input type="text" name="identifier" id="identifier" placeholder="+250788000000 or name@example.com" value="<?php echo e($identifier); ?>" required autocomplete="username">
          </div>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="field-wrap">
            <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password">
            <button class="toggle-pw" type="button" onclick="togglePassword()" id="toggleBtn" title="Show password">
              <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="field-row">
          <label class="checkbox-wrap">
            <input type="checkbox" name="remember">
            <span>Remember me</span>
          </label>
          <a href="#" class="forgot">Forgot password?</a>
        </div>

        <button class="btn-login" type="submit" id="loginBtn">
          <span>Sign In</span>
          <svg id="btnArrow" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </button>
      </form>

      <div class="form-footer">
        Want to register a business? <a href="pages/business_registration/index.php">Register Business</a>
      </div>

    </div>
  </div>

  <div class="login-footer">&copy; <?php echo date('Y'); ?> Business Management &middot; All rights reserved</div>
</div>

<script>
function togglePassword() {
  const pw = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (!pw || !icon) return;

  if (pw.type === 'password') {
    pw.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    pw.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

// Client side validation overlay helper (idempotency client-side safeguard)
document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  btn.style.opacity = '0.7';
  btn.querySelector('span').textContent = 'Authenticating...';
});
</script>
</body>
</html>
