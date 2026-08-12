<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/tenant.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';

requireLogin();
requireActiveBusiness($conn);

$permissions = require __DIR__ . '/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

validateCsrfToken($_POST['csrf_token'] ?? '');

$action = isset($_POST['action']) ? $_POST['action'] : '';
$businessId = $_SESSION['active_business_id'];
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';

switch ($action) {
    case 'create':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $location_type = trim($_POST['location_type'] ?? 'STORE');
        $address = trim($_POST['address'] ?? NULL);

        if (empty($code) || empty($name)) {
            setFlashMessage('error', 'Location code and name are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate uniqueness of code for this tenant
        $chkQuery = "SELECT id FROM business_locations WHERE business_id = ? AND code = ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkQuery);
        mysqli_stmt_bind_param($chkStmt, 'is', $businessId, $code);
        mysqli_stmt_execute($chkStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($chkStmt)) > 0) {
            setFlashMessage('error', 'A location with this code already exists.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            INSERT INTO business_locations (business_id, code, name, location_type, address, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW(6), NOW(6))
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issss', $businessId, $code, $name, $location_type, $address);
        if (mysqli_stmt_execute($stmt)) {
            $locationId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'LOCATION_CREATED', 'business_location', $locationId, [
                'code' => $code,
                'name' => $name,
                'location_type' => $location_type
            ]);
            setFlashMessage('success', 'Location created successfully.');
        } else {
            setFlashMessage('error', 'Failed to create location.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $locationId = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $location_type = trim($_POST['location_type'] ?? 'STORE');
        $address = trim($_POST['address'] ?? NULL);

        if (empty($locationId) || empty($code) || empty($name)) {
            setFlashMessage('error', 'Location code and name are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate uniqueness of code for this tenant (except current location)
        $chkQuery = "SELECT id FROM business_locations WHERE business_id = ? AND code = ? AND id != ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkQuery);
        mysqli_stmt_bind_param($chkStmt, 'isi', $businessId, $code, $locationId);
        mysqli_stmt_execute($chkStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($chkStmt)) > 0) {
            setFlashMessage('error', 'Another location with this code already exists.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch old values for audit
        $oldQuery = "SELECT * FROM business_locations WHERE id = ? AND business_id = ? LIMIT 1";
        $oldStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, 'ii', $locationId, $businessId);
        mysqli_stmt_execute($oldStmt);
        $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));

        if (!$oldRow) {
            setFlashMessage('error', 'Location record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE business_locations 
            SET code = ?, name = ?, location_type = ?, address = ?, updated_at = NOW(6) 
            WHERE id = ? AND business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssssii', $code, $name, $location_type, $address, $locationId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'LOCATION_UPDATED', 'business_location', $locationId, [
                'code' => $code,
                'name' => $name,
                'location_type' => $location_type,
                'address' => $address
            ], $oldRow);
            setFlashMessage('success', 'Location updated successfully.');
        } else {
            setFlashMessage('error', 'Failed to update location.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'toggle_status':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $locationId = isset($_POST['location_id']) ? (int)$_POST['location_id'] : 0;

        if (empty($locationId)) {
            setFlashMessage('error', 'Invalid location identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "SELECT is_active FROM business_locations WHERE id = ? AND business_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $locationId, $businessId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $new_status = $row['is_active'] ? 0 : 1;
            $updQuery = "UPDATE business_locations SET is_active = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?";
            $updStmt = mysqli_prepare($conn, $updQuery);
            mysqli_stmt_bind_param($updStmt, 'iii', $new_status, $locationId, $businessId);
            if (mysqli_stmt_execute($updStmt)) {
                writeAuditLog($conn, $businessId, 'LOCATION_STATUS_TOGGLED', 'business_location', $locationId, [
                    'is_active' => $new_status
                ], ['is_active' => $row['is_active']]);
                setFlashMessage('success', 'Location status toggled.');
            } else {
                setFlashMessage('error', 'Failed to toggle location status.');
            }
        } else {
            setFlashMessage('error', 'Location not found.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
