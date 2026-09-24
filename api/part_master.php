<?php
/**
 * WIP Management Portal - Part Master API Endpoint (PHP + MSSQL)
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

// Handle GET request - Fetch all Part records
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, part_code, part_name, child_parts, status, created_at FROM part_master ORDER BY id DESC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    // Read input (JSON or form-data)
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = trim($data['action'] ?? 'create');

    // 1. DELETE ACTION
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        $partCode = trim($data['part_code'] ?? '');

        if ($id <= 0 && empty($partCode)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Part ID or Part Code is required for deletion.']);
            exit;
        }

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM part_master WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM part_master WHERE part_code = ?");
                $stmt->execute([$partCode]);
            }

            echo json_encode(['success' => true, 'message' => 'Part deleted successfully.']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // Common fields
    $partCode = trim($data['part_code'] ?? '');
    $partName = trim($data['part_name'] ?? '');
    $status = trim($data['status'] ?? 'Active');
    
    // Child parts handling (array or JSON string)
    $childPartsRaw = $data['child_parts'] ?? [];
    if (is_array($childPartsRaw)) {
        $childPartsJson = json_encode($childPartsRaw);
    } else {
        $childPartsJson = trim($childPartsRaw);
    }

    if (empty($status)) {
        $status = 'Active';
    }

    // Validation
    if (empty($partCode) || empty($partName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Part Code and Part Name are required.']);
        exit;
    }

    // 2. UPDATE ACTION
    if ($action === 'update') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Valid ID is required for update.']);
            exit;
        }

        try {
            // Check if Part Code is taken by another row
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM part_master WHERE part_code = ? AND id != ?");
            $checkStmt->execute([$partCode, $id]);
            $exists = $checkStmt->fetch();
            if ($exists && $exists['cnt'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => "Part Code '{$partCode}' is already in use by another part."]);
                exit;
            }

            $updateStmt = $pdo->prepare("
                UPDATE part_master 
                SET part_code = ?, part_name = ?, child_parts = ?, status = ?, updated_at = GETDATE()
                WHERE id = ?
            ");
            $updateStmt->execute([$partCode, $partName, $childPartsJson, $status, $id]);

            echo json_encode([
                'success' => true,
                'message' => "Part '{$partCode}' updated successfully.",
                'data' => [
                    'id' => $id,
                    'part_code' => $partCode,
                    'part_name' => $partName,
                    'child_parts' => $childPartsJson,
                    'status' => $status
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // 3. CREATE ACTION
    try {
        // Check for duplicate Part Code
        $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM part_master WHERE part_code = ?");
        $checkStmt->execute([$partCode]);
        $exists = $checkStmt->fetch();
        if ($exists && $exists['cnt'] > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "Part Code '{$partCode}' already exists."]);
            exit;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO part_master (part_code, part_name, child_parts, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, GETDATE(), GETDATE())
        ");
        $insertStmt->execute([$partCode, $partName, $childPartsJson, $status]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => "Part '{$partCode}' created successfully.",
            'data' => [
                'id' => $newId,
                'part_code' => $partCode,
                'part_name' => $partName,
                'child_parts' => $childPartsJson,
                'status' => $status
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
