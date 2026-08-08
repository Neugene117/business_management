<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/tenant.php';
require_once __DIR__ . '/../../includes/permission_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';

requireLogin();

// Check platform permissions
$permissions = require __DIR__ . '/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

validateCsrfToken($_POST['csrf_token'] ?? '');

$action = isset($_POST['action']) ? $_POST['action'] : '';
$businessId = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (empty($businessId)) {
    setFlashMessage('error', 'Invalid business identifier.');
    header("Location: index.php");
    exit();
}

// Allow switches/overrides inside backend redirects
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';

switch ($action) {
    case 'approve':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['approve']);

        mysqli_begin_transaction($conn);
        try {
            // 1. Fetch created_by_user_id (owner user) from business record
            $bizQuery = "SELECT created_by_user_id, approval_status FROM businesses WHERE id = ? FOR UPDATE";
            $bizStmt = mysqli_prepare($conn, $bizQuery);
            mysqli_stmt_bind_param($bizStmt, 'i', $businessId);
            mysqli_stmt_execute($bizStmt);
            $bizRow = mysqli_fetch_assoc(mysqli_stmt_get_result($bizStmt));
            
            if (!$bizRow) {
                throw new Exception("Business record not found.");
            }
            if ($bizRow['approval_status'] !== 'PENDING') {
                throw new Exception("Business is not in PENDING state.");
            }

            $ownerUserId = $bizRow['created_by_user_id'];

            // 2. Update business status
            $updBiz = "
                UPDATE businesses 
                SET approval_status = 'APPROVED', approved_at = NOW(6), approved_by_user_id = ? 
                WHERE id = ?
            ";
            $ubStmt = mysqli_prepare($conn, $updBiz);
            mysqli_stmt_bind_param($ubStmt, 'ii', $_SESSION['user_id'], $businessId);
            mysqli_stmt_execute($ubStmt);

            // 3. Find target owner membership
            $memQuery = "SELECT id FROM business_memberships WHERE business_id = ? AND user_id = ? AND member_type = 'OWNER' LIMIT 1 FOR UPDATE";
            $mStmt = mysqli_prepare($conn, $memQuery);
            mysqli_stmt_bind_param($mStmt, 'ii', $businessId, $ownerUserId);
            mysqli_stmt_execute($mStmt);
            $memRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mStmt));
            if (!$memRow) {
                throw new Exception("Owner business membership record not found.");
            }
            $membershipId = $memRow['id'];

            // 4. Update owner user status to ACTIVE
            $updUser = "UPDATE users SET status = 'ACTIVE' WHERE id = ?";
            $uuStmt = mysqli_prepare($conn, $updUser);
            mysqli_stmt_bind_param($uuStmt, 'i', $ownerUserId);
            mysqli_stmt_execute($uuStmt);

            // 5. Update membership status to ACTIVE
            $updMem = "UPDATE business_memberships SET status = 'ACTIVE', joined_at = NOW(6) WHERE id = ?";
            $umStmt = mysqli_prepare($conn, $updMem);
            mysqli_stmt_bind_param($umStmt, 'i', $membershipId);
            mysqli_stmt_execute($umStmt);

            // 6. Verify or create system OWNER role for this business
            $roleCheck = "SELECT id FROM business_roles WHERE business_id = ? AND code = 'OWNER' LIMIT 1";
            $rcStmt = mysqli_prepare($conn, $roleCheck);
            mysqli_stmt_bind_param($rcStmt, 'i', $businessId);
            mysqli_stmt_execute($rcStmt);
            $roleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($rcStmt));
            
            if ($roleRow) {
                $roleId = $roleRow['id'];
            } else {
                $insRole = "
                    INSERT INTO business_roles (business_id, code, name, description, is_system, created_at, updated_at) 
                    VALUES (?, 'OWNER', 'Owner', 'Full business access.', 1, NOW(6), NOW(6))
                ";
                $irStmt = mysqli_prepare($conn, $insRole);
                mysqli_stmt_bind_param($irStmt, 'i', $businessId);
                mysqli_stmt_execute($irStmt);
                $roleId = mysqli_insert_id($conn);
            }

            // 7. Grant ALL business permissions to the OWNER role
            $permQuery = "SELECT id FROM permissions WHERE scope = 'BUSINESS'";
            $permResult = mysqli_query($conn, $permQuery);
            $insRolePerm = "
                INSERT IGNORE INTO business_role_permissions (business_id, business_role_id, permission_id) 
                VALUES (?, ?, ?)
            ";
            $irpStmt = mysqli_prepare($conn, $insRolePerm);
            while ($pRow = mysqli_fetch_assoc($permResult)) {
                mysqli_stmt_bind_param($irpStmt, 'iii', $businessId, $roleId, $pRow['id']);
                mysqli_stmt_execute($irpStmt);
            }

            // 8. Assign OWNER role to owner membership
            $mrCheck = "SELECT 1 FROM membership_roles WHERE business_id = ? AND membership_id = ? AND business_role_id = ? LIMIT 1";
            $mrcStmt = mysqli_prepare($conn, $mrCheck);
            mysqli_stmt_bind_param($mrcStmt, 'iii', $businessId, $membershipId, $roleId);
            mysqli_stmt_execute($mrcStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($mrcStmt)) === 0) {
                $insMr = "
                    INSERT INTO membership_roles (business_id, membership_id, business_role_id, assigned_by_membership_id, assigned_at) 
                    VALUES (?, ?, ?, NULL, NOW(6))
                ";
                $imrStmt = mysqli_prepare($conn, $insMr);
                mysqli_stmt_bind_param($imrStmt, 'iii', $businessId, $membershipId, $roleId);
                mysqli_stmt_execute($imrStmt);
            }

            // 9. Create default location for this business if none exists
            $locCheck = "SELECT 1 FROM business_locations WHERE business_id = ? LIMIT 1";
            $lcStmt = mysqli_prepare($conn, $locCheck);
            mysqli_stmt_bind_param($lcStmt, 'i', $businessId);
            mysqli_stmt_execute($lcStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($lcStmt)) === 0) {
                $insLoc = "
                    INSERT INTO business_locations (business_id, code, name, location_type, address, is_active, created_at, updated_at) 
                    VALUES (?, 'MAIN', 'Main Location', 'STORE', 'Headquarters Address', 1, NOW(6), NOW(6))
                ";
                $ilStmt = mysqli_prepare($conn, $insLoc);
                mysqli_stmt_bind_param($ilStmt, 'i', $businessId);
                mysqli_stmt_execute($ilStmt);
            }

            // 10. Log business approval event
            $eventType = 'APPROVED';
            $evQuery = "
                INSERT INTO business_approval_events (business_id, event_type, reason, actor_user_id, created_at) 
                VALUES (?, ?, NULL, ?, NOW(6))
            ";
            $eStmt = mysqli_prepare($conn, $evQuery);
            mysqli_stmt_bind_param($eStmt, 'isi', $businessId, $eventType, $_SESSION['user_id']);
            mysqli_stmt_execute($eStmt);

            // 11. Write audit log
            writeAuditLog($conn, $businessId, 'BUSINESS_APPROVED', 'business', $businessId, [
                'business_id' => $businessId,
                'approved_by_user_id' => $_SESSION['user_id']
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Business registration approved successfully.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Business Approval Failed: " . $e->getMessage());
            setFlashMessage('error', 'Failed to approve business: ' . $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'reject':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['reject']);

        if (empty($reason)) {
            setFlashMessage('error', 'Rejection reason is required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            $bizQuery = "SELECT created_by_user_id, approval_status FROM businesses WHERE id = ? FOR UPDATE";
            $bizStmt = mysqli_prepare($conn, $bizQuery);
            mysqli_stmt_bind_param($bizStmt, 'i', $businessId);
            mysqli_stmt_execute($bizStmt);
            $bizRow = mysqli_fetch_assoc(mysqli_stmt_get_result($bizStmt));
            
            if (!$bizRow) {
                throw new Exception("Business record not found.");
            }
            if ($bizRow['approval_status'] !== 'PENDING') {
                throw new Exception("Business is not in PENDING state.");
            }

            $ownerUserId = $bizRow['created_by_user_id'];

            // Update business status
            $updBiz = "
                UPDATE businesses 
                SET approval_status = 'REJECTED', rejected_at = NOW(6)
                WHERE id = ?
            ";
            $ubStmt = mysqli_prepare($conn, $updBiz);
            mysqli_stmt_bind_param($ubStmt, 'i', $businessId);
            mysqli_stmt_execute($ubStmt);

            // Update user status
            $updUser = "UPDATE users SET status = 'DISABLED' WHERE id = ?";
            $uuStmt = mysqli_prepare($conn, $updUser);
            mysqli_stmt_bind_param($uuStmt, 'i', $ownerUserId);
            mysqli_stmt_execute($uuStmt);

            // Log event
            $eventType = 'REJECTED';
            $evQuery = "
                INSERT INTO business_approval_events (business_id, event_type, reason, actor_user_id, created_at) 
                VALUES (?, ?, ?, ?, NOW(6))
            ";
            $eStmt = mysqli_prepare($conn, $evQuery);
            mysqli_stmt_bind_param($eStmt, 'issi', $businessId, $eventType, $reason, $_SESSION['user_id']);
            mysqli_stmt_execute($eStmt);

            // Write audit log
            writeAuditLog($conn, $businessId, 'BUSINESS_REJECTED', 'business', $businessId, [
                'business_id' => $businessId,
                'rejected_by_user_id' => $_SESSION['user_id'],
                'reason' => $reason
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Business registration rejected.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Business Rejection Failed: " . $e->getMessage());
            setFlashMessage('error', 'Failed to reject business: ' . $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'suspend':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['suspend']);

        mysqli_begin_transaction($conn);
        try {
            $updBiz = "UPDATE businesses SET approval_status = 'SUSPENDED' WHERE id = ?";
            $ubStmt = mysqli_prepare($conn, $updBiz);
            mysqli_stmt_bind_param($ubStmt, 'i', $businessId);
            mysqli_stmt_execute($ubStmt);

            $eventType = 'SUSPENDED';
            $evQuery = "
                INSERT INTO business_approval_events (business_id, event_type, reason, actor_user_id, created_at) 
                VALUES (?, ?, ?, ?, NOW(6))
            ";
            $eStmt = mysqli_prepare($conn, $evQuery);
            mysqli_stmt_bind_param($eStmt, 'issi', $businessId, $eventType, $reason, $_SESSION['user_id']);
            mysqli_stmt_execute($eStmt);

            writeAuditLog($conn, $businessId, 'BUSINESS_SUSPENDED', 'business', $businessId, [
                'business_id' => $businessId,
                'suspended_by_user_id' => $_SESSION['user_id']
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Business account suspended.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Business Suspension Failed: " . $e->getMessage());
            setFlashMessage('error', 'Failed to suspend business.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'reactivate':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['suspend']);

        mysqli_begin_transaction($conn);
        try {
            $updBiz = "UPDATE businesses SET approval_status = 'APPROVED' WHERE id = ?";
            $ubStmt = mysqli_prepare($conn, $updBiz);
            mysqli_stmt_bind_param($ubStmt, 'i', $businessId);
            mysqli_stmt_execute($ubStmt);

            $eventType = 'REACTIVATED';
            $evQuery = "
                INSERT INTO business_approval_events (business_id, event_type, reason, actor_user_id, created_at) 
                VALUES (?, ?, NULL, ?, NOW(6))
            ";
            $eStmt = mysqli_prepare($conn, $evQuery);
            mysqli_stmt_bind_param($eStmt, 'isi', $businessId, $eventType, $_SESSION['user_id']);
            mysqli_stmt_execute($eStmt);

            writeAuditLog($conn, $businessId, 'BUSINESS_REACTIVATED', 'business', $businessId, [
                'business_id' => $businessId,
                'reactivated_by_user_id' => $_SESSION['user_id']
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Business account reactivated.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Business Reactivation Failed: " . $e->getMessage());
            setFlashMessage('error', 'Failed to reactivate business.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
