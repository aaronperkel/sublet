<?php

function get_current_user_id(): string {
    return 'DemoUser';
}

function is_logged_in(): bool {
    return true;
}

function is_admin(): bool {
    return false;
}

function require_admin(): void {
    http_response_code(403);
    die('Access denied.');
}
