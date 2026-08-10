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
 * Validate and store a company logo, returning a safe project-relative path.
 * An absent upload is valid and returns a null path.
 */
function storeCompanyLogoUpload(?array $file, int $businessId): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'uploaded' => false, 'path' => null, 'error' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'The company logo upload did not complete successfully.'];
    }

    $temporaryPath = (string)($file['tmp_name'] ?? '');
    $fileSize = (int)($file['size'] ?? 0);
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'The uploaded company logo is invalid.'];
    }
    if ($fileSize <= 0 || $fileSize > 3 * 1024 * 1024) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'The company logo must be a JPG, PNG, or WEBP image no larger than 3 MB.'];
    }

    $imageInfo = @getimagesize($temporaryPath);
    $mimeDetector = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $mimeDetector->file($temporaryPath);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    if ($imageInfo === false || !isset($allowedTypes[$mimeType])) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'The company logo must be a valid JPG, PNG, or WEBP image.'];
    }

    $logoDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'company';
    if (!is_dir($logoDirectory) && !mkdir($logoDirectory, 0755, true) && !is_dir($logoDirectory)) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'The company logo storage directory is unavailable.'];
    }

    $extension = $allowedTypes[$mimeType];
    try {
        $randomSuffix = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'A secure company logo filename could not be generated.'];
    }
    $fileName = 'business_' . $businessId . '_' . $randomSuffix . '.' . $extension;
    $destination = $logoDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        return ['ok' => false, 'uploaded' => false, 'path' => null, 'error' => 'The company logo could not be saved.'];
    }

    return [
        'ok' => true,
        'uploaded' => true,
        'path' => 'src/images/company/' . $fileName,
        'error' => null
    ];
}

/**
 * Resolve only application-managed company logo paths for safe rendering.
 */
function getCompanyLogoUrl(?string $logoPath, string $rootPrefix = ''): ?string {
    $logoPath = trim((string)$logoPath);
    if (!preg_match('#^src/images/company/business_[0-9]+_[a-f0-9]{24}\.(?:jpg|png|webp)$#', $logoPath)) {
        return null;
    }
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoPath);
    return is_file($absolutePath) ? $rootPrefix . $logoPath : null;
}

/**
 * Delete only a previously managed company logo file.
 */
function deleteCompanyLogoFile(?string $logoPath): void {
    $logoPath = trim((string)$logoPath);
    if (!preg_match('#^src/images/company/business_[0-9]+_[a-f0-9]{24}\.(?:jpg|png|webp)$#', $logoPath)) {
        return;
    }

    $logoDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'company';
    $absolutePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logoPath);
    $resolvedDirectory = realpath($logoDirectory);
    $resolvedFile = realpath($absolutePath);
    if ($resolvedDirectory !== false && $resolvedFile !== false
        && str_starts_with($resolvedFile, $resolvedDirectory . DIRECTORY_SEPARATOR)
        && is_file($resolvedFile)) {
        unlink($resolvedFile);
    }
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
