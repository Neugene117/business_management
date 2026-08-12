<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
require_once __DIR__ . '/includes/audit.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $businessId = $_SESSION['active_business_id'] ?? null;
    
    // Reuse the shared request connection for the audit event.
    writeAuditLog($conn, $businessId, 'LOGOUT_SUCCESS', 'user', $userId);
}

// Unset all of the session variables
$_SESSION = array();

// Destroy cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
