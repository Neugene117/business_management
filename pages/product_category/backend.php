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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
validateCsrfToken($_POST['csrf_token'] ?? '');
$businessId = (int)$_SESSION['active_business_id'];
$membershipId = (int)$_SESSION['membership_id'];
$action = (string)($_POST['action'] ?? '');
$roleQuery = isset($_GET['role']) ? '?role=' . rawurlencode((string)$_GET['role']) : '';
$finish = static function (string $message, string $type = 'error') use ($roleQuery): void { setFlashMessage($type, $message); header('Location: index.php' . $roleQuery); exit; };
$readFields = static function (): array {
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? '')) ?: null;
    if ($name === '' || strlen($name) > 150 || ($description !== null && strlen($description) > 500)) throw new InvalidArgumentException('Enter a category name and keep the optional description within 500 characters.');
    return [$name,$description];
};

try {
    if ($action === 'create') {
        requirePermission($conn, $membershipId, $businessId, $permissions['create']);
        [$name,$description] = $readFields();
        $duplicate = mysqli_prepare($conn, 'SELECT id FROM product_categories WHERE business_id=? AND name=? LIMIT 1');
        mysqli_stmt_bind_param($duplicate, 'is', $businessId, $name); mysqli_stmt_execute($duplicate);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('A product category with that name already exists.');
        $stmt = mysqli_prepare($conn, 'INSERT INTO product_categories (business_id,parent_id,name,description,is_active,created_at,updated_at) VALUES (?,NULL,?,?,1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
        mysqli_stmt_bind_param($stmt, 'iss', $businessId, $name, $description);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('The product category could not be created.');
        $categoryId = mysqli_insert_id($conn);
        writeAuditLog($conn, $businessId, 'PRODUCT_CATEGORY_CREATED', 'product_category', $categoryId, ['name'=>$name,'description'=>$description]);
        $finish('Product category created.', 'success');
    }
    if ($action === 'update') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        [$name,$description] = $readFields();
        $isActive = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;
        $existingStmt = mysqli_prepare($conn, 'SELECT id,name,description,is_active FROM product_categories WHERE id=? AND business_id=? LIMIT 1');
        mysqli_stmt_bind_param($existingStmt, 'ii', $categoryId, $businessId); mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        if (!$existing) throw new RuntimeException('The product category was not found.');
        $duplicate = mysqli_prepare($conn, 'SELECT id FROM product_categories WHERE business_id=? AND name=? AND id<>? LIMIT 1');
        mysqli_stmt_bind_param($duplicate, 'isi', $businessId, $name, $categoryId); mysqli_stmt_execute($duplicate);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('Another product category already uses that name.');
        if ($isActive === 0) {
            $usageStmt = mysqli_prepare($conn, 'SELECT COUNT(*) total FROM products WHERE business_id=? AND category_id=? AND is_active=1');
            mysqli_stmt_bind_param($usageStmt, 'ii', $businessId, $categoryId); mysqli_stmt_execute($usageStmt);
            if ((int)(mysqli_fetch_assoc(mysqli_stmt_get_result($usageStmt))['total'] ?? 0) > 0) throw new RuntimeException('Move or deactivate the active products in this category before making it inactive.');
        }
        $stmt = mysqli_prepare($conn, 'UPDATE product_categories SET name=?,description=?,is_active=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
        mysqli_stmt_bind_param($stmt, 'ssiii', $name, $description, $isActive, $categoryId, $businessId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('The product category could not be updated.');
        writeAuditLog($conn, $businessId, 'PRODUCT_CATEGORY_UPDATED', 'product_category', $categoryId, ['name'=>$name,'description'=>$description,'is_active'=>$isActive], $existing);
        $finish('Product category updated.', 'success');
    }
    if ($action === 'delete') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $existingStmt = mysqli_prepare($conn, 'SELECT id,name,description,is_active FROM product_categories WHERE id=? AND business_id=? LIMIT 1');
        mysqli_stmt_bind_param($existingStmt, 'ii', $categoryId, $businessId); mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        if (!$existing) throw new RuntimeException('The product category was not found.');
        $usageStmt = mysqli_prepare($conn, 'SELECT COUNT(*) total FROM products WHERE business_id=? AND category_id=?');
        mysqli_stmt_bind_param($usageStmt, 'ii', $businessId, $categoryId); mysqli_stmt_execute($usageStmt);
        $usage = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($usageStmt))['total'] ?? 0);
        if ($usage > 0) throw new RuntimeException('This category contains ' . $usage . ' product(s). Assign them to another category before deleting it.');
        $childStmt = mysqli_prepare($conn, 'SELECT COUNT(*) total FROM product_categories WHERE business_id=? AND parent_id=?');
        mysqli_stmt_bind_param($childStmt, 'ii', $businessId, $categoryId); mysqli_stmt_execute($childStmt);
        if ((int)(mysqli_fetch_assoc(mysqli_stmt_get_result($childStmt))['total'] ?? 0) > 0) throw new RuntimeException('Remove the child categories before deleting this category.');
        $stmt = mysqli_prepare($conn, 'DELETE FROM product_categories WHERE id=? AND business_id=?');
        mysqli_stmt_bind_param($stmt, 'ii', $categoryId, $businessId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('The product category could not be deleted.');
        writeAuditLog($conn, $businessId, 'PRODUCT_CATEGORY_DELETED', 'product_category', $categoryId, [], $existing);
        $finish('Product category deleted.', 'success');
    }
    throw new InvalidArgumentException('Invalid category action.');
} catch (Throwable $error) { $finish($error->getMessage()); }
