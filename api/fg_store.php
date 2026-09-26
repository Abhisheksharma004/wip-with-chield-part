<?php
/**
 * WIP Management Portal - Finished Goods (FG) Store API
 * Endpoint: /api/fg_store.php
 * Handles CRUD and Inward actions for Finished Goods Inventory and Logs.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth/check_auth.php';
require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Ensure tables exist
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
    // Ignore if already verified
}

// -----------------------------------------------------------------
// 1. GET REQUEST - Fetch FG inventory or inward logs
// -----------------------------------------------------------------
if ($method === 'GET') {
    $type = trim($_GET['type'] ?? 'inventory');
    try {
        if ($type === 'logs') {
            $stmt = $pdo->query("
                SELECT id, inward_no, mip_no, 
                       CONVERT(VARCHAR(10), production_date, 120) as production_date,
                       part_code, part_name, ok_qty, uom, rack, bin, qc_status, 
                       work_order, received_by, remarks,
                       CONVERT(VARCHAR(19), created_at, 120) as created_at
                FROM fg_inward_logs
                ORDER BY id DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("
                SELECT id, part_code, part_name, total_ok_qty, uom, rack, bin, 
                       last_mip_no,
                       CONVERT(VARCHAR(10), last_production_date, 120) as production_date,
                       CONVERT(VARCHAR(19), updated_at, 120) as updated_at
                FROM fg_inventory
                ORDER BY id DESC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'data' => $data, 'count' => count($data)]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// -----------------------------------------------------------------
// 2. POST REQUEST - Save Inward FG
// -----------------------------------------------------------------
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    $inwardNo = trim($data['inward_no'] ?? '');
    $mipNo = trim($data['mip_no'] ?? '');
    $partCode = trim($data['part_code'] ?? '');
    $partName = trim($data['part_name'] ?? '');
    $okQty = floatval($data['inward_qty'] ?? $data['ok_qty'] ?? 0);
    $uom = trim($data['uom'] ?? 'PCS');
    $rack = trim($data['rack'] ?? 'RACK-A1');
    $bin = trim($data['bin'] ?? 'BIN-01');
    $prodDate = trim($data['inward_date'] ?? $data['production_date'] ?? date('Y-m-d'));
    $workOrder = trim($data['work_order'] ?? '');
    $receivedBy = trim($data['received_by'] ?? '');
    $remarks = trim($data['remarks'] ?? '');

    if (empty($inwardNo)) {
        $inwardNo = 'FGI-' . date('Y') . '-' . rand(100, 999);
    }
    if (empty($partCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Part Code is required.']);
        exit;
    }
    if ($okQty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ok quantity must be greater than zero.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert into fg_inward_logs
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
        $newTotal = floatval($sumRow['overall_total'] ?? $locationTotal);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Finished goods inward saved successfully!',
            'data' => [
                'part_code' => $partCode,
                'part_name' => $partName,
                'added_qty' => $okQty,
                'total_qty' => $newTotal,
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
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
