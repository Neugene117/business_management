<?php
$page_title = 'System Settings';
require_once __DIR__ . '/../../includes/header.php';

$permissions = require __DIR__ . '/permissions.php';
requirePermission($conn, $_SESSION['membership_id'] ?? null, $_SESSION['active_business_id'] ?? null, $permissions['view']);

$businessId = $_SESSION['active_business_id'] ?? 0;
$canUpdateSettings = hasPermission($conn, $_SESSION['membership_id'] ?? null, $businessId, $permissions['update']);
$canManageCompanyInfo = isBusinessOwner() || getEffectiveUserRole() === 'owner';

// Fetch business details
$bizQuery = "SELECT * FROM businesses WHERE id = ? LIMIT 1";
$bStmt = mysqli_prepare($conn, $bizQuery);
mysqli_stmt_bind_param($bStmt, 'i', $businessId);
mysqli_stmt_execute($bStmt);
$biz = mysqli_fetch_assoc(mysqli_stmt_get_result($bStmt));

if (!$biz) {
    echo '<div class="alert-msg error">Business details not found.</div>';
    require_once __DIR__ . '/../../includes/footer.php';
    exit();
}

// Fetch accounting settings
$acctQuery = "SELECT * FROM business_accounting_settings WHERE business_id = ? LIMIT 1";
$aStmt = mysqli_prepare($conn, $acctQuery);
mysqli_stmt_bind_param($aStmt, 'i', $businessId);
mysqli_stmt_execute($aStmt);
$acct = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));

// Ensure accounting settings row exists
if (!$acct) {
    $ins = "INSERT INTO business_accounting_settings (business_id, inventory_valuation_method, default_tax_rate, fiscal_year_start_month, allow_negative_stock, created_at, updated_at) VALUES (?, 'WEIGHTED_AVERAGE', 0.00, 1, 0, NOW(6), NOW(6))";
    $iStmt = mysqli_prepare($conn, $ins);
    mysqli_stmt_bind_param($iStmt, 'i', $businessId);
    mysqli_stmt_execute($iStmt);
    
    // refetch
    mysqli_stmt_execute($aStmt);
    $acct = mysqli_fetch_assoc(mysqli_stmt_get_result($aStmt));
}

$csrfToken = generateCsrfToken();
$role_query = getRolePreviewQuery();
$companyLogoUrl = getCompanyLogoUrl($biz['company_logo_path'] ?? null, getRootPrefix());
?>

<section class="card company-information-card">
  <div class="card-header">
    <div>
      <div class="card-title">Company Information Management</div>
      <p class="company-information-subtitle">Manage the company identity displayed to every user in this business workspace.</p>
    </div>
  </div>
  <form action="backend.php<?php echo e($role_query); ?>" method="POST" enctype="multipart/form-data" class="company-information-form" id="companyIdentityForm">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="action" value="update_company_identity">

    <div class="company-logo-manager">
      <div class="company-logo-large" id="settingsCompanyLogoPreview">
        <?php if ($companyLogoUrl): ?>
          <img src="<?php echo e($companyLogoUrl); ?>" alt="<?php echo e($biz['business_name']); ?> logo">
        <?php else: ?>
          <span><?php echo e(strtoupper(substr($biz['business_name'], 0, 1))); ?></span>
        <?php endif; ?>
      </div>
      <div>
        <strong>Company Logo</strong>
        <p>JPG, PNG, or WEBP up to 3 MB. The logo will appear in the system header for every company user.</p>
        <?php if ($canManageCompanyInfo): ?>
          <input type="file" name="company_logo" id="settingsCompanyLogo" accept="image/jpeg,image/png,image/webp">
        <?php endif; ?>
      </div>
    </div>

    <div class="company-name-manager field">
      <label for="company_name">Company Name <span style="color:var(--red);">*</span></label>
      <div class="field-wrap">
        <input type="text" name="business_name" id="company_name" value="<?php echo e($biz['business_name']); ?>" required <?php echo $canManageCompanyInfo ? '' : 'readonly'; ?>>
      </div>
      <p>This name is shown in the top header for the Business Owner and all employees in this company.</p>
    </div>

    <?php if ($canManageCompanyInfo): ?>
      <button type="submit" class="btn-primary company-information-save" id="companyIdentityBtn">Save Company Information</button>
    <?php else: ?>
      <div class="company-information-readonly">Company identity can only be changed by the Business Owner.</div>
    <?php endif; ?>
  </form>
</section>

<div class="settings-grid">
  
  <!-- Left Column: Business Profile parameters -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Business Profile Parameters</div>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="padding: 20px;" id="profileForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
      <input type="hidden" name="action" value="update_profile">

      <div class="field" style="margin-bottom: 12px;">
        <label for="legal_name">Legal Name / Trade Name</label>
        <div class="field-wrap">
          <input type="text" name="legal_name" id="legal_name" value="<?php echo e($biz['legal_name'] ?? ''); ?>">
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="biz_phone">Business Phone <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="tel" name="phone" id="biz_phone" value="<?php echo e($biz['phone']); ?>" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="biz_email">Business Email <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="email" name="email" id="biz_email" value="<?php echo e($biz['email'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="tax_num">Tax Identification Number (TIN)</label>
        <div class="field-wrap">
          <input type="text" name="tax_number" id="tax_num" value="<?php echo e($biz['tax_number'] ?? ''); ?>">
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="reg_num">RDB Registration Number</label>
        <div class="field-wrap">
          <input type="text" name="registration_number" id="reg_num" value="<?php echo e($biz['registration_number'] ?? ''); ?>">
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="country_code">Country</label>
        <div class="field-wrap">
          <select name="country_code" id="country_code">
            <option value="RW" <?php echo ($biz['country_code'] === 'RW') ? 'selected' : ''; ?>>Rwanda</option>
            <option value="UG" <?php echo ($biz['country_code'] === 'UG') ? 'selected' : ''; ?>>Uganda</option>
            <option value="KE" <?php echo ($biz['country_code'] === 'KE') ? 'selected' : ''; ?>>Kenya</option>
            <option value="TZ" <?php echo ($biz['country_code'] === 'TZ') ? 'selected' : ''; ?>>Tanzania</option>
          </select>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="city">City <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="text" name="city" id="city" value="<?php echo e($biz['city'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="address_line1">Business Address <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="text" name="address_line1" id="address_line1" value="<?php echo e($biz['address_line1'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 16px;">
        <label for="summary">Business Operations Summary <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <textarea name="summary" id="summary" required style="width:100%; border: 1px solid var(--border); border-radius: var(--radius); padding: 8px; background: var(--card); color: var(--text); min-height: 70px;"><?php echo e($biz['summary'] ?? ''); ?></textarea>
        </div>
      </div>

      <?php if ($canUpdateSettings): ?>
      <button type="submit" class="btn-primary" style="width: 100%; border:none; padding:10px; cursor:pointer;" id="profileBtn">
        Save Profile Parameters
      </button>
      <?php endif; ?>
    </form>
  </div>

  <!-- Right Column: Accounting & Inventory parameters -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Accounting &amp; Costing Parameters</div>
    </div>
    <form action="backend.php<?php echo $role_query; ?>" method="POST" style="padding: 20px;" id="acctForm">
      <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
      <input type="hidden" name="action" value="update_accounting">

      <div class="field" style="margin-bottom: 12px;">
        <label for="valuation_method">Inventory Costing Valuation Method <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <select name="inventory_valuation_method" id="valuation_method" required>
            <option value="WEIGHTED_AVERAGE" <?php echo ($acct['inventory_valuation_method'] === 'WEIGHTED_AVERAGE') ? 'selected' : ''; ?>>Weighted Average (WAVG)</option>
            <option value="FIFO" <?php echo ($acct['inventory_valuation_method'] === 'FIFO') ? 'selected' : ''; ?>>First-In, First-Out (FIFO)</option>
          </select>
        </div>
        <p style="font-size:10px; color: var(--text3); margin-top: 4px;">Valuation formula for gold/mineral lots and cost of goods sold calculations.</p>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="tax_rate">Default Tax Rate (%) <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <input type="number" name="default_tax_rate" id="tax_rate" step="0.0001" min="0" max="100" value="<?php echo (float)$acct['default_tax_rate']; ?>" required>
        </div>
      </div>

      <div class="field" style="margin-bottom: 12px;">
        <label for="fiscal_month">Fiscal Year Start Month <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <select name="fiscal_year_start_month" id="fiscal_month" required>
            <?php
            $months = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];
            foreach ($months as $num => $name):
            ?>
              <option value="<?php echo $num; ?>" <?php echo ($acct['fiscal_year_start_month'] == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field" style="margin-bottom: 16px;">
        <label class="checkbox-wrap">
          <input type="checkbox" name="allow_negative_stock" value="1" <?php echo ($acct['allow_negative_stock']) ? 'checked' : ''; ?>>
          <span>Allow negative inventory quantities</span>
        </label>
        <p style="font-size:10px; color: var(--text3); margin-top: 4px; padding-left: 24px;">If cleared, sales orders are blocked if available stock falls below requested sales quantities.</p>
      </div>

      <?php if ($canUpdateSettings): ?>
      <button type="submit" class="btn-primary" style="width: 100%; border:none; padding:10px; cursor:pointer;" id="acctBtn">
        Save Accounting Rules
      </button>
      <?php endif; ?>
    </form>
  </div>
</div>

<script>
<?php if (!$canUpdateSettings): ?>
document.querySelectorAll('#profileForm input:not([type="hidden"]), #profileForm select, #profileForm textarea, #profileForm button[type="submit"], #acctForm input:not([type="hidden"]), #acctForm select, #acctForm textarea, #acctForm button[type="submit"]').forEach(function (element) {
  element.disabled = true;
});
<?php endif; ?>

const settingsCompanyLogo = document.getElementById('settingsCompanyLogo');
const settingsCompanyLogoPreview = document.getElementById('settingsCompanyLogoPreview');
if (settingsCompanyLogo) {
  settingsCompanyLogo.addEventListener('change', function() {
    const file = settingsCompanyLogo.files[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 3 * 1024 * 1024) {
      settingsCompanyLogo.value = '';
      alert('Choose a JPG, PNG, or WEBP company logo no larger than 3 MB.');
      return;
    }
    const previewUrl = URL.createObjectURL(file);
    settingsCompanyLogoPreview.replaceChildren();
    const image = document.createElement('img');
    image.src = previewUrl;
    image.alt = 'Selected company logo';
    image.onload = function() { URL.revokeObjectURL(previewUrl); };
    settingsCompanyLogoPreview.appendChild(image);
  });
}

const companyIdentityForm = document.getElementById('companyIdentityForm');
const companyIdentityBtn = document.getElementById('companyIdentityBtn');
if (companyIdentityForm && companyIdentityBtn) {
  companyIdentityForm.addEventListener('submit', function() {
    companyIdentityBtn.disabled = true;
    companyIdentityBtn.style.opacity = '0.7';
    companyIdentityBtn.textContent = 'Saving Company Information...';
  });
}

// Safeguard double submissions client-side
document.getElementById('profileForm').addEventListener('submit', function() {
  document.getElementById('profileBtn').disabled = true;
  document.getElementById('profileBtn').style.opacity = '0.7';
  document.getElementById('profileBtn').textContent = 'Saving Profile...';
});
document.getElementById('acctForm').addEventListener('submit', function() {
  document.getElementById('acctBtn').disabled = true;
  document.getElementById('acctBtn').style.opacity = '0.7';
  document.getElementById('acctBtn').textContent = 'Saving Rules...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
