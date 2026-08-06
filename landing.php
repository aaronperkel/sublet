<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UVM Sublets — Find Your Next Sublet</title>
    <meta name="description" content="Find and post sublet listings exclusively for UVM students. Browse available sublets near campus, post your own, and connect with fellow Catamounts.">
    <meta name="author" content="Aaron Perkel">
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
        </div>
    </section>

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
                </ul>
            </div>
            <div class="roadmap-column roadmap-soon">
                <h3><span class="badge">Coming Soon</span></h3>
                <ul class="roadmap-list">
                    <li><i class="fa-solid fa-wrench"></i> Bedroom, bathroom &amp; roommate info</li>
                    <li><i class="fa-solid fa-wrench"></i> Filter listings by amenities</li>
                    <li><i class="fa-solid fa-wrench"></i> Improved home page sorting</li>
                    <li><i class="fa-solid fa-wrench"></i> Sublet over multiple semesters</li>
                    <li><i class="fa-solid fa-wrench"></i> Price negotiable flag</li>
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
