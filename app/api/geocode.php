<?php
/**
 * Nominatim geocoding proxy.
 * GET ?q=search+terms  → forward search
 */
header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
if (empty($query)) {
    echo json_encode([]);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $query,
    'format' => 'json',
    'limit' => 6,
    'countrycodes' => 'us',
    'addressdetails' => 1,
]);

$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: UVMSublets/1.0 (aperkel@uvm.edu)\r\n",
        'timeout' => 5,
    ]
]);

$response = @file_get_contents($url, false, $context);
if ($response === false) {
    echo json_encode([]);
    exit;
}

$results = json_decode($response, true);
if (!is_array($results)) {
    echo json_encode([]);
    exit;
}

// Simplify response
$output = [];
foreach ($results as $r) {
    $output[] = [
        'display_name' => $r['display_name'] ?? '',
        'lat' => $r['lat'] ?? 0,
        'lon' => $r['lon'] ?? 0,
    ];
}

echo json_encode($output);
