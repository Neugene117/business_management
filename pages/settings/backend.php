<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();
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
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$role_query = isset($_GET['role']) ? '?role=' . e($_GET['role']) : '';

switch ($action) {
    case 'create_tax':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);
        $taxName = trim((string)($_POST['tax_name'] ?? ''));
        $taxType = strtoupper(trim((string)($_POST['tax_type'] ?? '')));
        $taxValueRaw = trim((string)($_POST['tax_value'] ?? ''));
        $taxValue = is_numeric($taxValueRaw) ? (float)$taxValueRaw : -1;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $nameLength = function_exists('mb_strlen') ? mb_strlen($taxName) : strlen($taxName);
        if ($nameLength < 2 || $nameLength > 100) {
            setFlashMessage('error', 'Enter a tax name between 2 and 100 characters.');
            header('Location: index.php' . $role_query . '#tax-registration');
            exit();
        }
        if (!in_array($taxType, ['PERCENTAGE', 'FIXED'], true) || $taxValue < 0 || ($taxType === 'PERCENTAGE' && $taxValue > 100)) {
            setFlashMessage('error', 'Enter a valid percentage from 0 to 100 or a non-negative fixed amount.');
            header('Location: index.php' . $role_query . '#tax-registration');
            exit();
        }
        mysqli_begin_transaction($conn);
        try {
            if ($isActive) {
                $disableStmt = mysqli_prepare($conn, 'UPDATE taxes SET is_active=0,updated_at=UTC_TIMESTAMP(6) WHERE business_id=? AND is_active=1');
                mysqli_stmt_bind_param($disableStmt, 'i', $businessId);
                mysqli_stmt_execute($disableStmt);
            }
            $insertStmt = mysqli_prepare($conn, 'INSERT INTO taxes (business_id,name,tax_type,tax_value,is_active,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
            mysqli_stmt_bind_param($insertStmt, 'issdi', $businessId, $taxName, $taxType, $taxValue, $isActive);
            if (!mysqli_stmt_execute($insertStmt)) throw new RuntimeException(mysqli_stmt_error($insertStmt));
            $taxId = mysqli_insert_id($conn);
            writeAuditLog($conn, $businessId, 'TAX_REGISTERED', 'tax', $taxId, ['name'=>$taxName,'tax_type'=>$taxType,'tax_value'=>$taxValue,'is_active'=>(bool)$isActive]);
            mysqli_commit($conn);
            setFlashMessage('success', $isActive ? 'Tax registered and activated for new sales.' : 'Tax registered as inactive.');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            $duplicate = $error instanceof mysqli_sql_exception && $error->getCode() === 1062;
            setFlashMessage('error', $duplicate ? 'A tax with this name is already registered.' : 'The tax could not be registered.');
        }
        header('Location: index.php' . $role_query . '#tax-registration');
        exit();

    case 'toggle_tax':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);
        $taxId = (int)($_POST['tax_id'] ?? 0);
        mysqli_begin_transaction($conn);
        try {
            $taxStmt = mysqli_prepare($conn, 'SELECT id,name,is_active FROM taxes WHERE id=? AND business_id=? LIMIT 1 FOR UPDATE');
            mysqli_stmt_bind_param($taxStmt, 'ii', $taxId, $businessId);
            mysqli_stmt_execute($taxStmt);
            $tax = mysqli_fetch_assoc(mysqli_stmt_get_result($taxStmt));
            if (!$tax) throw new RuntimeException('Tax registration not found.');
            $activate = (int)$tax['is_active'] !== 1;
            if ($activate) {
                $disableStmt = mysqli_prepare($conn, 'UPDATE taxes SET is_active=0,updated_at=UTC_TIMESTAMP(6) WHERE business_id=? AND is_active=1');
                mysqli_stmt_bind_param($disableStmt, 'i', $businessId);
                mysqli_stmt_execute($disableStmt);
            }
            $newStatus = $activate ? 1 : 0;
            $toggleStmt = mysqli_prepare($conn, 'UPDATE taxes SET is_active=?,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND business_id=?');
            mysqli_stmt_bind_param($toggleStmt, 'iii', $newStatus, $taxId, $businessId);
            if (!mysqli_stmt_execute($toggleStmt)) throw new RuntimeException('Tax status could not be changed.');
            writeAuditLog($conn, $businessId, $activate ? 'TAX_ACTIVATED' : 'TAX_DEACTIVATED', 'tax', $taxId, ['name'=>$tax['name']]);
            mysqli_commit($conn);
            setFlashMessage('success', $activate ? 'Tax activated for new sales.' : 'Tax deactivated. New sales will have no tax.');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            setFlashMessage('error', $error->getMessage());
        }
        header('Location: index.php' . $role_query . '#tax-registration');
        exit();

    case 'save_report_email_setting':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, 'settings.update');
        if (!isBusinessOwner()) {
            http_response_code(403);
            setFlashMessage('error', 'Only the Business Owner can configure report email delivery.');
            header('Location: index.php#report-email');
            exit();
        }

        $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $smtpEncryption = strtoupper(trim((string)($_POST['smtp_encryption'] ?? 'TLS')));
        $smtpUsername = trim((string)($_POST['smtp_username'] ?? ''));
        $smtpPassword = trim((string)($_POST['smtp_password'] ?? ''));
        $fromEmail = strtolower(trim((string)($_POST['from_email'] ?? '')));
        $fromName = trim((string)($_POST['from_name'] ?? ''));
        $replyToEmail = strtolower(trim((string)($_POST['reply_to_email'] ?? '')));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($smtpHost === '' || strlen($smtpHost) > 255 || $smtpPort < 1 || $smtpPort > 65535 || !in_array($smtpEncryption, ['TLS', 'SMTPS', 'NONE'], true)) {
            setFlashMessage('error', 'Enter valid SMTP server, port, and encryption settings.');
            header('Location: index.php#report-email');
            exit();
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || ($replyToEmail !== '' && !filter_var($replyToEmail, FILTER_VALIDATE_EMAIL))) {
            setFlashMessage('error', 'Enter a valid sender and reply-to email address.');
            header('Location: index.php#report-email');
            exit();
        }

        $existingStmt = mysqli_prepare($conn, 'SELECT smtp_username_encrypted,smtp_password_encrypted FROM report_delivery_settings WHERE business_id=? LIMIT 1');
        mysqli_stmt_bind_param($existingStmt, 'i', $businessId);
        mysqli_stmt_execute($existingStmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStmt));
        $encryptedUsername = $smtpUsername !== '' ? encryptReportCredential($smtpUsername) : ($existing['smtp_username_encrypted'] ?? null);
        $encryptedPassword = $smtpPassword !== '' ? encryptReportCredential($smtpPassword) : ($existing['smtp_password_encrypted'] ?? null);
        if ($isActive && (empty($encryptedUsername) || empty($encryptedPassword))) {
            setFlashMessage('error', 'Enter the SMTP username and API key or app password before enabling report email.');
            header('Location: index.php#report-email');
            exit();
        }

        $membershipId = (int)($_SESSION['membership_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "INSERT INTO report_delivery_settings
            (business_id,smtp_host,smtp_port,smtp_encryption,smtp_username_encrypted,smtp_password_encrypted,from_email,from_name,reply_to_email,is_active,configured_by_membership_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),smtp_username_encrypted=VALUES(smtp_username_encrypted),smtp_password_encrypted=VALUES(smtp_password_encrypted),from_email=VALUES(from_email),from_name=VALUES(from_name),reply_to_email=VALUES(reply_to_email),is_active=VALUES(is_active),configured_by_membership_id=VALUES(configured_by_membership_id),updated_at=UTC_TIMESTAMP(6)");
        mysqli_stmt_bind_param($stmt, 'isissssssii', $businessId, $smtpHost, $smtpPort, $smtpEncryption, $encryptedUsername, $encryptedPassword, $fromEmail, $fromName, $replyToEmail, $isActive, $membershipId);
        if (!mysqli_stmt_execute($stmt)) {
            error_log('Report email setting error: ' . mysqli_stmt_error($stmt));
            setFlashMessage('error', 'Unable to save the report email configuration.');
            header('Location: index.php#report-email');
            exit();
        }
        writeAuditLog($conn, $businessId, 'REPORT_EMAIL_SETTING_SAVED', 'report_delivery_setting', (string)$businessId, ['smtp_host' => $smtpHost, 'from_email' => $fromEmail, 'active' => (bool)$isActive]);
        setFlashMessage('success', 'Report email configuration saved securely.');
        header('Location: index.php#report-email');
        exit();

    case 'save_report_schedule':
        requirePermission($conn, $_SESSION['membership_id'] ?? null, $businessId, 'reports.schedule');
        if (!isBusinessOwner()) {
            http_response_code(403);
            setFlashMessage('error', 'Only the authenticated Business Owner can manage report schedules.');
            header('Location: index.php#report-settings');
            exit();
        }

        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $isNewSchedule = $scheduleId === 0;
        $name = trim((string)($_POST['name'] ?? ''));
        $frequency = strtoupper(trim((string)($_POST['frequency'] ?? '')));
        $sendTime = trim((string)($_POST['send_time'] ?? ''));
        $weekday = $frequency === 'WEEKLY' ? (int)($_POST['weekday'] ?? 0) : null;
        $dayOfMonth = $frequency === 'MONTHLY' ? (int)($_POST['day_of_month'] ?? 0) : null;
        $email = trim((string)($_POST['email'] ?? ''));
        $channels = ['EMAIL'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $validationError = null;
        if (strlen($name) < 2 || strlen($name) > 150) $validationError = 'Enter a schedule name between 2 and 150 characters.';
        elseif (!in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) $validationError = 'Choose Daily, Weekly, or Monthly.';
        elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $sendTime)) $validationError = 'Choose a valid report time.';
        elseif ($frequency === 'WEEKLY' && ($weekday < 1 || $weekday > 7)) $validationError = 'Choose a valid day of the week.';
        elseif ($frequency === 'MONTHLY' && ($dayOfMonth < 1 || $dayOfMonth > 31)) $validationError = 'Choose a valid day of the month.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $validationError = 'Enter a valid report recipient email address.';
        if ($validationError !== null) {
            setFlashMessage('error', $validationError);
            header('Location: index.php#report-settings');
            exit();
        }

        try {
            $timezoneStmt = mysqli_prepare($conn, 'SELECT timezone FROM businesses WHERE id=? LIMIT 1');
            mysqli_stmt_bind_param($timezoneStmt, 'i', $businessId);
            mysqli_stmt_execute($timezoneStmt);
            $timezone = mysqli_fetch_assoc(mysqli_stmt_get_result($timezoneStmt))['timezone'] ?? 'Africa/Johannesburg';
            $nextRun = calculateReportNextRun($frequency, $sendTime, $weekday, $dayOfMonth, $timezone);
            mysqli_begin_transaction($conn);

            if ($scheduleId > 0) {
                $ownership = mysqli_prepare($conn, 'SELECT id FROM report_schedules WHERE id=? AND business_id=? LIMIT 1 FOR UPDATE');
                mysqli_stmt_bind_param($ownership, 'ii', $scheduleId, $businessId);
                mysqli_stmt_execute($ownership);
                if (mysqli_num_rows(mysqli_stmt_get_result($ownership)) !== 1) throw new RuntimeException('Report schedule not found.');
                $scheduleStmt = mysqli_prepare($conn, "UPDATE report_schedules SET name=?,frequency=?,weekday=?,day_of_month=?,send_time=?,timezone=?,report_format='CSV',is_active=?,next_run_at=? WHERE id=? AND business_id=?");
                mysqli_stmt_bind_param($scheduleStmt, 'ssiissisii', $name, $frequency, $weekday, $dayOfMonth, $sendTime, $timezone, $isActive, $nextRun, $scheduleId, $businessId);
            } else {
                $membershipId = (int)($_SESSION['membership_id'] ?? 0);
                $scheduleStmt = mysqli_prepare($conn, "INSERT INTO report_schedules (business_id,name,frequency,weekday,day_of_month,send_time,timezone,report_format,is_active,next_run_at,created_by_membership_id) VALUES (?,?,?,?,?,?,?,'CSV',?,?,?)");
                mysqli_stmt_bind_param($scheduleStmt, 'issiissisi', $businessId, $name, $frequency, $weekday, $dayOfMonth, $sendTime, $timezone, $isActive, $nextRun, $membershipId);
            }
            if (!mysqli_stmt_execute($scheduleStmt)) throw new RuntimeException(mysqli_stmt_error($scheduleStmt));
            if ($isNewSchedule) $scheduleId = mysqli_insert_id($conn);

            $deleteStmt = mysqli_prepare($conn, 'DELETE FROM report_schedule_recipients WHERE business_id=? AND report_schedule_id=?');
            mysqli_stmt_bind_param($deleteStmt, 'ii', $businessId, $scheduleId);
            mysqli_stmt_execute($deleteStmt);
            $recipientStmt = mysqli_prepare($conn, 'INSERT INTO report_schedule_recipients (business_id,report_schedule_id,channel,destination) VALUES (?,?,?,?)');
            foreach ($channels as $channel) {
                $destination = $email;
                mysqli_stmt_bind_param($recipientStmt, 'iiss', $businessId, $scheduleId, $channel, $destination);
                if (!mysqli_stmt_execute($recipientStmt)) throw new RuntimeException(mysqli_stmt_error($recipientStmt));
            }
            mysqli_commit($conn);
            writeAuditLog($conn, $businessId, $isNewSchedule ? 'REPORT_SCHEDULE_CREATED' : 'REPORT_SCHEDULE_SAVED', 'report_schedule', $scheduleId, ['frequency' => $frequency, 'channels' => $channels]);
            setFlashMessage('success', 'Automated report schedule saved.');
        } catch (Throwable $error) {
            @mysqli_rollback($conn);
            error_log('Report schedule settings error: ' . $error->getMessage());
            $message = stripos($error->getMessage(), 'Duplicate') !== false ? 'A report schedule with that name already exists.' : 'Unable to save the report schedule.';
            setFlashMessage('error', $message);
        }
        header('Location: index.php#report-settings');
        exit();

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
        $tax_number = normalizeOptionalText($_POST['tax_number'] ?? null);
        $registration_number = normalizeOptionalText($_POST['registration_number'] ?? null);
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
        $fiscal_year_start_month = (int)($_POST['fiscal_year_start_month'] ?? 1);
        $allow_negative_stock = isset($_POST['allow_negative_stock']) ? 1 : 0;

        if (!in_array($inventory_valuation_method, ['WEIGHTED_AVERAGE', 'FIFO'], true)) {
            setFlashMessage('error', 'Invalid inventory valuation method selected.');
            header("Location: index.php" . $role_query);
            exit();
        }
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
            SET inventory_valuation_method = ?, fiscal_year_start_month = ?,
                allow_negative_stock = ?, updated_at = NOW(6)
            WHERE business_id = ?
        ";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param(
            $stmt,
            'siii',
            $inventory_valuation_method,
            $fiscal_year_start_month, $allow_negative_stock, $businessId
        );

        mysqli_begin_transaction($conn);
        try {
            if ($inventory_valuation_method === 'FIFO' && ($old['inventory_valuation_method'] ?? 'WEIGHTED_AVERAGE') !== 'FIFO') {
                // Establish a transparent current-cost FIFO snapshot when changing methods.
                // Existing layers remain as history but no longer carry quantity.
                $clearLayers = mysqli_prepare($conn, 'UPDATE inventory_cost_layers SET remaining_quantity=0 WHERE business_id=? AND remaining_quantity<>0');
                mysqli_stmt_bind_param($clearLayers, 'i', $businessId);
                mysqli_stmt_execute($clearLayers);
                $balances = mysqli_prepare($conn, 'SELECT ib.location_id,ib.product_id,ib.quantity_on_hand,ib.average_unit_cost,p.track_batches FROM inventory_balances ib JOIN products p ON p.id=ib.product_id AND p.business_id=ib.business_id WHERE ib.business_id=? AND ib.quantity_on_hand>0 FOR UPDATE');
                mysqli_stmt_bind_param($balances, 'i', $businessId);
                mysqli_stmt_execute($balances);
                $balanceRows = mysqli_stmt_get_result($balances);
                while ($balance = mysqli_fetch_assoc($balanceRows)) {
                    if ((int)$balance['track_batches'] === 1) {
                        $batchBalances = mysqli_prepare($conn, 'SELECT bib.batch_id,bib.quantity_on_hand FROM batch_inventory_balances bib JOIN product_batches pb ON pb.id=bib.batch_id AND pb.business_id=bib.business_id WHERE bib.business_id=? AND bib.location_id=? AND pb.product_id=? AND bib.quantity_on_hand>0 FOR UPDATE');
                        mysqli_stmt_bind_param($batchBalances, 'iii', $businessId, $balance['location_id'], $balance['product_id']);
                        mysqli_stmt_execute($batchBalances);
                        $batchRows = mysqli_stmt_get_result($batchBalances);
                        $batchTotal = 0.0;
                        while ($batch = mysqli_fetch_assoc($batchRows)) {
                            $snapshot = mysqli_prepare($conn, 'INSERT INTO inventory_cost_layers (business_id,location_id,product_id,batch_id,purchase_item_id,received_at,original_quantity,remaining_quantity,unit_cost,created_at) VALUES (?,?,?,?,NULL,UTC_TIMESTAMP(6),?,?,?,UTC_TIMESTAMP(6))');
                            mysqli_stmt_bind_param($snapshot, 'iiiiddd', $businessId, $balance['location_id'], $balance['product_id'], $batch['batch_id'], $batch['quantity_on_hand'], $batch['quantity_on_hand'], $balance['average_unit_cost']);
                            mysqli_stmt_execute($snapshot);
                            $batchTotal += (float)$batch['quantity_on_hand'];
                        }
                        if (abs($batchTotal - (float)$balance['quantity_on_hand']) > 0.00005) throw new RuntimeException('Batch quantities must reconcile before switching to FIFO.');
                    } else {
                        $snapshot = mysqli_prepare($conn, 'INSERT INTO inventory_cost_layers (business_id,location_id,product_id,batch_id,purchase_item_id,received_at,original_quantity,remaining_quantity,unit_cost,created_at) VALUES (?,?,?,NULL,NULL,UTC_TIMESTAMP(6),?,?,?,UTC_TIMESTAMP(6))');
                        mysqli_stmt_bind_param($snapshot, 'iiiddd', $businessId, $balance['location_id'], $balance['product_id'], $balance['quantity_on_hand'], $balance['quantity_on_hand'], $balance['average_unit_cost']);
                        mysqli_stmt_execute($snapshot);
                    }
                }
            }
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Failed to update accounting parameters.');
            writeAuditLog($conn, $businessId, 'BUSINESS_ACCOUNTING_SETTINGS_UPDATED', 'business', $businessId, [
                'inventory_valuation_method' => $inventory_valuation_method,
                'fiscal_year_start_month' => $fiscal_year_start_month,
                'allow_negative_stock' => $allow_negative_stock
            ], $old);
            mysqli_commit($conn);
            setFlashMessage('success', 'Accounting parameters updated successfully.');
        } catch (Throwable $error) {
            mysqli_rollback($conn);
            error_log('Accounting settings update failed: ' . $error->getMessage());
            setFlashMessage('error', $error->getMessage());
        }
        header("Location: index.php" . $role_query);
        exit();

    default:
        setFlashMessage('error', 'Invalid action.');
        header("Location: index.php" . $role_query);
        exit();
}
?>
