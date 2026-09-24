<?php
require_once __DIR__ . '/auth/check_auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - WIP Management Portal</title>

  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Dashboard CSS -->
  <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

  <!-- Mobile Sidebar Backdrop -->
  <div id="sidebarBackdrop" class="sidebar-backdrop"></div>

  <div class="layout-container">
    
    <!-- ==========================================
         Simple Sidebar
         ========================================== -->
    <aside id="sidebar" class="sidebar">
      
      <!-- Brand Header -->
      <div class="sidebar-header">
        <h2 class="sidebar-brand">WIP Management</h2>
        <button id="sidebarCloseBtn" class="mobile-close-btn" aria-label="Close menu">&times;</button>
      </div>

      <!-- Navigation Menu -->
      <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item active">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="9"></rect>
            <rect x="14" y="3" width="7" height="5"></rect>
            <rect x="14" y="12" width="7" height="9"></rect>
            <rect x="3" y="16" width="7" height="5"></rect>
          </svg>
          Dashboard
        </a>
        <a href="vendor-master.php" class="menu-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
          Vendor Master
        </a>
        <a href="rm-master.php" class="menu-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
          RM Master
        </a>
        <a href="#" class="menu-item" title="Child Master">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
            <polyline points="2 17 12 22 22 17"></polyline>
            <polyline points="2 12 12 17 22 12"></polyline>
          </svg>
          Child Master
        </a>
        <a href="#" class="menu-item" title="Process Master">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          Process Master
        </a>
        <a href="#" class="menu-item" title="Part Master">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>
          </svg>
          Part Master
        </a>
        <a href="#" class="menu-item" title="DPR">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="20" x2="18" y2="10"></line>
            <line x1="12" y1="20" x2="12" y2="4"></line>
            <line x1="6" y1="20" x2="6" y2="14"></line>
          </svg>
          DPR
        </a>
        <a href="#" class="menu-item" title="BOM">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          BOM
        </a>
        <a href="#" class="menu-item" title="RM Consumption">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 3v18h18"></path>
            <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
          </svg>
          RM Consumption
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

    <!-- ==========================================
         Main Area
         ========================================== -->
    <div class="main-content">
      
      <!-- Top Bar -->
      <header class="topbar">
        <div class="topbar-left">
          <button id="menuToggleBtn" class="menu-toggle-btn" aria-label="Toggle navigation">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>
          <h1 class="page-title">Dashboard</h1>
        </div>

        <div class="topbar-right">
          <span class="user-greeting">
            Welcome, <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong> 
            <span style="font-size: 0.75rem; padding: 2px 7px; background: #eff6ff; color: #2563eb; border-radius: 999px; margin-left: 4px; font-weight: 600;">
              <?php echo htmlspecialchars($currentUser['role']); ?>
            </span>
          </span>
          <a href="auth/logout.php" class="btn-sm-logout">Logout</a>
        </div>
      </header>

      <!-- Page Body -->
      <div class="content-body">
        
        <!-- Simple 3 Stats Cards -->
        <div class="stats-row">
          <div class="simple-card stat-box">
            <div class="stat-number">28</div>
            <div class="stat-title">Total WIP Sheets</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number in-progress">19</div>
            <div class="stat-title">In Progress</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number completed">9</div>
            <div class="stat-title">Completed</div>
          </div>
        </div>

        <!-- Simple Table Card -->
        <div class="simple-card table-box">
          <div class="table-bar">
            <h2 class="box-title">WIP Excel Sheets</h2>
            <div class="table-actions">
              <input type="text" id="sheetSearch" class="simple-input" placeholder="Search sheet name...">
              <button type="button" class="btn-primary" id="addSheetBtn">+ New Sheet</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="simple-table" id="sheetsTable">
              <thead>
                <tr>
                  <th>Sheet Name</th>
                  <th>Department</th>
                  <th>Assigned To</th>
                  <th>Status</th>
                  <th>Last Updated</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>Q3_Financial_Consolidation.xlsx</strong></td>
                  <td>Finance</td>
                  <td>Rahul Kumar</td>
                  <td><span class="tag tag-progress">In Progress</span></td>
                  <td>Today, 02:40 PM</td>
                  <td><button class="btn-action">View</button></td>
                </tr>
                <tr>
                  <td><strong>Inventory_Reconciliation_Aug.xlsx</strong></td>
                  <td>Supply Chain</td>
                  <td>Priya Mishra</td>
                  <td><span class="tag tag-progress">In Progress</span></td>
                  <td>Today, 11:15 AM</td>
                  <td><button class="btn-action">View</button></td>
                </tr>
                <tr>
                  <td><strong>Payroll_Timesheets_W38.xlsx</strong></td>
                  <td>HR</td>
                  <td><?php echo htmlspecialchars($currentUser['name']); ?></td>
                  <td><span class="tag tag-completed">Completed</span></td>
                  <td>Yesterday</td>
                  <td><button class="btn-action">View</button></td>
                </tr>
                <tr>
                  <td><strong>Operations_Batch_Processing.xlsx</strong></td>
                  <td>Operations</td>
                  <td>Vikram Kohli</td>
                  <td><span class="tag tag-progress">In Progress</span></td>
                  <td>Yesterday</td>
                  <td><button class="btn-action">View</button></td>
                </tr>
                <tr>
                  <td><strong>Vendor_Billing_Audit_Sept.xlsx</strong></td>
                  <td>Accounts</td>
                  <td>Sneha Nair</td>
                  <td><span class="tag tag-completed">Completed</span></td>
                  <td>22 Sept</td>
                  <td><button class="btn-action">View</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

    </div>

  </div>

  <!-- Dashboard JavaScript -->
  <script src="js/dashboard.js"></script>
</body>
</html>
