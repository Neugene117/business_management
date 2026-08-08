<?php
if (session_status() === PHP_SESSION_NONE) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $cookie_path = '/';
    
    $pos = strpos($script, '/pages/');
    if ($pos === false) {
        $pos = strpos($script, '/index.php');
    }
    if ($pos === false) {
        $pos = strpos($script, '/config/');
    }
    
    if ($pos !== false) {
        $cookie_path = rtrim(substr($script, 0, $pos), '/') . '/';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookie_path,
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}
?>
