<!-- top.php -->
<?php
$phpSelf = htmlspecialchars($_SERVER['PHP_SELF']);
$pathParts = pathinfo($phpSelf);
# session_start();
?>
<!DOCTYPE HTML>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>UVM Sublets</title>
    <link rel="icon" type="image/x" href="./public/images/favicon.ico">
    <meta name="author" content="Aaron Perkel">
    <meta name="description" content="DESC HERE">

    <meta name="viewport" content="width=device-width, 
        initial-scale=1.0">

    <link href="css/custom.css?version=<?php print time(); ?>" rel="stylesheet" type="text/css">

    <!-- LEAVE THESE HERE RN
        <link href="css/layout-desktop.css?version=" 
            rel="stylesheet" 
            type="text/css">

        <link href="css/layout-tablet.css?version" 
            media="(max-width: 820px)"
            rel="stylesheet" 
            type="text/css">

        <link href="css/layout-phone.css?version=" 
            media="(max-width: 430px)"
            rel="stylesheet" 
            type="text/css">
        -->

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.1/nouislider.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Initialize price slider
            var priceSlider = document.getElementById('price-slider');
            if (priceSlider) {
                noUiSlider.create(priceSlider, {
                    start: [0, 3000],
                    connect: true,
                    step: 50,
                    range: { 'min': 0, 'max': 3000 },
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
                const initialMaxPrice = <?php echo isset($_GET['max_price']) ? $_GET['max_price'] : 3000; ?>;
                priceSlider.noUiSlider.set([initialMinPrice, initialMaxPrice]);
            }

            // Initialize distance slider
            var distanceSlider = document.getElementById('distance-slider');
            if (distanceSlider) {
                noUiSlider.create(distanceSlider, {
                    start: [2],
                    connect: [true, false],
                    step: 0.5,
                    range: { 'min': 0.5, 'max': 20 },
                    format: {
                        to: value => value.toFixed(1) + ' mi',
                        from: value => Number(value.replace(' mi', ''))
                    }
                });

                // On update, update hidden field and visible range value
                distanceSlider.noUiSlider.on('update', function (values) {
                    var numericValue = parseFloat(values[0]); // Extract numeric value
                    document.getElementById('distance-value').innerText = ">" + numericValue + " mi";
                    document.getElementById('max_distance').value = numericValue;
                });

                // Set initial value from GET parameter (or default to 6)
                const initialDistance = <?php echo isset($_GET['max_distance']) && $_GET['max_distance'] !== '' ? $_GET['max_distance'] : 2; ?>;
                distanceSlider.noUiSlider.set([initialDistance]);
            }
        });
    </script>
</head>
<?php
print '<body class="' . $pathParts['filename'] . '">';
print '<!-- #################   Body element    ################# -->';
include 'connect-db.php';
include 'nav.php';
?>