<?php
/**
 * Secure HTML escaping wrapper
 */
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Format currency using dynamic currency code and DECIMALS
 */
function formatCurrency($amount, $currency = 'RWF') {
    $amount = (float)$amount;
    if ($currency === 'USD') {
        return '$' . number_format($amount, 2);
    } elseif ($currency === 'EUR') {
        return '€' . number_format($amount, 2);
    }
    return number_format($amount, 0) . ' ' . $currency;
}

/**
 * Timezone-aware date formatter
 */
function formatDate($datetime, $timezone = 'Africa/Kigali', $format = 'Y-m-d H:i') {
    if (empty($datetime)) {
        return '';
    }
    try {
        $date = new DateTime($datetime, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Check if the active page matches the link page to apply active style class
 */
function isPageActive($pageTitle, $targetTitle) {
    return (strcasecmp((string)$pageTitle, (string)$targetTitle) === 0) ? ' active' : '';
}

/**
 * Store a notification for one explicit user.
 */
function createUserNotification($conn, int $userId, ?int $businessId, string $title, ?string $message = null, string $type = 'INFO', ?string $linkUrl = null): bool {
    if ($userId <= 0 || trim($title) === '') {
        return false;
    }

    $type = strtoupper($type);
    if (!in_array($type, ['INFO', 'SUCCESS', 'WARNING', 'DANGER'], true)) {
        $type = 'INFO';
    }

    $query = "
        INSERT INTO notifications (user_id, business_id, title, message, type, link_url, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(6))
    ";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iissss', $userId, $businessId, $title, $message, $type, $linkUrl);
    return mysqli_stmt_execute($stmt);
}

/**
 * Resolve a business membership to its user without accepting a client user id.
 */
function createMembershipNotification($conn, int $membershipId, int $businessId, string $title, ?string $message = null, string $type = 'INFO', ?string $linkUrl = null): bool {
    $query = "SELECT user_id FROM business_memberships WHERE id = ? AND business_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $membershipId, $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row
        ? createUserNotification($conn, (int)$row['user_id'], $businessId, $title, $message, $type, $linkUrl)
        : false;
}

/**
 * Notify active owners of one business, optionally excluding the acting user.
 */
function notifyBusinessOwners($conn, int $businessId, string $title, ?string $message = null, string $type = 'INFO', ?string $linkUrl = null, ?int $excludeUserId = null): void {
    $query = "
        SELECT DISTINCT user_id
        FROM business_memberships
        WHERE business_id = ? AND member_type = 'OWNER' AND status = 'ACTIVE'
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $userId = (int)$row['user_id'];
        if ($excludeUserId !== null && $userId === $excludeUserId) {
            continue;
        }
        createUserNotification($conn, $userId, $businessId, $title, $message, $type, $linkUrl);
    }
}
?>
