<?php
require_once __DIR__ . '/auth.php';
/**
 * Check if the user has a specific permission
 */
function hasPermission($conn, $membershipId, $businessId, $permissionCode): bool {
    // A preview is a read-only simulation and must be evaluated before the
    // authenticated Super Admin bypass.
    $previewRole = getPreviewRole();
    if ($previewRole !== null) {
        if ($previewRole === 'super_admin') {
            return true;
        }

        if ($previewRole === 'owner') {
            $scopeQuery = "SELECT 1 FROM permissions WHERE code = ? AND scope = 'BUSINESS' LIMIT 1";
            $scopeStmt = mysqli_prepare($conn, $scopeQuery);
            mysqli_stmt_bind_param($scopeStmt, 's', $permissionCode);
            mysqli_stmt_execute($scopeStmt);
            return mysqli_num_rows(mysqli_stmt_get_result($scopeStmt)) > 0;
        }

        $employeePermissions = [
            'dashboard.view',
            'sales.view',
            'sales.create',
            'purchases.view',
            'purchases.create',
            'inventory.view',
            'employees.view',
            'settings.view'
        ];
        return in_array($permissionCode, $employeePermissions, true);
    }

    // 1. Super Admin bypasses all checks
    if (isSuperAdmin()) {
        return true;
    }

    if (empty($membershipId) || empty($businessId)) {
        return false;
    }

    // 2. Check explicit DENY in overrides
    $denyQuery = "
        SELECT 1 FROM membership_permission_overrides o
        JOIN permissions p ON o.permission_id = p.id
        WHERE o.membership_id = ? AND o.business_id = ? AND p.code = ? AND o.effect = 'DENY'
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $denyQuery);
    mysqli_stmt_bind_param($stmt, 'iis', $membershipId, $businessId, $permissionCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        return false; // Explicitly denied
    }

    // 3. Check explicit ALLOW in overrides
    $allowQuery = "
        SELECT 1 FROM membership_permission_overrides o
        JOIN permissions p ON o.permission_id = p.id
        WHERE o.membership_id = ? AND o.business_id = ? AND p.code = ? AND o.effect = 'ALLOW'
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $allowQuery);
    mysqli_stmt_bind_param($stmt, 'iis', $membershipId, $businessId, $permissionCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        return true; // Explicitly allowed
    }

    // 4. Check role-assigned permissions (via view v_employee_effective_role_permissions)
    $roleQuery = "
        SELECT 1 FROM v_employee_effective_role_permissions
        WHERE membership_id = ? AND business_id = ? AND permission_code = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $roleQuery);
    mysqli_stmt_bind_param($stmt, 'iis', $membershipId, $businessId, $permissionCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        return true;
    }

    return false; // Default DENY
}

/**
 * Enforce that the user has a specific permission, otherwise abort access
 */
function requirePermission($conn, $membershipId, $businessId, $permissionCode): void {
    // Preview mode can show permitted UI and pages but can never mutate data.
    if (isRolePreviewActive() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(403);
        require_once __DIR__ . '/flash.php';
        setFlashMessage('error', 'Role preview is read-only. No changes were made.');
        header('Location: ' . getRootPrefix() . 'pages/dashboard/index.php' . getRolePreviewQuery());
        exit('Access denied. Role preview is read-only.');
    }

    if (!hasPermission($conn, $membershipId, $businessId, $permissionCode)) {
        http_response_code(403);
        
        // Log access violation attempts
        require_once __DIR__ . '/audit.php';
        writeAuditLog($conn, $businessId, 'UNAUTHORIZED_ACCESS_ATTEMPT', 'security', $permissionCode, [
            'permission_requested' => $permissionCode,
            'membership_id' => $membershipId
        ]);
        
        require_once __DIR__ . '/flash.php';
        setFlashMessage('error', 'Access denied. You do not have permission to perform this action.');
        
        // Redirect to dashboard or login
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $pos = strpos($script, '/pages/');
        $prefix = ($pos !== false) ? str_repeat('../', substr_count(substr($script, $pos), '/') - 1) : '';
        
        header("Location: " . $prefix . "pages/dashboard/index.php");
        exit('Access denied. Missing permission: ' . $permissionCode);
    }
}

/**
 * Enforce operations that are reserved for the authenticated Super Admin.
 * A preview never grants real administrative authority.
 */
function requireSuperAdmin(): void {
    if (!isSuperAdmin() || isRolePreviewActive()) {
        http_response_code(403);
        require_once __DIR__ . '/flash.php';
        setFlashMessage('error', 'Only the Super Admin can perform this action.');
        header('Location: ' . getRootPrefix() . 'pages/dashboard/index.php' . getRolePreviewQuery());
        exit('Access denied. Super Admin permission required.');
    }
}
?>
