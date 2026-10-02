<?php
/**
 * Activity beacon. POST only, from app.js's track() via navigator.sendBeacon.
 *
 *   type, listing_id, source?, target?
 *
 * Always answers 204 with no body (429 when rate-limited), whether or not the
 * event was stored: what counts, and why, is decided in record_event()
 * (includes/events.php) and is nobody's business but the log's.
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/events.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

// A beacon is a state-changing POST like any other; sendBeacon sends
// Sec-Fetch-Site: same-origin from our own pages.
require_same_origin();

$actor = get_current_user_id();
if (!$actor || !table_exists($pdo, 'listing_events')) {
    http_response_code(204);
    exit;
}

$source = isset($_POST['source']) && is_string($_POST['source']) ? $_POST['source'] : null;
$target = isset($_POST['target']) && is_string($_POST['target']) ? $_POST['target'] : null;

$result = record_event(
    $pdo,
    $actor,
    (int)($_POST['listing_id'] ?? 0),
    is_string($_POST['type'] ?? null) ? $_POST['type'] : '',
    $source,
    $target
);

http_response_code($result === 'limited' ? 429 : 204);
