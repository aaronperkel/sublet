<?php
$basePath = '../';
require_once '../includes/header.php';

// Same filters as index.php, from the same builder — see includes/listing_query.php.
$columns = table_columns($pdo, 'sublets');
$filters = build_listing_filters($_GET, $columns);
$activeAmenities = $filters['amenities'];
$hasActiveFilters = $filters['active'];

$sql = "SELECT s.*, COALESCE(sem.name, s.semester) as semester_name FROM sublets s LEFT JOIN semesters sem ON s.semester = sem.code";
$sql .= " WHERE " . implode(" AND ", $filters['where']);

$stmt = $pdo->prepare($sql);
$stmt->execute($filters['params']);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// The popup and modal only ever display the address, so hand JS the shortened
// form. The raw geocoder string stays in the database; post.php still edits it.
// Roommate codes become labels here for the same reason — app.js should not
// need its own copy of the vocabulary.
foreach ($sublets as &$s) {
    $s['address'] = format_address($s['address']);
    $s['size_summary'] = listing_size_summary($s);
    $s['roommate_gender_label'] = option_label(ROOMMATE_GENDER_OPTIONS, $s['roommate_gender'] ?? null);
    $s['roommate_preference_label'] = option_label(ROOMMATE_PREFERENCE_OPTIONS, $s['roommate_preference'] ?? null);
    // username stays as-is: app.js compares it to the signed-in user.
    $s['poster_name'] = poster_name($s);
}
unset($s);

$semesterMap = [];
foreach ($availableSemesters as $sem) {
    $semesterMap[$sem['semester']] = $sem['name'];
}
?>

<!-- Filters -->
<form id="filterForm" method="get" class="filters">
    <div class="filters-row">
        <div class="filter-group">
            <label>
                Price Range
                <span class="slider-value" id="priceValue"></span>
            </label>
            <div id="priceSlider"></div>
            <input type="hidden" name="min_price" id="minPrice">
            <input type="hidden" name="max_price" id="maxPrice">
        </div>
        <div class="filter-group">
            <label>Semester</label>
            <select name="semester" id="semesterFilter">
                <option value="">All Semesters</option>
                <?php foreach ($availableSemesters as $sem): ?>
                    <option value="<?= htmlspecialchars($sem['semester']) ?>" <?= (isset($_GET['semester']) && $_GET['semester'] === $sem['semester']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sem['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>
                Distance from Campus
                <span class="slider-value" id="distanceValue"></span>
            </label>
            <div id="distanceSlider"></div>
            <input type="hidden" name="max_distance" id="maxDistance">
        </div>
    </div>

    <?php /* Must match the set on index.php — both render from the same constant. */ ?>
    <div class="filter-chips">
        <span class="filter-chips-label">Must have</span>
        <?php foreach (LISTING_AMENITY_FILTERS as $key => $amenity): ?>
            <label class="filter-chip">
                <input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($key) ?>"
                       <?= in_array($key, $activeAmenities, true) ? 'checked' : '' ?>>
                <span><i class="fa-solid <?= htmlspecialchars($amenity['icon']) ?>"></i> <?= htmlspecialchars($amenity['label']) ?></span>
            </label>
        <?php endforeach; ?>
        <?php if (isset($columns['price_negotiable'])): ?>
            <label class="filter-chip">
                <input type="checkbox" name="negotiable" value="1" <?= !empty($_GET['negotiable']) ? 'checked' : '' ?>>
                <span><i class="fa-solid fa-tag"></i> Price negotiable</span>
            </label>
        <?php endif; ?>
        <?php if ($hasActiveFilters): ?>
            <a href="map.php" class="filter-clear"><i class="fa-solid fa-xmark"></i> Clear filters</a>
        <?php endif; ?>
    </div>
</form>

<div class="map-page-layout">
    <div id="mainMap"></div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal" role="dialog" aria-modal="true" aria-labelledby="modalPrice" aria-hidden="true">
    <div class="modal-container">
        <button class="modal-close" id="modalClose" aria-label="Close listing">&times;</button>
        <div class="modal-gallery" id="modalGallery">
            <img id="modalImage" src="" alt="Sublet image">
            <button class="gallery-nav prev" id="galleryPrev" aria-label="Previous photo"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="gallery-nav next" id="galleryNext" aria-label="Next photo"><i class="fa-solid fa-chevron-right"></i></button>
            <div class="gallery-dots" id="galleryDots"></div>
        </div>
        <div class="modal-details" id="modalDetails">
            <div class="modal-details-inner">
                <div class="modal-header">
                    <span class="modal-price" id="modalPrice"></span>
                    <div class="modal-actions" id="modalActions">
                        <button id="modalEmailBtn" class="btn btn-primary btn-sm" title="Email">
                            <i class="fa-solid fa-envelope"></i> Email
                        </button>
                        <button id="modalPhoneBtn" class="btn btn-primary btn-sm" title="Call" style="display:none;">
                            <i class="fa-solid fa-phone"></i> Call
                        </button>
                        <a id="modalEdit" href="post.php" class="btn btn-gold btn-sm" style="display:none;">
                            <i class="fa-solid fa-pen"></i> Edit
                        </a>
                        <?php if (is_admin()): ?>
                            <button id="modalDelete" class="btn btn-danger btn-sm">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="modal-field">
                        <i class="fa-solid fa-location-dot"></i>
                        <span id="modalAddress"></span>
                    </div>
                    <div class="modal-field">
                        <i class="fa-solid fa-calendar"></i>
                        <span id="modalSemester"></span>
                    </div>
                    <div class="modal-description" id="modalDescription"></div>
                    <div class="modal-poster" id="modalPoster"></div>
                </div>
            </div>
            <div class="modal-contact-panel" id="contactPanel">
                <button class="contact-back-btn" id="contactBackBtn">
                    <i class="fa-solid fa-arrow-left"></i> Back to listing
                </button>
                <h3 class="contact-panel-title" id="contactPanelTitle">Contact</h3>
                <div id="contactPanelBody"></div>
            </div>
        </div>
    </div>
</div>

<script>
    window.SUBLET_CONFIG = {
        maxPrice: <?= $maxPriceRounded ?>,
        maxDistance: <?= $maxDistanceRounded ?>,
        initialMinPrice: <?= isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0 ?>,
        initialMaxPrice: <?= isset($_GET['max_price']) ? (int)$_GET['max_price'] : $maxPriceRounded ?>,
        initialDistance: <?= isset($_GET['max_distance']) && $_GET['max_distance'] !== '' ? (float)$_GET['max_distance'] : $maxDistanceRounded ?>,
        semesterMap: <?= json_encode($semesterMap) ?>
    };
    window.MAP_SUBLETS = <?= json_encode($sublets) ?>;
</script>
<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
