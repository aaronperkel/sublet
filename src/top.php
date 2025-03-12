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
            // Price Slider
            var priceSlider = document.getElementById('price-slider');
            if (priceSlider) {
                noUiSlider.create(priceSlider, {
                    start: [0, 1500],
                    connect: true,
                    step: 50,
                    range: { 'min': 0, 'max': 3000 },
                    format: {
                        to: value => '$' + Math.round(value),
                        from: value => Number(value.replace('$', ''))
                    }
                });
                priceSlider.noUiSlider.on('update', function (values) {
                    document.getElementById('price-range-value').innerText = values.join(' - ');
                });
            }

            // Distance Slider
            var distanceSlider = document.getElementById('distance-slider');
            if (distanceSlider) {
                noUiSlider.create(distanceSlider, {
                    start: [6],
                    connect: [true, false],
                    step: 2,
                    range: { 'min': 0, 'max': 20 },
                    format: {
                        to: value => Math.round(value) + ' mi',
                        from: value => Number(value.replace(' mi', ''))
                    }
                });
                distanceSlider.noUiSlider.on('update', function (values) {
                    document.getElementById('distance-value').innerText = ">" + values[0];
                });
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