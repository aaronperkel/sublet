<?php
/**
 * Semester archive API (admin only). See includes/archive.php.
 *
 * GET  ?action=preview&code=X              the dry run; changes nothing
 * POST action=archive  code=X confirm=X    archive a hidden semester
 * POST action=delete_tarball name=...      delete one archive tarball
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/archive.php';

header('Content-Type: application/json');
require_admin();
require_same_origin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

if ($method === 'GET' && $action === 'preview') {
    $code = trim((string)($_GET['code'] ?? ''));
    echo json_encode(archive_plan_public(archive_plan($pdo, $code)));
    exit;
}

if ($method === 'POST' && $action === 'archive') {
    // Tarring and deleting a semester's photos takes a while, and stopping
    // halfway because the tab was closed would be worse than finishing.
    @set_time_limit(300);
    ignore_user_abort(true);

    $result = archive_execute(
        $pdo,
        trim((string)($_POST['code'] ?? '')),
        trim((string)($_POST['confirm'] ?? '')),
        get_current_user_id()
    );
    if (isset($result['error'])) {
        http_response_code(409);
    }
    echo json_encode($result);
    exit;
}

if ($method === 'POST' && $action === 'delete_tarball') {
    $name = (string)($_POST['name'] ?? '');
    if (!archive_delete_tarball($name)) {
        http_response_code(404);
        echo json_encode(['error' => 'No archive tarball by that name.']);
        exit;
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
