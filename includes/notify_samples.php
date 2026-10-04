<?php
/**
 * Sample admin notices, on made-up listings, to see what each kind looks like.
 *
 *   /usr/bin/php82 includes/notify_samples.php             send them to the admin
 *   /usr/bin/php82 includes/notify_samples.php --preview D  write them to D/*.html
 *
 * Command line only: includes/ is refused by the root .htaccess, and this
 * stops anything that reaches it some other way. It reads no database; the
 * listings below are invented.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/notify.php';
// The CLI does not read .user.ini; the site runs on Eastern time.
date_default_timezone_set('America/New_York');

$before = [
    'id' => 0, 'username' => 'sample1', 'display_name' => 'Maya', 'price' => 850, 'price_negotiable' => 0,
    'address' => '14 Larkspur Street, Burlington, Vermont 05401', 'lat' => 44.4790, 'lon' => -73.2010,
    'semester' => 'summer27', 'semester_names' => ['Summer 2027'],
    'bedrooms' => 3, 'bathrooms' => 1, 'roommates' => 2, 'roommate_gender' => 'mixed', 'roommate_preference' => '',
    'utility_electric' => 'landlord', 'utility_gas' => 'tenant', 'utility_water' => 'landlord', 'utility_internet' => 'landlord',
    'utility_cost' => 40, 'amenity_laundry_free' => 1, 'amenity_dishwasher' => 1,
    'contact_email' => 'sample1@uvm.edu', 'contact_phone' => '',
    'description' => "Sunny room in a friendly three-bedroom, a ten-minute walk to campus. Two roommates who keep things tidy.",
    'status' => 'open', 'posted_at' => date('Y-m-d H:i:s', strtotime('-9 days')), 'photo_count' => 3,
];
$after = array_merge($before, [
    'price' => 800, 'price_negotiable' => 1,
    'semester_names' => ['Summer 2027', 'Fall 2027'],
    'amenity_air_conditioning' => 1,
    'description' => "Sunny room in a friendly three-bedroom, a ten-minute walk to campus. Two roommates who keep things tidy. Now also available for the fall — happy to do summer and fall together for $800.",
    'photo_count' => 5,
]);

$notices = [
    'created' => admin_notice_created($before),
    'edited' => admin_notice_updated($before, $after, 2),
    'taken' => admin_notice_status($after, 'open', 'taken'),
    'deleted' => admin_notice_deleted(array_merge($after, ['status' => 'taken']), 'poster'),
];
foreach ($notices as &$n) {
    $n['subject'] = '[Sample] ' . $n['subject'];
}
unset($n);

$preview = array_search('--preview', $argv, true);
if ($preview !== false) {
    $dir = $argv[$preview + 1] ?? '.';
    foreach ($notices as $name => $n) {
        file_put_contents("$dir/notice-$name.html", "<!-- Subject: " . htmlspecialchars('[UVM Sublets] ' . $n['subject']) . " -->\n" . admin_notice_html($n));
        echo "$dir/notice-$name.html\n";
    }
    exit;
}

foreach ($notices as $name => $n) {
    echo str_pad($name, 8) . (admin_notify($n) ? 'sent' : 'mail() refused') . ' to ' . ADMIN_NOTICE_TO . "\n";
}
