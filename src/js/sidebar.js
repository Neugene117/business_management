/* ==========================================
   BUSINESS MANAGEMENT — Floating APPS Menu Logic
   ========================================== */

document.addEventListener('DOMContentLoaded', function () {
  var gridBtn = document.getElementById('gridBtn');
  var appGridPanel = document.getElementById('appGridPanel');
  var appGridOverlay = document.getElementById('appGridOverlay');

  if (!gridBtn || !appGridPanel || !appGridOverlay) return;

  /**
   * Toggle the floating app grid panel
   */
  function toggleAppGrid() {
    var isOpen = appGridPanel.classList.contains('show');
    if (isOpen) {
      closeAppGrid();
    } else {
      openAppGrid();
    }
  }

  /**
   * Open the app grid panel
   */
  function openAppGrid() {
    // Close profile dropdown first if it's open
    var profileDropdown = document.getElementById('profileDropdown');
    if (profileDropdown && profileDropdown.classList.contains('open')) {
      profileDropdown.classList.remove('open');
    }

    appGridPanel.classList.add('show');
    appGridOverlay.classList.add('show');
    gridBtn.classList.add('active');
  }

  /**
   * Close the app grid panel
   */
  function closeAppGrid() {
    appGridPanel.classList.remove('show');
    appGridOverlay.classList.remove('show');
    gridBtn.classList.remove('active');
  }

  // Toggle button click handler
  gridBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    toggleAppGrid();
  });

  // Click on overlay closes the menu
  appGridOverlay.addEventListener('click', closeAppGrid);

  // Close on ESC key press
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      closeAppGrid();
    }
  });

  // Close grid when clicking a navigation card (optional, since it will navigate)
  var gridItems = appGridPanel.querySelectorAll('.app-grid-item');
  gridItems.forEach(function (item) {
    item.addEventListener('click', function () {
      closeAppGrid();
    });
  });
});
