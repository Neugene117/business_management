<?php
ob_start();
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/permission_helper.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/functions.php';

requireLogin();
requireActiveBusiness($conn);

$active_page_title = $page_title ?? 'Dashboard';
$root_prefix = getRootPrefix();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Business Management — <?php echo e($active_page_title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo $root_prefix; ?>src/css/dashboard.css">
<link rel="stylesheet" href="<?php echo $root_prefix; ?>src/css/navbar.css">
<link rel="stylesheet" href="<?php echo $root_prefix; ?>src/css/sidebar.css">
<?php if (isset($extra_css)): ?>
  <?php foreach ($extra_css as $css_file): ?>
    <link rel="stylesheet" href="<?php echo $root_prefix; ?>src/css/<?php echo e($css_file); ?>">
  <?php endforeach; ?>
<?php endif; ?>
<script>
  (function() {
    var savedTheme = localStorage.getItem('theme');
    var currentTheme = savedTheme || 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
  })();
</script>
</head>
<body>

<div class="main">
  <?php include __DIR__ . '/navbar.php'; ?>
  <?php include __DIR__ . '/sidebar.php'; ?>
  
  <div class="content" id="dashboardContent">
    <?php displayFlashMessage(); ?>
