/* ==========================================
   BUSINESS MANAGEMENT — Login Page Logic
   ========================================== */

/**
 * Toggle password visibility between text and password
 */
function togglePassword() {
  const pw = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (!pw || !icon) return;

  if (pw.type === 'password') {
    pw.type = 'text';
    icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
  } else {
    pw.type = 'password';
    icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

/**
 * Handle form submission and mock validation
 */
function handleLogin() {
  const emailInput = document.getElementById('email');
  const pwInput = document.getElementById('password');
  const errEl = document.getElementById('alertError');
  const sucEl = document.getElementById('alertSuccess');
  const btn = document.getElementById('loginBtn');
  const txt = document.getElementById('btnText');
  const spin = document.getElementById('spinner');
  const arr = document.getElementById('btnArrow');
  const errTxt = document.getElementById('errorText');

  if (!emailInput || !pwInput || !errEl || !sucEl || !btn || !txt || !spin || !arr || !errTxt) return;

  const email = emailInput.value.trim();
  const pw = pwInput.value;

  errEl.style.display = 'none';
  sucEl.style.display = 'none';

  if (!email) {
    errTxt.textContent = 'Please enter your email address.';
    errEl.style.display = 'flex';
    emailInput.focus();
    return;
  }
  if (!pw) {
    errTxt.textContent = 'Please enter your password.';
    errEl.style.display = 'flex';
    pwInput.focus();
    return;
  }

  // Send AJAX request to PHP login backend
  const formData = new URLSearchParams();
  formData.append('email', email);
  formData.append('password', pw);

  fetch('pages/login/process', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: formData
  })
  .then(response => {
    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
    return response.json();
  })
  .then(data => {
    spin.style.display = 'none';
    arr.style.display = 'block';
    btn.disabled = false;
    btn.style.opacity = '1';
    txt.textContent = 'Sign In';

    if (data.success) {
      sucEl.style.display = 'flex';
      setTimeout(() => {
        window.location.href = 'pages/dashboard';
      }, 1000);
    } else {
      errTxt.textContent = data.message || 'Invalid email or password. Please try again.';
      errEl.style.display = 'flex';
    }
  })
  .catch(error => {
    spin.style.display = 'none';
    arr.style.display = 'block';
    btn.disabled = false;
    btn.style.opacity = '1';
    txt.textContent = 'Sign In';
    errTxt.textContent = 'An error occurred. Please try again later.';
    errEl.style.display = 'flex';
    console.error('Login Error:', error);
  });
}

// Add enter key submission listener
document.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') {
    handleLogin();
  }
});
