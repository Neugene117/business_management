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
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$role_query = getRolePreviewQuery();

/**
 * Accept only existing BUSINESS-scoped permission identifiers.
 * Returning null distinguishes invalid input from a valid empty selection.
 */
function validateRolePermissionSelection($conn, $submittedPermissions): ?array {
    if (!is_array($submittedPermissions)) {
        return null;
    }

    $permissionIds = [];
    foreach ($submittedPermissions as $permissionId) {
        $validatedId = filter_var($permissionId, FILTER_VALIDATE_INT);
        if ($validatedId === false || $validatedId <= 0) {
            return null;
        }
        $permissionIds[] = (int)$validatedId;
    }
    $permissionIds = array_values(array_unique($permissionIds));

    if ($permissionIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
    $query = "SELECT COUNT(*) AS total FROM permissions WHERE scope = 'BUSINESS' AND id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return null;
    }
    $types = str_repeat('i', count($permissionIds));
    mysqli_stmt_bind_param($stmt, $types, ...$permissionIds);
    mysqli_stmt_execute($stmt);
    $validTotal = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);

    return $validTotal === count($permissionIds) ? $permissionIds : null;
}

/**
 * Replace a role's assigned permission set inside the caller's transaction.
 */
function replaceRolePermissions($conn, int $businessId, int $roleId, array $permissionIds): void {
    $deleteQuery = "DELETE FROM business_role_permissions WHERE business_id = ? AND business_role_id = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteQuery);
    mysqli_stmt_bind_param($deleteStmt, 'ii', $businessId, $roleId);
    if (!mysqli_stmt_execute($deleteStmt)) {
        throw new RuntimeException('Unable to clear the existing role permissions.');
    }

    if ($permissionIds === []) {
        return;
    }

    $insertQuery = "INSERT INTO business_role_permissions (business_id, business_role_id, permission_id) VALUES (?, ?, ?)";
    $insertStmt = mysqli_prepare($conn, $insertQuery);
    foreach ($permissionIds as $permissionId) {
        mysqli_stmt_bind_param($insertStmt, 'iii', $businessId, $roleId, $permissionId);
        if (!mysqli_stmt_execute($insertStmt)) {
            throw new RuntimeException('Unable to assign a selected role permission.');
        }
    }
}

function roleDetailsRedirect(int $roleId): string {
    $params = ['role_id' => $roleId];
    $previewRole = getPreviewRole();
    if ($previewRole !== null) {
        $params['role'] = $previewRole;
    }
    return 'index.php?' . http_build_query($params);
}

switch ($action) {
    case 'create_permission':
        requireSuperAdmin();

        $code = strtolower(trim($_POST['code'] ?? ''));
        $module = strtolower(trim($_POST['module'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $code) || $module === '' || $name === '') {
            setFlashMessage('error', 'Enter a valid permission code, module, and name.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $permissionQuery = "INSERT INTO permissions (scope, code, module, name, description, created_at) VALUES ('BUSINESS', ?, ?, ?, ?, NOW(6))";
        $permissionStmt = mysqli_prepare($conn, $permissionQuery);
        mysqli_stmt_bind_param($permissionStmt, 'ssss', $code, $module, $name, $description);
        if (mysqli_stmt_execute($permissionStmt)) {
            $permissionId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId ?: null, 'PERMISSION_CREATED', 'permission', (string)$permissionId, [
                'code' => $code,
                'module' => $module,
                'name' => $name
            ]);
            setFlashMessage('success', 'Permission created successfully.');
        } else {
            setFlashMessage('error', mysqli_errno($conn) === 1062 ? 'That permission code already exists.' : 'Failed to create the permission.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update_permission':
        requireSuperAdmin();

        $permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
        $module = strtolower(trim($_POST['module'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($permissionId <= 0 || $module === '' || $name === '') {
            setFlashMessage('error', 'Invalid permission details.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $oldPermissionQuery = "SELECT code, module, name, description FROM permissions WHERE id = ? AND scope = 'BUSINESS' LIMIT 1";
        $oldPermissionStmt = mysqli_prepare($conn, $oldPermissionQuery);
        mysqli_stmt_bind_param($oldPermissionStmt, 'i', $permissionId);
        mysqli_stmt_execute($oldPermissionStmt);
        $oldPermission = mysqli_fetch_assoc(mysqli_stmt_get_result($oldPermissionStmt));
        if (!$oldPermission) {
            setFlashMessage('error', 'Permission record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $updatePermissionQuery = "UPDATE permissions SET module = ?, name = ?, description = ? WHERE id = ? AND scope = 'BUSINESS'";
        $updatePermissionStmt = mysqli_prepare($conn, $updatePermissionQuery);
        mysqli_stmt_bind_param($updatePermissionStmt, 'sssi', $module, $name, $description, $permissionId);
        if (mysqli_stmt_execute($updatePermissionStmt)) {
            writeAuditLog($conn, $businessId ?: null, 'PERMISSION_UPDATED', 'permission', (string)$permissionId, [
                'code' => $oldPermission['code'],
                'module' => $module,
                'name' => $name,
                'description' => $description
            ], $oldPermission);
            setFlashMessage('success', 'Permission updated successfully.');
        } else {
            setFlashMessage('error', 'Failed to update the permission.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'delete_permission':
        requireSuperAdmin();

        $permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
        $permissionQuery = "SELECT code, module, name FROM permissions WHERE id = ? AND scope = 'BUSINESS' LIMIT 1";
        $permissionStmt = mysqli_prepare($conn, $permissionQuery);
        mysqli_stmt_bind_param($permissionStmt, 'i', $permissionId);
        mysqli_stmt_execute($permissionStmt);
        $permissionRow = mysqli_fetch_assoc(mysqli_stmt_get_result($permissionStmt));
        if (!$permissionRow) {
            setFlashMessage('error', 'Permission record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $deletePermissionQuery = "DELETE FROM permissions WHERE id = ? AND scope = 'BUSINESS'";
        $deletePermissionStmt = mysqli_prepare($conn, $deletePermissionQuery);
        mysqli_stmt_bind_param($deletePermissionStmt, 'i', $permissionId);
        if (mysqli_stmt_execute($deletePermissionStmt)) {
            writeAuditLog($conn, $businessId ?: null, 'PERMISSION_DELETED', 'permission', (string)$permissionId, $permissionRow);
            setFlashMessage('success', 'Permission deleted successfully.');
        } else {
            setFlashMessage('error', 'Failed to delete the permission.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'create_role':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        if (!isSuperAdmin() && !isBusinessOwner()) {
            setFlashMessage('error', 'Only the Business Owner or Super Admin can create business roles.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $selectedPermissions = validateRolePermissionSelection($conn, $_POST['permissions'] ?? []);

        if (empty($code) || empty($name)) {
            setFlashMessage('error', 'Role code and display name are required.');
            header("Location: index.php" . $role_query);
            exit();
        }
        if ($selectedPermissions === null) {
            setFlashMessage('error', 'One or more selected permissions are invalid.');
            header("Location: index.php" . $role_query);
            exit();
        }

        if (!isSuperAdmin() && (strcasecmp($code, 'OWNER') === 0 || strcasecmp($name, 'Owner') === 0)) {
            setFlashMessage('error', 'Only the Super Admin can create or manage the Owner role.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate code uniqueness for business
        $chkQuery = "SELECT id FROM business_roles WHERE business_id = ? AND code = ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkQuery);
        mysqli_stmt_bind_param($chkStmt, 'is', $businessId, $code);
        mysqli_stmt_execute($chkStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($chkStmt)) > 0) {
            setFlashMessage('error', 'A role with this code already exists.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            $insQuery = "
                INSERT INTO business_roles (business_id, code, name, description, is_system, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, NOW(6), NOW(6))
            ";
            $stmt = mysqli_prepare($conn, $insQuery);
            mysqli_stmt_bind_param($stmt, 'isss', $businessId, $code, $name, $description);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException('Unable to create the business role.');
            }
            $roleId = mysqli_insert_id($conn);
            replaceRolePermissions($conn, $businessId, $roleId, $selectedPermissions);
            writeAuditLog($conn, $businessId, 'BUSINESS_ROLE_CREATED', 'business_role', $roleId, [
                'code' => $code,
                'name' => $name,
                'permission_ids' => $selectedPermissions
            ]);
            mysqli_commit($conn);
            setFlashMessage('success', 'Custom business role and permissions created successfully.');
            header('Location: ' . roleDetailsRedirect($roleId));
            exit();
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('Business role creation failed: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to create business role.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update_role':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);

        $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $selectedPermissions = validateRolePermissionSelection($conn, $_POST['permissions'] ?? []);

        if ($roleId <= 0 || $code === '' || $name === '') {
            setFlashMessage('error', 'Role code and display name are required.');
            header("Location: index.php" . $role_query);
            exit();
        }
        if ($selectedPermissions === null) {
            setFlashMessage('error', 'One or more selected permissions are invalid.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $roleQuery = "SELECT id, code, name, description, is_system FROM business_roles WHERE id = ? AND business_id = ? LIMIT 1";
        $roleStmt = mysqli_prepare($conn, $roleQuery);
        mysqli_stmt_bind_param($roleStmt, 'ii', $roleId, $businessId);
        mysqli_stmt_execute($roleStmt);
        $roleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($roleStmt));

        if (!$roleRow) {
            setFlashMessage('error', 'Role record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $isOwnerRole = strcasecmp($roleRow['code'], 'OWNER') === 0
            || strcasecmp($roleRow['name'], 'Owner') === 0;
        if (!isSuperAdmin()) {
            if (!isBusinessOwner()) {
                setFlashMessage('error', 'Only the Business Owner or Super Admin can edit business roles.');
                header("Location: index.php" . $role_query);
                exit();
            }
            if ($isOwnerRole || strcasecmp($code, 'OWNER') === 0 || strcasecmp($name, 'Owner') === 0) {
                setFlashMessage('error', 'Only the Super Admin can edit or manage the Owner role.');
                header("Location: index.php" . $role_query);
                exit();
            }
        }

        $duplicateQuery = "SELECT id FROM business_roles WHERE business_id = ? AND code = ? AND id <> ? LIMIT 1";
        $duplicateStmt = mysqli_prepare($conn, $duplicateQuery);
        mysqli_stmt_bind_param($duplicateStmt, 'isi', $businessId, $code, $roleId);
        mysqli_stmt_execute($duplicateStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($duplicateStmt)) > 0) {
            setFlashMessage('error', 'A role with this code already exists.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            $updateQuery = "UPDATE business_roles SET code = ?, name = ?, description = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?";
            $updateStmt = mysqli_prepare($conn, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, 'sssii', $code, $name, $description, $roleId, $businessId);
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new RuntimeException('Unable to update the business role.');
            }
            replaceRolePermissions($conn, $businessId, $roleId, $selectedPermissions);
            writeAuditLog($conn, $businessId, 'BUSINESS_ROLE_UPDATED', 'business_role', (string)$roleId, [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'permission_ids' => $selectedPermissions
            ], $roleRow);
            mysqli_commit($conn);
            setFlashMessage('success', 'Business role and assigned permissions updated successfully.');
            header('Location: ' . roleDetailsRedirect($roleId));
            exit();
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('Business role update failed: ' . $e->getMessage());
            setFlashMessage('error', 'Failed to update the business role.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'delete_role':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['delete']);

        $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        $roleQuery = "SELECT id, code, name, is_system FROM business_roles WHERE id = ? AND business_id = ? LIMIT 1";
        $roleStmt = mysqli_prepare($conn, $roleQuery);
        mysqli_stmt_bind_param($roleStmt, 'ii', $roleId, $businessId);
        mysqli_stmt_execute($roleStmt);
        $roleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($roleStmt));

        if (!$roleRow) {
            setFlashMessage('error', 'Role record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $isProtectedRole = (int)$roleRow['is_system'] === 1
            || strcasecmp($roleRow['code'], 'OWNER') === 0
            || strcasecmp($roleRow['name'], 'Owner') === 0;
        if ($isProtectedRole) {
            setFlashMessage('error', 'The Owner and other system roles cannot be deleted.');
            header("Location: index.php" . $role_query);
            exit();
        }

        if (!isSuperAdmin()) {
            if (!isBusinessOwner()) {
                setFlashMessage('error', 'Only the Business Owner who created this role can delete it.');
                header("Location: index.php" . $role_query);
                exit();
            }

            $creatorQuery = "
                SELECT 1 FROM audit_logs
                WHERE business_id = ? AND actor_user_id = ?
                  AND action = 'BUSINESS_ROLE_CREATED'
                  AND entity_type = 'business_role' AND entity_id = ?
                LIMIT 1
            ";
            $creatorStmt = mysqli_prepare($conn, $creatorQuery);
            $currentUserId = (int)($_SESSION['user_id'] ?? 0);
            $roleEntityId = (string)$roleId;
            mysqli_stmt_bind_param($creatorStmt, 'iis', $businessId, $currentUserId, $roleEntityId);
            mysqli_stmt_execute($creatorStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($creatorStmt)) === 0) {
                setFlashMessage('error', 'You can delete only custom roles that you created.');
                header("Location: index.php" . $role_query);
                exit();
            }
        }

        $deleteQuery = "DELETE FROM business_roles WHERE id = ? AND business_id = ?";
        $deleteStmt = mysqli_prepare($conn, $deleteQuery);
        mysqli_stmt_bind_param($deleteStmt, 'ii', $roleId, $businessId);
        if (mysqli_stmt_execute($deleteStmt)) {
            writeAuditLog($conn, $businessId, 'BUSINESS_ROLE_DELETED', 'business_role', (string)$roleId, [
                'code' => $roleRow['code'],
                'name' => $roleRow['name']
            ]);
            setFlashMessage('success', 'Custom role deleted successfully.');
        } else {
            setFlashMessage('error', 'Failed to delete the custom role.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update_role_permissions':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);

        if (!isSuperAdmin() && !isBusinessOwner()) {
            setFlashMessage('error', 'Only the Business Owner or Super Admin can assign role permissions.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        $selected_perms = validateRolePermissionSelection($conn, $_POST['permissions'] ?? []);

        if (empty($roleId) || $selected_perms === null) {
            setFlashMessage('error', 'Invalid role or permission selection.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate role belongs to this business
        $chkQuery = "SELECT id, code, name FROM business_roles WHERE id = ? AND business_id = ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkQuery);
        mysqli_stmt_bind_param($chkStmt, 'ii', $roleId, $businessId);
        mysqli_stmt_execute($chkStmt);
        $roleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
        if (!$roleRow) {
            setFlashMessage('error', 'Role record not found.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $isOwnerRole = strcasecmp($roleRow['code'], 'OWNER') === 0
            || strcasecmp($roleRow['name'], 'Owner') === 0;
        if (!isSuperAdmin() && $isOwnerRole) {
            setFlashMessage('error', 'Only the Super Admin can edit or manage the Owner role.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            replaceRolePermissions($conn, $businessId, $roleId, $selected_perms);

            writeAuditLog($conn, $businessId, 'BUSINESS_ROLE_PERMISSIONS_UPDATED', 'business_role', $roleId, [
                'role_id' => $roleId,
                'role_name' => $roleRow['name'],
                'granted_permissions_count' => count($selected_perms)
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Role privileges updated successfully.');
            header('Location: ' . roleDetailsRedirect($roleId));
            exit();

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log("Failed to update role permissions: " . $e->getMessage());
            setFlashMessage('error', 'Failed to save role privileges.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'create_override':
        requireSuperAdmin();

        $membershipId = isset($_POST['membership_id']) ? (int)$_POST['membership_id'] : 0;
        $permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;
        $effect = trim($_POST['effect'] ?? 'ALLOW');

        if (empty($membershipId) || empty($permissionId)) {
            setFlashMessage('error', 'Select both employee and permission.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate membership belongs to this business
        $memQuery = "SELECT id FROM business_memberships WHERE id = ? AND business_id = ? LIMIT 1";
        $mStmt = mysqli_prepare($conn, $memQuery);
        mysqli_stmt_bind_param($mStmt, 'ii', $membershipId, $businessId);
        mysqli_stmt_execute($mStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($mStmt)) === 0) {
            setFlashMessage('error', 'Invalid employee selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate permission is business scoped
        $permQuery = "SELECT id, code FROM permissions WHERE id = ? AND scope = 'BUSINESS' LIMIT 1";
        $pStmt = mysqli_prepare($conn, $permQuery);
        mysqli_stmt_bind_param($pStmt, 'i', $permissionId);
        mysqli_stmt_execute($pStmt);
        $pRow = mysqli_fetch_assoc(mysqli_stmt_get_result($pStmt));
        if (!$pRow) {
            setFlashMessage('error', 'Invalid privilege selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Check if override already exists, insert ignore or replace
        $insQuery = "
            INSERT INTO membership_permission_overrides (business_id, membership_id, permission_id, effect, created_at)
            VALUES (?, ?, ?, ?, NOW(6))
            ON DUPLICATE KEY UPDATE effect = VALUES(effect)
        ";
        $stmt = mysqli_prepare($conn, $insQuery);
        mysqli_stmt_bind_param($stmt, 'iiis', $businessId, $membershipId, $permissionId, $effect);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'PERMISSION_OVERRIDE_APPLIED', 'business_membership', $membershipId, [
                'permission_code' => $pRow['code'],
                'effect' => $effect
            ]);
            setFlashMessage('success', 'Direct permission override applied.');
        } else {
            setFlashMessage('error', 'Failed to apply direct permission override.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'delete_override':
        requireSuperAdmin();

        $membershipId = isset($_POST['membership_id']) ? (int)$_POST['membership_id'] : 0;
        $permissionId = isset($_POST['permission_id']) ? (int)$_POST['permission_id'] : 0;

        if (empty($membershipId) || empty($permissionId)) {
            setFlashMessage('error', 'Invalid override identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch override details for audit logging before delete
        $oldQuery = "SELECT o.*, p.code FROM membership_permission_overrides o JOIN permissions p ON o.permission_id = p.id WHERE o.membership_id = ? AND o.permission_id = ? AND o.business_id = ? LIMIT 1";
        $oldStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, 'iii', $membershipId, $permissionId, $businessId);
        mysqli_stmt_execute($oldStmt);
        $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));

        if ($oldRow) {
            $delQuery = "DELETE FROM membership_permission_overrides WHERE membership_id = ? AND permission_id = ? AND business_id = ?";
            $dStmt = mysqli_prepare($conn, $delQuery);
            mysqli_stmt_bind_param($dStmt, 'iii', $membershipId, $permissionId, $businessId);
            if (mysqli_stmt_execute($dStmt)) {
                writeAuditLog($conn, $businessId, 'PERMISSION_OVERRIDE_DELETED', 'business_membership', $oldRow['membership_id'], [
                    'permission_code' => $oldRow['code'],
                    'effect' => $oldRow['effect']
                ]);
                setFlashMessage('success', 'Direct override rule removed.');
            } else {
                setFlashMessage('error', 'Failed to remove override.');
            }
        } else {
            setFlashMessage('error', 'Override rule not found.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
