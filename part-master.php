<?php
/**
 * WIP Management Portal - Part Master
 */
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/db.php';

// Fetch real data from MSSQL database
$pdo = getDBConnection();
$parts = [];
$childPartsAvailable = [];
$totalCount = 0;
$activeCount = 0;
$totalChildPartsCount = 0;

if ($pdo) {
    try {
        // Fetch all registered parts
        $stmt = $pdo->query("SELECT id, part_code, part_name, child_parts, status FROM part_master ORDER BY id DESC");
        $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCount = count($parts);
        foreach ($parts as $p) {
            if (strcasecmp($p['status'] ?? '', 'active') === 0) {
                $activeCount++;
            }
            $cpArr = json_decode($p['child_parts'] ?? '[]', true) ?: [];
            $totalChildPartsCount += count($cpArr);
        }

        // Fetch active child parts to populate selection dropdowns
        $cpStmt = $pdo->query("SELECT id, part_code, part_name, grade_spec, uom FROM child_part_master WHERE status = 'Active' ORDER BY part_code ASC");
        $childPartsAvailable = $cpStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }
}

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
      max-width: 560px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      transform: scale(0.96) translateY(-8px);
      transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .modal-overlay.active .modal-card {
      transform: scale(1) translateY(0);
    }

    .modal-header {
      padding: 16px 20px;
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
      padding: 20px;
      max-height: 75vh;
      overflow-y: auto;
    }

    /* Simple 2-column form grid */
    .popup-form-grid {
      display: grid;
      grid-template-columns: 1fr 140px;
      gap: 14px;
    }

    @media (max-width: 520px) {
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

    /* Simple Child Parts Section */
    .cp-section {
      margin-top: 16px;
      padding-top: 14px;
      border-top: 1px solid var(--border);
    }

    .cp-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .cp-label {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
    }

    .btn-add-cp {
      background: none;
      border: none;
      color: var(--primary);
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      padding: 3px 6px;
      border-radius: 4px;
    }

    .btn-add-cp:hover {
      background: var(--primary-light);
    }

    .cp-rows {
      display: flex;
      flex-direction: column;
      gap: 8px;
      max-height: 190px;
      overflow-y: auto;
    }

    .cp-row {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .cp-row select {
      flex: 1;
      height: 38px;
      padding: 0 10px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.84rem;
      font-family: inherit;
      color: var(--text-main);
      background: #ffffff;
      outline: none;
      box-sizing: border-box;
    }

    .cp-row select:focus {
      border-color: var(--primary);
    }

    .cp-row input {
      width: 80px;
      height: 38px;
      padding: 0 10px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.84rem;
      font-family: inherit;
      color: var(--text-main);
      background: #ffffff;
      outline: none;
      text-align: center;
      box-sizing: border-box;
    }

    .cp-row input:focus {
      border-color: var(--primary);
    }

    .btn-remove-cp {
      width: 38px;
      height: 38px;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 6px;
      color: #94a3b8;
      cursor: pointer;
      font-size: 1.2rem;
      line-height: 1;
      transition: all 0.15s ease;
      box-sizing: border-box;
    }

    .btn-remove-cp:hover {
      color: #ef4444;
      border-color: #fecaca;
      background: #fee2e2;
    }

    .cp-empty-notice {
      padding: 6px 0;
      font-size: 0.82rem;
      color: var(--text-sub);
    }

    /* Clean Child Part Chips in Table */
    .cp-chip-list {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
    }

    .cp-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 7px;
      background: #f1f5f9;
      border-radius: 4px;
      font-size: 0.78rem;
      color: var(--text-main);
      white-space: nowrap;
    }

    .cp-chip .cp-code {
      font-weight: 600;
      color: var(--primary);
    }

    .cp-chip .cp-qty {
      color: var(--text-sub);
      font-size: 0.74rem;
    }

    .modal-footer {
      padding: 14px 20px;
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
      max-width: 400px !important;
      text-align: center;
      padding: 24px 20px;
    }

    .confirm-icon-box {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: #fee2e2;
      color: #ef4444;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 12px auto;
    }

    .confirm-icon-box svg {
      width: 24px;
      height: 24px;
    }

    .confirm-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    .confirm-desc {
      font-size: 0.86rem;
      color: var(--text-sub);
      line-height: 1.45;
      margin-bottom: 20px;
    }

    .confirm-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
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
            <div class="stat-number in-progress" id="statChildPartsPt"><?php echo $totalChildPartsCount; ?></div>
            <div class="stat-title">Mapped Child Parts</div>
          </div>
        </div>

        <!-- ==========================================
             Simple Table Card
             ========================================== -->
        <div class="simple-card table-box">
          <div class="table-bar">
            <h2 class="box-title">Part Master Directory</h2>
            <div class="table-actions">
              <input type="text" id="ptSearch" class="simple-input" placeholder="Search Part Code, Name, Child Parts...">
              <?php if (hasPermission('part_master', 'create')): ?>
              <button type="button" class="btn-primary" id="openAddPtModalBtn">+ Add Part</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="table-wrap">
            <table class="simple-table" id="ptTable">
              <thead>
                <tr>
                  <th style="width: 50px;">Sr No.</th>
                  <th style="width: 140px;">Part Code</th>
                  <th>Part Name</th>
                  <th>Child Parts</th>
                  <th style="width: 90px;">Status</th>
                  <th style="width: 120px;">Action</th>
                </tr>
              </thead>
              <tbody id="ptTableBody">
                <?php if (!empty($parts)): ?>
                  <?php $sr = 1; ?>
                  <?php foreach ($parts as $item): ?>
                    <?php 
                      $isActive = (strcasecmp($item['status'] ?? 'Active', 'Active') === 0);
                      $cpList = json_decode($item['child_parts'] ?? '[]', true) ?: [];
                    ?>
                    <tr data-id="<?php echo htmlspecialchars($item['id']); ?>" data-child-parts="<?php echo htmlspecialchars($item['child_parts'] ?? '[]'); ?>">
                      <td style="color: var(--text-sub); font-weight: 600;"><?php echo $sr++; ?></td>
                      <td><strong class="col-part-code"><?php echo htmlspecialchars($item['part_code']); ?></strong></td>
                      <td class="col-part-name"><?php echo htmlspecialchars($item['part_name']); ?></td>
                      <td class="col-child-parts">
                        <?php if (!empty($cpList)): ?>
                          <div class="cp-chip-list">
                            <?php foreach ($cpList as $cp): ?>
                              <span class="cp-chip" title="<?php echo htmlspecialchars($cp['name'] ?? ''); ?>">
                                <span class="cp-code"><?php echo htmlspecialchars($cp['code'] ?? ''); ?></span>
                                <span class="cp-qty">(x<?php echo htmlspecialchars($cp['qty'] ?? 1); ?>)</span>
                              </span>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <span style="color: var(--text-sub); font-size: 0.82rem;">-</span>
                        <?php endif; ?>
                      </td>
                      <td class="col-status">
                        <?php if ($isActive): ?>
                          <span class="tag tag-completed">Active</span>
                        <?php else: ?>
                          <span class="tag" style="background:#f1f5f9; color:#64748b;">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="display: flex; gap: 6px;">
                          <?php if (hasPermission('part_master', 'update')): ?>
                          <button type="button" class="btn-edit" title="Edit Part">Edit</button>
                          <?php endif; ?>
                          <?php if (hasPermission('part_master', 'delete')): ?>
                          <button type="button" class="btn-delete" title="Delete Part">Delete</button>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr id="emptyTableRow">
                    <td colspan="6" class="empty-row-msg">No parts found. Click "+ Add Part" to create one.</td>
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

            <!-- 2. Status -->
            <div class="pt-form-group">
              <label for="inputStatus">Status *</label>
              <select id="inputStatus" class="form-control" required>
                <option value="Active" selected>Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>

            <!-- 3. Part Name -->
            <div class="pt-form-group" style="grid-column: 1 / -1;">
              <label for="inputPartName">Part Name *</label>
              <input type="text" id="inputPartName" class="form-control" placeholder="e.g., Rear Bumper Stiffener" required>
            </div>

          </div>

          <!-- Clean Child Parts Section -->
          <div class="cp-section">
            <div class="cp-header">
              <label class="cp-label">Child Parts</label>
              <button type="button" class="btn-add-cp" id="btnAddCpRow">+ Add Child Part</button>
            </div>

            <div id="cpRowsContainer" class="cp-rows">
              <!-- Dynamic child part rows injected here -->
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
      <p class="confirm-desc">Are you sure you want to delete part <strong id="deleteTargetCode" style="color:var(--text-main);"></strong>? This part will be removed from the database.</p>
      <div class="confirm-actions">
        <button type="button" class="btn-secondary" id="cancelDeleteBtn">Cancel</button>
        <button type="button" class="btn-danger-confirm" id="confirmDeleteBtn">Yes, Delete</button>
      </div>
    </div>
  </div>

  <!-- Client-side Interactive Logic & Database API Integration -->
  <script>
    // Available child parts from database
    const availableChildParts = <?php echo json_encode($childPartsAvailable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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

      // Child Parts Elements
      const cpRowsContainer = document.getElementById('cpRowsContainer');
      const btnAddCpRow = document.getElementById('btnAddCpRow');

      // Delete Modal Elements
      const deleteConfirmModal = document.getElementById('deleteConfirmModal');
      const deleteTargetCode = document.getElementById('deleteTargetCode');
      const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

      let pendingDeleteId = null;
      let pendingDeleteRow = null;
      let pendingDeleteCode = null;

      // Stats Elements
      const statTotalPt = document.getElementById('statTotalPt');
      const statActivePt = document.getElementById('statActivePt');
      const statChildPartsPt = document.getElementById('statChildPartsPt');

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

      // Update Sr No. across all visible rows
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
        let totalChildParts = 0;

        rows.forEach(r => {
          const statusCell = r.querySelector('.col-status');
          if (statusCell && statusCell.textContent.toLowerCase().includes('active')) {
            active++;
          }
          const cpDataRaw = r.getAttribute('data-child-parts');
          if (cpDataRaw) {
            try {
              const cpArr = JSON.parse(cpDataRaw);
              if (Array.isArray(cpArr)) {
                totalChildParts += cpArr.length;
              }
            } catch (err) {}
          }
        });

        if (statTotalPt) statTotalPt.textContent = total;
        if (statActivePt) statActivePt.textContent = active;
        if (statChildPartsPt) statChildPartsPt.textContent = totalChildParts;

        const emptyRow = document.getElementById('emptyTableRow');
        if (total === 0) {
          if (!emptyRow) {
            const tr = document.createElement('tr');
            tr.id = 'emptyTableRow';
            tr.innerHTML = `<td colspan="6" class="empty-row-msg">No parts found. Click "+ Add Part" to create one.</td>`;
            ptTableBody.appendChild(tr);
          }
        } else if (emptyRow) {
          emptyRow.remove();
        }
      }

      // Helper function to escape HTML
      function escapeHtml(text) {
        if (!text) return '';
        return String(text)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      // Build Child Part Row Element
      function createChildPartRow(selectedCode = '', qty = 1) {
        const row = document.createElement('div');
        row.className = 'cp-row';

        let optionsHtml = '<option value="">-- Select Child Part --</option>';
        availableChildParts.forEach(cp => {
          const isSelected = (cp.part_code === selectedCode) ? 'selected' : '';
          const label = `${cp.part_code} - ${cp.part_name}` + (cp.uom ? ` (${cp.uom})` : '');
          optionsHtml += `<option value="${escapeHtml(cp.part_code)}" data-name="${escapeHtml(cp.part_name)}" ${isSelected}>${escapeHtml(label)}</option>`;
        });

        // If selectedCode not in list
        if (selectedCode && !availableChildParts.some(cp => cp.part_code === selectedCode)) {
          optionsHtml += `<option value="${escapeHtml(selectedCode)}" data-name="" selected>${escapeHtml(selectedCode)}</option>`;
        }

        row.innerHTML = `
          <select class="cp-select" required>
            ${optionsHtml}
          </select>
          <input type="number" step="0.001" min="0.001" class="cp-qty-input" placeholder="Qty" value="${qty}" title="Qty per Part" required>
          <button type="button" class="btn-remove-cp" title="Remove">&times;</button>
        `;

        // Remove row handler
        row.querySelector('.btn-remove-cp').addEventListener('click', () => {
          row.remove();
          if (cpRowsContainer.children.length === 0) {
            showEmptyNotice();
          }
        });

        return row;
      }

      function showEmptyNotice() {
        cpRowsContainer.innerHTML = `<div class="cp-empty-notice" id="cpEmptyNotice">No child parts added. Click "+ Add Child Part" to attach one.</div>`;
      }

      function addCpRow(selectedCode = '', qty = 1) {
        const emptyNotice = document.getElementById('cpEmptyNotice');
        if (emptyNotice) emptyNotice.remove();

        const row = createChildPartRow(selectedCode, qty);
        cpRowsContainer.appendChild(row);
      }

      // Add Row Button
      if (btnAddCpRow) {
        btnAddCpRow.addEventListener('click', () => {
          addCpRow('', 1);
        });
      }

      // Helper to generate Child Part chips HTML
      function generateCpChipsHtml(cpList) {
        if (!Array.isArray(cpList) || cpList.length === 0) {
          return '<span style="color: var(--text-sub); font-size: 0.82rem;">-</span>';
        }

        let html = '<div class="cp-chip-list">';
        cpList.forEach(cp => {
          const code = escapeHtml(cp.code || '');
          const name = escapeHtml(cp.name || '');
          const qty = escapeHtml(cp.qty || 1);
          html += `
            <span class="cp-chip" title="${name}">
              <span class="cp-code">${code}</span>
              <span class="cp-qty">(x${qty})</span>
            </span>
          `;
        });
        html += '</div>';
        return html;
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
        cpRowsContainer.innerHTML = '';
      }

      // Open Add Modal
      if (openAddPtModalBtn) {
        openAddPtModalBtn.addEventListener('click', () => {
          ptPopupForm.reset();
          editItemId.value = '';
          document.getElementById('inputStatus').value = 'Active';
          cpRowsContainer.innerHTML = '';
          addCpRow('', 1);
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
        pendingDeleteId = null;
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

      // Form Submission (Add / Edit) with Database API
      if (ptPopupForm) {
        ptPopupForm.addEventListener('submit', async (e) => {
          e.preventDefault();

          const id = editItemId.value ? parseInt(editItemId.value, 10) : 0;
          const code = document.getElementById('inputPartCode').value.trim();
          const name = document.getElementById('inputPartName').value.trim();
          const status = document.getElementById('inputStatus').value || 'Active';

          if (!code || !name) {
            showToast('Please fill in Part Code and Part Name.', 'error');
            return;
          }

          // Collect Child Parts
          const childParts = [];
          const rows = cpRowsContainer.querySelectorAll('.cp-row');
          rows.forEach(r => {
            const sel = r.querySelector('.cp-select');
            const qtyInput = r.querySelector('.cp-qty-input');
            if (sel && sel.value) {
              const opt = sel.options[sel.selectedIndex];
              childParts.push({
                code: sel.value,
                name: opt.getAttribute('data-name') || '',
                qty: parseFloat(qtyInput.value) || 1
              });
            }
          });

          // Disable submit button during request
          savePtSubmitBtn.disabled = true;
          const origBtnText = savePtSubmitBtn.textContent;
          savePtSubmitBtn.textContent = 'Saving...';

          try {
            const payload = {
              action: id > 0 ? 'update' : 'create',
              id: id,
              part_code: code,
              part_name: name,
              status: status,
              child_parts: childParts
            };

            const resp = await fetch('api/part_master.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload)
            });

            const res = await resp.json();

            if (!res.success) {
              showToast(res.message || 'Error occurred while saving.', 'error');
              return;
            }

            const activeTag = status === 'Active' 
              ? '<span class="tag tag-completed">Active</span>' 
              : '<span class="tag" style="background:#f1f5f9; color:#64748b;">Inactive</span>';

            const chipsHtml = generateCpChipsHtml(childParts);
            const childPartsJsonStr = JSON.stringify(childParts);

            const actionHtml = `
              <div style="display: flex; gap: 6px;">
                <button type="button" class="btn-edit" title="Edit Part">Edit</button>
                <button type="button" class="btn-delete" title="Delete Part">Delete</button>
              </div>
            `;

            if (id > 0) {
              // Update existing table row
              const targetRow = ptTableBody.querySelector(`tr[data-id="${id}"]`);
              if (targetRow) {
                targetRow.setAttribute('data-child-parts', childPartsJsonStr);
                targetRow.querySelector('.col-part-code').textContent = code;
                targetRow.querySelector('.col-part-name').textContent = name;
                targetRow.querySelector('.col-child-parts').innerHTML = chipsHtml;
                targetRow.querySelector('.col-status').innerHTML = activeTag;
              }
              showToast(`Part "${code}" updated successfully.`, 'success');
            } else {
              // Create new table row
              const emptyRow = document.getElementById('emptyTableRow');
              if (emptyRow) emptyRow.remove();

              const newId = res.data && res.data.id ? res.data.id : Date.now();
              const tr = document.createElement('tr');
              tr.setAttribute('data-id', newId);
              tr.setAttribute('data-child-parts', childPartsJsonStr);
              tr.innerHTML = `
                <td style="color: var(--text-sub); font-weight: 600;">1</td>
                <td><strong class="col-part-code">${escapeHtml(code)}</strong></td>
                <td class="col-part-name">${escapeHtml(name)}</td>
                <td class="col-child-parts">${chipsHtml}</td>
                <td class="col-status">${activeTag}</td>
                <td>${actionHtml}</td>
              `;
              ptTableBody.prepend(tr);
              reindexSrNo();
              showToast(`New Part "${code}" created successfully.`, 'success');
            }

            closeModal();
            updateCounters();
          } catch (err) {
            console.error(err);
            showToast('Network or server error while saving part.', 'error');
          } finally {
            savePtSubmitBtn.disabled = false;
            savePtSubmitBtn.textContent = origBtnText;
          }
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
            const code = row.querySelector('.col-part-code') ? row.querySelector('.col-part-code').textContent.trim() : '';
            const name = row.querySelector('.col-part-name') ? row.querySelector('.col-part-name').textContent.trim() : '';
            const statusCell = row.querySelector('.col-status');
            const status = (statusCell && statusCell.textContent.toLowerCase().includes('active')) ? 'Active' : 'Inactive';

            let cpList = [];
            const rawCp = row.getAttribute('data-child-parts');
            if (rawCp) {
              try {
                cpList = JSON.parse(rawCp);
              } catch (err) {}
            }

            document.getElementById('inputPartCode').value = code;
            document.getElementById('inputPartName').value = name;
            document.getElementById('inputStatus').value = status;
            editItemId.value = itemId;

            // Populate child parts
            cpRowsContainer.innerHTML = '';
            if (Array.isArray(cpList) && cpList.length > 0) {
              cpList.forEach(cp => {
                addCpRow(cp.code || '', cp.qty || 1);
              });
            } else {
              showEmptyNotice();
            }

            openModal(`Edit Part: ${code}`, 'Update Part');
          }

          // Delete Action
          if (e.target.classList.contains('btn-delete')) {
            const codeEl = row.querySelector('.col-part-code');
            const code = codeEl ? codeEl.textContent.trim() : 'Part';
            pendingDeleteId = itemId;
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

      // Confirm Delete Action with Database API
      if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
          if (!pendingDeleteRow) return;

          confirmDeleteBtn.disabled = true;
          confirmDeleteBtn.textContent = 'Deleting...';

          try {
            const resp = await fetch('api/part_master.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                action: 'delete',
                id: pendingDeleteId,
                part_code: pendingDeleteCode
              })
            });

            const res = await resp.json();

            if (!res.success) {
              showToast(res.message || 'Failed to delete part.', 'error');
              return;
            }

            const code = pendingDeleteCode || 'Part';
            pendingDeleteRow.remove();
            closeDeleteConfirmModal();
            reindexSrNo();
            updateCounters();
            showToast(`Part "${code}" deleted successfully.`, 'success');
          } catch (err) {
            console.error(err);
            showToast('Error deleting part from server.', 'error');
          } finally {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.textContent = 'Yes, Delete';
          }
        });
      }

    });
  </script>
</body>
</html>
