<?php
require_once __DIR__ . '/config/session.php';

if (isset($_SESSION['user_id'])) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $base_dir = rtrim(dirname($script), '/');
    header("Location: " . $base_dir . "/pages/dashboard");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Business Management — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="src/css/login.css">
</head>
<body>

<!-- LEFT: FORM PANEL -->
<div class="left-panel">
  <div class="form-card">

    <div class="brand">
      <div class="logo-badge">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
      <div>
        <div class="brand-text">Business Management</div>
        <div class="brand-sub">Financial Suite</div>
      </div>
    </div>

    <div class="form-title">Welcome back</div>
    <div class="form-desc">Sign in to access your account</div>

    <div class="alert-msg error" id="alertError" style="display:none;">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span id="errorText">Invalid email or password. Please try again.</span>
    </div>
    
    <div class="alert-msg success" id="alertSuccess" style="display:none;">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      <span>Signed in successfully. Redirecting…</span>
    </div>

    <div class="field">
      <label for="email">Email address</label>
      <div class="field-wrap">
        <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <input type="email" id="email" placeholder="admin@bm.rw" autocomplete="email">
      </div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="field-wrap">
        <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        <input type="password" id="password" placeholder="Enter your password" autocomplete="current-password">
        <button class="toggle-pw" type="button" onclick="togglePassword()" id="toggleBtn" title="Show password">
          <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <div class="field-row">
      <label class="checkbox-wrap">
        <input type="checkbox" id="rememberMe">
        <span>Remember me</span>
      </label>
      <a href="#" class="forgot">Forgot password?</a>
    </div>

    <button class="btn-login" onclick="handleLogin()" id="loginBtn">
      <span id="btnText">Sign In</span>
      <div class="spinner" id="spinner"></div>
      <svg id="btnArrow" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </button>

    <div class="form-footer">
      Don't have an account? <a href="#">Contact your administrator</a>
    </div>

  </div>
</div>

<!-- RIGHT: BRAND PANEL -->
<div class="right-panel">
  <div class="rp-content">
    <div class="rp-icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
    </div>
    <div class="rp-title">Business Management<br>Financial Suite</div>
    <div class="rp-desc">Consolidated cash schedules, bank reconciliation, petty cash tracking, and mineral stock valuation in a unified administration suite.</div>
  </div>

  <div class="rp-footer">© 2026 Business Management · All rights reserved</div>
</div>

<script src="src/js/login.js"></script>
</body>
</html>
