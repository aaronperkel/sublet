<?php
/**
 * Vocabularies and helpers for the structured listing fields.
 *
 * post.php validates against these, index.php/map.php/admin.php render from
 * them, and the amenity filter builds its SQL from them. Keeping the lists in
 * one file is what stops the <select> on the post form and the filter checkbox
 * row from drifting apart.
 *
 * The columns these describe (bedrooms, bathrooms, roommates, roommate_gender,
 * roommate_preference, price_negotiable) may not exist yet — see
 * table_columns() in db.php. Every reader here uses ?? so a listing loaded
 * before the migration simply has nothing to show.
 */

/** Amenity filter checkboxes on Browse and Map.
 *
 * `sql` is a literal fragment, never interpolated user input — only the array
 * key is matched against $_GET, and an unknown key is dropped.
 */
const LISTING_AMENITY_FILTERS = [
    'furnished'  => ['label' => 'Furnished',       'icon' => 'fa-couch',          'sql' => 's.amenity_furnished = 1'],
    'parking'    => ['label' => 'Parking',         'icon' => 'fa-square-parking', 'sql' => '(s.amenity_free_parking = 1 OR s.amenity_paid_parking = 1)'],
    'laundry'    => ['label' => 'In-unit laundry', 'icon' => 'fa-shirt',          'sql' => '(s.amenity_laundry_free = 1 OR s.amenity_laundry_paid = 1)'],
    'dishwasher' => ['label' => 'Dishwasher',      'icon' => 'fa-sink',           'sql' => 's.amenity_dishwasher = 1'],
    'ac'         => ['label' => 'A/C',             'icon' => 'fa-snowflake',      'sql' => 's.amenity_air_conditioning = 1'],
    'pets'       => ['label' => 'Pets OK',         'icon' => 'fa-paw',            'sql' => 's.amenity_pets_allowed = 1'],
];

/** Who currently lives in the unit. */
const ROOMMATE_GENDER_OPTIONS = [
    ''          => 'Prefer not to say',
    'women'     => 'All women',
    'men'       => 'All men',
    'nonbinary' => 'Nonbinary / gender-diverse',
    'mixed'     => 'Mixed genders',
];

/** Who the poster is hoping to sublet to. A preference, not a requirement. */
const ROOMMATE_PREFERENCE_OPTIONS = [
    ''                => 'Open to anyone',
    'women_nonbinary' => 'Women & nonbinary folks',
    'men_nonbinary'   => 'Men & nonbinary folks',
    'women'           => 'Women',
    'men'             => 'Men',
    'nonbinary'       => 'Nonbinary folks',
];

/**
 * What to call the person behind a listing.
 *
 * Falls back to the NetID, which is what every listing showed before
 * display_name existed and is still all we have for anyone who leaves it blank.
 * Accepts either a listing row or a bare username so callers that only have one
 * of the two do not have to special-case it.
 */
function poster_name($row, ?string $fallback = null): string {
    if (is_array($row)) {
        $name = trim((string)($row['display_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        return (string)($row['username'] ?? $fallback ?? '');
    }
    return trim((string)$row) !== '' ? (string)$row : (string)$fallback;
}

/**
 * Coerce a submitted value to one of an option list's keys.
 *
 * Anything unrecognised becomes '' rather than being stored, so the column can
 * only ever hold a value the renderers know how to label.
 */
function sanitize_option(array $options, $value): string {
    $value = is_string($value) ? $value : '';
    return array_key_exists($value, $options) ? $value : '';
}

/** Human label for a stored option value, or '' when unset/unknown. */
function option_label(array $options, ?string $value): string {
    if ($value === null || $value === '' || !array_key_exists($value, $options)) {
        return '';
    }
    return $options[$value];
}

/**
 * Clamp a submitted count to a sane range, or null when left blank.
 *
 * Blank has to stay distinguishable from zero: "no roommates" is a real answer
 * and "didn't say" is a different one.
 */
function optional_count($value, int $min, int $max): ?int {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    return max($min, min($max, (int)$value));
}

/** Same, for bathrooms, which come in halves. */
function optional_bathrooms($value): ?float {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    $n = round((float)$value * 2) / 2; // snap to the nearest half
    return max(0.5, min(9.5, $n));
}

/** "1", "1.5" — no trailing ".0". */
function format_half(float $n): string {
    return rtrim(rtrim(number_format($n, 1), '0'), '.');
}

/**
 * Short "2 bd · 1.5 ba · 1 roommate" line for cards and the admin table.
 *
 * Returns '' when the listing predates these fields, so callers can just skip
 * the element entirely rather than rendering an empty strip.
 */
function listing_size_summary(array $s): string {
    $bits = [];

    if (isset($s['bedrooms']) && $s['bedrooms'] !== null && $s['bedrooms'] !== '') {
        $bits[] = (int)$s['bedrooms'] . ' bd';
    }
    if (isset($s['bathrooms']) && $s['bathrooms'] !== null && $s['bathrooms'] !== '') {
        $bits[] = format_half((float)$s['bathrooms']) . ' ba';
    }
    if (isset($s['roommates']) && $s['roommates'] !== null && $s['roommates'] !== '') {
        $n = (int)$s['roommates'];
        $bits[] = $n === 0 ? 'No roommates' : $n . ' roommate' . ($n === 1 ? '' : 's');
    }

    return implode(' · ', $bits);
}
