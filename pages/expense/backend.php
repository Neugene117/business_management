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
    case 'create_category':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            setFlashMessage('error', 'Category name is required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            INSERT INTO expense_categories (business_id, name, description, is_active, created_at) 
            VALUES (?, ?, ?, 1, NOW(6))
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iss', $businessId, $name, $description);
        if (mysqli_stmt_execute($stmt)) {
            $catId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'EXPENSE_CATEGORY_CREATED', 'expense_category', $catId, [
                'name' => $name,
                'description' => $description
            ]);
            setFlashMessage('success', 'Expense category created successfully.');
        } else {
            setFlashMessage('error', 'Failed to create expense category.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'create_expense':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        $expense_number = trim($_POST['expense_number'] ?? '');
        $expense_date = trim($_POST['expense_date'] ?? '');
        $expense_category_id = isset($_POST['expense_category_id']) ? (int)$_POST['expense_category_id'] : 0;
        $location_id = isset($_POST['location_id']) && !empty($_POST['location_id']) ? (int)$_POST['location_id'] : NULL;
        $payee = trim($_POST['payee'] ?? NULL);
        $amount = (float)($_POST['amount'] ?? 0.0);
        $tax_amount = (float)($_POST['tax_amount'] ?? 0.0);
        $payment_method = trim($_POST['payment_method'] ?? 'CASH');
        $receipt_reference = trim($_POST['receipt_reference'] ?? NULL);
        $description = trim($_POST['description'] ?? NULL);
        $status = trim($_POST['status'] ?? 'POSTED');

        if (empty($expense_number) || empty($expense_date) || empty($expense_category_id) || $amount <= 0) {
            setFlashMessage('error', 'Expense number, date, category and subtotal amount are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate category belongs to this business
        $catQ = "SELECT id FROM expense_categories WHERE id = ? AND business_id = ? LIMIT 1";
        $cStmt = mysqli_prepare($conn, $catQ);
        mysqli_stmt_bind_param($cStmt, 'ii', $expense_category_id, $businessId);
        mysqli_stmt_execute($cStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($cStmt)) === 0) {
            setFlashMessage('error', 'Invalid expense category selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $total_amount = $amount + $tax_amount;

        $query = "
            INSERT INTO expenses (
                business_id, location_id, expense_category_id, expense_number, expense_date, 
                amount, tax_amount, total_amount, payment_method, payee, 
                description, receipt_reference, status, recorded_by_membership_id, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, NOW(6), NOW(6)
            )
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            'iiissddsssssii',
            $businessId, $location_id, $expense_category_id, $expense_number, $expense_date,
            $amount, $tax_amount, $total_amount, $payment_method, $payee,
            $description, $receipt_reference, $status, $_SESSION['membership_id']
        );

        if (mysqli_stmt_execute($stmt)) {
            $expenseId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'EXPENSE_RECORDED', 'expense', $expenseId, [
                'expense_number' => $expense_number,
                'total_amount' => $total_amount,
                'status' => $status
            ]);
            setFlashMessage('success', 'Expense recorded successfully.');
        } else {
            setFlashMessage('error', 'Failed to save expense ticket.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'post_expense':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['post']);

        $expenseId = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;

        if (empty($expenseId)) {
            setFlashMessage('error', 'Invalid expense identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "UPDATE expenses SET status = 'POSTED', updated_at = NOW(6) WHERE id = ? AND business_id = ? AND status = 'DRAFT'";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $expenseId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'EXPENSE_POSTED', 'expense', $expenseId, ['status' => 'POSTED']);
            setFlashMessage('success', 'Expense transaction posted successfully.');
        } else {
            setFlashMessage('error', 'Failed to post expense transaction.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'void_expense':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['void']);

        $expenseId = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;

        if (empty($expenseId)) {
            setFlashMessage('error', 'Invalid expense identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "UPDATE expenses SET status = 'VOIDED', updated_at = NOW(6) WHERE id = ? AND business_id = ? AND status != 'VOIDED'";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $expenseId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'EXPENSE_VOIDED', 'expense', $expenseId, ['status' => 'VOIDED']);
            setFlashMessage('success', 'Expense transaction voided.');
        } else {
            setFlashMessage('error', 'Failed to void expense.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
