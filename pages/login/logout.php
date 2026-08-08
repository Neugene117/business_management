<?php
require_once __DIR__ . '/../../config/session.php';

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$pos = strpos($script, '/pages/');
$base_dir = ($pos !== false) ? rtrim(substr($script, 0, $pos), '/') : '';

header("Location: " . $base_dir . "/index");
exit();
?>
