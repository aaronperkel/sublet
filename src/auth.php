<?php

if (!function_exists('start_session')) {
    function start_session() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SERVER['REMOTE_USER']) && !empty($_SERVER['REMOTE_USER']);
    }
}

if (!function_exists('get_current_user')) {
    function get_current_user() {
        // Line 13 would be here if comments/blank lines are minimal
        return $_SERVER['REMOTE_USER'] ?? null;
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            header('HTTP/1.1 403 Forbidden');
            echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Access Denied</title>";
            echo "<link rel='stylesheet' type='text/css' href='./css/base.css'>";
            echo "</head><body style='padding: 20px; text-align: center;'>";
            echo "<h1>Access Denied</h1><p>A UVM NetID is required to access this page. Please ensure you are logged in through the UVM portal.</p>";
            echo "<p><a href='index.php'>Return to Homepage</a></p>";
            echo "</body></html>";
            exit;
        }
    }
}

?>
