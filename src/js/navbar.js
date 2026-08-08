/* ==========================================
   BUSINESS MANAGEMENT — Navbar Scripts
   ========================================== */

/**
 * Toggle the profile dropdown menu
 */
function toggleDropdown() {
  var dropdown = document.getElementById('profileDropdown');
  var notifDropdown = document.getElementById('notifDropdown');
  var appGridPanel = document.getElementById('appGridPanel');
  var appGridOverlay = document.getElementById('appGridOverlay');
  var gridBtn = document.getElementById('gridBtn');

  if (dropdown) {
    var isOpen = dropdown.classList.contains('open');
    if (!isOpen) {
      // Close other menus if open
      if (appGridPanel && appGridOverlay && gridBtn) {
        appGridPanel.classList.remove('show');
        appGridOverlay.classList.remove('show');
        gridBtn.classList.remove('active');
      }
      if (notifDropdown) {
        notifDropdown.classList.remove('open');
      }
      dropdown.classList.add('open');
    } else {
      dropdown.classList.remove('open');
    }
  }
}

/**
 * Toggle the notifications dropdown menu
 */
function toggleNotifDropdown() {
  var notifDropdown = document.getElementById('notifDropdown');
  var profileDropdown = document.getElementById('profileDropdown');
  var appGridPanel = document.getElementById('appGridPanel');
  var appGridOverlay = document.getElementById('appGridOverlay');
  var gridBtn = document.getElementById('gridBtn');

  if (notifDropdown) {
    var isOpen = notifDropdown.classList.contains('open');
    if (!isOpen) {
      // Close other menus if open
      if (appGridPanel && appGridOverlay && gridBtn) {
        appGridPanel.classList.remove('show');
        appGridOverlay.classList.remove('show');
        gridBtn.classList.remove('active');
      }
      if (profileDropdown) {
        profileDropdown.classList.remove('open');
      }
      notifDropdown.classList.add('open');
    } else {
      notifDropdown.classList.remove('open');
    }
  }
}

/**
 * Clear all notifications and show empty state
 */
function clearAllNotifications(event) {
  if (event) {
    event.stopPropagation();
  }
  var notifList = document.getElementById('notifList');
  var notifDot = document.getElementById('notifDot');
  
  if (notifList) {
    notifList.innerHTML = `
      <div class="notif-empty">
        <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 003-3V9a7 7 0 0114 0v5a3 3 0 003 3zm-8.27 4a2 2 0 01-3.46 0"/></svg>
        <span>All caught up! No new notifications.</span>
      </div>
    `;
  }
  if (notifDot) {
    notifDot.style.display = 'none';
  }
}

/**
 * Close dropdowns when clicking outside
 */
document.addEventListener('click', function (e) {
  // Close profile dropdown
  var profileWrap = document.querySelector('.profile-wrap');
  if (profileWrap && !profileWrap.contains(e.target)) {
    var profileDropdown = document.getElementById('profileDropdown');
    if (profileDropdown) {
      profileDropdown.classList.remove('open');
    }
  }

  // Close notifications dropdown
  var notifWrap = document.querySelector('.notif-wrap');
  if (notifWrap && !notifWrap.contains(e.target)) {
    var notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown) {
      notifDropdown.classList.remove('open');
    }
  }
});

/**
 * Handle Dark/Light theme toggling, Scroll shadow effect, and Notifications population
 */
document.addEventListener('DOMContentLoaded', function () {
  // Theme Toggler (Defaulting to light mode fallback)
  var themeToggleBtn = document.getElementById('themeToggleBtn');
  if (themeToggleBtn) {
    var savedTheme = localStorage.getItem('theme');
    var currentTheme = savedTheme || 'light';

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

  // --- Dynamic Notifications Population ---
  var notifList = document.getElementById('notifList');
  var notifDot = document.getElementById('notifDot');
  if (notifList) {
    // Detect active role from query parameters (fallback to session/owner context)
    var urlParams = new URLSearchParams(window.location.search);
    var activeRole = urlParams.get('role') || 'owner';

    // Role-specific mock notifications
    var notifications = {
      super_admin: [
        {
          id: 1,
          title: "Pending Approval: 'Alpha Mining Co' submitted business registration documents.",
          type: "warning",
          time: "10 mins ago",
          unread: true
        },
        {
          id: 2,
          title: "Security Notice: 2 failed login attempts recorded for admin account.",
          type: "danger",
          time: "2 hours ago",
          unread: true
        },
        {
          id: 3,
          title: "Database Backup: Scheduled platform snapshot completed successfully.",
          type: "success",
          time: "Today, 4:00 AM",
          unread: false
        }
      ],
      owner: [
        {
          id: 1,
          title: "Low Stock Alert: Tin (Sn) inventory has reached minimum safety limit (1,326 kg).",
          type: "warning",
          time: "5 mins ago",
          unread: true
        },
        {
          id: 2,
          title: "Leave Request: John Doe submitted a Sick Leave application for review.",
          type: "info",
          time: "45 mins ago",
          unread: true
        },
        {
          id: 3,
          title: "Monthly Report: June Sales & Purchases spreadsheet is generated and ready.",
          type: "success",
          time: "Today, 9:15 AM",
          unread: false
        }
      ],
      employee: [
        {
          id: 1,
          title: "Leave Approved: Your Sick Leave request for Aug 10-12 has been approved.",
          type: "success",
          time: "1 hour ago",
          unread: true
        },
        {
          id: 2,
          title: "Performance Target: You achieved 110% of your weekly logged entries.",
          type: "info",
          time: "Yesterday",
          unread: false
        }
      ]
    };

    var currentNotifs = notifications[activeRole] || notifications['owner'];
    
    // Check for unread statuses to set badge dot
    var hasUnread = currentNotifs.some(function(n) { return n.unread; });
    if (notifDot) {
      notifDot.style.display = hasUnread ? 'block' : 'none';
    }

    renderNotifications(currentNotifs);
  }

  function renderNotifications(items) {
    if (!notifList) return;
    if (items.length === 0) {
      notifList.innerHTML = `
        <div class="notif-empty">
          <svg viewBox="0 0 24 24"><path d="M22 17H2a3 3 0 003-3V9a7 7 0 0114 0v5a3 3 0 003 3zm-8.27 4a2 2 0 01-3.46 0"/></svg>
          <span>All caught up! No new notifications.</span>
        </div>
      `;
      return;
    }

    var html = '';
    items.forEach(function(item) {
      var icon = '';
      if (item.type === 'warning') {
        icon = `<div class="notif-icon-box notif-icon-amber"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/></svg></div>`;
      } else if (item.type === 'danger') {
        icon = `<div class="notif-icon-box notif-icon-red"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>`;
      } else if (item.type === 'success') {
        icon = `<div class="notif-icon-box notif-icon-green"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>`;
      } else { // info
        icon = `<div class="notif-icon-box notif-icon-blue"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></div>`;
      }

      var unreadClass = item.unread ? ' unread' : '';

      html += `
        <div class="notif-item${unreadClass}" onclick="markAsRead(this, ${item.id})">
          ${icon}
          <div class="notif-text-box">
            <div class="notif-title">${item.title}</div>
            <div class="notif-time">${item.time}</div>
          </div>
        </div>
      `;
    });

    notifList.innerHTML = html;
  }
});

/**
 * Click handler to mark item as read
 */
function markAsRead(element, id) {
  if (element.classList.contains('unread')) {
    element.classList.remove('unread');
    
    // Check if there are any remaining unread items in DOM
    var remainingUnread = document.querySelector('.notif-item.unread');
    var notifDot = document.getElementById('notifDot');
    if (!remainingUnread && notifDot) {
      notifDot.style.display = 'none';
    }
  }
}
