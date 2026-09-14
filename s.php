<?php
/**
 * The public landing point for a shared listing. No authentication.
 *
 * A share link cannot point into app/ — that directory is AuthType CAS, so the
 * preview crawlers behind iMessage, Instagram, Snapchat and Discord get the 302
 * to idp.uvm.edu and never see any HTML. This page exists to be the thing they
 * can read: it carries the meta tags, and hands a human off to app/ behind SSO.
 *
 * It is an interstitial rather than a redirect on purpose. A recipient who is
 * not a UVM student would otherwise be bounced into CAS and land on a bare 403
 * with no idea what they had been sent.
 *
 * WHAT IS PUBLIC HERE. Everything on this page is readable by anyone the link
 * reaches, including the preview card, which third parties fetch and cache.
 * That is the whole of it: price, semester, size and distance from campus, via
 * share_card_lines(). The address, description, poster name, NetID, contact
 * email and phone are never loaded into the markup and must not be added —
 * they are the reason app/ is behind CAS in the first place.
 *
 * Self-contained like landing.php and recover/index.php: its own inline styles,
 * its own copy of the design tokens, no includes/header.php. header.php runs
 * three unguarded filter-bounds queries and reads REMOTE_USER, so it would
 * fatal here on a webdb outage — and a link already sent to a group chat has to
 * keep resolving through one.
 */
require_once __DIR__ . '/includes/share.php';

$slug = (string)($_GET['i'] ?? '');
$listing = null;

// db.php first, and not only for $pdo: it is what loads .env, and share.php
// reads the token secret out of $_ENV. Verifying the slug before this point
// throws for want of a key. Best-effort throughout, as in landing.php.
try {
    require_once __DIR__ . '/includes/db.php';

    $id = parse_share_slug($slug);

    if ($id !== null) {
        // The same visibility rule the rest of the public site uses: a listing
        // hidden by a deactivated semester must stop resolving from old links
        // too, not just disappear from Browse.
        $stmt = $pdo->prepare(
            "SELECT s.*, COALESCE(sem.name, s.semester) AS semester_name, "
            . campus_distance_expr() . " AS distance_mi
               FROM sublets s " . VISIBLE_SEMESTER_JOIN . "
              WHERE s.id = ? AND " . VISIBLE_SEMESTER_WHERE
        );
        $stmt->execute([$id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) {
    $listing = null;
}

// A bad token, an unknown id, a hidden listing and a database outage all render
// the same page. Distinguishing them would turn this into an oracle for which
// listing ids exist and which tokens are genuine.
if ($listing === null) {
    http_response_code(404);
    $lines = null;
    $canonical = SHARE_SHORT_URL;
    $pageTitle = 'Listing unavailable — UVM Sublets';
    $pageDesc = 'This sublet listing is no longer available.';
    $cardImage = SHARE_ORIGIN . '/assets/social/link-preview.png';
    $appUrl = SHARE_ORIGIN . '/app/';
} else {
    $lines = share_card_lines($listing);
    $canonicalSlug = share_slug((int)$listing['id']);
    $canonical = SHARE_ORIGIN . '/s/' . $canonicalSlug;
    $pageTitle = $lines['title'];
    $pageDesc = $lines['description'];
    $cardImage = SHARE_ORIGIN . '/share-card.php?i=' . rawurlencode($canonicalSlug);
    $appUrl = SHARE_ORIGIN . '/app/index.php?id=' . (int)$listing['id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">

    <?php /* Unfurl freely, index never. A share link is meant to be pasted into
             a group chat, not to put a student's listing into search results —
             and robots.txt disallows /s/ for the crawlers that honour it. */ ?>
    <meta name="robots" content="noindex, nofollow">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="UVM Sublets">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($cardImage) ?>">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($cardImage) ?>">
    <meta name="theme-color" content="#154734">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%23154734'/%3E%3Cpath d='M32 12 L52 28 L52 52 L38 52 L38 38 L26 38 L26 52 L12 52 L12 28 Z' fill='%23FFD100'/%3E%3C/svg%3E">
    <style>
        :root {
            --green: #154734;
            --green-light: #1a5a43;
            --gold: #FFD100;
            --slate: #00313C;
            --fog: #F7F7F7;
            --white: #FFFFFF;
            --text: #00313C;
            --text-secondary: #4a5e63;
            --border-light: #e4e9ea;
            --shadow-lg: 0 8px 32px rgba(0, 49, 60, 0.14);
            --radius: 12px;
            --transition: 0.2s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--text);
            background: var(--fog);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .share-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
        }

        /* height:auto and nothing else. object-fit:cover here meant the card
           got cropped a second time, by the browser, on top of the crop
           share-card.php had already composed — so the layout could never
           disagree with the 1200x630 the og:image tag promises. The width and
           height attributes on the tag reserve the space while it loads. */
        .share-preview {
            display: block;
            width: 100%;
            height: auto;
            background: var(--green);
        }

        .share-body { padding: 1.75rem; text-align: center; }

        .share-body h1 {
            font-size: 1.15rem;
            color: var(--green);
            margin-bottom: 0.4rem;
        }

        .share-lede {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .share-btn {
            display: block;
            background: var(--green);
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            padding: 0.85rem 1.25rem;
            border-radius: var(--radius);
            transition: background var(--transition);
        }

        .share-btn:hover, .share-btn:focus-visible { background: var(--green-light); }

        .share-note {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.9rem;
        }

        .share-alt {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-light);
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .share-alt a { color: var(--green); font-weight: 600; }

        .share-foot {
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .share-foot a { color: var(--green); text-decoration: none; font-weight: 600; }
        .share-foot a:hover { text-decoration: underline; }

        :focus-visible { outline: 3px solid var(--gold); outline-offset: 2px; }
    </style>
</head>
<body>
    <main class="share-card">
        <?php if ($lines !== null): ?>
            <?php /* The same bytes the og:image tag points at, so displaying it
                     here exposes nothing the link preview has not already
                     shown to everyone who received the link. */ ?>
            <img class="share-preview" src="<?= htmlspecialchars($cardImage) ?>"
                 alt="<?= htmlspecialchars($lines['title']) ?>" width="1200" height="630">
        <?php endif; ?>

        <div class="share-body">
            <?php if ($lines !== null): ?>
                <h1>Someone shared a sublet with you</h1>
                <p class="share-lede">Sign in to see the address, photos and how to get in touch.</p>
                <a class="share-btn" href="<?= htmlspecialchars($appUrl) ?>">Sign in with your UVM NetID</a>
                <p class="share-note">The address and contact details are only shown to signed-in UVM students.</p>
            <?php else: ?>
                <h1>This listing isn&rsquo;t available</h1>
                <p class="share-lede">
                    It may have been taken down, or the link may be incomplete.
                    Everything currently posted is on the site.
                </p>
                <a class="share-btn" href="<?= htmlspecialchars($appUrl) ?>">Browse sublets</a>
            <?php endif; ?>

            <p class="share-alt">
                Not a UVM student?
                <a href="<?= htmlspecialchars(SHARE_ORIGIN) ?>/demo/">See how the site works &rarr;</a>
            </p>
        </div>
    </main>

    <p class="share-foot"><a href="<?= htmlspecialchars(SHARE_SHORT_URL) ?>">UVM Sublets</a></p>
</body>
</html>
