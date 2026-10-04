<?php
/**
 * Listing visibility: by semester, and by the listing's own status.
 *
 * Deactivating a semester in the admin portal hides every listing for that
 * semester from the public site (browse, map, filter dropdown, slider bounds).
 * The listing is not deleted — reactivating the semester brings it back, and
 * the owner can still see and edit it on post.php.
 *
 * Listings whose semester code has no row in `semesters` at all (legacy or
 * unmapped codes) stay visible. Only an explicit deactivation hides a listing.
 *
 * A listing can run for several back-to-back semesters (sublet_semesters, see
 * semesters.php). It is visible while *any* of them is: a Summer and Fall
 * listing stays up for Fall after Summer is deactivated.
 *
 * A poster can also take their own listing off the board: `sublets.status` is
 * 'open', 'paused' or 'taken' (LISTING_STATUSES). Only an open listing in a
 * visible semester is public, and PUBLIC_LISTING_WHERE is that rule. Every
 * query that shows listings to students uses it; the semester-only constant is
 * for the places that deliberately ask about semesters alone (the admin's
 * "Hidden" flag, the Images tab).
 *
 * The constants assume the listings table is aliased `s`. VISIBLE_SEMESTER_JOIN
 * joins the listing's first semester as `sem`, for its name; the WHERE
 * constants no longer need it, since they look at every semester the listing
 * has through their own subquery (aliases vss/vsem, kept apart from the
 * caller's).
 */
require_once __DIR__ . '/semesters.php';

define('VISIBLE_SEMESTER_JOIN', 'LEFT JOIN semesters sem ON s.semester = sem.code');

define('VISIBLE_SEMESTER_WHERE', '(EXISTS (SELECT 1 FROM sublet_semesters vss'
    . ' LEFT JOIN semesters vsem ON vsem.code = vss.semester_code'
    . ' WHERE vss.sublet_id = s.id AND (vsem.code IS NULL OR vsem.active = 1)))');

define('PUBLIC_LISTING_WHERE', '(' . VISIBLE_SEMESTER_WHERE . " AND s.status = 'open')");

/**
 * What a listing's status can be, with the word the admin pages show for it.
 * 'paused' and 'taken' are both off the board; they differ in what a share
 * link says (s.php) and in the archive's count of listings that found someone.
 */
const LISTING_STATUSES = [
    'open' => 'Open',
    'paused' => 'Paused',
    'taken' => 'Taken',
];

/**
 * Set a listing's status. Returns false for a status that is not one of
 * LISTING_STATUSES, and true otherwise, including when nothing changed.
 * status_changed_at is filled by MySQL, like every other timestamp here, and
 * only moves when the status actually does.
 */
function set_listing_status(PDO $pdo, int $id, string $status): bool {
    if (!isset(LISTING_STATUSES[$status])) {
        return false;
    }
    $pdo->prepare('UPDATE sublets SET status = ?, status_changed_at = CURRENT_TIMESTAMP WHERE id = ? AND status <> ?')
        ->execute([$status, $id, $status]);
    return true;
}
