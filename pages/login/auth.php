<?php
require_once __DIR__ . '/../../config/session.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['user_id'])) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $pos = strpos($script, '/pages/');
    $base_dir = ($pos !== false) ? rtrim(substr($script, 0, $pos), '/') : '';
    
    header("Location: " . $base_dir . "/index");
    exit();
}

if (!isset($_SESSION['roles']) || !in_array('admin', $_SESSION['roles'])) {
    http_response_code(403);
    echo "<div style='font-family:\"Inter\", sans-serif; text-align:center; margin-top:50px;'>";
    echo "<h2 style='color:#E53E3E;'>Access Denied</h2>";
    echo "<p>You do not have permission to access this page.</p>";
    echo "<a href='../../index'>Back to Login</a>";
    echo "</div>";
    exit();
}
?>
