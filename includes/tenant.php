<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';

/**
 * Validate and enforce that the current user has access to their active business
 */
function requireActiveBusiness($conn) {
    // Super admins don't need a business scope to operate platform-wide
    if (isSuperAdmin()) {
        return;
    }

    if (empty($_SESSION['active_business_id']) || empty($_SESSION['membership_id'])) {
        setFlashMessage('error', 'No active business session found.');
        header('Location: ' . getRootPrefix() . 'login.php');
        exit();
    }

    $businessId = $_SESSION['active_business_id'];
    $userId = $_SESSION['user_id'];
    $membershipId = $_SESSION['membership_id'];

    // Verify status in DB: users, businesses, business_memberships
    $query = "
        SELECT u.status as user_status, b.approval_status as business_status, m.status as membership_status 
        FROM business_memberships m
        JOIN users u ON m.user_id = u.id
        JOIN businesses b ON m.business_id = b.id
        WHERE m.id = ? AND m.business_id = ? AND m.user_id = ?
        LIMIT 1
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'iii', $membershipId, $businessId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // 1. User status check
        if ($row['user_status'] !== 'ACTIVE') {
            setFlashMessage('error', 'Your account status is: ' . $row['user_status'] . '.');
            header('Location: ' . getRootPrefix() . 'login.php');
            exit();
        }

        // 2. Business status check
        if ($row['business_status'] === 'PENDING') {
            setFlashMessage('warning', 'Your business account is awaiting approval.');
            header('Location: ' . getRootPrefix() . 'login.php');
            exit();
        } elseif ($row['business_status'] === 'REJECTED') {
            setFlashMessage('error', 'Your business registration was rejected.');
            header('Location: ' . getRootPrefix() . 'login.php');
            exit();
        } elseif ($row['business_status'] === 'SUSPENDED') {
            setFlashMessage('error', 'Your business account has been suspended.');
            header('Location: ' . getRootPrefix() . 'login.php');
            exit();
        } elseif ($row['business_status'] !== 'APPROVED') {
            setFlashMessage('error', 'Your business account is inactive.');
            header('Location: ' . getRootPrefix() . 'login.php');
            exit();
        }

        // 3. Membership status check
        if ($row['membership_status'] !== 'ACTIVE') {
            setFlashMessage('error', 'Your business membership is: ' . $row['membership_status'] . '.');
            header('Location: ' . getRootPrefix() . 'login.php');
            exit();
        }
    } else {
        setFlashMessage('error', 'Unauthorized business access.');
        header('Location: ' . getRootPrefix() . 'login.php');
        exit();
    }
}

/**
 * Helper to get root path prefix based on file depth
 */
function getRootPrefix() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $pos = strpos($script, '/pages/');
    return ($pos !== false) ? str_repeat('../', substr_count(substr($script, $pos), '/') - 1) : '';
}
?>
