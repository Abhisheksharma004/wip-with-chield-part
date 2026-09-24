<?php
/**
 * WIP Management Portal - RM Inward API Endpoint (PHP + MSSQL)
 * Stores each raw material item as a separate row in rm_inward.
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET request - Fetch all RM Inward records (grouped by inward_no)
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("
            SELECT id, inward_no, CONVERT(VARCHAR(10), inward_date, 120) as inward_date, 
                   vendor_name, invoice_no, CONVERT(VARCHAR(10), invoice_date, 120) as invoice_date, 
                   rm_code, rm_name, received_qty, uom, created_at 
            FROM rm_inward 
            ORDER BY id DESC
        ");
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group rows by inward_no
        $grouped = [];
        foreach ($rawRows as $row) {
            $inwNo = !empty($row['inward_no']) ? $row['inward_no'] : ('ID_' . $row['id']);

            if (!isset($grouped[$inwNo])) {
                $grouped[$inwNo] = [
                    'id' => $row['id'],
                    'inward_no' => $row['inward_no'] ?? '',
                    'inward_date' => $row['inward_date'] ?? '',
                    'vendor_name' => $row['vendor_name'] ?? '',
                    'invoice_no' => $row['invoice_no'] ?? '',
                    'invoice_date' => $row['invoice_date'] ?? '',
                    'items' => [],
                    'received_qty' => 0,
                    'uom' => $row['uom'] ?? 'KG',
                    'created_at' => $row['created_at'] ?? ''
                ];
            }

            $grouped[$inwNo]['items'][] = [
                'id' => $row['id'],
                'rm_code' => $row['rm_code'] ?? '',
                'rm_name' => $row['rm_name'] ?? '',
                'received_qty' => floatval($row['received_qty'] ?? 0),
                'uom' => $row['uom'] ?? 'KG'
            ];
            $grouped[$inwNo]['received_qty'] += floatval($row['received_qty'] ?? 0);
        }

        $items = [];
        foreach ($grouped as $entry) {
            $itemsList = $entry['items'];
            $itemCount = count($itemsList);
            $firstItem = $itemCount > 0 ? $itemsList[0] : null;

            $entry['item_count'] = $itemCount;
            $entry['rm_code'] = ($itemCount === 1) ? ($firstItem['rm_code'] ?? '') : (($firstItem['rm_code'] ?? '') . " (+" . ($itemCount - 1) . " more)");
            $entry['rm_name'] = ($itemCount === 1) ? ($firstItem['rm_name'] ?? '') : ($itemCount . " RM Items");
            $entry['uom'] = $firstItem['uom'] ?? ($entry['uom'] ?? 'KG');
            $entry['items_data'] = json_encode($itemsList, JSON_UNESCAPED_UNICODE);

            $items[] = $entry;
        }

        echo json_encode([
            'success' => true,
            'data' => $items,
            'count' => count($items)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST request (create, update, delete)
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = trim($data['action'] ?? 'create');

    // 1. DELETE ACTION
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        $inwardNo = trim($data['inward_no'] ?? '');

        if ($id <= 0 && empty($inwardNo)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Inward ID or Inward No is required for deletion.']);
            exit;
        }

        try {
            if (!empty($inwardNo)) {
                $stmt = $pdo->prepare("DELETE FROM rm_inward WHERE inward_no = ?");
                $stmt->execute([$inwardNo]);
            } else {
                $findStmt = $pdo->prepare("SELECT inward_no FROM rm_inward WHERE id = ?");
                $findStmt->execute([$id]);
                $found = $findStmt->fetch();
                if ($found && !empty($found['inward_no'])) {
                    $stmt = $pdo->prepare("DELETE FROM rm_inward WHERE inward_no = ?");
                    $stmt->execute([$found['inward_no']]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM rm_inward WHERE id = ?");
                    $stmt->execute([$id]);
                }
            }

            echo json_encode(['success' => true, 'message' => 'RM Inward entry deleted successfully.']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // Common fields
    $inwardNo = trim($data['inward_no'] ?? '');
    $inwardDate = trim($data['inward_date'] ?? date('Y-m-d'));
    $vendorName = trim($data['vendor_name'] ?? '');
    $invoiceNo = trim($data['invoice_no'] ?? '');
    $invoiceDate = trim($data['invoice_date'] ?? '');

    if (empty($invoiceDate)) {
        $invoiceDate = null;
    }

    if (empty($vendorName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vendor is required.']);
        exit;
    }

    // Process all items
    $itemsList = [];
    $totalQty = 0;
    $primaryUom = 'KG';

    if (!empty($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $it) {
            $itRmCode = trim($it['rm_code'] ?? '');
            $itRmName = trim($it['rm_name'] ?? '');
            $itQty = floatval($it['received_qty'] ?? 0);
            $itUom = trim($it['uom'] ?? 'KG');

            if (empty($itRmCode) || $itQty <= 0) continue;

            if (empty($itRmName)) {
                $rmLk = $pdo->prepare("SELECT rm_name, uom FROM rm_master WHERE rm_code = ?");
                $rmLk->execute([$itRmCode]);
                $f = $rmLk->fetch();
                if ($f) {
                    $itRmName = $f['rm_name'];
                    if (empty($it['uom']) && !empty($f['uom'])) {
                        $itUom = $f['uom'];
                    }
                }
            }

            $itemsList[] = [
                'rm_code' => $itRmCode,
                'rm_name' => $itRmName,
                'received_qty' => $itQty,
                'uom' => $itUom
            ];
            $totalQty += $itQty;
            $primaryUom = $itUom;
        }
    } elseif (!empty($data['rm_code']) && floatval($data['received_qty'] ?? 0) > 0) {
        $singleRmCode = trim($data['rm_code']);
        $singleRmName = trim($data['rm_name'] ?? '');
        $singleQty = floatval($data['received_qty']);
        $singleUom = trim($data['uom'] ?? 'KG');

        if (empty($singleRmName)) {
            $rmLk = $pdo->prepare("SELECT rm_name, uom FROM rm_master WHERE rm_code = ?");
            $rmLk->execute([$singleRmCode]);
            $f = $rmLk->fetch();
            if ($f) {
                $singleRmName = $f['rm_name'];
                if (empty($data['uom']) && !empty($f['uom'])) {
                    $singleUom = $f['uom'];
                }
            }
        }

        $itemsList[] = [
            'rm_code' => $singleRmCode,
            'rm_name' => $singleRmName,
            'received_qty' => $singleQty,
            'uom' => $singleUom
        ];
        $totalQty = $singleQty;
        $primaryUom = $singleUom;
    }

    if (empty($itemsList)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please provide at least one valid RM item with positive quantity.']);
        exit;
    }

    $firstItem = $itemsList[0];
    $itemCount = count($itemsList);
    $summaryRmCode = ($itemCount === 1) ? $firstItem['rm_code'] : ($firstItem['rm_code'] . " (+" . ($itemCount - 1) . " more)");
    $summaryRmName = ($itemCount === 1) ? $firstItem['rm_name'] : ($itemCount . " Raw Material Items");
    $itemsJson = json_encode($itemsList, JSON_UNESCAPED_UNICODE);

    // 2. UPDATE ACTION
    if ($action === 'update') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0 && empty($inwardNo)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid ID or Inward No is required for update.']);
            exit;
        }

        try {
            if (empty($inwardNo) && $id > 0) {
                $lookupStmt = $pdo->prepare("SELECT inward_no FROM rm_inward WHERE id = ?");
                $lookupStmt->execute([$id]);
                $cur = $lookupStmt->fetch();
                if ($cur && !empty($cur['inward_no'])) {
                    $inwardNo = $cur['inward_no'];
                }
            }

            if (empty($inwardNo)) {
                $inwardNo = "INW-" . date('y') . "-" . str_pad($id, 4, '0', STR_PAD_LEFT);
            }

            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("DELETE FROM rm_inward WHERE inward_no = ?");
            $delStmt->execute([$inwardNo]);

            $insertStmt = $pdo->prepare("
                INSERT INTO rm_inward (
                    inward_no, inward_date, vendor_name, invoice_no, invoice_date,
                    rm_code, rm_name, received_qty, uom,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())
            ");

            $firstInsertedId = null;
            foreach ($itemsList as $it) {
                $insertStmt->execute([
                    $inwardNo,
                    $inwardDate,
                    $vendorName,
                    $invoiceNo,
                    $invoiceDate,
                    $it['rm_code'],
                    $it['rm_name'],
                    $it['received_qty'],
                    $it['uom']
                ]);

                if ($firstInsertedId === null) {
                    $firstInsertedId = $pdo->lastInsertId();
                }
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Inward entry '{$inwardNo}' updated successfully (" . count($itemsList) . " items saved as separate rows).",
                'data' => [
                    'id' => $firstInsertedId ?: $id,
                    'inward_no' => $inwardNo,
                    'inward_date' => $inwardDate,
                    'vendor_name' => $vendorName,
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $invoiceDate,
                    'rm_code' => $summaryRmCode,
                    'rm_name' => $summaryRmName,
                    'received_qty' => $totalQty,
                    'uom' => $primaryUom,
                    'items_data' => $itemsJson,
                    'items' => $itemsList
                ]
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // 3. CREATE ACTION
    try {
        if (empty($inwardNo)) {
            $yearPrefix = date('y');
            $prefix = "INW-{$yearPrefix}-";
            $stmtSeq = $pdo->prepare("SELECT inward_no FROM rm_inward WHERE inward_no LIKE ?");
            $stmtSeq->execute([$prefix . '%']);
            $existingNos = $stmtSeq->fetchAll(PDO::FETCH_COLUMN);
            $maxNum = 0;
            foreach ($existingNos as $no) {
                if (preg_match('/^INW-\d{2}-(\d+)$/', $no, $m)) {
                    $num = intval($m[1]);
                    if ($num > $maxNum) {
                        $maxNum = $num;
                    }
                }
            }
            $nextSeq = $maxNum + 1;
            $inwardNo = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        } else {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM rm_inward WHERE inward_no = ?");
            $checkStmt->execute([$inwardNo]);
            $exists = $checkStmt->fetch();
            if ($exists && $exists['cnt'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => "Inward No '{$inwardNo}' already exists."]);
                exit;
            }
        }

        $pdo->beginTransaction();

        $insertStmt = $pdo->prepare("
            INSERT INTO rm_inward (
                inward_no, inward_date, vendor_name, invoice_no, invoice_date,
                rm_code, rm_name, received_qty, uom,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE(), GETDATE())
        ");

        $firstInsertedId = null;
        foreach ($itemsList as $it) {
            $insertStmt->execute([
                $inwardNo,
                $inwardDate,
                $vendorName,
                $invoiceNo,
                $invoiceDate,
                $it['rm_code'],
                $it['rm_name'],
                $it['received_qty'],
                $it['uom']
            ]);

            if ($firstInsertedId === null) {
                $firstInsertedId = $pdo->lastInsertId();
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "RM Inward entry '{$inwardNo}' (" . count($itemsList) . " items) stored as separate rows in database.",
            'data' => [
                'id' => $firstInsertedId,
                'inward_no' => $inwardNo,
                'inward_date' => $inwardDate,
                'vendor_name' => $vendorName,
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'rm_code' => $summaryRmCode,
                'rm_name' => $summaryRmName,
                'received_qty' => $totalQty,
                'uom' => $primaryUom,
                'items_data' => $itemsJson,
                'items' => $itemsList
            ]
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
