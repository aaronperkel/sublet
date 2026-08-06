<?php
$basePath = '../';
require_once '../includes/header.php';

// Build filtered query. Listings in a deactivated semester are always excluded
// (see includes/visibility.php) regardless of the user's filter selections —
// build_listing_filters() applies that itself so map.php cannot diverge.
$columns = table_columns($pdo, 'sublets');
$filters = build_listing_filters($_GET, $columns);
$activeAmenities = $filters['amenities'];

// distance_mi is selected rather than only compared so "closest to campus" can
// sort on it and each card can carry it for the client-side re-sort.
$sql = "SELECT s.*, COALESCE(sem.name, s.semester) as semester_name, "
    . campus_distance_expr() . " as distance_mi "
    . "FROM sublets s LEFT JOIN semesters sem ON s.semester = sem.code";
$sql .= " WHERE " . implode(" AND ", $filters['where']);

[$orderBy, $sort] = listing_sort_sql($_GET['sort'] ?? null);
$sql .= $orderBy;

$stmt = $pdo->prepare($sql);
$stmt->execute($filters['params']);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasActiveFilters = $filters['active'];

// Get semester mapping for JS
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

    <?php /* Amenity toggles submit with the rest of the form. Rendered from
             LISTING_AMENITY_FILTERS so Browse and Map cannot offer different
             sets. Sort travels along as a hidden field so applying a filter
             does not silently reset it. */ ?>
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
            <a href="index.php" class="filter-clear"><i class="fa-solid fa-xmark"></i> Clear filters</a>
        <?php endif; ?>
    </div>
    <input type="hidden" name="sort" id="sortInput" value="<?= htmlspecialchars($sort) ?>">
</form>

<!-- Sort Bar -->
<div class="sort-bar">
    <span class="sort-bar-count">
        <?= count($sublets) ?> listing<?= count($sublets) !== 1 ? 's' : '' ?><?= $hasActiveFilters ? ' match these filters' : '' ?>
    </span>
    <div class="sort-bar-controls">
        <label for="sortFilter">Sort by</label>
        <select id="sortFilter">
            <?php foreach (LISTING_SORTS as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Listings Grid -->
<div class="listings-grid">
    <?php if (empty($sublets)): ?>
        <?php /* $availableSemesters is derived from visible listings only, so an
                 empty one means the site has nothing to show at all — a different
                 situation from filters that happen to match nothing, and the one
                 worth answering with a call to post rather than a dead end. */ ?>
        <?php if (empty($availableSemesters) && !$hasActiveFilters): ?>
            <div class="listings-empty">
                <i class="fa-solid fa-house-chimney"></i>
                <p>No listings are up yet for this semester.</p>
                <p class="listings-empty-sub">Subletting your place? Yours will be the first one people see.</p>
                <a href="post.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Post your sublet</a>
            </div>
        <?php else: ?>
            <div class="listings-empty">
                <i class="fa-solid fa-magnifying-glass"></i>
                <p>No sublets match these filters.</p>
                <p class="listings-empty-sub">Try widening the price or distance range.</p>
                <a href="index.php" class="btn btn-secondary">Clear filters</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($sublets as $sublet): ?>
            <?php
                // Build amenity tags for card
                $cardTags = [];
                if (!empty($sublet['amenity_free_parking'])) $cardTags[] = '<span class="utility-tag tag-included"><i class="fa-solid fa-square-parking"></i> Free Parking</span>';
                if (!empty($sublet['amenity_paid_parking'])) $cardTags[] = '<span class="utility-tag tag-tenant"><i class="fa-solid fa-square-parking"></i> Paid Parking</span>';
                if (!empty($sublet['amenity_laundry_free'])) $cardTags[] = '<span class="utility-tag tag-included"><i class="fa-solid fa-shirt"></i> Laundry</span>';
                if (!empty($sublet['amenity_laundry_paid'])) $cardTags[] = '<span class="utility-tag tag-tenant"><i class="fa-solid fa-shirt"></i> Laundry (Paid)</span>';
                if (!empty($sublet['amenity_pets_allowed'])) $cardTags[] = '<span class="utility-tag tag-included"><i class="fa-solid fa-paw"></i> Pets OK</span>';
                if (!empty($sublet['amenity_furnished'])) $cardTags[] = '<span class="utility-tag tag-included"><i class="fa-solid fa-couch"></i> Furnished</span>';
                if (!empty($sublet['amenity_air_conditioning'])) $cardTags[] = '<span class="utility-tag tag-included"><i class="fa-solid fa-snowflake"></i> A/C</span>';
                if (!empty($sublet['amenity_dishwasher'])) $cardTags[] = '<span class="utility-tag tag-included"><i class="fa-solid fa-sink"></i> Dishwasher</span>';
                if (!empty($sublet['utility_cost']) && $sublet['utility_cost'] > 0) $cardTags[] = '<span class="utility-tag"><i class="fa-solid fa-receipt"></i> ~$' . number_format($sublet['utility_cost']) . '/mo utils</span>';

                $displayAddress = format_address($sublet['address']);
                $sizeSummary = listing_size_summary($sublet);
                $prefLabel = option_label(ROOMMATE_PREFERENCE_OPTIONS, $sublet['roommate_preference'] ?? null);

                // '' is "open to anyone", which is the default and not worth a tag.
                if (!empty($sublet['roommate_preference']) && $prefLabel !== '') {
                    $cardTags[] = '<span class="utility-tag tag-preference"><i class="fa-solid fa-user-group"></i> Looking for: '
                        . htmlspecialchars($prefLabel) . '</span>';
                }
            ?>
            <?php /* Opened by a click handler in app.js, so it needs the role and
                     tab stop a <button> would have given it for free. */ ?>
            <div class="listing-card"
                 role="button"
                 tabindex="0"
                 aria-label="View listing at <?= htmlspecialchars($displayAddress) ?>, $<?= number_format($sublet['price']) ?> per month"
                 data-id="<?= $sublet['id'] ?>"
                 data-price="<?= $sublet['price'] ?>"
                 data-address="<?= htmlspecialchars($displayAddress) ?>"
                 data-semester="<?= htmlspecialchars($sublet['semester']) ?>"
                 data-semester-name="<?= htmlspecialchars($sublet['semester_name']) ?>"
                 data-description="<?= htmlspecialchars($sublet['description']) ?>"
                 <?php /* data-username stays the NetID — app.js compares it against
                          the signed-in user to decide who sees Edit. The display
                          name is a separate attribute, used only for labels. */ ?>
                 data-username="<?= htmlspecialchars($sublet['username']) ?>"
                 data-poster-name="<?= htmlspecialchars(poster_name($sublet)) ?>"
                 data-contact-email="<?= htmlspecialchars($sublet['contact_email'] ?? '') ?>"
                 data-contact-phone="<?= htmlspecialchars($sublet['contact_phone'] ?? '') ?>"
                 data-lat="<?= $sublet['lat'] ?>"
                 data-lon="<?= $sublet['lon'] ?>"
                 data-utility-electric="<?= htmlspecialchars($sublet['utility_electric'] ?? '') ?>"
                 data-utility-gas="<?= htmlspecialchars($sublet['utility_gas'] ?? '') ?>"
                 data-utility-water="<?= htmlspecialchars($sublet['utility_water'] ?? '') ?>"
                 data-utility-internet="<?= htmlspecialchars($sublet['utility_internet'] ?? '') ?>"
                 data-utility-cost="<?= htmlspecialchars($sublet['utility_cost'] ?? '') ?>"
                 data-amenity-free-parking="<?= $sublet['amenity_free_parking'] ?? 0 ?>"
                 data-amenity-paid-parking="<?= $sublet['amenity_paid_parking'] ?? 0 ?>"
                 data-amenity-laundry-free="<?= $sublet['amenity_laundry_free'] ?? 0 ?>"
                 data-amenity-laundry-paid="<?= $sublet['amenity_laundry_paid'] ?? 0 ?>"
                 data-amenity-dishwasher="<?= $sublet['amenity_dishwasher'] ?? 0 ?>"
                 data-amenity-air-conditioning="<?= $sublet['amenity_air_conditioning'] ?? 0 ?>"
                 data-amenity-pets-allowed="<?= $sublet['amenity_pets_allowed'] ?? 0 ?>"
                 data-amenity-furnished="<?= $sublet['amenity_furnished'] ?? 0 ?>"
                 data-distance="<?= isset($sublet['distance_mi']) ? round((float)$sublet['distance_mi'], 3) : '' ?>"
                 data-negotiable="<?= !empty($sublet['price_negotiable']) ? 1 : 0 ?>"
                 data-size-summary="<?= htmlspecialchars($sizeSummary) ?>"
                 data-roommate-gender="<?= htmlspecialchars(option_label(ROOMMATE_GENDER_OPTIONS, $sublet['roommate_gender'] ?? null)) ?>"
                 data-roommate-preference="<?= htmlspecialchars($prefLabel) ?>">
                <div class="card-image">
                    <img src="<?= htmlspecialchars($sublet['thumbnail_url'] ?: $sublet['image_url']) ?>" alt="Sublet at <?= htmlspecialchars($displayAddress) ?>" loading="lazy" onerror="this.style.display='none';var p=document.createElement('div');p.className='img-broken-placeholder';p.innerHTML='<i class=\'fa-solid fa-image\'></i><span>Image not available</span>';this.parentNode.appendChild(p);">
                    <span class="card-badge">
                        $<?= number_format($sublet['price']) ?><?php if (!empty($sublet['price_negotiable'])): ?><small class="card-badge-neg">or best offer</small><?php endif; ?>
                    </span>
                    <span class="card-semester"><?= htmlspecialchars($sublet['semester_name']) ?></span>
                </div>
                <div class="card-info">
                    <p class="card-address" title="<?= htmlspecialchars($sublet['address']) ?>"><?= htmlspecialchars($displayAddress) ?></p>
                    <?php if ($sizeSummary !== ''): ?>
                        <p class="card-size"><?= htmlspecialchars($sizeSummary) ?></p>
                    <?php endif; ?>
                    <p class="card-meta">
                        Posted by <?= htmlspecialchars(poster_name($sublet)) ?>
                        <?php if (isset($sublet['distance_mi'])): ?>
                            <span class="card-distance"><i class="fa-solid fa-location-arrow"></i> <?= number_format((float)$sublet['distance_mi'], 1) ?> mi</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!empty($cardTags)): ?>
                    <div class="card-utilities"><?= implode('', $cardTags) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
</script>
<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
