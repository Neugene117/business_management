<?php
/**
 * Backward-compatible database bootstrap.
 *
 * Older pages may still include config/db.php. Keep those pages on the same
 * canonical business_management connection used by the rest of the app.
 */
require_once __DIR__ . '/database.php';
?>
