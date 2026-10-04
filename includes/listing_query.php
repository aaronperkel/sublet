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
 * Deactivated semesters, and paused or taken listings, are excluded here
 * unconditionally (PUBLIC_LISTING_WHERE, see visibility.php), so no caller can
 * forget.
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
    $where = [PUBLIC_LISTING_WHERE];
    $params = [];

    if (isset($query['min_price'], $query['max_price'])
        && $query['min_price'] !== '' && $query['max_price'] !== '') {
        $where[] = 's.price BETWEEN ? AND ?';
        $params[] = $query['min_price'];
        $params[] = $query['max_price'];
    }

    // "Available in this semester": any of the listing's semesters, so a
    // Summer and Fall listing is found under either (see semesters.php).
    if (!empty($query['semester'])) {
        $where[] = 'EXISTS (SELECT 1 FROM sublet_semesters fss WHERE fss.sublet_id = s.id AND fss.semester_code = ?)';
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

    // How many separate things the visitor narrowed by: price, semester,
    // distance, each amenity, negotiable, bedrooms. It decides which empty
    // state to show, whether "Clear filters" is offered, and the number on the
    // phone's Filters button. app.js leaves the price and distance fields empty
    // while their sliders sit at the ends of the range, so an untouched slider
    // no longer counts as a filter.
    $count = count($amenities)
        + (!empty($query['semester']) ? 1 : 0)
        + (!empty($query['negotiable']) && isset($columns['price_negotiable']) ? 1 : 0)
        + (!empty($query['min_bedrooms']) && isset($columns['bedrooms']) ? 1 : 0)
        + ((isset($query['max_distance']) && $query['max_distance'] !== '') ? 1 : 0)
        + ((isset($query['min_price'], $query['max_price'])
            && $query['min_price'] !== '' && $query['max_price'] !== '') ? 1 : 0);

    return [
        'where' => $where,
        'params' => $params,
        'amenities' => $amenities,
        'active' => $count > 0,
        'count' => $count,
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

/** The query-string keys that describe listing filters (not sort, not ?id=). */
const LISTING_FILTER_KEYS = ['min_price', 'max_price', 'semester', 'max_distance', 'amenities', 'negotiable', 'min_bedrooms'];

/**
 * The filter part of a request's query, re-encoded, for links that switch
 * between Browse and Map without dropping the filters. Empty values are left
 * out; app.js keeps the same set in step after a live update.
 */
function listing_filter_query(array $query): string {
    $keep = [];
    foreach (LISTING_FILTER_KEYS as $key) {
        if (!isset($query[$key]) || $query[$key] === '' || $query[$key] === []) {
            continue;
        }
        $value = $query[$key];
        if (is_array($value)) {
            $value = array_values(array_filter($value, static fn($v) => is_string($v) && $v !== ''));
            if ($value === []) {
                continue;
            }
        } elseif (!is_string($value)) {
            continue;
        }
        $keep[$key] = $value;
    }
    return http_build_query($keep, '', '&', PHP_QUERY_RFC3986);
}
