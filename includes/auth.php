<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Require user to be logged in
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $pos = strpos($script, '/pages/');
        $prefix = ($pos !== false) ? str_repeat('../', substr_count(substr($script, $pos), '/') - 1) : '';
        
        // Prevent redirects loop if already on login
        if (basename($script) !== 'login.php') {
            header("Location: " . $prefix . "login.php");
            exit();
        }
    }
}

/**
 * Determine if active context is SUPER_ADMIN
 */
function isSuperAdmin(): bool {
    return isset($_SESSION['platform_context']) && $_SESSION['platform_context'] === 'SUPER_ADMIN';
}
?>
