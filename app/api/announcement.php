<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$file = ROOT_DIR . '/data/announcement.json';

// GET — anyone can read the current announcement
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['active' => false, 'message' => '', 'style' => 'info']);
    }
    exit;
}

// POST — admin only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_same_origin();
    require_admin();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $message = trim($_POST['message'] ?? '');
        $style = $_POST['style'] ?? 'info';

        if ($message === '') {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }

        // Validate style
        $allowedStyles = ['info', 'warning', 'success'];
        if (!in_array($style, $allowedStyles)) {
            $style = 'info';
        }

        $data = [
            'active' => true,
            'message' => $message,
            'style' => $style,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Ensure data directory exists
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } elseif ($action === 'clear') {
        $data = [
            'active' => false,
            'message' => '',
            'style' => 'info',
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Anything other than GET/POST fell through returning an empty body, which the
// client parsed as a JSON error rather than a method problem.
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
