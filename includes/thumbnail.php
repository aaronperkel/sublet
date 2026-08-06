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
 * Delete an image referenced by a DB-stored path, together with the
 * `_thumb.webp` sibling make_thumbnail() may have written next to it.
 *
 * Callers used to unlink only the image itself, which left thumbnails behind
 * on every delete. Requires resolve_path() from db.php.
 */
function delete_image_files(?string $urlPath): void {
    if (empty($urlPath)) return;

    $fs = resolve_path($urlPath);
    if (is_file($fs)) @unlink($fs);

    $thumb = preg_replace('/\.[^.]+$/', '_thumb.webp', $fs);
    if ($thumb && $thumb !== $fs && is_file($thumb)) @unlink($thumb);
}

/**
 * Ensure an uploaded image is browser-safe (JPEG/PNG/WebP/GIF).
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
            return $jpegPath;
        }

        return $filePath;
    }

    // Auto-orient browser-safe images (fix sideways phone photos)
    if ($info) {
        $escaped = escapeshellarg($filePath);
        @exec("convert $escaped -auto-orient $escaped 2>/dev/null");
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

    // Build thumbnail path from original path, _thumb.webp suffix
    $thumbPath = preg_replace('/\.[^.]+$/', '_thumb.webp', $originalPath);

    $success = imagewebp($thumb, $thumbPath, $quality);
    imagedestroy($src);
    imagedestroy($thumb);
    if ($heicConverted) @unlink($heicConverted);

    return $success ? $thumbPath : $originalPath;
}
