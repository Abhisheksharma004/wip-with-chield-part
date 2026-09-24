<?php
/**
 * WIP Management Portal - Child Part Master (UI Only)
 */
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/db.php';

// Fetch registered RMs and Vendors from database if available (for dropdown helpers)
$pdo = getDBConnection();
$rmList = [];
$vendorList = [];

if ($pdo) {
    try {
        $rmStmt = $pdo->query("SELECT id, rm_code, rm_name, grade_spec, size_dimension, uom FROM rm_master WHERE status = 'Active' ORDER BY rm_name ASC");
        $rmList = $rmStmt->fetchAll(PDO::FETCH_ASSOC);

        $vStmt = $pdo->query("SELECT id, vendor_name FROM vendor_master WHERE status = 'Active' ORDER BY vendor_name ASC");
        $vendorList = $vStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback gracefully
    }
}

// Initial Mock / Seed data matching requested columns:
// Sr No., Child Part Code, Child Part Name, RM Name, Grade / Specification, Size / Dimension, Nos Per K.g, Req. Qty, UOM, Source, Active
$childParts = [
    [
        'id' => 1,
        'part_code' => 'CP-1001',
        'part_name' => 'Base Mounting Bracket',
        'rm_name' => 'CRCA Sheet 1.2mm',
        'grade_spec' => 'IS 513 CR2',
        'size_dimension' => '120 x 85 mm',
        'nos_per_kg' => '14.50',
        'req_qty' => '2',
        'uom' => 'NOS',
        'source' => 'In-house',
        'status' => 'Active'
    ],
    [
        'id' => 2,
        'part_code' => 'CP-1002',
        'part_name' => 'Hinge Flange Plate',
        'rm_name' => 'HR Steel Plate 2.5mm',
        'grade_spec' => 'IS 2062 E250',
        'size_dimension' => '200 x 150 mm',
        'nos_per_kg' => '8.20',
        'req_qty' => '4',
        'uom' => 'NOS',
        'source' => 'Domestic',
        'status' => 'Active'
    ],
    [
        'id' => 3,
        'part_code' => 'CP-1003',
        'part_name' => 'Side Reinforcement Channel',
        'rm_name' => 'Aluminium Extrusion',
        'grade_spec' => '6063 T6',
        'size_dimension' => '50 x 25 x 3mm',
        'nos_per_kg' => '5.00',
        'req_qty' => '1',
        'uom' => 'NOS',
        'source' => 'Tata Steel Ltd.',
        'status' => 'Active'
    ],
    [
        'id' => 4,
        'part_code' => 'CP-1004',
        'part_name' => 'Shaft Spacer Bush',
        'rm_name' => 'Bright Bar Round',
        'grade_spec' => 'EN1A / 11SMn30',
        'size_dimension' => 'Dia 20mm x 15mm',
        'nos_per_kg' => '22.00',
        'req_qty' => '6',
        'uom' => 'NOS',
        'source' => 'Apex Fasteners',
        'status' => 'Active'
    ],
    [
        'id' => 5,
        'part_code' => 'CP-1005',
        'part_name' => 'Terminal Connection Lug',
        'rm_name' => 'Copper Strip',
        'grade_spec' => 'EC Grade Copper',
        'size_dimension' => '15 x 3mm x 45mm',
        'nos_per_kg' => '45.00',
        'req_qty' => '8',
        'uom' => 'NOS',
        'source' => 'Imported',
        'status' => 'Inactive'
    ]
];

$totalCount = count($childParts);
$activeCount = 0;
$inHouseCount = 0;

foreach ($childParts as $cp) {
    if (strcasecmp($cp['status'], 'Active') === 0) {
        $activeCount++;
    }
    if (stripos($cp['source'], 'in-house') !== false) {
        $inHouseCount++;
    }
}

$pageTitle = 'Child Part Master';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Child Part Master - WIP Management Portal</title>
  
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

    .cp-form-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .cp-form-group label {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
    }

    .cp-form-group .form-control {
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

    .cp-form-group .form-control:focus {
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
            <div class="stat-number" id="statTotalCp"><?php echo $totalCount; ?></div>
            <div class="stat-title">Total Child Parts</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number completed" id="statActiveCp"><?php echo $activeCount; ?></div>
            <div class="stat-title">Active Items</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number in-progress" id="statInHouseCp"><?php echo $inHouseCount; ?></div>
            <div class="stat-title">In-House Produced</div>
          </div>
        </div>

        <!-- ==========================================
             Simple Table Card
             ========================================== -->
        <div class="simple-card table-box">
          <div class="table-bar">
            <h2 class="box-title">Child Part Directory</h2>
            <div class="table-actions">
              <input type="text" id="cpSearch" class="simple-input" placeholder="Search Code, Name, RM, Grade, Size...">
              <button type="button" class="btn-primary" id="openAddCpModalBtn">+ Add Child Part</button>
            </div>
          </div>

          <div class="table-wrap">
            <table class="simple-table" id="cpTable">
              <thead>
                <tr>
                  <th style="width: 50px;">Sr No.</th>
                  <th>Child Part Code</th>
                  <th>Child Part Name</th>
                  <th>RM Name</th>
                  <th>Grade / Specification</th>
                  <th>Size / Dimension</th>
                  <th>Nos Per K.g</th>
                  <th>Req. Qty</th>
                  <th>UOM</th>
                  <th>Source</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="cpTableBody">
                <?php if (!empty($childParts)): ?>
                  <?php $sr = 1; ?>
                  <?php foreach ($childParts as $item): ?>
                    <?php 
                      $isActive = (strcasecmp($item['status'] ?? 'Active', 'Active') === 0);
                    ?>
                    <tr data-id="<?php echo htmlspecialchars($item['id']); ?>">
                      <td style="color: var(--text-sub); font-weight: 600;"><?php echo $sr++; ?></td>
                      <td><strong><?php echo htmlspecialchars($item['part_code']); ?></strong></td>
                      <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                      <td><?php echo htmlspecialchars($item['rm_name']); ?></td>
                      <td><?php echo htmlspecialchars($item['grade_spec'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($item['size_dimension'] ?: '-'); ?></td>
                      <td><?php echo htmlspecialchars($item['nos_per_kg'] ?: '-'); ?></td>
                      <td><strong><?php echo htmlspecialchars($item['req_qty'] ?: '1'); ?></strong></td>
                      <td><?php echo htmlspecialchars($item['uom']); ?></td>
                      <td><?php echo htmlspecialchars($item['source']); ?></td>
                      <td>
                        <?php if ($isActive): ?>
                          <span class="tag tag-completed">Active</span>
                        <?php else: ?>
                          <span class="tag" style="background:#f1f5f9; color:#64748b;">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="display: flex; gap: 6px;">
                          <button type="button" class="btn-edit" title="Edit Item">Edit</button>
                          <button type="button" class="btn-delete" title="Delete Item">Delete</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr id="emptyTableRow">
                    <td colspan="12" class="empty-row-msg">No child parts found. Click "+ Add Child Part" to create one.</td>
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
       Add / Edit Child Part Popup Modal
       ========================================== -->
  <div id="cpPopupModal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3 class="modal-title" id="modalFormTitle">+ Add Child Part</h3>
        <button type="button" class="modal-close-btn" id="closeModalBtn" aria-label="Close modal">&times;</button>
      </div>

      <form id="cpPopupForm">
        <input type="hidden" id="editItemId" value="">

        <div class="modal-body">
          <div class="popup-form-grid">
            
            <!-- 1. Child Part Code -->
            <div class="cp-form-group">
              <label for="inputPartCode">Child Part Code *</label>
              <input type="text" id="inputPartCode" class="form-control" placeholder="e.g., CP-1006" required>
            </div>

            <!-- 2. Child Part Name -->
            <div class="cp-form-group">
              <label for="inputPartName">Child Part Name *</label>
              <input type="text" id="inputPartName" class="form-control" placeholder="e.g., Mounting Flange Plate" required>
            </div>

            <!-- 3. RM Name -->
            <div class="cp-form-group">
              <label for="inputRmName">RM Name *</label>
              <input type="text" id="inputRmName" class="form-control" placeholder="e.g., CRCA Sheet 1.2mm" list="rmDataList" required>
              <datalist id="rmDataList">
                <?php if (!empty($rmList)): ?>
                  <?php foreach ($rmList as $r): ?>
                    <option value="<?php echo htmlspecialchars($r['rm_name']); ?>" 
                            data-grade="<?php echo htmlspecialchars($r['grade_spec']); ?>" 
                            data-size="<?php echo htmlspecialchars($r['size_dimension']); ?>" 
                            data-uom="<?php echo htmlspecialchars($r['uom']); ?>">
                      <?php echo htmlspecialchars($r['rm_name'] . ' (' . $r['rm_code'] . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <option value="CRCA Sheet 1.2mm">
                  <option value="HR Steel Plate 2.5mm">
                  <option value="Aluminium Extrusion 6063">
                  <option value="Bright Bar Round 20mm">
                  <option value="Copper Strip 15x3mm">
                <?php endif; ?>
              </datalist>
            </div>

            <!-- 4. Grade / Specification -->
            <div class="cp-form-group">
              <label for="inputGrade">Grade / Specification</label>
              <input type="text" id="inputGrade" class="form-control" placeholder="e.g., IS 513 CR2">
            </div>

            <!-- 5. Size / Dimension -->
            <div class="cp-form-group">
              <label for="inputSize">Size / Dimension</label>
              <input type="text" id="inputSize" class="form-control" placeholder="e.g., 120 x 85 mm">
            </div>

            <!-- 6. Nos Per K.g -->
            <div class="cp-form-group">
              <label for="inputNosPerKg">Nos Per K.g</label>
              <input type="number" step="any" min="0" id="inputNosPerKg" class="form-control" placeholder="e.g., 14.50">
            </div>

            <!-- 7. Req. Qty -->
            <div class="cp-form-group">
              <label for="inputReqQty">Req. Qty *</label>
              <input type="number" step="any" min="0" id="inputReqQty" class="form-control" placeholder="e.g., 2" required>
            </div>

            <!-- 8. UOM -->
            <div class="cp-form-group">
              <label for="inputUom">UOM *</label>
              <select id="inputUom" class="form-control" required>
                <option value="">-- Select UOM --</option>
                <option value="NOS">NOS</option>
                <option value="SET">SET</option>
                <option value="PCS">PCS</option>
                <option value="KG">KG</option>
                <option value="MTR">MTR</option>
                <option value="SQM">SQM</option>
                <option value="TON">TON</option>
                <option value="LTR">LTR</option>
              </select>
            </div>

            <!-- 9. Source -->
            <div class="cp-form-group" style="grid-column: 1 / -1;">
              <label for="inputSource">Source *</label>
              <select id="inputSource" class="form-control" required>
                <option value="">-- Select Source --</option>
                <optgroup label="General Sources">
                  <option value="In-house">In-house</option>
                  <option value="Domestic">Domestic</option>
                  <option value="Imported">Imported</option>
                </optgroup>
                <?php if (!empty($vendorList)): ?>
                  <optgroup label="Registered Vendors">
                    <?php foreach ($vendorList as $v): ?>
                      <option value="<?php echo htmlspecialchars($v['vendor_name']); ?>">
                        <?php echo htmlspecialchars($v['vendor_name']); ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
            </div>

            <!-- 10. Hidden Status (Default: Active) -->
            <input type="hidden" id="inputStatus" value="Active">

          </div>
        </div>

        <!-- Action Buttons -->
        <div class="modal-footer">
          <button type="button" class="btn-secondary" id="cancelModalBtn">Cancel</button>
          <button type="submit" class="btn-primary" id="saveCpSubmitBtn">+ Save Child Part</button>
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
      <p class="confirm-desc">Are you sure you want to delete child part <strong id="deleteTargetCode" style="color:var(--text-main);"></strong>? This item will be removed from the list.</p>
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
      const cpTableBody = document.getElementById('cpTableBody');
      const cpSearch = document.getElementById('cpSearch');
      const cpPopupModal = document.getElementById('cpPopupModal');
      const cpPopupForm = document.getElementById('cpPopupForm');
      const openAddCpModalBtn = document.getElementById('openAddCpModalBtn');
      const closeModalBtn = document.getElementById('closeModalBtn');
      const cancelModalBtn = document.getElementById('cancelModalBtn');
      const modalFormTitle = document.getElementById('modalFormTitle');
      const saveCpSubmitBtn = document.getElementById('saveCpSubmitBtn');
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
      const statTotalCp = document.getElementById('statTotalCp');
      const statActiveCp = document.getElementById('statActiveCp');
      const statInHouseCp = document.getElementById('statInHouseCp');

      // RM Input Autocomplete helpers
      const inputRmName = document.getElementById('inputRmName');
      const rmDataList = document.getElementById('rmDataList');
      if (inputRmName && rmDataList) {
        inputRmName.addEventListener('change', () => {
          const val = inputRmName.value.trim();
          const option = Array.from(rmDataList.options).find(opt => opt.value.toLowerCase() === val.toLowerCase());
          if (option) {
            const grade = option.getAttribute('data-grade');
            const size = option.getAttribute('data-size');
            const uom = option.getAttribute('data-uom');

            if (grade && !document.getElementById('inputGrade').value) {
              document.getElementById('inputGrade').value = grade;
            }
            if (size && !document.getElementById('inputSize').value) {
              document.getElementById('inputSize').value = size;
            }
            if (uom && !document.getElementById('inputUom').value) {
              document.getElementById('inputUom').value = uom;
            }
          }
        });
      }

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
        const rows = Array.from(cpTableBody.querySelectorAll('tr')).filter(r => r.id !== 'emptyTableRow');
        rows.forEach((r, idx) => {
          if (r.children[0]) {
            r.children[0].textContent = idx + 1;
          }
        });
      }

      // Update Statistics Counters
      function updateCounters() {
        const rows = Array.from(cpTableBody.querySelectorAll('tr')).filter(r => r.id !== 'emptyTableRow');
        let total = rows.length;
        let active = 0;
        let inHouse = 0;

        rows.forEach(r => {
          const cells = r.querySelectorAll('td');
          if (cells.length >= 11) {
            const sourceText = cells[9].textContent.trim();
            const statusText = cells[10].textContent.trim();
            if (statusText.toLowerCase().includes('active')) active++;
            if (sourceText.toLowerCase().includes('in-house')) inHouse++;
          }
        });

        if (statTotalCp) statTotalCp.textContent = total;
        if (statActiveCp) statActiveCp.textContent = active;
        if (statInHouseCp) statInHouseCp.textContent = inHouse;

        const emptyRow = document.getElementById('emptyTableRow');
        if (total === 0) {
          if (!emptyRow) {
            const tr = document.createElement('tr');
            tr.id = 'emptyTableRow';
            tr.innerHTML = `<td colspan="12" class="empty-row-msg">No child parts found. Click "+ Add Child Part" to create one.</td>`;
            cpTableBody.appendChild(tr);
          }
        } else if (emptyRow) {
          emptyRow.remove();
        }
      }

      // Open Form Modal
      function openModal(title = '+ Add Child Part', buttonText = '+ Save Child Part') {
        modalFormTitle.textContent = title;
        saveCpSubmitBtn.textContent = buttonText;
        cpPopupModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
          document.getElementById('inputPartCode').focus();
        }, 100);
      }

      // Close Form Modal
      function closeModal() {
        cpPopupModal.classList.remove('active');
        document.body.style.overflow = '';
        cpPopupForm.reset();
        editItemId.value = '';
        document.getElementById('inputStatus').value = 'Active';
      }

      // Open Add Modal
      if (openAddCpModalBtn) {
        openAddCpModalBtn.addEventListener('click', () => {
          cpPopupForm.reset();
          editItemId.value = '';
          document.getElementById('inputStatus').value = 'Active';
          openModal('+ Add Child Part', '+ Save Child Part');
        });
      }

      if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
      if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

      // Close when clicking outside modal
      if (cpPopupModal) {
        cpPopupModal.addEventListener('click', (e) => {
          if (e.target === cpPopupModal) closeModal();
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
          if (cpPopupModal && cpPopupModal.classList.contains('active')) closeModal();
          if (deleteConfirmModal && deleteConfirmModal.classList.contains('active')) closeDeleteConfirmModal();
        }
      });

      // Real-time table search filter
      if (cpSearch && cpTableBody) {
        cpSearch.addEventListener('input', () => {
          const q = cpSearch.value.toLowerCase().trim();
          const rows = cpTableBody.querySelectorAll('tr');
          rows.forEach(row => {
            if (row.id === 'emptyTableRow') return;
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
          });
        });
      }

      // Submit Form: Client-Side Interactive Handling (UI Only)
      if (cpPopupForm) {
        cpPopupForm.addEventListener('submit', (e) => {
          e.preventDefault();

          const id = editItemId.value ? parseInt(editItemId.value, 10) : 0;
          const code = document.getElementById('inputPartCode').value.trim();
          const name = document.getElementById('inputPartName').value.trim();
          const rmName = document.getElementById('inputRmName').value.trim();
          const grade = document.getElementById('inputGrade').value.trim() || '-';
          const size = document.getElementById('inputSize').value.trim() || '-';
          const nosPerKg = document.getElementById('inputNosPerKg').value.trim() || '-';
          const reqQty = document.getElementById('inputReqQty').value.trim() || '1';
          const uom = document.getElementById('inputUom').value;
          const source = document.getElementById('inputSource').value;
          const status = document.getElementById('inputStatus').value || 'Active';

          const activeTag = status === 'Active' 
            ? '<span class="tag tag-completed">Active</span>' 
            : '<span class="tag" style="background:#f1f5f9; color:#64748b;">Inactive</span>';

          const actionHtml = `
            <div style="display: flex; gap: 6px;">
              <button type="button" class="btn-edit" title="Edit Item">Edit</button>
              <button type="button" class="btn-delete" title="Delete Item">Delete</button>
            </div>
          `;

          if (id > 0) {
            // Update existing row
            const row = cpTableBody.querySelector(`tr[data-id="${id}"]`);
            if (row) {
              row.children[1].innerHTML = `<strong>${code}</strong>`;
              row.children[2].textContent = name;
              row.children[3].textContent = rmName;
              row.children[4].textContent = grade;
              row.children[5].textContent = size;
              row.children[6].textContent = nosPerKg;
              row.children[7].innerHTML = `<strong>${reqQty}</strong>`;
              row.children[8].textContent = uom;
              row.children[9].textContent = source;
              row.children[10].innerHTML = activeTag;
              row.children[11].innerHTML = actionHtml;
            }
            showToast(`Child Part "${code}" updated successfully.`, 'success');
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
              <td>${rmName}</td>
              <td>${grade}</td>
              <td>${size}</td>
              <td>${nosPerKg}</td>
              <td><strong>${reqQty}</strong></td>
              <td>${uom}</td>
              <td>${source}</td>
              <td>${activeTag}</td>
              <td>${actionHtml}</td>
            `;
            cpTableBody.prepend(tr);
            reindexSrNo();
            showToast(`New Child Part "${code}" added successfully.`, 'success');
          }

          closeModal();
          updateCounters();
        });
      }

      // Handle Edit and Delete click on Table rows
      if (cpTableBody) {
        cpTableBody.addEventListener('click', (e) => {
          const row = e.target.closest('tr');
          if (!row || row.id === 'emptyTableRow') return;

          const itemId = row.getAttribute('data-id');

          // Edit Action
          if (e.target.classList.contains('btn-edit')) {
            const cells = row.children;
            const code = cells[1].textContent.trim();
            const name = cells[2].textContent.trim();
            const rmName = cells[3].textContent.trim();
            const grade = cells[4].textContent.trim();
            const size = cells[5].textContent.trim();
            const nosPerKg = cells[6].textContent.trim();
            const reqQty = cells[7].textContent.trim();
            const uom = cells[8].textContent.trim();
            const source = cells[9].textContent.trim();
            const status = cells[10].textContent.trim().toLowerCase().includes('active') ? 'Active' : 'Inactive';

            document.getElementById('inputPartCode').value = code;
            document.getElementById('inputPartName').value = name;
            document.getElementById('inputRmName').value = rmName;
            document.getElementById('inputGrade').value = grade === '-' ? '' : grade;
            document.getElementById('inputSize').value = size === '-' ? '' : size;
            document.getElementById('inputNosPerKg').value = nosPerKg === '-' ? '' : nosPerKg;
            document.getElementById('inputReqQty').value = reqQty === '-' ? '' : reqQty;
            document.getElementById('inputUom').value = uom;
            
            // Set source
            const sourceSelect = document.getElementById('inputSource');
            let matchFound = Array.from(sourceSelect.options).some(o => o.value.toLowerCase() === source.toLowerCase());
            if (!matchFound && source && source !== '-') {
              sourceSelect.add(new Option(source, source, true, true));
            }
            sourceSelect.value = source;

            document.getElementById('inputStatus').value = status;

            editItemId.value = itemId;
            openModal(`Edit Child Part: ${code}`, 'Update Child Part');
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

          const code = pendingDeleteCode || 'Item';
          pendingDeleteRow.remove();
          closeDeleteConfirmModal();
          reindexSrNo();
          updateCounters();
          showToast(`Child Part "${code}" deleted successfully.`, 'success');
        });
      }

    });
  </script>
</body>
</html>
