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

/** Files generated next to an upload, named by replacing its extension. */
const IMAGE_THUMB_SUFFIX = '_thumb.webp';
const IMAGE_DISPLAY_SUFFIX = '_display.webp';

/** Longest edge, in pixels, an original upload is stored at. */
const IMAGE_ORIGINAL_MAX_EDGE = 3000;

/** Longest edge of the `_display.webp` the listing gallery shows. */
const IMAGE_DISPLAY_MAX_EDGE = 1600;

/**
 * The metadata every image this app writes keeps: the ICC colour profile, and
 * nothing else.
 *
 * Dropping the rest is a privacy fix, not tidiness. Phone photos carry the GPS
 * position they were taken at, and public/images/ is served without
 * authentication at URLs built from a NetID. ICC is the exception because
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
 * The size cap also keeps make_thumbnail() inside memory_limit: GD holds 4
 * bytes a pixel, and a 5712x4284 phone photo is 98 MB of that on its own.
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
 * through ImageMagick rather than GD for the memory reason above; `[0]` takes
 * the first frame of an animated GIF.
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
 * Generate a WebP thumbnail from a source image.
 * Returns the thumbnail path, or the original path on failure.
 */
function make_thumbnail(string $sourcePath, int $maxWidth = 600, int $quality = 80): string {
    if (!file_exists($sourcePath)) return $sourcePath;

    $originalPath = $sourcePath;
    $info = @getimagesize($sourcePath);
    $heicConverted = null;

    // HEIC/HEIF: convert to JPEG first, then process
    if (!$info) {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if ($ext === 'heic' || $ext === 'heif') {
            $heicConverted = $sourcePath . '.tmp.jpg';
            // Try sips (macOS) then convert (ImageMagick)
            $escaped = escapeshellarg($sourcePath);
            $escapedOut = escapeshellarg($heicConverted);
            if (PHP_OS_FAMILY === 'Darwin') {
                @exec("sips -s format jpeg $escaped --out $escapedOut 2>/dev/null", $out, $ret);
            } else {
                @exec("convert $escaped $escapedOut 2>/dev/null", $out, $ret);
            }
            if (isset($ret) && $ret === 0 && file_exists($heicConverted)) {
                $info = @getimagesize($heicConverted);
                $sourcePath = $heicConverted;
            } else {
                @unlink($heicConverted);
                return $sourcePath;
            }
        } else {
            return $sourcePath;
        }
    }

    $mime = $info['mime'];
    $origW = $info[0];
    $origH = $info[1];

    // Load source image
    switch ($mime) {
        case 'image/jpeg':
            $src = @imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $src = @imagecreatefrompng($sourcePath);
            break;
        case 'image/webp':
            $src = @imagecreatefromwebp($sourcePath);
            break;
        case 'image/gif':
            $src = @imagecreatefromgif($sourcePath);
            break;
        default:
            if ($heicConverted) @unlink($heicConverted);
            return $sourcePath;
    }

    if (!$src) {
        if ($heicConverted) @unlink($heicConverted);
        return $originalPath;
    }

    // Calculate new dimensions
    if ($origW <= $maxWidth) {
        $newW = $origW;
        $newH = $origH;
    } else {
        $newW = $maxWidth;
        $newH = (int)round($origH * ($maxWidth / $origW));
    }

    // Resize
    $thumb = imagecreatetruecolor($newW, $newH);
    // Preserve transparency for PNG sources
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $thumbPath = image_variant_path($originalPath, IMAGE_THUMB_SUFFIX);

    $success = $thumbPath !== null && imagewebp($thumb, $thumbPath, $quality);
    imagedestroy($src);
    imagedestroy($thumb);
    if ($heicConverted) @unlink($heicConverted);

    return $success ? $thumbPath : $originalPath;
}
