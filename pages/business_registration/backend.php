<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

// Validate CSRF
validateCsrfToken($_POST['csrf_token'] ?? '');

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'register') {
    // Collect and sanitize fields
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $owner_email = strtolower(trim($_POST['owner_email'] ?? ''));
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    $business_name = trim($_POST['business_name'] ?? '');
    $legal_name = trim($_POST['legal_name'] ?? '');
    if (empty($legal_name)) {
        $legal_name = $business_name;
    }
    $business_email = strtolower(trim($_POST['business_email'] ?? ''));
    $business_phone = trim($_POST['business_phone'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? NULL);
    $tax_number = trim($_POST['tax_number'] ?? NULL);
    $country_code = trim($_POST['country_code'] ?? 'RW');
    $currency_code = trim($_POST['currency_code'] ?? 'RWF');
    $timezone = trim($_POST['timezone'] ?? 'Africa/Kigali');
    $city = trim($_POST['city'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $summary = trim($_POST['summary'] ?? '');

    // Server-side validation
    if (empty($first_name) || empty($last_name) || empty($owner_email) || empty($owner_phone) || empty($password) || empty($business_name) || empty($business_email) || empty($business_phone) || empty($city) || empty($address_line1) || empty($summary)) {
        setFlashMessage('error', 'All marked fields (*) are required.');
        header("Location: index.php");
        exit();
    }

    if ($password !== $password_confirm) {
        setFlashMessage('error', 'Passwords do not match.');
        header("Location: index.php");
        exit();
    }

    if (strlen($password) < 8) {
        setFlashMessage('error', 'Password must be at least 8 characters long.');
        header("Location: index.php");
        exit();
    }

    // Check if email already exists
    $checkQuery = "SELECT id FROM users WHERE email = ? LIMIT 1";
    $chkStmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($chkStmt, 's', $owner_email);
    mysqli_stmt_execute($chkStmt);
    $chkResult = mysqli_stmt_get_result($chkStmt);

    if (mysqli_num_rows($chkResult) > 0) {
        setFlashMessage('error', 'The email address is already registered.');
        header("Location: index.php");
        exit();
    }

    // Check if user phone number already exists
    $checkPhoneQuery = "SELECT id FROM users WHERE phone = ? LIMIT 1";
    $cpStmt = mysqli_prepare($conn, $checkPhoneQuery);
    mysqli_stmt_bind_param($cpStmt, 's', $owner_phone);
    mysqli_stmt_execute($cpStmt);
    $cpResult = mysqli_stmt_get_result($cpStmt);

    if (mysqli_num_rows($cpResult) > 0) {
        setFlashMessage('error', 'The phone number is already registered.');
        header("Location: index.php");
        exit();
    }

    // Check if business registration number already exists
    if (!empty($registration_number)) {
        $checkRegQuery = "SELECT id FROM businesses WHERE registration_number = ? LIMIT 1";
        $crStmt = mysqli_prepare($conn, $checkRegQuery);
        mysqli_stmt_bind_param($crStmt, 's', $registration_number);
        mysqli_stmt_execute($crStmt);
        $crResult = mysqli_stmt_get_result($crStmt);

        if (mysqli_num_rows($crResult) > 0) {
            setFlashMessage('error', 'The business registration number is already registered.');
            header("Location: index.php");
            exit();
        }
    }

    // Begin Onboarding Database Transaction
    mysqli_begin_transaction($conn);

    try {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        // 1. Create User
        $userStatus = 'PENDING_APPROVAL';
        $userQuery = "
            INSERT INTO users (email, password_hash, phone, first_name, last_name, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(6), NOW(6))
        ";
        $uStmt = mysqli_prepare($conn, $userQuery);
        mysqli_stmt_bind_param($uStmt, 'ssssss', $owner_email, $passwordHash, $owner_phone, $first_name, $last_name, $userStatus);
        if (!mysqli_stmt_execute($uStmt)) {
            throw new Exception("Error creating user: " . mysqli_error($conn));
        }
        $userId = mysqli_insert_id($conn);

        // 1b. Assign BUSINESS_OWNER role to the user in user_roles
        $getRoleQuery = "SELECT id FROM roles WHERE name = 'BUSINESS_OWNER' LIMIT 1";
        $grStmt = mysqli_prepare($conn, $getRoleQuery);
        mysqli_stmt_execute($grStmt);
        $roleResult = mysqli_stmt_get_result($grStmt);
        $roleRow = mysqli_fetch_assoc($roleResult);
        $roleId = $roleRow ? $roleRow['id'] : 2;

        $userRoleQuery = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
        $urStmt = mysqli_prepare($conn, $userRoleQuery);
        mysqli_stmt_bind_param($urStmt, 'ii', $userId, $roleId);
        if (!mysqli_stmt_execute($urStmt)) {
            throw new Exception("Error assigning user role: " . mysqli_error($conn));
        }

        // 2. Hash Password and Store Credentials in credentials table as fallback
        $credQuery = "
            INSERT INTO auth_credentials (user_id, password_hash, created_at, updated_at) 
            VALUES (?, ?, NOW(6), NOW(6))
        ";
        $cStmt = mysqli_prepare($conn, $credQuery);
        mysqli_stmt_bind_param($cStmt, 'is', $userId, $passwordHash);
        if (!mysqli_stmt_execute($cStmt)) {
            throw new Exception("Error saving credentials: " . mysqli_error($conn));
        }

        // 3. Generate UUID for public_id
        $publicId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // 4. Create Business
        $approvalStatus = 'PENDING';
        $bizQuery = "
            INSERT INTO businesses (
                public_id, business_name, legal_name, phone, email, 
                summary, registration_number, tax_number, country_code, 
                currency_code, timezone, address_line1, city, 
                approval_status, created_by_user_id, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, ?, ?, 
                ?, ?, NOW(6), NOW(6)
            )
        ";
        $bStmt = mysqli_prepare($conn, $bizQuery);
        mysqli_stmt_bind_param(
            $bStmt,
            'ssssssssssssssi',
            $publicId, $business_name, $legal_name, $business_phone, $business_email,
            $summary, $registration_number, $tax_number, $country_code,
            $currency_code, $timezone, $address_line1, $city,
            $approvalStatus, $userId
        );
        if (!mysqli_stmt_execute($bStmt)) {
            throw new Exception("Error creating business: " . mysqli_error($conn));
        }
        $businessId = mysqli_insert_id($conn);

        // 5. Create Business Membership
        $memberType = 'OWNER';
        $membershipStatus = 'PENDING';
        $memQuery = "
            INSERT INTO business_memberships (business_id, user_id, member_type, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, NOW(6), NOW(6))
        ";
        $mStmt = mysqli_prepare($conn, $memQuery);
        mysqli_stmt_bind_param($mStmt, 'iiss', $businessId, $userId, $memberType, $membershipStatus);
        if (!mysqli_stmt_execute($mStmt)) {
            throw new Exception("Error creating business membership: " . mysqli_error($conn));
        }
        $membershipId = mysqli_insert_id($conn);

        // 6. Create Default Accounting Settings
        $acctQuery = "
            INSERT INTO business_accounting_settings (
                business_id, inventory_valuation_method, default_tax_rate, 
                fiscal_year_start_month, allow_negative_stock, created_at, updated_at
            ) VALUES (
                ?, 'WEIGHTED_AVERAGE', 0.0000, 1, 0, NOW(6), NOW(6)
            )
        ";
        $aStmt = mysqli_prepare($conn, $acctQuery);
        mysqli_stmt_bind_param($aStmt, 'i', $businessId);
        if (!mysqli_stmt_execute($aStmt)) {
            throw new Exception("Error creating business accounting settings: " . mysqli_error($conn));
        }

        // 7. Log Business Approval Event
        $eventType = 'SUBMITTED';
        $evQuery = "
            INSERT INTO business_approval_events (business_id, event_type, reason, actor_user_id, created_at) 
            VALUES (?, ?, NULL, ?, NOW(6))
        ";
        $eStmt = mysqli_prepare($conn, $evQuery);
        mysqli_stmt_bind_param($eStmt, 'isi', $businessId, $eventType, $userId);
        if (!mysqli_stmt_execute($eStmt)) {
            throw new Exception("Error logging business approval event: " . mysqli_error($conn));
        }

        // 8. Log Audit Event
        // Inject session parameters temporarily for audit logging context
        $_SESSION['user_id'] = $userId;
        $_SESSION['membership_id'] = $membershipId;
        
        writeAuditLog($conn, $businessId, 'BUSINESS_REGISTERED', 'business', $businessId, [
            'business_name' => $business_name,
            'owner_email' => $owner_email
        ]);

        // Clear temporary sessions
        unset($_SESSION['user_id']);
        unset($_SESSION['membership_id']);

        // Commit Transaction
        mysqli_commit($conn);

        setFlashMessage('success', 'Your business registration has been submitted and is waiting for Super Admin approval.');
        header("Location: ../../login.php");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Business Registration Failed: " . $e->getMessage());
        setFlashMessage('error', 'Registration failed. Please try again. System error logged.');
        header("Location: index.php");
        exit();
    }
} else {
    setFlashMessage('error', 'Invalid action.');
    header("Location: index.php");
    exit();
}
?>
