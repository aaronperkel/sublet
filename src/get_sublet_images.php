<?php
include 'connect-db.php'; // Ensures $pdo is available
require_once 'db_operations.php'; // For getSubletImages and getSubletById

// Input validation for id
$sublet_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($sublet_id === false || $sublet_id === null) {
    // http_response_code(400); // Bad Request - Consider proper error handling if needed
    echo json_encode([]); // Return empty array for invalid ID
    exit;
}

// $pdo is from connect-db.php
$images = getSubletImages($pdo, $sublet_id);

// If $images is empty and you want to include the main sublet image as a fallback:
if (empty($images)) {
    $mainSubletData = getSubletById($pdo, $sublet_id); // Fetches the main sublet record
    if ($mainSubletData && !empty($mainSubletData['image_url'])) {
        $images[] = $mainSubletData['image_url'];
    }
}

header('Content-Type: application/json');
echo json_encode($images);
?>