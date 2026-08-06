<?php
// Demo stub — return empty image list or success
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([]);
} else {
    echo json_encode(['success' => true, 'demo' => true]);
}
