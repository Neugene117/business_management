<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/flash.php';
require_once __DIR__ . '/../../includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/index.php");
    exit();
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Business — Business Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../src/css/login.css">
<link rel="stylesheet" href="../../src/css/searchable-select.css">
<style>
  .register-card {
    width: 100%;
    max-width: 800px;
    background: var(--card);
    padding: 30px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    margin: 40px auto;
  }
  .form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 20px;
  }
  .form-section-title {
    grid-column: span 2;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    padding-bottom: 8px;
    margin-top: 16px;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  @media (max-width: 600px) {
    .form-grid {
      grid-template-columns: 1fr;
    }
    .form-section-title {
      grid-column: span 1;
    }
  }
  .btn-submit {
    width: 100%;
    background: var(--orange);
    color: #fff;
    padding: 12px;
    border: none;
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 20px;
    transition: background 0.15s ease;
  }
  .btn-submit:hover {
    background: var(--orange-mid);
  }
  .company-logo-upload {
    grid-column: span 2;
    display: grid;
    grid-template-columns: 76px 1fr;
    align-items: center;
    gap: 14px;
    padding: 14px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
  }
  .company-logo-preview {
    width: 76px;
    height: 76px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--card);
    border-radius: var(--radius);
    color: var(--text3);
  }
  .company-logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
  .company-logo-preview svg {
    width: 28px;
    height: 28px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
  }
  .company-logo-upload input[type="file"] {
    width: 100%;
    margin-top: 7px;
    color: var(--text2);
    font-size: 11.5px;
  }
  .company-logo-help {
    margin: 5px 0 0;
    color: var(--text3);
    font-size: 10.5px;
    line-height: 1.4;
  }
  @media (max-width: 600px) {
    .company-logo-upload {
      grid-column: span 1;
      grid-template-columns: 60px 1fr;
    }
    .company-logo-preview {
      width: 60px;
      height: 60px;
    }
  }
  body {
    background: var(--bg);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
</style>
</head>
<body>

<div class="register-card">
  <div class="brand" style="margin-bottom: 20px; justify-content: center;">
    <div class="logo-badge">
      <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
    </div>
    <div>
      <div class="brand-text">Business Management</div>
      <div class="brand-sub">Register Your Enterprise</div>
    </div>
  </div>

  <div class="form-title" style="text-align: center;">Enterprise Onboarding</div>
  <div class="form-desc" style="text-align: center; margin-bottom: 24px;">Submit details to register your business for approval.</div>

  <?php displayFlashMessage(); ?>

  <form action="backend" method="POST" enctype="multipart/form-data" id="regForm">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="action" value="register">

    <div class="form-grid">
      <!-- Owner details -->
      <div class="form-section-title">Owner Details</div>
      
      <div class="field">
        <label for="first_name">First Name <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="first_name" id="first_name" placeholder="John" required>
        </div>
      </div>
      
      <div class="field">
        <label for="last_name">Last Name <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input type="text" name="last_name" id="last_name" placeholder="Doe" required>
        </div>
      </div>
      
      <div class="field">
        <label for="owner_email">Owner Email <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input type="email" name="owner_email" id="owner_email" placeholder="owner@company.com" required autocomplete="username">
        </div>
        <div class="field-feedback" id="feedback_owner_email" style="font-size:11px; margin-top:4px; font-weight:500; display:none;"></div>
      </div>
      
      <div class="field">
        <label for="owner_phone">Owner Phone <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
          <input type="tel" name="owner_phone" id="owner_phone" placeholder="+250788000000" required>
        </div>
        <div class="field-feedback" id="feedback_owner_phone" style="font-size:11px; margin-top:4px; font-weight:500; display:none;"></div>
      </div>

      <div class="field">
        <label for="password">Password <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          <input type="password" name="password" id="password" placeholder="Password (Min 8 chars)" required autocomplete="new-password">
        </div>
      </div>

      <div class="field">
        <label for="password_confirm">Confirm Password <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          <input type="password" name="password_confirm" id="password_confirm" placeholder="Confirm your password" required autocomplete="new-password">
        </div>
      </div>

      <!-- Business details -->
      <div class="form-section-title">Business Details</div>

      <div class="field">
        <label for="business_name">Business Name <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
          <input type="text" name="business_name" id="business_name" placeholder="Alpha Mining Services Ltd" required>
        </div>
      </div>

      <div class="field">
        <label for="legal_name">Legal/Trade Name</label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
          <input type="text" name="legal_name" id="legal_name" placeholder="Leave blank if same as Business Name">
        </div>
      </div>

      <div class="company-logo-upload">
        <div class="company-logo-preview" id="companyLogoPreview" aria-label="Company logo preview">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </div>
        <div>
          <label for="company_logo">Company Logo</label>
          <input type="file" name="company_logo" id="company_logo" accept="image/jpeg,image/png,image/webp">
          <p class="company-logo-help">Optional. Upload a JPG, PNG, or WEBP image up to 3 MB. A square or landscape logo works best.</p>
        </div>
      </div>

      <div class="field">
        <label for="business_email">Business Email <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <input type="email" name="business_email" id="business_email" placeholder="info@alphamining.rw" required>
        </div>
      </div>

      <div class="field">
        <label for="business_phone">Business Phone <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
          <input type="tel" name="business_phone" id="business_phone" placeholder="+250252555555" required>
        </div>
      </div>

      <div class="field">
        <label for="registration_number">Registration Number (RDB RFT)</label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          <input type="text" name="registration_number" id="registration_number" placeholder="RDB-2026-10492">
        </div>
        <div class="field-feedback" id="feedback_reg_num" style="font-size:11px; margin-top:4px; font-weight:500; display:none;"></div>
      </div>

      <div class="field">
        <label for="tax_number">TIN (Tax Identification Number)</label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/></svg>
          <input type="text" name="tax_number" id="tax_number" placeholder="104928372">
        </div>
      </div>

      <div class="field">
        <label for="country_code">Country <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>
          <select name="country_code" id="country_code" required>
            <option value="RW" selected>Rwanda</option>
            <option value="UG">Uganda</option>
            <option value="KE">Kenya</option>
            <option value="TZ">Tanzania</option>
            <option value="CD">Congo (DRC)</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="currency_code">Default Currency <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v12M15 9.5a2.5 2.5 0 00-5 0c0 4 5 1.5 5 5.5a2.5 2.5 0 01-5 0"/></svg>
          <select name="currency_code" id="currency_code" required>
            <option value="RWF" selected>RWF (Rwandan Franc)</option>
            <option value="USD">USD (US Dollar)</option>
            <option value="EUR">EUR (Euro)</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="timezone">Timezone <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <select name="timezone" id="timezone" required>
            <option value="Africa/Kigali" selected>Africa/Kigali (GMT+2)</option>
            <option value="Africa/Nairobi">Africa/Nairobi (GMT+3)</option>
            <option value="UTC">UTC / GMT</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="city">City <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <input type="text" name="city" id="city" placeholder="Kigali" required>
        </div>
      </div>

      <div class="field" style="grid-column: span 2;">
        <label for="address_line1">Business Address <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <svg class="field-icon" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <input type="text" name="address_line1" id="address_line1" placeholder="Plot 492, KG 12 Avenue, Kigali" required>
        </div>
      </div>

      <div class="field" style="grid-column: span 2;">
        <label for="summary">Short Business Summary <span style="color:var(--red);">*</span></label>
        <div class="field-wrap">
          <textarea name="summary" id="summary" placeholder="Provide a brief summary of the business operations, focus, or services..." required></textarea>
        </div>
      </div>
    </div>

    <button class="btn-submit" type="submit" id="submitBtn">
      <span>Submit Registration</span>
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </button>
  </form>

  <div class="form-footer" style="text-align: center; margin-top: 20px;">
    Already have an account? <a href="../../login">Sign In</a>
  </div>
</div>

<script>
const uniqueValidity = {
  owner_email: true,
  owner_phone: true,
  registration_number: true
};

function setupLiveValidation(inputId, fieldName, feedbackId) {
  const input = document.getElementById(inputId);
  const feedback = document.getElementById(feedbackId);
  if (!input || !feedback) return;
  let timer = null;

  input.addEventListener('input', function() {
    clearTimeout(timer);
    const val = input.value.trim();
    if (!val) {
      feedback.style.display = 'none';
      feedback.textContent = '';
      uniqueValidity[fieldName] = true;
      checkFormValidity();
      return;
    }

    feedback.style.display = 'block';
    feedback.style.color = 'var(--text3)';
    feedback.textContent = 'Checking availability...';

    timer = setTimeout(function() {
      fetch('check_unique?field=' + encodeURIComponent(fieldName) + '&value=' + encodeURIComponent(val))
        .then(res => res.json())
        .then(data => {
          if (data.available) {
            feedback.style.color = 'var(--green)';
            feedback.textContent = '✓ ' + (data.message || 'Available');
            uniqueValidity[fieldName] = true;
          } else {
            feedback.style.color = 'var(--red)';
            feedback.textContent = '✕ ' + data.message;
            uniqueValidity[fieldName] = false;
          }
          checkFormValidity();
        })
        .catch(err => {
          feedback.style.display = 'none';
          uniqueValidity[fieldName] = true;
          checkFormValidity();
        });
    }, 300);
  });
}

function checkFormValidity() {
  const submitBtn = document.getElementById('submitBtn');
  const allValid = Object.values(uniqueValidity).every(v => v === true);
  if (!allValid) {
    submitBtn.disabled = true;
    submitBtn.style.opacity = '0.6';
    submitBtn.title = 'Please fix unique validation errors before submitting';
  } else {
    submitBtn.disabled = false;
    submitBtn.style.opacity = '1';
    submitBtn.removeAttribute('title');
  }
}

setupLiveValidation('owner_email', 'owner_email', 'feedback_owner_email');
setupLiveValidation('owner_phone', 'owner_phone', 'feedback_owner_phone');
setupLiveValidation('registration_number', 'registration_number', 'feedback_reg_num');

const companyLogoInput = document.getElementById('company_logo');
const companyLogoPreview = document.getElementById('companyLogoPreview');
companyLogoInput.addEventListener('change', function() {
  const file = companyLogoInput.files[0];
  if (!file) return;
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 3 * 1024 * 1024) {
    companyLogoInput.value = '';
    alert('Choose a JPG, PNG, or WEBP company logo no larger than 3 MB.');
    return;
  }
  const previewUrl = URL.createObjectURL(file);
  companyLogoPreview.replaceChildren();
  const image = document.createElement('img');
  image.src = previewUrl;
  image.alt = 'Selected company logo';
  image.onload = function() { URL.revokeObjectURL(previewUrl); };
  companyLogoPreview.appendChild(image);
});

// Prevent double form submission client-side
document.getElementById('regForm').addEventListener('submit', function(e) {
  const pw = document.getElementById('password').value;
  const pwConfirm = document.getElementById('password_confirm').value;
  
  if (pw !== pwConfirm) {
    e.preventDefault();
    alert('Passwords do not match! Please check and try again.');
    return;
  }

  if (pw.length < 8) {
    e.preventDefault();
    alert('Password must be at least 8 characters long.');
    return;
  }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.style.opacity = '0.7';
  btn.querySelector('span').textContent = 'Submitting registration...';
});
</script>
<script src="../../src/js/searchable-select.js"></script>
</body>
</html>
