<?php
/**
 * Share links for a listing.
 *
 * A shared link cannot point into app/. That directory is AuthType CAS, so the
 * preview crawlers behind iMessage, Instagram, Snapchat and Discord arrive
 * without a session, get the 302 to idp.uvm.edu, and never see any HTML — the
 * link unfurls into nothing. So the URL that leaves this site is a public one
 * (/s/<id>-<token>, served by s.php) which carries only the meta tags and then
 * hands off to app/ behind SSO.
 *
 * Everything here is pure. s.php and share-card.php both use it and neither
 * depends on the other; listing_fields.php is the only include, for
 * listing_size_summary(), and it has no dependencies of its own.
 */
require_once __DIR__ . '/listing_fields.php';

/**
 * The host share links are built against.
 *
 * Hardcoded for the same reason landing.php hardcodes og:url: og:image and
 * og:url have to be absolute, and a link copied from any hostname still has to
 * unfurl. $_SERVER['HTTP_HOST'] would put whatever the request claimed into a
 * tag that third-party crawlers then fetch.
 *
 * This stays the real hostname even though go.uvm.edu/sublet is the link we
 * advertise, because the short link is a single redirect and not a prefix:
 * go.uvm.edu/sublet 302s to this origin's root, but go.uvm.edu/sublet/s/<slug>
 * 302s to go.uvm.edu's own home page. Building /s/ or /app/ URLs on top of it
 * would send every share link to the wrong site. Use SHARE_SHORT_URL where a
 * link only has to reach the front door, and SHARE_DISPLAY_URL where a human
 * reads or retypes it.
 */
const SHARE_ORIGIN = 'https://sublet.aperkel.w3.uvm.edu';

/**
 * The link we publish, and the way it is written when someone reads it.
 *
 * go.uvm.edu/sublet is the university's own short link for this site. It is
 * what belongs on anything public — the story graphics, the link previews, the
 * email footer — because it is short enough to be retyped off a phone screen
 * and it survives the app moving off a personal w3 hostname later.
 *
 * DISPLAY is the bare form painted into artwork, where "https://" is noise.
 */
const SHARE_SHORT_URL = 'https://go.uvm.edu/sublet';
const SHARE_DISPLAY_URL = 'go.uvm.edu/sublet';

/** Characters of HMAC kept in a share token. 40 bits, ~10^12 guesses. */
const SHARE_TOKEN_LENGTH = 10;

/**
 * The key share tokens are derived from.
 *
 * SHARE_SECRET in .env when it is set; the database password otherwise, so the
 * feature works before .env is edited rather than 500ing on a missing key.
 * Setting SHARE_SECRET is still worth doing — it decouples every share link
 * that has ever been sent from the DB password, so one can be rotated without
 * silently invalidating the other.
 *
 * Throws rather than falling back to a constant if neither exists: an empty key
 * makes every token computable by anyone, which would defeat the point. In
 * practice that state is unreachable — db.php's Dotenv load() throws first, and
 * the callers catch it and render their "unavailable" page.
 */
function share_secret(): string {
    foreach (['SHARE_SECRET', 'DBPASS'] as $key) {
        $value = $_ENV[$key] ?? '';
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }
    throw new RuntimeException('No SHARE_SECRET or DBPASS to derive share tokens from.');
}

/**
 * The unguessable half of a listing's share URL.
 *
 * Derived rather than stored because the app's database user has no DDL grant
 * and there is no migration runner — adding a column means pasting SQL into
 * phpMyAdmin by hand (see table_columns() in db.php). Deriving it also makes
 * every link revocable at once by rotating the secret.
 *
 * Without it, /s/1, /s/2, /s/3 would be a public index of every listing on the
 * site: the id is a plain autoincrement and is already in the page DOM.
 */
function share_token(int $id): string {
    return substr(hash_hmac('sha256', 'sublet:' . $id, share_secret()), 0, SHARE_TOKEN_LENGTH);
}

/** The path segment: "42-a1b2c3d4e5". */
function share_slug(int $id): string {
    return $id . '-' . share_token($id);
}

/** The absolute URL to hand to a share sheet. */
function share_url(int $id): string {
    return SHARE_ORIGIN . '/s/' . share_slug($id);
}

/** Constant-time comparison, so the token cannot be recovered a byte at a time. */
function verify_share_token(int $id, string $token): bool {
    return hash_equals(share_token($id), strtolower($token));
}

/**
 * The listing id in a slug, or null if the slug is malformed or unsigned.
 *
 * One gate for both failure modes so no caller can validate the shape and
 * forget the signature. The pattern is anchored with \z rather than $ for the
 * reason valid_uid() in htaccess_allowlist.php is: $ also matches before a
 * trailing newline, and this value reaches file paths in share-card.php's cache.
 */
function parse_share_slug(string $slug): ?int {
    if (!preg_match('/\A([0-9]{1,10})-([a-f0-9]{' . SHARE_TOKEN_LENGTH . '})\z/', $slug, $m)) {
        return null;
    }

    $id = (int)$m[1];
    if ($id <= 0 || !verify_share_token($id, $m[2])) {
        return null;
    }

    return $id;
}

/**
 * The text that describes a listing publicly, in one place.
 *
 * s.php bakes these into meta tags and share-card.php paints them into a PNG,
 * and the two have to agree — a preview whose image says one price and whose
 * title says another is worse than either alone. This is the pair CLAUDE.md's
 * "duplicated and drifts easily" section is about; do not inline either string.
 *
 * This function defines the public surface of a listing. Everything it returns
 * is readable by anyone the link reaches, so the address, description, poster
 * name, NetID, email and phone are deliberately absent and must stay absent.
 */
function share_card_lines(array $row): array {
    $price = '$' . number_format((float)($row['price'] ?? 0)) . '/mo';
    $semester = trim((string)($row['semester_name'] ?? $row['semester'] ?? ''));

    // Distance rather than a neighbourhood: it is the fact a reader actually
    // wants, and unlike an address it does not narrow the listing to a house.
    $facts = [];
    $size = listing_size_summary($row);
    if ($size !== '') {
        $facts[] = $size;
    }
    if (isset($row['distance_mi']) && $row['distance_mi'] !== null && $row['distance_mi'] !== '') {
        $facts[] = number_format((float)$row['distance_mi'], 1) . ' mi from campus';
    }
    $facts = implode(' · ', $facts);

    $titleBits = array_filter([$price, $semester]);
    $titleBits[] = 'UVM Sublets';

    return [
        'price'      => $price,
        'negotiable' => !empty($row['price_negotiable']),
        'semester'   => $semester,
        'facts'      => $facts,
        'title'      => implode(' · ', $titleBits),
        'description' => trim(($facts !== '' ? $facts . '. ' : '')
            . 'Sign in with your UVM NetID to see the listing.'),
    ];
}
