<?php

/**
 * Get current authenticated username from CAS/Apache.
 */
function get_current_user_id(): string {
    return $_SERVER['REMOTE_USER'] ?? '';
}

/**
 * Check if a user is logged in.
 */
function is_logged_in(): bool {
    return !empty(get_current_user_id());
}

/**
 * Check if current user is admin.
 */
function is_admin(): bool {
    return get_current_user_id() === 'aperkel';
}

/**
 * Require admin access or die.
 */
function require_admin(): void {
    if (!is_admin()) {
        http_response_code(403);
        die('Access denied.');
    }
}

/**
 * Reject state-changing requests that did not originate from this site.
 *
 * Authentication here is ambient: Apache/CAS identifies the user from browser
 * credentials, so *any* page on the internet could otherwise make a signed-in
 * user's browser POST to these endpoints (CSRF) — including the admin's, whose
 * endpoints delete every listing by a user or mail the whole user base.
 *
 * There is no PHP session to store a token in, so the request's own origin is
 * checked instead. Sec-Fetch-Site states the relationship directly and is sent
 * by every current browser; Origin/Referer is the fallback. A request carrying
 * none of the three is not a browser cross-site POST (curl and friends send no
 * ambient credentials), so it is allowed through rather than breaking clients.
 */
function require_same_origin(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return;
    }

    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if ($fetchSite !== '') {
        // 'none' = typed/bookmarked, 'same-origin' = our own pages.
        if ($fetchSite === 'same-origin' || $fetchSite === 'none') {
            return;
        }
        deny_cross_origin();
    }

    $source = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if ($source === '') {
        return;
    }

    $sourceHost = parse_url($source, PHP_URL_HOST) ?: '';
    $ownHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($sourceHost !== '' && strcasecmp($sourceHost, $ownHost) === 0) {
        return;
    }

    deny_cross_origin();
}

function deny_cross_origin(): void {
    http_response_code(403);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['error' => 'Cross-site request blocked.']));
}
