<?php
$basePath = '../';
require_once '../includes/header.php';
require_once '../includes/share.php';
require_once '../includes/thumbnail.php';

// Same filters as index.php, from the same builder — see includes/listing_query.php.
$columns = table_columns($pdo, 'sublets');
$filters = build_listing_filters($_GET, $columns);

// distance_mi for the listing view's facts line, as on Browse.
$sql = "SELECT s.*, COALESCE(sem.name, s.semester) as semester_name, "
    . campus_distance_expr() . " as distance_mi "
    . "FROM sublets s LEFT JOIN semesters sem ON s.semester = sem.code";
$sql .= " WHERE " . implode(" AND ", $filters['where']);

$stmt = $pdo->prepare($sql);
$stmt->execute($filters['params']);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// app.js builds the popup and modal image tags straight out of these values, and a
// page-relative path would resolve against /app/ and get gated on CAS. Hand it
// root-relative URLs instead — see image_src() in includes/db.php. The modal
// opens on display_url, so tapping a pin does not start a 20 MB download.
foreach ($sublets as &$sublet) {
    $sublet['display_url'] = display_src($sublet['image_url'] ?? null);
    $sublet['image_url'] = image_src($sublet['image_url'] ?? null);
    $sublet['thumbnail_url'] = image_src($sublet['thumbnail_url'] ?? null);
}
unset($sublet);

// The popup and modal only ever display the address, so hand JS the shortened
// form. The raw geocoder string stays in the database; post.php still edits it.
// Roommate codes become labels here for the same reason — app.js should not
// need its own copy of the vocabulary.
foreach ($sublets as &$s) {
    // Before the address is shortened: the maps links want all of it.
    $mapLinks = listing_map_links($s['address'], $s['lat'], $s['lon']);
    $s['maps_url'] = $mapLinks['google'];
    $s['apple_maps_url'] = $mapLinks['apple'];
    $s['address'] = format_address($s['address']);
    $s['size_summary'] = listing_size_summary($s);
    $s['roommate_gender_label'] = option_label(ROOMMATE_GENDER_OPTIONS, $s['roommate_gender'] ?? null);
    $s['roommate_preference_label'] = option_label(ROOMMATE_PREFERENCE_OPTIONS, $s['roommate_preference'] ?? null);
    // username stays as-is: app.js compares it to the signed-in user.
    $s['poster_name'] = poster_name($s);
    $s['posted_ago'] = posted_ago($s['posted_at'] ?? null);
    // The public /s/ link, built here rather than in JS so the HMAC secret
    // never has to reach the client. index.php carries the same value as
    // data-share-url on each card.
    $s['share_url'] = share_url((int)$s['id']);
}
unset($s);

$semesterMap = [];
foreach ($availableSemesters as $sem) {
    $semesterMap[$sem['semester']] = $sem['name'];
}
?>

<?php require '../includes/filter_bar.php'; ?>

<?php /* The pins are the page, so the heading is for screen readers only. */ ?>
<h1 class="sr-only">Map of <?= count($sublets) ?> sublet<?= count($sublets) === 1 ? '' : 's' ?></h1>

<div class="map-page-layout">
    <div id="mainMap"></div>
</div>

<?php require '../includes/listing_modal.php'; ?>

<?php require_once '../includes/share_sheet.php'; ?>

<script>
    window.SUBLET_CONFIG = {
        maxPrice: <?= $maxPriceRounded ?>,
        maxDistance: <?= $maxDistanceRounded ?>,
        minPrice: <?= $minPriceRounded ?>,
        initialMinPrice: <?= isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (int)$_GET['min_price'] : $minPriceRounded ?>,
        initialMaxPrice: <?= isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (int)$_GET['max_price'] : $maxPriceRounded ?>,
        initialDistance: <?= isset($_GET['max_distance']) && $_GET['max_distance'] !== '' ? (float)$_GET['max_distance'] : $maxDistanceRounded ?>,
        semesterMap: <?= json_encode($semesterMap) ?>
    };
    window.MAP_SUBLETS = <?= json_encode($sublets) ?>;
</script>
<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
