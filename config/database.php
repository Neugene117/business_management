<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'business_management');

$conn = mysqli_connect(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME
);

if (!$conn) {
    error_log(
        'Database connection failed: ' .
        mysqli_connect_error()
    );

    http_response_code(500);
    exit('Database connection failed.');
}

mysqli_set_charset($conn, 'utf8mb4');
?>
