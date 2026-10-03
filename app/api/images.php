<?php
/**
 * Image management API.
 *
 * GET  ?sublet_id=N                   list a listing's photos (the listing view's
 *                                     fallback; see listing_photos())
 * GET  ?action=inventory              every listing's photos, with sizes (admin)
 * GET  ?action=orphans                the orphan sweep's dry run (admin)
 * POST _method=DELETE id=N            delete one photo (admin or poster)
 * POST action=set_cover id=N          make a photo the card image (admin or poster)
 * POST action=move id=N dir=-1|1      move a photo earlier or later (admin or poster)
 * POST action=bulk_delete ids[]=...   delete several photos (admin)
 * POST action=delete_orphans names[]=... confirm=<count>   (admin)
 * POST action=make_thumbs skip[]=...  make missing thumbnails, a batch at a time (admin)
 *
 * Every change renumbers the listing's photos 0..n-1 and keeps the card image
 * on the first one (renumber_listing_photos()), and none may leave a listing
 * without a photo.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/thumbnail.php';
require_once __DIR__ . '/../../includes/image_admin.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function images_fail(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'inventory') {
        require_admin();
        echo json_encode(image_inventory($pdo));
        exit;
    }

    if ($action === 'orphans') {
        require_admin();
        echo json_encode(image_orphans($pdo));
        exit;
    }

    $subletId = $_GET['sublet_id'] ?? '';
    if (empty($subletId)) {
        echo json_encode([]);
        exit;
    }

    // The same order as listing_photos(), which the listing view uses first;
    // this is its fallback and has to agree with it.
    $stmt = $pdo->prepare("SELECT id, image_url, sort_order FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order, id");
    $stmt->execute([$subletId]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // app.js assigns these straight to img.src, so send URLs rather than the
    // page-relative stored paths — see image_src() in includes/db.php.
    // display_url is what the gallery shows; image_url stays the original.
    foreach ($images as &$image) {
        $urls = photo_urls($image['image_url']);
        $image['display_url'] = $urls['display'];
        $image['thumb_url'] = $urls['thumb'];
        $image['image_url'] = image_src($image['image_url']);
    }
    unset($image);

    echo json_encode($images);
    exit;
}

if ($method !== 'POST') {
    images_fail(405, 'Method not allowed');
}

require_same_origin();

$action = (isset($_POST['_method']) && $_POST['_method'] === 'DELETE') ? 'delete' : ($_POST['action'] ?? '');

// One photo, changed by the admin or by the poster of its listing.
if (in_array($action, ['delete', 'set_cover', 'move'], true)) {
    $image = photo_with_owner($pdo, (int)($_POST['id'] ?? 0));
    if (!$image) {
        images_fail(404, 'Image not found');
    }
    if (!is_admin() && get_current_user_id() !== $image['owner']) {
        images_fail(403, 'Access denied');
    }
    $subletId = (int)$image['sublet_id'];

    $stmt = $pdo->prepare('SELECT id FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order, id');
    $stmt->execute([$subletId]);
    $order = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($action === 'delete') {
        // Refuse to remove the only remaining photo. Deleting it left the
        // listing live with sublets.image_url and thumbnail_url pointing at
        // files that no longer exist, and nothing to promote in its place.
        if (count($order) <= 1) {
            images_fail(409, 'That is the only photo on this listing. Add another one first, or delete the whole listing.');
        }
        delete_image_files($image['image_url']);
        $pdo->prepare('DELETE FROM sublet_images WHERE id = ?')->execute([$image['id']]);
        // Renumbering also moves the card image (and its thumbnail) to the new
        // first photo when the deleted one was the cover.
        renumber_listing_photos($pdo, $subletId);
        echo json_encode(['success' => true]);
        exit;
    }

    $position = array_search((int)$image['id'], $order, true);
    if ($action === 'set_cover') {
        array_splice($order, $position, 1);
        array_unshift($order, (int)$image['id']);
    } else {
        $dir = (int)($_POST['dir'] ?? 0);
        $target = $position + ($dir < 0 ? -1 : 1);
        if ($dir === 0 || $target < 0 || $target >= count($order)) {
            images_fail(400, 'That photo cannot move that way.');
        }
        [$order[$position], $order[$target]] = [$order[$target], $order[$position]];
    }
    renumber_listing_photos($pdo, $subletId, $order);
    echo json_encode(['success' => true]);
    exit;
}

// Everything below is the admin's.
require_admin();

if ($action === 'bulk_delete') {
    $ids = array_values(array_unique(array_map('intval', (array)($_POST['ids'] ?? []))));
    if (!$ids) {
        images_fail(400, 'No photos selected.');
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, sublet_id, image_url FROM sublet_images WHERE id IN ($in)");
    $stmt->execute($ids);
    $byListing = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byListing[(int)$row['sublet_id']][(int)$row['id']] = $row['image_url'];
    }

    $deleted = 0;
    $kept = [];
    $count = $pdo->prepare('SELECT id FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order, id');
    foreach ($byListing as $subletId => $selected) {
        $count->execute([$subletId]);
        $all = array_map('intval', $count->fetchAll(PDO::FETCH_COLUMN));
        // Never a listing's last photo: if every one of them is selected, the
        // first (the cover) stays.
        if (count($selected) >= count($all)) {
            $keep = $all[0];
            unset($selected[$keep]);
            $kept[] = $keep;
        }
        foreach ($selected as $imageId => $stored) {
            delete_image_files($stored);
            $pdo->prepare('DELETE FROM sublet_images WHERE id = ?')->execute([$imageId]);
            $deleted++;
        }
        renumber_listing_photos($pdo, $subletId);
    }
    echo json_encode(['success' => true, 'deleted' => $deleted, 'kept' => $kept]);
    exit;
}

if ($action === 'delete_orphans') {
    $names = array_values(array_unique(array_map('strval', (array)($_POST['names'] ?? []))));
    // The typed confirmation is the number of files in the list being
    // deleted, so it is the list the admin actually looked at.
    if (!$names || (string)count($names) !== trim((string)($_POST['confirm'] ?? ''))) {
        images_fail(409, 'Type the number of files to confirm.');
    }
    [$deleted, $kept] = delete_orphans($pdo, $names);
    echo json_encode(['success' => true, 'deleted' => count($deleted), 'kept' => $kept]);
    exit;
}

if ($action === 'make_thumbs') {
    @set_time_limit(120);
    $skip = array_map('strval', (array)($_POST['skip'] ?? []));
    echo json_encode(['success' => true] + make_missing_thumbnails($pdo, $skip));
    exit;
}

images_fail(400, 'Invalid action');
