<?php

function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in() {
    return isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER']);
}

function get_current_user() {
    return $_SERVER['REMOTE_USER'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('HTTP/1.1 403 Forbidden');
        // Attempt to include a minimal HTML structure for the error, or keep it simple.
        echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Access Denied</title>";
        echo "<link rel='stylesheet' type='text/css' href='./css/base.css'>"; // Assuming base.css is relevant
        echo "</head><body style='padding: 20px; text-align: center;'>";
        echo "<h1>Access Denied</h1><p>A UVM NetID is required to access this page. Please ensure you are logged in through the UVM portal.</p>";
        echo "<p><a href='index.php'>Return to Homepage</a></p>";
        echo "</body></html>";
        exit;
    }
}

?>
