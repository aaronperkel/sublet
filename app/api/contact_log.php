<?php
/**
 * Contact logging API.
 * POST: Logs when a user clicks contact on a post.
 * GET: Returns contact logs (admin only).
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin();

    $postId = (int)($_POST['post_id'] ?? 0);
    $contactType = $_POST['contact_type'] ?? '';
    $contactedBy = get_current_user_id() ?: 'anonymous';

    // contact_type is an ENUM('email','phone') in the schema — anything else
    // would raise a PDO exception rather than being quietly dropped.
    if ($postId && in_array($contactType, ['email', 'phone'], true)) {
        // Take the poster from the post itself. It used to come from the
        // request body, so the log could be filled with attributions to
        // people who never posted.
        $stmtPoster = $pdo->prepare("SELECT username FROM sublets WHERE id = ?");
        $stmtPoster->execute([$postId]);
        $posterUsername = $stmtPoster->fetchColumn();

        if ($posterUsername) {
            $stmt = $pdo->prepare("INSERT INTO contact_logs (post_id, poster_username, contacted_by, contact_type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $posterUsername, $contactedBy, $contactType]);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $total = $pdo->query("SELECT COUNT(*) FROM contact_logs")->fetchColumn();

    $stmt = $pdo->prepare("SELECT cl.*, s.address FROM contact_logs cl LEFT JOIN sublets s ON cl.post_id = s.id ORDER BY cl.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'logs' => $logs,
        'total' => (int)$total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
