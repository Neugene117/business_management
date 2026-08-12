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

        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $uomId = (int)($_POST['uom'] ?? 0);

        if (empty($sku) || empty($name) || $categoryId <= 0 || $uomId <= 0) {
            setFlashMessage('error', 'SKU, name, product category, and UOM are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $uomStmt = mysqli_prepare($conn, 'SELECT id,code FROM units_of_measure WHERE id=? AND (business_id IS NULL OR business_id=?) LIMIT 1');
        mysqli_stmt_bind_param($uomStmt, 'ii', $uomId, $businessId);
        mysqli_stmt_execute($uomStmt);
        $uomRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uomStmt));
        if (!$uomRow) {
            setFlashMessage('error', 'Select a valid unit of measure.');
            header("Location: index.php" . $role_query);
            exit();
        }
        $uom = $uomRow['code'];
        $categoryStmt = mysqli_prepare($conn, 'SELECT id FROM product_categories WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
        mysqli_stmt_bind_param($categoryStmt, 'ii', $categoryId, $businessId);
        mysqli_stmt_execute($categoryStmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($categoryStmt))) {
            setFlashMessage('error', 'Select a valid active product category.');
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
                business_id, sku, name, category_id, uom, uom_id,
                is_active, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                1, NOW(6), NOW(6)
            )
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'issisi', $businessId, $sku, $name, $categoryId, $uom, $uomId);
        if (mysqli_stmt_execute($stmt)) {
            $productId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'PRODUCT_CREATED', 'product', $productId, [
                'sku' => $sku,
                'name' => $name,
                'category_id' => $categoryId,
                'uom_id' => $uomId
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
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $uomId = (int)($_POST['uom'] ?? 0);

        if (empty($productId) || empty($sku) || empty($name) || $categoryId <= 0 || $uomId <= 0) {
            setFlashMessage('error', 'SKU, name, product category, and UOM are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $uomStmt = mysqli_prepare($conn, 'SELECT id,code FROM units_of_measure WHERE id=? AND (business_id IS NULL OR business_id=?) LIMIT 1');
        mysqli_stmt_bind_param($uomStmt, 'ii', $uomId, $businessId);
        mysqli_stmt_execute($uomStmt);
        $uomRow = mysqli_fetch_assoc(mysqli_stmt_get_result($uomStmt));
        if (!$uomRow) {
            setFlashMessage('error', 'Select a valid unit of measure.');
            header("Location: index.php" . $role_query);
            exit();
        }
        $uom = $uomRow['code'];
        $categoryStmt = mysqli_prepare($conn, 'SELECT id FROM product_categories WHERE id=? AND business_id=? LIMIT 1');
        mysqli_stmt_bind_param($categoryStmt, 'ii', $categoryId, $businessId);
        mysqli_stmt_execute($categoryStmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($categoryStmt))) {
            setFlashMessage('error', 'Select a valid product category.');
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
            SET sku = ?, name = ?, category_id = ?, uom = ?, uom_id = ?, updated_at = NOW(6)
            WHERE id = ? AND business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssisiii', $sku, $name, $categoryId, $uom, $uomId, $productId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'PRODUCT_UPDATED', 'product', $productId, [
                'sku' => $sku,
                'name' => $name,
                'category_id' => $categoryId,
                'uom_id' => $uomId
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
