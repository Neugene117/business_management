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
    case 'update_company_identity':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);

        if (!isBusinessOwner()) {
            setFlashMessage('error', 'Only the Business Owner can update company information.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $businessName = trim($_POST['business_name'] ?? '');
        $businessNameLength = function_exists('mb_strlen') ? mb_strlen($businessName) : strlen($businessName);
        if ($businessName === '' || $businessNameLength > 200) {
            setFlashMessage('error', 'Enter a company name no longer than 200 characters.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $ownerUserId = (int)($_SESSION['user_id'] ?? 0);
        $oldQuery = "SELECT business_name, company_logo_path FROM businesses WHERE id = ? AND created_by_user_id = ? LIMIT 1";
        $oldStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, 'ii', $businessId, $ownerUserId);
        mysqli_stmt_execute($oldStmt);
        $oldCompany = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStmt));
        if (!$oldCompany) {
            setFlashMessage('error', 'Company information is unavailable for this owner account.');
            header("Location: index.php" . $role_query);
            exit();
        }

        $logoUpload = storeCompanyLogoUpload($_FILES['company_logo'] ?? null, (int)$businessId);
        if (!$logoUpload['ok']) {
            setFlashMessage('error', $logoUpload['error']);
            header("Location: index.php" . $role_query);
            exit();
        }
        $companyLogoPath = $logoUpload['uploaded'] ? $logoUpload['path'] : $oldCompany['company_logo_path'];

        $updateQuery = "
            UPDATE businesses
            SET business_name = ?, company_logo_path = ?, updated_at = NOW(6)
            WHERE id = ? AND created_by_user_id = ?
        ";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        $companyUpdated = false;
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'ssii', $businessName, $companyLogoPath, $businessId, $ownerUserId);
            $companyUpdated = mysqli_stmt_execute($updateStmt);
        }
        if ($companyUpdated) {
            writeAuditLog($conn, $businessId, 'COMPANY_INFORMATION_UPDATED', 'business', $businessId, [
                'business_name' => $businessName,
                'company_logo_changed' => (bool)$logoUpload['uploaded']
            ], $oldCompany);
            if ($logoUpload['uploaded'] && !empty($oldCompany['company_logo_path'])) {
                deleteCompanyLogoFile($oldCompany['company_logo_path']);
            }
            setFlashMessage('success', 'Company information updated successfully.');
        } else {
            if ($logoUpload['uploaded']) {
                deleteCompanyLogoFile($logoUpload['path']);
            }
            setFlashMessage('error', 'Failed to update company information.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update_profile':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $legal_name = trim($_POST['legal_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $tax_number = trim($_POST['tax_number'] ?? NULL);
        $registration_number = trim($_POST['registration_number'] ?? NULL);
        $country_code = trim($_POST['country_code'] ?? 'RW');
        $city = trim($_POST['city'] ?? '');
        $address_line1 = trim($_POST['address_line1'] ?? '');
        $summary = trim($_POST['summary'] ?? '');

        if (empty($phone) || empty($email) || empty($city) || empty($address_line1) || empty($summary)) {
            setFlashMessage('error', 'All marked fields (*) are required.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch old values for audit logging
        $oldQuery = "SELECT * FROM businesses WHERE id = ? LIMIT 1";
        $oStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oStmt, 'i', $businessId);
        mysqli_stmt_execute($oStmt);
        $old = mysqli_fetch_assoc(mysqli_stmt_get_result($oStmt));

        $query = "
            UPDATE businesses 
            SET legal_name = ?, phone = ?, email = ?,
                tax_number = ?, registration_number = ?, country_code = ?, 
                city = ?, address_line1 = ?, summary = ?, updated_at = NOW(6)
            WHERE id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssssi',
            $legal_name, $phone, $email,
            $tax_number, $registration_number, $country_code,
            $city, $address_line1, $summary, $businessId
        );

        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'BUSINESS_PROFILE_UPDATED', 'business', $businessId, [
                'legal_name' => $legal_name,
                'phone' => $phone,
                'email' => $email
            ], $old);
            setFlashMessage('success', 'Business profile settings updated successfully.');
        } else {
            setFlashMessage('error', 'Failed to update business profile settings.');
        }
        header("Location: index.php" . $role_query);
        exit();

    case 'update_accounting':
        requirePermission($conn, $_SESSION['membership_id'], $businessId, $permissions['update']);

        $inventory_valuation_method = trim($_POST['inventory_valuation_method'] ?? 'WEIGHTED_AVERAGE');
        $default_tax_rate = (float)($_POST['default_tax_rate'] ?? 0.0);
        $fiscal_year_start_month = (int)($_POST['fiscal_year_start_month'] ?? 1);
        $allow_negative_stock = isset($_POST['allow_negative_stock']) ? 1 : 0;

        if ($fiscal_year_start_month < 1 || $fiscal_year_start_month > 12) {
            setFlashMessage('error', 'Invalid fiscal start month selected.');
            header("Location: index.php" . $role_query);
            exit();
        }

        // Fetch old values for audit logging
        $oldQuery = "SELECT * FROM business_accounting_settings WHERE business_id = ? LIMIT 1";
        $oStmt = mysqli_prepare($conn, $oldQuery);
        mysqli_stmt_bind_param($oStmt, 'i', $businessId);
        mysqli_stmt_execute($oStmt);
        $old = mysqli_fetch_assoc(mysqli_stmt_get_result($oStmt));

        $query = "
            UPDATE business_accounting_settings 
            SET inventory_valuation_method = ?, default_tax_rate = ?, 
                fiscal_year_start_month = ?, allow_negative_stock = ?, updated_at = NOW(6)
            WHERE business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            'sdiii',
            $inventory_valuation_method, $default_tax_rate,
            $fiscal_year_start_month, $allow_negative_stock, $businessId
        );

        if (mysqli_stmt_execute($stmt)) {
            writeAuditLog($conn, $businessId, 'BUSINESS_ACCOUNTING_SETTINGS_UPDATED', 'business', $businessId, [
                'inventory_valuation_method' => $inventory_valuation_method,
                'default_tax_rate' => $default_tax_rate,
                'fiscal_year_start_month' => $fiscal_year_start_month,
                'allow_negative_stock' => $allow_negative_stock
            ], $old);
            setFlashMessage('success', 'Accounting parameters updated successfully.');
        } else {
            setFlashMessage('error', 'Failed to update accounting parameters.');
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
