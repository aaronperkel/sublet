<?php
/**
 * The Browse/Map listing query, in one place.
 *
 * index.php and map.php show the same listings through two different lenses,
 * so their WHERE clause has to agree exactly — a filter that narrows the grid
 * but not the map is a bug the user sees as missing pins. The clause used to be
 * copy-pasted between them, which is also how the campus coordinates ended up
 * written out four times.
 *
 * Deactivated semesters are excluded here unconditionally (see visibility.php),
 * so no caller can forget.
 */
require_once __DIR__ . '/visibility.php';
require_once __DIR__ . '/listing_fields.php';

/** Waterman Building, the point every "distance from campus" is measured to. */
const CAMPUS_LAT = 44.477435;
const CAMPUS_LON = -73.195323;

/**
 * Great-circle miles from campus, as a SQL expression over `lat`/`lon`.
 *
 * LEAST(1, ...) guards the acos() domain: floating-point drift can push the
 * cosine a hair above 1 for a listing sitting exactly on the campus point,
 * which would make acos() return NULL and silently drop the row.
 */
function campus_distance_expr(string $alias = 's'): string {
    return sprintf(
        '3959 * acos(LEAST(1, cos(radians(%1$F)) * cos(radians(%3$s.lat)) '
        . '* cos(radians(%3$s.lon) - radians(%2$F)) '
        . '+ sin(radians(%1$F)) * sin(radians(%3$s.lat))))',
        CAMPUS_LAT,
        CAMPUS_LON,
        $alias
    );
}

/**
 * Translate a query string into WHERE fragments and bound parameters.
 *
 * $columns is the result of table_columns($pdo, 'sublets'); filters that need a
 * column the database does not have yet are skipped rather than fataling.
 *
 * Returns ['where' => string[], 'params' => mixed[], 'amenities' => string[]],
 * where `amenities` is the accepted subset of the request, for re-checking the
 * boxes on render.
 */
function build_listing_filters(array $query, array $columns): array {
    $where = [VISIBLE_SEMESTER_WHERE];
    $params = [];

    if (isset($query['min_price'], $query['max_price'])
        && $query['min_price'] !== '' && $query['max_price'] !== '') {
        $where[] = 's.price BETWEEN ? AND ?';
        $params[] = $query['min_price'];
        $params[] = $query['max_price'];
    }

    if (!empty($query['semester'])) {
        $where[] = 's.semester = ?';
        $params[] = $query['semester'];
    }

    if (isset($query['max_distance']) && $query['max_distance'] !== '') {
        $where[] = campus_distance_expr() . ' <= ?';
        $params[] = $query['max_distance'];
    }

    // Only keys present in LISTING_AMENITY_FILTERS reach the SQL, and what they
    // map to is a literal defined in this codebase.
    $amenities = [];
    $requested = $query['amenities'] ?? [];
    if (is_array($requested)) {
        foreach ($requested as $key) {
            if (is_string($key) && isset(LISTING_AMENITY_FILTERS[$key])) {
                $amenities[] = $key;
                $where[] = LISTING_AMENITY_FILTERS[$key]['sql'];
            }
        }
    }

    if (!empty($query['negotiable']) && isset($columns['price_negotiable'])) {
        $where[] = 's.price_negotiable = 1';
    }

    if (!empty($query['min_bedrooms']) && isset($columns['bedrooms'])) {
        $where[] = 's.bedrooms >= ?';
        $params[] = (int)$query['min_bedrooms'];
    }

    // Whether the visitor narrowed anything, which decides both which empty
    // state to show and whether a "Clear filters" link is worth offering.
    $active = $amenities !== []
        || !empty($query['semester'])
        || !empty($query['negotiable'])
        || !empty($query['min_bedrooms'])
        || (isset($query['max_distance']) && $query['max_distance'] !== '')
        || (isset($query['min_price'], $query['max_price'])
            && $query['min_price'] !== '' && $query['max_price'] !== '');

    return [
        'where' => $where,
        'params' => $params,
        'amenities' => $amenities,
        'active' => $active,
    ];
}

/** Sort options offered on Browse, in menu order. */
const LISTING_SORTS = [
    'newest'     => 'Newest first',
    'oldest'     => 'Oldest first',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'closest'    => 'Closest to campus',
];

/**
 * ORDER BY clause for a requested sort, plus the sort key actually used.
 *
 * Unknown input falls back to 'newest' rather than being interpolated.
 */
function listing_sort_sql(?string $sort): array {
    $map = [
        'newest'     => 's.id DESC',
        'oldest'     => 's.id ASC',
        'price_asc'  => 's.price ASC',
        'price_desc' => 's.price DESC',
        'closest'    => 'distance_mi ASC',
    ];

    if (!is_string($sort) || !isset($map[$sort])) {
        $sort = 'newest';
    }

    return [' ORDER BY ' . $map[$sort], $sort];
}
