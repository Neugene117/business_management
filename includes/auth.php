<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Require user to be logged in
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $pos = strpos($script, '/pages/');
        $prefix = ($pos !== false) ? str_repeat('../', substr_count(substr($script, $pos), '/') - 1) : '';
        
        // Prevent redirects loop if already on login
        if (basename($script) !== 'login.php') {
            header("Location: " . $prefix . "login.php");
            exit();
        }
    }
}

/**
 * Determine if active context is SUPER_ADMIN
 */
function isSuperAdmin(): bool {
    return isset($_SESSION['platform_context']) && $_SESSION['platform_context'] === 'SUPER_ADMIN';
}

/**
 * Return the validated role being previewed by the logged-in Super Admin.
 * The query string never changes the authenticated user's real role.
 */
function getPreviewRole(): ?string {
    if (!isSuperAdmin()) {
        return null;
    }

    $role = strtolower(trim((string)($_GET['role'] ?? '')));
    $allowed = ['super_admin', 'owner', 'employee'];

    return in_array($role, $allowed, true) ? $role : null;
}

function isRolePreviewActive(): bool {
    return getPreviewRole() !== null;
}

/**
 * Determine which role should be rendered and evaluated for read-only preview.
 */
function getEffectiveUserRole(): string {
    $previewRole = getPreviewRole();
    if ($previewRole !== null) {
        return $previewRole;
    }

    if (isSuperAdmin()) {
        return 'super_admin';
    }

    $roles = array_map('strtolower', $_SESSION['roles'] ?? []);
    if (in_array('employee', $roles, true) || ($_SESSION['member_type'] ?? '') !== 'OWNER') {
        return 'employee';
    }

    return 'owner';
}

function isEffectiveSuperAdmin(): bool {
    return getEffectiveUserRole() === 'super_admin';
}

function isBusinessOwner(): bool {
    return !isSuperAdmin()
        && (($_SESSION['member_type'] ?? '') === 'OWNER'
            || in_array('owner', array_map('strtolower', $_SESSION['roles'] ?? []), true));
}

function getRolePreviewQuery(): string {
    $previewRole = getPreviewRole();
    return $previewRole === null ? '' : '?role=' . rawurlencode($previewRole);
}

function getRolePreviewUrl(string $role): string {
    if (!isSuperAdmin() || !in_array($role, ['super_admin', 'owner', 'employee'], true)) {
        return '#';
    }

    $query = $_GET;
    $query['role'] = $role;
    return '?' . http_build_query($query);
}
?>
