<?php
/**
 * Photo housekeeping: order and cover, the admin Images tab's inventory, and
 * the orphan sweep. Requires db.php; used by app/api/images.php.
 *
 * Order. A listing's photos are shown by sort_order, and the first one is its
 * card image (sublets.image_url and thumbnail_url). Every change here
 * renumbers the photos 0..n-1 and then makes the card image the first photo,
 * so "first" and "sort_order 0" are the same thing again. They drifted apart
 * when a deleted cover promoted the next photo without renumbering.
 *
 * Orphans. Every file in public/images/ is an upload or one of its generated
 * copies (see CLAUDE.md, "Cleaning up public/images"). A file is in use when
 * its name is stored in sublet_images.image_url, sublets.image_url or
 * sublets.thumbnail_url, when it is the _thumb.webp or _display.webp of one
 * that is, or when the source code names it. Anything else is an orphan.
 */
require_once __DIR__ . '/thumbnail.php';
require_once __DIR__ . '/visibility.php';

/** Photos a request may make thumbnails for before answering. */
const THUMB_BACKFILL_BATCH = 8;

/**
 * Renumber a listing's photos 0..n-1, in $orderedIds order when given (every
 * photo id of the listing, each once) or else in their current order, and make
 * the first one the card image. Returns false if $orderedIds is not exactly
 * the listing's photos.
 */
function renumber_listing_photos(PDO $pdo, int $subletId, ?array $orderedIds = null): bool {
    $stmt = $pdo->prepare('SELECT id FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order, id');
    $stmt->execute([$subletId]);
    $current = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($orderedIds !== null) {
        $orderedIds = array_map('intval', $orderedIds);
        $a = $orderedIds;
        $b = $current;
        sort($a);
        sort($b);
        if ($a !== $b) {
            return false;
        }
        $current = $orderedIds;
    }

    $update = $pdo->prepare('UPDATE sublet_images SET sort_order = ? WHERE id = ? AND sublet_id = ?');
    foreach ($current as $position => $id) {
        $update->execute([$position, $id, $subletId]);
    }

    sync_listing_cover($pdo, $subletId);
    return true;
}

/**
 * Point sublets.image_url and thumbnail_url at the listing's first photo,
 * making its thumbnail if it has none. A listing with no photos is left alone.
 */
function sync_listing_cover(PDO $pdo, int $subletId): void {
    $stmt = $pdo->prepare('SELECT image_url FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order, id LIMIT 1');
    $stmt->execute([$subletId]);
    $first = $stmt->fetchColumn();
    if (!$first) {
        return;
    }

    $thumb = image_variant_path($first, IMAGE_THUMB_SUFFIX);
    if ($thumb === null || !is_file(resolve_path($thumb))) {
        // make_thumbnail() hands back the original when it fails, so the card
        // degrades to the full photo rather than to nothing.
        $thumb = './public/images/' . basename(make_thumbnail(resolve_path($first)));
    }

    $stmt = $pdo->prepare('SELECT image_url, thumbnail_url FROM sublets WHERE id = ?');
    $stmt->execute([$subletId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && ($row['image_url'] !== $first || $row['thumbnail_url'] !== $thumb)) {
        $pdo->prepare('UPDATE sublets SET image_url = ?, thumbnail_url = ? WHERE id = ?')
            ->execute([$first, $thumb, $subletId]);
    }
}

/**
 * Who may change a photo: the admin, or the poster of its listing. Returns
 * the photo row joined to its listing, or null when there is no such photo.
 */
function photo_with_owner(PDO $pdo, int $imageId): ?array {
    $stmt = $pdo->prepare(
        'SELECT si.id, si.sublet_id, si.image_url, si.sort_order, s.username AS owner
         FROM sublet_images si JOIN sublets s ON s.id = si.sublet_id WHERE si.id = ?'
    );
    $stmt->execute([$imageId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** What the Images tab shows for one stored photo. */
function photo_details(string $stored): array {
    $fs = resolve_path($stored);
    $exists = is_file($fs);
    $info = $exists ? @getimagesize($fs) : false;
    $copies = 0;
    foreach ([IMAGE_THUMB_SUFFIX, IMAGE_DISPLAY_SUFFIX] as $suffix) {
        $variant = image_variant_path($fs, $suffix);
        if ($variant !== null && is_file($variant)) {
            $copies += (int)filesize($variant);
        }
    }
    $formats = [IMAGETYPE_JPEG => 'JPEG', IMAGETYPE_PNG => 'PNG', IMAGETYPE_GIF => 'GIF', IMAGETYPE_WEBP => 'WebP'];
    $urls = photo_urls($stored);

    return [
        'name' => basename($stored),
        'missing' => !$exists,
        'original' => $exists ? image_src($stored) : null,
        'display' => $urls['display'],
        'thumb' => $urls['thumb'],
        'width' => $info ? (int)$info[0] : null,
        'height' => $info ? (int)$info[1] : null,
        'format' => $info ? ($formats[$info[2]] ?? strtoupper(pathinfo($fs, PATHINFO_EXTENSION))) : null,
        'bytes' => $exists ? (int)filesize($fs) : 0,
        'copies_bytes' => $copies,
    ];
}

/**
 * Everything the Images tab draws: every listing (hidden ones too) with its
 * photos in order, per-semester storage, and totals for the whole folder.
 */
function image_inventory(PDO $pdo): array {
    $listings = $pdo->query(
        'SELECT s.id, s.username, s.address, s.semester, s.image_url, s.thumbnail_url, s.posted_at,
                COALESCE(sem.name, s.semester) AS semester_name,
                NOT (' . VISIBLE_SEMESTER_WHERE . ') AS is_hidden
         FROM sublets s ' . VISIBLE_SEMESTER_JOIN . '
         ORDER BY is_hidden, sem.sort_order, s.semester, s.posted_at DESC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $photosBy = [];
    foreach ($pdo->query('SELECT id, sublet_id, image_url, sort_order FROM sublet_images ORDER BY sublet_id, sort_order, id') as $row) {
        $photosBy[(int)$row['sublet_id']][] = $row;
    }

    $out = [];
    $semesters = [];
    $missingThumbs = 0;
    foreach ($listings as $listing) {
        $id = (int)$listing['id'];
        $photos = [];
        $bytes = 0;
        foreach ($photosBy[$id] ?? [] as $position => $row) {
            $details = photo_details($row['image_url']) + [
                'id' => (int)$row['id'],
                'sort_order' => (int)$row['sort_order'],
                'cover' => $position === 0,
            ];
            if (!$details['missing'] && $details['thumb'] === null) {
                $missingThumbs++;
            }
            $bytes += $details['bytes'] + $details['copies_bytes'];
            $photos[] = $details;
        }
        $code = (string)$listing['semester'];
        $semesters[$code] ??= ['code' => $code, 'name' => $listing['semester_name'], 'hidden' => (bool)$listing['is_hidden'], 'listings' => 0, 'photos' => 0, 'bytes' => 0];
        $semesters[$code]['listings']++;
        $semesters[$code]['photos'] += count($photos);
        $semesters[$code]['bytes'] += $bytes;

        $out[] = [
            'id' => $id,
            'address' => format_address($listing['address']),
            'username' => $listing['username'],
            'semester' => $code,
            'semester_name' => $listing['semester_name'],
            'hidden' => (bool)$listing['is_hidden'],
            'photos' => $photos,
            'bytes' => $bytes,
            // The card image should be the first photo. Listings where it is
            // not are what the old cover promotion left behind.
            'cover_out_of_step' => $photos && $listing['image_url'] !== ($photosBy[$id][0]['image_url'] ?? null),
        ];
    }

    $disk = image_folder_usage();
    return [
        'listings' => $out,
        'semesters' => array_values($semesters),
        'totals' => [
            'listings' => count($out),
            'photos' => array_sum(array_map(static fn($l) => count($l['photos']), $out)),
            'bytes_used' => array_sum(array_column($out, 'bytes')),
            'files_on_disk' => $disk['files'],
            'bytes_on_disk' => $disk['bytes'],
            'missing_thumbs' => $missingThumbs,
        ],
    ];
}

/** Files and bytes in public/images/, dotfiles (temporaries) excluded. */
function image_folder_usage(): array {
    $files = 0;
    $bytes = 0;
    foreach (scandir(ROOT_DIR . '/public/images') ?: [] as $name) {
        $path = ROOT_DIR . '/public/images/' . $name;
        if ($name[0] !== '.' && is_file($path)) {
            $files++;
            $bytes += (int)filesize($path);
        }
    }
    return ['files' => $files, 'bytes' => $bytes];
}

/**
 * File names the source code mentions as public/images/<name>. A hard-coded
 * reference is a use the database cannot see (the favicon used to be one), so
 * those files are never orphans.
 */
function image_source_refs(): array {
    $names = [];
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator(ROOT_DIR, FilesystemIterator::SKIP_DOTS),
        static function ($file) {
            $rel = substr($file->getPathname(), strlen(ROOT_DIR) + 1);
            if ($file->isDir()) {
                return !in_array($rel, ['vendor', 'public/images', 'public/share', 'node_modules'], true)
                    && $file->getFilename()[0] !== '.';
            }
            return in_array(strtolower($file->getExtension()), ['php', 'js', 'css', 'html', 'json', 'md', 'txt'], true);
        }
    ));
    foreach ($it as $file) {
        if ($file->getSize() > 2 * 1024 * 1024) {
            continue;
        }
        if (preg_match_all('#public/images/([A-Za-z0-9._-]+)#', (string)file_get_contents($file->getPathname()), $m)) {
            foreach ($m[1] as $name) {
                $names[$name] = true;
            }
        }
    }
    return $names;
}

/**
 * The orphan sweep's dry run: files nothing uses, and stored paths with no
 * file. Read-only.
 */
function image_orphans(PDO $pdo): array {
    $stored = [];
    $missing = [];
    $sources = [
        ['sublet_images.image_url', 'SELECT si.image_url AS path, s.id, s.address FROM sublet_images si LEFT JOIN sublets s ON s.id = si.sublet_id'],
        ['sublets.image_url', 'SELECT image_url AS path, id, address FROM sublets'],
        ['sublets.thumbnail_url', 'SELECT thumbnail_url AS path, id, address FROM sublets'],
    ];
    foreach ($sources as [$column, $sql]) {
        foreach ($pdo->query($sql) as $row) {
            if (empty($row['path'])) {
                continue;
            }
            $stored[basename($row['path'])] = true;
            if (!is_file(resolve_path($row['path']))) {
                $missing[] = [
                    'column' => $column,
                    'name' => basename($row['path']),
                    'listing_id' => $row['id'] !== null ? (int)$row['id'] : null,
                    'address' => $row['address'] !== null ? format_address($row['address']) : null,
                ];
            }
        }
    }

    // A generated copy is in use when its original is: x_thumb.webp and
    // x_display.webp belong to x.<anything>.
    $usedStems = [];
    foreach (array_keys($stored) as $name) {
        $usedStems[preg_replace('/\.[^.]+$/', '', $name)] = true;
    }

    $codeRefs = image_source_refs();
    $orphans = [];
    $protected = [];
    foreach (scandir(ROOT_DIR . '/public/images') ?: [] as $name) {
        $path = ROOT_DIR . '/public/images/' . $name;
        if ($name[0] === '.' || !is_file($path) || isset($stored[$name])) {
            continue;
        }
        if (preg_match('/^(.+)_(thumb|display)\.webp$/', $name, $m) && isset($usedStems[$m[1]])) {
            continue;
        }
        if (isset($codeRefs[$name])) {
            $protected[] = $name;
            continue;
        }
        $orphans[] = [
            'name' => $name,
            'bytes' => (int)filesize($path),
            'mtime' => (int)filemtime($path),
            'kind' => preg_match('/_thumb\.webp$/', $name) ? 'thumbnail' : (preg_match('/_display\.webp$/', $name) ? 'display copy' : 'original'),
            'url' => image_src('./public/images/' . $name),
        ];
    }
    usort($orphans, static fn($a, $b) => strcmp($a['name'], $b['name']));

    return [
        'orphans' => $orphans,
        'bytes' => array_sum(array_column($orphans, 'bytes')),
        'missing' => $missing,
        'protected' => $protected,
    ];
}

/**
 * Delete the named orphans, re-checking each against a fresh sweep so a file
 * that came into use since the dry run is kept. Returns [deleted, kept].
 */
function delete_orphans(PDO $pdo, array $names): array {
    $current = array_column(image_orphans($pdo)['orphans'], null, 'name');
    $deleted = [];
    $kept = [];
    foreach ($names as $name) {
        $name = (string)$name;
        if (!preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*\z/', $name) || !isset($current[$name])) {
            $kept[] = $name;
            continue;
        }
        $path = ROOT_DIR . '/public/images/' . $name;
        if (is_file($path) && !is_link($path) && unlink($path)) {
            $deleted[] = $name;
        } else {
            $kept[] = $name;
        }
    }
    return [$deleted, $kept];
}

/**
 * Make up to THUMB_BACKFILL_BATCH missing thumbnails. Returns
 * ['made' => n, 'failed' => [names], 'remaining' => n].
 */
function make_missing_thumbnails(PDO $pdo, array $skip = []): array {
    $made = 0;
    $failed = [];
    $todo = [];
    foreach ($pdo->query('SELECT image_url FROM sublet_images ORDER BY id') as $row) {
        $fs = resolve_path($row['image_url']);
        $thumb = image_variant_path($fs, IMAGE_THUMB_SUFFIX);
        if ($thumb !== null && is_file($fs) && !is_file($thumb) && !in_array(basename($fs), $skip, true)) {
            $todo[] = $fs;
        }
    }
    foreach (array_slice($todo, 0, THUMB_BACKFILL_BATCH) as $fs) {
        if (make_thumbnail($fs) !== $fs) {
            $made++;
        } else {
            $failed[] = basename($fs);
        }
    }
    return ['made' => $made, 'failed' => $failed, 'remaining' => max(0, count($todo) - $made - count($failed))];
}
