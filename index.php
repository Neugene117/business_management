<?php
require_once __DIR__ . '/config/session.php';

if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard/index.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>
