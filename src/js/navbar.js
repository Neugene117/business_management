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
        var notifBtn = document.getElementById('notifBtn');
        if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
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
  var notifBtn = document.getElementById('notifBtn');

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
      if (notifBtn) notifBtn.setAttribute('aria-expanded', 'true');
    } else {
      notifDropdown.classList.remove('open');
      if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
    }
  }
}

var notificationState = {
  endpoint: '',
  csrfToken: '',
  items: [],
  unreadCount: 0
};

function notificationActionUrl(action) {
  var url = new URL(notificationState.endpoint, window.location.href);
  url.searchParams.set('notification_action', action);
  return url.toString();
}

function formatNotificationTime(value) {
  if (!value) return '';
  var date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return value;

  var seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
  if (seconds < 60) return 'Just now';
  if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
  if (seconds < 86400) return Math.floor(seconds / 3600) + ' hr ago';
  if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
  return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function notificationIcon(type) {
  var icon = document.createElement('span');
  var normalizedType = ['warning', 'danger', 'success'].includes(type) ? type : 'info';
  var colorClass = normalizedType === 'warning' ? 'amber' : normalizedType === 'danger' ? 'red' : normalizedType === 'success' ? 'green' : 'blue';
  icon.className = 'notif-icon-box notif-icon-' + colorClass;
  icon.setAttribute('aria-hidden', 'true');

  if (normalizedType === 'warning') {
    icon.innerHTML = '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"/></svg>';
  } else if (normalizedType === 'danger') {
    icon.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  } else if (normalizedType === 'success') {
    icon.innerHTML = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
  } else {
    icon.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }
  return icon;
}

function updateNotificationCount(count) {
  notificationState.unreadCount = Math.max(0, Number(count) || 0);
  var badge = document.getElementById('notifCount');
  var subtitle = document.getElementById('notifSubtitle');
  var markAllButton = document.getElementById('notifMarkAllBtn');

  if (badge) {
    badge.textContent = notificationState.unreadCount > 99 ? '99+' : String(notificationState.unreadCount);
    badge.hidden = notificationState.unreadCount === 0;
  }
  if (subtitle) {
    subtitle.textContent = notificationState.unreadCount === 0
      ? 'You are all caught up'
      : notificationState.unreadCount + (notificationState.unreadCount === 1 ? ' unread notification' : ' unread notifications');
  }
  if (markAllButton) markAllButton.disabled = notificationState.unreadCount === 0;
}

function renderNotifications(items) {
  var list = document.getElementById('notifList');
  if (!list) return;
  list.replaceChildren();

  if (!items.length) {
    var empty = document.createElement('div');
    empty.className = 'notif-empty';
    empty.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 17H2a3 3 0 003-3V9a7 7 0 0114 0v5a3 3 0 003 3zm-8.27 4a2 2 0 01-3.46 0"/></svg><span>No notifications yet.</span>';
    list.appendChild(empty);
    return;
  }

  items.forEach(function (item) {
    var row = document.createElement('button');
    row.type = 'button';
    row.className = 'notif-item' + (item.unread ? ' unread' : '');
    row.dataset.notificationId = String(item.id);
    row.appendChild(notificationIcon(item.type));

    var textBox = document.createElement('span');
    textBox.className = 'notif-text-box';
    var title = document.createElement('span');
    title.className = 'notif-title';
    title.textContent = item.title;
    textBox.appendChild(title);

    if (item.message) {
      var message = document.createElement('span');
      message.className = 'notif-message';
      message.textContent = item.message;
      textBox.appendChild(message);
    }

    var time = document.createElement('span');
    time.className = 'notif-time';
    time.textContent = formatNotificationTime(item.created_at);
    textBox.appendChild(time);
    row.appendChild(textBox);
    row.addEventListener('click', function () { markAsRead(row, item.id); });
    list.appendChild(row);
  });
}

async function loadNotifications() {
  var list = document.getElementById('notifList');
  try {
    var response = await fetch(notificationActionUrl('list'), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    if (!response.ok) throw new Error('Unable to load notifications.');
    var data = await response.json();
    if (!data.success) throw new Error(data.message || 'Unable to load notifications.');
    notificationState.items = Array.isArray(data.notifications) ? data.notifications : [];
    renderNotifications(notificationState.items);
    updateNotificationCount(data.unread_count);
  } catch (error) {
    if (list) {
      list.replaceChildren();
      var failure = document.createElement('div');
      failure.className = 'notif-empty';
      failure.textContent = 'Notifications could not be loaded.';
      list.appendChild(failure);
    }
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
      var notifBtn = document.getElementById('notifBtn');
      if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
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

  var notificationCenter = document.getElementById('notificationCenter');
  if (notificationCenter) {
    notificationState.endpoint = notificationCenter.dataset.endpoint || '';
    notificationState.csrfToken = notificationCenter.dataset.csrfToken || '';

    var markAllButton = document.getElementById('notifMarkAllBtn');
    if (markAllButton) {
      markAllButton.addEventListener('click', function (event) {
        event.stopPropagation();
        markAllNotificationsAsRead();
      });
    }

    loadNotifications();
    window.setInterval(loadNotifications, 60000);
  }
});

async function markAsRead(element, id) {
  if (!element.classList.contains('unread')) return;

  var formData = new URLSearchParams();
  formData.set('csrf_token', notificationState.csrfToken);
  formData.set('notification_id', String(id));

  try {
    var response = await fetch(notificationActionUrl('mark_read'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: formData.toString()
    });
    if (!response.ok) throw new Error('Unable to mark notification as read.');
    var data = await response.json();
    if (!data.success) throw new Error(data.message || 'Unable to mark notification as read.');
    element.classList.remove('unread');
    var item = notificationState.items.find(function (notification) { return Number(notification.id) === Number(id); });
    if (item) item.unread = false;
    updateNotificationCount(data.unread_count);
  } catch (error) {
    loadNotifications();
  }
}

async function markAllNotificationsAsRead() {
  if (notificationState.unreadCount === 0) return;
  var button = document.getElementById('notifMarkAllBtn');
  if (button) button.disabled = true;

  var formData = new URLSearchParams();
  formData.set('csrf_token', notificationState.csrfToken);

  try {
    var response = await fetch(notificationActionUrl('mark_all_read'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: formData.toString()
    });
    if (!response.ok) throw new Error('Unable to update notifications.');
    var data = await response.json();
    if (!data.success) throw new Error(data.message || 'Unable to update notifications.');
    notificationState.items.forEach(function (item) { item.unread = false; });
    document.querySelectorAll('.notif-item.unread').forEach(function (item) { item.classList.remove('unread'); });
    updateNotificationCount(data.unread_count);
  } catch (error) {
    loadNotifications();
  } finally {
    if (button) button.disabled = notificationState.unreadCount === 0;
  }
}
