<?php require_once __DIR__ . '/share.php'; ?>
    </main>
    <footer class="site-footer">
        <div class="footer-content">
            <?php /* The disclaimer is a product commitment (PRODUCT.md): UVM's
                     colors and sign-in must not read as a university service. */ ?>
            <p>UVM Sublets &mdash; Built by <a href="https://aaronperkel.com" target="_blank">Aaron Perkel</a><br>Questions or issues? DM <a href="<?= htmlspecialchars(SOCIAL_INSTAGRAM_URL) ?>" target="_blank"><?= htmlspecialchars(SOCIAL_INSTAGRAM_HANDLE) ?></a><br><span class="footer-disclaimer">An independent student project, not affiliated with the University of Vermont.</span><br><span class="footer-disclaimer">Listing views and contact taps are counted. Posters and the admin see totals, never who.</span></p>
            <div class="footer-links">
                <a href="<?= htmlspecialchars(SOCIAL_INSTAGRAM_URL) ?>" target="_blank" aria-label="Instagram">
                    <i class="fa-brands fa-instagram"></i>
                </a>
                <a href="https://github.com/aaronperkel/sublet" target="_blank" aria-label="GitHub">
                    <i class="fa-brands fa-github"></i>
                </a>
            </div>
        </div>
    </footer>
</body>
</html>
