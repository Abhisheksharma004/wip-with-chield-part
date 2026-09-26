<?php
/**
 * WIP Management Portal - Reusable Topbar Header Component
 * Auto-detects the page title from current script if $pageTitle is not provided.
 */
if (!isset($pageTitle)) {
    $script = basename($_SERVER['PHP_SELF']);
    $pageTitles = [
        'dashboard.php' => 'Dashboard',
        'vendor-master.php' => 'Vendor Master',
        'rm-master.php' => 'RM Master',
        'child-part-master.php' => 'Child Part Master',
        'child-master.php' => 'Child Part Master',
        'process-master.php' => 'Process Master',
        'part-master.php' => 'Part Master',
        'dpr.php' => 'DPR',
        'bom.php' => 'BOM',
        'rm-in.php' => 'RM Inward',
        'child-part-in.php' => 'Child Part Inward',
        'mip.php' => 'Material Issue for Production (MIP)',
        'production.php' => 'Production Entry / DPR',
        'fg-store.php' => 'Finished Goods Store (FG Store)',
        'reports.php' => 'Reports & Analytics',
    ];
    $pageTitle = $pageTitles[$script] ?? 'WIP Management';
}

$userName = $currentUser['name'] ?? ($_SESSION['name'] ?? 'User');
$userRole = $currentUser['role'] ?? ($_SESSION['role'] ?? 'Staff');
?>
<!-- Reusable Topbar Header Component -->
<header class="topbar">
  <div class="topbar-left">
    <button id="menuToggleBtn" class="menu-toggle-btn" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
  </div>

  <div class="topbar-right">
    <span class="user-greeting">
      Welcome, <strong><?php echo htmlspecialchars($userName); ?></strong> 
      <span style="font-size: 0.75rem; padding: 2px 7px; background: #eff6ff; color: #2563eb; border-radius: 999px; margin-left: 4px; font-weight: 600;">
        <?php echo htmlspecialchars($userRole); ?>
      </span>
    </span>
    <a href="auth/logout.php" class="btn-sm-logout">Logout</a>
  </div>
</header>
