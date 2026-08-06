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
