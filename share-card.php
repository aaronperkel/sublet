<?php
/**
 * The image a shared listing unfurls into. Public, and fetched by crawlers.
 *
 * This file *is* the public surface of a listing: Instagram, iMessage, Discord
 * and X fetch it without a session and cache the result, so everything painted
 * here is readable by anyone the link reaches. It draws only what
 * share_card_lines() returns — price, semester, size, distance from campus.
 * The address, description, poster name, NetID, email and phone are not
 * available to this file by design; do not add them.
 *
 * Re-encoding through GD also drops EXIF, so the GPS tags that survive in the
 * full-size uploads under public/images/ (already reachable without auth) do
 * not travel into Meta's and X's caches with the card.
 *
 * Nothing here is allowed to 500. A crawler that gets an error caches the
 * absence of a preview, and the next share of that listing silently unfurls
 * into nothing — so every failure path falls back to a static asset instead.
 */
require_once __DIR__ . '/includes/share.php';

/** Output geometry per format. */
const SHARE_CARD_SIZES = [
    'og'    => [1200, 630],
    'story' => [1080, 1920],
];

/**
 * Most pixels we will hand to imagecreatefromstring().
 *
 * GD holds a truecolor image as 4 bytes per pixel, and memory_limit here is
 * 128M. The largest photo already uploaded is 24.5 MP (5712x4284), which alone
 * would need ~98 MB and take the request down. Anything over this budget gets
 * pre-shrunk by ImageMagick — or, failing that, falls back to the 600px
 * thumbnail, which is soft at card size but always renders.
 */
const SHARE_SOURCE_MAX_PIXELS = 12000000;

/** Bumped when the layout changes, so cached cards regenerate. */
const SHARE_CARD_VERSION = 5;

/* ---------------------------------------------------------------- serving */

/** Send a file and stop. Content type follows the extension, not the request. */
function share_serve(string $path): void {
    $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=86400');
    // The card is meant to be embedded by other sites, never indexed as a page.
    header('X-Robots-Tag: noindex');
    readfile($path);
    exit;
}

/**
 * The generic artwork, used whenever a real card cannot be produced.
 *
 * These already ship in the repository and are what landing.php unfurls with,
 * so a listing whose photo is missing still gets a branded preview.
 */
function share_serve_fallback(string $format): void {
    $file = $format === 'story'
        ? __DIR__ . '/assets/social/story-find-a-sublet.png'
        : __DIR__ . '/assets/social/link-preview.png';

    if (is_file($file)) {
        share_serve($file);
    }

    http_response_code(404);
    exit;
}

/* ----------------------------------------------------------------- typing */

/** A bold or regular TTF that exists on this host. */
function share_font(bool $bold = true): string {
    $candidates = $bold
        ? ['/usr/share/fonts/open-sans/OpenSans-Bold.ttf',
           '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans-Bold.ttf']
        : ['/usr/share/fonts/open-sans/OpenSans-Regular.ttf',
           '/usr/share/fonts/dejavu-sans-fonts/DejaVuSans.ttf'];

    foreach ($candidates as $font) {
        if (is_file($font)) {
            return $font;
        }
    }

    throw new RuntimeException('No TTF font available for the share card.');
}

/** Rendered width of a string, in pixels. */
function share_text_width(string $font, float $size, string $text): int {
    if ($text === '') {
        return 0;
    }
    $box = imagettfbbox($size, 0, $font, $text);
    return (int)ceil(max($box[2], $box[4]) - min($box[0], $box[6]));
}

/**
 * Shrink a font size until the string fits.
 *
 * Prices and fact lines are user data — a five-figure rent or a listing with
 * every optional field filled in is longer than the layout assumes, and a
 * clipped price is worse than a smaller one.
 */
function share_fit_size(string $font, float $size, string $text, int $maxWidth, float $min = 14): float {
    while ($size > $min && share_text_width($font, $size, $text) > $maxWidth) {
        $size -= 2;
    }
    return $size;
}

/**
 * Draw a string, optionally letter-spaced.
 *
 * imagettftext() has no tracking, so the wordmark is drawn a character at a
 * time. Split with the /u flag so a multi-byte character is never cut in half.
 */
function share_draw(
    $im, string $font, float $size, int $x, int $y, int $color, string $text, float $tracking = 0
): void {
    if ($tracking <= 0) {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        return;
    }

    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $char);
        $x += share_text_width($font, $size, $char) + (int)round($tracking);
        if ($char === ' ') {
            $x += (int)round($size * 0.25);
        }
    }
}

/**
 * Greedily pack " · "-separated segments into lines that fit.
 *
 * Packing whole segments rather than words is what stops a line ending on a
 * dangling separator — "Spring 2027 · 2 bd ·" — which is what plain word
 * wrapping produces on a string built out of separators.
 */
function share_pack_segments(string $font, float $size, array $segments, int $maxWidth): array {
    $segments = array_values(array_filter(array_map('trim', $segments), static fn($s) => $s !== ''));
    if ($segments === []) {
        return [];
    }

    $lines = [];
    $current = '';

    foreach ($segments as $segment) {
        $candidate = $current === '' ? $segment : $current . ' · ' . $segment;
        if ($current !== '' && share_text_width($font, $size, $candidate) > $maxWidth) {
            $lines[] = $current;
            $current = $segment;
        } else {
            $current = $candidate;
        }
    }

    $lines[] = $current;
    return $lines;
}

/* ---------------------------------------------------------------- imagery */

/** Centre-crop an image to exactly $w x $h without distorting it. */
function share_cover($src, int $w, int $h) {
    $sw = imagesx($src);
    $sh = imagesy($src);
    $scale = max($w / $sw, $h / $sh);

    $cropW = (int)round($w / $scale);
    $cropH = (int)round($h / $scale);

    $dst = imagecreatetruecolor($w, $h);
    imagecopyresampled(
        $dst, $src,
        0, 0,
        (int)round(($sw - $cropW) / 2), (int)round(($sh - $cropH) / 2),
        $w, $h,
        $cropW, $cropH
    );

    return $dst;
}

/**
 * A GD image of the listing's photo, within the pixel budget.
 *
 * Prefers the full-size upload for sharpness and falls back to the 600px
 * thumbnail, pre-shrinking an oversized original with ImageMagick when it is
 * available (includes/thumbnail.php already depends on `convert` being there).
 * Returns null when nothing usable exists, which is a fallback-artwork case,
 * not an error.
 */
function share_load_photo(array $row) {
    $candidates = array_filter([
        (string)($row['image_url'] ?? ''),
        (string)($row['thumbnail_url'] ?? ''),
    ]);

    $oversized = [];

    foreach ($candidates as $stored) {
        $path = resolve_path($stored);
        if (!is_file($path)) {
            continue;
        }

        $info = @getimagesize($path);
        if (!$info) {
            continue;
        }

        if ($info[0] * $info[1] <= SHARE_SOURCE_MAX_PIXELS) {
            $im = @imagecreatefromstring((string)file_get_contents($path));
            if ($im !== false) {
                return $im;
            }
            continue;
        }

        $oversized[] = $path;
    }

    // Everything usable was too large to load directly. Let ImageMagick do the
    // downscale in its own address space rather than ours.
    foreach ($oversized as $path) {
        $tmp = tempnam(sys_get_temp_dir(), 'sublet-card-') ?: null;
        if ($tmp === null) {
            continue;
        }

        @exec(sprintf(
            'convert %s -auto-orient -resize 2000x2000\> -strip %s 2>/dev/null',
            escapeshellarg($path),
            escapeshellarg('jpg:' . $tmp)
        ), $out, $status);

        if ($status === 0 && filesize($tmp) > 0) {
            $im = @imagecreatefromstring((string)file_get_contents($tmp));
            @unlink($tmp);
            if ($im !== false) {
                return $im;
            }
        } else {
            @unlink($tmp);
        }
    }

    return null;
}

/* ---------------------------------------------------------------- drawing */

/** Compose the card. Returns raw JPEG bytes. */
function share_render(array $lines, string $format): string {
    [$w, $h] = SHARE_CARD_SIZES[$format];

    $bold = share_font(true);
    $regular = share_font(false);

    $factLine = trim(implode(' · ', array_filter([$lines['semester'], $lines['facts']])));
    // The same facts unjoined, so the og card can wrap between them.
    $factSegments = array_merge(
        [$lines['semester']],
        $lines['facts'] !== '' ? explode(' · ', $lines['facts']) : []
    );
    $note = $lines['negotiable'] ? 'or best offer' : '';

    $im = imagecreatetruecolor($w, $h);
    $green = imagecolorallocate($im, 21, 71, 52);
    $gold  = imagecolorallocate($im, 255, 209, 0);
    $white = imagecolorallocate($im, 255, 255, 255);
    $muted = imagecolorallocate($im, 197, 214, 206);

    imagefilledrectangle($im, 0, 0, $w, $h, $green);

    $photo = $lines['photo'] ?? null;

    if ($format === 'story') {
        // Photo on top, a solid green panel beneath it for the text. Keeping
        // the two apart means the type is legible over any photo, which a
        // scrim alone cannot promise at story size.
        $photoH = 1150;
        if ($photo) {
            $cover = share_cover($photo, $w, $photoH);
            imagecopy($im, $cover, 0, 0, 0, 0, $w, $photoH);
            imagedestroy($cover);
        }

        $pad = 72;
        $maxW = $w - $pad * 2;

        share_draw($im, $bold, 40, $pad, 1300, $gold, 'UVM SUBLETS', 8);

        $priceSize = share_fit_size($bold, 128, $lines['price'], $maxW);
        share_draw($im, $bold, $priceSize, $pad, 1450, $white, $lines['price']);

        $y = 1520;
        if ($note !== '') {
            share_draw($im, $regular, 36, $pad, $y, $gold, $note);
            $y += 60;
        }

        $factSize = share_fit_size($regular, 44, $factLine, $maxW);
        share_draw($im, $regular, $factSize, $pad, $y, $white, $factLine);

        // A story is a picture, not a link: nobody can tap this, they have to
        // read it off the screen and type it. That is why it is the short link
        // and not the hostname — see SHARE_DISPLAY_URL in share.php.
        share_draw($im, $regular, 32, $pad, $h - 168, $muted, 'Full listing at');
        $domainSize = share_fit_size($bold, 44, SHARE_DISPLAY_URL, $maxW);
        share_draw($im, $bold, $domainSize, $pad, $h - 108, $gold, SHARE_DISPLAY_URL);
    } else {
        // Photo in a near-square panel on the left, text on solid green to the
        // right — the same split the story card uses, turned on its side.
        //
        // The photo used to bleed across the whole 1200x630 frame. That reads
        // well for a landscape shot, but listings are photographed on phones:
        // cover-cropping a 1125x2436 screenshot to 1.9:1 scaled it four times
        // over and kept a sliver out of the middle. A 552x630 panel is 0.88:1,
        // which sits near the middle of the range real uploads actually span
        // (0.46 to 1.5 on the current data), so every one of them loses only
        // its edges. It also puts the type on flat colour instead of a scrim.
        $pad = 56;
        $panelX = $photo ? 552 : 0;

        if ($photo) {
            $cover = share_cover($photo, $panelX, $h);
            imagecopy($im, $cover, 0, 0, 0, 0, $panelX, $h);
            imagedestroy($cover);
        }

        $textX = $panelX + $pad;
        $maxW = $w - $textX - $pad;

        // Measured before anything is drawn so the block can be centred: the
        // fact line wraps to one or two lines depending on the listing, and a
        // top-anchored block visibly sags on the short ones.
        // Wrapping handles the length of the line; fitting handles the length
        // of any single segment, which wrapping cannot break ("Nonbinary /
        // gender-diverse" is one token as far as the packer is concerned).
        $factSize = 30;
        foreach ($factSegments as $segment) {
            $factSize = share_fit_size($regular, $factSize, $segment, $maxW);
        }
        $factLines = share_pack_segments($regular, $factSize, $factSegments, $maxW);
        $priceSize = share_fit_size($bold, 76, $lines['price'], $maxW);

        $blockH = 34 + 40 + $priceSize + 18 + ($note !== '' ? 44 : 0) + count($factLines) * 42;
        $y = (int)round(($h - $blockH) / 2) + 26;

        share_draw($im, $bold, 26, $textX, $y, $gold, 'UVM SUBLETS', 6);
        $y += 40 + (int)round($priceSize);

        share_draw($im, $bold, $priceSize, $textX, $y, $gold, $lines['price']);
        $y += 18;

        if ($note !== '') {
            $y += 26;
            share_draw($im, $regular, 26, $textX, $y, $white, $note);
            $y += 18;
        }

        foreach ($factLines as $line) {
            $y += 42;
            share_draw($im, $regular, $factSize, $textX, $y, $white, $line);
        }
    }

    ob_start();
    imagejpeg($im, null, 90);
    $bytes = (string)ob_get_clean();

    imagedestroy($im);

    return $bytes;
}

/* ---------------------------------------------------------------- request */

$slug = (string)($_GET['i'] ?? '');
$format = (($_GET['f'] ?? 'og') === 'story') ? 'story' : 'og';

// db.php first, and not only for $pdo: it is what loads .env, and share.php
// reads the token secret out of $_ENV. Verifying the slug before this point
// throws for want of a key, on every request — which is the one outcome this
// file is not allowed to have.
try {
    require_once __DIR__ . '/includes/db.php';
} catch (Throwable $e) {
    share_serve_fallback($format);
}

try {
    $id = parse_share_slug($slug);
} catch (Throwable $e) {
    $id = null;
}

if ($id === null) {
    http_response_code(404);
    exit;
}

try {
    // Same visibility rule as the rest of the public site: a listing hidden by
    // a deactivated semester must not keep unfurling from an old link.
    $stmt = $pdo->prepare(
        "SELECT s.*, COALESCE(sem.name, s.semester) AS semester_name, "
        . campus_distance_expr() . " AS distance_mi
           FROM sublets s " . VISIBLE_SEMESTER_JOIN . "
          WHERE s.id = ? AND " . VISIBLE_SEMESTER_WHERE
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $row = null;
}

if ($row === null) {
    share_serve_fallback($format);
}

$lines = share_card_lines($row);

// Cache key covers everything the picture depends on, so editing a listing or
// changing the layout produces a new file rather than a stale one.
$photoPath = resolve_path((string)($row['image_url'] ?? ''));
$fingerprint = substr(md5(implode('|', [
    SHARE_CARD_VERSION,
    $row['image_url'] ?? '',
    is_file($photoPath) ? (string)filemtime($photoPath) : '0',
    $lines['price'],
    $lines['semester'],
    $lines['facts'],
    $lines['negotiable'] ? '1' : '0',
])), 0, 10);

$cacheDir = ROOT_DIR . '/public/share';
$cacheFile = $cacheDir . '/' . $id . '-' . $format . '-' . $fingerprint . '.jpg';

if (is_file($cacheFile)) {
    share_serve($cacheFile);
}

try {
    $lines['photo'] = share_load_photo($row);
    $bytes = share_render($lines, $format);

    if ($lines['photo']) {
        imagedestroy($lines['photo']);
    }
} catch (Throwable $e) {
    share_serve_fallback($format);
}

// Best-effort cache. A read-only directory should cost speed, not the preview,
// so a failed write still serves the bytes we already have in hand.
if (is_dir($cacheDir) || @mkdir($cacheDir, 0755, true)) {
    foreach (glob($cacheDir . '/' . $id . '-' . $format . '-*.jpg') ?: [] as $stale) {
        @unlink($stale);
    }
    @file_put_contents($cacheFile, $bytes, LOCK_EX);
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: public, max-age=86400');
header('X-Robots-Tag: noindex');
echo $bytes;
