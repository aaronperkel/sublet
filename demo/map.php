<?php
require_once 'includes/header.php';

// Build filtered query (same as index.php)
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

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fix image paths for demo
foreach ($sublets as &$sublet) {
    if (!empty($sublet['image_url']) && strpos($sublet['image_url'], 'http') !== 0 && strpos($sublet['image_url'], '../') !== 0 && strpos($sublet['image_url'], '/') !== 0) {
        $sublet['image_url'] = '../' . $sublet['image_url'];
    }
    if (!empty($sublet['thumbnail_url']) && strpos($sublet['thumbnail_url'], 'http') !== 0 && strpos($sublet['thumbnail_url'], '../') !== 0 && strpos($sublet['thumbnail_url'], '/') !== 0) {
        $sublet['thumbnail_url'] = '../' . $sublet['thumbnail_url'];
    }
}
unset($sublet);

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
            <a href="map.php" class="btn btn-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="map-page-layout">
    <div id="mainMap"></div>
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
    window.MAP_SUBLETS = <?= json_encode($sublets) ?>;
    window.DEMO_MODE = true;
</script>
<script src="<?= $basePath ?>js/app.js?v=<?= filemtime(__DIR__ . '/../js/app.js') ?>"></script>

<?php require_once 'includes/footer.php'; ?>
