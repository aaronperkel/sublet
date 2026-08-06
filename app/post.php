<?php
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/thumbnail.php';

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

// Get active semesters for dropdown
$stmtSem = $pdo->query("SELECT code, name FROM semesters WHERE active = 1 ORDER BY sort_order, code");
$semesterOptions = $stmtSem->fetchAll(PDO::FETCH_ASSOC);

// If no semesters in DB, fall back to querying distinct from sublets
if (empty($semesterOptions)) {
    $stmtSem = $pdo->query("SELECT DISTINCT semester as code, semester as name FROM sublets ORDER BY semester");
    $semesterOptions = $stmtSem->fetchAll(PDO::FETCH_ASSOC);
}

// If the user's semester has since been deactivated it is missing from the
// dropdown above, so the browser would fall back to the first option and
// silently move their listing on save. Keep it selectable and flag it instead.
$listingHidden = false;
if ($isEdit && !in_array($existingPost['semester'], array_column($semesterOptions, 'code'), true)) {
    $stmtCurSem = $pdo->prepare("SELECT code, name FROM semesters WHERE code = ?");
    $stmtCurSem->execute([$existingPost['semester']]);
    $currentSem = $stmtCurSem->fetch(PDO::FETCH_ASSOC);

    // Only a row that exists but is inactive means "hidden" — an unmapped
    // legacy code still shows on the site (see includes/visibility.php).
    if ($currentSem) {
        $listingHidden = true;
    } else {
        $currentSem = ['code' => $existingPost['semester'], 'name' => $existingPost['semester']];
    }

    $currentSem['hidden'] = $listingHidden;
    array_unshift($semesterOptions, $currentSem);
}

// Handle delete action. POST only, and same-origin: as a GET this could be
// triggered by any other site simply embedding <img src=".../post.php?action=
// delete">, silently destroying a signed-in user's listing.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && $isEdit) {
    require_same_origin();

    // Delete image files
    $stmtImages = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ?");
    $stmtImages->execute([$existingPost['id']]);
    foreach ($stmtImages->fetchAll(PDO::FETCH_COLUMN) as $file) {
        delete_image_files($file);
    }
    delete_image_files($existingPost['image_url']);
    delete_image_files($existingPost['thumbnail_url']);

    $pdo->prepare("DELETE FROM sublets WHERE id = ?")->execute([$existingPost['id']]);

    $to = 'aperkel@uvm.edu';
    $subject = 'Sublet Post Deleted';
    mail($to, $subject, "User $username deleted their sublet post.");

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$postTooLarge) {
    require_same_origin();

    $price = $_POST['price'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $semester = $_POST['semester'] ?? '';
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
        $error_message = "Please pick your address from the dropdown suggestions so your listing can be placed on the map.";
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
            $sql = "UPDATE sublets SET $assignments WHERE username = ?";
            $pdo->prepare($sql)->execute([...array_values($fields), $username]);
            $subletId = $existingPost['id'];

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
                    $filename = $username . '_' . time() . '_' . $newOrder . '.' . $ext;
                    $fsTarget = $fs_dir . $filename;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $fsTarget)) {
                        $fsTarget = ensure_browser_safe($fsTarget);
                        // No thumbnail here: only sublets.thumbnail_url (the
                        // card image) is ever read, so a thumb for a gallery
                        // image would be a file nothing loads.
                        $urlTarget = $url_prefix . basename($fsTarget);
                        $stmtImage->execute([$subletId, $urlTarget, $newOrder]);
                    }
                }
            }

            mail('aperkel@uvm.edu', 'Sublet Post Updated', "User $username updated their sublet post.");
            $success_message = "Your listing has been updated!";

            // Refresh post data
            $stmtCheck->execute([$username]);
            $existingPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        } else {
            // Create new post. Extension comes from the file's own bytes, never
            // its name — public/images/ is web-served, so a .php upload there
            // would be executable.
            $ext = safe_image_extension($_FILES['images']['tmp_name'][0]);
            $fsTarget = $ext === null ? '' : $fs_dir . $username . '_0.' . $ext;

            if ($ext === null) {
                $error_message = "That file isn't a supported image. Please upload a JPEG, PNG, GIF, WebP, or HEIC photo.";
            } elseif (!move_uploaded_file($_FILES['images']['tmp_name'][0], $fsTarget)) {
                $error_message = "Error uploading image.";
            } else {
                $fsTarget = ensure_browser_safe($fsTarget);
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
                    $fname = $username . '_' . $i . '.' . $ext;
                    $fsT = $fs_dir . $fname;
                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $fsT)) {
                        $fsT = ensure_browser_safe($fsT);
                        // See above: gallery images need no thumbnail.
                        $urlT = $url_prefix . basename($fsT);
                        $stmtImage->execute([$subletId, $urlT, $i]);
                    }
                }

                mail('aperkel@uvm.edu', 'New Sublet Post', "New sublet posted by $username.\nPrice: $price\nAddress: $address");
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
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error" role="alert"><i class="fa-solid fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<?php if ($listingHidden): ?>
    <div class="alert alert-info">
        <i class="fa-solid fa-eye-slash"></i>
        Your listing is currently hidden because
        <strong><?= htmlspecialchars($semesterOptions[0]['name']) ?></strong> is no longer open for sublets.
        It hasn't been deleted &mdash; pick a current semester below to make it visible again.
    </div>
<?php endif; ?>

<div class="post-layout">
    <div class="post-form-section">
        <h1><?= $isEdit ? 'Edit Your Listing' : 'Create a Listing' ?></h1>

        <form method="post" action="post.php" enctype="multipart/form-data" id="postForm">
            <!-- Image Upload -->
            <div class="form-group">
                <label>Photos</label>
                <div class="upload-zone" id="dropZone">
                    <input type="file" name="images[]" id="imageInput" accept="image/*" multiple <?= $isEdit ? '' : 'required' ?>>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p><strong>Click to upload</strong> or drag and drop</p>
                    <p class="text-muted" style="font-size: 0.8rem; margin-top: 0.25rem;">First image will be the thumbnail</p>
                </div>
                <div class="image-previews" id="imagePreviews">
                    <?php foreach ($existingImages as $img): ?>
                        <div class="image-preview <?= $img['sort_order'] === 0 ? 'is-thumbnail' : '' ?>" data-image-id="<?= $img['id'] ?>">
                            <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Listing image">
                            <button type="button" class="remove-image" data-image-id="<?= $img['id'] ?>">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                            <?php if ($img['sort_order'] === 0): ?>
                                <span class="thumbnail-badge">Thumbnail</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Price -->
            <div class="form-group">
                <label for="price">Price per month <span class="text-muted" style="font-weight: 400; text-transform: none;">(rent only, not including utilities)</span></label>
                <div class="input-with-prefix">
                    <span class="input-prefix">$</span>
                    <input type="number" id="price" name="price" step="0.01" min="0"
                           value="<?= $isEdit ? htmlspecialchars($existingPost['price']) : '' ?>" required>
                </div>
                <?php if (isset($subletColumns['price_negotiable'])): ?>
                    <label class="inline-checkbox">
                        <input type="checkbox" name="price_negotiable" value="1"
                               <?= ($isEdit && !empty($existingPost['price_negotiable'])) ? 'checked' : '' ?>>
                        <span>Price is negotiable — show an "or best offer" tag</span>
                    </label>
                <?php endif; ?>
            </div>

            <?php /* The stored address is re-rendered through format_address(),
                     so the field matches what the cards and map popups show.
                     Saving then writes back the shortened form; lat/lon are
                     untouched, so an existing listing keeps its map position. */ ?>
            <!-- Address -->
            <div class="form-group">
                <label for="address">Address</label>
                <div class="address-wrapper">
                    <input type="text" id="address" name="address" placeholder="Start typing an address..."
                           value="<?= $isEdit ? htmlspecialchars(format_address($existingPost['address'])) : '' ?>"
                           autocomplete="off" required>
                    <div class="autocomplete-results" id="addressResults"></div>
                </div>
                <p class="field-hint"><i class="fa-solid fa-circle-info"></i> Pick a suggestion from the dropdown so your listing lands in the right spot on the map.</p>
                <input type="hidden" id="lat" name="lat" value="<?= $isEdit ? $existingPost['lat'] : '' ?>">
                <input type="hidden" id="lon" name="lon" value="<?= $isEdit ? $existingPost['lon'] : '' ?>">
            </div>

            <!-- Semester -->
            <div class="form-group">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" required>
                    <option value="" disabled <?= !$isEdit ? 'selected' : '' ?>>Select semester</option>
                    <?php foreach ($semesterOptions as $sem): ?>
                        <option value="<?= htmlspecialchars($sem['code']) ?>"
                            <?= ($isEdit && $existingPost['semester'] === $sem['code']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sem['name']) ?><?= !empty($sem['hidden']) ? ' (closed — listing hidden)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (isset($subletColumns['bedrooms'])): ?>
                <?php
                    $curBedrooms = $isEdit ? ($existingPost['bedrooms'] ?? '') : '';
                    $curBathrooms = ($isEdit && ($existingPost['bathrooms'] ?? null) !== null)
                        ? format_half((float)$existingPost['bathrooms']) : '';
                    $curRoommates = $isEdit ? ($existingPost['roommates'] ?? '') : '';
                ?>
                <!-- Place & Roommates -->
                <div class="form-group">
                    <div class="form-section-header">
                        <h3><i class="fa-solid fa-bed"></i> The Place &amp; Roommates</h3>
                        <span class="badge-optional">Optional</span>
                    </div>

                    <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 0.75rem;">
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
                                        <?= ($isEdit && ($existingPost['roommate_gender'] ?? '') === $val) ? 'selected' : '' ?>>
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
                                        <?= ($isEdit && ($existingPost['roommate_preference'] ?? '') === $val) ? 'selected' : '' ?>>
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
                          placeholder="Describe your place — bedrooms, bathrooms, amenities, parking, etc."><?= $isEdit ? htmlspecialchars($existingPost['description']) : '' ?></textarea>
            </div>

            <!-- Utilities & Amenities -->
            <div class="form-group">
                <div class="form-section-header">
                    <h3><i class="fa-solid fa-plug"></i> Utilities & Amenities</h3>
                    <span class="badge-optional">Optional</span>
                </div>

                <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 0.75rem;">
                    Who pays for each utility? Leave as "Not specified" if unsure.
                </p>

                <div class="utility-grid">
                    <div class="utility-row">
                        <label><i class="fa-solid fa-bolt"></i> Electric</label>
                        <select name="utility_electric">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($isEdit && ($existingPost['utility_electric'] ?? '') === 'landlord') ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($isEdit && ($existingPost['utility_electric'] ?? '') === 'tenant') ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label><i class="fa-solid fa-fire-flame-simple"></i> Gas</label>
                        <select name="utility_gas">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($isEdit && ($existingPost['utility_gas'] ?? '') === 'landlord') ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($isEdit && ($existingPost['utility_gas'] ?? '') === 'tenant') ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label><i class="fa-solid fa-droplet"></i> Water</label>
                        <select name="utility_water">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($isEdit && ($existingPost['utility_water'] ?? '') === 'landlord') ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($isEdit && ($existingPost['utility_water'] ?? '') === 'tenant') ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label><i class="fa-solid fa-wifi"></i> Internet</label>
                        <select name="utility_internet">
                            <option value="">Not specified</option>
                            <option value="landlord" <?= ($isEdit && ($existingPost['utility_internet'] ?? '') === 'landlord') ? 'selected' : '' ?>>Included in rent</option>
                            <option value="tenant" <?= ($isEdit && ($existingPost['utility_internet'] ?? '') === 'tenant') ? 'selected' : '' ?>>Tenant pays</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <label for="utility_cost">Estimated Monthly Utility Cost <span class="text-muted" style="font-weight: 400; text-transform: none;">(what tenant pays)</span></label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">$</span>
                        <input type="number" id="utility_cost" name="utility_cost" step="1" min="0"
                               value="<?= $isEdit ? htmlspecialchars($existingPost['utility_cost'] ?? '') : '' ?>"
                               placeholder="e.g. 150">
                    </div>
                </div>

                <div style="margin-top: 1.25rem;">
                    <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 0.75rem;">
                        Check all amenities that apply:
                    </p>
                    <div class="amenity-checkboxes">
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_free_parking" value="1" <?= ($isEdit && ($existingPost['amenity_free_parking'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-square-parking"></i>
                            <span>Free Parking</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_paid_parking" value="1" <?= ($isEdit && ($existingPost['amenity_paid_parking'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-square-parking"></i>
                            <span>Paid Parking</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_laundry_free" value="1" <?= ($isEdit && ($existingPost['amenity_laundry_free'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-shirt"></i>
                            <span>In-Unit Laundry (Free)</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_laundry_paid" value="1" <?= ($isEdit && ($existingPost['amenity_laundry_paid'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-shirt"></i>
                            <span>In-Unit Laundry (Paid)</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_dishwasher" value="1" <?= ($isEdit && ($existingPost['amenity_dishwasher'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-sink"></i>
                            <span>Dishwasher</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_air_conditioning" value="1" <?= ($isEdit && ($existingPost['amenity_air_conditioning'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-snowflake"></i>
                            <span>A/C</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_pets_allowed" value="1" <?= ($isEdit && ($existingPost['amenity_pets_allowed'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-paw"></i>
                            <span>Pets Allowed</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_furnished" value="1" <?= ($isEdit && ($existingPost['amenity_furnished'] ?? 0)) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-couch"></i>
                            <span>Furnished</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <?php if (isset($subletColumns['display_name'])): ?>
                <div class="form-group">
                    <label for="display_name">Your Name <span class="text-muted" style="font-weight: 400; text-transform: none;">(optional)</span></label>
                    <input type="text" id="display_name" name="display_name" maxlength="60"
                           value="<?= $isEdit ? htmlspecialchars($existingPost['display_name'] ?? '') : '' ?>"
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
                       value="<?= $isEdit && !empty($existingPost['contact_email']) ? htmlspecialchars($existingPost['contact_email']) : htmlspecialchars($username) . '@uvm.edu' ?>"
                       placeholder="your.email@uvm.edu" required>
            </div>

            <div class="form-group">
                <label for="contact_phone">Phone Number <span class="text-muted" style="font-weight: 400; text-transform: none;">(optional)</span></label>
                <input type="tel" id="contact_phone" name="contact_phone"
                       value="<?= $isEdit && !empty($existingPost['contact_phone']) ? htmlspecialchars($existingPost['contact_phone']) : '' ?>"
                       placeholder="(802) 555-1234">
            </div>

            <!-- Info -->
            <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 1rem;">
                <i class="fa-solid fa-circle-info"></i>
                Your listing will show contact buttons so interested users can reach you via email and phone (if provided).
            </p>

            <!-- Actions -->
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;">
                    <i class="fa-solid fa-paper-plane"></i>
                    <?= $isEdit ? 'Update Listing' : 'Post Listing' ?>
                </button>
                <?php if ($isEdit): ?>
                    <?php /* Submits the surrounding form as POST — see the delete handler above.
                             formnovalidate so the required fields don't block a delete. */ ?>
                    <button type="submit" name="action" value="delete" class="btn btn-danger" formnovalidate
                            onclick="return confirm('Are you sure you want to delete your listing? This cannot be undone.');">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="post-map-section">
        <div class="map-container">
            <div id="postMap"></div>
            <p class="map-hint">
                <i class="fa-solid fa-location-dot"></i>
                Select an address to see it on the map
            </p>
        </div>
    </div>
</div>

<script>
    window.POST_CONFIG = {
        isEdit: <?= $isEdit ? 'true' : 'false' ?>,
        lat: <?= $isEdit ? $existingPost['lat'] : '44.477435' ?>,
        lon: <?= $isEdit ? $existingPost['lon'] : '-73.195323' ?>
    };
</script>
<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
