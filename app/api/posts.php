<?php
/**
 * Post management API (admin only).
 * POST action=delete   &id=N        → delete a post
 * POST action=delete_user &username=X → delete all posts by user
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/thumbnail.php';

header('Content-Type: application/json');
require_same_origin();
require_admin();

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing post ID']);
        exit;
    }

    // Get images to delete files
    $stmt = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ?");
    $stmt->execute([$id]);
    $imageFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get thumbnail
    $stmtThumb = $pdo->prepare("SELECT image_url, thumbnail_url, username FROM sublets WHERE id = ?");
    $stmtThumb->execute([$id]);
    $post = $stmtThumb->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Post not found']);
        exit;
    }

    // Delete image files (and their generated thumbnails)
    foreach ($imageFiles as $file) {
        delete_image_files($file);
    }
    delete_image_files($post['image_url']);
    delete_image_files($post['thumbnail_url']);

    // Delete from DB (cascade will handle sublet_images)
    $pdo->prepare("DELETE FROM sublets WHERE id = ?")->execute([$id]);

    mail('aperkel@uvm.edu', 'Sublet Post Deleted (Admin)', "Admin deleted post #{$id} by {$post['username']}.");

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_user') {
    $username = $_POST['username'] ?? '';
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing username']);
        exit;
    }

    // Get all posts by user
    $stmt = $pdo->prepare("SELECT id, image_url, thumbnail_url FROM sublets WHERE username = ?");
    $stmt->execute([$username]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtImg = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ?");
    foreach ($posts as $post) {
        // Delete images (and their generated thumbnails)
        $stmtImg->execute([$post['id']]);
        foreach ($stmtImg->fetchAll(PDO::FETCH_COLUMN) as $file) {
            delete_image_files($file);
        }
        delete_image_files($post['image_url']);
        delete_image_files($post['thumbnail_url']);
    }

    $pdo->prepare("DELETE FROM sublets WHERE username = ?")->execute([$username]);

    echo json_encode(['success' => true, 'deleted' => count($posts)]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
