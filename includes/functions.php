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
 * Normalize an optional text field for nullable database columns.
 *
 * HTML forms submit blank inputs as empty strings. Converting those values to
 * NULL keeps optional unique columns (such as a business registration number)
 * from treating every blank input as the same duplicate value.
 */
function normalizeOptionalText($value): ?string {
    $value = trim((string)$value);
    return $value === '' ? null : $value;
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

/**
 * Calculate the next UTC execution time for a business report schedule.
 */
function calculateReportNextRun(string $frequency, string $sendTime, ?int $weekday, ?int $dayOfMonth, string $timezone, ?DateTimeImmutable $from = null): string {
    try {
        $zone = new DateTimeZone($timezone);
    } catch (Throwable $error) {
        $zone = new DateTimeZone('Africa/Johannesburg');
    }
    $now = $from ? $from->setTimezone($zone) : new DateTimeImmutable('now', $zone);
    [$hour, $minute] = array_map('intval', explode(':', substr($sendTime, 0, 5)));
    $candidate = $now->setTime($hour, $minute, 0);

    if ($frequency === 'DAILY') {
        if ($candidate <= $now) $candidate = $candidate->modify('+1 day');
    } elseif ($frequency === 'WEEKLY') {
        $targetWeekday = max(1, min(7, (int)$weekday));
        $candidate = $candidate->modify('+' . (($targetWeekday - (int)$now->format('N') + 7) % 7) . ' days');
        if ($candidate <= $now) $candidate = $candidate->modify('+7 days');
    } elseif ($frequency === 'MONTHLY') {
        $targetDay = max(1, min(31, (int)$dayOfMonth));
        $candidate = $now->setDate((int)$now->format('Y'), (int)$now->format('m'), min($targetDay, (int)$now->format('t')))->setTime($hour, $minute, 0);
        if ($candidate <= $now) {
            $nextMonth = $now->modify('first day of next month');
            $candidate = $nextMonth->setDate((int)$nextMonth->format('Y'), (int)$nextMonth->format('m'), min($targetDay, (int)$nextMonth->format('t')))->setTime($hour, $minute, 0);
        }
    } else {
        throw new InvalidArgumentException('Unsupported report frequency.');
    }
    return $candidate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
}

/**
 * Encrypt a delivery credential before it is persisted.
 */
function encryptReportCredential(string $plainText): string {
    $key = hash('sha256', REPORT_CONFIG_KEY, true);
    $iv = random_bytes(12);
    $tag = '';
    $cipherText = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipherText === false) {
        throw new RuntimeException('Unable to encrypt the API credential.');
    }
    return base64_encode($iv . $tag . $cipherText);
}

/**
 * Decrypt a report-delivery credential for the background worker only.
 */
function decryptReportCredential(?string $encrypted): string {
    if (empty($encrypted)) {
        return '';
    }
    $payload = base64_decode($encrypted, true);
    if ($payload === false || strlen($payload) < 29) {
        throw new RuntimeException('The stored API credential is invalid.');
    }
    $iv = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $cipherText = substr($payload, 28);
    $plainText = openssl_decrypt($cipherText, 'aes-256-gcm', hash('sha256', REPORT_CONFIG_KEY, true), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plainText === false) {
        throw new RuntimeException('Unable to decrypt the stored API credential.');
    }
    return $plainText;
}

/**
 * Keep the product's displayed purchase cost aligned with the weighted value
 * of the stock that is currently on hand across all business locations.
 */
function syncProductWeightedAverageCost($conn, int $businessId, int $productId): float {
    $query = "
        SELECT
            COALESCE(SUM(CASE WHEN quantity_on_hand > 0 THEN quantity_on_hand * average_unit_cost ELSE 0 END), 0) AS stock_value,
            COALESCE(SUM(CASE WHEN quantity_on_hand > 0 THEN quantity_on_hand ELSE 0 END), 0) AS stock_quantity
        FROM inventory_balances
        WHERE business_id = ? AND product_id = ?
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $productId);
    mysqli_stmt_execute($stmt);
    $totals = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $quantity = (float)($totals['stock_quantity'] ?? 0);
    if ($quantity <= 0) {
        $current = mysqli_prepare($conn, 'SELECT cost_price FROM products WHERE id = ? AND business_id = ? LIMIT 1');
        mysqli_stmt_bind_param($current, 'ii', $productId, $businessId);
        mysqli_stmt_execute($current);
        return (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($current))['cost_price'] ?? 0.0);
    }
    $average = (float)$totals['stock_value'] / $quantity;
    $update = mysqli_prepare($conn, 'UPDATE products SET cost_price = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?');
    mysqli_stmt_bind_param($update, 'dii', $average, $productId, $businessId);
    mysqli_stmt_execute($update);

    return $average;
}

function getBusinessInventoryConfig($conn, int $businessId): array {
    $query = "
        SELECT b.timezone, a.inventory_valuation_method, a.default_tax_rate, a.allow_negative_stock
        FROM businesses b
        LEFT JOIN business_accounting_settings a ON a.business_id = b.id
        WHERE b.id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$row) {
        throw new RuntimeException('Business inventory settings are unavailable.');
    }
    return [
        'timezone' => $row['timezone'] ?: 'Africa/Kigali',
        'inventory_valuation_method' => $row['inventory_valuation_method'] ?: 'WEIGHTED_AVERAGE',
        'default_tax_rate' => (float)($row['default_tax_rate'] ?? 0),
        'allow_negative_stock' => (int)($row['allow_negative_stock'] ?? 0)
    ];
}

/**
 * Return the one tax currently enabled for a business.
 *
 * Tax values are stored as human-facing values: 18 means 18 percent and a
 * fixed tax value is stored in the business currency.
 */
function getActiveBusinessTax($conn, int $businessId, bool $lockForShare = false): ?array {
    $query = "SELECT id,name,tax_type,tax_value FROM taxes WHERE business_id=? AND is_active=1 ORDER BY id DESC LIMIT 1";
    if ($lockForShare) {
        $query .= ' LOCK IN SHARE MODE';
    }
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new RuntimeException('Tax settings are unavailable. Apply the latest database migration.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $tax = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$tax) {
        return null;
    }
    return [
        'id' => (int)$tax['id'],
        'name' => (string)$tax['name'],
        'tax_type' => (string)$tax['tax_type'],
        'tax_value' => (float)$tax['tax_value']
    ];
}

/** Calculate a sale-level tax from the active tax snapshot. */
function calculateSaleTax(float $subtotal, ?array $tax): float {
    if ($tax === null) {
        return 0.0;
    }
    $value = max(0.0, (float)($tax['tax_value'] ?? 0));
    if (($tax['tax_type'] ?? '') === 'PERCENTAGE') {
        return round(max(0.0, $subtotal) * ($value / 100), 4);
    }
    return ($tax['tax_type'] ?? '') === 'FIXED' ? round($value, 4) : 0.0;
}

/**
 * Allocate a sale-level tax to item rows so refunds retain their proportional
 * tax value. The last item receives the rounding remainder.
 */
function allocateSaleTax(array $lineAmounts, float $totalTax): array {
    if (!$lineAmounts) {
        return [];
    }
    $totalTax = round(max(0.0, $totalTax), 4);
    $subtotal = array_sum(array_map('floatval', $lineAmounts));
    $allocations = [];
    $allocated = 0.0;
    $lastIndex = array_key_last($lineAmounts);
    foreach ($lineAmounts as $index => $lineAmount) {
        if ($index === $lastIndex) {
            $lineTax = round($totalTax - $allocated, 4);
        } elseif ($subtotal > 0) {
            $lineTax = round($totalTax * ((float)$lineAmount / $subtotal), 4);
            $allocated += $lineTax;
        } else {
            $lineTax = 0.0;
        }
        $allocations[$index] = max(0.0, $lineTax);
    }
    return $allocations;
}

/** Remove .php from navigable links and form targets in rendered HTML. */
function cleanPageUrlsInHtml(string $html): string {
    return preg_replace_callback(
        '/\b(href|action)=(\"|\')([^\"\']+)\2/i',
        static function (array $match): string {
            $url = preg_replace('/\.(?:php|html)(?=([?#]|$))/i', '', $match[3]);
            return $match[1] . '=' . $match[2] . $url . $match[2];
        },
        $html
    ) ?? $html;
}

function businessLocalDateTimeToUtc(string $value, string $timezone): string {
    $value = trim($value);
    if ($value === '') {
        throw new InvalidArgumentException('A valid date and time is required.');
    }
    try {
        $local = new DateTimeImmutable($value, new DateTimeZone($timezone));
    } catch (Throwable $error) {
        throw new InvalidArgumentException('A valid date and time is required.');
    }
    return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
}

function getBusinessPeriodBounds(string $fromDate, string $toDate, string $timezone): array {
    $validDate = static function (string $value): bool {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    };
    if (!$validDate($fromDate) || !$validDate($toDate) || $toDate < $fromDate) {
        throw new InvalidArgumentException('Select a valid date range.');
    }
    $zone = new DateTimeZone($timezone);
    // Business reporting days always open at 12:00 AM and close at 12:00 AM
    // on the following day. No user-supplied time can alter these boundaries.
    $startLocal = new DateTimeImmutable($fromDate . ' 00:00:00', $zone);
    $endLocal = (new DateTimeImmutable($toDate . ' 00:00:00', $zone))->modify('+1 day');
    return [
        'start_utc' => $startLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        'end_utc' => $endLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        'today_local' => (new DateTimeImmutable('now', $zone))->format('Y-m-d')
    ];
}

/** Build a short, URL-safe batch prefix from a company's registered name. */
function getCompanyBatchPrefix(string $companyName): string {
    $companyName = trim($companyName);
    if (function_exists('iconv')) {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $companyName);
        if ($transliterated !== false && $transliterated !== '') {
            $companyName = $transliterated;
        }
    }
    $prefix = strtoupper((string)preg_replace('/[^A-Z0-9]+/i', '', $companyName));
    // A fixed alphabetic fallback guarantees that every generated batch has
    // both letters and numbers, even when the company name is numeric/symbols.
    if ($prefix === '' || !preg_match('/[A-Z]/', $prefix)) {
        $prefix = 'COMPANY' . $prefix;
    }
    return substr($prefix, 0, 18);
}

/**
 * Generate a company-derived, company-wide unique batch number.
 * Example: KIGALIRETAILHUB-BAT-20260812124530-A1F93C
 */
function generateUniqueCompanyBatchNumber($conn, int $businessId, string $companyName): string {
    $prefix = getCompanyBatchPrefix($companyName);
    $checkStmt = mysqli_prepare($conn, 'SELECT 1 FROM product_batches WHERE business_id=? AND lot_number=? LIMIT 1');
    if (!$checkStmt) {
        throw new RuntimeException('Batch number uniqueness could not be verified.');
    }
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $randomPart = strtoupper(bin2hex(random_bytes(4)));
        $candidate = $prefix . '-BAT-' . gmdate('YmdHis') . '-' . $randomPart;
        mysqli_stmt_bind_param($checkStmt, 'is', $businessId, $candidate);
        mysqli_stmt_execute($checkStmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt))) {
            return $candidate;
        }
    }
    throw new RuntimeException('A unique batch number could not be generated. Please try again.');
}

function createIdempotencyToken(): string {
    return bin2hex(random_bytes(24));
}

function claimIdempotencyKey($conn, int $businessId, string $key, string $operation, array $requestData): void {
    if (!preg_match('/^[a-f0-9]{48}$/', $key)) {
        throw new InvalidArgumentException('The request token is invalid. Refresh the page and try again.');
    }
    $requestHash = hash('sha256', json_encode($requestData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $query = "INSERT INTO idempotency_keys (business_id,idempotency_key,operation,request_hash,expires_at,created_at) VALUES (?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 1 DAY),UTC_TIMESTAMP(6))";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'isss', $businessId, $key, $operation, $requestHash);
    try {
        $executed = mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1062) {
            throw new RuntimeException('This request was already processed. No duplicate transaction was created.');
        }
        throw new RuntimeException('The duplicate-request protection could not be applied.', 0, $error);
    }
    if (!$executed) {
        if (mysqli_stmt_errno($stmt) === 1062) throw new RuntimeException('This request was already processed. No duplicate transaction was created.');
        throw new RuntimeException('The duplicate-request protection could not be applied.');
    }
}

function completeIdempotencyKey($conn, int $businessId, string $key, int $responseCode, array $response): void {
    $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = mysqli_prepare($conn, 'UPDATE idempotency_keys SET response_code = ?, response_body = ? WHERE business_id = ? AND idempotency_key = ?');
    mysqli_stmt_bind_param($stmt, 'isis', $responseCode, $responseJson, $businessId, $key);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('The transaction result could not be finalized.');
    }
}

/** Normalize values stored in DECIMAL(18,4) inventory columns. */
function normalizeInventoryDecimal($value, string $label = 'Quantity'): float {
    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' must be a valid number.');
    }
    $normalized = round((float)$value, 4);
    if (!is_finite($normalized)) {
        throw new InvalidArgumentException($label . ' must be a finite number.');
    }
    return abs($normalized) < 0.00005 ? 0.0 : $normalized;
}

/** Resolve a submitted transaction UOM from trusted product and tenant data. */
function resolveProductTransactionUom($conn, int $businessId, array $product, int $selectedUomId): array {
    $baseUomId = (int)($product['uom_id'] ?? 0);
    $packageUomId = !empty($product['package_uom_id']) ? (int)$product['package_uom_id'] : null;
    if ($selectedUomId <= 0 || ($selectedUomId !== $baseUomId && $selectedUomId !== $packageUomId)) {
        throw new InvalidArgumentException('Select a valid transaction unit for ' . ($product['name'] ?? 'this product') . '.');
    }

    $unitStmt = mysqli_prepare($conn, 'SELECT id,code,name,symbol FROM units_of_measure WHERE id=? AND (business_id IS NULL OR business_id=?) LIMIT 1');
    mysqli_stmt_bind_param($unitStmt, 'ii', $selectedUomId, $businessId);
    mysqli_stmt_execute($unitStmt);
    $unit = mysqli_fetch_assoc(mysqli_stmt_get_result($unitStmt));
    if (!$unit) {
        throw new InvalidArgumentException('The selected transaction unit is not available to this business.');
    }

    $isPackage = $packageUomId !== null && $selectedUomId === $packageUomId;
    $factor = $isPackage ? normalizeInventoryDecimal($product['units_per_package'] ?? 0, 'Units per package') : 1.0;
    if ($factor <= 0) {
        throw new InvalidArgumentException('The product package conversion is invalid.');
    }
    return [
        'id' => $selectedUomId,
        'code' => (string)$unit['code'],
        'name' => (string)$unit['name'],
        'factor' => $factor,
        'is_package' => $isPackage,
    ];
}

function convertTransactionQuantityToBase($quantity, $conversionFactor): float {
    $quantity = normalizeInventoryDecimal($quantity, 'Quantity');
    $factor = normalizeInventoryDecimal($conversionFactor, 'Conversion factor');
    if ($quantity <= 0 || $factor <= 0) {
        throw new InvalidArgumentException('Quantity and conversion factor must be greater than zero.');
    }
    $baseQuantity = normalizeInventoryDecimal($quantity * $factor, 'Base quantity');
    if ($baseQuantity <= 0) {
        throw new InvalidArgumentException('The converted base quantity is too small.');
    }
    return $baseQuantity;
}

function convertTransactionUnitPriceToBase($unitPrice, $conversionFactor, string $label = 'Unit price'): float {
    $price = normalizeInventoryDecimal($unitPrice, $label);
    $factor = normalizeInventoryDecimal($conversionFactor, 'Conversion factor');
    if ($price < 0 || $factor <= 0) {
        throw new InvalidArgumentException($label . ' cannot be negative and the conversion factor must be greater than zero.');
    }
    return normalizeInventoryDecimal($price / $factor, 'Base ' . strtolower($label));
}

function convertBaseQuantityToTransaction($baseQuantity, $conversionFactor): float {
    $base = normalizeInventoryDecimal($baseQuantity, 'Base quantity');
    $factor = normalizeInventoryDecimal($conversionFactor, 'Conversion factor');
    if ($factor <= 0) throw new InvalidArgumentException('Conversion factor must be greater than zero.');
    return normalizeInventoryDecimal($base / $factor, 'Transaction quantity');
}

function formatInventoryDecimal($quantity): string {
    $quantity = normalizeInventoryDecimal($quantity);
    if (abs($quantity - round($quantity)) < 0.00005) return number_format($quantity, 0, '.', '');
    return number_format($quantity, 4, '.', '');
}

/** Format canonical base stock as full packages plus a base-unit remainder. */
function formatBaseInventoryAsPackages($baseQuantity, string $baseUomCode, ?string $packageUomCode = null, $unitsPerPackage = null): string {
    $base = normalizeInventoryDecimal($baseQuantity, 'Stock quantity');
    $factor = $unitsPerPackage === null ? 0.0 : normalizeInventoryDecimal($unitsPerPackage, 'Units per package');
    if ($packageUomCode === null || $packageUomCode === '' || $factor <= 0) {
        return formatInventoryDecimal($base) . ' ' . $baseUomCode;
    }

    $sign = $base < 0 ? '-' : '';
    $absolute = abs($base);
    $packages = (int)floor(($absolute + 0.0000001) / $factor);
    $remainder = normalizeInventoryDecimal($absolute - ($packages * $factor), 'Stock remainder');
    $parts = [];
    if ($packages > 0) $parts[] = $sign . $packages . ' ' . $packageUomCode;
    if ($remainder > 0 || !$parts) $parts[] = ($parts ? '' : $sign) . formatInventoryDecimal($remainder) . ' ' . $baseUomCode;
    return implode($base < 0 ? ' - ' : ' + ', $parts);
}

function lockInventoryBalance($conn, int $businessId, int $locationId, int $productId): array {
    $insert = mysqli_prepare($conn, 'INSERT IGNORE INTO inventory_balances (business_id,location_id,product_id,quantity_on_hand,reserved_quantity,average_unit_cost,updated_at) VALUES (?,?,?,0,0,0,UTC_TIMESTAMP(6))');
    mysqli_stmt_bind_param($insert, 'iii', $businessId, $locationId, $productId);
    if (!mysqli_stmt_execute($insert)) {
        throw new RuntimeException('Inventory balance could not be initialized.');
    }
    $query = 'SELECT quantity_on_hand,reserved_quantity,available_quantity,average_unit_cost FROM inventory_balances WHERE business_id=? AND location_id=? AND product_id=? FOR UPDATE';
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'iii', $businessId, $locationId, $productId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$row) {
        throw new RuntimeException('Inventory balance could not be locked.');
    }
    return $row;
}

function consumeFifoCost($conn, int $businessId, int $locationId, int $productId, float $quantity, ?int $batchId = null): array {
    $sql = "SELECT id,remaining_quantity,unit_cost FROM inventory_cost_layers WHERE business_id=? AND location_id=? AND product_id=? AND remaining_quantity>0";
    $types = 'iii';
    $params = [$businessId, $locationId, $productId];
    if ($batchId !== null) {
        $sql .= ' AND batch_id=?';
        $types .= 'i';
        $params[] = $batchId;
    }
    $sql .= ' ORDER BY received_at,id FOR UPDATE';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $remaining = $quantity;
    $totalCost = 0.0;
    $allocations = [];
    while ($remaining > 0.0000001 && ($layer = mysqli_fetch_assoc($result))) {
        $used = min($remaining, (float)$layer['remaining_quantity']);
        if ($used <= 0) continue;
        $newRemaining = (float)$layer['remaining_quantity'] - $used;
        $update = mysqli_prepare($conn, 'UPDATE inventory_cost_layers SET remaining_quantity=? WHERE id=? AND business_id=?');
        mysqli_stmt_bind_param($update, 'dii', $newRemaining, $layer['id'], $businessId);
        if (!mysqli_stmt_execute($update)) throw new RuntimeException('FIFO cost layer could not be updated.');
        $totalCost += $used * (float)$layer['unit_cost'];
        $allocations[] = ['layer_id'=>(int)$layer['id'],'quantity'=>$used,'unit_cost'=>(float)$layer['unit_cost']];
        $remaining -= $used;
    }
    if ($remaining > 0.0001) {
        throw new RuntimeException('FIFO cost layers do not cover the requested stock quantity.');
    }
    return ['total_cost'=>$totalCost,'unit_cost'=>$quantity > 0 ? $totalCost / $quantity : 0.0,'allocations'=>$allocations];
}

function applyInventoryMovement($conn, array $movement): array {
    $businessId = (int)$movement['business_id'];
    $locationId = (int)$movement['location_id'];
    $productId = (int)$movement['product_id'];
    $batchId = !empty($movement['batch_id']) ? (int)$movement['batch_id'] : null;
    $quantityDelta = (float)$movement['quantity_delta'];
    $unitCost = max(0.0, (float)$movement['unit_cost']);
    if ($quantityDelta == 0.0) throw new InvalidArgumentException('Inventory movement quantity cannot be zero.');

    $productStmt = mysqli_prepare($conn, 'SELECT track_batches FROM products WHERE id=? AND business_id=? LIMIT 1');
    mysqli_stmt_bind_param($productStmt, 'ii', $productId, $businessId);
    mysqli_stmt_execute($productStmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($productStmt));
    if (!$product) throw new RuntimeException('The inventory product is invalid.');
    if ((int)$product['track_batches'] === 1 && $batchId === null) throw new RuntimeException('A batch/lot is required for this product.');

    if ($batchId !== null) {
        $batchStmt = mysqli_prepare($conn, 'SELECT id FROM product_batches WHERE id=? AND business_id=? AND product_id=? LIMIT 1');
        mysqli_stmt_bind_param($batchStmt, 'iii', $batchId, $businessId, $productId);
        mysqli_stmt_execute($batchStmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($batchStmt))) throw new RuntimeException('The selected batch does not belong to this product.');
    }

    $config = $movement['config'] ?? getBusinessInventoryConfig($conn, $businessId);
    $balance = lockInventoryBalance($conn, $businessId, $locationId, $productId);
    $newQuantity = (float)$balance['quantity_on_hand'] + $quantityDelta;
    if ($quantityDelta < 0 && !(int)$config['allow_negative_stock'] && (float)$balance['available_quantity'] < abs($quantityDelta)) {
        throw new RuntimeException('Insufficient available stock. Requested: ' . number_format(abs($quantityDelta), 4, '.', '') . '; Available: ' . number_format((float)$balance['available_quantity'], 4, '.', ''));
    }
    $newAverage = (float)$balance['average_unit_cost'];
    if (($config['inventory_valuation_method'] ?? 'WEIGHTED_AVERAGE') === 'WEIGHTED_AVERAGE' && $newQuantity > 0) {
        $newAverage = (((float)$balance['quantity_on_hand'] * (float)$balance['average_unit_cost']) + ($quantityDelta * $unitCost)) / $newQuantity;
        $newAverage = max(0.0, $newAverage);
    } elseif ($newQuantity == 0.0) {
        $newAverage = 0.0;
    }
    $update = mysqli_prepare($conn, 'UPDATE inventory_balances SET quantity_on_hand=?,average_unit_cost=?,updated_at=UTC_TIMESTAMP(6) WHERE business_id=? AND location_id=? AND product_id=?');
    mysqli_stmt_bind_param($update, 'ddiii', $newQuantity, $newAverage, $businessId, $locationId, $productId);
    if (!mysqli_stmt_execute($update)) throw new RuntimeException('Inventory balance could not be updated.');

    if ($batchId !== null) {
        $batchInsert = mysqli_prepare($conn, 'INSERT IGNORE INTO batch_inventory_balances (business_id,location_id,batch_id,quantity_on_hand,reserved_quantity,updated_at) VALUES (?,?,?,0,0,UTC_TIMESTAMP(6))');
        mysqli_stmt_bind_param($batchInsert, 'iii', $businessId, $locationId, $batchId);
        mysqli_stmt_execute($batchInsert);
        $batchLock = mysqli_prepare($conn, 'SELECT quantity_on_hand,available_quantity FROM batch_inventory_balances WHERE business_id=? AND location_id=? AND batch_id=? FOR UPDATE');
        mysqli_stmt_bind_param($batchLock, 'iii', $businessId, $locationId, $batchId);
        mysqli_stmt_execute($batchLock);
        $batchBalance = mysqli_fetch_assoc(mysqli_stmt_get_result($batchLock));
        if ($quantityDelta < 0 && !(int)$config['allow_negative_stock'] && (float)$batchBalance['available_quantity'] < abs($quantityDelta)) throw new RuntimeException('The selected batch has insufficient available stock.');
        $newBatchQuantity = (float)$batchBalance['quantity_on_hand'] + $quantityDelta;
        $batchUpdate = mysqli_prepare($conn, 'UPDATE batch_inventory_balances SET quantity_on_hand=?,updated_at=UTC_TIMESTAMP(6) WHERE business_id=? AND location_id=? AND batch_id=?');
        mysqli_stmt_bind_param($batchUpdate, 'diii', $newBatchQuantity, $businessId, $locationId, $batchId);
        if (!mysqli_stmt_execute($batchUpdate)) throw new RuntimeException('Batch inventory could not be updated.');
    }

    $movementType = (string)$movement['movement_type'];
    $occurredAt = (string)$movement['occurred_at'];
    $membershipId = (int)$movement['created_by_membership_id'];
    $notes = trim((string)($movement['notes'] ?? '')) ?: null;
    $purchaseItemId = !empty($movement['purchase_item_id']) ? (int)$movement['purchase_item_id'] : null;
    $purchaseReturnItemId = !empty($movement['purchase_return_item_id']) ? (int)$movement['purchase_return_item_id'] : null;
    $saleItemId = !empty($movement['sale_item_id']) ? (int)$movement['sale_item_id'] : null;
    $saleReturnItemId = !empty($movement['sale_return_item_id']) ? (int)$movement['sale_return_item_id'] : null;
    $adjustmentItemId = !empty($movement['stock_adjustment_item_id']) ? (int)$movement['stock_adjustment_item_id'] : null;
    $stockTakeItemId = !empty($movement['stock_take_item_id']) ? (int)$movement['stock_take_item_id'] : null;
    $insertMovement = mysqli_prepare($conn, 'INSERT INTO inventory_movements (business_id,location_id,product_id,batch_id,movement_type,quantity_delta,unit_cost,occurred_at,purchase_item_id,purchase_return_item_id,sale_item_id,sale_return_item_id,stock_adjustment_item_id,stock_take_item_id,created_by_membership_id,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))');
    mysqli_stmt_bind_param($insertMovement, 'iiiisddsiiiiiiis', $businessId, $locationId, $productId, $batchId, $movementType, $quantityDelta, $unitCost, $occurredAt, $purchaseItemId, $purchaseReturnItemId, $saleItemId, $saleReturnItemId, $adjustmentItemId, $stockTakeItemId, $membershipId, $notes);
    if (!mysqli_stmt_execute($insertMovement)) throw new RuntimeException('Inventory movement could not be recorded.');
    $movementId = mysqli_insert_id($conn);

    if ($quantityDelta > 0 && ($config['inventory_valuation_method'] ?? 'WEIGHTED_AVERAGE') === 'FIFO') {
        $layer = mysqli_prepare($conn, 'INSERT INTO inventory_cost_layers (business_id,location_id,product_id,batch_id,purchase_item_id,received_at,original_quantity,remaining_quantity,unit_cost,created_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(6))');
        mysqli_stmt_bind_param($layer, 'iiiiisddd', $businessId, $locationId, $productId, $batchId, $purchaseItemId, $occurredAt, $quantityDelta, $quantityDelta, $unitCost);
        if (!mysqli_stmt_execute($layer)) throw new RuntimeException('FIFO cost layer could not be recorded.');
    }

    if (($config['inventory_valuation_method'] ?? 'WEIGHTED_AVERAGE') === 'FIFO') {
        $fifoAverageStmt = mysqli_prepare($conn, 'SELECT COALESCE(SUM(remaining_quantity * unit_cost) / NULLIF(SUM(remaining_quantity),0),0) average_cost FROM inventory_cost_layers WHERE business_id=? AND location_id=? AND product_id=? AND remaining_quantity>0');
        mysqli_stmt_bind_param($fifoAverageStmt, 'iii', $businessId, $locationId, $productId);
        mysqli_stmt_execute($fifoAverageStmt);
        $newAverage = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($fifoAverageStmt))['average_cost'] ?? 0);
        $fifoBalanceUpdate = mysqli_prepare($conn, 'UPDATE inventory_balances SET average_unit_cost=? WHERE business_id=? AND location_id=? AND product_id=?');
        mysqli_stmt_bind_param($fifoBalanceUpdate, 'diii', $newAverage, $businessId, $locationId, $productId);
        if (!mysqli_stmt_execute($fifoBalanceUpdate)) throw new RuntimeException('FIFO inventory valuation could not be synchronized.');
    }

    syncProductWeightedAverageCost($conn, $businessId, $productId);
    return ['movement_id'=>$movementId,'quantity_on_hand'=>$newQuantity,'average_unit_cost'=>$newAverage];
}
?>
