<?php
$page_title = 'Settings';
require_once __DIR__ . '/../../includes/header.php';
/** @var mysqli $conn */
$conn = getDatabaseConnection();

$permissions = require __DIR__ . '/permissions.php';
$businessId = (int)($_SESSION['active_business_id'] ?? 0);
$membershipId = $_SESSION['membership_id'] ?? null;
requirePermission($conn, $membershipId, $businessId, $permissions['view']);

if (isSuperAdmin() && getEffectiveUserRole() === 'super_admin') {
    ?>
    <div class="settings-shell">
      <div class="settings-page-header"><div><h1>Settings</h1><p>Business configuration is managed securely by each Business Owner.</p></div></div>
      <section class="card settings-empty-state"><h2>No platform configuration required</h2><p>Email credentials and report schedules belong to each business and are isolated from other companies.</p></section>
    </div>
    <?php require_once __DIR__ . '/../../includes/footer.php'; exit();
}

$canUpdateSettings = hasPermission($conn, $membershipId, $businessId, $permissions['update']);
$canManageOwnerSettings = isBusinessOwner() && !isRolePreviewActive();
$canViewReportSettings = (isBusinessOwner() || getEffectiveUserRole() === 'owner')
    && hasPermission($conn, $membershipId, $businessId, 'reports.schedule');
$canManageReportSettings = $canViewReportSettings && $canManageOwnerSettings;

$businessStmt = mysqli_prepare($conn, 'SELECT * FROM businesses WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($businessStmt, 'i', $businessId);
mysqli_stmt_execute($businessStmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($businessStmt));
if (!$business) {
    echo '<div class="alert-msg error">Business settings are unavailable.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit();
}

$accountingStmt = mysqli_prepare($conn, 'SELECT * FROM business_accounting_settings WHERE business_id=? LIMIT 1');
mysqli_stmt_bind_param($accountingStmt, 'i', $businessId);
mysqli_stmt_execute($accountingStmt);
$accounting = mysqli_fetch_assoc(mysqli_stmt_get_result($accountingStmt));
if (!$accounting) {
    $createAccounting = mysqli_prepare($conn, "INSERT INTO business_accounting_settings (business_id,inventory_valuation_method,default_tax_rate,fiscal_year_start_month,allow_negative_stock,created_at,updated_at) VALUES (?,'WEIGHTED_AVERAGE',0,1,0,NOW(6),NOW(6))");
    mysqli_stmt_bind_param($createAccounting, 'i', $businessId);
    mysqli_stmt_execute($createAccounting);
    mysqli_stmt_execute($accountingStmt);
    $accounting = mysqli_fetch_assoc(mysqli_stmt_get_result($accountingStmt));
}

$emailSetting = null;
$emailStmt = mysqli_prepare($conn, 'SELECT smtp_host,smtp_port,smtp_encryption,from_email,from_name,reply_to_email,is_active,smtp_username_encrypted IS NOT NULL has_username,smtp_password_encrypted IS NOT NULL has_password,updated_at FROM report_delivery_settings WHERE business_id=? LIMIT 1');
mysqli_stmt_bind_param($emailStmt, 'i', $businessId);
mysqli_stmt_execute($emailStmt);
$emailSetting = mysqli_fetch_assoc(mysqli_stmt_get_result($emailStmt));
$emailReady = $emailSetting && (int)$emailSetting['is_active'] === 1 && !empty($emailSetting['has_username']) && !empty($emailSetting['has_password']);

$reportSchedules = [];
if ($canViewReportSettings) {
    $scheduleStmt = mysqli_prepare($conn, "SELECT rs.*,(SELECT rr.destination FROM report_schedule_recipients rr WHERE rr.business_id=rs.business_id AND rr.report_schedule_id=rs.id AND rr.channel='EMAIL' LIMIT 1) email_destination FROM report_schedules rs WHERE rs.business_id=? ORDER BY rs.created_at DESC");
    mysqli_stmt_bind_param($scheduleStmt, 'i', $businessId);
    mysqli_stmt_execute($scheduleStmt);
    $scheduleResult = mysqli_stmt_get_result($scheduleStmt);
    while ($schedule = mysqli_fetch_assoc($scheduleResult)) $reportSchedules[] = $schedule;
}

$csrfToken = generateCsrfToken();
$roleQuery = getRolePreviewQuery();
$companyLogoUrl = getCompanyLogoUrl($business['company_logo_path'] ?? null, getRootPrefix());
?>

<div class="settings-shell">
  <div class="settings-page-header">
    <div><h1>Business Settings</h1><p>Manage company information, financial rules, report email, and automation.</p></div>
    <?php if (isRolePreviewActive()): ?><span class="settings-pill">Preview only</span><?php endif; ?>
  </div>

  <nav class="settings-nav" aria-label="Settings sections" role="tablist">
    <a href="#company-identity" role="tab">Company</a><a href="#business-profile" role="tab">Business profile</a><a href="#accounting-settings" role="tab">Accounting</a>
    <?php if ($canViewReportSettings): ?><a href="#report-email" role="tab">Report email</a><a href="#report-settings" role="tab">Scheduling</a><?php endif; ?>
  </nav>

  <section class="card settings-section" id="company-identity">
    <div class="settings-section-heading"><div><h2>Company identity</h2><p>The name and logo displayed in the application header.</p></div></div>
    <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" enctype="multipart/form-data" class="settings-form identity-layout" id="companyIdentityForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="update_company_identity">
      <div class="company-logo-panel">
        <div class="company-logo-preview" id="settingsCompanyLogoPreview"><?php if ($companyLogoUrl): ?><img src="<?php echo e($companyLogoUrl); ?>" alt="<?php echo e($business['business_name']); ?> logo"><?php else: ?><span><?php echo e(strtoupper(substr($business['business_name'],0,1))); ?></span><?php endif; ?></div>
        <div><strong>Company logo</strong><p>JPG, PNG, or WEBP up to 3 MB.</p><?php if ($canManageOwnerSettings): ?><input type="file" name="company_logo" id="settingsCompanyLogo" accept="image/jpeg,image/png,image/webp"><?php endif; ?></div>
      </div>
      <div class="setting-field"><label for="company-name">Company name</label><input type="text" name="business_name" id="company-name" value="<?php echo e($business['business_name']); ?>" maxlength="200" required <?php echo $canManageOwnerSettings ? '' : 'readonly'; ?>></div>
      <?php if ($canManageOwnerSettings): ?><div class="settings-form-actions"><button class="btn-primary" type="submit" id="companyIdentityBtn">Save company identity</button></div><?php endif; ?>
    </form>
  </section>

  <section class="card settings-section" id="business-profile">
    <div class="settings-section-heading"><div><h2>Business profile</h2><p>Contact, registration, and operating information.</p></div></div>
    <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" class="settings-form settings-form-grid" id="profileForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="update_profile">
      <div class="setting-field"><label>Legal or trade name</label><input type="text" name="legal_name" value="<?php echo e($business['legal_name'] ?? ''); ?>"></div>
      <div class="setting-field"><label>Business phone</label><input type="tel" name="phone" value="<?php echo e($business['phone']); ?>" required></div>
      <div class="setting-field"><label>Business email</label><input type="email" name="email" value="<?php echo e($business['email'] ?? ''); ?>" required></div>
      <div class="setting-field"><label>Tax identification number</label><input type="text" name="tax_number" value="<?php echo e($business['tax_number'] ?? ''); ?>"></div>
      <div class="setting-field"><label>Registration number</label><input type="text" name="registration_number" value="<?php echo e($business['registration_number'] ?? ''); ?>"></div>
      <div class="setting-field"><label>Country</label><select name="country_code"><option value="RW" <?php echo $business['country_code']==='RW'?'selected':''; ?>>Rwanda</option><option value="ZA" <?php echo $business['country_code']==='ZA'?'selected':''; ?>>South Africa</option><option value="UG" <?php echo $business['country_code']==='UG'?'selected':''; ?>>Uganda</option><option value="KE" <?php echo $business['country_code']==='KE'?'selected':''; ?>>Kenya</option><option value="TZ" <?php echo $business['country_code']==='TZ'?'selected':''; ?>>Tanzania</option></select></div>
      <div class="setting-field"><label>City</label><input type="text" name="city" value="<?php echo e($business['city'] ?? ''); ?>" required></div>
      <div class="setting-field"><label>Business address</label><input type="text" name="address_line1" value="<?php echo e($business['address_line1'] ?? ''); ?>" required></div>
      <div class="setting-field full-field"><label>Business operations summary</label><textarea name="summary" required><?php echo e($business['summary'] ?? ''); ?></textarea></div>
      <?php if ($canUpdateSettings): ?><div class="settings-form-actions full-field"><button class="btn-primary" type="submit" id="profileSaveBtn">Save business profile</button></div><?php endif; ?>
    </form>
  </section>

  <section class="card settings-section" id="accounting-settings">
    <div class="settings-section-heading"><div><h2>Accounting and inventory</h2><p>Core costing, tax, fiscal year, and stock controls.</p></div></div>
    <form action="backend.php<?php echo e($roleQuery); ?>" method="POST" class="settings-form settings-form-grid" id="acctForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="update_accounting">
      <div class="setting-field"><label>Inventory valuation</label><select name="inventory_valuation_method" required><option value="WEIGHTED_AVERAGE" <?php echo $accounting['inventory_valuation_method']==='WEIGHTED_AVERAGE'?'selected':''; ?>>Weighted average</option><option value="FIFO" <?php echo $accounting['inventory_valuation_method']==='FIFO'?'selected':''; ?>>First-in, first-out</option></select></div>
      <div class="setting-field"><label>Default tax rate (%)</label><input type="number" name="default_tax_rate" step="0.0001" min="0" max="100" value="<?php echo e((float)$accounting['default_tax_rate']); ?>" required></div>
      <div class="setting-field"><label>Fiscal year starts</label><select name="fiscal_year_start_month"><?php foreach ([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $number=>$month): ?><option value="<?php echo $number; ?>" <?php echo (int)$accounting['fiscal_year_start_month']===$number?'selected':''; ?>><?php echo e($month); ?></option><?php endforeach; ?></select></div>
      <label class="setting-check"><input type="checkbox" name="allow_negative_stock" value="1" <?php echo !empty($accounting['allow_negative_stock'])?'checked':''; ?>><span><strong>Allow negative inventory</strong><small>Permit sales when recorded stock is insufficient.</small></span></label>
      <?php if ($canUpdateSettings): ?><div class="settings-form-actions full-field"><button class="btn-primary" type="submit" id="accountingSaveBtn">Save accounting settings</button></div><?php endif; ?>
    </form>
  </section>

  <?php if ($canViewReportSettings): ?>
  <section class="card settings-section" id="report-email">
    <div class="settings-section-heading"><div><h2>Report email</h2><p>Secure SMTP credentials used by PHPMailer for automated reports.</p></div><span class="settings-pill <?php echo $emailReady?'ready-pill':''; ?>"><?php echo $emailReady?'Ready':'Not configured'; ?></span></div>
    <form action="backend.php" method="POST" class="settings-form settings-form-grid" id="reportEmailForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="save_report_email_setting">
      <div class="setting-field"><label>Email provider preset</label><select id="smtp-preset" <?php echo $canManageReportSettings?'':'disabled'; ?>><option value="custom">Custom SMTP</option><option value="gmail">Gmail / Google Workspace</option><option value="outlook">Microsoft Outlook / 365</option></select></div>
      <div class="setting-field"><label>SMTP host</label><input type="text" name="smtp_host" id="smtp-host" value="<?php echo e($emailSetting['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com" required <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <div class="setting-field"><label>SMTP port</label><input type="number" name="smtp_port" id="smtp-port" min="1" max="65535" value="<?php echo e($emailSetting['smtp_port'] ?? 587); ?>" required <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <div class="setting-field"><label>Encryption</label><select name="smtp_encryption" id="smtp-encryption" <?php echo $canManageReportSettings?'':'disabled'; ?>><option value="TLS" <?php echo ($emailSetting['smtp_encryption']??'TLS')==='TLS'?'selected':''; ?>>STARTTLS</option><option value="SMTPS" <?php echo ($emailSetting['smtp_encryption']??'')==='SMTPS'?'selected':''; ?>>Implicit TLS / SSL</option><option value="NONE" <?php echo ($emailSetting['smtp_encryption']??'')==='NONE'?'selected':''; ?>>None</option></select></div>
      <div class="setting-field"><label>SMTP username</label><input type="text" name="smtp_username" autocomplete="username" placeholder="<?php echo !empty($emailSetting['has_username'])?'Saved securely — enter only to replace':'Usually your email address'; ?>" <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <div class="setting-field"><label>API key / app password</label><input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?php echo !empty($emailSetting['has_password'])?'Saved securely — enter only to replace':'Provider API key or app password'; ?>" <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <div class="setting-field"><label>Sender email</label><input type="email" name="from_email" value="<?php echo e($emailSetting['from_email'] ?? ($business['email'] ?? '')); ?>" required <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <div class="setting-field"><label>Sender name</label><input type="text" name="from_name" value="<?php echo e($emailSetting['from_name'] ?? $business['business_name']); ?>" maxlength="200" <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <div class="setting-field"><label>Reply-to email</label><input type="email" name="reply_to_email" value="<?php echo e($emailSetting['reply_to_email'] ?? ''); ?>" placeholder="Optional" <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
      <label class="setting-check"><input type="checkbox" name="is_active" <?php echo !empty($emailSetting['is_active'])?'checked':''; ?> <?php echo $canManageReportSettings?'':'disabled'; ?>><span><strong>Enable scheduled report email</strong><small>Reports send only when this setting is enabled.</small></span></label>
      <?php if ($canManageReportSettings): ?><div class="settings-form-actions full-field"><button class="btn-primary" type="submit">Save report email</button></div><?php endif; ?>
    </form>
    <div class="settings-note">Credentials are encrypted before storage. For Gmail, use an App Password rather than your normal account password.</div>
  </section>

  <section class="card settings-section" id="report-settings">
    <div class="settings-section-heading"><div><h2>Report scheduling</h2><p>Send the complete business report automatically by email.</p></div></div>
    <div class="schedule-layout">
      <form action="backend.php" method="POST" class="settings-form schedule-form" id="scheduleForm">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>"><input type="hidden" name="action" value="save_report_schedule"><input type="hidden" name="schedule_id" id="schedule-id">
        <div class="setting-field full-field"><label>Schedule name</label><input type="text" name="name" id="schedule-name" maxlength="150" placeholder="Monthly management report" required <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
        <div class="setting-field"><label>Frequency</label><select name="frequency" id="schedule-frequency" <?php echo $canManageReportSettings?'':'disabled'; ?>><option value="DAILY">Daily</option><option value="WEEKLY">Weekly</option><option value="MONTHLY">Monthly</option></select></div>
        <div class="setting-field"><label>Send time</label><input type="time" name="send_time" id="schedule-time" value="08:00" required <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
        <div class="setting-field" id="weekly-field" hidden><label>Day of week</label><select name="weekday" id="schedule-weekday" <?php echo $canManageReportSettings?'':'disabled'; ?>><option value="1">Monday</option><option value="2">Tuesday</option><option value="3">Wednesday</option><option value="4">Thursday</option><option value="5">Friday</option><option value="6">Saturday</option><option value="7">Sunday</option></select></div>
        <div class="setting-field" id="monthly-field" hidden><label>Day of month</label><input type="number" name="day_of_month" id="schedule-month-day" min="1" max="31" value="1" <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
        <div class="setting-field full-field"><label>Recipient email</label><input type="email" name="email" id="schedule-email" value="<?php echo e($business['email'] ?? ''); ?>" required <?php echo $canManageReportSettings?'':'disabled'; ?>></div>
        <label class="setting-check full-field"><input type="checkbox" name="is_active" id="schedule-active" checked <?php echo $canManageReportSettings?'':'disabled'; ?>><span><strong>Schedule active</strong><small>The worker waits until report email is configured.</small></span></label>
        <?php if ($canManageReportSettings): ?><div class="settings-form-actions full-field"><button class="btn-primary" type="submit">Save schedule</button><button class="btn-secondary" type="button" id="clearSchedule">Clear</button></div><?php endif; ?>
      </form>
      <div class="schedule-list">
        <?php if (!$reportSchedules): ?><div class="settings-empty-inline">No report schedules configured.</div><?php endif; ?>
        <?php foreach ($reportSchedules as $schedule): ?>
          <?php
            $frequencyLabel=ucfirst(strtolower($schedule['frequency']));
            $weekdays=[1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'];
            if($schedule['frequency']==='WEEKLY')$frequencyLabel.=' · '.($weekdays[(int)$schedule['weekday']]??'');
            if($schedule['frequency']==='MONTHLY')$frequencyLabel.=' · day '.(int)$schedule['day_of_month'];
            $nextLabel='Not scheduled';
            if($schedule['next_run_at']){try{$next=new DateTime((string)$schedule['next_run_at'],new DateTimeZone('UTC'));$next->setTimezone(new DateTimeZone($schedule['timezone']));$nextLabel=$next->format('d M Y, H:i');}catch(Throwable $ignored){}}
          ?>
          <article class="schedule-item"><div><strong><?php echo e($schedule['name']); ?></strong><p><?php echo e($frequencyLabel); ?> at <?php echo e(substr($schedule['send_time'],0,5)); ?></p><small><?php echo e($schedule['email_destination'] ?: 'Email required'); ?> · Next: <?php echo e($nextLabel); ?></small></div><div class="schedule-item-actions"><span class="settings-pill <?php echo (int)$schedule['is_active']===1?'ready-pill':''; ?>"><?php echo (int)$schedule['is_active']===1?'Active':'Paused'; ?></span><?php if($schedule['frequency']!=='YEARLY'&&$canManageReportSettings): ?><button type="button" class="btn-secondary schedule-edit" data-schedule="<?php echo e(json_encode($schedule)); ?>">Edit</button><?php endif; ?></div></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>
</div>

<style>
.settings-shell{max-width:1180px;margin:0 auto}.settings-page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:14px}.settings-page-header h1{margin:0;color:var(--text);font-size:21px}.settings-page-header p,.settings-section-heading p{margin:5px 0 0;color:var(--text3);font-size:11px;line-height:1.5}.settings-nav{display:flex;gap:6px;overflow-x:auto;margin-bottom:14px;padding:6px;border-radius:var(--radius-lg);background:var(--card)}.settings-nav a{padding:8px 11px;border-radius:var(--radius);color:var(--text2);font-size:10px;font-weight:600;text-decoration:none;white-space:nowrap}.settings-nav a:hover{background:var(--bg);color:var(--text)}.settings-nav a.active{background:var(--bg);color:var(--text)}.settings-section{margin-bottom:14px;overflow:hidden;box-shadow:none;scroll-margin-top:74px}.settings-section[hidden]{display:none!important}.settings-section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 18px;border-bottom:1px solid var(--table-border)}.settings-section-heading h2{margin:0;color:var(--text);font-size:15px}.settings-form{padding:18px}.settings-form-grid,.schedule-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.identity-layout{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(280px,1.2fr);gap:18px;align-items:center}.company-logo-panel{display:flex;align-items:center;gap:14px}.company-logo-panel p{margin:4px 0 8px;color:var(--text3);font-size:10px}.company-logo-preview{width:70px;height:70px;border-radius:var(--radius-lg);background:var(--orange-light);display:grid;place-items:center;overflow:hidden;color:var(--orange);font-size:22px;font-weight:700;flex:0 0 auto}.company-logo-preview img{width:100%;height:100%;object-fit:contain}.setting-field{min-width:0}.setting-field label{display:block;margin-bottom:6px;color:var(--text2);font-size:10px;font-weight:600}.setting-field input,.setting-field select,.setting-field textarea{width:100%;min-height:39px;padding:9px 10px;border:1px solid var(--border);border-radius:var(--radius);outline:none;background:var(--card);color:var(--text);font:inherit}.setting-field textarea{min-height:78px;resize:vertical}.setting-field input:focus,.setting-field select:focus,.setting-field textarea:focus{border-color:var(--border-hover)}.full-field{grid-column:1/-1}.settings-form-actions{display:flex;align-items:center;gap:8px;margin-top:2px}.settings-form-actions .btn-primary,.settings-form-actions .btn-secondary{width:auto}.btn-secondary{padding:8px 11px;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);color:var(--text2);font:inherit;font-size:10px;cursor:pointer}.setting-check{display:flex;align-items:flex-start;gap:9px;padding:11px;border-radius:var(--radius);background:var(--bg);color:var(--text2);font-size:10px}.setting-check input{margin-top:2px;accent-color:var(--orange)}.setting-check strong,.setting-check small{display:block}.setting-check small{margin-top:3px;color:var(--text3);font-weight:400}.settings-pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:var(--bg);color:var(--text2);font-size:9px;font-weight:650;white-space:nowrap}.ready-pill{background:var(--green-bg);color:var(--green)}.settings-note{margin:0 18px 18px;padding:11px 13px;border-radius:var(--radius);background:var(--bg);color:var(--text2);font-size:10px;line-height:1.5}.schedule-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,.8fr);align-items:start}.schedule-form{border-right:1px solid var(--table-border)}.schedule-list{max-height:500px;overflow:auto;padding:5px 18px 18px}.schedule-item{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:14px 0;border-bottom:1px solid var(--table-border)}.schedule-item:last-child{border-bottom:0}.schedule-item strong{color:var(--text);font-size:11px}.schedule-item p{margin:5px 0;color:var(--text2);font-size:10px}.schedule-item small{color:var(--text3);font-size:9px;line-height:1.5}.schedule-item-actions{display:flex;align-items:center;gap:7px;flex-shrink:0}.settings-empty-state{padding:35px;text-align:center;box-shadow:none}.settings-empty-state h2{font-size:16px}.settings-empty-state p,.settings-empty-inline{color:var(--text3);font-size:11px}.settings-empty-inline{padding:28px;text-align:center}.settings-section input:disabled,.settings-section select:disabled,.settings-section textarea:disabled{opacity:.65;cursor:not-allowed}@media(max-width:900px){.identity-layout,.schedule-layout{grid-template-columns:1fr}.schedule-form{border-right:0;border-bottom:1px solid var(--table-border)}}@media(max-width:620px){.settings-form-grid,.schedule-form,.identity-layout{grid-template-columns:1fr}.full-field{grid-column:1}.settings-section-heading,.settings-page-header{align-items:flex-start}.company-logo-panel{align-items:flex-start}.schedule-item{flex-direction:column}.schedule-item-actions{width:100%;justify-content:space-between}}
</style>

<script>
const settingsLinks=Array.from(document.querySelectorAll('.settings-nav a[href^="#"]'));
const settingsPanels=Array.from(document.querySelectorAll('.settings-section[id]'));
function showSettingsPanel(hash){
  const requested=(hash||'').replace(/^#/,'');
  const selected=settingsPanels.some(function(panel){return panel.id===requested;})?requested:(settingsPanels[0]?.id||'');
  settingsPanels.forEach(function(panel){panel.hidden=panel.id!==selected;});
  settingsLinks.forEach(function(link){const active=link.getAttribute('href')==='#'+selected;link.classList.toggle('active',active);link.setAttribute('aria-selected',active?'true':'false');link.setAttribute('tabindex',active?'0':'-1');});
}
settingsLinks.forEach(function(link){link.addEventListener('click',function(event){event.preventDefault();history.pushState(null,'',this.getAttribute('href'));showSettingsPanel(this.hash);});});
window.addEventListener('popstate',function(){showSettingsPanel(window.location.hash);});
window.addEventListener('hashchange',function(){showSettingsPanel(window.location.hash);});
showSettingsPanel(window.location.hash);
<?php if (!$canUpdateSettings): ?>document.querySelectorAll('#profileForm input:not([type="hidden"]),#profileForm select,#profileForm textarea,#acctForm input:not([type="hidden"]),#acctForm select,#acctForm textarea').forEach(function(element){element.disabled=true;});<?php endif; ?>
const logoInput=document.getElementById('settingsCompanyLogo');const logoPreview=document.getElementById('settingsCompanyLogoPreview');if(logoInput&&logoPreview){logoInput.addEventListener('change',function(){const file=this.files[0];if(!file)return;if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>3*1024*1024){this.value='';alert('Choose a JPG, PNG, or WEBP logo no larger than 3 MB.');return;}const url=URL.createObjectURL(file);const image=document.createElement('img');image.src=url;image.alt='Selected company logo';image.onload=function(){URL.revokeObjectURL(url)};logoPreview.replaceChildren(image);});}
const preset=document.getElementById('smtp-preset');if(preset){preset.addEventListener('change',function(){const values={gmail:['smtp.gmail.com','587','TLS'],outlook:['smtp.office365.com','587','TLS']};if(!values[this.value])return;document.getElementById('smtp-host').value=values[this.value][0];document.getElementById('smtp-port').value=values[this.value][1];document.getElementById('smtp-encryption').value=values[this.value][2];});}
const scheduleForm=document.getElementById('scheduleForm');if(scheduleForm){const frequency=document.getElementById('schedule-frequency');const weekly=document.getElementById('weekly-field');const monthly=document.getElementById('monthly-field');function syncFrequency(){weekly.hidden=frequency.value!=='WEEKLY';monthly.hidden=frequency.value!=='MONTHLY';}function clearSchedule(){scheduleForm.reset();document.getElementById('schedule-id').value='';document.getElementById('schedule-time').value='08:00';document.getElementById('schedule-active').checked=true;syncFrequency();}frequency.addEventListener('change',syncFrequency);document.getElementById('clearSchedule')?.addEventListener('click',clearSchedule);document.querySelectorAll('.schedule-edit').forEach(function(button){button.addEventListener('click',function(){let data;try{data=JSON.parse(this.dataset.schedule)}catch(error){return}document.getElementById('schedule-id').value=data.id||'';document.getElementById('schedule-name').value=data.name||'';frequency.value=data.frequency||'DAILY';document.getElementById('schedule-time').value=(data.send_time||'08:00').substring(0,5);document.getElementById('schedule-weekday').value=data.weekday||'1';document.getElementById('schedule-month-day').value=data.day_of_month||'1';document.getElementById('schedule-email').value=data.email_destination||'';document.getElementById('schedule-active').checked=Number(data.is_active)===1;syncFrequency();scheduleForm.scrollIntoView({behavior:'smooth',block:'center'});});});syncFrequency();}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
