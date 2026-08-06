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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin();

    $price = $_POST['price'] ?? '';
    $address = $_POST['address'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $lat = (float)($_POST['lat'] ?? 0);
    $lon = (float)($_POST['lon'] ?? 0);
    $description = $_POST['description'] ?? '';
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    // Utility fields
    $utility_electric = $_POST['utility_electric'] ?? '';
    $utility_gas = $_POST['utility_gas'] ?? '';
    $utility_water = $_POST['utility_water'] ?? '';
    $utility_internet = $_POST['utility_internet'] ?? '';
    $utility_cost = $_POST['utility_cost'] !== '' ? (float)$_POST['utility_cost'] : null;

    // Amenity fields
    $amenity_free_parking = isset($_POST['amenity_free_parking']) ? 1 : 0;
    $amenity_paid_parking = isset($_POST['amenity_paid_parking']) ? 1 : 0;
    $amenity_laundry_free = isset($_POST['amenity_laundry_free']) ? 1 : 0;
    $amenity_laundry_paid = isset($_POST['amenity_laundry_paid']) ? 1 : 0;
    $amenity_dishwasher = isset($_POST['amenity_dishwasher']) ? 1 : 0;
    $amenity_air_conditioning = isset($_POST['amenity_air_conditioning']) ? 1 : 0;
    $amenity_pets_allowed = isset($_POST['amenity_pets_allowed']) ? 1 : 0;
    $amenity_furnished = isset($_POST['amenity_furnished']) ? 1 : 0;

    // Validate distance from campus
    $campusLat = 44.477435;
    $campusLon = -73.195323;
    $dLat = deg2rad($lat - $campusLat);
    $dLon = deg2rad($lon - $campusLon);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($campusLat)) * cos(deg2rad($lat)) * sin($dLon / 2) ** 2;
    $distance = 3959 * 2 * asin(sqrt($a));

    if ($distance > 50) {
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
            // Update existing post
            $sql = "UPDATE sublets SET price = ?, address = ?, semester = ?, lat = ?, lon = ?, description = ?, contact_email = ?, contact_phone = ?, utility_electric = ?, utility_gas = ?, utility_water = ?, utility_internet = ?, utility_cost = ?, amenity_free_parking = ?, amenity_paid_parking = ?, amenity_laundry_free = ?, amenity_laundry_paid = ?, amenity_dishwasher = ?, amenity_air_conditioning = ?, amenity_pets_allowed = ?, amenity_furnished = ? WHERE username = ?";
            $pdo->prepare($sql)->execute([$price, $address, $semester, $lat, $lon, $description, $contact_email, $contact_phone, $utility_electric, $utility_gas, $utility_water, $utility_internet, $utility_cost, $amenity_free_parking, $amenity_paid_parking, $amenity_laundry_free, $amenity_laundry_paid, $amenity_dishwasher, $amenity_air_conditioning, $amenity_pets_allowed, $amenity_furnished, $username]);
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

                $sql = "INSERT INTO sublets (image_url, thumbnail_url, price, address, semester, lat, lon, description, username, contact_email, contact_phone, utility_electric, utility_gas, utility_water, utility_internet, utility_cost, amenity_free_parking, amenity_paid_parking, amenity_laundry_free, amenity_laundry_paid, amenity_dishwasher, amenity_air_conditioning, amenity_pets_allowed, amenity_furnished) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $pdo->prepare($sql)->execute([$urlTarget, $urlThumb, $price, $address, $semester, $lat, $lon, $description, $username, $contact_email, $contact_phone, $utility_electric, $utility_gas, $utility_water, $utility_internet, $utility_cost, $amenity_free_parking, $amenity_paid_parking, $amenity_laundry_free, $amenity_laundry_paid, $amenity_dishwasher, $amenity_air_conditioning, $amenity_pets_allowed, $amenity_furnished]);
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
if ($skippedUploads > 0) {
    $note = $skippedUploads . ' file' . ($skippedUploads === 1 ? ' was' : 's were')
        . ' skipped because they are not images (JPEG, PNG, GIF, WebP, or HEIC only).';
    $success_message = $success_message ? $success_message . ' ' . $note : '';
    $error_message = $error_message ?: $note;
}

// Get existing images for edit mode
$existingImages = [];
if ($isEdit) {
    $stmtImages = $pdo->prepare("SELECT id, image_url, sort_order FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order");
    $stmtImages->execute([$existingPost['id']]);
    $existingImages = $stmtImages->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= $success_message ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><i class="fa-solid fa-exclamation-triangle"></i> <?= $error_message ?></div>
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
            </div>

            <!-- Address -->
            <div class="form-group">
                <label for="address">Address</label>
                <div class="address-wrapper">
                    <input type="text" id="address" name="address" placeholder="Start typing an address..."
                           value="<?= $isEdit ? htmlspecialchars($existingPost['address']) : '' ?>"
                           autocomplete="off" required>
                    <div class="autocomplete-results" id="addressResults"></div>
                </div>
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
