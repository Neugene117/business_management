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

        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? NULL);
        $uom = strtoupper(trim($_POST['uom'] ?? 'UNIT'));
        $cost_price = (float)($_POST['cost_price'] ?? 0.0);
        $sale_price = (float)($_POST['sale_price'] ?? 0.0);
        $reorder_level = (float)($_POST['reorder_level'] ?? 0.0);

        if (empty($sku) || empty($name) || empty($uom)) {
            setFlashMessage('error', 'SKU, name, and UOM are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate SKU uniqueness for this business
        $chkQuery = "SELECT id FROM products WHERE business_id = ? AND sku = ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkQuery);
        mysqli_stmt_bind_param($chkStmt, 'is', $businessId, $sku);
        mysqli_stmt_execute($chkStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($chkStmt)) > 0) {
            setFlashMessage('error', 'A product with this SKU already exists in catalog.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            INSERT INTO products (
                business_id, sku, name, category, uom, 
                cost_price, sale_price, reorder_level, is_active, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, 1, NOW(6), NOW(6)
            )
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issssddd', $businessId, $sku, $name, $category, $uom, $cost_price, $sale_price, $reorder_level);
        if (mysqli_stmt_execute($stmt)) {
            $productId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'PRODUCT_CREATED', 'product', $productId, [
                'sku' => $sku,
                'name' => $name,
                'cost_price' => $cost_price,
                'sale_price' => $sale_price
            ]);
            setFlashMessage('success', 'Product saved to catalog.');
        } else {
            setFlashMessage('error', 'Failed to register product.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? NULL);
        $uom = strtoupper(trim($_POST['uom'] ?? 'UNIT'));
        $cost_price = (float)($_POST['cost_price'] ?? 0.0);
        $sale_price = (float)($_POST['sale_price'] ?? 0.0);
        $reorder_level = (float)($_POST['reorder_level'] ?? 0.0);

        if (empty($productId) || empty($sku) || empty($name) || empty($uom)) {
            setFlashMessage('error', 'SKU, name, and UOM are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate SKU uniqueness except current
        $chkQuery = "SELECT id FROM products WHERE business_id = ? AND sku = ? AND id != ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkQuery);
        mysqli_stmt_bind_param($chkStmt, 'isi', $businessId, $sku, $productId);
        mysqli_stmt_execute($chkStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($chkStmt)) > 0) {
            setFlashMessage('error', 'Another product with this SKU already exists in catalog.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch old for audit
        $oldQuery = "SELECT * FROM products WHERE id = ? AND business_id = ? LIMIT 1";
        $oldStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, 'ii', $productId, $businessId);
        mysqli_stmt_execute($oldStmt);
        $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));

        if (!$oldRow) {
            setFlashMessage('error', 'Product record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE products 
            SET sku = ?, name = ?, category = ?, uom = ?, 
                cost_price = ?, sale_price = ?, reorder_level = ?, updated_at = NOW(6) 
            WHERE id = ? AND business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssssdddii', $sku, $name, $category, $uom, $cost_price, $sale_price, $reorder_level, $productId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'PRODUCT_UPDATED', 'product', $productId, [
                'sku' => $sku,
                'name' => $name,
                'cost_price' => $cost_price,
                'sale_price' => $sale_price,
                'reorder_level' => $reorder_level
            ], $oldRow);
            setFlashMessage('success', 'Product catalog parameters updated.');
        } else {
            setFlashMessage('error', 'Failed to update product.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'toggle_status':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

        if (empty($productId)) {
            setFlashMessage('error', 'Invalid product identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "SELECT is_active FROM products WHERE id = ? AND business_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ii', $productId, $businessId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $new_status = $row['is_active'] ? 0 : 1;
            $updQuery = "UPDATE products SET is_active = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?";
            $updStmt = mysqli_prepare($conn, $updQuery);
            mysqli_stmt_bind_param($updStmt, 'iii', $new_status, $productId, $businessId);
            if (mysqli_stmt_execute($updStmt)) {
                writeAuditLog($conn, $businessId, 'PRODUCT_STATUS_TOGGLED', 'product', $productId, [
                    'is_active' => $new_status
                ], ['is_active' => $row['is_active']]);
                setFlashMessage('success', 'Product status toggled.');
            } else {
                setFlashMessage('error', 'Failed to toggle product status.');
            }
        } else {
            setFlashMessage('error', 'Product not found.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
