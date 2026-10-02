<?php
/**
 * Display formatting helpers.
 *
 * Addresses are stored exactly as Nominatim returns them, because the geocoder
 * is the source of truth and the raw string is what a future re-geocode would
 * match against. That string is far too long to show:
 *
 *   62, King Street, Downtown, Burlington, Chittenden County, Vermont, 05401, United States
 *
 * The card address is a single ellipsised line, so the useful part — house
 * number, street, neighbourhood — was the part that got cut off.
 *
 * These helpers only change what is rendered. Nothing rewrites the database.
 */

/**
 * US state names and postal abbreviations, keyed lowercase for lookup.
 *
 * The 50-mile radius around campus reaches into New York, so this cannot just
 * special-case Vermont.
 */
function us_state_names(): array {
    static $states = null;
    if ($states === null) {
        $names = [
            'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR',
            'California' => 'CA', 'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE',
            'Florida' => 'FL', 'Georgia' => 'GA', 'Hawaii' => 'HI', 'Idaho' => 'ID',
            'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
            'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD',
            'Massachusetts' => 'MA', 'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS',
            'Missouri' => 'MO', 'Montana' => 'MT', 'Nebraska' => 'NE', 'Nevada' => 'NV',
            'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM', 'New York' => 'NY',
            'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
            'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC',
            'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT',
            'Vermont' => 'VT', 'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV',
            'Wisconsin' => 'WI', 'Wyoming' => 'WY', 'District of Columbia' => 'DC',
        ];
        $states = [];
        foreach ($names as $full => $abbr) {
            $states[strtolower($full)] = true;
            $states[strtolower($abbr)] = true;
        }
    }
    return $states;
}

/**
 * Trim the administrative tail off a geocoded address.
 *
 * Drops trailing segments that carry no information for a reader who already
 * knows the listing is near UVM: the country, a US ZIP, the state, and any
 * "... County". Everything up to that point is kept in order.
 *
 * Only trailing segments are removed, so a street genuinely called
 * "County Road" survives — it is not in the tail position.
 */
function format_address(?string $address): string {
    $address = trim((string)$address);
    if ($address === '') {
        return '';
    }

    $parts = array_map('trim', explode(',', $address));
    $states = us_state_names();

    while (count($parts) > 1) {
        $last = end($parts);

        $isCountry = strcasecmp($last, 'United States') === 0
            || strcasecmp($last, 'USA') === 0;
        $isZip     = (bool)preg_match('/^\d{5}(-\d{4})?$/', $last);
        $isState   = isset($states[strtolower($last)]);
        $isCounty  = (bool)preg_match('/\bCounty$/i', $last);

        if ($isCountry || $isZip || $isState || $isCounty) {
            array_pop($parts);
            continue;
        }

        break;
    }

    // Nominatim writes a bare house number as its own leading segment
    // ("62, King Street"); join that one to the street so it reads normally.
    if (count($parts) > 1 && preg_match('/^\d+[a-zA-Z]?$/', $parts[0])) {
        $number = array_shift($parts);
        $parts[0] = $number . ' ' . $parts[0];
    }

    return implode(', ', $parts);
}

/**
 * Build a short address from a Nominatim result's structured `address` object.
 *
 * Preferred over parsing display_name because the field names are stable and
 * the house number arrives already separated from the road. Falls back to
 * format_address() when the result has no road — a point of interest such as
 * "University of Vermont" carries its name only in display_name, and trimming
 * that string keeps it, whereas assembling from fields would drop it.
 */
function short_address_from_details(array $result): string {
    $a = $result['address'] ?? [];
    if (!is_array($a) || empty($a['road'])) {
        return format_address($result['display_name'] ?? '');
    }

    $parts = [trim(($a['house_number'] ?? '') . ' ' . $a['road'])];

    $area = $a['neighbourhood'] ?? $a['suburb'] ?? $a['hamlet'] ?? '';
    $city = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? '';

    if ($area !== '') {
        $parts[] = $area;
    }
    if ($city !== '' && strcasecmp($city, $area) !== 0) {
        $parts[] = $city;
    }

    return implode(', ', $parts);
}

/**
 * "Open in Maps" links for a listing, built from the full stored address
 * rather than format_address()'s short form, which drops the state and ZIP and
 * so can match a street of the same name in another town.
 *
 * Both are built here so the listing view has a working Google link whatever
 * the browser; app.js swaps in the Apple one on iPhone, iPad and Mac, where
 * maps.apple.com opens the Maps app. ll pins Apple's search to the geocoded
 * point when there is one.
 */
function listing_map_links(?string $address, $lat, $lon): array {
    $parts = array_map('trim', explode(',', trim((string)$address)));
    // "62, King Street" -> "62 King Street", as format_address() does.
    if (count($parts) > 1 && preg_match('/^\d+[a-zA-Z]?$/', $parts[0])) {
        $number = array_shift($parts);
        $parts[0] = $number . ' ' . $parts[0];
    }
    $query = implode(', ', array_filter($parts, 'strlen'));
    if ($query === '') {
        return ['google' => '', 'apple' => ''];
    }

    $apple = 'https://maps.apple.com/?q=' . rawurlencode($query);
    if (is_numeric($lat) && is_numeric($lon)) {
        $apple .= '&ll=' . (float)$lat . ',' . (float)$lon;
    }

    // Addresses picked since the geocoder switch are stored short ("37 South
    // Williams Street, Burlington"), and Google's link has no ll= to pin them,
    // so "Burlington" could resolve to any of a dozen. Name the state, but only
    // inside Vermont's corner of the 50-mile radius, which also reaches New
    // York and Quebec: east of -73.35 (every New York shore town on Lake
    // Champlain lies west of it, Plattsburgh included) and south of 45.0 (the
    // Quebec line; Philipsburg and Bedford are east of -73.35). A pin dropped
    // by hand can land anywhere in the radius. Outside that, add nothing
    // rather than guess.
    $google = $query;
    $states = us_state_names();
    $hasState = false;
    foreach ($parts as $part) {
        if (isset($states[strtolower($part)])) {
            $hasState = true;
            break;
        }
    }
    if (!$hasState && is_numeric($lat) && is_numeric($lon)
            && (float)$lon > -73.35 && (float)$lat < 45.0) {
        $google .= ', VT';
    }

    return [
        'google' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($google),
        'apple'  => $apple,
    ];
}

/**
 * When a listing went up, in the words a reader uses: "today", "3 days ago",
 * "Sep 14". posted_at is set once on insert and never on edit, so this is
 * the listing's age, which is what tells a November post from a fresh one.
 * Both sides are Eastern (see CLAUDE.md, "Time zones").
 */
function posted_ago(?string $postedAt, ?int $now = null): string {
    $ts = $postedAt ? strtotime($postedAt) : false;
    if ($ts === false) {
        return '';
    }
    $now = $now ?? time();
    // Rounded, not floored: a span across a DST change is 23 or 25 hours a day.
    $days = (int)round((strtotime('today', $now) - strtotime('today', $ts)) / 86400);

    if ($days <= 0) {
        return 'today';
    }
    if ($days === 1) {
        return 'yesterday';
    }
    if ($days < 7) {
        return $days . ' days ago';
    }
    if ($days < 28) {
        $weeks = intdiv($days, 7);
        return $weeks === 1 ? 'last week' : $weeks . ' weeks ago';
    }
    return date('Y', $ts) === date('Y', $now) ? date('M j', $ts) : date('M j, Y', $ts);
}
