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
    case 'create':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['create']);

        // Collect parameters
        $employee_number = strtoupper(trim($_POST['employee_number'] ?? ''));
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $job_title = trim($_POST['job_title'] ?? NULL);
        $department = trim($_POST['department'] ?? NULL);
        $hire_date = trim($_POST['hire_date'] ?? NULL);
        $business_role_id = isset($_POST['business_role_id']) ? (int)$_POST['business_role_id'] : 0;
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? NULL);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? NULL);

        if (empty($employee_number) || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($business_role_id)) {
            setFlashMessage('error', 'All marked fields (*) are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        if (strlen($password) < 8) {
            setFlashMessage('error', 'Password must be at least 8 characters long.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate business role exists under this business
        $brQuery = "SELECT id FROM business_roles WHERE id = ? AND business_id = ? LIMIT 1";
        $brStmt = mysqli_prepare($conn, $brQuery);
        mysqli_stmt_bind_param($brStmt, 'ii', $business_role_id, $businessId);
        mysqli_stmt_execute($brStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($brStmt)) === 0) {
            setFlashMessage('error', 'Invalid business role selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // Check email uniqueness
            $chkUserQ = "SELECT id FROM users WHERE email = ? LIMIT 1 FOR UPDATE";
            $cuStmt = mysqli_prepare($conn, $chkUserQ);
            mysqli_stmt_bind_param($cuStmt, 's', $email);
            mysqli_stmt_execute($cuStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($cuStmt)) > 0) {
                throw new Exception("Email is already registered in the system.");
            }

            // Check employee code uniqueness for this business
            $chkEmpQ = "SELECT membership_id FROM employee_profiles WHERE business_id = ? AND employee_number = ? LIMIT 1 FOR UPDATE";
            $ceStmt = mysqli_prepare($conn, $chkEmpQ);
            mysqli_stmt_bind_param($ceStmt, 'is', $businessId, $employee_number);
            mysqli_stmt_execute($ceStmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($ceStmt)) > 0) {
                throw new Exception("Employee number code already exists in this business.");
            }

            // 1. Insert User
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insUser = "
                INSERT INTO users (email, password_hash, phone, first_name, last_name, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 'ACTIVE', NOW(6), NOW(6))
            ";
            $iuStmt = mysqli_prepare($conn, $insUser);
            mysqli_stmt_bind_param($iuStmt, 'sssss', $email, $passwordHash, $phone, $first_name, $last_name);
            mysqli_stmt_execute($iuStmt);
            $userId = mysqli_insert_id($conn);

            // 2. Insert Credentials in credentials table as fallback
            $insCred = "
                INSERT INTO auth_credentials (user_id, password_hash, created_at, updated_at) 
                VALUES (?, ?, NOW(6), NOW(6))
            ";
            $icStmt = mysqli_prepare($conn, $insCred);
            mysqli_stmt_bind_param($icStmt, 'is', $userId, $passwordHash);
            mysqli_stmt_execute($icStmt);

            // 3. Create Business Membership
            $memberType = 'EMPLOYEE';
            $membershipStatus = 'ACTIVE';
            $insMem = "
                INSERT INTO business_memberships (business_id, user_id, member_type, status, joined_at, invited_by_membership_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(6), ?, NOW(6), NOW(6))
            ";
            $imStmt = mysqli_prepare($conn, $insMem);
            mysqli_stmt_bind_param($imStmt, 'iissi', $businessId, $userId, $memberType, $membershipStatus, $_SESSION['membership_id']);
            mysqli_stmt_execute($imStmt);
            $empMembershipId = mysqli_insert_id($conn);

            // 4. Create Employee Profile
            $insProf = "
                INSERT INTO employee_profiles (
                    membership_id, business_id, employee_number, job_title, department, 
                    hire_date, emergency_contact_name, emergency_contact_phone, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(6), NOW(6))
            ";
            $ipStmt = mysqli_prepare($conn, $insProf);
            mysqli_stmt_bind_param(
                $ipStmt,
                'iissssss',
                $empMembershipId, $businessId, $employee_number, $job_title, $department,
                $hire_date, $emergency_contact_name, $emergency_contact_phone
            );
            mysqli_stmt_execute($ipStmt);

            // 5. Assign business role
            $insMr = "
                INSERT INTO membership_roles (business_id, membership_id, business_role_id, assigned_by_membership_id, assigned_at) 
                VALUES (?, ?, ?, ?, NOW(6))
            ";
            $imrStmt = mysqli_prepare($conn, $insMr);
            mysqli_stmt_bind_param($imrStmt, 'iiii', $businessId, $empMembershipId, $business_role_id, $_SESSION['membership_id']);
            mysqli_stmt_execute($imrStmt);

            // 6. Write Audit log
            writeAuditLog($conn, $businessId, 'EMPLOYEE_CREATED', 'business_membership', $empMembershipId, [
                'employee_number' => $employee_number,
                'email' => $email,
                'job_title' => $job_title,
                'business_role_id' => $business_role_id
            ]);

            mysqli_commit($conn);
            setFlashMessage('success', 'Employee account created successfully.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Employee Creation Transaction Failed: " . $e->getMessage());
            setFlashMessage('error', 'Failed to create employee: ' . $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $empMembershipId = isset($_POST['membership_id']) ? (int)$_POST['membership_id'] : 0;
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $job_title = trim($_POST['job_title'] ?? NULL);
        $department = trim($_POST['department'] ?? NULL);
        $status = trim($_POST['status'] ?? 'ACTIVE');
        $business_role_id = isset($_POST['business_role_id']) ? (int)$_POST['business_role_id'] : 0;
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? NULL);
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? NULL);

        if (empty($empMembershipId) || empty($first_name) || empty($last_name) || empty($business_role_id)) {
            setFlashMessage('error', 'All marked fields (*) are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Validate business role
        $brQuery = "SELECT id FROM business_roles WHERE id = ? AND business_id = ? LIMIT 1";
        $brStmt = mysqli_prepare($conn, $brQuery);
        mysqli_stmt_bind_param($brStmt, 'ii', $business_role_id, $businessId);
        mysqli_stmt_execute($brStmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($brStmt)) === 0) {
            setFlashMessage('error', 'Invalid business role selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // Lock and fetch current details
            $memQ = "
                SELECT m.*, u.id as user_id, u.first_name, u.last_name, ep.job_title, ep.department
                FROM business_memberships m
                JOIN users u ON m.user_id = u.id
                LEFT JOIN employee_profiles ep ON m.id = ep.membership_id AND ep.business_id = m.business_id
                WHERE m.id = ? AND m.business_id = ? AND m.member_type = 'EMPLOYEE'
                LIMIT 1 FOR UPDATE
            ";
            $mStmt = mysqli_prepare($conn, $memQ);
            mysqli_stmt_bind_param($mStmt, 'ii', $empMembershipId, $businessId);
            mysqli_stmt_execute($mStmt);
            $oldRow = mysqli_fetch_assoc(mysqli_stmt_get_result($mStmt));

            if (!$oldRow) {
                throw new Exception("Employee membership not found.");
            }

            // 1. Update user first_name, last_name, and user status to DISABLED if membership is TERMINATED
            $userStatus = ($status === 'TERMINATED') ? 'DISABLED' : 'ACTIVE';
            $updUser = "UPDATE users SET first_name = ?, last_name = ?, status = ?, updated_at = NOW(6) WHERE id = ?";
            $uuStmt = mysqli_prepare($conn, $updUser);
            mysqli_stmt_bind_param($uuStmt, 'sssi', $first_name, $last_name, $userStatus, $oldRow['user_id']);
            mysqli_stmt_execute($uuStmt);

            // 2. Update membership status
            $updMem = "UPDATE business_memberships SET status = ?, updated_at = NOW(6) WHERE id = ? AND business_id = ?";
            $umStmt = mysqli_prepare($conn, $updMem);
            mysqli_stmt_bind_param($umStmt, 'sii', $status, $empMembershipId, $businessId);
            mysqli_stmt_execute($umStmt);

            // 3. Update employee profile details
            $updProf = "
                UPDATE employee_profiles 
                SET job_title = ?, department = ?, emergency_contact_name = ?, emergency_contact_phone = ?, updated_at = NOW(6) 
                WHERE membership_id = ? AND business_id = ?
            ";
            $upStmt = mysqli_prepare($conn, $updProf);
            mysqli_stmt_bind_param(
                $upStmt,
                'ssssii',
                $job_title, $department, $emergency_contact_name, $emergency_contact_phone,
                $empMembershipId, $businessId
            );
            mysqli_stmt_execute($upStmt);

            // 4. Update assigned role (delete & insert)
            $delMr = "DELETE FROM membership_roles WHERE business_id = ? AND membership_id = ?";
            $dmrStmt = mysqli_prepare($conn, $delMr);
            mysqli_stmt_bind_param($dmrStmt, 'ii', $businessId, $empMembershipId);
            mysqli_stmt_execute($dmrStmt);

            $insMr = "
                INSERT INTO membership_roles (business_id, membership_id, business_role_id, assigned_by_membership_id, assigned_at) 
                VALUES (?, ?, ?, ?, NOW(6))
            ";
            $imrStmt = mysqli_prepare($conn, $insMr);
            mysqli_stmt_bind_param($imrStmt, 'iiii', $businessId, $empMembershipId, $business_role_id, $_SESSION['membership_id']);
            mysqli_stmt_execute($imrStmt);

            // 5. Log audit trail
            writeAuditLog($conn, $businessId, 'EMPLOYEE_UPDATED', 'business_membership', $empMembershipId, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'job_title' => $job_title,
                'department' => $department,
                'status' => $status,
                'business_role_id' => $business_role_id
            ], $oldRow);

            mysqli_commit($conn);
            setFlashMessage('success', 'Employee profile updated successfully.');

        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Employee Update Failed: " . $e->getMessage());
            setFlashMessage('error', 'Failed to update employee: ' . $e->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
