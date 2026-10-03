<?php
/**
 * Decide the extension an upload may be saved under, based on the file's own
 * bytes rather than the name the client sent.
 *
 * This is a security boundary, not a convenience check. Uploads land in
 * public/images/, which Apache serves directly, so honouring a client-supplied
 * extension would let any signed-in user drop a .php file into a web-reachable
 * directory and execute it.
 *
 * Returns null when the file is not a supported image; the caller must skip it.
 */
function safe_image_extension(string $tmpPath): ?string {
    $byType = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_BMP  => 'bmp',
    ];

    $info = @getimagesize($tmpPath);
    if ($info && isset($info[2], $byType[$info[2]])) {
        return $byType[$info[2]];
    }

    // getimagesize() cannot read HEIC/HEIF. Accept it only when the container
    // header says so — ensure_browser_safe() converts it to JPEG afterwards.
    return is_heic_file($tmpPath) ? 'heic' : null;
}

/**
 * Detect HEIC/HEIF by its ISO base media file format header.
 */
function is_heic_file(string $path): bool {
    $fh = @fopen($path, 'rb');
    if (!$fh) return false;
    $header = fread($fh, 12);
    fclose($fh);

    if (strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') return false;

    $brand = strtolower(substr($header, 8, 4));
    return in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs', 'mif1', 'msf1'], true);
}

/**
 * A fresh name for an upload: 32 random hex characters plus the extension.
 *
 * Uploads used to be named {username}_{n}.{ext}. public/images/ is served
 * without authentication, so anyone who knew a NetID could fetch
 * {netid}_0.jpg and learn whether that student had a listing, and see inside
 * their place. 128 random bits cannot be guessed. The generated siblings
 * below take the same name, so they are no more guessable than the original.
 */
function new_upload_name(string $ext): string {
    return bin2hex(random_bytes(16)) . '.' . $ext;
}

/** Files generated next to an upload, named by replacing its extension. */
const IMAGE_THUMB_SUFFIX = '_thumb.webp';
const IMAGE_DISPLAY_SUFFIX = '_display.webp';

/** Longest edge, in pixels, an original upload is stored at. */
const IMAGE_ORIGINAL_MAX_EDGE = 3000;

/** Longest edge of the `_display.webp` the listing gallery shows. */
const IMAGE_DISPLAY_MAX_EDGE = 1600;

/** Width of the `_thumb.webp` a listing card shows. */
const IMAGE_THUMB_WIDTH = 600;

/**
 * The metadata every image this app writes keeps: the ICC colour profile, and
 * nothing else.
 *
 * Dropping the rest is a privacy fix, not tidiness. Phone photos carry the GPS
 * position they were taken at, and public/images/ is served without
 * authentication. ICC is the exception because
 * iPhones shoot in Display P3: a P3 photo whose profile is removed is read as
 * sRGB and comes out visibly washed out, which is what -strip would do.
 */
const IMAGE_KEEP_ONLY_ICC = ['+profile', '!icc,*'];

/**
 * Run ImageMagick's `convert` over one file.
 *
 * Every argument is shell-escaped, operators included, so a geometry such as
 * `3000x3000>` reaches convert intact instead of being read as a redirect.
 */
function run_convert(string $in, array $ops, string $out): bool {
    $cmd = 'convert ' . escapeshellarg($in);
    foreach ($ops as $op) {
        $cmd .= ' ' . escapeshellarg($op);
    }
    $cmd .= ' ' . escapeshellarg($out) . ' 2>/dev/null';

    @exec($cmd, $output, $ret);
    return $ret === 0;
}

/**
 * The path of a file generated next to an upload: `x.jpg` becomes
 * `x_thumb.webp`. Works on DB-stored and filesystem paths alike.
 *
 * Null when the path has no extension to replace, where the "variant" would
 * be the file itself. The character class excludes `/` so that a dot earlier
 * in the path — `./public/...`, or the docroot's own name — is never mistaken
 * for one.
 */
function image_variant_path(string $path, string $suffix): ?string {
    $variant = preg_replace('#\.[^./]+$#', $suffix, $path);
    return ($variant === null || $variant === $path) ? null : $variant;
}

/**
 * Write a file next to its final path, then rename it into place, so a request
 * never reads half an image.
 *
 * The temporary is a dotfile, which the root .htaccess refuses to serve, and it
 * keeps the final extension because that is how convert picks the format.
 */
function convert_into_place(string $in, array $ops, string $out): bool {
    $tmp = dirname($out) . '/.tmp-' . getmypid() . '-' . basename($out);

    if (run_convert($in, $ops, $tmp) && is_file($tmp) && rename($tmp, $out)) {
        return true;
    }

    @unlink($tmp);
    return false;
}

/**
 * Rewrite an upload in place the way every original is stored: rotated
 * upright, no longer than IMAGE_ORIGINAL_MAX_EDGE on its long side, and with
 * every profile but ICC removed (see IMAGE_KEEP_ONLY_ICC).
 *
 * The cap also bounds what share-card.php loads into GD, which holds 4 bytes
 * a pixel inside memory_limit: a 5712x4284 phone photo is 98 MB on its own.
 */
function normalize_original(string $path): bool {
    $edge = IMAGE_ORIGINAL_MAX_EDGE . 'x' . IMAGE_ORIGINAL_MAX_EDGE . '>';
    $ops = array_merge(['-auto-orient'], IMAGE_KEEP_ONLY_ICC, ['-resize', $edge]);

    return convert_into_place($path, $ops, $path);
}

/**
 * Write the `_display.webp` the listing gallery shows in place of the original.
 *
 * The gallery used to load originals as uploaded, and those reach 20 MB. This
 * is the same photo at IMAGE_DISPLAY_MAX_EDGE, typically 90-170 KB. It goes
 * through ImageMagick rather than GD for the memory reason above and to keep
 * the ICC profile; `[0]` takes the first frame of an animated GIF.
 *
 * Returns the display image's path, or null if one could not be made. Readers
 * go through display_src(), which falls back to the original.
 */
function make_display_image(string $sourcePath): ?string {
    $out = image_variant_path($sourcePath, IMAGE_DISPLAY_SUFFIX);
    if ($out === null || !is_file($sourcePath)) {
        return null;
    }

    $edge = IMAGE_DISPLAY_MAX_EDGE . 'x' . IMAGE_DISPLAY_MAX_EDGE . '>';
    $ops = array_merge(['-auto-orient'], IMAGE_KEEP_ONLY_ICC, ['-resize', $edge, '-quality', '80']);

    return convert_into_place($sourcePath . '[0]', $ops, $out) ? $out : null;
}

/**
 * URL to show an upload at gallery size: its `_display.webp` when one exists,
 * otherwise the original. Requires image_src() and resolve_path() from db.php.
 */
function display_src(?string $stored): string {
    if ($stored !== null && $stored !== '') {
        $display = image_variant_path($stored, IMAGE_DISPLAY_SUFFIX);
        if ($display !== null && is_file(resolve_path($display))) {
            return image_src($display);
        }
    }

    return image_src($stored);
}

/**
 * The URLs the listing view needs for one stored photo: `display`, from
 * display_src(), and `thumb`, its `_thumb.webp`, or null where none has been
 * made (only card images have one). Both carry ?v= like every image URL.
 */
function photo_urls(string $stored): array {
    $thumb = image_variant_path($stored, IMAGE_THUMB_SUFFIX);

    return [
        'display' => display_src($stored),
        'thumb' => ($thumb !== null && is_file(resolve_path($thumb))) ? image_src($thumb) : null,
    ];
}

/**
 * Every listing's photos, in gallery order, keyed by listing id: one query for
 * the whole page.
 *
 * Browse and Map hand these out with each listing (data-photos on a card,
 * `photos` in MAP_SUBLETS), so opening one knows how many photos it has and
 * where they are without asking images.php first. The view used to open on
 * the card image alone and fetch the list, and its arrows and "1 / N" counter
 * waited that whole round trip. images.php stays as the fallback.
 *
 * A listing with no sublet_images rows falls back to its card image.
 *
 * @param array $listings rows with at least `id` and the stored `image_url`
 */
function listing_photos(PDO $pdo, array $listings): array {
    $ids = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $listings)));
    $photos = array_fill_keys($ids, []);

    if ($ids) {
        $stmt = $pdo->prepare(
            'SELECT sublet_id, image_url FROM sublet_images WHERE sublet_id IN ('
            . implode(',', array_fill(0, count($ids), '?'))
            . ') ORDER BY sublet_id, sort_order, id'
        );
        $stmt->execute($ids);
        foreach ($stmt as $row) {
            $photos[(int)$row['sublet_id']][] = photo_urls($row['image_url']);
        }
    }

    foreach ($listings as $row) {
        $id = (int)$row['id'];
        if (!$photos[$id] && !empty($row['image_url'])) {
            $photos[$id][] = photo_urls($row['image_url']);
        }
    }

    return $photos;
}

/**
 * Delete an image referenced by a DB-stored path, together with the
 * `_thumb.webp` and `_display.webp` generated next to it.
 *
 * Callers used to unlink only the image itself, which left thumbnails behind
 * on every delete. Requires resolve_path() from db.php.
 */
function delete_image_files(?string $urlPath): void {
    if (empty($urlPath)) return;

    $fs = resolve_path($urlPath);
    if (is_file($fs)) @unlink($fs);

    foreach ([IMAGE_THUMB_SUFFIX, IMAGE_DISPLAY_SUFFIX] as $suffix) {
        $variant = image_variant_path($fs, $suffix);
        if ($variant !== null && is_file($variant)) @unlink($variant);
    }
}

/**
 * Ensure an uploaded image is browser-safe (JPEG/PNG/WebP/GIF), and store it
 * the way every original is stored — see normalize_original().
 * Converts HEIC/HEIF and other unsupported formats to JPEG.
 * Returns the (possibly new) file path.
 */
function ensure_browser_safe(string $filePath): string {
    $info = @getimagesize($filePath);
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // Convert HEIC/HEIF to JPEG
    if (!$info && ($ext === 'heic' || $ext === 'heif')) {
        $jpegPath = preg_replace('/\.[^.]+$/', '.jpg', $filePath);
        $escaped = escapeshellarg($filePath);
        $escapedOut = escapeshellarg($jpegPath);

        if (PHP_OS_FAMILY === 'Darwin') {
            @exec("sips -s format jpeg $escaped --out $escapedOut 2>/dev/null", $out, $ret);
        } else {
            @exec("convert $escaped -auto-orient $escapedOut 2>/dev/null", $out, $ret);
        }

        if (isset($ret) && $ret === 0 && file_exists($jpegPath)) {
            @unlink($filePath);
            normalize_original($jpegPath);
            return $jpegPath;
        }

        return $filePath;
    }

    // Upright, size-capped and stripped of GPS.
    if ($info) {
        normalize_original($filePath);
    }

    return $filePath;
}

/**
 * Write the `_thumb.webp` a listing card shows: IMAGE_THUMB_WIDTH wide, ICC only.
 *
 * ImageMagick, like make_display_image(). This used to be GD, which discards the
 * ICC profile, so every Display P3 card thumbnail came out slightly washed out
 * next to the same photo in the gallery.
 *
 * Returns the thumbnail path, or the source path on failure. Callers store
 * whichever comes back as sublets.thumbnail_url, so a failure degrades to the
 * card showing the original rather than nothing.
 */
function make_thumbnail(string $sourcePath, int $maxWidth = IMAGE_THUMB_WIDTH, int $quality = 80): string {
    $out = image_variant_path($sourcePath, IMAGE_THUMB_SUFFIX);
    if ($out === null || !is_file($sourcePath)) {
        return $sourcePath;
    }

    $ops = array_merge(['-auto-orient'], IMAGE_KEEP_ONLY_ICC,
        ['-resize', $maxWidth . 'x>', '-quality', (string)$quality]);

    return convert_into_place($sourcePath . '[0]', $ops, $out) ? $out : $sourcePath;
}

/**
 * A php.ini size ("30M", "120M", "1G") in bytes, for telling the post form the
 * limits it will be held to. 0 when unset or unlimited.
 */
function ini_bytes(string $key): int {
    $value = trim((string)ini_get($key));
    if ($value === '' || !preg_match('/^(\d+)\s*([KMG]?)$/i', $value, $m)) {
        return 0;
    }
    $shift = ['' => 0, 'K' => 10, 'M' => 20, 'G' => 30][strtoupper($m[2])];
    return (int)$m[1] << $shift;
}
