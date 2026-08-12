<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'business_management');

// Used to encrypt report-delivery API credentials at rest. In production,
// provide BM_REPORT_CONFIG_KEY as an environment variable and keep it outside
// the web root. The local fallback keeps this installation operational.
define('REPORT_CONFIG_KEY', getenv('BM_REPORT_CONFIG_KEY') ?: '6ad9be75f746c61a58213d6c4fcf52445c735a3727e740e7eff564171d2657b3');

/**
 * Return the single typed database connection used during this request.
 *
 * Keeping connection creation behind a function makes the dependency explicit
 * to PHP language servers and prevents included files from creating duplicates.
 */
function getDatabaseConnection(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$connection) {
        error_log('Database connection failed: ' . mysqli_connect_error());
        http_response_code(500);
        exit('Database connection failed.');
    }

    mysqli_set_charset($connection, 'utf8mb4');
    return $connection;
}

/** @var mysqli $conn */
$conn = getDatabaseConnection();
?>
