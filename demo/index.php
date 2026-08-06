<?php
require_once 'includes/header.php';

// Build filtered query. Listings in a deactivated semester are always excluded
// (see includes/visibility.php) regardless of the user's filter selections.
$filters = [VISIBLE_SEMESTER_WHERE];
$params = [];

if (isset($_GET['min_price'], $_GET['max_price']) && $_GET['min_price'] !== '' && $_GET['max_price'] !== '') {
    $filters[] = "price BETWEEN ? AND ?";
    $params[] = $_GET['min_price'];
    $params[] = $_GET['max_price'];
}

if (!empty($_GET['semester'])) {
    $filters[] = "semester = ?";
    $params[] = $_GET['semester'];
}

if (isset($_GET['max_distance']) && $_GET['max_distance'] !== '') {
    $filters[] = "3959 * acos(LEAST(1, cos(radians(44.477435)) * cos(radians(lat)) * cos(radians(lon) - radians(-73.195323)) + sin(radians(44.477435)) * sin(radians(lat)))) <= ?";
    $params[] = $_GET['max_distance'];
}

$sql = "SELECT s.*, COALESCE(sem.name, s.semester) as semester_name FROM $SUBLET_TABLE s LEFT JOIN semesters sem ON s.semester = sem.code";
if ($filters) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}
// Sort
$sort = $_GET['sort'] ?? '';
switch ($sort) {
    case 'price_asc':
        $sql .= " ORDER BY s.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY s.price DESC";
        break;
    case 'newest':
        $sql .= " ORDER BY s.id DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY s.id ASC";
        break;
    default:
        $sql .= " ORDER BY s.id DESC";
        $sort = 'newest';
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i> Apply
            </button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </div>
    </div>
</form>

<!-- Sort Bar -->
<div class="sort-bar">
    <span class="sort-bar-count"><?= count($sublets) ?> listing<?= count($sublets) !== 1 ? 's' : '' ?></span>
    <div class="sort-bar-controls">
        <label for="sortFilter">Sort by</label>
        <select id="sortFilter">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
        </select>
    </div>
</div>

<!-- Listings Grid -->
<div class="listings-grid">
    <?php if (empty($sublets)): ?>
        <div class="listings-empty">
            <i class="fa-solid fa-magnifying-glass"></i>
            <p>No sublets found matching your filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($sublets as $sublet): ?>
            <?php
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

                // Fix image paths for demo (prepend ../ if relative)
                $imgSrc = $sublet['thumbnail_url'] ?: $sublet['image_url'];
                if ($imgSrc && strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '../') !== 0 && strpos($imgSrc, '/') !== 0) {
                    $imgSrc = '../' . $imgSrc;
                }
            ?>
            <div class="listing-card"
                 data-id="<?= $sublet['id'] ?>"
                 data-price="<?= $sublet['price'] ?>"
                 data-address="<?= htmlspecialchars($sublet['address']) ?>"
                 data-semester="<?= htmlspecialchars($sublet['semester']) ?>"
                 data-semester-name="<?= htmlspecialchars($sublet['semester_name']) ?>"
                 data-description="<?= htmlspecialchars($sublet['description']) ?>"
                 data-username="<?= htmlspecialchars($sublet['username']) ?>"
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
                 data-amenity-furnished="<?= $sublet['amenity_furnished'] ?? 0 ?>">
                <div class="card-image">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Sublet at <?= htmlspecialchars($sublet['address']) ?>" loading="lazy" onerror="this.style.display='none';var p=document.createElement('div');p.className='img-broken-placeholder';p.innerHTML='<i class=\'fa-solid fa-image\'></i><span>Image not available</span>';this.parentNode.appendChild(p);">
                    <span class="card-badge">$<?= number_format($sublet['price']) ?></span>
                    <span class="card-semester"><?= htmlspecialchars($sublet['semester_name']) ?></span>
                </div>
                <div class="card-info">
                    <p class="card-address"><?= htmlspecialchars($sublet['address']) ?></p>
                    <p class="card-meta">Posted by <?= htmlspecialchars($sublet['username']) ?></p>
                </div>
                <?php if (!empty($cardTags)): ?>
                    <div class="card-utilities"><?= implode('', $cardTags) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal">
    <div class="modal-container">
        <button class="modal-close" id="modalClose">&times;</button>
        <div class="modal-gallery" id="modalGallery">
            <img id="modalImage" src="" alt="Sublet image">
            <button class="gallery-nav prev" id="galleryPrev"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="gallery-nav next" id="galleryNext"><i class="fa-solid fa-chevron-right"></i></button>
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
    window.DEMO_MODE = true;
</script>
<script src="<?= $basePath ?>js/app.js?v=<?= filemtime(__DIR__ . '/../js/app.js') ?>"></script>

<?php require_once 'includes/footer.php'; ?>
