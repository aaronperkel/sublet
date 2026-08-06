<?php
ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentUser = get_current_user_id();
$basePath = '../';

// Determine post button state
$postButtonText = 'New Post';
$postButtonLink = 'post.php';

// Get dynamic data for filters from demo table — bounds are derived only from
// listings the public can actually see (see includes/visibility.php).
$stmtMaxPrice = $pdo->query("SELECT MAX(s.price) as max_price FROM $SUBLET_TABLE s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE);
$maxPrice = $stmtMaxPrice->fetch(PDO::FETCH_ASSOC)['max_price'] ?? 3000;
$maxPriceRounded = max(ceil($maxPrice / 50) * 50, 100);

$stmtMaxDist = $pdo->query("SELECT MAX(3959 * acos(LEAST(1, cos(radians(44.477435)) * cos(radians(s.lat)) * cos(radians(s.lon) - radians(-73.195323)) + sin(radians(44.477435)) * sin(radians(s.lat))))) as max_distance FROM $SUBLET_TABLE s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE);
$maxDistance = $stmtMaxDist->fetch(PDO::FETCH_ASSOC)['max_distance'] ?? 20;
$maxDistanceRounded = max(ceil($maxDistance * 2) / 2, 1);

$stmtSemesters = $pdo->query("SELECT DISTINCT s.semester, COALESCE(sem.name, s.semester) as name FROM $SUBLET_TABLE s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE . " ORDER BY s.semester");
$availableSemesters = $stmtSemesters->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UVM Sublets — Demo</title>
    <meta name="description" content="Demo of UVM Sublets — browse sublet listings near campus.">
    <meta name="author" content="Aaron Perkel">
    <link rel="icon" type="image/svg+xml" href="<?= $basePath ?>assets/favicon.svg">
    <?php if ($currentPage === 'index' || $currentPage === 'map'): ?>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <?php endif; ?>
    <?php if ($currentPage === 'map' || $currentPage === 'post'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9/dist/leaflet.css" />
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $basePath ?>css/style.css?v=<?= filemtime(__DIR__ . '/../../css/style.css') ?>">
    <script src="https://kit.fontawesome.com/c428e5511d.js" crossorigin="anonymous"></script>
    <?php if ($currentPage === 'map' || $currentPage === 'post'): ?>
        <script src="https://unpkg.com/leaflet@1.9/dist/leaflet.js"></script>
    <?php endif; ?>
    <?php if ($currentPage === 'index' || $currentPage === 'map'): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>
    <?php endif; ?>
</head>
<body data-page="<?= $currentPage ?>" data-user="<?= htmlspecialchars($currentUser) ?>" data-admin="0">
    <!-- Demo Banner -->
    <div style="background: var(--gold); color: var(--green); text-align: center; padding: 0.5rem 1rem; font-size: 0.85rem; font-weight: 600;">
        <i class="fa-solid fa-flask"></i>
        You're viewing a demo with sample data.
        <a href="<?= $basePath ?>landing.php" style="color: var(--green); text-decoration: underline; margin-left: 0.5rem;">Back to home</a> |
        <a href="<?= $basePath ?>app/" style="color: var(--green); text-decoration: underline; margin-left: 0.5rem;">Sign in with UVM</a>
    </div>
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
                <span class="nav-user">
                    <i class="fa-solid fa-user"></i>
                    <?= htmlspecialchars($currentUser) ?>
                </span>
            </div>
        </div>
    </nav>
    <main class="main">
