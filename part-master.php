<?php
/**
 * WIP Management Portal - Part Master (UI Only)
 */
require_once __DIR__ . '/auth/check_auth.php';

// Initial Mock / Seed data matching requested fields:
// Part Code, Part Name, Category, Gross Weight / Pc (kg), Net Weight / Pc (kg), Route Code, Route Revision, Status, Remarks
$parts = [
    [
        'id' => 1,
        'part_code' => 'PT-1001',
        'part_name' => 'Front Chassis Cross-Member',
        'category' => 'Sheet Metal Sub-Assy',
        'gross_weight' => '3.450',
        'net_weight' => '3.120',
        'route_code' => 'RT-CH-01',
        'route_rev' => 'Rev 1.0',
        'status' => 'Active',
        'remarks' => 'Critical mounting structural member'
    ],
    [
        'id' => 2,
        'part_code' => 'PT-1002',
        'part_name' => 'Rear Suspension Arm',
        'category' => 'Forged / Machined',
        'gross_weight' => '5.800',
        'net_weight' => '5.250',
        'route_code' => 'RT-SUS-03',
        'route_rev' => 'Rev 2.1',
        'status' => 'Active',
        'remarks' => 'High tensile steel forged arm'
    ],
    [
        'id' => 3,
        'part_code' => 'PT-1003',
        'part_name' => 'Engine Mounting Bracket LH',
        'category' => 'Pressed Part',
        'gross_weight' => '1.650',
        'net_weight' => '1.480',
        'route_code' => 'RT-ENG-02',
        'route_rev' => 'Rev 0',
        'status' => 'Active',
        'remarks' => 'Anti-vibration bracket assembly'
    ],
    [
        'id' => 4,
        'part_code' => 'PT-1004',
        'part_name' => 'Door Hinge Upper Assembly',
        'category' => 'Sub-Assembly',
        'gross_weight' => '0.850',
        'net_weight' => '0.780',
        'route_code' => 'RT-HNG-01',
        'route_rev' => 'Rev 1.2',
        'status' => 'Active',
        'remarks' => 'Supplied to final assembly shop'
    ],
    [
        'id' => 5,
        'part_code' => 'PT-1005',
        'part_name' => 'Exhaust Heat Shield Rear',
        'category' => 'Sheet Metal',
        'gross_weight' => '0.420',
        'net_weight' => '0.380',
        'route_code' => 'RT-EXH-04',
        'route_rev' => 'Rev 0',
        'status' => 'Inactive',
        'remarks' => 'Aluminized steel component on hold'
    ]
];

$totalCount = count($parts);
$activeCount = 0;
$categoriesSet = [];

foreach ($parts as $p) {
    if (strcasecmp($p['status'], 'Active') === 0) {
        $activeCount++;
    }
    $cat = trim($p['category']);
    if (!empty($cat) && !in_array(strtolower($cat), $categoriesSet)) {
        $categoriesSet[] = strtolower($cat);
    }
}
$categoriesCount = count($categoriesSet);

$pageTitle = 'Part Master';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Part Master - WIP Management Portal</title>
  
  <!-- Modern Clean Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="css/dashboard.css">

  <style>
    /* Table specific & utility styles */
    .btn-edit {
      padding: 4px 10px;
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 500;
      color: var(--primary);
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .btn-edit:hover {
      background: var(--primary-light);
      border-color: var(--primary);
    }

    .btn-delete {
      padding: 4px 10px;
      background: #ffffff;
      border: 1px solid #fecaca;
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 500;
      color: #ef4444;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .btn-delete:hover {
      background: #fee2e2;
      border-color: #ef4444;
    }

    /* Toast Notification */
    .toast-container {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    }

    .toast {
      min-width: 280px;
      max-width: 380px;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-left: 4px solid var(--primary);
      opacity: 0;
      transform: translateY(-12px);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: auto;
      font-size: 0.88rem;
      color: var(--text-main);
    }

    .toast.show {
      opacity: 1;
      transform: translateY(0);
    }

    .toast-success {
      border-left-color: #10b981;
    }

    .toast-error {
      border-left-color: #ef4444;
    }

    .toast-icon {
      flex-shrink: 0;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .toast-icon svg {
      width: 18px;
      height: 18px;
    }

    .toast-success .toast-icon {
      color: #10b981;
    }

    .toast-error .toast-icon {
      color: #ef4444;
    }

    .toast-message {
      flex: 1;
      line-height: 1.4;
      font-weight: 500;
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(2px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
      padding: 16px;
    }

    .modal-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .modal-card {
      background: #ffffff;
      border-radius: 10px;
      width: 100%;
      max-width: 680px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      transform: scale(0.96) translateY(-8px);
      transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-overlay.active .modal-card {
      transform: scale(1) translateY(0);
    }

    .modal-header {
      padding: 16px 22px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #ffffff;
    }

    .modal-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text-main);
      margin: 0;
    }

    .modal-close-btn {
      background: transparent;
      border: none;
      font-size: 1.5rem;
      line-height: 1;
      color: var(--text-sub);
      cursor: pointer;
      padding: 0 4px;
    }

    .modal-close-btn:hover {
      color: var(--text-main);
    }

    .modal-body {
      padding: 20px 22px;
      max-height: 75vh;
      overflow-y: auto;
    }

    .popup-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px 18px;
    }

    @media (max-width: 620px) {
      .popup-form-grid {
        grid-template-columns: 1fr;
      }
    }

    .pt-form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .pt-form-group label {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
    }

    .pt-form-group .form-control {
      width: 100%;
      height: 38px;
      padding: 0 12px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-family: inherit;
      font-size: 0.86rem;
      color: var(--text-main);
      background: #ffffff;
      outline: none;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
      box-sizing: border-box;
    }

    .pt-form-group .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .modal-footer {
      padding: 14px 22px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 10px;
      background: #f8fafc;
    }

    .btn-secondary {
      height: 36px;
      padding: 0 14px;
      background: #ffffff;
      color: var(--text-main);
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s ease;
    }

    .btn-secondary:hover {
      background: #f1f5f9;
    }

    /* Delete Confirm Modal */
    .confirm-modal-card {
      max-width: 420px !important;
      text-align: center;
      padding: 24px 20px;
    }

    .confirm-icon-box {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: #fee2e2;
      color: #ef4444;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px auto;
    }

    .confirm-icon-box svg {
      width: 26px;
      height: 26px;
    }

    .confirm-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .confirm-desc {
      font-size: 0.88rem;
      color: var(--text-sub);
      line-height: 1.45;
      margin-bottom: 22px;
    }

    .confirm-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .btn-danger-confirm {
      height: 36px;
      padding: 0 18px;
      background: #ef4444;
      color: #ffffff;
      border: none;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s ease;
    }

    .btn-danger-confirm:hover {
      background: #dc2626;
    }
  </style>
</head>
<body>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="toast-container" aria-live="polite"></div>

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
            <div class="stat-number" id="statTotalPt"><?php echo $totalCount; ?></div>
            <div class="stat-title">Total Parts</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number completed" id="statActivePt"><?php echo $activeCount; ?></div>
            <div class="stat-title">Active Parts</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number in-progress" id="statCategoriesPt"><?php echo $categoriesCount; ?></div>
            <div class="stat-title">Product Categories</div>
          </div>
        </div>

        <!-- ==========================================
             Simple Table Card
             ========================================== -->
        <div class="simple-card table-box">
          <div class="table-bar">
            <h2 class="box-title">Part Master Directory</h2>
            <div class="table-actions">
              <input type="text" id="ptSearch" class="simple-input" placeholder="Search Part Code, Name, Category, Route...">
              <button type="button" class="btn-primary" id="openAddPtModalBtn">+ Add Part</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="simple-table" id="ptTable">
              <thead>
                <tr>
                  <th style="width: 50px;">Sr No.</th>
                  <th>Part Code</th>
                  <th>Part Name</th>
                  <th>Category</th>
                  <th>Gross Weight / Pc (kg)</th>
                  <th>Net Weight / Pc (kg)</th>
                  <th>Route Code</th>
                  <th>Route Revision</th>
                  <th>Status</th>
                  <th>Remarks</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="ptTableBody">
                <?php if (!empty($parts)): ?>
                  <?php $sr = 1; ?>
                  <?php foreach ($parts as $item): ?>
                    <?php 
                      $isActive = (strcasecmp($item['status'] ?? 'Active', 'Active') === 0);
                    ?>
                    <tr data-id="<?php echo htmlspecialchars($item['id']); ?>">
                      <td style="color: var(--text-sub); font-weight: 600;"><?php echo $sr++; ?></td>
                      <td><strong><?php echo htmlspecialchars($item['part_code']); ?></strong></td>
                      <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                      <td><?php echo htmlspecialchars($item['category']); ?></td>
                      <td><?php echo htmlspecialchars($item['gross_weight'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($item['net_weight'] ?: '-'); ?></td>
                      <td><span style="font-family: monospace; font-size: 0.84rem; font-weight: 600;"><?php echo htmlspecialchars($item['route_code'] ?: '-'); ?></span></td>
                      <td><?php echo htmlspecialchars($item['route_rev'] ?: '-'); ?></td>
                      <td>
                        <?php if ($isActive): ?>
                          <span class="tag tag-completed">Active</span>
                        <?php else: ?>
                          <span class="tag" style="background:#f1f5f9; color:#64748b;">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($item['remarks'] ?: '-'); ?></td>
                      <td>
                        <div style="display: flex; gap: 6px;">
                          <button type="button" class="btn-edit" title="Edit Part">Edit</button>
                          <button type="button" class="btn-delete" title="Delete Part">Delete</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr id="emptyTableRow">
                    <td colspan="11" class="empty-row-msg">No parts found. Click "+ Add Part" to create one.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

    </div>

  </div>

  <!-- ==========================================
       Add / Edit Part Popup Modal
       ========================================== -->
  <div id="ptPopupModal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title" id="modalFormTitle">+ Add Part</h3>
        <button type="button" class="modal-close-btn" id="closeModalBtn" aria-label="Close modal">&times;</button>
      </div>

      <form id="ptPopupForm">
        <input type="hidden" id="editItemId" value="">

        <div class="modal-body">
          <div class="popup-form-grid">
            
            <!-- 1. Part Code -->
            <div class="pt-form-group">
              <label for="inputPartCode">Part Code *</label>
              <input type="text" id="inputPartCode" class="form-control" placeholder="e.g., PT-1006" required>
            </div>

            <!-- 2. Part Name -->
            <div class="pt-form-group">
              <label for="inputPartName">Part Name *</label>
              <input type="text" id="inputPartName" class="form-control" placeholder="e.g., Rear Bumper Stiffener" required>
            </div>

            <!-- 3. Category -->
            <div class="pt-form-group">
              <label for="inputCategory">Category *</label>
              <select id="inputCategory" class="form-control" required>
                <option value="">-- Select Category --</option>
                <option value="Sheet Metal">Sheet Metal</option>
                <option value="Sheet Metal Sub-Assy">Sheet Metal Sub-Assy</option>
                <option value="Forged / Machined">Forged / Machined</option>
                <option value="Pressed Part">Pressed Part</option>
                <option value="Sub-Assembly">Sub-Assembly</option>
                <option value="Finished Goods">Finished Goods</option>
              </select>
            </div>

            <!-- 4. Route Code -->
            <div class="pt-form-group">
              <label for="inputRouteCode">Route Code *</label>
              <input type="text" id="inputRouteCode" class="form-control" placeholder="e.g., RT-BM-01" required>
            </div>

            <!-- 5. Gross Weight / Pc (kg) -->
            <div class="pt-form-group">
              <label for="inputGrossWeight">Gross Weight / Pc (kg)</label>
              <input type="number" step="0.001" min="0" id="inputGrossWeight" class="form-control" placeholder="e.g., 2.450">
            </div>

            <!-- 6. Net Weight / Pc (kg) -->
            <div class="pt-form-group">
              <label for="inputNetWeight">Net Weight / Pc (kg)</label>
              <input type="number" step="0.001" min="0" id="inputNetWeight" class="form-control" placeholder="e.g., 2.120">
            </div>

            <!-- 7. Route Revision -->
            <div class="pt-form-group">
              <label for="inputRouteRev">Route Revision</label>
              <input type="text" id="inputRouteRev" class="form-control" placeholder="e.g., Rev 1.0">
            </div>

            <!-- 8. Status -->
            <div class="pt-form-group">
              <label for="inputStatus">Status *</label>
              <select id="inputStatus" class="form-control" required>
                <option value="Active" selected>Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>

            <!-- 9. Remarks -->
            <div class="pt-form-group" style="grid-column: 1 / -1;">
              <label for="inputRemarks">Remarks</label>
              <input type="text" id="inputRemarks" class="form-control" placeholder="Optional notes...">
            </div>

          </div>
        </div>

        <!-- Action Buttons -->
        <div class="modal-footer">
          <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
          <button type="submit" class="btn-primary" id="savePtSubmitBtn">+ Save Part</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ==========================================
       Delete Confirmation Popup Modal
       ========================================== -->
  <div id="deleteConfirmModal" class="modal-overlay">
    <div class="modal-card confirm-modal-card">
      <div class="confirm-icon-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      </div>
      <h3 class="confirm-title">Confirm Deletion</h3>
      <p class="confirm-desc">Are you sure you want to delete part <strong id="deleteTargetCode" style="color:var(--text-main);"></strong>? This part will be removed from the list.</p>
      <div class="confirm-actions">
        <button type="button" class="btn-secondary" id="cancelDeleteBtn">Cancel</button>
        <button type="button" class="btn-danger-confirm" id="confirmDeleteBtn">Yes, Delete</button>
      </div>
    </div>
  </div>

  <!-- Client-side Interactive Logic (UI Only) -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // DOM Elements
      const ptTableBody = document.getElementById('ptTableBody');
      const ptSearch = document.getElementById('ptSearch');
      const ptPopupModal = document.getElementById('ptPopupModal');
      const ptPopupForm = document.getElementById('ptPopupForm');
      const openAddPtModalBtn = document.getElementById('openAddPtModalBtn');
      const closeModalBtn = document.getElementById('closeModalBtn');
      const cancelModalBtn = document.getElementById('cancelModalBtn');
      const modalFormTitle = document.getElementById('modalFormTitle');
      const savePtSubmitBtn = document.getElementById('savePtSubmitBtn');
      const editItemId = document.getElementById('editItemId');
      const toastContainer = document.getElementById('toastContainer');

      // Delete Modal Elements
      const deleteConfirmModal = document.getElementById('deleteConfirmModal');
      const deleteTargetCode = document.getElementById('deleteTargetCode');
      const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

      let pendingDeleteRow = null;
      let pendingDeleteCode = null;

      // Stats Elements
      const statTotalPt = document.getElementById('statTotalPt');
      const statActivePt = document.getElementById('statActivePt');
      const statCategoriesPt = document.getElementById('statCategoriesPt');

      // Toast Notification Function
      const showToast = (message, type = 'info') => {
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        let iconSvg = '';
        if (type === 'success') {
          iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`;
        } else if (type === 'error') {
          iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;
        } else {
          iconSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
        }

        toast.innerHTML = `
          <span class="toast-icon">${iconSvg}</span>
          <span class="toast-message">${message}</span>
        `;

        toastContainer.appendChild(toast);

        requestAnimationFrame(() => {
          toast.classList.add('show');
        });

        setTimeout(() => {
          toast.classList.remove('show');
          setTimeout(() => {
            if (toast.parentElement) toast.remove();
          }, 280);
        }, 4000);
      };

      // Re-index Sr No. across all visible rows
      function reindexSrNo() {
        const rows = Array.from(ptTableBody.querySelectorAll('tr')).filter(r => r.id !== 'emptyTableRow');
        rows.forEach((r, idx) => {
          if (r.children[0]) {
            r.children[0].textContent = idx + 1;
          }
        });
      }

      // Update Statistics Counters
      function updateCounters() {
        const rows = Array.from(ptTableBody.querySelectorAll('tr')).filter(r => r.id !== 'emptyTableRow');
        let total = rows.length;
        let active = 0;
        let catSet = new Set();

        rows.forEach(r => {
          const cells = r.querySelectorAll('td');
          if (cells.length >= 10) {
            const catText = cells[3].textContent.trim();
            const statusText = cells[8].textContent.trim();
            if (statusText.toLowerCase().includes('active')) active++;
            if (catText && catText !== '-') catSet.add(catText.toLowerCase());
          }
        });

        if (statTotalPt) statTotalPt.textContent = total;
        if (statActivePt) statActivePt.textContent = active;
        if (statCategoriesPt) statCategoriesPt.textContent = catSet.size;

        const emptyRow = document.getElementById('emptyTableRow');
        if (total === 0) {
          if (!emptyRow) {
            const tr = document.createElement('tr');
            tr.id = 'emptyTableRow';
            tr.innerHTML = `<td colspan="11" class="empty-row-msg">No parts found. Click "+ Add Part" to create one.</td>`;
            ptTableBody.appendChild(tr);
          }
        } else if (emptyRow) {
          emptyRow.remove();
        }
      }

      // Open Form Modal
      function openModal(title = '+ Add Part', buttonText = '+ Save Part') {
        modalFormTitle.textContent = title;
        savePtSubmitBtn.textContent = buttonText;
        ptPopupModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
          document.getElementById('inputPartCode').focus();
        }, 100);
      }

      // Close Form Modal
      function closeModal() {
        ptPopupModal.classList.remove('active');
        document.body.style.overflow = '';
        ptPopupForm.reset();
        editItemId.value = '';
        document.getElementById('inputStatus').value = 'Active';
      }

      // Open Add Modal
      if (openAddPtModalBtn) {
        openAddPtModalBtn.addEventListener('click', () => {
          ptPopupForm.reset();
          editItemId.value = '';
          document.getElementById('inputStatus').value = 'Active';
          openModal('+ Add Part', '+ Save Part');
        });
      }

      if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
      if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

      // Close when clicking outside modal
      if (ptPopupModal) {
        ptPopupModal.addEventListener('click', (e) => {
          if (e.target === ptPopupModal) closeModal();
        });
      }

      // Delete Confirm Modal Handlers
      function closeDeleteConfirmModal() {
        if (deleteConfirmModal) {
          deleteConfirmModal.classList.remove('active');
          document.body.style.overflow = '';
        }
        pendingDeleteRow = null;
        pendingDeleteCode = null;
      }

      if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', closeDeleteConfirmModal);

      if (deleteConfirmModal) {
        deleteConfirmModal.addEventListener('click', (e) => {
          if (e.target === deleteConfirmModal) closeDeleteConfirmModal();
        });
      }

      // Escape key to close modals
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
          if (ptPopupModal && ptPopupModal.classList.contains('active')) closeModal();
          if (deleteConfirmModal && deleteConfirmModal.classList.contains('active')) closeDeleteConfirmModal();
        }
      });

      // Real-time table search filter
      if (ptSearch && ptTableBody) {
        ptSearch.addEventListener('input', () => {
          const q = ptSearch.value.toLowerCase().trim();
          const rows = ptTableBody.querySelectorAll('tr');
          rows.forEach(row => {
            if (row.id === 'emptyTableRow') return;
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
          });
        });
      }

      // Submit Form: Client-Side Interactive Handling (UI Only)
      if (ptPopupForm) {
        ptPopupForm.addEventListener('submit', (e) => {
          e.preventDefault();

          const id = editItemId.value ? parseInt(editItemId.value, 10) : 0;
          const code = document.getElementById('inputPartCode').value.trim();
          const name = document.getElementById('inputPartName').value.trim();
          const category = document.getElementById('inputCategory').value;
          const grossWeight = document.getElementById('inputGrossWeight').value.trim() || '-';
          const netWeight = document.getElementById('inputNetWeight').value.trim() || '-';
          const routeCode = document.getElementById('inputRouteCode').value.trim();
          const routeRev = document.getElementById('inputRouteRev').value.trim() || '-';
          const remarks = document.getElementById('inputRemarks').value.trim() || '-';
          const status = document.getElementById('inputStatus').value || 'Active';

          const activeTag = status === 'Active' 
            ? '<span class="tag tag-completed">Active</span>' 
            : '<span class="tag" style="background:#f1f5f9; color:#64748b;">Inactive</span>';

          const actionHtml = `
            <div style="display: flex; gap: 6px;">
              <button type="button" class="btn-edit" title="Edit Part">Edit</button>
              <button type="button" class="btn-delete" title="Delete Part">Delete</button>
            </div>
          `;

          if (id > 0) {
            // Update existing row
            const row = ptTableBody.querySelector(`tr[data-id="${id}"]`);
            if (row) {
              row.children[1].innerHTML = `<strong>${code}</strong>`;
              row.children[2].textContent = name;
              row.children[3].textContent = category;
              row.children[4].textContent = grossWeight;
              row.children[5].textContent = netWeight;
              row.children[6].innerHTML = `<span style="font-family: monospace; font-size: 0.84rem; font-weight: 600;">${routeCode}</span>`;
              row.children[7].textContent = routeRev;
              row.children[8].innerHTML = activeTag;
              row.children[9].textContent = remarks;
              row.children[10].innerHTML = actionHtml;
            }
            showToast(`Part "${code}" updated successfully.`, 'success');
          } else {
            // Create new row
            const emptyRow = document.getElementById('emptyTableRow');
            if (emptyRow) emptyRow.remove();

            const newId = Date.now();
            const tr = document.createElement('tr');
            tr.setAttribute('data-id', newId);
            tr.innerHTML = `
              <td style="color: var(--text-sub); font-weight: 600;">1</td>
              <td><strong>${code}</strong></td>
              <td>${name}</td>
              <td>${category}</td>
              <td>${grossWeight}</td>
              <td>${netWeight}</td>
              <td><span style="font-family: monospace; font-size: 0.84rem; font-weight: 600;">${routeCode}</span></td>
              <td>${routeRev}</td>
              <td>${activeTag}</td>
              <td>${remarks}</td>
              <td>${actionHtml}</td>
            `;
            ptTableBody.prepend(tr);
            reindexSrNo();
            showToast(`New Part "${code}" added successfully.`, 'success');
          }

          closeModal();
          updateCounters();
        });
      }

      // Handle Edit and Delete click on Table rows
      if (ptTableBody) {
        ptTableBody.addEventListener('click', (e) => {
          const row = e.target.closest('tr');
          if (!row || row.id === 'emptyTableRow') return;

          const itemId = row.getAttribute('data-id');

          // Edit Action
          if (e.target.classList.contains('btn-edit')) {
            const cells = row.children;
            const code = cells[1].textContent.trim();
            const name = cells[2].textContent.trim();
            const category = cells[3].textContent.trim();
            const grossWeight = cells[4].textContent.trim();
            const netWeight = cells[5].textContent.trim();
            const routeCode = cells[6].textContent.trim();
            const routeRev = cells[7].textContent.trim();
            const status = cells[8].textContent.trim().toLowerCase().includes('active') ? 'Active' : 'Inactive';
            const remarks = cells[9].textContent.trim();

            document.getElementById('inputPartCode').value = code;
            document.getElementById('inputPartName').value = name;
            
            // Set category
            const catSelect = document.getElementById('inputCategory');
            let match = Array.from(catSelect.options).some(o => o.value.toLowerCase() === category.toLowerCase());
            if (!match && category && category !== '-') {
              catSelect.add(new Option(category, category, true, true));
            }
            catSelect.value = category;

            document.getElementById('inputGrossWeight').value = grossWeight === '-' ? '' : grossWeight;
            document.getElementById('inputNetWeight').value = netWeight === '-' ? '' : netWeight;
            document.getElementById('inputRouteCode').value = routeCode === '-' ? '' : routeCode;
            document.getElementById('inputRouteRev').value = routeRev === '-' ? '' : routeRev;
            document.getElementById('inputRemarks').value = remarks === '-' ? '' : remarks;
            document.getElementById('inputStatus').value = status;

            editItemId.value = itemId;
            openModal(`Edit Part: ${code}`, 'Update Part');
          }

          // Delete Action
          if (e.target.classList.contains('btn-delete')) {
            const code = row.children[1].textContent.trim();
            pendingDeleteRow = row;
            pendingDeleteCode = code;

            if (deleteTargetCode) deleteTargetCode.textContent = code;
            if (deleteConfirmModal) {
              deleteConfirmModal.classList.add('active');
              document.body.style.overflow = 'hidden';
            }
          }
        });
      }

      // Confirm Delete Action
      if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', () => {
          if (!pendingDeleteRow) return;

          const code = pendingDeleteCode || 'Part';
          pendingDeleteRow.remove();
          closeDeleteConfirmModal();
          reindexSrNo();
          updateCounters();
          showToast(`Part "${code}" deleted successfully.`, 'success');
        });
      }

    });
  </script>
</body>
</html>
