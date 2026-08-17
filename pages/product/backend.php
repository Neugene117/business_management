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
$readPackageConfiguration = static function ($conn, int $businessId, int $baseUomId): array {
    $hasPackage = isset($_POST['has_package']) && (string)$_POST['has_package'] === '1';
    if (!$hasPackage) return [null, null, null];

    $packageUomId = (int)($_POST['package_uom_id'] ?? 0);
    $unitsPerPackage = normalizeInventoryDecimal($_POST['units_per_package'] ?? 0, 'Units per package');
    $packagePriceInput = trim((string)($_POST['package_sale_price'] ?? ''));
    $packageSalePrice = $packagePriceInput === '' ? null : normalizeInventoryDecimal($packagePriceInput, 'Package selling price');
    if ($packageUomId <= 0 || $unitsPerPackage <= 0) throw new InvalidArgumentException('Package Unit and Units per Package are required.');
    if ($packageUomId === $baseUomId) throw new InvalidArgumentException('Package Unit must be different from the Base Unit of Measure.');
    if ($packageSalePrice !== null && $packageSalePrice < 0) throw new InvalidArgumentException('Package selling price cannot be negative.');
    $packageStmt = mysqli_prepare($conn, 'SELECT id FROM units_of_measure WHERE id=? AND (business_id IS NULL OR business_id=?) LIMIT 1');
    mysqli_stmt_bind_param($packageStmt, 'ii', $packageUomId, $businessId);
    mysqli_stmt_execute($packageStmt);
    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($packageStmt))) throw new InvalidArgumentException('Select a valid package unit available to this business.');
    return [$packageUomId, $unitsPerPackage, $packageSalePrice];
};

switch ($action) {
    case 'create':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $uomId = (int)($_POST['uom'] ?? 0);

        if (empty($name) || $categoryId <= 0 || $uomId <= 0) {
            setFlashMessage('error', 'Name, product category, and UOM are required.');
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
        try {
            [$packageUomId, $unitsPerPackage, $packageSalePrice] = $readPackageConfiguration($conn, (int)$businessId, $uomId);
        } catch (Throwable $error) {
            setFlashMessage('error', $error->getMessage());
            header("Location: index.php" . $role_query);
            exit();
        }
        $categoryStmt = mysqli_prepare($conn, 'SELECT id FROM product_categories WHERE id=? AND business_id=? AND is_active=1 LIMIT 1');
        mysqli_stmt_bind_param($categoryStmt, 'ii', $categoryId, $businessId);
        mysqli_stmt_execute($categoryStmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($categoryStmt))) {
            setFlashMessage('error', 'Select a valid active product category.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // The temporary value satisfies the existing NOT NULL constraint but
            // remains invisible outside this transaction. The permanent product
            // code is based on the globally unique auto-increment product ID.
            $temporaryCode = 'AUTO-' . strtoupper(bin2hex(random_bytes(16)));
            $query = "
                INSERT INTO products (
                    business_id, sku, name, category_id, uom, uom_id, package_uom_id, units_per_package, package_sale_price,
                    is_active, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    1, NOW(6), NOW(6)
                )
            ";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'issisiidd', $businessId, $temporaryCode, $name, $categoryId, $uom, $uomId, $packageUomId, $unitsPerPackage, $packageSalePrice);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Could not insert the product record.');
            }

            $productId = mysqli_insert_id($conn);
            $productCode = sprintf('PRD-%06d', $productId);
            $codeStmt = mysqli_prepare($conn, 'UPDATE products SET sku=? WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($codeStmt, 'sii', $productCode, $productId, $businessId);
            if (!mysqli_stmt_execute($codeStmt) || mysqli_stmt_affected_rows($codeStmt) !== 1) {
                throw new RuntimeException('Could not assign the generated product code.');
            }

            writeAuditLog($conn, $businessId, 'PRODUCT_CREATED', 'product', $productId, [
                'product_code' => $productCode,
                'name' => $name,
                'category_id' => $categoryId,
                'uom_id' => $uomId,
                'package_uom_id' => $packageUomId,
                'units_per_package' => $unitsPerPackage,
                'package_sale_price' => $packageSalePrice
            ]);
            mysqli_commit($conn);
            setFlashMessage('success', 'Product saved to catalog with code ' . $productCode . '.');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            error_log('Product registration failed: ' . $error->getMessage());
            setFlashMessage('error', 'Failed to register product.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $uomId = (int)($_POST['uom'] ?? 0);

        if (empty($productId) || empty($name) || $categoryId <= 0 || $uomId <= 0) {
            setFlashMessage('error', 'Name, product category, and UOM are required.');
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
        try {
            [$packageUomId, $unitsPerPackage, $packageSalePrice] = $readPackageConfiguration($conn, (int)$businessId, $uomId);
        } catch (Throwable $error) {
            setFlashMessage('error', $error->getMessage());
            header("Location: index.php" . $role_query);
            exit();
        }
        $categoryStmt = mysqli_prepare($conn, 'SELECT id FROM product_categories WHERE id=? AND business_id=? LIMIT 1');
        mysqli_stmt_bind_param($categoryStmt, 'ii', $categoryId, $businessId);
        mysqli_stmt_execute($categoryStmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($categoryStmt))) {
            setFlashMessage('error', 'Select a valid product category.');
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

        if ($uomId !== (int)$oldRow['uom_id']) {
            $historyStmt = mysqli_prepare($conn, 'SELECT 1 FROM inventory_movements WHERE business_id=? AND product_id=? UNION ALL SELECT 1 FROM purchase_items WHERE business_id=? AND product_id=? UNION ALL SELECT 1 FROM sale_items WHERE business_id=? AND product_id=? LIMIT 1');
            mysqli_stmt_bind_param($historyStmt, 'iiiiii', $businessId, $productId, $businessId, $productId, $businessId, $productId);
            mysqli_stmt_execute($historyStmt);
            if (mysqli_fetch_assoc(mysqli_stmt_get_result($historyStmt))) {
                setFlashMessage('error', 'The base unit cannot be changed after this product has inventory history.');
                header("Location: index.php" . $role_query);
                exit();
            }
        }

        $oldPackageUomId = !empty($oldRow['package_uom_id']) ? (int)$oldRow['package_uom_id'] : null;
        $packageChanged = $packageUomId !== $oldPackageUomId
            || (($unitsPerPackage === null) !== ($oldRow['units_per_package'] === null))
            || ($unitsPerPackage !== null && abs($unitsPerPackage - (float)$oldRow['units_per_package']) > 0.00005);
        if ($packageChanged) {
            $stockStmt = mysqli_prepare($conn, 'SELECT 1 FROM inventory_balances WHERE business_id=? AND product_id=? AND ABS(quantity_on_hand)>.00005 LIMIT 1');
            mysqli_stmt_bind_param($stockStmt, 'ii', $businessId, $productId);
            mysqli_stmt_execute($stockStmt);
            $hasStock = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($stockStmt));
            $hasOpenPackageOrder = false;
            if ($oldPackageUomId !== null) {
                $openStmt = mysqli_prepare($conn, "SELECT 1 FROM purchase_items pi JOIN purchases pu ON pu.id=pi.purchase_id AND pu.business_id=pi.business_id WHERE pi.business_id=? AND pi.product_id=? AND pi.purchase_uom_id=? AND pu.status IN ('DRAFT','ORDERED','PARTIALLY_RECEIVED') LIMIT 1");
                mysqli_stmt_bind_param($openStmt, 'iii', $businessId, $productId, $oldPackageUomId);
                mysqli_stmt_execute($openStmt);
                $hasOpenPackageOrder = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($openStmt));
            }
            if ($hasStock || $hasOpenPackageOrder) {
                setFlashMessage('error', 'Package Unit and Units per Package cannot be changed while this product has stock or an open package purchase order.');
                header("Location: index.php" . $role_query);
                exit();
            }
        }

        $query = "
            UPDATE products 
            SET name = ?, category_id = ?, uom = ?, uom_id = ?, package_uom_id=?, units_per_package=?, package_sale_price=?, updated_at = NOW(6)
            WHERE id = ? AND business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sisiiddii', $name, $categoryId, $uom, $uomId, $packageUomId, $unitsPerPackage, $packageSalePrice, $productId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'PRODUCT_UPDATED', 'product', $productId, [
                'product_code' => $oldRow['sku'],
                'name' => $name,
                'category_id' => $categoryId,
                'uom_id' => $uomId,
                'package_uom_id' => $packageUomId,
                'units_per_package' => $unitsPerPackage,
                'package_sale_price' => $packageSalePrice
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
