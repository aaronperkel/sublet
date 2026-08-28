<?php
/**
 * Access allowlist API (admin only).
 * POST action=add     → add a uid to the allow or block list
 * POST action=remove  → remove a uid from either list
 * POST action=rebuild → regenerate app/.htaccess from the table, changing no rows
 *
 * The allowed_users table is the source of truth; app/.htaccess is derived from
 * it and rewritten after every change. Because the file is what Apache actually
 * enforces, a row that saved but failed to reach the file would be a lie — the
 * portal would show access that nobody has. So each mutation below undoes its
 * own database change if write_htaccess_block() reports failure, leaving the
 * table and the file agreeing either way.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/htaccess_allowlist.php';

header('Content-Type: application/json');
require_same_origin();
require_admin();

if (!table_exists($pdo, 'allowed_users')) {
    http_response_code(503);
    echo json_encode(['error' => 'The allowed_users table does not exist yet. Create it first — the Access tab shows the SQL.']);
    exit;
}

/**
 * The two lists as the generator wants them.
 */
function allowlist_current(PDO $pdo): array {
    $lists = ['allow' => [], 'block' => []];
    foreach ($pdo->query("SELECT uid, kind FROM allowed_users") as $row) {
        if (isset($lists[$row['kind']])) {
            $lists[$row['kind']][] = $row['uid'];
        }
    }
    return $lists;
}

/**
 * Regenerate app/.htaccess from whatever the table currently says.
 */
function allowlist_sync(PDO $pdo, ?string &$error): bool {
    $lists = allowlist_current($pdo);
    return write_htaccess_block($lists['allow'], $lists['block'], $error);
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    // Netids are case-insensitive in practice and people type them either way,
    // so normalise before validating. Anything still invalid after that is
    // rejected outright rather than repaired — see valid_uid().
    $uid = strtolower(trim($_POST['uid'] ?? ''));
    $kind = $_POST['kind'] ?? 'allow';
    $note = trim($_POST['note'] ?? '');

    if ($kind !== 'allow' && $kind !== 'block') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid list.']);
        exit;
    }

    if (!valid_uid($uid)) {
        http_response_code(400);
        echo json_encode(['error' => 'That is not a valid netid. Use lowercase letters and digits, starting with a letter (e.g. ocongdon).']);
        exit;
    }

    if ($kind === 'block' && $uid === ADMIN_UID) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot block your own account.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT kind FROM allowed_users WHERE uid = ?");
    $stmt->execute([$uid]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        http_response_code(409);
        echo json_encode(['error' => $existing === $kind
            ? $uid . ' is already on this list.'
            : $uid . ' is currently on the ' . $existing . ' list. Remove it there first.']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO allowed_users (uid, kind, note, added_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $kind, $note !== '' ? $note : null, get_current_user_id()]);
    $newId = (int)$pdo->lastInsertId();

    $error = null;
    if (!allowlist_sync($pdo, $error)) {
        // Undo the insert so the table never claims access the file does not grant.
        $pdo->prepare("DELETE FROM allowed_users WHERE id = ?")->execute([$newId]);
        http_response_code(500);
        echo json_encode(['error' => $error]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove') {
    $id = $_POST['id'] ?? '';
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT uid, kind, note, added_by FROM allowed_users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'That entry no longer exists.']);
        exit;
    }

    if ($row['uid'] === ADMIN_UID) {
        // build_require_line() force-adds ADMIN_UID regardless, so removing the
        // row would not actually revoke anything — it would just make the list
        // disagree with the file. Refuse, rather than show a no-op.
        http_response_code(400);
        echo json_encode(['error' => 'Your own account is always allowed and cannot be removed.']);
        exit;
    }

    $pdo->prepare("DELETE FROM allowed_users WHERE id = ?")->execute([$id]);

    $error = null;
    if (!allowlist_sync($pdo, $error)) {
        // Put the row back. The id will differ, which is fine — nothing
        // references it, and the uid is what the generated file is keyed on.
        $pdo->prepare("INSERT INTO allowed_users (uid, kind, note, added_by) VALUES (?, ?, ?, ?)")
            ->execute([$row['uid'], $row['kind'], $row['note'], $row['added_by']]);
        http_response_code(500);
        echo json_encode(['error' => $error]);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'rebuild') {
    // Changes no rows: this is the resync for when app/.htaccess has drifted
    // from the table, which mainly happens after a git checkout reverts it.
    $error = null;
    if (!allowlist_sync($pdo, $error)) {
        http_response_code(500);
        echo json_encode(['error' => $error]);
        exit;
    }

    echo json_encode(['success' => true, 'rule' => read_managed_require_line()]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
