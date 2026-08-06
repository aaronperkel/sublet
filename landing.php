<?php
/**
 * Public landing page. No authentication, and the only page most visitors ever
 * see, so nothing below is allowed to take it down: the whole database section
 * is best-effort. If webdb is unreachable the page still renders, just without
 * the photo strip and the live counts.
 *
 * Only listings the signed-in site would show are used (visibility.php), and
 * only their photos — no address, price, or username leaves the app.
 */
$showcaseImages = [];
$liveCount = null;
$liveSemesters = [];

try {
    require_once __DIR__ . '/includes/db.php';

    $stmt = $pdo->query(
        "SELECT COALESCE(NULLIF(s.thumbnail_url, ''), s.image_url) AS img
         FROM sublets s " . VISIBLE_SEMESTER_JOIN . "
         WHERE " . VISIBLE_SEMESTER_WHERE . "
         ORDER BY s.id DESC
         LIMIT 12"
    );

    // A tile is 240x160 and purely decorative, so it is never worth a large
    // file. Listings whose thumbnail_url still points at a full-size upload
    // (images added during an edit never get a _thumb.webp generated) would
    // otherwise put several megabytes on the front page.
    $maxTileBytes = 400 * 1024;

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $img) {
        if (!$img) {
            continue;
        }
        // A row can outlive its file (see the image API notes); a missing file
        // would render as a broken tile in the middle of the strip.
        $fsPath = resolve_path($img);
        if (is_file($fsPath) && filesize($fsPath) <= $maxTileBytes) {
            $showcaseImages[] = $img;
        }
    }

    $stmtCount = $pdo->query(
        "SELECT COUNT(*) FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE
    );
    $liveCount = (int)$stmtCount->fetchColumn();

    $stmtSem = $pdo->query(
        "SELECT DISTINCT COALESCE(sem.name, s.semester) AS name
         FROM sublets s " . VISIBLE_SEMESTER_JOIN . "
         WHERE " . VISIBLE_SEMESTER_WHERE . "
         ORDER BY name"
    );
    $liveSemesters = $stmtSem->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $showcaseImages = [];
    $liveCount = null;
    $liveSemesters = [];
}

// A marquee needs enough tiles to fill the viewport twice over before the loop
// reads as a loop. Repeat what we have until there are at least eight.
if ($showcaseImages && count($showcaseImages) < 8) {
    $source = $showcaseImages;
    while (count($showcaseImages) < 8) {
        $showcaseImages = array_merge($showcaseImages, $source);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UVM Sublets — Find Your Next Sublet</title>
    <meta name="description" content="Find and post sublet listings exclusively for UVM students. Browse available sublets near campus, post your own, and connect with fellow Catamounts.">
    <meta name="author" content="Aaron Perkel">

    <?php /* Link preview for GroupMe, Discord, iMessage and Instagram bios.
             og:image has to be an absolute URL, and the canonical host is
             hardcoded so a link shared from any hostname still unfurls. */ ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="UVM Sublets">
    <meta property="og:title" content="UVM Sublets — Find Your Next Sublet">
    <meta property="og:description" content="Browse and post sublets near campus. UVM students only.">
    <meta property="og:url" content="https://sublet.aperkel.w3.uvm.edu/">
    <meta property="og:image" content="https://sublet.aperkel.w3.uvm.edu/assets/social/link-preview.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="UVM Sublets — Find Your Next Sublet">
    <meta name="twitter:description" content="Browse and post sublets near campus. UVM students only.">
    <meta name="twitter:image" content="https://sublet.aperkel.w3.uvm.edu/assets/social/link-preview.png">
    <meta name="theme-color" content="#154734">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%23154734'/%3E%3Cpath d='M32 12 L52 28 L52 52 L38 52 L38 38 L26 38 L26 52 L12 52 L12 28 Z' fill='%23FFD100'/%3E%3C/svg%3E">
    <script src="https://kit.fontawesome.com/c428e5511d.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --green: #154734;
            --green-light: #1a5a43;
            --gold: #FFD100;
            --slate: #00313C;
            --sky: #489FDF;
            --fog: #F7F7F7;
            --white: #FFFFFF;
            --text: #00313C;
            --text-secondary: #4a5e63;
            --shadow-lg: 0 8px 32px rgba(0, 49, 60, 0.14);
            --radius: 12px;
            --radius-sm: 8px;
            --transition: 0.2s ease;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--text);
            background: var(--fog);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Hero */
        .hero {
            background: var(--green);
            color: var(--white);
            text-align: center;
            padding: 5rem 1.5rem 4rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 209, 0, 0.06) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -15%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(72, 159, 223, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 680px;
        }

        .hero-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: var(--gold);
            color: var(--green);
            border-radius: 16px;
            font-size: 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 24px rgba(255, 209, 0, 0.25);
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
        }

        .hero h1 span {
            color: var(--gold);
        }

        .hero p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 520px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }

        .hero-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: var(--gold);
            color: var(--green);
            font-size: 1.1rem;
            font-weight: 600;
            padding: 0.9rem 2rem;
            border-radius: var(--radius);
            text-decoration: none;
            transition: transform var(--transition), box-shadow var(--transition);
            box-shadow: 0 4px 16px rgba(255, 209, 0, 0.3);
        }

        .hero-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(255, 209, 0, 0.4);
            color: var(--green);
        }

        .hero-cta i {
            font-size: 0.95rem;
            transition: transform var(--transition);
        }

        .hero-cta:hover i {
            transform: translateX(3px);
        }

        /* Live counts under the hero button. Block-level flex, not inline-flex:
           the CTA above is an inline-level box, so an inline-flex sibling would
           sit on the same line as the button instead of below it. */
        .hero-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 1.75rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.75);
        }

        .hero-stats .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 3px rgba(255, 209, 0, 0.2);
        }

        .hero-stats strong {
            color: var(--white);
            font-weight: 600;
        }

        /* Photo strip — real listing photos, no identifying detail with them. */
        .showcase {
            overflow: hidden;
            background: var(--white);
            border-top: 1px solid #eef1f2;
            border-bottom: 1px solid #eef1f2;
            padding: 1.25rem 0;
            -webkit-mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent);
            mask-image: linear-gradient(to right, transparent, #000 8%, #000 92%, transparent);
        }

        /* The track holds the tiles twice. Each tile carries its own
           margin-right rather than the flex container using `gap`, so the
           duplicated half is exactly 50% of the total width and the -50%
           keyframe loops without a seam. */
        .showcase-track {
            display: flex;
            width: max-content;
            animation: showcase-scroll 45s linear infinite;
        }

        .showcase-track img {
            width: 240px;
            height: 160px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            margin-right: 1rem;
            background: var(--fog);
            flex-shrink: 0;
        }

        @keyframes showcase-scroll {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        .showcase:hover .showcase-track {
            animation-play-state: paused;
        }

        @media (prefers-reduced-motion: reduce) {
            .showcase-track {
                animation: none;
            }
            /* Without the animation the track would just overflow off-screen;
               let it scroll by hand instead. */
            .showcase {
                overflow-x: auto;
            }
        }

        .demo-link-text {
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .demo-link-text a {
            color: var(--green);
            text-decoration: underline;
            text-underline-offset: 2px;
            font-weight: 500;
        }

        .demo-link-text a:hover {
            color: var(--sky);
        }

        /* Features */
        .features {
            padding: 3.5rem 1.5rem 4rem;
            max-width: 960px;
            margin: 0 auto;
            width: 100%;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .feature-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 49, 60, 0.08);
            transition: transform var(--transition), box-shadow var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--fog);
            color: var(--green);
            border-radius: var(--radius-sm);
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .feature-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.55;
        }

        /* Roadmap */
        .roadmap {
            padding: 0 1.5rem 4rem;
            max-width: 960px;
            margin: 0 auto;
            width: 100%;
        }

        .roadmap h2 {
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text);
        }

        .roadmap-columns {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .roadmap-column {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.75rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 49, 60, 0.08);
        }

        .roadmap-column h3 {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .roadmap-column h3 .badge {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .roadmap-done h3 { color: #1a6b4a; }
        .roadmap-done .badge { background: #e8f5e9; color: #1a6b4a; }
        .roadmap-soon h3 { color: #b8860b; }
        .roadmap-soon .badge { background: #fff8e1; color: #7a6100; }
        .roadmap-future h3 { color: #1565c0; }
        .roadmap-future .badge { background: #e3f2fd; color: #1565c0; }

        .roadmap-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }

        .roadmap-list li {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .roadmap-list li i {
            margin-top: 0.2rem;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .roadmap-done .roadmap-list li i { color: #1a6b4a; }
        .roadmap-soon .roadmap-list li i { color: #b8860b; }
        .roadmap-future .roadmap-list li i { color: #1565c0; }

        @media (max-width: 700px) {
            .roadmap-columns {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        /* Footer */
        .landing-footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            border-top: 1px solid #eef1f2;
        }

        .landing-footer a {
            color: var(--green);
            text-decoration: none;
        }

        .landing-footer a:hover {
            color: var(--sky);
        }

        /* Responsive */
        @media (max-width: 700px) {
            .hero {
                padding: 3.5rem 1.25rem 3rem;
            }

            .hero h1 {
                font-size: 2.2rem;
            }

            .hero p {
                font-size: 1.05rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .feature-card {
                padding: 1.5rem 1.25rem;
            }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="hero-content">
            <div class="hero-icon">
                <i class="fa-solid fa-house"></i>
            </div>
            <h1><span>UVM</span> Sublets</h1>
            <p>Find and post sublet listings exclusively for UVM students. Browse available places near campus and connect with fellow Catamounts.</p>
            <a href="app/" class="hero-cta">
                Sign In with UVM
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <?php if ($liveCount): ?>
                <p class="hero-stats">
                    <span class="dot"></span>
                    <span><strong><?= $liveCount ?></strong> listing<?= $liveCount === 1 ? '' : 's' ?> up right now</span>
                    <?php if ($liveSemesters): ?>
                        <span>&middot; <?= implode(' &amp; ', array_map('htmlspecialchars', $liveSemesters)) ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($showcaseImages): ?>
        <?php /* Decorative: the photos carry no address or poster, and the
                 strip repeats, so there is nothing here for a screen reader. */ ?>
        <section class="showcase" aria-hidden="true">
            <div class="showcase-track">
                <?php for ($pass = 0; $pass < 2; $pass++): ?>
                    <?php foreach ($showcaseImages as $img): ?>
                        <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy" width="240" height="160">
                    <?php endforeach; ?>
                <?php endfor; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="features">
        <p class="demo-link-text">
            Not a UVM student? <a href="demo/">See how the site works</a>
        </p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3>Browse Listings</h3>
                <p>Filter by price, distance from campus, and semester to find the perfect sublet.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-map-location-dot"></i>
                </div>
                <h3>Map View</h3>
                <p>See all available sublets on an interactive map to find the best location for you.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <h3>Post Your Sublet</h3>
                <p>Create a listing with photos, pricing, and details to find your next subletter.</p>
            </div>
        </div>
    </section>

    <section class="roadmap" id="roadmap">
        <h2>Roadmap</h2>
        <div class="roadmap-columns">
            <div class="roadmap-column roadmap-done">
                <h3><span class="badge">Completed</span></h3>
                <ul class="roadmap-list">
                    <li><i class="fa-solid fa-check"></i> Full site refresh &amp; complete backend redesign</li>
                    <li><i class="fa-solid fa-check"></i> New address autocomplete</li>
                    <li><i class="fa-solid fa-check"></i> New presentation of contact info</li>
                    <li><i class="fa-solid fa-check"></i> Better image uploader &amp; rendering</li>
                    <li><i class="fa-solid fa-check"></i> Utilities &amp; amenity flags</li>
                    <li><i class="fa-solid fa-check"></i> New landing page</li>
                    <li><i class="fa-solid fa-check"></i> New demo site</li>
                    <li><i class="fa-solid fa-check"></i> Bedroom, bathroom &amp; roommate info</li>
                    <li><i class="fa-solid fa-check"></i> Filter listings by amenities</li>
                    <li><i class="fa-solid fa-check"></i> Sort by price, date &amp; distance</li>
                    <li><i class="fa-solid fa-check"></i> Price negotiable flag</li>
                </ul>
            </div>
            <div class="roadmap-column roadmap-soon">
                <h3><span class="badge">Coming Soon</span></h3>
                <ul class="roadmap-list">
                    <li><i class="fa-solid fa-wrench"></i> Sublet over multiple semesters</li>
                    <li><i class="fa-solid fa-wrench"></i> Filter by bedrooms &amp; bathrooms</li>
                    <li><i class="fa-solid fa-wrench"></i> Roommate preference filters</li>
                </ul>
            </div>
            <div class="roadmap-column roadmap-future">
                <h3><span class="badge">Long Term</span></h3>
                <ul class="roadmap-list">
                    <li><i class="fa-solid fa-lightbulb"></i> Custom date range listing</li>
                    <li><i class="fa-solid fa-lightbulb"></i> Chat with subletter on-site</li>
                    <li><i class="fa-solid fa-lightbulb"></i> Saved searches &amp; favorites</li>
                    <li><i class="fa-solid fa-lightbulb"></i> Roommate matching</li>
                </ul>
            </div>
        </div>
    </section>

    <footer class="landing-footer">
        Built for UVM students by <a href="https://aaronperkel.com" target="_blank" rel="noopener">Aaron Perkel</a>
    </footer>
</body>
</html>
