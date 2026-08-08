<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
}

/**
 * Set flash message
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display and clear flash message
 */
function displayFlashMessage(): void {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        
        $type = htmlspecialchars($flash['type']);
        $message = htmlspecialchars($flash['message']);
        
        $svg = '';
        if ($type === 'success') {
            $svg = '<svg viewBox="0 0 24 24" style="stroke:var(--green);"><polyline points="20 6 9 17 4 12"/></svg>';
        } elseif ($type === 'error' || $type === 'danger') {
            $svg = '<svg viewBox="0 0 24 24" style="stroke:var(--red);"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        } elseif ($type === 'warning') {
            $svg = '<svg viewBox="0 0 24 24" style="stroke:var(--amber);"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/></svg>';
        } else {
            $svg = '<svg viewBox="0 0 24 24" style="stroke:var(--blue);"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
        }
        
        echo "
        <div class=\"alert-msg {$type}\" style=\"margin-bottom: 20px;\">
            {$svg}
            <span>{$message}</span>
        </div>";
    }
}
?>
