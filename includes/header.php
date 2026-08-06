<?php
ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = get_current_user_id();

// Base path for browser-relative URLs (set by page files in subdirectories)
if (!isset($basePath)) {
    $basePath = './';
}

// Determine post button state
$postButtonText = 'New Post';
$postButtonLink = 'post.php';
if (is_logged_in()) {
    $stmtNav = $pdo->prepare("SELECT id FROM sublets WHERE username = ?");
    $stmtNav->execute([$currentUser]);
    if ($stmtNav->rowCount() > 0) {
        $postButtonText = 'My Post';
    }
}

// Get dynamic data for filters — bounds are derived only from listings the
// public can actually see, so a deactivated semester can't stretch a slider.
$stmtMaxPrice = $pdo->query("SELECT MAX(s.price) as max_price FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE);
$maxPrice = $stmtMaxPrice->fetch(PDO::FETCH_ASSOC)['max_price'] ?? 3000;
$maxPriceRounded = max(ceil($maxPrice / 50) * 50, 100);

$stmtMaxDist = $pdo->query("SELECT MAX(3959 * acos(LEAST(1, cos(radians(44.477435)) * cos(radians(s.lat)) * cos(radians(s.lon) - radians(-73.195323)) + sin(radians(44.477435)) * sin(radians(s.lat))))) as max_distance FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE);
$maxDistance = $stmtMaxDist->fetch(PDO::FETCH_ASSOC)['max_distance'] ?? 20;
$maxDistanceRounded = max(ceil($maxDistance * 2) / 2, 1);

$stmtSemesters = $pdo->query("SELECT DISTINCT s.semester, COALESCE(sem.name, s.semester) as name FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE . " ORDER BY s.semester");
$availableSemesters = $stmtSemesters->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UVM Sublets</title>
    <meta name="description" content="Find and post sublet listings exclusively for UVM students.">
    <meta name="author" content="Aaron Perkel">
    <link rel="icon" type="image/svg+xml" href="<?= $basePath ?>assets/favicon.svg">
    <?php if ($currentPage === 'index' || $currentPage === 'map'): ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <?php endif; ?>
    <?php if ($currentPage === 'map' || $currentPage === 'post'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $basePath ?>css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <script src="https://kit.fontawesome.com/c428e5511d.js" crossorigin="anonymous"></script>
    <?php if ($currentPage === 'map' || $currentPage === 'post'): ?>
        <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
    <?php endif; ?>
    <?php if ($currentPage === 'index' || $currentPage === 'map'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>
    <?php endif; ?>
</head>
<body data-page="<?= $currentPage ?>" data-user="<?= htmlspecialchars($currentUser ?: 'Guest') ?>" data-admin="<?= is_admin() ? '1' : '0' ?>">
    <nav class="nav">
        <div class="nav-inner">
            <a href="index.php" class="nav-brand">
                <span class="nav-logo">
                    <i class="fa-solid fa-house"></i>
                </span>
                <span class="nav-title">UVM Sublets</span>
            </a>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="nav-menu" id="navMenu">
                <a href="index.php" class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>">Browse</a>
                <a href="map.php" class="nav-link <?= $currentPage === 'map' ? 'active' : '' ?>">Map</a>
                <a href="post.php" class="nav-link <?= $currentPage === 'post' ? 'active' : '' ?>"><?= $postButtonText ?></a>
                <?php if (is_admin()): ?>
                    <a href="admin.php" class="nav-link <?= $currentPage === 'admin' ? 'active' : '' ?>">Admin</a>
                <?php endif; ?>
                <span class="nav-user">
                    <i class="fa-solid fa-user"></i>
                    <?= htmlspecialchars($currentUser ?: 'Guest') ?>
                </span>
            </div>
        </div>
    </nav>
    <?php
    $announcementFile = __DIR__ . '/../data/announcement.json';
    if (file_exists($announcementFile)) {
        $announcement = json_decode(file_get_contents($announcementFile), true);
        if (!empty($announcement['active']) && !empty($announcement['message'])):
            $aStyle = $announcement['style'] ?? 'info';
    ?>
    <div class="announcement-banner announcement-<?= htmlspecialchars($aStyle) ?>" id="announcementBanner">
        <div class="announcement-inner">
            <span class="announcement-text">
                <?php if ($aStyle === 'warning'): ?><i class="fa-solid fa-triangle-exclamation"></i>
                <?php elseif ($aStyle === 'success'): ?><i class="fa-solid fa-circle-check"></i>
                <?php else: ?><i class="fa-solid fa-bullhorn"></i>
                <?php endif; ?>
                <?php
                    // Escape HTML, then convert newlines to <br> and auto-link URLs
                    $safe = htmlspecialchars($announcement['message']);
                    $safe = nl2br($safe);
                    $safe = preg_replace('/(https?:\/\/[^\s<]+)/', '<a href="$1" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">$1</a>', $safe);
                    echo $safe;
                ?>
            </span>
            <button class="announcement-dismiss" onclick="this.parentNode.parentNode.style.display='none'" aria-label="Dismiss">&times;</button>
        </div>
    </div>
    <?php
        endif;
    }
    ?>
    <main class="main">
