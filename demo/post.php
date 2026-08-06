<?php
require_once 'includes/header.php';

$success_message = '';
$error_message = '';

// Get active semesters for dropdown
$stmtSem = $pdo->query("SELECT code, name FROM semesters WHERE active = 1 ORDER BY sort_order, code");
$semesterOptions = $stmtSem->fetchAll(PDO::FETCH_ASSOC);

if (empty($semesterOptions)) {
    $stmtSem = $pdo->query("SELECT DISTINCT semester as code, semester as name FROM $SUBLET_TABLE ORDER BY semester");
    $semesterOptions = $stmtSem->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission — show success without saving
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success_message = "This is a demo — in the real site, your listing would be posted! Sign in with your UVM account to post for real.";
}
?>

<?php if ($success_message): ?>
    <div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= $success_message ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-error"><i class="fa-solid fa-exclamation-triangle"></i> <?= $error_message ?></div>
<?php endif; ?>

<div class="post-layout">
    <div class="post-form-section">
        <h1>Create a Listing</h1>

        <form method="post" action="post.php" enctype="multipart/form-data" id="postForm">
            <!-- Image Upload -->
            <div class="form-group">
                <label>Photos</label>
                <div class="upload-zone" id="dropZone">
                    <input type="file" name="images[]" id="imageInput" accept="image/*" multiple>
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p><strong>Click to upload</strong> or drag and drop</p>
                    <p class="text-muted" style="font-size: 0.8rem; margin-top: 0.25rem;">First image will be the thumbnail</p>
                </div>
                <div class="image-previews" id="imagePreviews"></div>
            </div>

            <!-- Price -->
            <div class="form-group">
                <label for="price">Price per month <span class="text-muted" style="font-weight: 400; text-transform: none;">(rent only, not including utilities)</span></label>
                <div class="input-with-prefix">
                    <span class="input-prefix">$</span>
                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                </div>
            </div>

            <!-- Address -->
            <div class="form-group">
                <label for="address">Address</label>
                <div class="address-wrapper">
                    <input type="text" id="address" name="address" placeholder="Start typing an address..."
                           autocomplete="off" required>
                    <div class="autocomplete-results" id="addressResults"></div>
                </div>
                <input type="hidden" id="lat" name="lat" value="">
                <input type="hidden" id="lon" name="lon" value="">
            </div>

            <!-- Semester -->
            <div class="form-group">
                <label for="semester">Semester</label>
                <select id="semester" name="semester" required>
                    <option value="" disabled selected>Select semester</option>
                    <?php foreach ($semesterOptions as $sem): ?>
                        <option value="<?= htmlspecialchars($sem['code']) ?>">
                            <?= htmlspecialchars($sem['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5"
                          placeholder="Describe your place — bedrooms, bathrooms, amenities, parking, etc."></textarea>
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
                            <option value="landlord">Included in rent</option>
                            <option value="tenant">Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label><i class="fa-solid fa-fire-flame-simple"></i> Gas</label>
                        <select name="utility_gas">
                            <option value="">Not specified</option>
                            <option value="landlord">Included in rent</option>
                            <option value="tenant">Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label><i class="fa-solid fa-droplet"></i> Water</label>
                        <select name="utility_water">
                            <option value="">Not specified</option>
                            <option value="landlord">Included in rent</option>
                            <option value="tenant">Tenant pays</option>
                        </select>
                    </div>
                    <div class="utility-row">
                        <label><i class="fa-solid fa-wifi"></i> Internet</label>
                        <select name="utility_internet">
                            <option value="">Not specified</option>
                            <option value="landlord">Included in rent</option>
                            <option value="tenant">Tenant pays</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <label for="utility_cost">Estimated Monthly Utility Cost <span class="text-muted" style="font-weight: 400; text-transform: none;">(what tenant pays)</span></label>
                    <div class="input-with-prefix">
                        <span class="input-prefix">$</span>
                        <input type="number" id="utility_cost" name="utility_cost" step="1" min="0" placeholder="e.g. 150">
                    </div>
                </div>

                <div style="margin-top: 1.25rem;">
                    <p class="text-muted" style="font-size: 0.8rem; margin-bottom: 0.75rem;">
                        Check all amenities that apply:
                    </p>
                    <div class="amenity-checkboxes">
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_free_parking" value="1">
                            <i class="fa-solid fa-square-parking"></i>
                            <span>Free Parking</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_paid_parking" value="1">
                            <i class="fa-solid fa-square-parking"></i>
                            <span>Paid Parking</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_laundry_free" value="1">
                            <i class="fa-solid fa-shirt"></i>
                            <span>In-Unit Laundry (Free)</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_laundry_paid" value="1">
                            <i class="fa-solid fa-shirt"></i>
                            <span>In-Unit Laundry (Paid)</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_dishwasher" value="1">
                            <i class="fa-solid fa-sink"></i>
                            <span>Dishwasher</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_air_conditioning" value="1">
                            <i class="fa-solid fa-snowflake"></i>
                            <span>A/C</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_pets_allowed" value="1">
                            <i class="fa-solid fa-paw"></i>
                            <span>Pets Allowed</span>
                        </label>
                        <label class="amenity-checkbox">
                            <input type="checkbox" name="amenity_furnished" value="1">
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
                       value="demo@uvm.edu" placeholder="your.email@uvm.edu" required>
            </div>

            <div class="form-group">
                <label for="contact_phone">Phone Number <span class="text-muted" style="font-weight: 400; text-transform: none;">(optional)</span></label>
                <input type="tel" id="contact_phone" name="contact_phone" placeholder="(802) 555-1234">
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
                    Post Listing
                </button>
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
        isEdit: false,
        lat: 44.477435,
        lon: -73.195323
    };
    window.DEMO_MODE = true;
</script>
<script src="<?= $basePath ?>js/app.js?v=<?= filemtime(__DIR__ . '/../js/app.js') ?>"></script>

<?php require_once 'includes/footer.php'; ?>
