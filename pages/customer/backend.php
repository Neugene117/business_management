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
$roleValue = isset($_GET['role']) ? (string)$_GET['role'] : '';
$role_query = $roleValue !== '' ? '?role=' . rawurlencode($roleValue) : '';
$sendJson = static function (int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
};

switch ($action) {
    case 'create':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        $returnToSale = ($_POST['return_to'] ?? '') === 'sale';
        $returnToSalePopup = ($_POST['return_to'] ?? '') === 'sale_popup';
        $createFailureUrl = $returnToSale
            ? 'index.php?open=add&return_to=sale' . ($roleValue !== '' ? '&role=' . rawurlencode($roleValue) : '')
            : 'index.php' . $role_query;

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? NULL);
        $email = strtolower(trim($_POST['email'] ?? NULL));
        $tax_number = trim($_POST['tax_number'] ?? NULL);
        $address = trim($_POST['address'] ?? NULL);

        if ($name === '') {
            if ($returnToSalePopup) $sendJson(422, ['success'=>false, 'message'=>'Customer name is required.']);
            setFlashMessage('error', 'Customer name is required.');
            header('Location: ' . $createFailureUrl);
            exit();
        }
        if (strlen($name) > 200 || strlen($phone) > 32 || strlen($email) > 254 || strlen($tax_number) > 100 || strlen($address) > 500) {
            if ($returnToSalePopup) $sendJson(422, ['success'=>false, 'message'=>'One or more customer details are longer than allowed.']);
            setFlashMessage('error', 'One or more customer details are longer than allowed.');
            header('Location: ' . $createFailureUrl);
            exit();
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($returnToSalePopup) $sendJson(422, ['success'=>false, 'message'=>'Enter a valid email address.']);
            setFlashMessage('error', 'Enter a valid email address.');
            header('Location: ' . $createFailureUrl);
            exit();
        }

        $customerCode = 'CUS-' . gmdate('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));

        $query = "
            INSERT INTO customers (
                business_id,
                customer_code,
                name,
                phone,
                email,
                tax_number,
                address,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(6), NOW(6))
        ";

        $stmt = mysqli_prepare($conn, $query);

        if (!$stmt) {
            if ($returnToSalePopup) $sendJson(500, ['success'=>false, 'message'=>'Customer registration could not be prepared. Please try again.']);
            setFlashMessage('error', 'Failed to prepare customer registration.');
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'issssss',
                $businessId,
                $customerCode,
                $name,
                $phone,
                $email,
                $tax_number,
                $address
            );

            if (mysqli_stmt_execute($stmt)) {
                $customerId = mysqli_insert_id($conn);

                writeAuditLog(
                    $conn,
                    $businessId,
                    'CUSTOMER_CREATED',
                    'customer',
                    $customerId,
                    [
                        'name' => $name,
                        'phone' => $phone,
                        'tax_number' => $tax_number
                    ]
                );

                if ($returnToSalePopup) {
                    mysqli_stmt_close($stmt);
                    $sendJson(201, ['success'=>true, 'message'=>'Customer registered successfully.', 'customer'=>['id'=>$customerId, 'name'=>$name]]);
                }
                setFlashMessage('success', 'Customer registered successfully.');
                if ($returnToSale) {
                    $saleQuery = ['resume_sale'=>'1', 'customer_id'=>(string)$customerId];
                    if ($roleValue !== '') $saleQuery['role'] = $roleValue;
                    header('Location: ../sale/create.php?' . http_build_query($saleQuery));
                    exit();
                }
            } else {
                if ($returnToSalePopup) {
                    mysqli_stmt_close($stmt);
                    $sendJson(500, ['success'=>false, 'message'=>'Customer registration failed. Please try again.']);
                }
                setFlashMessage('error', 'Failed to register customer.');
            }

            mysqli_stmt_close($stmt);
        }
        header('Location: ' . $createFailureUrl);
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
