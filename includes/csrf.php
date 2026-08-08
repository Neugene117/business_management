<?php
/**
 * Generate CSRF Token and store in session
 */
function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../config/session.php';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate submitted CSRF Token
 */
function validateCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        require_once __DIR__ . '/../config/session.php';
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token) || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        
        // Show user error or redirect back with error
        require_once __DIR__ . '/flash.php';
        setFlashMessage('error', 'CSRF validation failed. Invalid request token.');
        
        // Redirect to login or previous page
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        header("Location: $referer");
        exit('CSRF token validation failed.');
    }
}
?>
