<?php
/**
 * Semester management API (admin only).
 * POST action=add     → add new semester
 * POST action=toggle  → toggle active status
 * POST action=delete  → delete semester (only if no posts)
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
require_same_origin();
require_admin();

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (empty($code) || empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Code and name are required']);
        exit;
    }

    // Check duplicate
    $stmt = $pdo->prepare("SELECT id FROM semesters WHERE code = ?");
    $stmt->execute([$code]);
    if ($stmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Semester code already exists']);
        exit;
    }

    // Get max sort order
    $maxOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM semesters")->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO semesters (code, name, active, sort_order) VALUES (?, ?, 1, ?)");
    $stmt->execute([$code, $name, $maxOrder + 1]);

    echo json_encode([
        'success' => true,
        'semester' => [
            'id' => $pdo->lastInsertId(),
            'code' => $code,
            'name' => $name,
            'active' => 1,
            'post_count' => 0
        ]
    ]);
    exit;
}

if ($action === 'toggle') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing semester ID']);
        exit;
    }

    $pdo->prepare("UPDATE semesters SET active = NOT active WHERE id = ?")->execute([$id]);

    $stmt = $pdo->prepare("SELECT active FROM semesters WHERE id = ?");
    $stmt->execute([$id]);
    $newState = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'active' => (bool)$newState]);
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing semester ID']);
        exit;
    }

    // Check if any posts use this semester
    $stmt = $pdo->prepare("SELECT code FROM semesters WHERE id = ?");
    $stmt->execute([$id]);
    $code = $stmt->fetchColumn();

    if ($code) {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM sublets WHERE semester = ?");
        $stmtCount->execute([$code]);
        if ($stmtCount->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Cannot delete: semester has active posts']);
            exit;
        }
    }

    $pdo->prepare("DELETE FROM semesters WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
