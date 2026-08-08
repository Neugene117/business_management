<?php
if (session_status() === PHP_SESSION_NONE) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $cookie_path = '/';
    
    // Detect project base path consistently for all pages
    $bmPos = strpos($script, '/business_management');
    if ($bmPos !== false) {
        $cookie_path = substr($script, 0, $bmPos) . '/business_management/';
    }

    $cookie_domain = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($cookie_domain)) {
        $cookie_domain = explode(':', $cookie_domain)[0];
    }

    session_name('BM_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookie_path,
        'domain'   => $cookie_domain,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}
?>
