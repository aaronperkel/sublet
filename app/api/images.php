<?php
/**
 * Image management API.
 * GET    ?sublet_id=N      → list images for a sublet
 * DELETE ?id=N             → delete a single image (admin or post owner)
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/thumbnail.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $subletId = $_GET['sublet_id'] ?? '';
    if (empty($subletId)) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, image_url, sort_order FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order");
    $stmt->execute([$subletId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE') {
    require_same_origin();

    // Delete image — admin or post owner
    $imageId = $_POST['id'] ?? '';
    if (empty($imageId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing image ID']);
        exit;
    }

    // Get image info with post owner
    $stmt = $pdo->prepare("SELECT si.*, s.image_url as thumbnail, s.id as sublet_id, s.username as owner FROM sublet_images si JOIN sublets s ON si.sublet_id = s.id WHERE si.id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$image) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }

    // Check authorization: must be admin or the post owner
    if (!is_admin() && get_current_user_id() !== $image['owner']) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    // Delete the file and its generated thumbnail
    delete_image_files($image['image_url']);

    // Delete from DB
    $pdo->prepare("DELETE FROM sublet_images WHERE id = ?")->execute([$imageId]);

    // If this was the card image, promote the next one in its place.
    if ($image['image_url'] === $image['thumbnail']) {
        $stmtNext = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order LIMIT 1");
        $stmtNext->execute([$image['sublet_id']]);
        $nextImage = $stmtNext->fetchColumn();

        if ($nextImage) {
            // thumbnail_url has to move too. Updating only image_url left it
            // pointing at the deleted image's _thumb.webp, so the listing card
            // rendered the broken-image placeholder.
            $thumbFs = make_thumbnail(resolve_path($nextImage));
            $thumbUrl = './public/images/' . basename($thumbFs);
            $pdo->prepare("UPDATE sublets SET image_url = ?, thumbnail_url = ? WHERE id = ?")
                ->execute([$nextImage, $thumbUrl, $image['sublet_id']]);
        }
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
