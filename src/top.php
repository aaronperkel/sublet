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
        <title>Sublet Website</title>
        <link rel="icon" type="image/x" href="./public/images/rallycat_final_icon.png">
        <meta name="author" content="Aaron Perkel">
        <meta name="description" content="DESC HERE">
        
        <meta name="viewport" content="width=device-width, 
        initial-scale=1.0">

        <link href="css/custom.css?version=<?php print time(); ?>" 
            rel="stylesheet" 
            type="text/css">

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

        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
            rel="stylesheet">

        <link rel="icon" href="./public/imagesfavicon.ico">
    </head>
    <?php
    print '<body class="' . $pathParts['filename'] . '">';
    print '<!-- #################   Body element    ################# -->';
    include 'connect-db.php';
    include 'nav.php';
    ?>