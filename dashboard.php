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
    
    <!-- Reusable Sidebar Component -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- ==========================================
         Main Area
         ========================================== -->
    <div class="main-content">
      
      <!-- Reusable Top Bar Component -->
      <?php include __DIR__ . '/includes/header.php'; ?>

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
