/* ==========================================
   BUSINESS MANAGEMENT — Dashboard Page Logic
   ========================================== */

document.addEventListener('DOMContentLoaded', function () {
  // Add Entry Button handler
  var addEntryBtn = document.getElementById('addEntryBtn');
  if (addEntryBtn) {
    addEntryBtn.addEventListener('click', function () {
      alert('Add Entry feature: This will open a form to record a new transaction or mineral purchase.');
    });
  }

  // Export Button handler
  var exportBtn = document.getElementById('exportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', function () {
      alert('Export feature: Preparing cash lead schedule data for CSV/Excel download.');
    });
  }

  // Add slight hover animations to stat cards
  var statCards = document.querySelectorAll('.stat-card');
  statCards.forEach(function (card) {
    card.addEventListener('mouseenter', function () {
      card.style.transform = 'translateY(-2px)';
      card.style.transition = 'all 0.2s ease-in-out';
    });
    card.addEventListener('mouseleave', function () {
      card.style.transform = 'translateY(0)';
    });
  });
});
