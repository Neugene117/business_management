<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'business_management');

// Used to encrypt report-delivery API credentials at rest. In production,
// provide BM_REPORT_CONFIG_KEY as an environment variable and keep it outside
// the web root. The local fallback keeps this installation operational.
define('REPORT_CONFIG_KEY', getenv('BM_REPORT_CONFIG_KEY') ?: '6ad9be75f746c61a58213d6c4fcf52445c735a3727e740e7eff564171d2657b3');

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
