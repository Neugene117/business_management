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
?>
