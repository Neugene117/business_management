<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
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

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? NULL);
        $email = strtolower(trim($_POST['email'] ?? NULL));
        $tax_number = trim($_POST['tax_number'] ?? NULL);
        $address = trim($_POST['address'] ?? NULL);

        if (empty($name)) {
            setFlashMessage('error', 'Customer name is required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            INSERT INTO customers (business_id, name, phone, email, tax_number, address, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(6), NOW(6))
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issssss', $businessId, $name, $phone, $email, $tax_number, $address);
        if (mysqli_stmt_execute($stmt)) {
            $customerId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'CUSTOMER_CREATED', 'customer', $customerId, [
                'name' => $name,
                'phone' => $phone,
                'tax_number' => $tax_number
            ]);
            setFlashMessage('success', 'Customer registered successfully.');
        } else {
            setFlashMessage('error', 'Failed to register customer.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? NULL);
        $email = strtolower(trim($_POST['email'] ?? NULL));
        $tax_number = trim($_POST['tax_number'] ?? NULL);
        $address = trim($_POST['address'] ?? NULL);

        if (empty($customerId) || empty($name)) {
            setFlashMessage('error', 'Customer name is required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch old for audit
        $oldQuery = "SELECT * FROM customers WHERE id = ? AND business_id = ? LIMIT 1";
        $oldStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, 'ii', $customerId, $businessId);
        mysqli_stmt_execute($oldStmt);
        $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));

        if (!$oldRow) {
            setFlashMessage('error', 'Customer record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE customers 
            SET name = ?, phone = ?, email = ?, tax_number = ?, address = ?, updated_at = NOW(6) 
            WHERE id = ? AND business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssssssii', $name, $phone, $email, $tax_number, $address, $customerId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'CUSTOMER_UPDATED', 'customer', $customerId, [
                'name' => $name,
                'phone' => $phone,
                'tax_number' => $tax_number,
                'address' => $address
            ], $oldRow);
            setFlashMessage('success', 'Customer parameters updated.');
        } else {
            setFlashMessage('error', 'Failed to update customer.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'toggle_status':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;

        if (empty($customerId)) {
            setFlashMessage('error', 'Invalid customer identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "SELECT is_active FROM customers WHERE id = ? AND business_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $customerId, $businessId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $new_status = $row['is_active'] ? 0 : 1;
            $updQuery = "UPDATE customers SET is_active = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?";
            $updStmt = mysqli_prepare($conn, $updQuery);
            mysqli_stmt_bind_param($updStmt, 'iii', $new_status, $customerId, $businessId);
            if (mysqli_stmt_execute($updStmt)) {
                writeAuditLog($conn, $businessId, 'CUSTOMER_STATUS_TOGGLED', 'customer', $customerId, [
                    'is_active' => $new_status
                ], ['is_active' => $row['is_active']]);
                setFlashMessage('success', 'Customer status toggled.');
            } else {
                setFlashMessage('error', 'Failed to toggle customer status.');
            }
        } else {
            setFlashMessage('error', 'Customer not found.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
