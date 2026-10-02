<?php
/**
 * The activity log: what happens to listings, counted per listing.
 *
 * One table, listing_events, written by app/api/events.php from beacons that
 * app.js sends (track()), and read back as totals by the admin Activity tab and
 * the poster's line on post.php. Nothing here ever says who did what: the actor
 * is stored only as actor_key(), and every reader counts distinct keys.
 *
 * The table is created by hand (the app's DB user has no CREATE grant), so
 * every caller checks table_exists() first and treats its absence as "nothing
 * recorded yet" rather than an error.
 */
require_once __DIR__ . '/share.php';   // share_secret()
require_once __DIR__ . '/auth.php';    // ADMIN_UID

/**
 * The day the log began. Views and shares only exist from here on, so a
 * listing posted in March has no view counts for March; the poster's line
 * counts from whichever is later, this or the day the listing went up.
 */
const LISTING_EVENTS_SINCE = '2026-10-02';

/**
 * Every type the endpoint accepts, and whether it is counted once per person,
 * listing and day. Opening things repeats harmlessly (a refresh, a second look)
 * and is deduplicated; acting on a listing is counted every time.
 */
const EVENT_TYPES = [
    'listing_open'  => true,
    'map_pin_open'  => true,
    'share_open'    => true,
    'share_arrival' => true,
    'share_target'  => false,
    'email_click'   => false,
    'call_click'    => false,
    'copy_email'    => false,
    'copy_phone'    => false,
    'copy_message'  => false,
    'mail_app'      => false,
    'dial'          => false,
    'text'          => false,
];

/**
 * Getting in touch, in any form. Tapping Email or Call only opens the contact
 * panel; the rest are the hand-off itself (the email app, the dialler, a copy).
 * Contacts are counted as people, so one person who taps three of these is one.
 */
const CONTACT_EVENT_TYPES = [
    'email_click', 'call_click', 'copy_email', 'copy_phone',
    'copy_message', 'mail_app', 'dial', 'text',
];

/** Posters' own events of these types still count: sharing your own listing
 *  is how most listings travel. Their own views and contacts do not. */
const SHARE_EVENT_TYPES = ['share_open', 'share_target'];

const EVENT_SOURCES = ['browse', 'map', 'share-link', 'deeplink', 'post'];

/** The share sheet's tiles (SHARE_TILES in app.js) plus its Copy button. */
const SHARE_TARGETS = ['native', 'instagram', 'snapchat', 'text', 'email', 'x', 'copy'];

/** Stored events per person per minute before the endpoint stops storing. */
const EVENTS_PER_MINUTE = 60;

/**
 * Who did it, as a key rather than a NetID. The 'actor:' prefix keeps it in a
 * different domain from share_token()'s 'sublet:' input under the same
 * secret, so the two can never produce each other.
 *
 * This is pseudonymous, not anonymous: anyone holding the secret can compute
 * a given NetID's key and look for it. What it prevents is the table answering
 * "who viewed this?" on its own.
 */
function actor_key(string $netid): string {
    return substr(hash_hmac('sha256', 'actor:' . $netid, share_secret()), 0, 16);
}

/**
 * Store one event if it should count. Returns what happened, for tests and
 * logs; the endpoint tells the browser nothing either way.
 *
 *   stored | duplicate | excluded | limited | invalid
 */
function record_event(PDO $pdo, string $actor, int $listingId, string $type, ?string $source = null, ?string $target = null): string {
    if ($actor === '' || $listingId <= 0 || !isset(EVENT_TYPES[$type])) {
        return 'invalid';
    }
    if ($source !== null && !in_array($source, EVENT_SOURCES, true)) {
        $source = null;
    }
    if ($type === 'share_target') {
        if (!in_array($target, SHARE_TARGETS, true)) {
            return 'invalid';
        }
    } else {
        $target = null;
    }

    // Poster and semester come from the listing itself, never the request,
    // as contact_log.php learned: otherwise anyone can attribute events to
    // a poster of their choosing.
    $stmt = $pdo->prepare("SELECT username, semester FROM sublets WHERE id = ?");
    $stmt->execute([$listingId]);
    $listing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$listing) {
        return 'invalid';
    }

    // The admin's testing is not interest, and neither is a poster looking at
    // their own listing. A poster sharing it is, though.
    if ($actor === ADMIN_UID) {
        return 'excluded';
    }
    if ($actor === $listing['username'] && !in_array($type, SHARE_EVENT_TYPES, true)) {
        return 'excluded';
    }

    $key = actor_key($actor);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM listing_events WHERE actor_key = ? AND created_at > NOW() - INTERVAL 1 MINUTE");
    $stmt->execute([$key]);
    if ((int)$stmt->fetchColumn() >= EVENTS_PER_MINUTE) {
        return 'limited';
    }

    $dedupe = EVENT_TYPES[$type]
        ? implode(':', [$type, $listingId, $key, date('Y-m-d')])
        : null;

    // ON DUPLICATE KEY rather than INSERT IGNORE, which would also swallow a
    // truncation or a bad value instead of only the repeat.
    $stmt = $pdo->prepare(
        "INSERT INTO listing_events (listing_id, poster_username, actor_key, semester, type, source, target, dedupe_key)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE id = id"
    );
    $stmt->execute([$listingId, $listing['username'], $key, $listing['semester'], $type, $source, $target, $dedupe]);

    return $stmt->rowCount() > 0 ? 'stored' : 'duplicate';
}

/**
 * The WHERE clause and parameters for a reporting window. $range is one of
 * 'open' (events whose semester is currently active), '30d', or 'all'.
 */
function activity_window(string $range): array {
    switch ($range) {
        case '30d':
            return ["e.created_at >= NOW() - INTERVAL 30 DAY", []];
        case 'all':
            return ["1 = 1", []];
        case 'open':
        default:
            return ["e.semester IN (SELECT code FROM semesters WHERE active = 1)", []];
    }
}

/**
 * Per-listing totals, keyed by listing_id. Everything about people is a count
 * of distinct actor keys:
 *
 *   views      people who opened the listing
 *   contacts   people who got in touch in any way (CONTACT_EVENT_TYPES)
 *   converted  people who did both, which is what conversion divides by views
 *              (contacts logged before the log began have no view to match)
 *   shares     share-target taps, counted every time
 *   arrivals   people who came in through a share link or deep link
 */
function listing_activity(PDO $pdo, string $where, array $params = []): array {
    $contactIn = "'" . implode("','", CONTACT_EVENT_TYPES) . "'";

    $stmt = $pdo->prepare(
        "SELECT e.listing_id,
                COUNT(DISTINCT CASE WHEN e.type = 'listing_open' THEN e.actor_key END) AS views,
                COUNT(DISTINCT CASE WHEN e.type IN ($contactIn) THEN e.actor_key END) AS contacts,
                SUM(e.type = 'share_target') AS shares,
                COUNT(DISTINCT CASE WHEN e.type = 'share_arrival' THEN e.actor_key END) AS arrivals
         FROM listing_events e
         WHERE $where
         GROUP BY e.listing_id"
    );
    $stmt->execute($params);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[(int)$row['listing_id']] = [
            'views' => (int)$row['views'],
            'contacts' => (int)$row['contacts'],
            'converted' => 0,
            'shares' => (int)$row['shares'],
            'arrivals' => (int)$row['arrivals'],
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT listing_id, COUNT(*) AS converted FROM (
             SELECT e.listing_id, e.actor_key
             FROM listing_events e
             WHERE $where
             GROUP BY e.listing_id, e.actor_key
             HAVING SUM(e.type = 'listing_open') > 0 AND SUM(e.type IN ($contactIn)) > 0
         ) t
         GROUP BY listing_id"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($out[(int)$row['listing_id']])) {
            $out[(int)$row['listing_id']]['converted'] = (int)$row['converted'];
        }
    }

    return $out;
}

/** Share-target taps by target, most used first. */
function share_target_counts(PDO $pdo, string $where, array $params = []): array {
    $stmt = $pdo->prepare(
        "SELECT e.target, COUNT(*) AS n FROM listing_events e
         WHERE e.type = 'share_target' AND $where
         GROUP BY e.target ORDER BY n DESC"
    );
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

/** Listing views by where they started (browse, map, share-link, ...). */
function view_source_counts(PDO $pdo, string $where, array $params = []): array {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(e.source, 'unknown') AS source, COUNT(*) AS n FROM listing_events e
         WHERE e.type = 'listing_open' AND $where
         GROUP BY source ORDER BY n DESC"
    );
    $stmt->execute($params);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

/**
 * People viewing and getting in touch per day for the last $days days,
 * oldest first, with empty days filled in. A person counts once per listing
 * per day.
 */
function daily_activity(PDO $pdo, int $days): array {
    $contactIn = "'" . implode("','", CONTACT_EVENT_TYPES) . "'";
    $stmt = $pdo->prepare(
        "SELECT DATE(e.created_at) AS day,
                COUNT(DISTINCT CASE WHEN e.type = 'listing_open' THEN CONCAT(e.listing_id, ':', e.actor_key) END) AS views,
                COUNT(DISTINCT CASE WHEN e.type IN ($contactIn) THEN CONCAT(e.listing_id, ':', e.actor_key) END) AS contacts
         FROM listing_events e
         WHERE e.created_at >= CURDATE() - INTERVAL ? DAY
         GROUP BY day"
    );
    $stmt->execute([$days - 1]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[$row['day']] = ['views' => (int)$row['views'], 'contacts' => (int)$row['contacts']];
    }

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $out[$day] = $rows[$day] ?? ['views' => 0, 'contacts' => 0];
    }
    return $out;
}
