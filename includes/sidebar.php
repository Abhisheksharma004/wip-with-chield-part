<?php
/**
 * WIP Management Portal - Reusable Sidebar Component
 * Auto-detects the active page based on current script or $activeMenu variable.
 */
$currentScript = $activeMenu ?? basename($_SERVER['PHP_SELF']);

// Check if any Master sub-item is active
$isMasterActive = in_array($currentScript, [
    'vendor-master.php', 'vendor-master',
    'rm-master.php', 'rm-master',
    'child-part-master.php', 'child-part-master',
    'child-master.php', 'child-master',
    'process-master.php', 'process-master',
    'part-master.php', 'part-master'
]);
?>
<!-- Reusable Sidebar Component -->
<aside id="sidebar" class="sidebar">
  
  <!-- Brand Header -->
  <div class="sidebar-header">
    <h2 class="sidebar-brand">WIP Management</h2>
    <button id="sidebarCloseBtn" class="mobile-close-btn" aria-label="Close menu">&times;</button>
  </div>

  <!-- Navigation Menu -->
  <nav class="sidebar-menu">
    <!-- 1. Dashboard -->
    <a href="dashboard.php" class="menu-item <?= ($currentScript === 'dashboard.php' || $currentScript === 'dashboard') ? 'active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="7" height="9"></rect>
        <rect x="14" y="3" width="7" height="5"></rect>
        <rect x="14" y="12" width="7" height="9"></rect>
        <rect x="3" y="16" width="7" height="5"></rect>
      </svg>
      Dashboard
    </a>

    <!-- 2. Master Dropdown (Contains Vendor, RM, Child, Process, Part) -->
    <div class="sidebar-dropdown <?= $isMasterActive ? 'open' : '' ?>" id="masterDropdown">
      <button type="button" class="dropdown-toggle" id="masterDropdownBtn" aria-expanded="<?= $isMasterActive ? 'true' : 'false' ?>">
        <div class="dropdown-toggle-left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
          </svg>
          <span>Master</span>
        </div>
        <svg class="dropdown-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </button>

      <div class="sidebar-submenu" id="masterSubmenu">
        <!-- Sub 1. Vendor Master -->
        <a href="vendor-master.php" class="submenu-item <?= ($currentScript === 'vendor-master.php' || $currentScript === 'vendor-master') ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          Vendor Master
        </a>

        <!-- Sub 2. RM Master -->
        <a href="rm-master.php" class="submenu-item <?= ($currentScript === 'rm-master.php' || $currentScript === 'rm-master') ? 'active' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
          RM Master
        </a>

        <!-- Sub 3. Child Part Master -->
        <a href="child-part-master.php" class="submenu-item <?= ($currentScript === 'child-part-master.php' || $currentScript === 'child-part-master' || $currentScript === 'child-master.php') ? 'active' : '' ?>" title="Child Part Master">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
            <polyline points="2 17 12 22 22 17"></polyline>
            <polyline points="2 12 12 17 22 12"></polyline>
          </svg>
          Child Part Master
        </a>

        <!-- Sub 4. Process Master -->
        <a href="process-master.php" class="submenu-item <?= ($currentScript === 'process-master.php' || $currentScript === 'process-master') ? 'active' : '' ?>" title="Process Master">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          Process Master
        </a>

        <!-- Sub 5. Part Master -->
        <a href="part-master.php" class="submenu-item <?= ($currentScript === 'part-master.php' || $currentScript === 'part-master') ? 'active' : '' ?>" title="Part Master">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
          </svg>
          Part Master
        </a>
      </div>
    </div>

    <!-- 5. RM In -->
    <a href="rm-in.php" class="menu-item <?= ($currentScript === 'rm-in.php' || $currentScript === 'rm-in') ? 'active' : '' ?>" title="RM In">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
      </svg>
      RM In
    </a>

    <!-- 6. Child Part In -->
    <a href="child-part-in.php" class="menu-item <?= ($currentScript === 'child-part-in.php' || $currentScript === 'child-part-in') ? 'active' : '' ?>" title="Child Part In">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
        <polyline points="2 17 12 22 22 17"></polyline>
        <polyline points="2 12 12 17 22 12"></polyline>
      </svg>
      Child Part In
    </a>

    <!-- 7. MIP (Material Issue for Production) -->
    <a href="mip.php" class="menu-item <?= ($currentScript === 'mip.php' || $currentScript === 'mip') ? 'active' : '' ?>" title="Material Issue for Production (MIP)">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="17 8 12 3 7 8"></polyline>
        <line x1="12" y1="3" x2="12" y2="15"></line>
      </svg>
      MIP
    </a>

    <!-- 8. Production -->
    <a href="production.php" class="menu-item <?= ($currentScript === 'production.php' || $currentScript === 'production' || $currentScript === 'dpr.php' || $currentScript === 'dpr') ? 'active' : '' ?>" title="Production">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4H2z"></path>
        <line x1="17" y1="18" x2="17.01" y2="18"></line>
        <line x1="12" y1="18" x2="12.01" y2="18"></line>
        <line x1="7" y1="18" x2="7.01" y2="18"></line>
      </svg>
      Production
    </a>

    <!-- 9. FG Store -->
    <a href="fg-store.php" class="menu-item <?= ($currentScript === 'fg-store.php' || $currentScript === 'fg-store') ? 'active' : '' ?>" title="FG Store">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="21 8 21 21 3 21 3 8"></polyline>
        <rect x="1" y="3" width="22" height="5"></rect>
        <line x1="10" y1="12" x2="14" y2="12"></line>
      </svg>
      FG Store
    </a>

    <!-- 10. Reports -->
    <a href="reports.php" class="menu-item <?= ($currentScript === 'reports.php' || $currentScript === 'reports') ? 'active' : '' ?>" title="Reports">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="20" x2="18" y2="10"></line>
        <line x1="12" y1="20" x2="12" y2="4"></line>
        <line x1="6" y1="20" x2="6" y2="14"></line>
      </svg>
      Reports
    </a>

  </nav>

  <!-- Sidebar Bottom Logout -->
  <div class="sidebar-bottom">
    <a href="auth/logout.php" class="logout-link" id="logoutLink">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
        <polyline points="16 17 21 12 16 7"></polyline>
        <line x1="21" y1="12" x2="9" y2="12"></line>
      </svg>
      Logout
    </a>
  </div>

</aside>

<!-- Script for Sidebar Dropdown Toggle -->
<script>
  (function() {
    const dropdownBtn = document.getElementById('masterDropdownBtn');
    const dropdown = document.getElementById('masterDropdown');
    if (dropdownBtn && dropdown) {
      dropdownBtn.addEventListener('click', function(e) {
        e.preventDefault();
        dropdown.classList.toggle('open');
        const isOpen = dropdown.classList.contains('open');
        dropdownBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }
  })();
</script>
