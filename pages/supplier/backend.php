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
        $contact_person = trim($_POST['contact_person'] ?? NULL);
        $phone = trim($_POST['phone'] ?? NULL);
        $email = strtolower(trim($_POST['email'] ?? NULL));
        $tax_number = trim($_POST['tax_number'] ?? NULL);
        $address = trim($_POST['address'] ?? NULL);

        if (empty($name)) {
            setFlashMessage('error', 'Supplier name is required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            INSERT INTO suppliers (business_id, name, contact_person, phone, email, tax_number, address, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(6), NOW(6))
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issssss', $businessId, $name, $contact_person, $phone, $email, $tax_number, $address);
        if (mysqli_stmt_execute($stmt)) {
            $supplierId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'SUPPLIER_CREATED', 'supplier', $supplierId, [
                'name' => $name,
                'contact_person' => $contact_person,
                'tax_number' => $tax_number
            ]);
            setFlashMessage('success', 'Supplier created successfully.');
        } else {
            setFlashMessage('error', 'Failed to register supplier.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $supplierId = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? NULL);
        $phone = trim($_POST['phone'] ?? NULL);
        $email = strtolower(trim($_POST['email'] ?? NULL));
        $tax_number = trim($_POST['tax_number'] ?? NULL);
        $address = trim($_POST['address'] ?? NULL);

        if (empty($supplierId) || empty($name)) {
            setFlashMessage('error', 'Supplier name is required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch old for audit
        $oldQuery = "SELECT * FROM suppliers WHERE id = ? AND business_id = ? LIMIT 1";
        $oldStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, 'ii', $supplierId, $businessId);
        mysqli_stmt_execute($oldStmt);
        $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));

        if (!$oldRow) {
            setFlashMessage('error', 'Supplier record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE suppliers 
            SET name = ?, contact_person = ?, phone = ?, email = ?, tax_number = ?, address = ?, updated_at = NOW(6) 
            WHERE id = ? AND business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssssssii', $name, $contact_person, $phone, $email, $tax_number, $address, $supplierId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'SUPPLIER_UPDATED', 'supplier', $supplierId, [
                'name' => $name,
                'contact_person' => $contact_person,
                'tax_number' => $tax_number,
                'address' => $address
            ], $oldRow);
            setFlashMessage('success', 'Supplier settings updated.');
        } else {
            setFlashMessage('error', 'Failed to update supplier.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'toggle_status':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $supplierId = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;

        if (empty($supplierId)) {
            setFlashMessage('error', 'Invalid supplier identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "SELECT is_active FROM suppliers WHERE id = ? AND business_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $supplierId, $businessId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $new_status = $row['is_active'] ? 0 : 1;
            $updQuery = "UPDATE suppliers SET is_active = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?";
            $updStmt = mysqli_prepare($conn, $updQuery);
            mysqli_stmt_bind_param($updStmt, 'iii', $new_status, $supplierId, $businessId);
            if (mysqli_stmt_execute($updStmt)) {
                writeAuditLog($conn, $businessId, 'SUPPLIER_STATUS_TOGGLED', 'supplier', $supplierId, [
                    'is_active' => $new_status
                ], ['is_active' => $row['is_active']]);
                setFlashMessage('success', 'Supplier status toggled.');
            } else {
                setFlashMessage('error', 'Failed to toggle supplier status.');
            }
        } else {
            setFlashMessage('error', 'Supplier not found.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
