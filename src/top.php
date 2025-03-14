<!-- top.php -->
<?php
ob_start();
$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']);
$pathParts = pathinfo($phpSelf);

include 'connect-db.php';

// Query maximum price and round up to nearest 50
$stmt = $pdo->query("SELECT MAX(price) as max_price FROM sublets");
$maxPriceResult = $stmt->fetch(PDO::FETCH_ASSOC);
$maxPrice = $maxPriceResult['max_price'] ?? 3000;
$maxPriceRounded = ceil($maxPrice / 50) * 50;

// Query maximum distance from campus using the Haversine formula
$stmtDistance = $pdo->query("SELECT MAX(3959 * acos(cos(radians(44.477435)) * cos(radians(lat)) * cos(radians(lon) - radians(-73.195323)) + sin(radians(44.477435)) * sin(radians(lat)))) as max_distance FROM sublets");
$distanceResult = $stmtDistance->fetch(PDO::FETCH_ASSOC);
$maxDistance = $distanceResult['max_distance'] ?? 20;
$maxDistanceRounded = ceil($maxDistance * 2) / 2;
if ($maxDistanceRounded < 20) {
    $maxDistanceRounded = 20;
}

// Query distinct semesters available
$stmtSem = $pdo->query("SELECT DISTINCT semester FROM sublets");
$semesters = $stmtSem->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>UVM Sublets</title>
    <link rel="icon" type="image/x" href="./public/images/favicon.ico">
    <meta name="author" content="Aaron Perkel">
    <meta name="description" content="DESC HERE">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- New CSS files -->
    <link rel="stylesheet" href="./css/base.css">
    <link rel="stylesheet" href="./css/components.css">
    <link rel="stylesheet" href="./css/form.css">
    <link rel="stylesheet" href="./css/grid.css">
    <link rel="stylesheet" href="./css/responsive.css">

    <!-- Other external CSS/JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>

    <!-- <link href="css/custom.css?version=<?php print time(); ?>" rel="stylesheet" type="text/css"> -->

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Initialize price slider
            var priceSlider = document.getElementById('price-slider');
            if (priceSlider) {
                noUiSlider.create(priceSlider, {
                    start: [800, 1500],
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
                const initialMinPrice = <?php echo isset($_GET['min_price']) ? $_GET['min_price'] : 800; ?>;
                const initialMaxPrice = <?php echo isset($_GET['max_price']) ? $_GET['max_price'] : 1500; ?>;
                priceSlider.noUiSlider.set([initialMinPrice, initialMaxPrice]);
            }

            var distanceSlider = document.getElementById('distance-slider');
            if (distanceSlider) {
                noUiSlider.create(distanceSlider, {
                    start: [5.5],
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
                    document.getElementById('distance-value').innerText = ">" + numericValue + " mi";
                    document.getElementById('max_distance').value = numericValue;
                });
                const initialDistance = <?php echo isset($_GET['max_distance']) && $_GET['max_distance'] !== '' ? $_GET['max_distance'] : 5.5; ?>;
                distanceSlider.noUiSlider.set([initialDistance]);
            }
        });
    </script>
</head>
<?php
print '<body class="' . $pathParts['filename'] . '" data-user="' . ($_SERVER['REMOTE_USER'] ?? 'Guest') . '">';
print '<!-- #################   Body element    ################# -->';
include 'nav.php';
?>