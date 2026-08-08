<?php
$cur_page = $page_title ?? 'Cash Lead Schedule';
?>
<!-- ========== FLOATING APPS PANEL ========== -->
<div class="app-grid-overlay" id="appGridOverlay"></div>
<div class="app-grid-panel" id="appGridPanel">
  <div class="app-grid-header">
    <span class="app-grid-title">APPS</span>
    <span class="app-grid-esc">ESC</span>
  </div>
  
  <div class="app-grid-body">
    <!-- Main -->
    <div class="app-grid-section-title">Main</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'Cash Lead Schedule' || $cur_page === 'Dashboard') ? ' active' : ''; ?>" href="#" id="nav-main">
        <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
          <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Dashboard</div>
          <div class="app-grid-desc">Overview & statistics</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Totals') ? ' active' : ''; ?>" href="#" id="nav-totals">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M5 8h14M5 12h14M5 16h14M3 6v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2z"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Totals</div>
          <div class="app-grid-desc">Financial totals summary</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Monthly Transactions') ? ' active' : ''; ?>" href="#" id="nav-monthly">
        <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--green);"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Monthly Transactions</div>
          <div class="app-grid-desc">Monthly transaction history</div>
        </div>
      </a>
    </div>

    <!-- Bank Accounts -->
    <div class="app-grid-section-title">Bank Accounts</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'US$ Equity') ? ' active' : ''; ?>" href="#" id="nav-usd-equity">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">US$ Equity</div>
          <div class="app-grid-desc">US Dollar accounts</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'RWF Equity') ? ' active' : ''; ?>" href="#" id="nav-rwf-equity">
        <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
          <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">RWF Equity</div>
          <div class="app-grid-desc">Rwandan Franc accounts</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Euro Equity') ? ' active' : ''; ?>" href="#" id="nav-euro-equity">
        <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Euro Equity</div>
          <div class="app-grid-desc">Euro currency accounts</div>
        </div>
      </a>
    </div>

    <!-- Bank Recon -->
    <div class="app-grid-section-title">Bank Recon</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'Recon — $ Equity') ? ' active' : ''; ?>" href="#" id="nav-recon-usd">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Recon — $ Equity</div>
          <div class="app-grid-desc">USD bank reconciliation</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Recon — RWF Equity') ? ' active' : ''; ?>" href="#" id="nav-recon-rwf">
        <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
          <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Recon — RWF Equity</div>
          <div class="app-grid-desc">RWF bank reconciliation</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Recon — Euro') ? ' active' : ''; ?>" href="#" id="nav-recon-euro">
        <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Recon — Euro</div>
          <div class="app-grid-desc">Euro bank reconciliation</div>
        </div>
      </a>
    </div>

    <!-- Petty Cash -->
    <div class="app-grid-section-title">Petty Cash</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'PC BM HQ') ? ' active' : ''; ?>" href="#" id="nav-pc-hq">
        <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--green);"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">PC BM HQ</div>
          <div class="app-grid-desc">Petty cash log HQ</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Cash Count HQ') ? ' active' : ''; ?>" href="#" id="nav-cashcount-hq">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Cash Count HQ</div>
          <div class="app-grid-desc">Reconciliation at HQ</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'PC BM RUB') ? ' active' : ''; ?>" href="#" id="nav-pc-rub">
        <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
          <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">PC BM RUB</div>
          <div class="app-grid-desc">Petty cash log Rubavu</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Cash Count RUB') ? ' active' : ''; ?>" href="#" id="nav-cashcount-rub">
        <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Cash Count RUB</div>
          <div class="app-grid-desc">Reconciliation Rubavu</div>
        </div>
      </a>
    </div>

    <!-- Purchases & Stock -->
    <div class="app-grid-section-title">Purchases &amp; Stock</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'Purchase Logs — Ta') ? ' active' : ''; ?>" href="#" id="nav-purchase-ta">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v10a2 2 0 01-2 2h-4M12 7v10M9 12h6"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Purchase Logs — Ta</div>
          <div class="app-grid-desc">Tantalum purchases logs <span class="nav-badge green">Ta</span></div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Purchase Logs — Sn') ? ' active' : ''; ?>" href="#" id="nav-purchase-sn">
        <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
          <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><path d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v10a2 2 0 01-2 2h-4M12 7v10M9 12h6"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Purchase Logs — Sn</div>
          <div class="app-grid-desc">Tin purchases logs <span class="nav-badge green">Sn</span></div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Tin Summary') ? ' active' : ''; ?>" href="#" id="nav-tin-summary">
        <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--green);"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Tin Summary</div>
          <div class="app-grid-desc">Tin stock & processing</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Ta Summary') ? ' active' : ''; ?>" href="#" id="nav-ta-summary">
        <div class="app-grid-icon-wrap" style="background: var(--amber-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--amber);"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Ta Summary</div>
          <div class="app-grid-desc">Tantalum stock & processing</div>
        </div>
      </a>
    </div>

    <!-- Finance -->
    <div class="app-grid-section-title">Finance</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'Chart of Accounts') ? ' active' : ''; ?>" href="#" id="nav-accounts">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Chart of Accounts</div>
          <div class="app-grid-desc">Financial accounts list</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Accounts Payable') ? ' active' : ''; ?>" href="#" id="nav-payable">
        <div class="app-grid-icon-wrap" style="background: var(--red-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--red);"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Accounts Payable</div>
          <div class="app-grid-desc">Payables summary <span class="nav-badge">10</span></div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Daily Expenses') ? ' active' : ''; ?>" href="#" id="nav-daily-exp">
        <div class="app-grid-icon-wrap" style="background: var(--orange-light);">
          <svg viewBox="0 0 24 24" style="stroke: var(--orange);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Daily Expenses</div>
          <div class="app-grid-desc">Daily expense ledger</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Gedeon Account') ? ' active' : ''; ?>" href="#" id="nav-gedeon">
        <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--green);"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Gedeon Account</div>
          <div class="app-grid-desc">Gedeon capital account</div>
        </div>
      </a>
    </div>

    <!-- System -->
    <div class="app-grid-section-title">System</div>
    <div class="app-grid-group">
      <a class="app-grid-item<?php echo ($cur_page === 'Settings') ? ' active' : ''; ?>" href="#" id="nav-settings">
        <div class="app-grid-icon-wrap" style="background: var(--blue-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--blue);"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M2 12h2M20 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Settings</div>
          <div class="app-grid-desc">System configurations</div>
        </div>
      </a>
      <a class="app-grid-item<?php echo ($cur_page === 'Help & Support') ? ' active' : ''; ?>" href="#" id="nav-help">
        <div class="app-grid-icon-wrap" style="background: var(--green-bg);">
          <svg viewBox="0 0 24 24" style="stroke: var(--green);"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        </div>
        <div class="app-grid-info">
          <div class="app-grid-name">Help &amp; Support</div>
          <div class="app-grid-desc">Support and documentation</div>
        </div>
      </a>
    </div>
  </div>
</div>
