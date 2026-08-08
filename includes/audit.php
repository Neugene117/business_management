<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
}

/**
 * Write a transaction-safe audit log entry
 */
function writeAuditLog($conn, $businessId, string $action, string $entityType, ?string $entityId, ?array $newValues = null, ?array $oldValues = null): bool {
    $userId = $_SESSION['user_id'] ?? null;
    $membershipId = $_SESSION['membership_id'] ?? null;
    
    // Generate UUID for request tracking if not already set in this execution
    static $requestId = null;
    if ($requestId === null) {
        $requestId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ipBinary = inet_pton($ip);
    if ($ipBinary === false) {
        $ipBinary = null;
    }
    
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    
    $newJson = $newValues ? json_encode($newValues) : null;
    $oldJson = $oldValues ? json_encode($oldValues) : null;
    
    $query = "
        INSERT INTO audit_logs (
            business_id, actor_user_id, actor_membership_id, action, 
            entity_type, entity_id, old_values, new_values, 
            request_id, ip_address, user_agent, created_at
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, 
            ?, ?, ?, NOW(6)
        )
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Failed to prepare audit log insert: " . mysqli_error($conn));
        return false;
    }
    
    mysqli_stmt_bind_param(
        $stmt,
        'iiissssssbs',
        $businessId,
        $userId,
        $membershipId,
        $action,
        $entityType,
        $entityId,
        $oldJson,
        $newJson,
        $requestId,
        $ipBinary,
        $userAgent
    );
    
    // Special send_long_data binding for blobs/varbinary
    if ($ipBinary !== null) {
        mysqli_stmt_send_long_data($stmt, 9, $ipBinary);
    }
    
    return mysqli_stmt_execute($stmt);
}
?>
