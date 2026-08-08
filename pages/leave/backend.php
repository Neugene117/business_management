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
requireActiveBusiness($conn);

$permissions = require __DIR__ . '/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

validateCsrfToken($_POST['csrf_token'] ?? '');

$action = isset($_POST['action']) ? $_POST['action'] : '';
$businessId = $_SESSION['active_business_id'];
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';

switch ($action) {
    case 'submit':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['submit']);

        $leave_type_id = isset($_POST['leave_type_id']) ? (int)$_POST['leave_type_id'] : 0;
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $requested_days = (float)($_POST['requested_days'] ?? 0.0);
        $reason = trim($_POST['reason'] ?? '');

        if (empty($leave_type_id) || empty($start_date) || empty($end_date) || $requested_days <= 0 || empty($reason)) {
            setFlashMessage('error', 'All fields are required. Requested days must be greater than zero.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate leave type
        $ltQ = "SELECT id, name FROM leave_types WHERE id = ? AND business_id = ? AND is_active = 1 LIMIT 1";
        $ltStmt = mysqli_prepare($conn, $ltQ);
        mysqli_stmt_bind_param($ltStmt, 'ii', $leave_type_id, $businessId);
        mysqli_stmt_execute($ltStmt);
        $ltRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ltStmt));
        if (!$ltRow) {
            setFlashMessage('error', 'Invalid leave type selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            INSERT INTO leave_requests (
                business_id, membership_id, leave_type_id, start_date, end_date, 
                requested_days, reason, status, submitted_at, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, 'PENDING', NOW(6), NOW(6), NOW(6)
            )
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            'iiissds',
            $businessId, $_SESSION['membership_id'], $leave_type_id, $start_date, $end_date,
            $requested_days, $reason
        );

        if (mysqli_stmt_execute($stmt)) {
            $leaveId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'LEAVE_REQUEST_SUBMITTED', 'leave_request', $leaveId, [
                'leave_type' => $ltRow['name'],
                'requested_days' => $requested_days,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            setFlashMessage('success', 'Leave request submitted successfully.');
        } else {
            setFlashMessage('error', 'Failed to submit leave request.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'approve':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['approve']);

        $leaveId = isset($_POST['leave_id']) ? (int)$_POST['leave_id'] : 0;
        if (empty($leaveId)) {
            setFlashMessage('error', 'Invalid leave request identifier.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE leave_requests 
            SET status = 'APPROVED', current_approver_membership_id = ?, decided_at = NOW(6), decision_note = 'Approved', updated_at = NOW(6) 
            WHERE id = ? AND business_id = ? AND status = 'PENDING'
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iii', $_SESSION['membership_id'], $leaveId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'LEAVE_REQUEST_APPROVED', 'leave_request', $leaveId, ['status' => 'APPROVED']);
            setFlashMessage('success', 'Leave request approved.');
        } else {
            setFlashMessage('error', 'Failed to approve leave request.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'reject':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['approve']);

        $leaveId = isset($_POST['leave_id']) ? (int)$_POST['leave_id'] : 0;
        $decision_note = trim($_POST['decision_note'] ?? '');

        if (empty($leaveId) || empty($decision_note)) {
            setFlashMessage('error', 'Decision note is required to reject leave request.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE leave_requests 
            SET status = 'REJECTED', current_approver_membership_id = ?, decided_at = NOW(6), decision_note = ?, updated_at = NOW(6) 
            WHERE id = ? AND business_id = ? AND status = 'PENDING'
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'isii', $_SESSION['membership_id'], $decision_note, $leaveId, $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'LEAVE_REQUEST_REJECTED', 'leave_request', $leaveId, [
                'status' => 'REJECTED',
                'decision_note' => $decision_note
            ]);
            setFlashMessage('success', 'Leave request rejected.');
        } else {
            setFlashMessage('error', 'Failed to reject leave request.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'cancel':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['submit']);

        $leaveId = isset($_POST['leave_id']) ? (int)$_POST['leave_id'] : 0;
        if (empty($leaveId)) {
            setFlashMessage('error', 'Invalid leave request.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $query = "
            UPDATE leave_requests 
            SET status = 'CANCELLED', updated_at = NOW(6) 
            WHERE id = ? AND membership_id = ? AND business_id = ? AND status = 'PENDING'
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'iii', $leaveId, $_SESSION['membership_id'], $businessId);
        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'LEAVE_REQUEST_CANCELLED', 'leave_request', $leaveId, ['status' => 'CANCELLED']);
            setFlashMessage('success', 'Leave request cancelled.');
        } else {
            setFlashMessage('error', 'Failed to cancel leave request.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
