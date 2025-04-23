<?php
ob_start();
$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']);
$pathParts = pathinfo($phpSelf);

include 'connect-db.php';

$_SERVER['REMOTE_USER'] = 'rpantale';

$_SERVER['REMOTE_USER'] = $_SERVER['REMOTE_USER'] ?? 'Guest';

// price slider bounds
$stmt = $pdo->query("SELECT MAX(price) as max_price FROM sublets");
$maxPriceRounded = ceil(($stmt->fetch()['max_price'] ?? 3000) / 50) * 50;

// distance slider bounds
$stmt = $pdo->query("
  SELECT MAX(
    3959 * acos(
      cos(radians(44.477435)) * cos(radians(lat)) *
      cos(radians(lon) - radians(-73.195323)) +
      sin(radians(44.477435)) * sin(radians(lat))
    )
  ) AS max_dist
  FROM sublets
");
$maxDistanceRounded = ceil((($stmt->fetch()['max_dist'] ?? 20) * 2)) / 2;

// distinct semesters
$stmt = $pdo->query("SELECT DISTINCT semester FROM sublets");
$semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>UVM Sublets</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="UVM students’ sublet listings.">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- noUiSlider & Leaflet (for map page) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>
    <?php if ($pathParts['filename'] === 'map'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php endif; ?>
    <script src="./js/main.js" defer></script>
</head>

<body class="<?= $pathParts['filename'] ?> bg-gray-50 text-gray-800" data-user="<?= $_SERVER['REMOTE_USER'] ?>">
    <?php include 'nav.php'; ?>