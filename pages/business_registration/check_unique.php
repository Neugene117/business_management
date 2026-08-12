<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$field = isset($_GET['field']) ? trim($_GET['field']) : '';
$value = isset($_GET['value']) ? trim($_GET['value']) : '';

if (empty($field) || empty($value)) {
    echo json_encode(['available' => true, 'message' => '']);
    exit();
}

$available = true;
$message = 'Available';

switch ($field) {
    case 'owner_email':
        $value = strtolower($value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['available' => false, 'message' => 'Invalid email address format']);
            exit();
        }
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $value);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($res) > 0) {
            $available = false;
            $message = 'Email address is already registered';
        }
        break;

    case 'owner_phone':
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE phone = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $value);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($res) > 0) {
            $available = false;
            $message = 'Phone number is already registered';
        }
        break;

    case 'registration_number':
        $stmt = mysqli_prepare($conn, "SELECT id FROM businesses WHERE registration_number = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $value);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($res) > 0) {
            $available = false;
            $message = 'Business registration number is already registered';
        }
        break;

    default:
        $available = true;
        $message = '';
        break;
}

echo json_encode(['available' => $available, 'message' => $message]);
exit();
