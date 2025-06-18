<!-- top.php -->
<?php
require_once 'auth.php'; // Added for authentication
start_session(); // Added for session management

$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']);
$pathParts = pathinfo($phpSelf);

include 'connect-db.php'; // connect-db after auth
require_once 'db_operations.php'; // Include the new DB operations file
require_once 'config.php'; // Include the new config file

// Fetch filter data using the new function
// $pdo is available from connect-db.php which is included before this
$filterData = getFilterData($pdo);
$maxPriceRounded = $filterData['maxPriceRounded'];
$maxDistanceRounded = $filterData['maxDistanceRounded'];
$semesters = $filterData['semesters'];
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>UVM Sublets</title>
    <link rel="icon" type="image/x" href="./public/images/favicon.ico">
    <meta name="author" content="Aaron Perkel">
    <meta name="description" content="A platform created exclusively for UVM students to post and find sublet listings.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- New CSS files -->
    <link rel="stylesheet" type="text/css" href="./css/base.css?version=<?= CSS_VERSION ?>">
    <link rel="stylesheet" type="text/css" href="./css/components.css?version=<?= CSS_VERSION ?>">
    <link rel="stylesheet" type="text/css" href="./css/form.css?version=<?= CSS_VERSION ?>">
    <link rel="stylesheet" type="text/css" href="./css/grid.css?version=<?= CSS_VERSION ?>">
    <link rel="stylesheet" type="text/css" href="./css/responsive.css?version=<?= CSS_VERSION ?>">

    <!-- Other external CSS/JS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>
    <script src="https://kit.fontawesome.com/c428e5511d.js" crossorigin="anonymous"></script>

    <?php if ($pathParts['filename'] === 'map'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <?php endif; ?>

    <script src="./js/main.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Initialize price slider
            var priceSlider = document.getElementById('price-slider');
            if (priceSlider) {
                noUiSlider.create(priceSlider, {
                    start: [0, <?php echo $maxPriceRounded; ?>],
                    connect: true,
                    step: 50,
                    range: { 'min': 0, 'max': <?php echo $maxPriceRounded; ?> },
                    format: {
                        to: value => '$' + Math.round(value),
                        from: value => Number(value.replace('$', ''))
                    }
                });

                // On update, update hidden fields and the visible range value
                priceSlider.noUiSlider.on('update', function (values, handle) {
                    document.getElementById('price-range-value').innerText = values.join(' - ');
                    if (handle === 0) {
                        document.getElementById('min_price').value = Math.round(values[0].replace('$', ''));
                    } else {
                        document.getElementById('max_price').value = Math.round(values[1].replace('$', ''));
                    }
                });

                // Set initial slider values from GET parameters if available
                const initialMinPrice = <?php echo isset($_GET['min_price']) ? $_GET['min_price'] : 0; ?>;
                const initialMaxPrice = <?php echo isset($_GET['max_price']) ? $_GET['max_price'] : $maxPriceRounded; ?>;
                priceSlider.noUiSlider.set([initialMinPrice, initialMaxPrice]);
            }

            var distanceSlider = document.getElementById('distance-slider');
            if (distanceSlider) {
                noUiSlider.create(distanceSlider, {
                    start: [<?php echo $maxDistanceRounded; ?>],
                    connect: [true, false],
                    step: 0.5,
                    range: { 'min': 0.5, 'max': <?php echo $maxDistanceRounded; ?> },
                    format: {
                        to: value => value.toFixed(1) + ' mi',
                        from: value => Number(value.replace(' mi', ''))
                    }
                });
                distanceSlider.noUiSlider.on('update', function (values) {
                    var numericValue = parseFloat(values[0]);
                    document.getElementById('distance-value').innerText = "<" + numericValue + " mi";
                    document.getElementById('max_distance').value = numericValue;
                });
                const initialDistance = <?php echo isset($_GET['max_distance']) && $_GET['max_distance'] !== '' ? $_GET['max_distance'] : $maxDistanceRounded; ?>;
                distanceSlider.noUiSlider.set([initialDistance]);
            }
        });
    </script>
</head>
<?php
print '<body class="' . $pathParts['filename'] . '" data-user="' . htmlspecialchars(get_current_user() ?? 'Guest') . '">';
print '<!-- #################   Body element    ################# -->';
include 'nav.php';
?>