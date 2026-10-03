<?php
/**
 * Archiving a semester: the last step of active -> hidden -> archived.
 *
 * Deactivating a semester (semesters.active = 0) hides its listings and deletes
 * nothing. Archiving one that is already hidden removes it for good, in this
 * order, and stops at the first thing that does not check out:
 *
 *   1. archive_plan() works out exactly what would go: the listings, their
 *      photo rows, every file on disk (originals and their _thumb/_display
 *      copies), bytes, events and share cards. It changes nothing; the admin
 *      page shows it as the dry run.
 *   2. archive_execute() takes the semester code typed back as confirmation,
 *      tars the files to ~/sublet-image-backups/semester-<code>-<UTC>.tar.gz
 *      (outside the docroot) and checks the tarball's listing, names and sizes,
 *      against the plan.
 *   3. One transaction re-reads the listings and photo rows under lock, refuses
 *      if anything changed since the plan, writes the totals to
 *      semester_archives, deletes the semester's events and listings (their
 *      sublet_images rows go by ON DELETE CASCADE) and sets
 *      semesters.archived_at.
 *   4. Only after the commit are the files deleted, through
 *      delete_image_files(), and the listings' cached share cards swept.
 *
 * semesters.archived_at and semester_archives are created by hand (the app's
 * DB user has no DDL grant). archive_schema_report() compares them with the
 * Phase 5 DDL so the admin page can say whether they match, and nothing runs
 * until the columns it writes exist.
 *
 * Requires db.php (ROOT_DIR, resolve_path(), table_columns()), thumbnail.php
 * and events.php.
 */
require_once __DIR__ . '/thumbnail.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/auth.php';

/** Name of a tarball this module wrote; nothing else in the folder matches. */
const ARCHIVE_TARBALL_PATTERN = '/^semester-[A-Za-z0-9_-]{1,40}-\d{8}T\d{6}Z\.tar\.gz\z/';

/**
 * The Phase 5 DDL, column by column, as SHOW COLUMNS reports it (int display
 * widths and the spelling of CURRENT_TIMESTAMP are normalised before comparing).
 * `photos` was in the plan's DDL but not in the copy shown in the terminal, so
 * it is optional: written when the column exists, skipped when it does not.
 */
const ARCHIVE_TABLE_COLUMNS = [
    'id'             => ['type' => 'int',           'null' => 'NO',  'default' => null, 'extra' => 'auto_increment'],
    'semester_code'  => ['type' => 'varchar(20)',   'null' => 'NO',  'default' => null],
    'semester_name'  => ['type' => 'varchar(50)',   'null' => 'NO',  'default' => null],
    'archived_at'    => ['type' => 'timestamp',     'null' => 'NO',  'default' => 'current_timestamp'],
    'archived_by'    => ['type' => 'varchar(50)',   'null' => 'NO',  'default' => null],
    'listings'       => ['type' => 'int',           'null' => 'NO',  'default' => null],
    'taken'          => ['type' => 'int',           'null' => 'NO',  'default' => '0'],
    'price_median'   => ['type' => 'decimal(10,2)', 'null' => 'YES', 'default' => null],
    'price_min'      => ['type' => 'decimal(10,2)', 'null' => 'YES', 'default' => null],
    'price_max'      => ['type' => 'decimal(10,2)', 'null' => 'YES', 'default' => null],
    'views'          => ['type' => 'int',           'null' => 'NO',  'default' => '0'],
    'contacts'       => ['type' => 'int',           'null' => 'NO',  'default' => '0'],
    'shares'         => ['type' => 'int',           'null' => 'NO',  'default' => '0'],
    'share_arrivals' => ['type' => 'int',           'null' => 'NO',  'default' => '0'],
    'photos'         => ['type' => 'int',           'null' => 'NO',  'default' => '0', 'optional' => true],
    'bytes'          => ['type' => 'bigint',        'null' => 'NO',  'default' => '0'],
    'tarball'        => ['type' => 'varchar(120)',  'null' => 'YES', 'default' => null],
];

/** Where tarballs go: beside the docroot, with the pre-backfill backup. */
function archive_backup_dir(): string {
    return dirname(ROOT_DIR) . '/sublet-image-backups';
}

/**
 * Compare the live schema with the Phase 5 DDL.
 *
 * Returns ['ready' => bool, 'matches' => bool, 'rows' => [...], 'notes' => [...]]
 * where each row is [what, expected, actual, ok]. `ready` means archiving can
 * run (the columns it writes exist); `matches` means everything is as planned.
 */
function archive_schema_report(PDO $pdo): array {
    $rows = [];
    $notes = [];
    $ready = true;

    $semCols = archive_show_columns($pdo, 'semesters');
    $col = $semCols['archived_at'] ?? null;
    $actual = $col ? archive_describe_column($col) : 'missing';
    $ok = $col !== null && $actual === 'timestamp NULL default NULL';
    $rows[] = ['semesters.archived_at', 'timestamp NULL default NULL', $actual, $ok];
    if ($col === null) {
        $ready = false;
    }

    if (!table_exists($pdo, 'semester_archives')) {
        $rows[] = ['semester_archives', 'table', 'missing', false];
        return ['ready' => false, 'matches' => false, 'rows' => $rows, 'notes' => $notes];
    }

    $cols = archive_show_columns($pdo, 'semester_archives');
    foreach (ARCHIVE_TABLE_COLUMNS as $name => $spec) {
        $expected = archive_describe_spec($spec);
        if (!isset($cols[$name])) {
            if (!empty($spec['optional'])) {
                $notes[] = "semester_archives.$name is not there. It is optional: the photo count is left out of the archive's row.";
                continue;
            }
            $rows[] = ["semester_archives.$name", $expected, 'missing', false];
            $ready = false;
            continue;
        }
        $actual = archive_describe_column($cols[$name]);
        $rows[] = ["semester_archives.$name", $expected, $actual, $actual === $expected];
    }
    foreach (array_diff_key($cols, ARCHIVE_TABLE_COLUMNS) as $name => $_) {
        $rows[] = ["semester_archives.$name", 'not in the plan', archive_describe_column($cols[$name]), false];
    }

    // The unique key is what makes archiving the same code twice impossible.
    $unique = [];
    foreach ($pdo->query('SHOW INDEX FROM semester_archives') as $idx) {
        if ((int)$idx['Non_unique'] === 0) {
            $unique[$idx['Key_name']][] = $idx['Column_name'];
        }
    }
    $hasUq = in_array(['semester_code'], array_values($unique), true);
    $rows[] = ['UNIQUE (semester_code)', 'present', $hasUq ? 'present' : 'missing', $hasUq];
    $hasPk = ($unique['PRIMARY'] ?? []) === ['id'];
    $rows[] = ['PRIMARY KEY (id)', 'present', $hasPk ? 'present' : 'missing', $hasPk];

    // Engine and collation, against the table the archive is summarising.
    $status = [];
    foreach (['semester_archives', 'sublets'] as $table) {
        $stmt = $pdo->prepare('SHOW TABLE STATUS WHERE Name = ?');
        $stmt->execute([$table]);
        $status[$table] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $engine = $status['semester_archives']['Engine'] ?? '';
    $rows[] = ['engine', 'InnoDB', $engine, strcasecmp($engine, 'InnoDB') === 0];
    $coll = $status['semester_archives']['Collation'] ?? '';
    $want = $status['sublets']['Collation'] ?? 'utf8mb4_0900_ai_ci';
    $rows[] = ['collation', $want . ' (as sublets)', $coll, $coll === $want];

    $matches = !in_array(false, array_column($rows, 3), true);
    return ['ready' => $ready, 'matches' => $matches, 'rows' => $rows, 'notes' => $notes];
}

/** SHOW COLUMNS, keyed by name. */
function archive_show_columns(PDO $pdo, string $table): array {
    $out = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $row) {
        $out[$row['Field']] = $row;
    }
    return $out;
}

/** One SHOW COLUMNS row as "type NULL|NOT NULL default X [auto_increment]". */
function archive_describe_column(array $col): string {
    $type = strtolower(preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', $col['Type']));
    $default = $col['Default'];
    if ($default !== null) {
        $default = strtolower(trim($default, "'"));
        if (preg_match('/^current_timestamp(\(\))?$/', $default)) {
            $default = 'current_timestamp';
        }
    }
    // MariaDB reports a nullable column with no default as the string NULL.
    if ($default === 'null') {
        $default = null;
    }
    $extra = stripos((string)$col['Extra'], 'auto_increment') !== false ? ' auto_increment' : '';
    return archive_describe_spec([
        'type' => $type,
        'null' => $col['Null'],
        'default' => $default,
        'extra' => trim($extra),
    ]);
}

function archive_describe_spec(array $spec): string {
    $s = $spec['type'] . ($spec['null'] === 'YES' ? ' NULL' : ' NOT NULL');
    if ($spec['default'] !== null) {
        $s .= ' default ' . $spec['default'];
    } elseif ($spec['null'] === 'YES') {
        $s .= ' default NULL';
    }
    if (!empty($spec['extra'])) {
        $s .= ' ' . $spec['extra'];
    }
    return $s;
}

/**
 * Every file on disk behind a set of stored image paths: each original plus
 * its _thumb.webp and _display.webp, deduplicated, existing files only.
 * Returns [path => bytes] and the stored paths whose original is missing.
 */
function archive_files_for(array $storedPaths): array {
    $files = [];
    $missing = [];
    $imagesDir = ROOT_DIR . '/public/images/';
    foreach (array_unique($storedPaths) as $stored) {
        $fs = resolve_path($stored);
        // Only ever files directly inside public/images/: a stored path that
        // resolves anywhere else is not this module's to tar or delete.
        if (dirname($fs) . '/' !== $imagesDir) {
            $missing[] = $stored;
            continue;
        }
        if (!is_file($fs)) {
            $missing[] = $stored;
        }
        foreach ([$fs, image_variant_path($fs, IMAGE_THUMB_SUFFIX), image_variant_path($fs, IMAGE_DISPLAY_SUFFIX)] as $path) {
            if ($path !== null && is_file($path)) {
                $files[$path] = (int)filesize($path);
            }
        }
    }
    ksort($files);
    return [$files, $missing];
}

/** Median of a list of numbers, or null for an empty one. */
function archive_median(array $values): ?float {
    if (!$values) {
        return null;
    }
    sort($values);
    $n = count($values);
    $mid = intdiv($n, 2);
    return $n % 2 ? (float)$values[$mid] : ((float)$values[$mid - 1] + (float)$values[$mid]) / 2;
}

/** A tarball name for a semester code, safe whatever the code contains. */
function archive_tarball_name(string $code, ?int $time = null): string {
    $slug = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $code), '-');
    $slug = substr($slug !== '' ? $slug : 'semester', 0, 40);
    // UTC, like the .htaccess backups: names sort by time, and local time
    // repeats an hour every November.
    return 'semester-' . $slug . '-' . gmdate('Ymd\THis\Z', $time ?? time()) . '.tar.gz';
}

/**
 * The dry run: everything archiving $code would remove, and whether it may.
 * Read-only.
 */
function archive_plan(PDO $pdo, string $code): array {
    $plan = ['code' => $code, 'blocking' => [], 'warnings' => []];

    $schema = archive_schema_report($pdo);
    $plan['schema'] = $schema;
    if (!$schema['ready']) {
        $plan['blocking'][] = 'The Phase 5 tables are not ready: see the schema check above.';
    }

    $semCols = table_columns($pdo, 'semesters');
    $stmt = $pdo->prepare('SELECT * FROM semesters WHERE code = ?');
    $stmt->execute([$code]);
    $semester = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$semester) {
        $plan['blocking'][] = 'There is no semester with that code.';
        return $plan + ['semester' => null, 'listings' => [], 'files' => [], 'totals' => []];
    }
    $plan['semester'] = [
        'code' => $semester['code'],
        'name' => $semester['name'],
        'active' => (bool)$semester['active'],
        'archived_at' => $semester['archived_at'] ?? null,
    ];
    if ($semester['active']) {
        $plan['blocking'][] = 'It is active. Deactivate it first, so its listings are hidden before they are removed.';
    }
    if (isset($semCols['archived_at']) && !empty($semester['archived_at'])) {
        $plan['blocking'][] = 'It was already archived on ' . $semester['archived_at'] . '.';
    }

    $stmt = $pdo->prepare('SELECT * FROM sublets WHERE semester = ? ORDER BY id');
    $stmt->execute([$code]);
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(static fn($row) => (int)$row['id'], $listings);

    $imageRows = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id, sublet_id, image_url FROM sublet_images WHERE sublet_id IN ($in) ORDER BY sublet_id, sort_order, id");
        $stmt->execute($ids);
        $imageRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Every stored path that belongs to these listings, from all three columns.
    $refsByListing = array_fill_keys($ids, []);
    foreach ($imageRows as $row) {
        $refsByListing[(int)$row['sublet_id']][] = $row['image_url'];
    }
    foreach ($listings as $row) {
        foreach (['image_url', 'thumbnail_url'] as $col) {
            if (!empty($row[$col])) {
                $refsByListing[(int)$row['id']][] = $row[$col];
            }
        }
    }
    $refs = array_values(array_unique(array_merge([], ...array_values($refsByListing ?: [[]]))));

    // A file is never shared between listings (names are random), but if one
    // ever were, deleting it would break a listing that is staying.
    if ($refs) {
        $in = implode(',', array_fill(0, count($refs), '?'));
        $stmt = $pdo->prepare(
            "SELECT DISTINCT si.image_url FROM sublet_images si JOIN sublets s ON s.id = si.sublet_id
             WHERE s.semester <> ? AND si.image_url IN ($in)
             UNION SELECT image_url FROM sublets WHERE semester <> ? AND image_url IN ($in)
             UNION SELECT thumbnail_url FROM sublets WHERE semester <> ? AND thumbnail_url IN ($in)"
        );
        $stmt->execute(array_merge([$code], $refs, [$code], $refs, [$code], $refs));
        $shared = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($shared) {
            $plan['blocking'][] = count($shared) . ' file(s) are also used by listings in other semesters: ' . implode(', ', array_map('basename', $shared));
        }
    }

    [$files, $missing] = archive_files_for($refs);
    if ($missing) {
        $plan['warnings'][] = count($missing) . ' stored photo path(s) have no file on disk; their rows are removed with the listings: '
            . implode(', ', array_map('basename', $missing));
    }

    // Per listing, for the preview table and the tarball's manifest.
    $rows = [];
    $filesByListing = [];
    foreach ($listings as $row) {
        $id = (int)$row['id'];
        [$listingFiles] = archive_files_for($refsByListing[$id]);
        $filesByListing[$id] = array_map('basename', array_keys($listingFiles));
        $rows[] = [
            'id' => $id,
            'address' => format_address($row['address']),
            'username' => $row['username'],
            'price' => (float)$row['price'],
            'status' => $row['status'] ?? 'open',
            'posted_at' => $row['posted_at'] ?? null,
            'photos' => count(array_filter($imageRows, static fn($r) => (int)$r['sublet_id'] === $id)),
            'files' => count($listingFiles),
            'bytes' => array_sum($listingFiles),
        ];
    }

    // Share cards cached for these listings (public/share/<id>-<format>-*.jpg).
    $shareCards = [];
    foreach ($ids as $id) {
        foreach (glob(ROOT_DIR . '/public/share/' . $id . '-*.jpg') ?: [] as $card) {
            $shareCards[] = $card;
        }
    }

    // What the archive row keeps: totals, never names.
    $events = ['rows' => 0, 'by_type' => []];
    $activity = ['views' => 0, 'contacts' => 0, 'shares' => 0, 'arrivals' => 0];
    if (table_exists($pdo, 'listing_events')) {
        $stmt = $pdo->prepare('SELECT type, COUNT(*) FROM listing_events WHERE semester = ? GROUP BY type ORDER BY type');
        $stmt->execute([$code]);
        $events['by_type'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
        $events['rows'] = array_sum($events['by_type']);
        foreach (listing_activity($pdo, 'e.semester = ?', [$code]) as $counts) {
            $activity['views'] += $counts['views'];
            $activity['contacts'] += $counts['contacts'];
            $activity['shares'] += $counts['shares'];
            $activity['arrivals'] += $counts['arrivals'];
        }
    }

    $prices = array_map(static fn($row) => (float)$row['price'], $listings);
    $sublets = table_columns($pdo, 'sublets');
    $taken = isset($sublets['status'])
        ? count(array_filter($listings, static fn($row) => ($row['status'] ?? '') === 'taken'))
        : 0;

    $plan['listings'] = $rows;
    $plan['listing_ids'] = $ids;
    $plan['refs'] = $refs;
    $plan['files'] = array_map('basename', array_keys($files));
    $plan['files_by_listing'] = $filesByListing;
    $plan['file_paths'] = $files;
    $plan['missing'] = array_map('basename', $missing);
    $plan['share_cards'] = array_map('basename', $shareCards);
    $plan['events'] = $events;
    $plan['tarball'] = $files ? archive_tarball_name($code) : null;
    $plan['backup_dir'] = archive_backup_dir();
    $plan['totals'] = [
        'listings' => count($listings),
        'taken' => $taken,
        'photos' => count($imageRows),
        'files' => count($files),
        'bytes' => array_sum($files),
        'price_median' => archive_median($prices),
        'price_min' => $prices ? min($prices) : null,
        'price_max' => $prices ? max($prices) : null,
        'views' => $activity['views'],
        'contacts' => $activity['contacts'],
        'shares' => $activity['shares'],
        'share_arrivals' => $activity['arrivals'],
        'share_cards' => count($shareCards),
    ];

    return $plan;
}

/** The plan as the admin page shows it: no filesystem paths. */
function archive_plan_public(array $plan): array {
    unset($plan['file_paths'], $plan['refs'], $plan['listing_ids']);
    return $plan;
}

/**
 * Write and verify the tarball for a plan. Returns [name, bytes] or throws.
 * The tarball holds the files under their own names plus manifest.json, which
 * says which files were which listing's (ids and file names only, no personal
 * details), so a listing's photos can be found again.
 */
function archive_write_tarball(array $plan, string $dir): array {
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        throw new RuntimeException("Could not create $dir.");
    }
    $name = $plan['tarball'];
    $final = $dir . '/' . $name;
    if (file_exists($final)) {
        throw new RuntimeException("$name already exists; try again in a second.");
    }

    $work = $dir . '/.work-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!@mkdir($work, 0700)) {
        throw new RuntimeException('Could not create a working folder in ' . $dir . '.');
    }
    $tmp = $dir . '/.' . $name . '.tmp';

    try {
        $manifest = [
            'semester' => $plan['semester']['code'],
            'semester_name' => $plan['semester']['name'],
            'written_utc' => gmdate('c'),
            'listings' => $plan['totals']['listings'],
            'files' => $plan['files'],
            'files_by_listing' => $plan['files_by_listing'],
        ];
        file_put_contents($work . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($work . '/files.txt', implode("\n", $plan['files']) . "\n");

        $cmd = 'tar -czf ' . escapeshellarg($tmp)
            . ' -C ' . escapeshellarg($work) . ' manifest.json'
            . ' -C ' . escapeshellarg(ROOT_DIR . '/public/images')
            . ' -T ' . escapeshellarg($work . '/files.txt') . ' 2>&1';
        exec($cmd, $out, $ret);
        if ($ret !== 0 || !is_file($tmp)) {
            throw new RuntimeException('tar failed: ' . trim(implode(' ', $out)));
        }

        // Read it back: every planned file, at its planned size, and nothing else.
        exec('tar -tvzf ' . escapeshellarg($tmp) . ' 2>&1', $listing, $ret);
        if ($ret !== 0) {
            throw new RuntimeException('Could not read the tarball back: ' . trim(implode(' ', $listing)));
        }
        $found = [];
        foreach ($listing as $line) {
            if (!preg_match('/^\S+\s+\S+\s+(\d+)\s+\S+\s+\S+\s+(.+)$/', $line, $m)) {
                throw new RuntimeException('Unexpected line in the tarball listing: ' . $line);
            }
            $found[$m[2]] = (int)$m[1];
        }
        unset($found['manifest.json']);
        $expected = [];
        foreach ($plan['file_paths'] as $path => $bytes) {
            $expected[basename($path)] = $bytes;
        }
        ksort($found);
        ksort($expected);
        if ($found !== $expected) {
            $missingNames = array_keys(array_diff_key($expected, $found));
            $extra = array_keys(array_diff_key($found, $expected));
            $sized = array_keys(array_filter($expected, static fn($b, $n) => isset($found[$n]) && $found[$n] !== $b, ARRAY_FILTER_USE_BOTH));
            throw new RuntimeException(sprintf(
                'The tarball does not match the plan (%d expected, %d found; missing: %s; unexpected: %s; wrong size: %s).',
                count($expected), count($found),
                implode(', ', $missingNames) ?: 'none', implode(', ', $extra) ?: 'none', implode(', ', $sized) ?: 'none'
            ));
        }

        @chmod($tmp, 0600);
        if (!rename($tmp, $final)) {
            throw new RuntimeException('Could not move the tarball into place.');
        }
        return [$name, (int)filesize($final)];
    } finally {
        @unlink($tmp);
        @unlink($work . '/manifest.json');
        @unlink($work . '/files.txt');
        @rmdir($work);
    }
}

/**
 * Archive $code. $confirm must be the code, typed. Returns a summary array, or
 * ['error' => message] with nothing changed (or, after the database step, a
 * note of any files that could not be removed).
 */
function archive_execute(PDO $pdo, string $code, string $confirm, string $by): array {
    if ($confirm !== $code) {
        return ['error' => 'The confirmation does not match the semester code.'];
    }

    $dir = archive_backup_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return ['error' => "Could not create $dir."];
    }

    // One archive at a time, so a double click cannot run two.
    $lock = fopen($dir . '/.archive.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        return ['error' => 'Another archive is already running.'];
    }

    try {
        $plan = archive_plan($pdo, $code);
        if ($plan['blocking']) {
            return ['error' => 'Not archived: ' . implode(' ', $plan['blocking'])];
        }

        $tarball = null;
        $tarBytes = 0;
        if ($plan['files']) {
            try {
                [$tarball, $tarBytes] = archive_write_tarball($plan, $dir);
            } catch (Throwable $e) {
                return ['error' => 'Not archived: ' . $e->getMessage()];
            }
        }

        try {
            [$listingsDeleted, $eventsDeleted] = archive_commit($pdo, $plan, $tarball, $by);
        } catch (Throwable $e) {
            // Nothing was deleted, so the tarball is only a copy; remove it so
            // the folder does not suggest an archive that did not happen.
            if ($tarball) {
                @unlink($dir . '/' . $tarball);
            }
            return ['error' => 'Not archived: ' . $e->getMessage() . ' Nothing was changed.'];
        }

        // The rows are gone; now the files. A failure here leaves orphans for
        // the Images tab's sweep, never a listing pointing at a missing file.
        foreach ($plan['refs'] as $stored) {
            delete_image_files($stored);
        }
        $left = array_values(array_filter(array_keys($plan['file_paths']), 'file_exists'));
        foreach ($plan['share_cards'] as $card) {
            @unlink(ROOT_DIR . '/public/share/' . $card);
        }

        return [
            'success' => true,
            'semester' => $plan['semester']['name'],
            'listings' => $listingsDeleted,
            'photos' => $plan['totals']['photos'],
            'files_deleted' => count($plan['files']) - count($left),
            'files_left' => array_map('basename', $left),
            'bytes' => $plan['totals']['bytes'],
            'events_deleted' => $eventsDeleted,
            'share_cards' => count($plan['share_cards']),
            'tarball' => $tarball,
            'tarball_bytes' => $tarBytes,
        ];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * The database step: one transaction that re-reads the semester, its listings
 * and their photo paths under lock, refuses if any of it differs from $plan
 * (the tarball would then be missing something), and otherwise writes the
 * archive row, deletes the events and listings (photo rows cascade) and marks
 * the semester archived. Returns [listings deleted, events deleted]; throws,
 * having rolled back, on any mismatch or error.
 */
function archive_commit(PDO $pdo, array $plan, ?string $tarball, string $by): array {
    $code = $plan['semester']['code'];
    $ids = $plan['listing_ids'];
    try {
        $pdo->beginTransaction();

        // Anything that changed since the plan (a poster adding a photo, a
        // listing moving semester) would make the tarball incomplete.
        $hasArchivedAt = isset(table_columns($pdo, 'semesters')['archived_at']);
        $stmt = $pdo->prepare('SELECT active' . ($hasArchivedAt ? ', archived_at' : '') . ' FROM semesters WHERE code = ? FOR UPDATE');
        $stmt->execute([$code]);
        $sem = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sem || $sem['active'] || !empty($sem['archived_at'])) {
            throw new RuntimeException('The semester changed state since the preview.');
        }
        $stmt = $pdo->prepare('SELECT id, image_url, thumbnail_url FROM sublets WHERE semester = ? ORDER BY id FOR UPDATE');
        $stmt->execute([$code]);
        $now = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (array_map(static fn($r) => (int)$r['id'], $now) !== $ids) {
            throw new RuntimeException('Listings were added to or moved out of the semester since the preview.');
        }
        $refsNow = [];
        foreach ($now as $row) {
            foreach (['image_url', 'thumbnail_url'] as $col) {
                if (!empty($row[$col])) {
                    $refsNow[] = $row[$col];
                }
            }
        }
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id IN ($in) FOR UPDATE");
            $stmt->execute($ids);
            $refsNow = array_merge($refsNow, $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        $refsNow = array_values(array_unique($refsNow));
        $refsPlan = $plan['refs'];
        sort($refsNow);
        sort($refsPlan);
        if ($refsNow !== $refsPlan) {
            throw new RuntimeException('Photos changed on these listings since the preview.');
        }

        $t = $plan['totals'];
        $values = [
            'semester_code' => $code,
            'semester_name' => $plan['semester']['name'],
            'archived_by' => $by,
            'listings' => $t['listings'],
            'taken' => $t['taken'],
            'price_median' => $t['price_median'],
            'price_min' => $t['price_min'],
            'price_max' => $t['price_max'],
            'views' => $t['views'],
            'contacts' => $t['contacts'],
            'shares' => $t['shares'],
            'share_arrivals' => $t['share_arrivals'],
            'photos' => $t['photos'],
            'bytes' => $t['bytes'],
            'tarball' => $tarball,
        ];
        // Only the columns that exist: `photos` is optional (see
        // ARCHIVE_TABLE_COLUMNS).
        $values = array_intersect_key($values, table_columns($pdo, 'semester_archives'));
        $pdo->prepare(
            'INSERT INTO semester_archives (`' . implode('`, `', array_keys($values)) . '`) VALUES ('
            . implode(', ', array_fill(0, count($values), '?')) . ')'
        )->execute(array_values($values));

        $eventsDeleted = 0;
        if (table_exists($pdo, 'listing_events')) {
            $stmt = $pdo->prepare('DELETE FROM listing_events WHERE semester = ?');
            $stmt->execute([$code]);
            $eventsDeleted = $stmt->rowCount();
        }

        $listingsDeleted = 0;
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM sublets WHERE semester = ? AND id IN ($in)");
            $stmt->execute(array_merge([$code], $ids));
            $listingsDeleted = $stmt->rowCount();
        }

        $pdo->prepare('UPDATE semesters SET archived_at = CURRENT_TIMESTAMP WHERE code = ?')->execute([$code]);
        $pdo->commit();
        return [$listingsDeleted, $eventsDeleted];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Tarballs this module wrote, newest first: [name, bytes, mtime]. */
function archive_tarballs(): array {
    $out = [];
    foreach (glob(archive_backup_dir() . '/semester-*.tar.gz') ?: [] as $path) {
        $name = basename($path);
        if (preg_match(ARCHIVE_TARBALL_PATTERN, $name) && is_file($path)) {
            $out[] = ['name' => $name, 'bytes' => (int)filesize($path), 'mtime' => (int)filemtime($path)];
        }
    }
    usort($out, static fn($a, $b) => strcmp($b['name'], $a['name']));
    return $out;
}

/**
 * Delete one archive tarball by name. Only names this module writes are
 * accepted, so nothing else in the folder (the pre-backfill backup, the
 * rename journal) can be removed from the web.
 */
function archive_delete_tarball(string $name): bool {
    if (!preg_match(ARCHIVE_TARBALL_PATTERN, $name)) {
        return false;
    }
    $path = archive_backup_dir() . '/' . $name;
    return is_file($path) && !is_link($path) && unlink($path);
}

/** Past archives, newest first. Empty when the table does not exist yet. */
function archive_history(PDO $pdo): array {
    if (!table_exists($pdo, 'semester_archives')) {
        return [];
    }
    return $pdo->query('SELECT * FROM semester_archives ORDER BY archived_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
}
