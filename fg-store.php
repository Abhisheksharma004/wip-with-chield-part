<?php
/**
 * WIP Management Portal - Finished Goods Store (FG Store)
 * 3 Tabs Architecture: FG Stock Inventory, Inward Logs, Dispatch Logs
 */
require_once __DIR__ . '/auth/check_auth.php';
require_once __DIR__ . '/config/db.php';

// Format stock cleanly
function formatCleanStock($val) {
    $f = floatval($val ?? 0);
    if ($f == (int)$f) {
        return number_format($f, 0);
    }
    return rtrim(rtrim(number_format($f, 3), '0'), '.');
}

// Format date to DD-MM-YYYY
function formatDateDMY($dStr) {
    if (empty($dStr)) return '';
    $clean = explode('T', (string)$dStr)[0];
    $ts = strtotime($clean);
    return $ts ? date('d-m-Y', $ts) : $dStr;
}

$pdo = getDBConnection();

// Live AJAX API handler for scanning / searching MIP directly from production_entry table
if (isset($_GET['action']) && $_GET['action'] === 'search_mip') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    if (!$pdo || empty($q)) {
        echo json_encode(['success' => false, 'message' => 'Empty query']);
        exit;
    }
    try {
        $cleanQ = preg_replace('/[^a-zA-Z0-9]/', '', $q);
        // STRICT EXACT MATCH ONLY on mip_no: no LIKE search, no work_order
        $stmtSearch = $pdo->prepare("
            SELECT TOP 1 id, mip_no, work_order, part_code, part_name,
                   department, received_by, process_name, shift,
                   target_qty, ok_qty, rework_qty, rejected_qty,
                   operator_name, uom, status,
                   CONVERT(VARCHAR(10), created_at, 120) as production_date,
                   CONVERT(VARCHAR(19), created_at, 120) as created_at
            FROM production_entry
            WHERE LOWER(RTRIM(LTRIM(mip_no))) = LOWER(?)
               OR REPLACE(REPLACE(LOWER(mip_no), '-', ''), ' ', '') = LOWER(?)
            ORDER BY id DESC
        ");
        $stmtSearch->execute([$q, $cleanQ]);
        $row = $stmtSearch->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['mip_no'])) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No record found for MIP: ' . $q]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $e->getMessage()]);
    }
    exit;
}

// Auto-verify and create FG Store tables if not yet created
if ($pdo) {
    try {
        $pdo->exec("
            IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='fg_inventory' AND xtype='U')
            BEGIN
                CREATE TABLE fg_inventory (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    part_code NVARCHAR(50) NOT NULL,
                    part_name NVARCHAR(150) NOT NULL,
                    total_ok_qty DECIMAL(18, 3) NOT NULL DEFAULT 0.000,
                    uom NVARCHAR(20) NOT NULL DEFAULT 'PCS',
                    rack NVARCHAR(50) NOT NULL DEFAULT 'RACK-A1',
                    bin NVARCHAR(50) NOT NULL DEFAULT 'BIN-01',
                    last_mip_no NVARCHAR(50) NULL,
                    last_production_date DATE NULL,
                    created_at DATETIME DEFAULT GETDATE(),
                    updated_at DATETIME DEFAULT GETDATE(),
                    CONSTRAINT UQ_fg_inv_part_rack_bin UNIQUE (part_code, rack, bin)
                );
            END

            IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='fg_inward_logs' AND xtype='U')
            BEGIN
                CREATE TABLE fg_inward_logs (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    inward_no NVARCHAR(50) NOT NULL,
                    mip_no NVARCHAR(50) NOT NULL,
                    production_date DATE NULL,
                    part_code NVARCHAR(50) NOT NULL,
                    part_name NVARCHAR(150) NOT NULL,
                    ok_qty DECIMAL(18, 3) NOT NULL,
                    uom NVARCHAR(20) NOT NULL DEFAULT 'PCS',
                    rack NVARCHAR(50) NULL DEFAULT 'RACK-A1',
                    bin NVARCHAR(50) NULL DEFAULT 'BIN-01',
                    qc_status NVARCHAR(50) DEFAULT 'QC Passed',
                    work_order NVARCHAR(100) NULL,
                    received_by NVARCHAR(150) NULL,
                    remarks NVARCHAR(500) NULL,
                    created_at DATETIME DEFAULT GETDATE()
                );
            END
        ");
    } catch (PDOException $e) {
        // Table check fallback
    }
}

// Live AJAX API handler for Saving Inward Finished Goods
if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save_inward') ||
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_inward')) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $postData = json_decode($rawInput, true);
    if (!is_array($postData) || empty($postData)) {
        $postData = $_POST;
    }

    $inwardNo = trim($postData['inward_no'] ?? '');
    $mipNo = trim($postData['mip_no'] ?? '');
    $partCode = trim($postData['part_code'] ?? '');
    $partName = trim($postData['part_name'] ?? '');
    $okQty = floatval($postData['inward_qty'] ?? $postData['ok_qty'] ?? 0);
    $uom = trim($postData['uom'] ?? 'PCS');
    $rack = trim($postData['rack'] ?? 'RACK-A1');
    $bin = trim($postData['bin'] ?? 'BIN-01');
    $prodDate = trim($postData['inward_date'] ?? $postData['production_date'] ?? date('Y-m-d'));
    $workOrder = trim($postData['work_order'] ?? '');
    $receivedBy = trim($postData['received_by'] ?? '');
    $remarks = trim($postData['remarks'] ?? '');

    if (empty($inwardNo)) {
        $inwardNo = 'FGI-' . date('Y') . '-' . rand(100, 999);
    }
    if (empty($partCode)) {
        echo json_encode(['success' => false, 'message' => 'Part code is required.']);
        exit;
    }
    if ($okQty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ok quantity must be greater than zero.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert into fg_inward_logs (individual inward transaction log)
        $stmtLog = $pdo->prepare("
            INSERT INTO fg_inward_logs 
                (inward_no, mip_no, production_date, part_code, part_name, ok_qty, uom, rack, bin, qc_status, work_order, received_by, remarks, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, 'QC Passed', ?, ?, ?, GETDATE())
        ");
        $stmtLog->execute([
            $inwardNo,
            $mipNo,
            !empty($prodDate) ? $prodDate : null,
            $partCode,
            $partName,
            $okQty,
            $uom,
            $rack,
            $bin,
            $workOrder,
            $receivedBy,
            $remarks
        ]);

        // 2. Check if this part already exists in THIS specific rack & bin
        $stmtCheck = $pdo->prepare("SELECT id, total_ok_qty FROM fg_inventory WHERE part_code = ? AND rack = ? AND bin = ?");
        $stmtCheck->execute([$partCode, $rack, $bin]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Part exists in this specific location: accumulate quantity
            $stmtUpd = $pdo->prepare("
                UPDATE fg_inventory 
                SET total_ok_qty = total_ok_qty + ?,
                    part_name = ?,
                    uom = ?,
                    last_mip_no = ?,
                    last_production_date = ?,
                    updated_at = GETDATE()
                WHERE id = ?
            ");
            $stmtUpd->execute([
                $okQty,
                $partName,
                $uom,
                $mipNo,
                !empty($prodDate) ? $prodDate : null,
                $existing['id']
            ]);
            $locationTotal = floatval($existing['total_ok_qty']) + $okQty;
        } else {
            // New rack/bin for this part: insert new location row
            $stmtIns = $pdo->prepare("
                INSERT INTO fg_inventory 
                    (part_code, part_name, total_ok_qty, uom, rack, bin, last_mip_no, last_production_date, created_at, updated_at)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())
            ");
            $stmtIns->execute([
                $partCode,
                $partName,
                $okQty,
                $uom,
                $rack,
                $bin,
                $mipNo,
                !empty($prodDate) ? $prodDate : null
            ]);
            $locationTotal = $okQty;
        }

        // Calculate consolidated total stock across all locations for this part
        $stmtSum = $pdo->prepare("SELECT SUM(total_ok_qty) as overall_total FROM fg_inventory WHERE part_code = ?");
        $stmtSum->execute([$partCode]);
        $sumRow = $stmtSum->fetch(PDO::FETCH_ASSOC);
        $updatedTotal = floatval($sumRow['overall_total'] ?? $locationTotal);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Finished goods inward saved successfully!',
            'data' => [
                'part_code' => $partCode,
                'part_name' => $partName,
                'added_qty' => $okQty,
                'total_qty' => $updatedTotal,
                'uom' => $uom,
                'rack' => $rack,
                'bin' => $bin,
                'inward_no' => $inwardNo,
                'mip_no' => $mipNo
            ]
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$activeParts = [];
$productionList = [];

if ($pdo) {
    // 1. Fetch active parts from part_master
    try {
        $stmtParts = $pdo->query("SELECT id, part_code, part_name FROM part_master WHERE status = 'Active' ORDER BY part_code ASC");
        if ($stmtParts) {
            $activeParts = $stmtParts->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Fallback gracefully
    }

    // 2. Fetch production records directly from production_entry table
    try {
        $stmtPrd = $pdo->query("
            SELECT id, mip_no, work_order, part_code, part_name,
                   department, received_by, process_name, shift,
                   target_qty, ok_qty, rework_qty, rejected_qty,
                   operator_name, uom, status,
                   CONVERT(VARCHAR(10), created_at, 120) as production_date,
                   CONVERT(VARCHAR(19), created_at, 120) as created_at
            FROM production_entry
            ORDER BY id DESC
        ");
        if ($stmtPrd) {
            $productionList = $stmtPrd->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Fallback gracefully
    }
}

// Fallback active parts if database is empty
if (empty($activeParts)) {
    $activeParts = [
        ['part_code' => 'P1001', 'part_name' => 'Front Mounting Assembly', 'uom' => 'PCS'],
        ['part_code' => 'P1002', 'part_name' => 'Main Chassis Sub-Assembly', 'uom' => 'PCS'],
        ['part_code' => 'P1003', 'part_name' => 'Support Bracket Assembly', 'uom' => 'PCS'],
        ['part_code' => 'P1004', 'part_name' => 'Retainer Frame Assembly', 'uom' => 'PCS'],
        ['part_code' => 'P1005', 'part_name' => 'Heavy Duty Base Plate', 'uom' => 'PCS'],
    ];
}

// Fallback sample production records only if database connection is not available
if (!$pdo && empty($productionList)) {
    $productionList = [
        [
            'id' => 1,
            'mip_no' => 'MIP-001',
            'work_order' => 'WO-9841',
            'part_code' => 'P1001',
            'part_name' => 'Front Mounting Assembly',
            'ok_qty' => 350,
            'uom' => 'PCS',
            'department' => 'Machining Shop',
            'operator_name' => 'Rajesh Sharma',
            'production_date' => date('Y-m-d', strtotime('-4 days')),
            'status' => 'Completed'
        ],
        [
            'id' => 2,
            'mip_no' => 'MIP-002',
            'work_order' => 'WO-9842',
            'part_code' => 'P1002',
            'part_name' => 'Main Chassis Sub-Assembly',
            'ok_qty' => 200,
            'uom' => 'PCS',
            'department' => 'Fabrication Floor',
            'operator_name' => 'Amit Kumar',
            'production_date' => date('Y-m-d', strtotime('-2 days')),
            'status' => 'Completed'
        ],
        [
            'id' => 3,
            'mip_no' => 'MIP-003',
            'work_order' => 'WO-9845',
            'part_code' => 'P1003',
            'part_name' => 'Support Bracket Assembly',
            'ok_qty' => 450,
            'uom' => 'PCS',
            'department' => 'Welding Bay 2',
            'operator_name' => 'Sunil Verma',
            'production_date' => date('Y-m-d', strtotime('-1 days')),
            'status' => 'Completed'
        ],
        [
            'id' => 4,
            'mip_no' => 'MIP-004',
            'work_order' => 'WO-9849',
            'part_code' => 'P1004',
            'part_name' => 'Retainer Frame Assembly',
            'ok_qty' => 180,
            'uom' => 'PCS',
            'department' => 'Assembly Line 1',
            'operator_name' => 'Rajesh Sharma',
            'production_date' => date('Y-m-d'),
            'status' => 'Completed'
        ],
        [
            'id' => 5,
            'mip_no' => 'MIP-005',
            'work_order' => 'WO-9850',
            'part_code' => 'P1005',
            'part_name' => 'Heavy Duty Base Plate',
            'ok_qty' => 220,
            'uom' => 'PCS',
            'department' => 'Press Shop',
            'operator_name' => 'Vikas Singh',
            'production_date' => date('Y-m-d'),
            'status' => 'Completed'
        ]
    ];
}

// Fetch real FG Inventory (Tab 1: Part-wise accumulated stock) and Inward Logs (Tab 2)
$fgInventoryList = [];
$fgInwardLogsList = [];

if ($pdo) {
    try {
        $stmtInv = $pdo->query("
            SELECT id, part_code, part_name, total_ok_qty, uom, rack, bin, 
                   last_mip_no,
                   CONVERT(VARCHAR(10), last_production_date, 120) as production_date,
                   CONVERT(VARCHAR(19), updated_at, 120) as updated_at
            FROM fg_inventory
            ORDER BY part_code ASC, rack ASC, bin ASC
        ");
        $rawInvRows = $stmtInv ? $stmtInv->fetchAll(PDO::FETCH_ASSOC) : [];

        $groupedParts = [];
        foreach ($rawInvRows as $r) {
            $pCode = $r['part_code'];
            if (!isset($groupedParts[$pCode])) {
                $groupedParts[$pCode] = [
                    'id' => $r['id'],
                    'part_code' => $pCode,
                    'part_name' => $r['part_name'],
                    'total_ok_qty' => 0,
                    'uom' => $r['uom'] ?? 'PCS',
                    'last_mip_no' => $r['last_mip_no'] ?? '',
                    'production_date' => $r['production_date'] ?? '',
                    'locations' => []
                ];
            }
            $groupedParts[$pCode]['total_ok_qty'] += floatval($r['total_ok_qty']);
            $groupedParts[$pCode]['locations'][] = [
                'rack' => $r['rack'] ?? 'RACK-A1',
                'bin' => $r['bin'] ?? 'BIN-01',
                'qty' => floatval($r['total_ok_qty']),
                'uom' => $r['uom'] ?? 'PCS',
                'last_mip' => $r['last_mip_no'] ?? '',
                'date' => $r['production_date'] ?? ''
            ];
        }
        $fgInventoryList = array_values($groupedParts);
    } catch (PDOException $e) {
        // Fallback
    }

    try {
        $stmtLogs = $pdo->query("
            SELECT id, inward_no, mip_no,
                   CONVERT(VARCHAR(10), production_date, 120) as production_date,
                   part_code, part_name, ok_qty, uom, rack, bin, qc_status,
                   work_order, received_by, remarks,
                   CONVERT(VARCHAR(19), created_at, 120) as created_at
            FROM fg_inward_logs
            ORDER BY id DESC
        ");
        if ($stmtLogs) {
            $fgInwardLogsList = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Fallback
    }
}

// Sample Dispatch logs data
$dispatchSampleList = [
    [
        'id' => 101,
        'dispatch_no' => 'DSP-2026-081',
        'dispatch_date' => date('Y-m-d', strtotime('-1 days')),
        'invoice_ref' => 'INV-2026-8801 / DC-102',
        'part_code' => 'P1001',
        'part_name' => 'Front Mounting Assembly',
        'batch_no' => 'LOT-2026-03-A',
        'dispatch_qty' => 80,
        'uom' => 'PCS',
        'dispatched_by' => 'Amit Sharma',
        'customer' => 'Apex Motors Ltd.',
        'status' => 'Dispatched'
    ],
    [
        'id' => 102,
        'dispatch_no' => 'DSP-2026-082',
        'dispatch_date' => date('Y-m-d', strtotime('-2 days')),
        'invoice_ref' => 'INV-2026-8795 / DC-099',
        'part_code' => 'P1002',
        'part_name' => 'Main Chassis Sub-Assembly',
        'batch_no' => 'LOT-2026-03-B',
        'dispatch_qty' => 170,
        'uom' => 'PCS',
        'dispatched_by' => 'Rajesh Store',
        'customer' => 'Bharat Auto Components',
        'status' => 'Dispatched'
    ],
    [
        'id' => 103,
        'dispatch_no' => 'DSP-2026-083',
        'dispatch_date' => date('Y-m-d', strtotime('-3 days')),
        'invoice_ref' => 'INV-2026-8780 / DC-095',
        'part_code' => 'P1003',
        'part_name' => 'Support Bracket Assembly',
        'batch_no' => 'LOT-2026-03-C',
        'dispatch_qty' => 50,
        'uom' => 'PCS',
        'dispatched_by' => 'Vikas Singh',
        'customer' => 'Global Engineering Works',
        'status' => 'Dispatched'
    ],
    [
        'id' => 104,
        'dispatch_no' => 'DSP-2026-084',
        'dispatch_date' => date('Y-m-d', strtotime('-6 days')),
        'invoice_ref' => 'INV-2026-8742 / DC-088',
        'part_code' => 'P1005',
        'part_name' => 'Heavy Duty Base Plate',
        'batch_no' => 'LOT-2026-02-X',
        'dispatch_qty' => 150,
        'uom' => 'PCS',
        'dispatched_by' => 'Vikas Singh',
        'customer' => 'Dynamic Heavy Equipments',
        'status' => 'Dispatched'
    ]
];

// Calculated stats based on real database records
$totalStock = 0;
$todayInward = 0;
$totalDispatched = 0;
$todayStr = date('Y-m-d');

foreach ($fgInventoryList as $item) {
    $totalStock += floatval($item['total_ok_qty'] ?? 0);
}

foreach ($fgInwardLogsList as $item) {
    $inDate = $item['production_date'] ?? '';
    if (empty($inDate) && !empty($item['created_at'])) {
        $inDate = substr($item['created_at'], 0, 10);
    }
    if ($inDate === $todayStr) {
        $todayInward += floatval($item['ok_qty'] ?? 0);
    }
}

foreach ($dispatchSampleList as $item) {
    $totalDispatched += floatval($item['dispatch_qty'] ?? 0);
}

$pageTitle = 'Finished Goods Store (FG Store)';
$activeMenu = 'fg-store.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finished Goods Store (FG Store) - WIP Management Portal</title>
  
  <!-- Modern Clean Google Font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="css/dashboard.css">

  <style>
    /* Clean, Simple UI Overrides matching MIP portal */
    .stat-number.dispatched {
      color: #6366f1;
    }
    
    /* Sub metadata below main text (Prevents text merging) */
    .sub-meta {
      display: block;
      font-size: 0.74rem;
      color: var(--text-sub);
      margin-top: 3px;
      font-weight: 500;
      line-height: 1.25;
      white-space: nowrap;
    }

    /* Batch / Lot Code */
    .batch-lot-code {
      white-space: nowrap;
      font-weight: 600;
      color: #334155;
      font-family: inherit;
      letter-spacing: -0.01em;
    }

    /* Quantity Badges */
    .qty-badge {
      display: inline-block;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 5px;
      font-variant-numeric: tabular-nums;
      font-size: 0.82rem;
      min-width: 48px;
      text-align: center;
      box-sizing: border-box;
      line-height: 1.3;
    }
    .qty-badge.neutral {
      background: #f1f5f9;
      color: #334155;
      border: 1px solid #e2e8f0;
    }
    .qty-badge.in-stock {
      background: #ecfdf5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }
    .qty-badge.low-stock {
      background: #fffbeb;
      color: #b45309;
      border: 1px solid #fde68a;
    }
    .qty-badge.zero-stock {
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    /* Status Tags */
    .tag-available {
      background: #ecfdf5;
      color: #059669;
    }
    .tag-low {
      background: #fffbeb;
      color: #d97706;
    }
    .tag-dispatched {
      background: #eff6ff;
      color: #2563eb;
    }
    .tag-passed {
      background: #ecfdf5;
      color: #047857;
      font-weight: 700;
    }

    /* Action Buttons in Table */
    .action-btns {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      flex-wrap: nowrap;
    }
    .btn-view, .btn-dispatch, .btn-print, .btn-delete {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 4px 9px;
      border-radius: 4px;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;
      line-height: 1.2;
      white-space: nowrap;
      border: 1px solid var(--border);
      text-decoration: none;
    }
    .btn-view {
      background: #ffffff;
      color: #0284c7;
      border-color: #bae6fd;
    }
    .btn-view:hover {
      background: #f0f9ff;
      border-color: #0284c7;
    }
    .btn-dispatch {
      background: #eef2ff;
      color: #4f46e5;
      border-color: #c7d2fe;
    }
    .btn-dispatch:hover {
      background: #e0e7ff;
      border-color: #4f46e5;
    }
    .btn-print {
      background: #ffffff;
      color: #059669;
      border-color: #a7f3d0;
    }
    .btn-print:hover {
      background: #ecfdf5;
      border-color: #059669;
    }
    .btn-delete {
      background: #ffffff;
      color: #ef4444;
      border-color: #fecaca;
    }
    .btn-delete:hover {
      background: #fef2f2;
      border-color: #ef4444;
    }

    /* Table alignment & spacing enhancements */
    .simple-table th {
      vertical-align: middle;
      padding: 11px 12px;
      font-size: 0.76rem;
      letter-spacing: 0.03em;
    }
    .simple-table td {
      vertical-align: middle;
      padding: 10px 12px;
      font-size: 0.86rem;
    }

    /* 3 Clean Tabs Navigation */
    .tabs-nav {
      display: flex;
      gap: 10px;
      border-bottom: 2px solid #e2e8f0;
      margin-bottom: 18px;
    }
    .tab-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 20px;
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--text-sub);
      background: transparent;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      cursor: pointer;
      transition: all 0.15s ease;
      font-family: inherit;
    }
    .tab-btn:hover {
      color: var(--primary);
      background: #f8fafc;
      border-radius: 6px 6px 0 0;
    }
    .tab-btn.active {
      color: var(--primary);
      border-bottom: 2px solid var(--primary);
      background: transparent;
    }
    .tab-count {
      background: #f1f5f9;
      color: #475569;
      font-size: 0.74rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 12px;
    }
    .tab-btn.active .tab-count {
      background: var(--primary-light);
      color: var(--primary);
    }
    .tab-pane {
      display: none;
    }
    .tab-pane.active {
      display: block;
    }

    /* Modal Form Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(3px);
      z-index: 100;
      align-items: center;
      justify-content: center;
      padding: 16px;
      box-sizing: border-box;
    }
    .modal-overlay.active {
      display: flex;
    }
    .modal-card {
      background: #ffffff;
      border-radius: 12px;
      width: 100%;
      max-width: 680px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      border: 1px solid var(--border);
      animation: modalPopIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes modalPopIn {
      from { opacity: 0; transform: scale(0.96) translateY(8px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal-header {
      padding: 16px 22px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f8fafc;
    }
    .modal-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-main);
      letter-spacing: -0.01em;
    }
    .modal-close-btn {
      background: transparent;
      border: none;
      font-size: 1.6rem;
      line-height: 1;
      color: var(--text-sub);
      cursor: pointer;
      padding: 2px 6px;
      border-radius: 4px;
      transition: all 0.15s ease;
    }
    .modal-close-btn:hover {
      color: var(--text-main);
      background: #e2e8f0;
    }
    .modal-body {
      padding: 22px;
      max-height: calc(85vh - 130px);
      overflow-y: auto;
    }
    .popup-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    @media (max-width: 580px) {
      .popup-form-grid {
        grid-template-columns: 1fr;
      }
    }
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .form-group.col-full {
      grid-column: 1 / -1;
    }
    .form-group label {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-main);
    }
    .form-group .form-control {
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
    .form-group .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .form-group .form-control[readonly] {
      background: #f8fafc;
      color: var(--text-sub);
      cursor: not-allowed;
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
      padding: 8px 16px;
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.86rem;
      font-weight: 600;
      color: var(--text-sub);
      cursor: pointer;
      transition: all 0.15s ease;
    }
    .btn-secondary:hover {
      background: #f1f5f9;
      color: var(--text-main);
    }

    /* Print Slip Styles */
    @media print {
      body * {
        visibility: hidden !important;
      }
      #printArea, #printArea * {
        visibility: visible !important;
      }
      #printArea {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        background: #fff;
        padding: 20px;
        box-sizing: border-box;
      }
    }
    .print-box {
      display: none;
    }
    @media print {
      .print-box {
        display: block !important;
      }
    }

    /* Toast Notification System */
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    }
    .toast {
      min-width: 290px;
      max-width: 380px;
      padding: 13px 16px;
      border-radius: 8px;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 0.88rem;
      color: var(--text-main);
      pointer-events: auto;
      opacity: 0;
      transform: translateX(40px);
      transition: all 0.28s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .toast.show {
      opacity: 1;
      transform: translateX(0);
    }
    .toast.toast-success {
      border-left: 4px solid #10b981;
      color: #065f46;
    }
    .toast.toast-error {
      border-left: 4px solid #ef4444;
      color: #991b1b;
    }
    .toast.toast-info {
      border-left: 4px solid #2563eb;
      color: #1e40af;
    }
    .toast-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .toast-message {
      flex: 1;
      line-height: 1.4;
      font-weight: 500;
    }
  </style>
</head>
<body>

  <div class="layout-wrapper">
    
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
      
      <!-- Top Header Navbar -->
      <?php include __DIR__ . '/includes/header.php'; ?>

      <!-- Content Body -->
      <div class="content-body">
        
        <!-- 3 Simple Stats Cards (Identical to MIP & Child Part Pages) -->
        <div class="stats-row">
          <div class="simple-card stat-box">
            <div class="stat-number completed" id="statTotalStock"><?php echo number_format($totalStock); ?></div>
            <div class="stat-title">Total FG Available Stock (Units)</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number in-progress" id="statTodayInward"><?php echo number_format($todayInward); ?></div>
            <div class="stat-title">Today's Inward Qty</div>
          </div>

          <div class="simple-card stat-box">
            <div class="stat-number dispatched" id="statTotalDispatched"><?php echo number_format($totalDispatched); ?></div>
            <div class="stat-title">Total Dispatched Qty</div>
          </div>
        </div>

        <!-- 3 Clean Tabs Navigation -->
        <div class="tabs-nav">
          <button type="button" class="tab-btn active" data-tab="tabInventory">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            <span>FG Stock Inventory</span>
            <span class="tab-count" id="countInventory"><?php echo count($fgInventoryList); ?></span>
          </button>

          <button type="button" class="tab-btn" data-tab="tabInwardLogs">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
            <span>Inward Logs</span>
            <span class="tab-count" id="countInward"><?php echo count($fgInwardLogsList); ?></span>
          </button>

          <button type="button" class="tab-btn" data-tab="tabDispatchLogs">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
            <span>Dispatch Logs</span>
            <span class="tab-count" id="countDispatch"><?php echo count($dispatchSampleList); ?></span>
          </button>
        </div>

        <!-- =========================================================
             TAB 1: FG Stock Inventory
             ========================================================= -->
        <div class="tab-pane active" id="tabInventory">
          <div class="simple-card table-box">
            
            <div class="table-bar">
              <h2 class="box-title">Finished Goods (FG) Inventory Stock</h2>
              <div class="table-actions">
                <input type="text" id="fgSearch" class="simple-input" placeholder="Search Part Code or Name..." style="width: 280px;">
              </div>
            </div>

            <!-- Table Container -->
            <div class="table-wrap">
              <table class="simple-table" id="fgTable">
                <thead>
                  <tr>
                    <th style="width: 60px; text-align: center;">Sr No.</th>
                    <th style="min-width: 280px;">Part Code &amp; Name</th>
                    <th style="width: 210px; text-align: center; white-space: nowrap;">Location</th>
                    <th style="width: 160px; text-align: center; white-space: nowrap;">Ok Qty</th>
                    <th style="width: 100px; text-align: center; white-space: nowrap;">Action</th>
                  </tr>
                </thead>
                <tbody id="fgTableBody">
                  <?php if (empty($fgInventoryList)): ?>
                    <tr id="emptyFgRow">
                      <td colspan="5" style="text-align: center; padding: 36px 20px; color: var(--text-sub);">
                        No finished goods inventory recorded yet. Go to <strong>Inward Logs</strong> tab and click <strong>+ Inward FG</strong> to add stock.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php $sr = 1; ?>
                    <?php foreach ($fgInventoryList as $item): ?>
                      <tr data-id="<?php echo htmlspecialchars($item['id']); ?>"
                          data-part_code="<?php echo htmlspecialchars($item['part_code']); ?>"
                          data-part_name="<?php echo htmlspecialchars($item['part_name']); ?>"
                          data-ok_qty="<?php echo htmlspecialchars($item['total_ok_qty']); ?>"
                          data-available_stock="<?php echo htmlspecialchars($item['total_ok_qty']); ?>"
                          data-uom="<?php echo htmlspecialchars($item['uom'] ?? 'PCS'); ?>"
                          data-locations="<?php echo htmlspecialchars(json_encode($item['locations'] ?? []), ENT_QUOTES, 'UTF-8'); ?>"
                          data-mip_no="<?php echo htmlspecialchars($item['last_mip_no'] ?? ''); ?>"
                          data-inward_no="<?php echo htmlspecialchars($item['last_mip_no'] ?? ''); ?>"
                          data-batch_no="<?php echo htmlspecialchars($item['last_mip_no'] ?? ''); ?>"
                          data-production_date="<?php echo htmlspecialchars($item['production_date'] ?? ''); ?>"
                          data-inward_date="<?php echo htmlspecialchars($item['production_date'] ?? ''); ?>"
                          data-status="Available">
                        
                        <td style="color: var(--text-sub); font-weight: 600; text-align: center;"><?php echo $sr++; ?></td>
                        
                        <td>
                          <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                            <?php echo htmlspecialchars($item['part_code']); ?> - <?php echo htmlspecialchars($item['part_name']); ?>
                          </div>
                        </td>

                        <td style="text-align: center; white-space: nowrap;">
                          <?php $locCount = count($item['locations'] ?? []); ?>
                          <?php if ($locCount > 1): ?>
                            <span style="font-weight: 600; color: #4338ca; font-size: 0.88rem;">
                              <?php echo $locCount; ?> Locations
                            </span>
                          <?php elseif ($locCount === 1): ?>
                            <span style="font-weight: 600; color: #334155; font-size: 0.88rem;">
                              <?php echo htmlspecialchars($item['locations'][0]['rack'] . ' / ' . $item['locations'][0]['bin']); ?>
                            </span>
                          <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.88rem;">-</span>
                          <?php endif; ?>
                        </td>
                        
                        <td style="text-align: center; white-space: nowrap;">
                          <?php 
                            $stockQty = floatval($item['total_ok_qty']);
                            $badgeCls = 'in-stock';
                            if ($stockQty <= 0) $badgeCls = 'zero-stock';
                            elseif ($stockQty <= 35) $badgeCls = 'low-stock';
                          ?>
                          <span class="qty-badge <?php echo $badgeCls; ?>"><?php echo formatCleanStock($stockQty); ?> <?php echo htmlspecialchars($item['uom'] ?? 'PCS'); ?></span>
                        </td>

                        <td style="text-align: center; white-space: nowrap;">
                          <div class="action-btns" style="justify-content: center;">
                            <button type="button" class="btn-view" title="View Storage Breakdown">View</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

        <!-- =========================================================
             TAB 2: Inward Logs
             ========================================================= -->
        <div class="tab-pane" id="tabInwardLogs">
          <div class="simple-card table-box">
            
            <div class="table-bar">
              <h2 class="box-title">Finished Goods Inward Receipts &amp; Logs</h2>
              <div class="table-actions">
                <input type="text" id="inwardSearch" class="simple-input" placeholder="Search MIP No, Part, Rack, Bin..." style="width: 280px;">
                <button type="button" class="btn-primary" id="openInwardModalBtn">+ Inward FG</button>
              </div>
            </div>

            <div class="table-wrap">
              <table class="simple-table" id="inwardTable">
                <thead>
                  <tr>
                    <th style="width: 50px; text-align: center;">Sr No.</th>
                    <th style="width: 130px; white-space: nowrap;">MIP No.</th>
                    <th style="min-width: 220px;">Part Code &amp; Name</th>
                    <th style="width: 120px; text-align: center; white-space: nowrap;">Ok Qty</th>
                    <th style="width: 140px; text-align: center; white-space: nowrap;">Production Date</th>
                    <th style="width: 120px; white-space: nowrap;">Rack</th>
                    <th style="width: 120px; white-space: nowrap;">Bin</th>
                    <th style="width: 110px; text-align: center; white-space: nowrap;">QC Status</th>
                    <th style="width: 130px; text-align: center; white-space: nowrap;">Action</th>
                  </tr>
                </thead>
                <tbody id="inwardTableBody">
                  <?php if (empty($fgInwardLogsList)): ?>
                    <tr id="emptyInwardRow">
                      <td colspan="9" style="text-align: center; padding: 36px 20px; color: var(--text-sub);">
                        No inward logs recorded yet. Click <strong>+ Inward FG</strong> above to inward finished goods.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php $srIn = 1; ?>
                    <?php foreach ($fgInwardLogsList as $item): ?>
                      <tr data-inward_no="<?php echo htmlspecialchars($item['inward_no']); ?>"
                          data-mip_no="<?php echo htmlspecialchars($item['mip_no']); ?>"
                          data-part_code="<?php echo htmlspecialchars($item['part_code']); ?>"
                          data-part_name="<?php echo htmlspecialchars($item['part_name']); ?>"
                          data-batch_no="<?php echo htmlspecialchars($item['mip_no']); ?>"
                          data-inward_qty="<?php echo htmlspecialchars($item['ok_qty']); ?>"
                          data-ok_qty="<?php echo htmlspecialchars($item['ok_qty']); ?>"
                          data-uom="<?php echo htmlspecialchars($item['uom']); ?>"
                          data-rack="<?php echo htmlspecialchars($item['rack'] ?? 'RACK-A1'); ?>"
                          data-bin="<?php echo htmlspecialchars($item['bin'] ?? 'BIN-01'); ?>"
                          data-location="<?php echo htmlspecialchars(($item['rack'] ?? 'RACK-A1') . ' / ' . ($item['bin'] ?? 'BIN-01')); ?>"
                          data-work_order="<?php echo htmlspecialchars($item['work_order'] ?? ''); ?>"
                          data-received_by="<?php echo htmlspecialchars($item['received_by'] ?? ''); ?>"
                          data-remarks="<?php echo htmlspecialchars($item['remarks'] ?? ''); ?>">
                        <td style="color: var(--text-sub); font-weight: 600; text-align: center;"><?php echo $srIn++; ?></td>
                        <td style="white-space: nowrap;">
                          <strong style="color: var(--text-main); font-size: 0.95rem;"><?php echo htmlspecialchars($item['mip_no']); ?></strong>
                        </td>
                        <td>
                          <div style="font-weight: 700; color: var(--text-main);">
                            <?php echo htmlspecialchars($item['part_code']); ?> - <?php echo htmlspecialchars($item['part_name']); ?>
                          </div>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                          <span class="qty-badge neutral"><?php echo formatCleanStock($item['ok_qty']); ?> <?php echo htmlspecialchars($item['uom']); ?></span>
                        </td>
                        <td style="text-align: center; white-space: nowrap; font-weight: 600; color: var(--text-main);">
                          <?php echo htmlspecialchars(formatDateDMY($item['production_date'])); ?>
                        </td>
                        <td style="white-space: nowrap;">
                          <span class="batch-lot-code"><?php echo htmlspecialchars($item['rack'] ?? 'RACK-A1'); ?></span>
                        </td>
                        <td style="white-space: nowrap;">
                          <span class="batch-lot-code"><?php echo htmlspecialchars($item['bin'] ?? 'BIN-01'); ?></span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                          <span class="tag tag-passed"><?php echo htmlspecialchars($item['qc_status'] ?? 'QC Passed'); ?></span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                          <div class="action-btns" style="justify-content: center;">
                            <button type="button" class="btn-print" onclick="printRowTag('<?php echo htmlspecialchars($item['inward_no']); ?>')">Print Tag</button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

        <!-- =========================================================
             TAB 3: Dispatch Logs
             ========================================================= -->
        <div class="tab-pane" id="tabDispatchLogs">
          <div class="simple-card table-box">
            
            <div class="table-bar">
              <h2 class="box-title">Finished Goods Dispatch &amp; Outward Logs</h2>
              <div class="table-actions">
                <input type="text" id="dispatchSearch" class="simple-input" placeholder="Search Dispatch No, Customer, Part..." style="width: 280px;">
              </div>
            </div>

            <div class="table-wrap">
              <table class="simple-table" id="dispatchTable">
                <thead>
                  <tr>
                    <th style="width: 45px; text-align: center;">Sr No.</th>
                    <th style="width: 140px; white-space: nowrap;">Dispatch No. &amp; Date</th>
                    <th style="width: 160px; white-space: nowrap;">Invoice / DC Ref.</th>
                    <th style="min-width: 220px;">Part Description</th>
                    <th style="width: 130px; white-space: nowrap;">Batch / Lot No.</th>
                    <th style="width: 110px; text-align: center; white-space: nowrap;">Dispatched Qty</th>
                    <th style="width: 55px; text-align: center; white-space: nowrap;">UOM</th>
                    <th style="min-width: 180px; white-space: nowrap;">Customer / Destination</th>
                    <th style="width: 130px; white-space: nowrap;">Dispatched By</th>
                    <th style="width: 100px; text-align: center; white-space: nowrap;">Status</th>
                    <th style="width: 120px; text-align: center; white-space: nowrap;">Action</th>
                  </tr>
                </thead>
                <tbody id="dispatchTableBody">
                  <?php $srDsp = 1; ?>
                  <?php foreach ($dispatchSampleList as $dsp): ?>
                    <tr data-dispatch_no="<?php echo htmlspecialchars($dsp['dispatch_no']); ?>"
                        data-part_code="<?php echo htmlspecialchars($dsp['part_code']); ?>"
                        data-customer="<?php echo htmlspecialchars($dsp['customer']); ?>">
                      <td style="color: var(--text-sub); font-weight: 600; text-align: center;"><?php echo $srDsp++; ?></td>
                      <td style="white-space: nowrap;">
                        <strong style="color: var(--text-main);"><?php echo htmlspecialchars($dsp['dispatch_no']); ?></strong>
                        <span class="sub-meta"><?php echo htmlspecialchars(formatDateDMY($dsp['dispatch_date'])); ?></span>
                      </td>
                      <td style="white-space: nowrap;">
                        <strong style="color: #4f46e5;"><?php echo htmlspecialchars($dsp['invoice_ref']); ?></strong>
                      </td>
                      <td>
                        <div style="font-weight: 700; color: var(--text-main);">
                          <?php echo htmlspecialchars($dsp['part_code']); ?> - <?php echo htmlspecialchars($dsp['part_name']); ?>
                        </div>
                      </td>
                      <td style="white-space: nowrap;">
                        <span class="batch-lot-code"><?php echo htmlspecialchars($dsp['batch_no']); ?></span>
                      </td>
                      <td style="text-align: center; white-space: nowrap;">
                        <span class="qty-badge" style="background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;"><?php echo formatCleanStock($dsp['dispatch_qty']); ?></span>
                      </td>
                      <td style="text-align: center; color: var(--text-sub); font-weight: 600; font-size: 0.8rem; white-space: nowrap;"><?php echo htmlspecialchars($dsp['uom']); ?></td>
                      <td style="white-space: nowrap;">
                        <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($dsp['customer']); ?></div>
                      </td>
                      <td style="white-space: nowrap;">
                        <strong><?php echo htmlspecialchars($dsp['dispatched_by']); ?></strong>
                      </td>
                      <td style="text-align: center; white-space: nowrap;">
                        <span class="tag tag-dispatched">Dispatched</span>
                      </td>
                      <td style="text-align: center; white-space: nowrap;">
                        <div class="action-btns">
                          <button type="button" class="btn-print" onclick="alert('Print Delivery Challan for ' + '<?php echo htmlspecialchars($dsp['dispatch_no']); ?>')">Print DC</button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

      </div>

    </div>

  </div>

  <!-- =========================================================
       Modal 1: Inward Finished Goods (Scan & Fetch MIP)
       ========================================================= -->
  <div class="modal-overlay" id="inwardModal">
    <div class="modal-card" style="max-width: 760px;">
      <div class="modal-header">
        <h3 class="modal-title" id="inwardModalTitle">Inward Finished Goods</h3>
        <button type="button" class="modal-close-btn" id="closeInwardModalBtn" aria-label="Close modal">&times;</button>
      </div>
      
      <form id="inwardForm">
        <div class="modal-body">
          <div class="popup-form-grid" style="grid-template-columns: 1fr;">
            
            <!-- Scan MIP Issue Slip (Simple Plain Input Field - matching production) -->
            <div class="form-group" style="grid-column: 1 / -1;">
              <label for="scanMipInput">Scan MIP Issue Slip</label>
              <input type="text" id="scanMipInput" class="form-control" placeholder="Scan barcode or enter MIP No." autocomplete="off">
            </div>

            <!-- Fetched Production Data Table (Only 4 Columns: MIP No., Part Code & Name, Ok Qty, Production Date) -->
            <div id="fetchedMipTableWrap" class="form-group" style="grid-column: 1 / -1; display: none;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-main);">
                  Fetched Production Details:
                </span>
                <span id="fetchedMipStatusTag" style="font-size: 0.72rem; padding: 2px 7px; border-radius: 4px; background: #ecfdf5; color: #059669; font-weight: 600; border: 1px solid #a7f3d0;">
                  Data Loaded
                </span>
              </div>
              <div style="overflow-x: auto; border: 1px solid var(--border); border-radius: 6px;">
                <table class="simple-table" style="font-size: 0.84rem; margin: 0; width: 100%;">
                  <thead style="background: #f8fafc;">
                    <tr>
                      <th style="padding: 8px 12px; width: 130px;">MIP No.</th>
                      <th style="padding: 8px 12px;">Part Code &amp; Name</th>
                      <th style="padding: 8px 12px; text-align: center; width: 130px;">Ok Qty</th>
                      <th style="padding: 8px 12px; text-align: center; width: 140px;">Production Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td style="padding: 10px 12px;"><strong id="tblMipNo">-</strong></td>
                      <td style="padding: 10px 12px;" id="tblMipPart">-</td>
                      <td style="padding: 10px 12px; text-align: center;"><strong id="tblMipQty" style="color: #059669; font-size: 0.98rem;">-</strong></td>
                      <td style="padding: 10px 12px; text-align: center;" id="tblMipDate">-</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Storage Location: Rack and Bin -->
            <div id="rackBinWrap" style="grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 6px;">
              <div class="form-group" style="margin-bottom: 0;">
                <label for="inputRack">Rack</label>
                <input type="text" id="inputRack" class="form-control" placeholder="e.g. RACK-A1" value="RACK-A1" autocomplete="off">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label for="inputBin">Bin</label>
                <input type="text" id="inputBin" class="form-control" placeholder="e.g. BIN-01" value="BIN-01" autocomplete="off">
              </div>
            </div>

            <!-- Hidden Inputs to hold scanned data for saving into tables -->
            <input type="hidden" id="inward_no" value="">
            <input type="hidden" id="inward_date" value="">
            <input type="hidden" id="part_code" value="">
            <input type="hidden" id="part_name" value="">
            <input type="hidden" id="batch_no" value="">
            <input type="hidden" id="inward_qty" value="">
            <input type="hidden" id="uom" value="PCS">
            <input type="hidden" id="work_order" value="">
            <input type="hidden" id="location" value="RACK-A1 / BIN-01">
            <input type="hidden" id="received_by" value="">
            <input type="hidden" id="remarks" value="">

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" id="cancelInwardBtn">Cancel</button>
          <button type="submit" class="btn-primary" id="saveInwardBtn" disabled style="opacity: 0.5; cursor: not-allowed;">Save Inward Entry</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       Modal 2: Quick Dispatch FG (Simple Clean Popup)
       ========================================================= -->
  <div class="modal-overlay" id="dispatchModal">
    <div class="modal-card" style="max-width: 540px;">
      <div class="modal-header">
        <h3 class="modal-title">Dispatch Finished Goods</h3>
        <button type="button" class="modal-close-btn" id="closeDispatchModalBtn">&times;</button>
      </div>

      <form id="dispatchForm">
        <input type="hidden" id="disp_row_id">
        <div class="modal-body">
          <div class="popup-form-grid">
            
            <div class="form-group col-full">
              <label>Part Description</label>
              <input type="text" id="disp_part_display" class="form-control" readonly>
            </div>

            <div class="form-group">
              <label>Batch / Lot No.</label>
              <input type="text" id="disp_batch_no" class="form-control" readonly>
            </div>

            <div class="form-group">
              <label>Current Available Stock</label>
              <input type="text" id="disp_available_stock" class="form-control" readonly style="font-weight:700; color:#059669;">
            </div>

            <div class="form-group">
              <label for="disp_qty">Dispatch Quantity *</label>
              <input type="number" id="disp_qty" class="form-control" min="1" step="1" placeholder="Enter qty to dispatch" required>
            </div>

            <div class="form-group">
              <label for="disp_date">Dispatch Date *</label>
              <input type="date" id="disp_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group col-full">
              <label for="disp_invoice">Invoice / Delivery Challan Ref. *</label>
              <input type="text" id="disp_invoice" class="form-control" placeholder="e.g. INV-2026-8802 / DC-104" required>
            </div>

            <div class="form-group col-full">
              <label for="disp_customer">Customer / Destination Name *</label>
              <input type="text" id="disp_customer" class="form-control" placeholder="e.g. Apex Motors Ltd." required>
            </div>

            <div class="form-group col-full">
              <label for="disp_by">Dispatched By *</label>
              <input type="text" id="disp_by" class="form-control" placeholder="Store Executive Name" required>
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn-secondary" id="cancelDispatchBtn">Cancel</button>
          <button type="submit" class="btn-primary" style="background:#4f46e5; border-color:#4338ca;">Confirm Dispatch</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========================================================
       Modal 3: View Details (Simple Clean Popup)
       ========================================================= -->
  <div class="modal-overlay" id="viewModal">
    <div class="modal-card" style="max-width: 580px;">
      <div class="modal-header">
        <h3 class="modal-title">Finished Goods Details</h3>
        <button type="button" class="modal-close-btn" id="closeViewModalBtn">&times;</button>
      </div>

      <div class="modal-body" id="viewModalContent">
        <!-- Rendered dynamically -->
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" id="closeViewBtn">Close</button>
        <button type="button" class="btn-print" id="printFromViewBtn">Print Slip</button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       Print Slip Area (Hidden on screen, visible during print)
       ========================================================= -->
  <div id="printArea" class="print-box">
    <div style="border: 2px solid #000; padding: 20px; font-family: 'Plus Jakarta Sans', sans-serif;">
      <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 12px; margin-bottom: 15px;">
        <h2 style="margin: 0; font-size: 20px; text-transform: uppercase;">Finished Goods Stock &amp; Inward Tag</h2>
        <p style="margin: 4px 0 0 0; font-size: 13px; color: #555;">WIP Manufacturing Management Portal - Store Department</p>
      </div>

      <table style="width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 13px;" border="1" cellpadding="8">
        <tr>
          <td style="width: 25%; font-weight: bold; background: #f2f2f2;">Inward Slip No:</td>
          <td style="width: 25%;" id="prn_inward_no">-</td>
          <td style="width: 25%; font-weight: bold; background: #f2f2f2;">Inward Date:</td>
          <td style="width: 25%;" id="prn_inward_date">-</td>
        </tr>
        <tr>
          <td style="font-weight: bold; background: #f2f2f2;">Part Code:</td>
          <td id="prn_part_code">-</td>
          <td style="font-weight: bold; background: #f2f2f2;">Part Name:</td>
          <td id="prn_part_name">-</td>
        </tr>
        <tr>
          <td style="font-weight: bold; background: #f2f2f2;">Batch / Lot No:</td>
          <td id="prn_batch_no">-</td>
          <td style="font-weight: bold; background: #f2f2f2;">Rack / Location:</td>
          <td id="prn_location">-</td>
        </tr>
        <tr>
          <td style="font-weight: bold; background: #f2f2f2;">Inward Quantity:</td>
          <td id="prn_inward_qty" style="font-weight: bold; font-size: 15px;">-</td>
          <td style="font-weight: bold; background: #f2f2f2;">Available Stock:</td>
          <td id="prn_available_stock" style="font-weight: bold; font-size: 15px;">-</td>
        </tr>
        <tr>
          <td style="font-weight: bold; background: #f2f2f2;">Work Order Ref:</td>
          <td id="prn_work_order">-</td>
          <td style="font-weight: bold; background: #f2f2f2;">Received By:</td>
          <td id="prn_received_by">-</td>
        </tr>
        <tr>
          <td style="font-weight: bold; background: #f2f2f2;">Remarks:</td>
          <td colspan="3" id="prn_remarks">-</td>
        </tr>
      </table>

      <div style="display: flex; justify-content: space-between; margin-top: 40px; padding: 0 20px;">
        <div style="text-align: center;">
          <div style="border-top: 1px solid #000; width: 160px; padding-top: 5px; font-size: 12px; font-weight: bold;">Store Keeper Signature</div>
        </div>
        <div style="text-align: center;">
          <div style="border-top: 1px solid #000; width: 160px; padding-top: 5px; font-size: 12px; font-weight: bold;">Quality QC Inspector</div>
        </div>
        <div style="text-align: center;">
          <div style="border-top: 1px solid #000; width: 160px; padding-top: 5px; font-size: 12px; font-weight: bold;">Authorized Signatory</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="toast-container" aria-live="polite"></div>

  <!-- Client-side Interactive Logic -->
  <script>
    function escapeHtml(text) {
      if (text === null || text === undefined) return '';
      const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
      return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function formatDateDMY(dStr) {
      if (!dStr) return '';
      const clean = dStr.toString().trim().split('T')[0].split(' ')[0];
      const p = clean.split('-');
      if (p.length === 3) {
        if (p[0].length === 4) {
          return `${p[2].padStart(2, '0')}-${p[1].padStart(2, '0')}-${p[0]}`;
        } else if (p[2].length === 4) {
          return clean;
        }
      }
      return dStr;
    }

    function showToast(message, type = 'success') {
      const container = document.getElementById('toastContainer');
      if (!container) return;
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      const iconSvg = type === 'success'
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"></polyline></svg>`
        : (type === 'error'
          ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>`
          : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`);

      toast.innerHTML = `
        <div class="toast-icon">${iconSvg}</div>
        <div class="toast-message">${escapeHtml(message)}</div>
      `;
      container.appendChild(toast);
      requestAnimationFrame(() => toast.classList.add('show'));
      setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 250);
      }, 3500);
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Available Production Entries from production_entry table
      const availableProductionEntries = <?php echo json_encode($productionList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

      // 3 Tabs switching logic
      const tabBtns = document.querySelectorAll('.tab-btn');
      const tabPanes = document.querySelectorAll('.tab-pane');

      tabBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          tabBtns.forEach(function(b) { b.classList.remove('active'); });
          tabPanes.forEach(function(p) { p.classList.remove('active'); });

          btn.classList.add('active');
          const targetId = btn.dataset.tab;
          const targetPane = document.getElementById(targetId);
          if (targetPane) {
            targetPane.classList.add('active');
          }
        });
      });

      // Elements
      const fgSearch = document.getElementById('fgSearch');
      const statusFilter = document.getElementById('statusFilter');
      const fgTableBody = document.getElementById('fgTableBody');
      const inwardTableBody = document.getElementById('inwardTableBody');
      const dispatchTableBody = document.getElementById('dispatchTableBody');

      // Inward Modal & MIP Elements
      const inwardModal = document.getElementById('inwardModal');
      const openInwardModalBtn = document.getElementById('openInwardModalBtn');
      const closeInwardModalBtn = document.getElementById('closeInwardModalBtn');
      const cancelInwardBtn = document.getElementById('cancelInwardBtn');
      const inwardForm = document.getElementById('inwardForm');
      const scanMipInput = document.getElementById('scanMipInput');
      const saveInwardBtn = document.getElementById('saveInwardBtn');

      const dispatchModal = document.getElementById('dispatchModal');
      const closeDispatchModalBtn = document.getElementById('closeDispatchModalBtn');
      const cancelDispatchBtn = document.getElementById('cancelDispatchBtn');
      const dispatchForm = document.getElementById('dispatchForm');

      const viewModal = document.getElementById('viewModal');
      const closeViewModalBtn = document.getElementById('closeViewModalBtn');
      const closeViewBtn = document.getElementById('closeViewBtn');
      const viewModalContent = document.getElementById('viewModalContent');
      const printFromViewBtn = document.getElementById('printFromViewBtn');

      let currentActiveRow = null;

      // Clear MIP Scan state
      function clearMipScan() {
        if (scanMipInput) scanMipInput.value = '';
        const tblWrap = document.getElementById('fetchedMipTableWrap');
        if (tblWrap) tblWrap.style.display = 'none';

        ['inward_no', 'inward_date', 'part_code', 'part_name', 'batch_no', 'inward_qty', 'uom', 'work_order', 'location', 'received_by', 'remarks'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.value = '';
        });

        const rackEl = document.getElementById('inputRack');
        if (rackEl) rackEl.value = 'RACK-A1';
        const binEl = document.getElementById('inputBin');
        if (binEl) binEl.value = 'BIN-01';

        if (saveInwardBtn) {
          saveInwardBtn.disabled = true;
          saveInwardBtn.style.opacity = '0.5';
          saveInwardBtn.style.cursor = 'not-allowed';
        }
      }

      // Format clean integer / decimal for stock display
      function formatCleanStockJs(val) {
        const f = parseFloat(val) || 0;
        if (Number.isInteger(f)) {
          return f.toLocaleString();
        }
        return f.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 3});
      }

      // Fetch Scanned MIP from production_entry table
      async function fetchScannedMip(code) {
        const query = (code || '').trim().toLowerCase();
        if (!query) {
          clearMipScan();
          return;
        }

        const cleanQuery = query.replace(/[^a-z0-9]/g, '');

        // 1. Search in preloaded production entries STRICTLY by exact mip_no
        let match = availableProductionEntries.find(p => {
          if (!p.mip_no) return false;
          const mip = p.mip_no.trim().toLowerCase();
          const cleanMip = mip.replace(/[^a-z0-9]/g, '');
          return mip === query || (cleanQuery.length >= 3 && cleanMip === cleanQuery);
        });

        // 2. If not found in preloaded list, query server for exact mip_no
        if (!match) {
          try {
            const res = await fetch('fg-store.php?action=search_mip&q=' + encodeURIComponent(query));
            const resJson = await res.json();
            if (resJson.success && resJson.data && resJson.data.mip_no) {
              match = resJson.data;
              if (!availableProductionEntries.find(p => p.id === match.id)) {
                availableProductionEntries.unshift(match);
              }
            }
          } catch (e) {
            // ignore network errors
          }
        }

        const tblWrap = document.getElementById('fetchedMipTableWrap');

        if (match && match.mip_no) {
          const okQty = parseFloat(match.ok_qty || 0);
          const pCode = match.part_code || '';
          const pName = match.part_name || pCode;
          const uom = match.uom || 'PCS';
          const prodDate = match.production_date || (match.created_at ? match.created_at.substring(0, 10) : new Date().toISOString().split('T')[0]);
          const defaultLoc = match.department ? `${match.department} / BAY-1` : 'RACK-A1 / BIN-01';
          const newInNo = 'FGI-' + new Date().getFullYear() + '-' + String(Math.floor(100 + Math.random() * 900));

          // Set hidden inputs for saving
          document.getElementById('inward_no').value = newInNo;
          document.getElementById('inward_date').value = prodDate;
          document.getElementById('part_code').value = pCode;
          document.getElementById('part_name').value = pName;
          document.getElementById('batch_no').value = match.mip_no || ('LOT-' + match.id);
          document.getElementById('inward_qty').value = okQty;
          document.getElementById('uom').value = uom;
          document.getElementById('work_order').value = match.work_order || '';
          document.getElementById('location').value = defaultLoc;
          document.getElementById('received_by').value = match.operator_name || match.received_by || '';
          document.getElementById('remarks').value = `Inwarded from Production Entry (MIP: ${match.mip_no || '-'}, OK Qty: ${formatCleanStockJs(okQty)} ${uom})`;

          // Populate exactly the 4 requested table columns:
          // 1. MIP No., 2. Part Code & Name, 3. Ok Qty, 4. Production Date
          document.getElementById('tblMipNo').textContent = match.mip_no || '-';
          document.getElementById('tblMipPart').textContent = `${pCode} - ${pName}`;
          document.getElementById('tblMipQty').textContent = `${formatCleanStockJs(okQty)} ${uom}`;
          document.getElementById('tblMipDate').textContent = formatDateDMY(prodDate);

          if (tblWrap) tblWrap.style.display = 'block';

          // Enable Save Button
          if (saveInwardBtn) {
            saveInwardBtn.disabled = false;
            saveInwardBtn.style.opacity = '1';
            saveInwardBtn.style.cursor = 'pointer';
          }

          showToast(`Production Entry for MIP "${match.mip_no}" loaded successfully!`, 'success');
        } else {
          // If NOT a valid MIP number, completely hide table and disable save
          if (tblWrap) tblWrap.style.display = 'none';
          ['inward_no', 'inward_date', 'part_code', 'part_name', 'batch_no', 'inward_qty', 'uom', 'work_order', 'location', 'received_by', 'remarks'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
          });
          if (saveInwardBtn) {
            saveInwardBtn.disabled = true;
            saveInwardBtn.style.opacity = '0.5';
            saveInwardBtn.style.cursor = 'not-allowed';
          }
          showToast(`Invalid MIP No. "${code}". No record found in production_entry table. Please enter a valid MIP number.`, 'error');
        }
      }

      // Scan input events: Triggers strictly on Enter (barcode scanner) or change (blur)
      if (scanMipInput) {
        scanMipInput.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            fetchScannedMip(scanMipInput.value);
          }
        });

        scanMipInput.addEventListener('change', function() {
          fetchScannedMip(scanMipInput.value);
        });

        scanMipInput.addEventListener('input', function() {
          if (!scanMipInput.value.trim()) {
            clearMipScan();
          }
        });
      }

      // Open Inward Modal
      if (openInwardModalBtn) {
        openInwardModalBtn.addEventListener('click', function() {
          clearMipScan();
          inwardModal.classList.add('active');
          setTimeout(() => {
            if (scanMipInput) scanMipInput.focus();
          }, 120);
        });
      }

      // Close Inward Modal
      function closeInwardModal() {
        inwardModal.classList.remove('active');
      }
      if (closeInwardModalBtn) closeInwardModalBtn.addEventListener('click', closeInwardModal);
      if (cancelInwardBtn) cancelInwardBtn.addEventListener('click', closeInwardModal);

      // Close Dispatch Modal
      function closeDispatchModal() {
        dispatchModal.classList.remove('active');
      }
      if (closeDispatchModalBtn) closeDispatchModalBtn.addEventListener('click', closeDispatchModal);
      if (cancelDispatchBtn) cancelDispatchBtn.addEventListener('click', closeDispatchModal);

      // Close View Modal
      function closeViewModal() {
        viewModal.classList.remove('active');
      }
      if (closeViewModalBtn) closeViewModalBtn.addEventListener('click', closeViewModal);
      if (closeViewBtn) closeViewBtn.addEventListener('click', closeViewModal);

      // Close modal on click outside
      [inwardModal, dispatchModal, viewModal].forEach(function(m) {
        if (!m) return;
        m.addEventListener('click', function(e) {
          if (e.target === m) {
            m.classList.remove('active');
          }
        });
      });

      // Recalculate Stats & Tab counts
      function recalculateStats() {
        let totalStock = 0;
        let todayInward = 0;
        let totalDispatched = 0;
        const todayStr = new Date().toISOString().split('T')[0];

        const rows = fgTableBody.querySelectorAll('tr');
        rows.forEach(function(tr) {
          const stock = parseFloat(tr.dataset.available_stock || 0);
          const inQty = parseFloat(tr.dataset.inward_qty || 0);
          const dispQty = parseFloat(tr.dataset.dispatched_qty || 0);
          const inDate = tr.dataset.inward_date || '';

          totalStock += stock;
          totalDispatched += dispQty;
          if (inDate === todayStr) {
            todayInward += inQty;
          }
        });

        document.getElementById('statTotalStock').textContent = Math.round(totalStock).toLocaleString();
        document.getElementById('statTodayInward').textContent = Math.round(todayInward).toLocaleString();
        document.getElementById('statTotalDispatched').textContent = Math.round(totalDispatched).toLocaleString();

        document.getElementById('countInventory').textContent = rows.length;
        if (inwardTableBody) document.getElementById('countInward').textContent = inwardTableBody.querySelectorAll('tr').length;
        if (dispatchTableBody) document.getElementById('countDispatch').textContent = dispatchTableBody.querySelectorAll('tr').length;
      }

      // Save Inward Form (Persists to database: fg_inward_logs and fg_inventory)
      inwardForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const partCode = document.getElementById('part_code').value;
        if (!partCode) {
          showToast('Please scan or fetch a valid MIP No. first.', 'error');
          return;
        }

        const inNo = document.getElementById('inward_no').value;
        const inDate = document.getElementById('inward_date').value;
        const partName = document.getElementById('part_name').value;
        const batchNo = document.getElementById('batch_no').value || (scanMipInput ? scanMipInput.value.trim() : '');
        const inQty = parseFloat(document.getElementById('inward_qty').value) || 0;
        const uom = document.getElementById('uom').value || 'PCS';
        const rackVal = (document.getElementById('inputRack')?.value || 'RACK-A1').trim();
        const binVal = (document.getElementById('inputBin')?.value || 'BIN-01').trim();
        const wo = document.getElementById('work_order').value;
        const recBy = document.getElementById('received_by').value;
        const remarks = document.getElementById('remarks').value;

        if (inQty <= 0) {
          showToast('Inward quantity must be greater than zero.', 'error');
          return;
        }

        if (saveInwardBtn) {
          saveInwardBtn.disabled = true;
          saveInwardBtn.textContent = 'Saving Inward...';
        }

        const payload = {
          inward_no: inNo,
          inward_date: inDate,
          mip_no: batchNo,
          part_code: partCode,
          part_name: partName,
          inward_qty: inQty,
          uom: uom,
          rack: rackVal || 'RACK-A1',
          bin: binVal || 'BIN-01',
          work_order: wo,
          received_by: recBy,
          remarks: remarks
        };

        try {
          const resp = await fetch('api/fg_store.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });
          const res = await resp.json();

          if (res.success) {
            showToast('Finished goods inward saved successfully!', 'success');
            closeInwardModal();
            setTimeout(() => {
              window.location.reload();
            }, 600);
          } else {
            showToast(res.message || 'Error saving inward entry.', 'error');
            if (saveInwardBtn) {
              saveInwardBtn.disabled = false;
              saveInwardBtn.textContent = 'Save Inward Entry';
            }
          }
        } catch (err) {
          showToast('Network error while saving: ' + err.message, 'error');
          if (saveInwardBtn) {
            saveInwardBtn.disabled = false;
            saveInwardBtn.textContent = 'Save Inward Entry';
          }
        }
      });

      // Quick Dispatch Form Submission (Updates Tab 1, Adds to Tab 3)
      dispatchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentActiveRow) return;

        const dispQty = parseFloat(document.getElementById('disp_qty').value) || 0;
        const currentStock = parseFloat(currentActiveRow.dataset.available_stock || 0);

        if (dispQty > currentStock) {
          alert('Error: Dispatch quantity cannot be greater than available stock (' + currentStock + ')');
          return;
        }

        const prevDisp = parseFloat(currentActiveRow.dataset.dispatched_qty || 0);
        const newDisp = prevDisp + dispQty;
        const newStock = currentStock - dispQty;

        currentActiveRow.dataset.dispatched_qty = newDisp;
        currentActiveRow.dataset.available_stock = newStock;

        // Update badge and status
        let status = 'Available';
        let badgeCls = 'in-stock';
        let tagCls = 'tag-available';

        if (newStock <= 0) {
          status = 'Dispatched';
          badgeCls = 'zero-stock';
          tagCls = 'tag-dispatched';
        } else if (newStock <= 35) {
          status = 'Low Stock';
          badgeCls = 'low-stock';
          tagCls = 'tag-low';
        }

        currentActiveRow.dataset.status = status;

        // Update cell in Tab 1
        const qtyBadge = currentActiveRow.querySelector('.qty-badge');
        if (qtyBadge) {
          qtyBadge.className = `qty-badge ${badgeCls}`;
          qtyBadge.textContent = `${formatCleanStockJs(newStock)} ${currentActiveRow.dataset.uom || 'PCS'}`;
        }

        // Disable dispatch button if zero stock
        const dispBtn = currentActiveRow.querySelector('.btn-dispatch');
        if (dispBtn && newStock <= 0) {
          dispBtn.disabled = true;
          dispBtn.style.opacity = '0.4';
          dispBtn.style.cursor = 'not-allowed';
        }

        // Add to Tab 3 (Dispatch Logs)
        if (dispatchTableBody) {
          const dspDate = document.getElementById('disp_date').value;
          const dParts = dspDate.split('-');
          const dspDateDMY = dParts.length === 3 ? (dParts[2] + '-' + dParts[1] + '-' + dParts[0]) : dspDate;
          const invoiceRef = document.getElementById('disp_invoice').value;
          const customer = document.getElementById('disp_customer').value;
          const dispBy = document.getElementById('disp_by').value;
          const dspNo = 'DSP-' + new Date().getFullYear() + '-' + String(Math.floor(100 + Math.random() * 900));

          const dspTr = document.createElement('tr');
          const dspSr = dispatchTableBody.querySelectorAll('tr').length + 1;
          dspTr.innerHTML = `
            <td style="color: var(--text-sub); font-weight: 600; text-align: center;">${dspSr}</td>
            <td style="white-space: nowrap;">
              <strong style="color: var(--text-main);">${dspNo}</strong>
              <span class="sub-meta">${dspDateDMY}</span>
            </td>
            <td style="white-space: nowrap;">
              <strong style="color: #4f46e5;">${invoiceRef}</strong>
            </td>
            <td>
              <div style="font-weight: 700; color: var(--text-main);">${currentActiveRow.dataset.part_code} - ${currentActiveRow.dataset.part_name}</div>
            </td>
            <td style="white-space: nowrap;">
              <span class="batch-lot-code">${currentActiveRow.dataset.batch_no}</span>
            </td>
            <td style="text-align: center; white-space: nowrap;">
              <span class="qty-badge" style="background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe;">${dispQty.toLocaleString()}</span>
            </td>
            <td style="text-align: center; color: var(--text-sub); font-weight: 600; font-size: 0.8rem; white-space: nowrap;">${currentActiveRow.dataset.uom}</td>
            <td style="white-space: nowrap;">
              <div style="font-weight: 700; color: var(--text-main);">${customer}</div>
            </td>
            <td style="white-space: nowrap;">
              <strong>${dispBy}</strong>
            </td>
            <td style="text-align: center; white-space: nowrap;">
              <span class="tag tag-dispatched">Dispatched</span>
            </td>
            <td style="text-align: center; white-space: nowrap;">
              <div class="action-btns">
                <button type="button" class="btn-print" onclick="alert('Print Delivery Challan for ' + '${dspNo}')">Print DC</button>
              </div>
            </td>
          `;
          dispatchTableBody.prepend(dspTr);
        }

        closeDispatchModal();
        recalculateStats();
        alert('Success: ' + dispQty + ' units dispatched successfully!');
      });

      // Row Actions handler
      function attachRowEvents(tr) {
        // View Details
        const viewBtn = tr.querySelector('.btn-view');
        if (viewBtn) {
          viewBtn.addEventListener('click', function() {
            currentActiveRow = tr;
            showViewModal(tr);
          });
        }

        // Dispatch
        const dispatchBtn = tr.querySelector('.btn-dispatch');
        if (dispatchBtn) {
          dispatchBtn.addEventListener('click', function() {
            currentActiveRow = tr;
            const stock = parseFloat(tr.dataset.available_stock || 0);
            if (stock <= 0) {
              alert('This batch has zero stock available for dispatch.');
              return;
            }
            document.getElementById('disp_row_id').value = tr.dataset.id;
            document.getElementById('disp_part_display').value = tr.dataset.part_code + ' - ' + tr.dataset.part_name;
            document.getElementById('disp_batch_no').value = tr.dataset.batch_no;
            document.getElementById('disp_available_stock').value = stock + ' ' + tr.dataset.uom;
            document.getElementById('disp_qty').value = '';
            document.getElementById('disp_qty').max = stock;
            document.getElementById('disp_invoice').value = '';
            document.getElementById('disp_customer').value = '';
            document.getElementById('disp_by').value = '';
            dispatchModal.classList.add('active');
          });
        }

        // Print Tag
        const printBtn = tr.querySelector('.btn-print');
        if (printBtn) {
          printBtn.addEventListener('click', function() {
            populatePrintArea(tr);
            window.print();
          });
        }

        // Delete Row
        const deleteBtn = tr.querySelector('.btn-delete');
        if (deleteBtn) {
          deleteBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete this finished goods entry?')) {
              tr.remove();
              recalculateStats();
            }
          });
        }
      }

      // Show View Modal
      function showViewModal(tr) {
        const d = tr.dataset;
        const dParts = (d.inward_date || '').split('-');
        const dateDMY = dParts.length === 3 ? (dParts[2] + '-' + dParts[1] + '-' + dParts[0]) : d.inward_date;

        let locations = [];
        try {
          locations = JSON.parse(d.locations || '[]');
        } catch (e) {
          locations = [];
        }

        const totalQty = parseFloat(d.ok_qty || d.available_stock || 0);

        let locationRowsHtml = '';
        if (locations && locations.length > 0) {
          locationRowsHtml = `
            <div style="grid-column: 1 / -1; margin-top: 6px;">
              <span style="font-size: 0.8rem; font-weight: 700; color: #1e293b; display: block; margin-bottom: 8px;">
                Warehouse Storage Breakdown (${locations.length} Location${locations.length > 1 ? 's' : ''}):
              </span>
              <table style="width: 100%; border-collapse: collapse; font-size: 0.83rem; background: #f8fafc; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                <thead>
                  <tr style="background: #f1f5f9; text-align: left; color: #475569; font-size: 0.78rem;">
                    <th style="padding: 8px 12px; font-weight: 600;">Rack / Bin</th>
                    <th style="padding: 8px 12px; font-weight: 600; text-align: right;">Quantity</th>
                    <th style="padding: 8px 12px; font-weight: 600; text-align: center;">Last MIP</th>
                    <th style="padding: 8px 12px; font-weight: 600; text-align: right;">Last Inward</th>
                  </tr>
                </thead>
                <tbody>
                  ${locations.map(loc => `
                    <tr style="border-top: 1px solid #e2e8f0;">
                      <td style="padding: 8px 12px; font-weight: 600; color: #1e293b;">
                        ${loc.rack} / ${loc.bin}
                      </td>
                      <td style="padding: 8px 12px; font-weight: 700; color: #059669; text-align: right;">
                        ${formatCleanStockJs(loc.qty)} ${loc.uom || d.uom || 'PCS'}
                      </td>
                      <td style="padding: 8px 12px; text-align: center; color: #334155; font-weight: 600;">
                        ${loc.last_mip || '-'}
                      </td>
                      <td style="padding: 8px 12px; text-align: right; color: #64748b;">
                        ${loc.date ? (loc.date.split('-').length === 3 ? (loc.date.split('-')[2] + '-' + loc.date.split('-')[1] + '-' + loc.date.split('-')[0]) : loc.date) : '-'}
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          `;
        }

        viewModalContent.innerHTML = `
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; font-size: 0.9rem;">
            <div style="grid-column: 1 / -1;">
              <span style="font-size: 0.78rem; color: var(--text-sub); display: block;">Finished Part</span>
              <strong style="font-size: 1.15rem; color: var(--text-main);">${d.part_code}</strong> - <span style="font-weight: 600; color: #334155;">${d.part_name}</span>
            </div>
            <div>
              <span style="font-size: 0.78rem; color: var(--text-sub); display: block;">Total Available Stock</span>
              <strong style="font-size: 1.25rem; color: #059669;">${formatCleanStockJs(totalQty)} ${d.uom || 'PCS'}</strong>
            </div>
            <div>
              <span style="font-size: 0.78rem; color: var(--text-sub); display: block;">Stock Status</span>
              <span class="tag tag-available">Available</span>
            </div>
            <div>
              <span style="font-size: 0.78rem; color: var(--text-sub); display: block;">Last Inward MIP</span>
              <strong style="color: var(--primary); font-size: 0.95rem;">${d.mip_no || d.batch_no || d.inward_no || '-'}</strong>
            </div>
            <div>
              <span style="font-size: 0.78rem; color: var(--text-sub); display: block;">Last Inward Date</span>
              <strong>${dateDMY || '-'}</strong>
            </div>

            ${locationRowsHtml}

            ${d.remarks ? `
            <div style="grid-column: 1 / -1;">
              <span style="font-size: 0.78rem; color: var(--text-sub); display: block;">Remarks</span>
              <p style="margin: 3px 0 0 0; color: #475569;">${d.remarks}</p>
            </div>` : ''}
          </div>
        `;
        viewModal.classList.add('active');
      }

      // Populate Print Area
      function populatePrintArea(tr) {
        const d = tr.dataset;
        const dParts = (d.inward_date || '').split('-');
        const dateDMY = dParts.length === 3 ? (dParts[2] + '-' + dParts[1] + '-' + dParts[0]) : d.inward_date;

        document.getElementById('prn_inward_no').textContent = d.inward_no;
        document.getElementById('prn_inward_date').textContent = dateDMY;
        document.getElementById('prn_part_code').textContent = d.part_code;
        document.getElementById('prn_part_name').textContent = d.part_name;
        document.getElementById('prn_batch_no').textContent = d.batch_no;
        document.getElementById('prn_location').textContent = d.location;
        document.getElementById('prn_inward_qty').textContent = parseFloat(d.inward_qty).toLocaleString() + ' ' + d.uom;
        document.getElementById('prn_available_stock').textContent = parseFloat(d.available_stock).toLocaleString() + ' ' + d.uom;
        document.getElementById('prn_work_order').textContent = d.work_order || '-';
        document.getElementById('prn_received_by').textContent = d.received_by || '-';
        document.getElementById('prn_remarks').textContent = d.remarks || '-';
      }

      window.printRowTag = function(inwardNo) {
        const tr = fgTableBody.querySelector(`tr[data-inward_no="${inwardNo}"]`);
        if (tr) {
          populatePrintArea(tr);
          window.print();
        }
      };

      if (printFromViewBtn) {
        printFromViewBtn.addEventListener('click', function() {
          if (currentActiveRow) {
            populatePrintArea(currentActiveRow);
            window.print();
          }
        });
      }

      // Attach events to existing rows
      fgTableBody.querySelectorAll('tr').forEach(attachRowEvents);

      // Search Filter for Tab 1 (Inventory)
      function filterInventoryTable() {
        const q = (fgSearch ? fgSearch.value : '').trim().toLowerCase();

        fgTableBody.querySelectorAll('tr').forEach(function(tr) {
          const text = tr.textContent.toLowerCase();
          const matchesSearch = !q || text.includes(q);
          tr.style.display = matchesSearch ? '' : 'none';
        });
      }

      if (fgSearch) fgSearch.addEventListener('input', filterInventoryTable);

      // Search for Tab 2 (Inward Logs)
      const inwardSearch = document.getElementById('inwardSearch');
      if (inwardSearch && inwardTableBody) {
        inwardSearch.addEventListener('input', function() {
          const q = (inwardSearch.value || '').trim().toLowerCase();
          inwardTableBody.querySelectorAll('tr').forEach(function(tr) {
            tr.style.display = (!q || tr.textContent.toLowerCase().includes(q)) ? '' : 'none';
          });
        });
      }

      // Search for Tab 3 (Dispatch Logs)
      const dispatchSearch = document.getElementById('dispatchSearch');
      if (dispatchSearch && dispatchTableBody) {
        dispatchSearch.addEventListener('input', function() {
          const q = (dispatchSearch.value || '').trim().toLowerCase();
          dispatchTableBody.querySelectorAll('tr').forEach(function(tr) {
            tr.style.display = (!q || tr.textContent.toLowerCase().includes(q)) ? '' : 'none';
          });
        });
      }
    });
  </script>
</body>
</html>
