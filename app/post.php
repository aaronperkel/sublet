<?php
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/thumbnail.php';
require_once '../includes/share.php';
require_once '../includes/events.php';
require_once '../includes/notify.php';

$username = get_current_user_id();
if (!$username) {
    header("Location: index.php");
    exit;
}

$error_message = '';
$success_message = '';
$skippedUploads = 0; // uploads rejected by safe_image_extension()

// Which of the newer listing columns this database actually has. The form hides
// the fields it cannot store, and the writers below skip them, so the page works
// whether or not the schema change has been applied yet.
$subletColumns = table_columns($pdo, 'sublets');

// Check if user already has a post
$stmtCheck = $pdo->prepare("SELECT * FROM sublets WHERE username = ?");
$stmtCheck->execute([$username]);
$existingPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);
$isEdit = (bool)$existingPost;

/**
 * The semester pills for the form, and the listing's own semesters.
 *
 * Every open semester, in calendar order, plus any the listing already has
 * that has since been deactivated: those stay in the row, ticked and marked
 * "(closed)", so saving never silently drops one. A listing is for one or
 * more back-to-back semesters (see includes/semesters.php).
 *
 * Returns [$options, $listingSemesters], each option ['code', 'name',
 * 'closed', 'key'].
 */
function post_semester_options(PDO $pdo, ?array $post): array {
    $open = $pdo->query("SELECT code, name, sort_order FROM semesters WHERE active = 1")->fetchAll(PDO::FETCH_ASSOC);
    // No semesters configured at all: offer the codes listings already use.
    if (!$open) {
        $open = $pdo->query("SELECT DISTINCT semester_code AS code, semester_code AS name, 0 AS sort_order FROM sublet_semesters")->fetchAll(PDO::FETCH_ASSOC);
    }
    $options = [];
    foreach ($open as $row) {
        $options[$row['code']] = ['code' => $row['code'], 'name' => $row['name'], 'sort_order' => (int)$row['sort_order'], 'closed' => false];
    }

    $mine = [];
    if ($post) {
        $mine = listing_semesters($pdo, [(int)$post['id']])[(int)$post['id']] ?? [];
        if (!$mine) {
            $mine = [['code' => $post['semester'], 'name' => $post['semester'], 'open' => true]];
        }
        foreach ($mine as $s) {
            // A code with no semesters row still counts as open (visibility.php);
            // only a deactivated one is "closed".
            $options[$s['code']] ??= ['code' => $s['code'], 'name' => $s['name'], 'sort_order' => 0, 'closed' => !$s['open']];
        }
    }
    $options = array_map(static fn($o) => $o + ['key' => semester_key($o['name'])], sort_semesters(array_values($options)));
    return [$options, $mine];
}

[$semesterOptions, $listingSemesters] = post_semester_options($pdo, $isEdit ? $existingPost : null);

// Hidden when every semester the listing has is closed. One closed semester
// next to an open one leaves it up.
$closedSemesters = array_values(array_filter($listingSemesters, static fn($s) => !$s['open']));
$listingHidden = $isEdit && $listingSemesters && count($closedSemesters) === count($listingSemesters);

// Pause, resume or mark taken. Each is a small form of its own above the
// listing form, posting action=status. POST only and same-origin for the
// reason Delete is below. It redirects back, so a reload cannot post it again
// and the banner comes from the status as saved.
$postAction = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $postAction === 'status') {
    require_same_origin();
    $newStatus = (string)($_POST['status'] ?? '');
    $oldStatus = (string)($existingPost['status'] ?? 'open');
    if ($isEdit && set_listing_status($pdo, (int)$existingPost['id'], $newStatus)) {
        if ($newStatus !== $oldStatus) {
            admin_notify(admin_notice_status(listing_snapshot($pdo, (int)$existingPost['id']), $oldStatus, $newStatus));
        }
        header('Location: post.php?status=' . rawurlencode($newStatus), true, 303);
        exit;
    }
    $error_message = 'That change could not be made. Reload the page and try again.';
}

// Handle delete action. POST only, and same-origin: as a GET this could be
// triggered by any other site simply embedding <img src=".../post.php?action=
// delete">, silently destroying a signed-in user's listing.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && $isEdit) {
    require_same_origin();

    // What the listing was, for the admin's notice, before it goes.
    $deleted = listing_snapshot($pdo, (int)$existingPost['id']);

    // Delete image files
    $stmtImages = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ?");
    $stmtImages->execute([$existingPost['id']]);
    foreach ($stmtImages->fetchAll(PDO::FETCH_COLUMN) as $file) {
        delete_image_files($file);
    }
    delete_image_files($existingPost['image_url']);
    delete_image_files($existingPost['thumbnail_url']);

    $pdo->prepare("DELETE FROM sublets WHERE id = ?")->execute([$existingPost['id']]);

    if ($deleted) {
        admin_notify(admin_notice_deleted($deleted, 'poster'));
    }

    header("Location: index.php");
    exit;
}

// A request larger than post_max_size reaches PHP with $_POST and $_FILES both
// empty and no error of its own to inspect. Detect it before the handler below
// reads every field as blank: it would fall through to the distance check and
// report "more than 50 miles from campus" (lat/lon default to 0), and on an
// edit it would otherwise try to save a listing with every field cleared.
$postTooLarge = $_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

if ($postTooLarge) {
    $limit = ini_get('post_max_size');
    $error_message = 'Those photos are too large to upload at once'
        . ($limit ? " (the server accepts up to $limit per submission)" : '')
        . '. Try adding a few at a time, or resizing them first. Nothing was changed.';
}

// Handle form submission. A status change is handled above, and must not fall
// through here: this would save the listing from a form that has no fields.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$postTooLarge && $postAction !== 'status') {
    require_same_origin();

    $price = $_POST['price'] ?? '';
    $address = trim($_POST['address'] ?? '');

    // The semesters ticked, checked against the pills this page offered (open
    // semesters, and closed ones the listing already has), in calendar order.
    $requested = array_values(array_unique(array_filter(array_map('strval', (array)($_POST['semesters'] ?? [])), 'strlen')));
    $chosenSemesters = array_values(array_filter($semesterOptions, static fn($o) => in_array($o['code'], $requested, true)));
    $semesterError = '';
    if (!$chosenSemesters) {
        $semesterError = 'Pick the semester your place is available for.';
    } elseif (count($chosenSemesters) !== count($requested)) {
        $semesterError = "One of those semesters isn't open for sublets. Reload the page and pick again.";
    } elseif (!semesters_back_to_back(array_column($chosenSemesters, 'name'))) {
        $semesterError = 'Semesters have to be back to back, like Summer and Fall. For a later one with a gap, post again once this sublet is over.';
    }
    $semester = $chosenSemesters[0]['code'] ?? '';
    $lat = (float)($_POST['lat'] ?? 0);
    $lon = (float)($_POST['lon'] ?? 0);
    $description = $_POST['description'] ?? '';
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    // Every column written below except the image ones, so the two statements
    // can be assembled from a single list rather than two hand-kept orders.
    $fields = [
        'price' => $price,
        'address' => $address,
        'semester' => $semester,
        'lat' => $lat,
        'lon' => $lon,
        'description' => $description,
        'contact_email' => $contact_email,
        'contact_phone' => $contact_phone,
        'utility_electric' => $_POST['utility_electric'] ?? '',
        'utility_gas' => $_POST['utility_gas'] ?? '',
        'utility_water' => $_POST['utility_water'] ?? '',
        'utility_internet' => $_POST['utility_internet'] ?? '',
        'utility_cost' => ($_POST['utility_cost'] ?? '') !== '' ? (float)$_POST['utility_cost'] : null,
        'amenity_free_parking' => isset($_POST['amenity_free_parking']) ? 1 : 0,
        'amenity_paid_parking' => isset($_POST['amenity_paid_parking']) ? 1 : 0,
        'amenity_laundry_free' => isset($_POST['amenity_laundry_free']) ? 1 : 0,
        'amenity_laundry_paid' => isset($_POST['amenity_laundry_paid']) ? 1 : 0,
        'amenity_dishwasher' => isset($_POST['amenity_dishwasher']) ? 1 : 0,
        'amenity_air_conditioning' => isset($_POST['amenity_air_conditioning']) ? 1 : 0,
        'amenity_pets_allowed' => isset($_POST['amenity_pets_allowed']) ? 1 : 0,
        'amenity_furnished' => isset($_POST['amenity_furnished']) ? 1 : 0,
    ];

    // Place & roommate details. These columns are newer than some deployments
    // of this file, so each is written only if the database actually has it —
    // naming a missing column in an INSERT is a fatal error on a live site.
    $roommates = optional_count($_POST['roommates'] ?? '', 0, 20);
    $optionalFields = [
        // Blank is meaningful: it means "just use my NetID" (see poster_name()).
        'display_name' => mb_substr(trim($_POST['display_name'] ?? ''), 0, 60),
        'price_negotiable' => isset($_POST['price_negotiable']) ? 1 : 0,
        'bedrooms' => optional_count($_POST['bedrooms'] ?? '', 0, 20),
        'bathrooms' => optional_bathrooms($_POST['bathrooms'] ?? ''),
        'roommates' => $roommates,
        // Nobody to describe or prefer if the subletter would have the place to
        // themselves; clear both rather than storing a contradiction.
        'roommate_gender' => $roommates === 0 ? '' : sanitize_option(ROOMMATE_GENDER_OPTIONS, $_POST['roommate_gender'] ?? ''),
        'roommate_preference' => $roommates === 0 ? '' : sanitize_option(ROOMMATE_PREFERENCE_OPTIONS, $_POST['roommate_preference'] ?? ''),
    ];
    foreach ($optionalFields as $col => $value) {
        if (isset($subletColumns[$col])) {
            $fields[$col] = $value;
        }
    }

    // Coordinates only ever come from picking a geocoder suggestion. Without
    // that the listing has no map position, and the distance check below would
    // measure from (0, 0) in the Atlantic and blame the address.
    if ($lat === 0.0 && $lon === 0.0) {
        $error_message = "Pick your address from the suggestions, or tap your place on the map, so your listing lands in the right spot.";
    }

    // Validate distance from campus
    $dLat = deg2rad($lat - CAMPUS_LAT);
    $dLon = deg2rad($lon - CAMPUS_LON);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad(CAMPUS_LAT)) * cos(deg2rad($lat)) * sin($dLon / 2) ** 2;
    $distance = 3959 * 2 * asin(sqrt($a));

    if (!$error_message && $distance > 50) {
        $error_message = "The location is more than 50 miles from campus.";
    }

    // For new posts, require at least one image
    if (!$isEdit && empty($_FILES['images']['name'][0])) {
        $error_message = "Please upload at least one image.";
    }

    if (!$error_message && $semesterError !== '') {
        $error_message = $semesterError;
    }

    if (empty($error_message)) {
        $fs_dir = ROOT_DIR . "/public/images/";
        $url_prefix = "./public/images/";

        if ($isEdit) {
            // Update existing post. Column names come only from the $fields keys
            // built above — all literals in this file, never request data.
            $assignments = implode(', ', array_map(
                static fn($col) => "`$col` = ?",
                array_keys($fields)
            ));
            // The listing as it was, so the admin's notice can say what changed.
            $beforeSave = listing_snapshot($pdo, (int)$existingPost['id']);
            $photosAdded = 0;

            $sql = "UPDATE sublets SET $assignments WHERE username = ?";
            $pdo->prepare($sql)->execute([...array_values($fields), $username]);
            $subletId = $existingPost['id'];
            set_listing_semesters($pdo, (int)$subletId, array_column($chosenSemesters, 'code'), $semester);

            // Process new images if uploaded
            if (!empty($_FILES['images']['name'][0])) {
                $stmtMax = $pdo->prepare("SELECT MAX(sort_order) FROM sublet_images WHERE sublet_id = ?");
                $stmtMax->execute([$subletId]);
                $maxOrder = (int)$stmtMax->fetchColumn();

                $stmtImage = $pdo->prepare("INSERT INTO sublet_images (sublet_id, image_url, sort_order) VALUES (?, ?, ?)");
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    // Extension comes from the file's own bytes, never its name.
                    $ext = safe_image_extension($_FILES['images']['tmp_name'][$i]);
                    if ($ext === null) {
                        $skippedUploads++;
                        continue;
                    }
                    $newOrder = $maxOrder + $i + 1;
                    $fsTarget = $fs_dir . new_upload_name($ext);
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $fsTarget)) {
                        $fsTarget = ensure_browser_safe($fsTarget);
                        // Every photo gets both copies: the display size for
                        // the gallery, and a thumbnail, which the gallery shows
                        // while the display copy loads and the admin Images tab
                        // lays out (see listing_photos()).
                        make_display_image($fsTarget);
                        make_thumbnail($fsTarget);
                        $urlTarget = $url_prefix . basename($fsTarget);
                        $stmtImage->execute([$subletId, $urlTarget, $newOrder]);
                        $photosAdded++;
                    }
                }
            }

            // Only what changed; a save that changed nothing sends nothing.
            if ($beforeSave) {
                admin_notify(admin_notice_updated($beforeSave, listing_snapshot($pdo, (int)$subletId), $photosAdded));
            }
            $success_message = "Your listing has been updated!";

            // Refresh post data
            $stmtCheck->execute([$username]);
            $existingPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            // Saving does not change the status, so say so: someone who edits a
            // paused listing may expect the save to put it back up.
            $savedStatus = $existingPost['status'] ?? 'open';
            if ($savedStatus === 'paused') {
                $success_message .= ' It\'s still paused, so it stays off Browse until you resume it.';
            } elseif ($savedStatus === 'taken') {
                $success_message .= ' It\'s still marked taken, so it stays off Browse.';
            }
        } else {
            // Create new post. Extension comes from the file's own bytes, never
            // its name — public/images/ is web-served, so a .php upload there
            // would be executable.
            $ext = safe_image_extension($_FILES['images']['tmp_name'][0]);
            $fsTarget = $ext === null ? '' : $fs_dir . new_upload_name($ext);

            if ($ext === null) {
                $error_message = "That file isn't a supported image. Please upload a JPEG, PNG, GIF, WebP, or HEIC photo.";
            } elseif (!move_uploaded_file($_FILES['images']['tmp_name'][0], $fsTarget)) {
                $error_message = "Error uploading image.";
            } else {
                $fsTarget = ensure_browser_safe($fsTarget);
                make_display_image($fsTarget);
                $urlTarget = $url_prefix . basename($fsTarget);
                $thumbWebp = make_thumbnail($fsTarget);
                $urlThumb = $url_prefix . basename($thumbWebp);

                $insert = array_merge([
                    'image_url' => $urlTarget,
                    'thumbnail_url' => $urlThumb,
                    'username' => $username,
                ], $fields);

                $sql = "INSERT INTO sublets ("
                    . implode(', ', array_map(static fn($col) => "`$col`", array_keys($insert)))
                    . ") VALUES (" . implode(', ', array_fill(0, count($insert), '?')) . ")";
                $pdo->prepare($sql)->execute(array_values($insert));
                $subletId = $pdo->lastInsertId();
                set_listing_semesters($pdo, (int)$subletId, array_column($chosenSemesters, 'code'), $semester);

                // Insert all images
                $stmtImage = $pdo->prepare("INSERT INTO sublet_images (sublet_id, image_url, sort_order) VALUES (?, ?, ?)");
                $stmtImage->execute([$subletId, $urlTarget, 0]);

                for ($i = 1; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $ext = safe_image_extension($_FILES['images']['tmp_name'][$i]);
                    if ($ext === null) {
                        $skippedUploads++;
                        continue;
                    }
                    $fsT = $fs_dir . new_upload_name($ext);
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $fsT)) {
                        $fsT = ensure_browser_safe($fsT);
                        // Both copies, as above.
                        make_display_image($fsT);
                        make_thumbnail($fsT);
                        $urlT = $url_prefix . basename($fsT);
                        $stmtImage->execute([$subletId, $urlT, $i]);
                    }
                }

                admin_notify(admin_notice_created(listing_snapshot($pdo, (int)$subletId)));
                $success_message = "Your listing has been posted!";
                $isEdit = true;
                $stmtCheck->execute([$username]);
                $existingPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}

// Tell the user when files were dropped rather than silently ignoring them.
// The note belongs to exactly one banner: appending it to a success message
// *and* falling through to the error branch showed it twice, in two different
// colours, for what is one event.
if ($skippedUploads > 0) {
    $note = $skippedUploads . ' file' . ($skippedUploads === 1 ? ' was' : 's were')
        . ' skipped because they are not images (JPEG, PNG, GIF, WebP, or HEIC only).';
    if ($success_message) {
        $success_message .= ' ' . $note;
    } else {
        $error_message = $error_message ?: $note;
    }
}

// After a save the listing's semesters may have changed, so the pills and the
// hidden notice are rebuilt from what was stored.
if ($isEdit && $success_message !== '') {
    [$semesterOptions, $listingSemesters] = post_semester_options($pdo, $existingPost);
    $closedSemesters = array_values(array_filter($listingSemesters, static fn($s) => !$s['open']));
    $listingHidden = $listingSemesters && count($closedSemesters) === count($listingSemesters);
}

// The share message names the semesters the way Browse does: the open ones,
// as one label ("Summer & Fall 2027").
$shareSemesterName = '';
if (!empty($existingPost['id'])) {
    $shareSemesterName = with_semester_labels($pdo, [$existingPost + ['semester_name' => $existingPost['semester']]], true)[0]['semester_name'];
}

// What the form shows. After a failed save that is what the student just
// submitted: rendering from the database instead (or from nothing, for a new
// listing) threw away a whole form over one bad field. Otherwise it is the
// saved listing when editing, and empty for a new one. Every value is escaped
// where it is printed. Unchecked boxes are simply absent from $_POST, which is
// why this replaces $existingPost rather than merging into it.
$formFailed = $_SERVER['REQUEST_METHOD'] === 'POST' && !$postTooLarge && $error_message !== '' && $postAction !== 'status';
$form = $isEdit ? $existingPost : [];
if ($formFailed) {
    $form = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $_POST);

    // Coordinates go back only as numbers. They are what tells app.js the
    // address was picked from the suggestions rather than typed.
    foreach (['lat', 'lon'] as $coord) {
        if (!is_numeric($form[$coord] ?? null) || (float)$form[$coord] === 0.0) {
            $form['lat'] = $form['lon'] = '';
            break;
        }
    }

    // A browser cannot be handed files back, so say so rather than letting
    // the student find out at the next submit.
    if (!empty($_FILES['images']['name'][0])) {
        $error_message .= ' Your photos weren\'t saved, so please add them again.';
    }
}
// Which semester pills start ticked: what was just submitted after a failed
// save, otherwise the listing's own semesters.
$checkedSemesters = $formFailed
    ? array_map('strval', (array)($_POST['semesters'] ?? []))
    : array_column($listingSemesters, 'code');

$formHasLocation = is_numeric($form['lat'] ?? null) && is_numeric($form['lon'] ?? null)
    && (float)$form['lat'] !== 0.0;

// The poster's own numbers, counted from the later of the day the activity log
// began and the day the listing went up, so a March listing does not claim
// zero views for months nobody was counting. People, not taps (see
// listing_activity()); their own views never count.
$myStats = null;
if ($isEdit && !empty($existingPost['id']) && table_exists($pdo, 'listing_events')) {
    $statsSince = max(LISTING_EVENTS_SINCE, substr((string)($existingPost['posted_at'] ?? ''), 0, 10));
    $mine = listing_activity($pdo, "e.listing_id = ? AND e.created_at >= ?", [(int)$existingPost['id'], $statsSince]);
    $myStats = ($mine[(int)$existingPost['id']] ?? ['views' => 0, 'contacts' => 0, 'shares' => 0]) + ['since' => $statsSince];
}

// Where the listing stands: open, paused or taken (see visibility.php), and
// what the status panel above the form offers from there. The panel's buttons
// are separate forms, so none of them saves the listing form's fields.
$listingStatus = $isEdit ? (string)($existingPost['status'] ?? 'open') : 'open';
$statusPanel = [
    'open' => [
        'icon' => 'fa-circle-check',
        'heading' => 'On the board',
        'note' => 'Students can find it on Browse and Map. Found someone? Mark it taken and it comes down.',
        'actions' => [['taken', 'Mark as taken', 'fa-handshake'], ['paused', 'Pause', 'fa-circle-pause']],
    ],
    'paused' => [
        'icon' => 'fa-circle-pause',
        'heading' => 'Paused',
        'note' => 'Off Browse and Map until you resume it. Anyone who opens its share link is told it isn\'t available.',
        'actions' => [['open', 'Resume', 'fa-circle-play'], ['taken', 'Mark as taken', 'fa-handshake']],
    ],
    'taken' => [
        'icon' => 'fa-handshake',
        'heading' => 'Taken',
        'note' => 'Off Browse and Map, and its share link says it has been taken. If it falls through, put it back up.',
        'actions' => [['open', 'Put it back up', 'fa-rotate-left']],
    ],
][$listingStatus] ?? null;
$statusSince = !empty($existingPost['status_changed_at']) && $listingStatus !== 'open'
    ? date('M j', strtotime($existingPost['status_changed_at'])) : '';

// The banner after a status change, chosen from the saved status rather than
// echoed from the URL.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $isEdit && ($_GET['status'] ?? '') === $listingStatus) {
    $success_message = [
        'open' => 'Your listing is back up on Browse and Map.',
        'paused' => 'Your listing is paused. It\'s off Browse and Map until you resume it.',
        'taken' => 'Marked as taken. Congratulations! Your listing is off Browse and Map.',
    ][$listingStatus] ?? '';
}

// Get existing images for edit mode
$existingImages = [];
if ($isEdit) {
    $stmtImages = $pdo->prepare("SELECT id, image_url, sort_order FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order");
    $stmtImages->execute([$existingPost['id']]);
    $existingImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php /* Escaped even though both messages are built from literals here — an
         unescaped echo of a variable named $error_message is one careless edit
         away from reflecting user input. */ ?>
<?php if ($success_message): ?>
    <div class="alert alert-success" role="status"><i class="fa-solid fa-check"></i> <?= htmlspecialchars($success_message) ?></div>
    <?php /* Straight after a save is when someone actually wants to post their
             listing to a story or drop it in a group chat, so the link is
             offered here rather than only from the modal on Browse. Only
             for a listing that is up: a paused or taken one's link says it
             isn't available. */ ?>
    <?php if (!empty($existingPost['id']) && $listingStatus === 'open' && !$listingHidden): ?>
        <div class="post-share-row">
            <button type="button" class="btn btn-primary" id="postShareBtn">
                <i class="fa-solid fa-arrow-up-from-bracket"></i> Share your listing
            </button>
            <span class="post-share-hint">Send it to a group chat or put it on your story.</span>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error" role="alert"><i class="fa-solid fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php if ($listingHidden): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-eye-slash"></i>
        <span>Your listing is currently hidden because
        <strong><?= htmlspecialchars(semester_label(array_column($closedSemesters, 'name'))) ?></strong> <?= count($closedSemesters) === 1 ? 'is' : 'are' ?> no longer open for sublets.
        It hasn't been deleted &mdash; pick a current semester below to make it visible again.</span>
    </div>
<?php endif; ?>

<div class="post-layout">
    <div class="post-form-section">
        <h1><?= $isEdit ? 'Edit Your Listing' : 'Create a Listing' ?></h1>

        <?php if ($myStats !== null): ?>
            <?php $plural = static fn(int $count, string $one, string $many) => $count . ' ' . ($count === 1 ? $one : $many); ?>
            <div class="listing-stats">
                <p class="listing-stats-line">
                    <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                    <span>Since <?= htmlspecialchars(date('M j', strtotime($myStats['since']))) ?>:
                    <strong><?= $plural($myStats['views'], 'person', 'people') ?></strong> viewed it &middot;
                    <strong><?= $myStats['contacts'] ?></strong> got in touch &middot;
                    shared <strong><?= $plural($myStats['shares'], 'time', 'times') ?></strong></span>
                </p>
                <p class="listing-stats-note">Views and contact taps are counted. You and the admin see totals, never who.</p>
            </div>
        <?php endif; ?>

        <?php if ($isEdit && $statusPanel): ?>
            <section class="listing-status is-<?= htmlspecialchars($listingStatus) ?>" aria-labelledby="listingStatusHeading">
                <div class="listing-status-text">
                    <h2 class="listing-status-heading" id="listingStatusHeading">
                        <i class="fa-solid <?= $statusPanel['icon'] ?>" aria-hidden="true"></i>
                        <?= htmlspecialchars($statusPanel['heading']) ?><?= $statusSince !== '' ? ' <span class="listing-status-since">since ' . htmlspecialchars($statusSince) . '</span>' : '' ?>
                    </h2>
                    <p class="listing-status-note"><?= htmlspecialchars($statusPanel['note']) ?></p>
                </div>
                <div class="listing-status-actions">
                    <?php foreach ($statusPanel['actions'] as $n => [$to, $label, $icon]): ?>
                        <form method="post" action="post.php">
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($to) ?>">
                            <button type="submit" class="btn btn-sm <?= $n === 0 && $listingStatus !== 'open' ? 'btn-primary' : 'btn-secondary' ?>">
                                <i class="fa-solid <?= $icon ?>" aria-hidden="true"></i> <?= htmlspecialchars($label) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <form method="post" action="post.php" enctype="multipart/form-data" id="postForm">
            <!-- Image Upload -->
            <?php /* Each pick adds to the photos already chosen (app.js keeps
                     the list), and every photo has a remove button that is
                     visible without hovering, since most posts come from a
                     phone. */ ?>
            <div class="form-group">
                <label for="imageInput">Photos</label>
                <div class="upload-zone" id="dropZone">
                    <input type="file" name="images[]" id="imageInput" accept="image/*" multiple <?= $isEdit ? '' : 'required' ?>>
                    <i class="fa-solid fa-camera" aria-hidden="true"></i>
                    <p><strong>Add photos</strong><span class="upload-drag-hint"> or drag them here</span></p>
                    <p class="form-note">The first photo is the cover on your card.</p>
                </div>
                <div class="image-previews" id="imagePreviews">
                    <?php /* The cover is the first photo, not sort_order 0: deleting
                             the cover promotes the next photo without renumbering,
                             so a listing can have no 0 at all. */ ?>
                    <?php foreach ($existingImages as $n => $img): ?>
                        <div class="image-preview <?= $n === 0 ? 'is-thumbnail' : '' ?>" data-image-id="<?= $img['id'] ?>">
                            <img src="<?= htmlspecialchars(display_src($img['image_url'])) ?>" alt="Photo <?= $n + 1 ?>" decoding="async">
                            <button type="button" class="remove-image" data-image-id="<?= $img['id'] ?>" aria-label="Delete photo <?= $n + 1 ?>">
                                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            </button>
                            <?php if ($n === 0): ?>
                                <span class="thumbnail-badge">Cover</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="upload-status" id="uploadStatus" role="status" aria-live="polite"></p>
            </div>

            <!-- Price -->
            <div class="form-group">
                <label for="price">Price per month <span class="label-aside">(rent only, not including utilities)</span></label>
                <div class="input-with-prefix">
                    <span class="input-prefix">$</span>
                    <input type="number" id="price" name="price" step="0.01" min="0"
                           value="<?= htmlspecialchars((string)($form['price'] ?? '')) ?>" required>
                </div>
                <?php if (isset($subletColumns['price_negotiable'])): ?>
                    <label class="inline-checkbox">
                        <input type="checkbox" name="price_negotiable" value="1"
                               <?= !empty($form['price_negotiable']) ? 'checked' : '' ?>>
                        <span>Price is negotiable — show an "or best offer" tag</span>
                    </label>
                <?php endif; ?>
            </div>

            <?php /* The stored address is re-rendered through format_address(),
                     so the field matches what the cards and map popups show.
                     Saving then writes back the shortened form; lat/lon are
                     untouched, so an existing listing keeps its map position. */ ?>
            <!-- Address -->
            <?php /* An ARIA combobox: the suggestions are a listbox the arrow
                     keys move through, and what the search found (or did not)
                     is announced through #addressStatus. Messages sit outside
                     the listbox, since a listbox may only hold options. */ ?>
            <div class="form-group">
                <label for="address" id="addressLabel">Address</label>
                <div class="address-wrapper">
                    <input type="text" id="address" name="address" placeholder="Start typing your street address"
                           value="<?= htmlspecialchars(format_address($form['address'] ?? '')) ?>"
                           autocomplete="off" required
                           role="combobox" aria-autocomplete="list" aria-expanded="false"
                           aria-controls="addressListbox" aria-describedby="addressHint addressPrivacy">
                    <div class="autocomplete-results" id="addressResults">
                        <div role="listbox" id="addressListbox" aria-labelledby="addressLabel"></div>
                        <div class="autocomplete-empty" id="addressMessage" hidden></div>
                    </div>
                </div>
                <p class="sr-only" id="addressStatus" role="status" aria-live="polite"></p>
                <p class="field-hint" id="addressHint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Pick a suggestion, or tap your place on the map if it isn&rsquo;t listed.</p>
                <p class="field-hint" id="addressPrivacy"><i class="fa-solid fa-lock" aria-hidden="true"></i> Only signed-in UVM students see your address. Shared links and previews show how far it is from campus, never the street.</p>
                <input type="hidden" id="lat" name="lat" value="<?= $formHasLocation ? htmlspecialchars((string)$form['lat']) : '' ?>">
                <input type="hidden" id="lon" name="lon" value="<?= $formHasLocation ? htmlspecialchars((string)$form['lon']) : '' ?>">
                <?php /* On a phone app.js moves the map in here, under the field
                         it answers to; on a desktop it stays in the side column. */ ?>
                <div class="post-map-slot" id="postMapSlot"></div>
            </div>

            <!-- Semester -->
            <?php /* One pill per open semester, in calendar order, plus any closed
                     one the listing already has. Several can be ticked if they
                     run back to back; app.js greys out the ones that would leave
                     a gap, and the server checks again. data-key is the
                     semester's place in the calendar (semester_key()), empty
                     for a name that is not a term and year. */ ?>
            <fieldset class="form-group semester-picker" id="semesterPicker" aria-describedby="semesterHint">
                <legend>Semester</legend>
                <div class="semester-options">
                    <?php foreach ($semesterOptions as $sem): ?>
                        <label class="semester-option<?= $sem['closed'] ? ' is-closed' : '' ?>">
                            <input type="checkbox" name="semesters[]" value="<?= htmlspecialchars($sem['code']) ?>"
                                   data-key="<?= $sem['key'] ?? '' ?>"
                                   <?= in_array($sem['code'], $checkedSemesters, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($sem['name']) ?><?= $sem['closed'] ? ' (closed)' : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field-hint" id="semesterHint"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Pick more than one if it&rsquo;s available back to back, like Summer and Fall. For a later semester with a gap, post again once this sublet is over.</p>
            </fieldset>

            <?php if (isset($subletColumns['bedrooms'])): ?>
                <?php
                    $curBedrooms = $form['bedrooms'] ?? '';
                    $curBathrooms = is_numeric($form['bathrooms'] ?? null)
                        ? format_half((float)$form['bathrooms']) : '';
                    $curRoommates = $form['roommates'] ?? '';
                ?>
                <!-- Place & Roommates -->
                <div class="form-group">
                    <div class="form-section-header">
                        <h3><i class="fa-solid fa-bed"></i> The Place &amp; Roommates</h3>
                        <span class="badge-optional">Optional</span>
                    </div>

                    <p class="form-note form-note-lead">
                        Leave anything blank if it doesn't apply or you'd rather not say.
                    </p>

                    <div class="size-grid">
                        <div>
                            <label for="bedrooms">Bedrooms</label>
                            <input type="number" id="bedrooms" name="bedrooms" min="0" max="20" step="1"
                                   placeholder="e.g. 3" value="<?= htmlspecialchars((string)$curBedrooms) ?>">
                        </div>
                        <div>
                            <label for="bathrooms">Bathrooms</label>
                            <input type="number" id="bathrooms" name="bathrooms" min="0.5" max="9.5" step="0.5"
                                   placeholder="e.g. 1.5" value="<?= htmlspecialchars($curBathrooms) ?>">
                        </div>
                        <div>
                            <label for="roommates">Roommates staying</label>
                            <input type="number" id="roommates" name="roommates" min="0" max="20" step="1"
                                   placeholder="e.g. 2" value="<?= htmlspecialchars((string)$curRoommates) ?>">
                        </div>
                    </div>

                    <?php /* Hidden by app.js when "roommates" is 0 — the server
                             blanks both fields in that case anyway, so the two
                             cannot disagree if JS never runs. */ ?>
                    <div class="roommate-details" id="roommateDetails">
                        <div class="utility-row">
                            <label for="roommate_gender">Who lives here now</label>
                            <select id="roommate_gender" name="roommate_gender">
                                <?php foreach (ROOMMATE_GENDER_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"
                                        <?= ($form['roommate_gender'] ?? '') === $val ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="utility-row">
                            <label for="roommate_preference">Hoping to sublet to</label>
                            <select id="roommate_preference" name="roommate_preference">
                                <?php foreach (ROOMMATE_PREFERENCE_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"
                                        <?= ($form['roommate_preference'] ?? '') === $val ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <p class="field-hint">
                            <i class="fa-solid fa-circle-info"></i>
                            This shows on your listing as a preference, not a requirement — anyone can still message you.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5"
                          placeholder="Describe your place — bedrooms, bathrooms, amenities, parking, etc."><?= htmlspecialchars((string)($form['description'] ?? '')) ?></textarea>
            </div>

            <!-- Utilities & Amenities -->
            <div class="form-group">
                <div class="form-section-header">
                    <h3><i class="fa-solid fa-plug"></i> Utilities & Amenities</h3>
                    <span class="badge-optional">Optional</span>
                </div>

                <p class="form-note form-note-lead">
                    Who pays for each utility? Leave as "Not specified" if unsure.
                </p>

                <div class="utility-grid">
                    <div class="utility-row">
                        <label for="utility_electric"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Electric</label>
                        <select id="utility_electric" name="utility_electric">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($form['utility_electric'] ?? '') === 'landlord' ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($form['utility_electric'] ?? '') === 'tenant' ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label for="utility_gas"><i class="fa-solid fa-fire-flame-simple" aria-hidden="true"></i> Gas</label>
                        <select id="utility_gas" name="utility_gas">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($form['utility_gas'] ?? '') === 'landlord' ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($form['utility_gas'] ?? '') === 'tenant' ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label for="utility_water"><i class="fa-solid fa-droplet" aria-hidden="true"></i> Water</label>
                        <select id="utility_water" name="utility_water">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($form['utility_water'] ?? '') === 'landlord' ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($form['utility_water'] ?? '') === 'tenant' ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label for="utility_internet"><i class="fa-solid fa-wifi" aria-hidden="true"></i> Internet</label>
                        <select id="utility_internet" name="utility_internet">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($form['utility_internet'] ?? '') === 'landlord' ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($form['utility_internet'] ?? '') === 'tenant' ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                </div>

                <div class="form-subgroup">
                    <label for="utility_cost">Estimated Monthly Utility Cost <span class="label-aside">(what tenant pays)</span></label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">$</span>
                        <input type="number" id="utility_cost" name="utility_cost" step="1" min="0"
                               value="<?= htmlspecialchars((string)($form['utility_cost'] ?? '')) ?>"
                               placeholder="e.g. 150">
                    </div>
                </div>

                <div class="form-subgroup">
                    <p class="form-note form-note-lead">
                        Check all amenities that apply:
                    </p>
                    <div class="amenity-checkboxes">
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_free_parking" value="1" <?= !empty($form['amenity_free_parking']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-square-parking"></i>
                            <span>Free Parking</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_paid_parking" value="1" <?= !empty($form['amenity_paid_parking']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-square-parking"></i>
                            <span>Paid Parking</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_laundry_free" value="1" <?= !empty($form['amenity_laundry_free']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-shirt"></i>
                            <span>In-Unit Laundry (Free)</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_laundry_paid" value="1" <?= !empty($form['amenity_laundry_paid']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-shirt"></i>
                            <span>In-Unit Laundry (Paid)</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_dishwasher" value="1" <?= !empty($form['amenity_dishwasher']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-sink"></i>
                            <span>Dishwasher</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_air_conditioning" value="1" <?= !empty($form['amenity_air_conditioning']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-snowflake"></i>
                            <span>A/C</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_pets_allowed" value="1" <?= !empty($form['amenity_pets_allowed']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-paw"></i>
                            <span>Pets Allowed</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_furnished" value="1" <?= !empty($form['amenity_furnished']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-couch"></i>
                            <span>Furnished</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <?php if (isset($subletColumns['display_name'])): ?>
                <div class="form-group">
                    <label for="display_name">Your Name <span class="label-aside">(optional)</span></label>
                    <input type="text" id="display_name" name="display_name" maxlength="60"
                           value="<?= htmlspecialchars((string)($form['display_name'] ?? '')) ?>"
                           placeholder="<?= htmlspecialchars($username) ?>">
                    <p class="field-hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Shown on your listing instead of your NetID. Leave it blank to keep showing <strong><?= htmlspecialchars($username) ?></strong>.
                    </p>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email"
                       value="<?= htmlspecialchars(!empty($form['contact_email']) ? (string)$form['contact_email'] : $username . '@uvm.edu') ?>"
                       placeholder="your.email@uvm.edu" required>
            </div>

            <div class="form-group">
                <label for="contact_phone">Phone Number <span class="label-aside">(optional)</span></label>
                <input type="tel" id="contact_phone" name="contact_phone"
                       value="<?= htmlspecialchars((string)($form['contact_phone'] ?? '')) ?>"
                       placeholder="(802) 555-1234">
            </div>

            <!-- Info -->
            <p class="form-note form-note-closing">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Signed-in UVM students can email you, and call or text if you add a number. Neither shows up in shared links or previews.
            </p>

            <!-- Actions -->
            <div class="form-actions">
                <?php /* app.js swaps the label for "Uploading 3 photos…" while the
                         request is in flight, and ignores a second press. */ ?>
                <button type="submit" class="btn btn-primary btn-lg" id="postSubmit">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span class="btn-label"><?= $isEdit ? 'Update Listing' : 'Post Listing' ?></span>
                </button>
                <?php if ($isEdit): ?>
                    <?php /* Submits the surrounding form as POST — see the delete handler above.
                             formnovalidate so the required fields don't block a delete. */ ?>
                    <button type="submit" name="action" value="delete" class="btn btn-danger" formnovalidate
                            onclick="return confirm('Are you sure you want to delete your listing? This cannot be undone.');">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i> <span class="btn-label">Delete</span>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="post-map-section">
        <div class="map-container">
            <div id="postMap"></div>
            <p class="map-hint" id="postMapHint">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span id="postMapHintText"><?= $formHasLocation ? 'Drag the pin or tap the map to move it.' : 'Search your address, or tap the map where your place is.' ?></span>
            </p>
        </div>
    </div>
</div>

<script>
    window.POST_CONFIG = {
        isEdit: <?= $isEdit ? 'true' : 'false' ?>,
        // null until the listing has a place: the map then opens on campus with
        // no pin, rather than a pin on campus that looks like an answer.
        lat: <?= $formHasLocation ? (float)$form['lat'] : 'null' ?>,
        lon: <?= $formHasLocation ? (float)$form['lon'] : 'null' ?>,
        // The limits PHP enforces, so the form can say so before uploading.
        uploadMaxBytes: <?= ini_bytes('upload_max_filesize') ?>,
        postMaxBytes: <?= ini_bytes('post_max_size') ?>,
        maxFiles: <?= (int)ini_get('max_file_uploads') ?>,
        // For the activity log's share events (source "post").
        listingId: <?= !empty($existingPost['id']) ? (int)$existingPost['id'] : 0 ?>,
        shareUrl: <?= json_encode(!empty($existingPost['id']) ? share_url((int)$existingPost['id']) : '') ?>,
        sharePrice: <?= json_encode(!empty($existingPost['price']) ? '$' . number_format((float)$existingPost['price']) : '') ?>,
        shareSemester: <?= json_encode($shareSemesterName) ?>
    };
</script>
<?php require_once '../includes/share_sheet.php'; ?>

<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
