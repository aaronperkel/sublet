<?php
/**
 * Semester-based listing visibility.
 *
 * Deactivating a semester in the admin portal hides every listing for that
 * semester from the public site (browse, map, filter dropdown, slider bounds).
 * The listing is not deleted — reactivating the semester brings it back, and
 * the owner can still see and edit it on post.php.
 *
 * Listings whose semester code has no row in `semesters` at all (legacy or
 * unmapped codes) stay visible. Only an explicit deactivation hides a listing.
 *
 * Both constants assume the listings table is aliased `s` and `semesters` is
 * aliased `sem`. VISIBLE_SEMESTER_WHERE requires VISIBLE_SEMESTER_JOIN (or an
 * equivalent join) to already be part of the query.
 */

define('VISIBLE_SEMESTER_JOIN', 'LEFT JOIN semesters sem ON s.semester = sem.code');

define('VISIBLE_SEMESTER_WHERE', '(sem.code IS NULL OR sem.active = 1)');
