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
$finish = static function (string $message, string $type = 'error') use ($roleQuery): void {
    setFlashMessage($type, $message);
    header('Location: index.php' . $roleQuery);
    exit;
};
$readFields = static function (): array {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $symbol = trim((string)($_POST['symbol'] ?? '')) ?: null;
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{0,31}$/', $code) || $name === '' || strlen($name) > 100 || ($symbol !== null && strlen($symbol) > 20)) {
        throw new InvalidArgumentException('Enter a valid code, name, and optional symbol. Codes may contain letters, numbers, hyphens, and underscores.');
    }
    return [$code,$name,$symbol];
};

try {
    if ($action === 'create') {
        requirePermission($conn, $membershipId, $businessId, $permissions['create']);
        [$code,$name,$symbol] = $readFields();
        $duplicate = mysqli_prepare($conn, 'SELECT id FROM units_of_measure WHERE code=? AND (business_id IS NULL OR business_id=?) LIMIT 1');
        mysqli_stmt_bind_param($duplicate, 'si', $code, $businessId);
        mysqli_stmt_execute($duplicate);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('That unit code already exists in the available units.');
        $stmt = mysqli_prepare($conn, 'INSERT INTO units_of_measure (business_id,code,name,symbol,created_at,updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
        mysqli_stmt_bind_param($stmt, 'isss', $businessId, $code, $name, $symbol);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('The unit of measure could not be created.');
        $unitId = mysqli_insert_id($conn);
        writeAuditLog($conn, $businessId, 'UNIT_OF_MEASURE_CREATED', 'unit_of_measure', $unitId, ['code'=>$code,'name'=>$name,'symbol'=>$symbol]);
        $finish('Unit of measure created.', 'success');
    }

    if ($action === 'update') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        [$code,$name,$symbol] = $readFields();
        $existingStmt = mysqli_prepare($conn, 'SELECT id,code,name,symbol FROM units_of_measure WHERE id=? AND business_id=? LIMIT 1');
        mysqli_stmt_bind_param($existingStmt, 'ii', $unitId, $businessId);
        mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        if (!$existing) throw new RuntimeException('Only custom units belonging to this company can be updated.');
        $duplicate = mysqli_prepare($conn, 'SELECT id FROM units_of_measure WHERE code=? AND id<>? AND (business_id IS NULL OR business_id=?) LIMIT 1');
        mysqli_stmt_bind_param($duplicate, 'sii', $code, $unitId, $businessId);
        mysqli_stmt_execute($duplicate);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicate))) throw new RuntimeException('That unit code already exists in the available units.');
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, 'UPDATE units_of_measure SET code=?,name=?,symbol=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($stmt, 'sssii', $code, $name, $symbol, $unitId, $businessId);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('The unit could not be updated.');
            $productsStmt = mysqli_prepare($conn, 'UPDATE products SET uom=?,updated_at=UTC_TIMESTAMP(6) WHERE business_id=? AND uom_id=?');
            mysqli_stmt_bind_param($productsStmt, 'sii', $code, $businessId, $unitId);
            if (!mysqli_stmt_execute($productsStmt)) throw new RuntimeException('Products using the unit could not be synchronized.');
            writeAuditLog($conn, $businessId, 'UNIT_OF_MEASURE_UPDATED', 'unit_of_measure', $unitId, ['code'=>$code,'name'=>$name,'symbol'=>$symbol], $existing);
            mysqli_commit($conn);
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            throw $error;
        }
        $finish('Unit of measure updated.', 'success');
    }

    if ($action === 'delete') {
        requirePermission($conn, $membershipId, $businessId, $permissions['update']);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $existingStmt = mysqli_prepare($conn, 'SELECT id,code,name,symbol FROM units_of_measure WHERE id=? AND business_id=? LIMIT 1');
        mysqli_stmt_bind_param($existingStmt, 'ii', $unitId, $businessId);
        mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        if (!$existing) throw new RuntimeException('Only custom units belonging to this company can be deleted.');
        $usageStmt = mysqli_prepare($conn, 'SELECT
            (SELECT COUNT(*) FROM products WHERE business_id=? AND uom_id=?) base_products,
            (SELECT COUNT(*) FROM products WHERE business_id=? AND package_uom_id=?) package_products,
            (SELECT COUNT(*) FROM purchase_items WHERE business_id=? AND purchase_uom_id=?) purchase_lines,
            (SELECT COUNT(*) FROM sale_items WHERE business_id=? AND sale_uom_id=?) sale_lines');
        mysqli_stmt_bind_param($usageStmt, 'iiiiiiii', $businessId, $unitId, $businessId, $unitId, $businessId, $unitId, $businessId, $unitId);
        mysqli_stmt_execute($usageStmt);
        $usage = mysqli_fetch_assoc(mysqli_stmt_get_result($usageStmt)) ?: [];
        $usageTotal = array_sum(array_map('intval', $usage));
        if ($usageTotal > 0) throw new RuntimeException('This unit cannot be deleted because it is used by a product or historical purchase/sale transaction.');
        $stmt = mysqli_prepare($conn, 'DELETE FROM units_of_measure WHERE id=? AND business_id=?');
        mysqli_stmt_bind_param($stmt, 'ii', $unitId, $businessId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('The unit could not be deleted.');
        writeAuditLog($conn, $businessId, 'UNIT_OF_MEASURE_DELETED', 'unit_of_measure', $unitId, [], $existing);
        $finish('Unit of measure deleted.', 'success');
    }
    throw new InvalidArgumentException('Invalid unit action.');
} catch (Throwable $error) {
    $finish($error->getMessage());
}
