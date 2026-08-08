/* ==========================================
   BUSINESS MANAGEMENT — Navbar Scripts
   ========================================== */

/**
 * Toggle the profile dropdown menu
 */
function toggleDropdown() {
  var dropdown = document.getElementById('profileDropdown');
  var appGridPanel = document.getElementById('appGridPanel');
  var appGridOverlay = document.getElementById('appGridOverlay');
  var gridBtn = document.getElementById('gridBtn');

  if (dropdown) {
    var isOpen = dropdown.classList.contains('open');
    if (!isOpen) {
      // Close the app grid menu if open
      if (appGridPanel && appGridOverlay && gridBtn) {
        appGridPanel.classList.remove('show');
        appGridOverlay.classList.remove('show');
        gridBtn.classList.remove('active');
      }
      dropdown.classList.add('open');
    } else {
      dropdown.classList.remove('open');
    }
  }
}

/**
 * Close dropdown when clicking outside
 */
document.addEventListener('click', function (e) {
  var wrap = document.querySelector('.profile-wrap');
  if (wrap && !wrap.contains(e.target)) {
    var dropdown = document.getElementById('profileDropdown');
    if (dropdown) {
      dropdown.classList.remove('open');
    }
  }
});

/**
 * Handle Dark/Light theme toggling and Scroll shadow effect
 */
document.addEventListener('DOMContentLoaded', function () {
  // Theme Toggler
  var themeToggleBtn = document.getElementById('themeToggleBtn');
  if (themeToggleBtn) {
    var savedTheme = localStorage.getItem('theme');
    var systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var currentTheme = savedTheme || (systemPrefersDark ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', currentTheme);

    themeToggleBtn.addEventListener('click', function () {
      var activeTheme = document.documentElement.getAttribute('data-theme');
      var newTheme = activeTheme === 'dark' ? 'light' : 'dark';
      
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
    });
  }

  // Scroll Shadow Effect for Sticky Header
  var topbar = document.querySelector('.topbar');
  var scrollContainer = document.querySelector('.content');

  if (topbar && scrollContainer) {
    scrollContainer.addEventListener('scroll', function () {
      if (scrollContainer.scrollTop > 5) {
        topbar.classList.add('topbar-scrolled');
      } else {
        topbar.classList.remove('topbar-scrolled');
      }
    });
  }
});
