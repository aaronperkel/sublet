/* ==========================================================================
   UVM Sublets — Client-Side Application
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    const page = document.body.dataset.page;
    const currentUser = document.body.dataset.user || 'Guest';
    const isAdmin = document.body.dataset.admin === '1';

    // Mirrors CAMPUS_LAT / CAMPUS_LON in includes/listing_query.php. Kept in
    // step by hand — there is no server→client channel for it, and it is the
    // default view of both maps.
    const CAMPUS = { lat: 44.477435, lon: -73.195323 };

    // Muted basemap shared by the map page and the post preview, so a listing
    // looks the same wherever it is shown.
    //
    // This was CARTO Voyager until CARTO began requiring an API key on
    // basemaps.cartocdn.com. The keyless request still returns HTTP 200 and a
    // real-looking tile, but with "API KEY REQUIRED" stamped diagonally across
    // it, so the map degrades into nonsense instead of failing loudly. Esri's
    // World Street Map is the nearest keyless equivalent: the same warm,
    // labelled street style, which keeps the green-and-gold pins the most
    // saturated thing on screen.
    //
    // Two traps here, both unlike every {s}/{z}/{x}/{y} provider: the path is
    // {z}/{y}/{x} (row before column), and there is no {s} subdomain to rotate,
    // so `subdomains` has to go or Leaflet builds URLs that 404.
    const TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}';
    const TILE_OPTS = {
        attribution: '&copy; Esri &mdash; Esri, HERE, Garmin, USGS, NGA',
        // Esri has tiles to z19 around campus and answers z20 with a grey "Map
        // data not yet available" placeholder, so cap the requests at 19 and
        // let Leaflet upscale the last step in — maxNativeZoom is what makes a
        // pinch past 19 blurry rather than blank. detectRetina went with CARTO:
        // it worked through the {r} -> @2x tiles this service has no equivalent
        // for, and left on it would only re-request the denser z+1 style.
        maxZoom: 20,
        maxNativeZoom: 19
    };

    // Share-sheet state and tile list.
    //
    // These live up here, above the dispatch, because initShare() reads
    // SHARE_TILES as soon as it is called. Function declarations hoist but
    // `var` assignments do not, so declaring them further down — next to
    // initShare(), where they read better — left SHARE_TILES undefined at call
    // time. It threw after shareEls was assigned but before a single listener
    // was attached, so the sheet opened, rendered no tiles, and could not be
    // closed or copied from; the throw also aborted the rest of this handler,
    // which took the ?id= deep link with it.
    // Set by initShare() once the partial is on the page; null on any page that
    // does not include includes/share_sheet.php.
    var shareEls = null;

    // Read by initFilters() during the dispatch below, so declared up here for
    // the same reason as shareEls: a `var` further down is still undefined
    // when init runs, and matchMedia(undefined) silently never matches.
    var PHONE_QUERY = '(max-width: 768px)';
    // The in-flight Browse filter request, aborted when a newer one starts.
    var filterController = null;
    var shareTarget = null;

    // Listing-view state, up here for the same reason. openSharedListing()
    // opens a listing during the dispatch, and these used to be declared
    // further down, where their `var` lines ran afterwards and reset them under
    // the open view: currentPostId went back to null, so the admin's Delete
    // button did nothing on a listing opened from a share link.
    var modalImages = [];
    var modalIndex = 0;
    var currentPostId = null;
    // Where the open listing was opened from, for the activity log:
    // browse, map, share-link or deeplink.
    var currentSource = null;
    var lastFocused = null;
    // Display images already requested, by URL, so each is fetched once however
    // often it is preloaded (a finger on a card, a photo's neighbours).
    var photoCache = {};
    // Bumped on every open, so a slow images.php answer for one listing cannot
    // fill the gallery of the next one.
    var galleryRequest = 0;
    // Set by initImagesTab() during the admin init, called by the Posts tab's
    // photo buttons; up here so a later `var` line cannot reset it.
    var showListingImages = null;

    // The tiles, in order. `when` decides whether a tile is worth showing on
    // this device: "Share to…" in a browser with no navigator.share is a dead
    // button, and dead buttons in a share sheet are how people decide the
    // whole feature is broken.
    //
    // Instagram and Snapchat have no web endpoint that posts to a story — the
    // official route is a native SDK. What does work is handing the OS share
    // sheet an image file, which surfaces "Instagram Stories" and Snapchat as
    // real targets, so both tiles go through the generated story graphic.
    var SHARE_TILES = [
        {
            key: 'native',
            label: 'Share to…',
            icon: 'fa-solid fa-arrow-up-from-bracket',
            when: function () { return typeof navigator.share === 'function'; }
        },
        { key: 'instagram', label: 'Insta Story', icon: 'fa-brands fa-instagram' },
        { key: 'snapchat',  label: 'Snapchat',  icon: 'fa-brands fa-snapchat' },
        { key: 'text',      label: 'Text',      icon: 'fa-solid fa-comment' },
        { key: 'email',     label: 'Email',     icon: 'fa-solid fa-envelope' },
        { key: 'x',         label: 'X',         icon: 'fa-brands fa-x-twitter' }
    ];


    // ---- Navigation ----
    initNav();

    // ---- Page-specific init ----
    if (page === 'index') initIndex();
    if (page === 'map') initMap();
    if (page === 'post') initPost();
    if (page === 'admin') initAdmin();

    // ---- Shared: Filters ----
    if (page === 'index' || page === 'map') initFilters();

    // ---- Shared: Modal ----
    if (page === 'index' || page === 'map') initModal();

    // ---- Shared: Share sheet ----
    if (page === 'index' || page === 'map' || page === 'post') initShare();

    // Deep link from a share link, after initModal() so the modal's close and
    // keyboard handlers are already bound when it opens.
    if (page === 'index') openSharedListing();

    /* ======================================================================
       Navigation
       ====================================================================== */
    function initNav() {
        const toggle = document.getElementById('navToggle');
        const menu = document.getElementById('navMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // Close menu on link click (mobile)
        menu.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    /* ======================================================================
       Filters (noUiSlider)
       ====================================================================== */
    function initFilters() {
        var config = window.SUBLET_CONFIG;

        // Filtering must work even if the sliders fail to build: they come
        // from a CDN, and without this guard a blocked CDN would leave the
        // whole filter bar inert.
        try {
            if (config && typeof noUiSlider !== 'undefined') {
                buildSliders(config);
            }
        } catch (e) {
            /* checkboxes and the semester select still work */
        }

        initAutoApply();
        initFilterSheet();
    }

    function money(n) {
        return '$' + Math.round(n).toLocaleString('en-US');
    }

    function buildSliders(config) {
        var minPrice = config.minPrice || 0;

        // Price. The range runs from the cheapest listing to the dearest, and
        // the hidden fields stay empty while both handles sit at those ends,
        // so an untouched slider is not a filter: it no longer counts as
        // active, and a bookmarked URL no longer freezes today's maximum and
        // hides a dearer listing posted later.
        var priceEl = document.getElementById('priceSlider');
        if (priceEl) {
            noUiSlider.create(priceEl, {
                start: [Math.max(config.initialMinPrice, minPrice), config.initialMaxPrice],
                connect: true,
                step: 50,
                range: { min: minPrice, max: config.maxPrice },
                handleAttributes: [{ 'aria-label': 'Minimum price per month' }, { 'aria-label': 'Maximum price per month' }],
                ariaFormat: { to: money, from: Number }
            });

            priceEl.noUiSlider.on('update', function (values) {
                var lo = Number(values[0]);
                var hi = Number(values[1]);
                var open = lo <= minPrice && hi >= config.maxPrice;
                document.getElementById('priceValue').textContent = open ? 'Any price' : money(lo) + ' – ' + money(hi);
                document.getElementById('minPrice').value = open ? '' : Math.round(lo);
                document.getElementById('maxPrice').value = open ? '' : Math.round(hi);
            });
        }

        // Distance, in quarter-mile steps: listings sit within about a mile
        // and a half, and half-mile steps left the slider three stops long.
        var distEl = document.getElementById('distanceSlider');
        if (distEl) {
            noUiSlider.create(distEl, {
                start: [config.initialDistance],
                connect: [true, false],
                step: 0.25,
                range: { min: 0.25, max: config.maxDistance },
                handleAttributes: [{ 'aria-label': 'Maximum distance from campus, in miles' }],
                ariaFormat: { to: function (v) { return Number(v) + ' miles'; }, from: Number }
            });

            distEl.noUiSlider.on('update', function (values) {
                var d = Number(values[0]);
                var open = d >= config.maxDistance;
                document.getElementById('distanceValue').textContent = open ? 'Any distance' : 'Within ' + d + ' mi';
                document.getElementById('maxDistance').value = open ? '' : d;
            });
        }
    }

    // The form's filter fields as a query string, without empty values.
    // `sort` is left out when only the filters are wanted (links to Map).
    function filterQuery(form, withSort) {
        var params = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            if (value === '' || (!withSort && key === 'sort')) return;
            params.append(key, value);
        });
        return params.toString();
    }

    // Browse/Map links (nav, the phone's List/Map switch) carry the filters,
    // so switching view does not reset them.
    function updateCarryLinks(query) {
        document.querySelectorAll('[data-carry-filters]').forEach(function (a) {
            a.setAttribute('href', a.dataset.carryFilters + (query ? '?' + query : ''));
        });
    }

    function phoneSheetOpen() {
        var form = document.getElementById('filterForm');
        return !!form && form.classList.contains('open');
    }

    /* Apply filters as they change. On Browse the results are fetched and
       swapped in place: the server stays the single source of truth for what
       matches, the URL stays shareable (replaceState), and nothing reloads, so
       a second tap during an update is no longer lost and keyboard focus stays
       on the slider. Map's results live in a JS global and its pins, so it
       still submits the form, but not while the phone sheet is open: there the
       sheet's button applies. */
    function initAutoApply() {
        var form = document.getElementById('filterForm');
        if (!form) return;

        var live = form.dataset.live === '1' && typeof fetch === 'function'
            && typeof DOMParser === 'function' && typeof AbortController === 'function';
        var phone = window.matchMedia(PHONE_QUERY);
        var timer = null;
        var lastQuery = filterQuery(form, true);

        function schedule() {
            clearTimeout(timer);
            // Long enough to collect a burst of chip taps into one request,
            // short enough that a single tap still feels direct.
            timer = setTimeout(apply, 300);
        }

        function apply() {
            clearTimeout(timer);
            var query = filterQuery(form, true);
            if (query === lastQuery) return;

            if (!live) {
                if (phone.matches && phoneSheetOpen()) return;
                lastQuery = query;
                document.body.classList.add('filters-applying');
                form.submit();
                return;
            }
            lastQuery = query;
            liveUpdate(form, query);
        }
        form._applyNow = apply;

        form.querySelectorAll('input[type="checkbox"], select').forEach(function (el) {
            el.addEventListener('change', schedule);
        });

        // noUiSlider fires 'change' once on release (and per keyboard step),
        // unlike the continuous 'update' the readouts use.
        ['priceSlider', 'distanceSlider'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.noUiSlider) el.noUiSlider.on('change', schedule);
        });

        form.addEventListener('submit', function (e) {
            if (!live) return;
            e.preventDefault();
            apply();
        });

        updateCarryLinks(filterQuery(form, false));
        updateSheetButton();
    }

    function liveUpdate(form, query) {
        var url = form.getAttribute('action') + (query ? '?' + query : '');

        // Only the latest change matters: an answer for an older one arriving
        // late must not overwrite a newer grid.
        if (filterController) filterController.abort();
        var mine = filterController = new AbortController();
        document.body.classList.add('filters-applying');

        fetch(url, { signal: mine.signal, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                if (mine !== filterController) return;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var nextGrid = doc.getElementById('listingsGrid');
                var nextHeading = doc.querySelector('.sort-bar-heading');
                // Anything else (a sign-in page after the session lapsed, an
                // error page) means the in-place update cannot be trusted.
                if (!nextGrid || !nextHeading) throw new Error('unexpected response');

                var grid = document.getElementById('listingsGrid');
                grid.innerHTML = nextGrid.innerHTML;
                grid.dataset.count = nextGrid.dataset.count;
                document.querySelector('.sort-bar-heading').innerHTML = nextHeading.innerHTML;

                var nextClear = doc.getElementById('filterClear');
                var clear = document.getElementById('filterClear');
                if (clear && nextClear) clear.hidden = nextClear.hidden;
                var nextCount = doc.getElementById('filterCount');
                var count = document.getElementById('filterCount');
                if (count && nextCount) {
                    count.hidden = nextCount.hidden;
                    count.textContent = nextCount.textContent;
                }

                history.replaceState(history.state, '', url);
                updateCarryLinks(filterQuery(form, false));
                updateSheetButton();
                fitCardTags(grid);
                checkCardImages(grid);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                // Fall back to an ordinary page load, which also sends a
                // lapsed session through sign-in.
                window.location.href = url;
            })
            .then(function () {
                if (mine === filterController) {
                    filterController = null;
                    document.body.classList.remove('filters-applying');
                }
            });
    }

    // "Show 12 sublets" on Browse, from the count the server put on the grid.
    function updateSheetButton() {
        var btn = document.getElementById('filterSheetApply');
        var grid = document.getElementById('listingsGrid');
        if (!btn || !grid) return;
        var n = parseInt(grid.dataset.count, 10);
        if (isNaN(n)) return;
        btn.textContent = n === 0 ? 'No matches yet' : 'Show ' + n + ' sublet' + (n === 1 ? '' : 's');
    }

    /* The phone's bottom sheet. Below 768px the filter form is hidden behind
       the sticky Filters button; this opens it, closes it, keeps keyboard
       focus inside while it is open, and gives focus back afterwards. */
    function initFilterSheet() {
        var form = document.getElementById('filterForm');
        var openBtn = document.getElementById('filterSheetOpen');
        var closeBtn = document.getElementById('filterSheetClose');
        var backdrop = document.getElementById('filterSheetBackdrop');
        var applyBtn = document.getElementById('filterSheetApply');
        if (!form || !openBtn || !backdrop) return;

        var phone = window.matchMedia(PHONE_QUERY);
        var live = form.dataset.live === '1';

        function open() {
            form.classList.add('open');
            backdrop.hidden = false;
            openBtn.setAttribute('aria-expanded', 'true');
            form.setAttribute('role', 'dialog');
            form.setAttribute('aria-modal', 'true');
            document.body.classList.add('filter-sheet-open');
            if (closeBtn) closeBtn.focus();
        }

        function close(restoreFocus) {
            if (!form.classList.contains('open')) return;
            form.classList.remove('open');
            backdrop.hidden = true;
            openBtn.setAttribute('aria-expanded', 'false');
            form.removeAttribute('role');
            form.removeAttribute('aria-modal');
            document.body.classList.remove('filter-sheet-open');
            if (restoreFocus !== false) openBtn.focus();
        }

        openBtn.addEventListener('click', open);
        if (closeBtn) closeBtn.addEventListener('click', function () { close(); });
        backdrop.addEventListener('click', function () { close(); });

        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                // Browse has been updating underneath all along; flush any
                // change still waiting on its debounce, then reveal it. Map
                // lets the button submit the form.
                if (live) {
                    e.preventDefault();
                    if (form._applyNow) form._applyNow();
                    close();
                }
            });
        }

        form.addEventListener('keydown', function (e) {
            if (!form.classList.contains('open')) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                return;
            }
            if (e.key !== 'Tab') return;
            var focusable = Array.prototype.filter.call(
                form.querySelectorAll('button, [href], input:not([type="hidden"]), select, [tabindex]:not([tabindex="-1"])'),
                function (el) { return !el.disabled && !el.hidden && el.offsetParent !== null; }
            );
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        // Rotating or resizing past the phone layout leaves no sheet to close.
        var onChange = function () { if (!phone.matches) close(false); };
        if (phone.addEventListener) phone.addEventListener('change', onChange);
        else if (phone.addListener) phone.addListener(onChange);
    }

    /* ======================================================================
       Card tags: one row, with the overflow counted in "+N more"
       ====================================================================== */
    // The server sends at most three tags, rarest first. Whether three fit
    // depends on the card's width, so this hides from the end until the row
    // fits and adds what it hid to "+N more". The first tag is never hidden; if
    // it is too long on its own (a roommate preference), CSS shortens it with
    // an ellipsis instead.
    function fitCardTags(root) {
        (root || document).querySelectorAll('.card-utilities').forEach(fitTagRow);
    }

    function fitTagRow(row) {
        var tags = Array.prototype.slice.call(row.querySelectorAll('.utility-tag:not(.tag-more)'));
        var more = row.querySelector('.tag-more');
        var serverMore = more ? (parseInt(more.dataset.more, 10) || 0) : 0;

        tags.forEach(function (t) { t.hidden = false; });
        if (more) {
            more.hidden = serverMore === 0;
            more.textContent = '+' + serverMore + ' more';
        }

        // Measure at natural widths: while the first tag may shrink, the row
        // would never report an overflow.
        row.classList.add('measuring');
        var hidden = 0;
        while (row.scrollWidth > row.clientWidth + 1 && tags.length - hidden > 1) {
            hidden++;
            tags[tags.length - hidden].hidden = true;
            if (!more) {
                more = document.createElement('span');
                more.className = 'utility-tag tag-more';
                more.dataset.more = '0';
                row.appendChild(more);
            }
            more.hidden = false;
            more.textContent = '+' + (serverMore + hidden) + ' more';
        }
        row.classList.remove('measuring');
    }

    function initCardTags() {
        if (!document.querySelector('.card-utilities')) return;
        fitCardTags();
        // Bricolage arriving changes every width, and so does a resize.
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () { fitCardTags(); });
        }
        var t = null;
        window.addEventListener('resize', function () {
            clearTimeout(t);
            t = setTimeout(function () { fitCardTags(); }, 150);
        });
    }

    /* ======================================================================
       Activity log
       ====================================================================== */
    // One beacon per event to api/events.php, which decides what counts: the
    // admin and a poster's own views and contacts are dropped there, repeats
    // of a view are folded into one per day, and nothing about who sent it
    // is ever shown. sendBeacon survives the page being left, which is what a
    // tap on a mailto:, tel: or share link does next.
    function track(type, listingId, source, target) {
        if (!listingId) return;
        var body = new URLSearchParams();
        body.set('type', type);
        body.set('listing_id', String(listingId));
        if (source) body.set('source', source);
        if (target) body.set('target', target);
        try {
            if (navigator.sendBeacon && navigator.sendBeacon('api/events.php', body)) return;
        } catch (e) { /* fall through to fetch */ }
        if (window.fetch) {
            fetch('api/events.php', { method: 'POST', body: body, keepalive: true, credentials: 'same-origin' })
                .catch(function () {});
        }
    }

    /* ======================================================================
       Modal
       ====================================================================== */
    function initModal() {
        var overlay = document.getElementById('modal');
        if (!overlay) return;

        document.getElementById('modalClose').addEventListener('click', closeModal);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('open')) return;
            // The share sheet sits on top and handles its own keys.
            if (shareEls && shareEls.overlay && shareEls.overlay.classList.contains('open')) return;
            if (e.key === 'Escape') closeModal();
            if (e.key === 'ArrowLeft') navigateGallery(-1);
            if (e.key === 'ArrowRight') navigateGallery(1);
            if (e.key === 'Tab') trapFocus(e, overlay);
        });

        // Back (or the close button, which steps back) closes the view.
        window.addEventListener('popstate', function () {
            if (overlay.classList.contains('open')) hideModal();
        });

        // Swipe between photos. touch-action: pan-y on the gallery (CSS)
        // leaves vertical scrolling to the browser, which cancels the pointer
        // when it takes over; a mostly-horizontal flick is ours.
        var gallery = document.getElementById('modalGallery');
        if (gallery) {
            var startX = null;
            var startY = 0;
            gallery.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'mouse') return;
                startX = e.clientX;
                startY = e.clientY;
            });
            gallery.addEventListener('pointerup', function (e) {
                if (startX === null) return;
                var dx = e.clientX - startX;
                var dy = e.clientY - startY;
                startX = null;
                if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                    navigateGallery(dx < 0 ? 1 : -1);
                }
            });
            gallery.addEventListener('pointercancel', function () { startX = null; });
        }

        // The phone's top and bottom bars repeat buttons that live in the
        // header; they forward, so there is one handler per action.
        overlay.querySelectorAll('[data-forward]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = document.getElementById(btn.dataset.forward);
                if (target) target.click();
            });
        });
        var shareTop = document.getElementById('modalShareTop');
        if (shareTop) {
            shareTop.addEventListener('click', function () {
                var shareBtn = document.getElementById('modalShareBtn');
                if (shareBtn) shareBtn.click();
            });
        }

        // The photo fades in once loaded (renderGallery() holds it
        // transparent until then). Errors go through imageFailed(), below.
        var modalImg = document.getElementById('modalImage');
        if (modalImg) {
            modalImg.addEventListener('load', function () { modalImg.classList.remove('is-loading'); });
        }
        // The thumbnail under it is only a stand-in: if it fails, drop it
        // rather than putting "Image not available" over the real photo.
        var modalUnder = document.getElementById('modalImageUnder');
        if (modalUnder) {
            modalUnder.addEventListener('error', function () { modalUnder.hidden = true; });
        }

        var prevBtn = document.getElementById('galleryPrev');
        var nextBtn = document.getElementById('galleryNext');
        if (prevBtn) prevBtn.addEventListener('click', function () { navigateGallery(-1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { navigateGallery(1); });

        // Delete button
        var deleteBtn = document.getElementById('modalDelete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                if (!currentPostId) return;
                if (!confirm('Are you sure you want to delete this post?')) return;
                fetch('api/posts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete&id=' + currentPostId
                }).then(function (r) { return r.json(); }).then(function () {
                    location.reload();
                });
            });
        }

        // Contact popup
        initContactPopup();
    }

    // Keep Tab inside an open dialog, cycling at the ends.
    function trapFocus(e, container) {
        var focusable = Array.prototype.filter.call(
            container.querySelectorAll('button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])'),
            function (el) { return !el.disabled && !el.hidden && el.offsetParent !== null; }
        );
        if (!focusable.length) return;
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (e.shiftKey && (document.activeElement === first || !container.contains(document.activeElement))) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && (document.activeElement === last || !container.contains(document.activeElement))) {
            e.preventDefault();
            first.focus();
        }
    }

    function initContactPopup() {
        var backBtn = document.getElementById('contactBackBtn');
        if (backBtn) {
            backBtn.addEventListener('click', function () {
                var details = document.getElementById('modalDetails');
                if (details) details.classList.remove('contact-open');
                var container = details ? details.closest('.modal-container') : null;
                if (container) container.classList.remove('contact-active');
            });
        }
    }

    function showContactPopup(type, data) {
        var details = document.getElementById('modalDetails');
        var title = document.getElementById('contactPanelTitle');
        var body = document.getElementById('contactPanelBody');
        if (!details || !body) return;

        // Tapping Email or Call opens this panel. What happens in it (the
        // email app, the dialler, a copy) is logged by the data-track
        // attributes below.
        track(type === 'email' ? 'email_click' : 'call_click', data.id, currentSource);

        if (type === 'email') {
            var email = data.contactEmail || (data.username + '@uvm.edu');
            var subject = 'Your sublet at ' + data.address + ' (UVM Sublets)';

            // Greet the poster by the name they chose; a NetID is not a name,
            // so without one it is "Hi there". Sign only with a real name too:
            // the email app already says who it is from.
            var posterNamed = data.posterName && data.posterName !== data.username;
            var myName = document.body.dataset.userName || '';
            var meNamed = myName && myName !== currentUser;
            var semesterText = data.semesterName || data.semester;
            var draftBody = (posterNamed ? 'Hi ' + data.posterName + ',' : 'Hi there,') + '\n\n' +
                'I found your sublet at ' + data.address + ' on UVM Sublets ($' + Number(data.price).toLocaleString() + '/mo' +
                (semesterText ? ', ' + semesterText : '') + ') and I\u2019m interested. Is it still available? ' +
                'I\u2019d love to hear a bit more about it, and to see it if that works for you.\n\n' +
                'Thanks!' + (meNamed ? '\n' + myName : '');

            var mailtoFor = function (text) {
                return 'mailto:' + encodeURIComponent(email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(text);
            };

            title.textContent = 'Send an email';
            body.innerHTML =
                '<div class="contact-field">' +
                    '<label>To</label>' +
                    '<div class="contact-value-row">' +
                        '<span class="contact-value">' + escapeHtml(email) + '</span>' +
                        '<button type="button" class="btn btn-secondary btn-sm contact-copy" data-track="copy_email" data-copy="' + escapeHtml(email) + '"><i class="fa-solid fa-copy" aria-hidden="true"></i> Copy</button>' +
                    '</div>' +
                '</div>' +
                '<div class="contact-field">' +
                    '<label>Subject</label>' +
                    '<span class="contact-value">' + escapeHtml(subject) + '</span>' +
                '</div>' +
                '<div class="contact-field">' +
                    '<label for="contactDraft">Message <span class="label-aside">(yours to edit)</span></label>' +
                    '<textarea class="contact-draft" id="contactDraft" rows="7">' + escapeHtml(draftBody) + '</textarea>' +
                '</div>' +
                '<div class="contact-actions">' +
                    '<a href="' + escapeHtml(mailtoFor(draftBody)) + '" class="btn btn-primary" id="contactMailto" data-track="mail_app"><i class="fa-solid fa-envelope" aria-hidden="true"></i> Open in your email app</a>' +
                    '<button type="button" class="btn btn-secondary contact-copy" data-track="copy_message" data-copy-from="contactDraft"><i class="fa-solid fa-copy" aria-hidden="true"></i> Copy message</button>' +
                '</div>' +
                '<p class="contact-note">Everyone here signs in with a UVM NetID. If no email app opens (Instagram\u2019s browser often won\u2019t), copy the address and message instead.</p>';

            // The link carries whatever the message says when it is tapped.
            var mailtoLink = body.querySelector('#contactMailto');
            var draftField = body.querySelector('#contactDraft');
            if (mailtoLink && draftField) {
                mailtoLink.addEventListener('click', function () {
                    mailtoLink.href = mailtoFor(draftField.value);
                });
            }
        } else if (type === 'phone') {
            var phone = data.contactPhone;
            title.textContent = 'Call or Text';
            body.innerHTML =
                '<div class="contact-field">' +
                    '<label>Phone Number</label>' +
                    '<div class="contact-value-row">' +
                        '<span class="contact-value contact-value-lg">' + escapeHtml(phone) + '</span>' +
                        '<button type="button" class="btn btn-secondary btn-sm contact-copy" data-track="copy_phone" data-copy="' + escapeHtml(phone) + '"><i class="fa-solid fa-copy" aria-hidden="true"></i> Copy</button>' +
                    '</div>' +
                '</div>' +
                '<div class="contact-actions">' +
                    '<a href="tel:' + encodeURIComponent(phone) + '" class="btn btn-primary" data-track="dial"><i class="fa-solid fa-phone"></i> Call</a>' +
                    '<a href="sms:' + encodeURIComponent(phone) + '" class="btn btn-secondary" data-track="text"><i class="fa-solid fa-message"></i> Text</a>' +
                '</div>';
        }

        // Attach copy handlers. Shared with the share sheet's copy button, which
        // is also where the execCommand fallback and the failure state came
        // from — this used to be a bare .then() with no rejection path, so a
        // clipboard write that failed left the button saying nothing at all.
        body.querySelectorAll('[data-track]').forEach(function (el) {
            el.addEventListener('click', function () {
                track(el.dataset.track, data.id, currentSource);
            });
        });

        body.querySelectorAll('.contact-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // data-copy-from: copy the field as edited, not as drafted.
                var from = btn.dataset.copyFrom && document.getElementById(btn.dataset.copyFrom);
                copyToClipboard(from ? from.value : btn.dataset.copy, btn);
            });
        });

        details.classList.add('contact-open');
        var container = details.closest('.modal-container');
        if (container) container.classList.add('contact-active');
    }

    function openModal(data, fromHistory) {
        currentPostId = data.id;
        currentSource = data.source || null;
        if (!fromHistory) track('listing_open', data.id, currentSource);
        var overlay = document.getElementById('modal');

        // Name the dialog after the place, not its price.
        overlay.setAttribute('aria-label', 'Listing at ' + (data.address || 'this address'));

        var priceText = '$' + Number(data.price).toLocaleString();
        var priceEl = document.getElementById('modalPrice');
        priceEl.textContent = priceText;
        var unit = document.createElement('span');
        unit.className = 'price-unit';
        unit.textContent = '/mo';
        priceEl.appendChild(unit);

        var barPrice = document.getElementById('modalBarPrice');
        if (barPrice) {
            barPrice.textContent = priceText;
            barPrice.appendChild(unit.cloneNode(true));
        }

        // What a seeker compares first, in one line under the price. Distance
        // used to be on the card and nowhere in here.
        var facts = [];
        var dist = parseFloat(data.distance);
        if (!isNaN(dist)) facts.push(dist.toFixed(1) + ' mi from campus');
        if (data.semesterName || data.semester) facts.push(data.semesterName || data.semester);
        if (data.sizeSummary) facts.push(data.sizeSummary);
        var factsEl = document.getElementById('modalFacts');
        if (factsEl) factsEl.textContent = facts.join(' \u00b7 ');
        if (isFlagSet(data.negotiable)) {
            var neg = document.createElement('small');
            neg.className = 'modal-price-neg';
            neg.textContent = 'or best offer';
            priceEl.appendChild(neg);
        }

        document.getElementById('modalAddress').textContent = data.address;
        // Both links are built server-side from the full stored address; the
        // Apple one opens the Maps app, so it is only offered where that is.
        var mapLink = document.getElementById('modalMapLink');
        if (mapLink) {
            var useApple = prefersAppleMaps() && data.appleMapsUrl;
            mapLink.href = (useApple ? data.appleMapsUrl : data.mapsUrl) || '';
            mapLink.textContent = useApple ? 'Open in Maps' : 'Open in Google Maps';
            mapLink.hidden = !data.mapsUrl;
        }
        document.getElementById('modalSemester').textContent = data.semesterName || data.semester;
        document.getElementById('modalDescription').textContent = data.description || 'No description provided.';

        // Bedrooms / bathrooms / roommates. Absent on listings that predate
        // those fields, so the block is only built when there is something to
        // put in it.
        var existingPlace = document.getElementById('modalPlace');
        if (existingPlace) existingPlace.remove();
        var placeHtml = buildPlaceHtml(data);
        if (placeHtml) {
            var placeDiv = document.createElement('div');
            placeDiv.id = 'modalPlace';
            placeDiv.innerHTML = placeHtml;
            var semesterField = document.getElementById('modalSemester').closest('.modal-field');
            semesterField.parentNode.insertBefore(placeDiv, semesterField.nextSibling);
        }

        // Utilities section
        var existingUtils = document.getElementById('modalUtilities');
        if (existingUtils) existingUtils.remove();
        var utilsHtml = buildUtilitiesHtml(data);
        if (utilsHtml) {
            var utilsDiv = document.createElement('div');
            utilsDiv.id = 'modalUtilities';
            utilsDiv.innerHTML = utilsHtml;
            var modalDesc = document.getElementById('modalDescription');
            modalDesc.parentNode.insertBefore(utilsDiv, modalDesc.nextSibling);
        }

        // "Posted by Maya · 3 days ago". The date is when it went up, not when
        // it was last edited, so a stale listing reads as one.
        var postedBy = 'Posted by ' + (data.posterName || data.username);
        document.getElementById('modalPoster').textContent = data.postedAgo
            ? postedBy + ' \u00b7 ' + data.postedAgo
            : postedBy;

        var isOwn = currentUser === data.username;

        // Email button
        var emailBtn = document.getElementById('modalEmailBtn');
        if (emailBtn) {
            emailBtn.hidden = isOwn;
            emailBtn.onclick = isOwn ? null : function () { showContactPopup('email', data); };
        }

        // Phone button
        var phoneBtn = document.getElementById('modalPhoneBtn');
        if (phoneBtn) {
            phoneBtn.hidden = isOwn || !data.contactPhone;
            phoneBtn.onclick = phoneBtn.hidden ? null : function () { showContactPopup('phone', data); };
        }

        // The phone's bottom bar mirrors those two.
        var barEmail = document.getElementById('modalBarEmail');
        var barCall = document.getElementById('modalBarCall');
        if (barEmail && emailBtn) barEmail.hidden = emailBtn.hidden;
        if (barCall && phoneBtn) barCall.hidden = phoneBtn.hidden;

        // Edit button
        var editBtn = document.getElementById('modalEdit');
        if (editBtn) {
            editBtn.hidden = !isOwn;
        }

        // Share button. Shown to everyone, not only the poster: sending a
        // listing to a roommate group chat is as much the point as putting
        // your own on a story. Hidden if the page could not build a link.
        var shareBtn = document.getElementById('modalShareBtn');
        var shareTop = document.getElementById('modalShareTop');
        if (shareTop) shareTop.hidden = !data.shareUrl;
        if (shareBtn) {
            if (data.shareUrl) {
                shareBtn.hidden = false;
                shareBtn.onclick = function () {
                    openShareSheet({
                        url: data.shareUrl,
                        price: '$' + Number(data.price).toLocaleString(),
                        semester: data.semesterName || data.semester,
                        listingId: data.id,
                        source: currentSource
                    });
                };
            } else {
                shareBtn.hidden = true;
            }
        }

        // The photo list comes with the listing (data-photos on a card,
        // `photos` on a map pin), so the arrows and "1 / N" are drawn now.
        // They used to wait for a round trip to images.php, which is now only
        // the fallback for a listing that arrives without a list.
        var photos = parsePhotos(data.photos);
        var request = ++galleryRequest;
        modalImages = photos.length ? photos : [{ display: data.image_url || data.imageUrl || '', thumb: null }];
        modalIndex = 0;
        renderGallery();

        if (!photos.length) {
            fetch('api/images.php?sublet_id=' + encodeURIComponent(data.id))
                .then(function (r) { return r.json(); })
                .then(function (images) {
                    if (request !== galleryRequest || !Array.isArray(images)) return;
                    // The display-size copy, not the original upload, which
                    // can run to 20 MB. display_url falls back to the original
                    // server-side when no copy exists.
                    var list = parsePhotos(images.map(function (img) {
                        return { display: img.display_url || img.image_url, thumb: img.thumb_url };
                    }));
                    if (!list.length) return;
                    modalImages = list;
                    modalIndex = 0;
                    renderGallery();
                })
                .catch(function () {});
        }

        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('listing-open');

        // A history entry for the open listing, so a phone's Back button closes
        // it instead of leaving the page. closeModal() steps back over it.
        if (!fromHistory) {
            history.pushState({ listing: String(data.id) }, '', window.location.href);
        }

        // Remember where focus came from so Escape returns the user to the card
        // they opened, rather than dumping them at the top of the document.
        lastFocused = document.activeElement;
        var closeBtn = document.getElementById('modalClose');
        if (closeBtn) closeBtn.focus();
    }

    // iPhone, iPad and Mac. iPadOS asks for desktop sites by default and then
    // reports itself as a Mac (only maxTouchPoints tells it apart), but since a
    // Mac gets the Apple link too, matching "Macintosh" covers the iPad as well.
    function prefersAppleMaps() {
        return /iPhone|iPad|iPod|Macintosh/.test(navigator.userAgent || '');
    }

    // Closing goes through history when opening added an entry, so the close
    // button and the Back button leave the same history behind; the popstate
    // handler in initModal() then hides the view.
    function closeModal() {
        if (history.state && history.state.listing) {
            history.back();
            return;
        }
        hideModal();
    }

    function hideModal() {
        var overlay = document.getElementById('modal');
        if (overlay) {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            document.body.classList.remove('listing-open');
        }
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
            lastFocused = null;
        }
        var details = document.getElementById('modalDetails');
        if (details) {
            details.classList.remove('contact-open');
            var container = details.closest('.modal-container');
            if (container) container.classList.remove('contact-active');
        }
    }

    // Draws the gallery for modalImages[modalIndex]. The arrows, counter and
    // dots depend only on the list, so they are right the moment the view
    // opens; the photo itself fades in when it has loaded.
    function renderGallery() {
        var count = modalImages.length;
        var img = document.getElementById('modalImage');
        var under = document.getElementById('modalImageUnder');
        if (img && count > 0) {
            var photo = modalImages[modalIndex];
            // Reset broken state. imgRetried has to go too: this one element is
            // reused for every image in the gallery, so leaving it set would
            // deny the next image its retry.
            img.hidden = false;
            img.style.display = '';
            delete img.dataset.broken;
            delete img.dataset.imgRetried;
            delete img.dataset.imgError;
            var oldPlaceholder = img.parentNode.querySelector('.img-broken-placeholder');
            if (oldPlaceholder) oldPlaceholder.remove();

            // Under the photo, its thumbnail where it has one. For the first
            // photo that is the card's own image, already loaded, so the view
            // opens on a picture rather than an empty frame.
            if (under) {
                if (photo.thumb) {
                    if (under.getAttribute('src') !== photo.thumb) under.src = photo.thumb;
                    under.hidden = false;
                } else {
                    under.hidden = true;
                    under.removeAttribute('src');
                }
            }

            // Held transparent until it has loaded (the load listener in
            // initModal() lifts that), so the previous photo never shows
            // under the new counter. One the cache already has shows at once.
            if (img.getAttribute('src') !== photo.display) {
                img.classList.add('is-loading');
                img.src = photo.display;
            }
            if (img.complete && img.naturalWidth > 0) img.classList.remove('is-loading');
            img.alt = 'Photo ' + (modalIndex + 1) + ' of ' + count;

            // Both neighbours, so a swipe either way shows its photo at once.
            if (modalIndex + 1 < count) preloadPhoto(modalImages[modalIndex + 1].display);
            if (modalIndex > 0) preloadPhoto(modalImages[modalIndex - 1].display);
        }

        var prevBtn = document.getElementById('galleryPrev');
        var nextBtn = document.getElementById('galleryNext');
        if (prevBtn) prevBtn.hidden = modalIndex <= 0;
        if (nextBtn) nextBtn.hidden = modalIndex >= count - 1;

        var counter = document.getElementById('galleryCount');
        if (counter) {
            counter.hidden = count < 2;
            counter.textContent = (modalIndex + 1) + ' / ' + count;
        }

        var dotsContainer = document.getElementById('galleryDots');
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            if (count > 1) {
                for (var i = 0; i < count; i++) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'gallery-dot' + (i === modalIndex ? ' active' : '');
                    dot.dataset.index = i;
                    dot.setAttribute('aria-label', 'Show photo ' + (i + 1) + ' of ' + count);
                    if (i === modalIndex) dot.setAttribute('aria-current', 'true');
                    dot.addEventListener('click', function () {
                        modalIndex = parseInt(this.dataset.index, 10);
                        renderGallery();
                    });
                    dotsContainer.appendChild(dot);
                }
            }
        }
    }

    function navigateGallery(dir) {
        var newIndex = modalIndex + dir;
        if (newIndex >= 0 && newIndex < modalImages.length) {
            modalIndex = newIndex;
            renderGallery();
        }
    }

    // A listing's photo list: data-photos (a JSON string) or a map pin's
    // `photos`, as [{display, thumb}, ...] with thumb null where there is
    // none. Malformed entries are dropped; an unreadable list comes back
    // empty, which sends openModal() to images.php instead.
    function parsePhotos(raw) {
        var list = raw;
        if (typeof raw === 'string') {
            try { list = JSON.parse(raw); } catch (e) { return []; }
        }
        if (!Array.isArray(list)) return [];
        return list.filter(function (p) {
            return p && typeof p.display === 'string' && p.display !== '';
        }).map(function (p) {
            return { display: p.display, thumb: (typeof p.thumb === 'string' && p.thumb !== '') ? p.thumb : null };
        });
    }

    // Start a display image downloading without showing it. It lands in the
    // cache the gallery's <img> reads from, so by the time it is shown it is
    // there, or on its way. A failed one is forgotten so it can be tried again.
    function preloadPhoto(url) {
        if (!url || photoCache[url]) return;
        var img = new Image();
        img.decoding = 'async';
        img.onerror = function () { delete photoCache[url]; };
        img.src = url;
        photoCache[url] = img;
    }

    // Expose for map popups
    window.openSubletModal = function (data) {
        openModal(data);
    };

    /* ======================================================================
       Share Sheet
       ====================================================================== */

    function initShare() {
        var overlay = document.getElementById('shareSheet');
        if (!overlay) return;

        shareEls = {
            overlay: overlay,
            grid: document.getElementById('shareGrid'),
            input: document.getElementById('shareLinkInput'),
            copyBtn: document.getElementById('shareCopyBtn'),
            closeBtn: document.getElementById('shareSheetClose'),
            note: document.getElementById('shareSheetNote')
        };
        shareEls.defaultNote = shareEls.note ? shareEls.note.textContent.trim() : '';

        shareEls.grid.innerHTML = SHARE_TILES.filter(function (tile) {
            return !tile.when || tile.when();
        }).map(function (tile) {
            return '<button type="button" class="share-tile" data-share="' + escapeHtml(tile.key) + '">' +
                       '<span class="share-tile-icon"><i class="' + escapeHtml(tile.icon) + '"></i></span>' +
                       '<span class="share-tile-label">' + escapeHtml(tile.label) + '</span>' +
                   '</button>';
        }).join('');

        shareEls.grid.addEventListener('click', function (e) {
            var tile = e.target.closest('.share-tile');
            if (!tile || !shareTarget) return;
            track('share_target', shareTarget.listingId, shareTarget.source, tile.dataset.share);
            runShareAction(tile.dataset.share, tile);
        });

        shareEls.copyBtn.addEventListener('click', function () {
            if (!shareTarget) return;
            track('share_target', shareTarget.listingId, shareTarget.source, 'copy');
            copyToClipboard(shareTarget.url, shareEls.copyBtn);
        });

        shareEls.closeBtn.addEventListener('click', closeShareSheet);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeShareSheet();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('open')) {
                e.stopPropagation();
                closeShareSheet();
            }
        }, true);
    }

    function openShareSheet(target) {
        if (!shareEls || !target || !target.url) return;

        shareTarget = target;
        track('share_open', target.listingId, target.source);
        shareEls.input.value = target.url;
        setShareNote('');

        shareEls.overlay.classList.add('open');
        shareEls.overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        shareEls.closeBtn.focus();
    }

    function closeShareSheet() {
        if (!shareEls) return;

        shareEls.overlay.classList.remove('open');
        shareEls.overlay.setAttribute('aria-hidden', 'true');
        shareTarget = null;

        // The sheet opens over the listing modal, which locked scrolling first.
        // Clearing it unconditionally would unlock the page behind a modal that
        // is still open.
        var modal = document.getElementById('modal');
        var modalOpen = modal && modal.classList.contains('open');
        document.body.style.overflow = modalOpen ? 'hidden' : '';
    }

    /** Replace the sheet's footnote, or restore it when passed nothing. */
    function setShareNote(message) {
        if (!shareEls || !shareEls.note) return;
        shareEls.note.textContent = message || shareEls.defaultNote;
    }

    /** "Check out this $850/mo sublet for Fall 2026 on UVM Sublets" */
    function shareMessage(target) {
        var bits = ['Check out this'];
        if (target.price) bits.push(target.price);
        bits.push('sublet');
        if (target.semester) bits.push('for ' + target.semester);
        return bits.join(' ') + ' on UVM Sublets';
    }

    /** The "42-a1b2c3d4e5" out of a /s/ URL, or '' if it is not one. */
    function shareSlug(url) {
        var match = /\/s\/(\d+-[a-f0-9]+)\/?$/.exec(String(url || ''));
        return match ? match[1] : '';
    }

    function runShareAction(key, tile) {
        var url = shareTarget.url;
        var text = shareMessage(shareTarget);

        if (key === 'native') {
            navigator.share({ title: 'UVM Sublets', text: text, url: url }).catch(function () {});
            return;
        }

        if (key === 'instagram' || key === 'snapchat') {
            shareStoryImage(tile, key === 'snapchat' ? 'Snapchat' : 'Instagram');
            return;
        }

        if (key === 'text') {
            // "sms:?&body=" is the form both iOS and Android accept; the bare
            // "sms:?body=" is ignored on iOS.
            window.location.href = 'sms:?&body=' + encodeURIComponent(text + ' ' + url);
            return;
        }

        if (key === 'email') {
            window.location.href = 'mailto:?subject=' + encodeURIComponent(text) +
                '&body=' + encodeURIComponent(text + '\n\n' + url);
            return;
        }

        if (key === 'x') {
            window.open(
                'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) +
                '&url=' + encodeURIComponent(url),
                '_blank',
                'noopener'
            );
        }
    }

    /**
     * Fetch the 1080x1920 story graphic and hand it to the OS share sheet.
     *
     * Sharing a *file* is what makes Instagram Stories and Snapchat appear as
     * targets; sharing a URL alone gets a link at best. Desktop browsers cannot
     * share files, so there the image is downloaded instead — which is the same
     * end state, one manual step later.
     */
    function shareStoryImage(tile, label) {
        var slug = shareSlug(shareTarget.url);
        if (!slug) {
            setShareNote('This listing has no share link yet.');
            return;
        }

        // Root-relative: share-card.php sits at the document root, outside the
        // CAS-protected app/ directory, and a page-relative path from /app/
        // would ask Apache for /app/share-card.php.
        var imageUrl = '/share-card.php?f=story&i=' + encodeURIComponent(slug);

        tile.classList.add('busy');
        setShareNote('Building your story image…');

        fetch(imageUrl).then(function (response) {
            if (!response.ok) throw new Error('card unavailable');
            return response.blob();
        }).then(function (blob) {
            var file = null;
            try {
                file = new File([blob], 'uvm-sublet-story.jpg', { type: 'image/jpeg' });
            } catch (e) {
                file = null;
            }

            if (file && navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                return navigator.share({ files: [file], text: shareTarget.url })
                    .then(function () { setShareNote('Shared.'); })
                    .catch(function (err) {
                        // Dismissing the OS sheet is not a failure worth a
                        // fallback download the user did not ask for.
                        if (err && err.name === 'AbortError') {
                            setShareNote('');
                            return;
                        }
                        downloadStoryImage(blob, label);
                    });
            }

            downloadStoryImage(blob, label);
        }).catch(function () {
            setShareNote('Could not build the story image — copy the link instead.');
        }).finally(function () {
            tile.classList.remove('busy');
        });
    }

    function downloadStoryImage(blob, label) {
        var href = URL.createObjectURL(blob);
        var link = document.createElement('a');

        link.href = href;
        link.download = 'uvm-sublet-story.jpg';
        document.body.appendChild(link);
        link.click();
        link.remove();

        setTimeout(function () { URL.revokeObjectURL(href); }, 1000);
        setShareNote('Story image saved — open ' + label + ' and add it to your story.');
    }

    /* ---------------------------------------------------------------------
       Clipboard
       --------------------------------------------------------------------- */

    /**
     * Copy text, and say so on the button either way.
     *
     * navigator.clipboard is undefined on insecure origins and rejects outright
     * when the document is not focused, so the deprecated execCommand path is
     * still the difference between a copy button that works and one that
     * silently does nothing.
     */
    function copyToClipboard(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flashCopied(btn, true);
            }).catch(function () {
                flashCopied(btn, legacyCopy(text));
            });
            return;
        }

        flashCopied(btn, legacyCopy(text));
    }

    function legacyCopy(text) {
        var field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '-1000px';

        document.body.appendChild(field);
        field.select();

        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }

        field.remove();
        return copied;
    }

    function flashCopied(btn, copied) {
        if (!btn) return;

        var original = btn.innerHTML;
        btn.innerHTML = copied
            ? '<i class="fa-solid fa-check"></i> Copied!'
            : '<i class="fa-solid fa-triangle-exclamation"></i> Copy failed';
        btn.classList.add(copied ? 'copied' : 'copy-failed');

        setTimeout(function () {
            btn.innerHTML = original;
            btn.classList.remove('copied', 'copy-failed');
        }, 1600);
    }

    /**
     * Open the listing a share link points at.
     *
     * index.php drops every other filter when ?id= is present, so the card is
     * guaranteed to be in the grid unless the listing has since been hidden.
     * Clicking it goes through the same handler a real click does rather than
     * assembling the modal's data object a third time here — that object has
     * drifted between the index and map copies before.
     */
    function openSharedListing() {
        var config = window.SUBLET_CONFIG || {};
        var openId = parseInt(config.openId, 10);
        if (!openId) return;

        // s.php's sign-in link carries via=share; any other ?id= was typed or
        // pasted. Logged before the card check: the arrival happened even if
        // the listing has since been hidden.
        var source = config.openSource === 'share-link' ? 'share-link' : 'deeplink';
        track('share_arrival', openId, source);

        // This entry becomes plain Browse (the grid already is: ?id= drops the
        // filters), and the click below pushes the listing on top of it the
        // way a tapped card does, so Back closes the listing onto Browse.
        // s.php replaces itself on the way here, so Back cannot reach the
        // sign-in screen either.
        history.replaceState(null, '', window.location.pathname.replace(/index\.php$/, ''));

        var card = document.querySelector('.listing-card[data-id="' + openId + '"]');
        if (!card) return;

        openModalFromCard(card, source);
        card.scrollIntoView({ block: 'center' });
    }

    /* ======================================================================
       Index Page — Listing Cards
       ====================================================================== */
    // The grid's contents are replaced on every filter change, so everything a
    // card responds to is handled once, on the grid, rather than per card.
    function initIndex() {
        var grid = document.getElementById('listingsGrid');
        if (!grid) return;

        grid.addEventListener('click', function (e) {
            var card = e.target.closest('.listing-card');
            if (card && grid.contains(card)) openModalFromCard(card);
        });

        // A finger (or button) going down on a card is the earliest sign it is
        // about to open, about 100 ms before the click: start its first photo
        // downloading then. touchstart too, for browsers without pointer events.
        function warmCard(e) {
            var card = e.target.closest ? e.target.closest('.listing-card') : null;
            if (!card || !grid.contains(card)) return;
            var photos = parsePhotos(card.dataset.photos);
            if (photos.length) preloadPhoto(photos[0].display);
        }
        grid.addEventListener('pointerdown', warmCard, { passive: true });
        grid.addEventListener('touchstart', warmCard, { passive: true });

        // The card carries role="button", so it has to answer Enter and Space
        // the way a real button would.
        grid.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
            var card = e.target.closest('.listing-card');
            if (!card || e.target !== card) return;
            e.preventDefault();
            openModalFromCard(card);
        });

        // Images: error does not bubble, so listen in the capture phase.
        grid.addEventListener('error', function (e) {
            if (e.target.tagName === 'IMG' && e.target.closest('.card-image')) imageFailed(e.target);
        }, true);
        checkCardImages(grid);

        initCardTags();

        // Instant client-side sorting. The hidden `sort` field on the filter
        // form is kept in step so a filter change comes back in the same
        // order, and the URL carries it so a refresh does too.
        var sortSelect = document.getElementById('sortFilter');
        var sortInput = document.getElementById('sortInput');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                var sortVal = sortSelect.value;
                if (sortInput) sortInput.value = sortVal;

                var params = new URLSearchParams(window.location.search);
                params.delete('id');
                if (sortVal === 'newest') params.delete('sort'); else params.set('sort', sortVal);
                var qs = params.toString();
                history.replaceState(history.state, '', window.location.pathname + (qs ? '?' + qs : ''));

                var cards = Array.from(grid.querySelectorAll('.listing-card'));
                cards.sort(function (a, b) {
                    switch (sortVal) {
                        case 'price_asc':
                            return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                        case 'price_desc':
                            return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                        case 'closest':
                            // Cards with no distance sort last instead of
                            // becoming NaN and freezing the comparator.
                            return distanceOf(a) - distanceOf(b);
                        case 'oldest':
                            return parseInt(a.dataset.id) - parseInt(b.dataset.id);
                        case 'newest':
                        default:
                            return parseInt(b.dataset.id) - parseInt(a.dataset.id);
                    }
                });

                cards.forEach(function (card) { grid.appendChild(card); });
            });
        }

        function distanceOf(card) {
            var d = parseFloat(card.dataset.distance);
            return isNaN(d) ? Infinity : d;
        }
    }

    // A card image whose load failed before this file ran fires no event left
    // to catch; the markup records those with onerror="this.dataset.imgError".
    function checkCardImages(root) {
        root.querySelectorAll('.card-image img').forEach(function (img) {
            if (img.dataset.imgError) imageFailed(img);
        });
    }

    function openModalFromCard(card, source) {
        var imgEl = card.querySelector('.card-image img');
        openModal({
            id: card.dataset.id,
            source: source || 'browse',
            shareUrl: card.dataset.shareUrl || '',
            price: card.dataset.price,
            address: card.dataset.address,
            semester: card.dataset.semester,
            semesterName: card.dataset.semesterName,
            description: card.dataset.description,
            username: card.dataset.username,
            contactEmail: card.dataset.contactEmail,
            contactPhone: card.dataset.contactPhone,
            image_url: imgEl ? imgEl.src : '',
            utility_electric: card.dataset.utilityElectric || '',
            utility_gas: card.dataset.utilityGas || '',
            utility_water: card.dataset.utilityWater || '',
            utility_internet: card.dataset.utilityInternet || '',
            utility_cost: card.dataset.utilityCost || '',
            amenity_free_parking: card.dataset.amenityFreeParking || '0',
            amenity_paid_parking: card.dataset.amenityPaidParking || '0',
            amenity_laundry_free: card.dataset.amenityLaundryFree || '0',
            amenity_laundry_paid: card.dataset.amenityLaundryPaid || '0',
            amenity_dishwasher: card.dataset.amenityDishwasher || '0',
            amenity_air_conditioning: card.dataset.amenityAirConditioning || '0',
            amenity_pets_allowed: card.dataset.amenityPetsAllowed || '0',
            amenity_furnished: card.dataset.amenityFurnished || '0',
            posterName: card.dataset.posterName || '',
            postedAgo: card.dataset.postedAgo || '',
            negotiable: card.dataset.negotiable || '0',
            sizeSummary: card.dataset.sizeSummary || '',
            roommateGender: card.dataset.roommateGender || '',
            roommatePreference: card.dataset.roommatePreference || '',
            distance: card.dataset.distance || '',
            lat: card.dataset.lat || '',
            lon: card.dataset.lon || '',
            mapsUrl: card.dataset.mapsUrl || '',
            appleMapsUrl: card.dataset.appleMapsUrl || '',
            photos: card.dataset.photos || ''
        });
    }

    /* ======================================================================
       Custom Map Pin
       ====================================================================== */
    function createUvmIcon() {
        if (typeof L === 'undefined') return null;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="40" viewBox="0 0 28 40">' +
            '<path d="M14 0C6.268 0 0 6.268 0 14c0 10.5 14 26 14 26s14-15.5 14-26C28 6.268 21.732 0 14 0z" fill="%23154734"/>' +
            '<circle cx="14" cy="14" r="7" fill="%23FFD100"/>' +
            '<circle cx="14" cy="14" r="3.5" fill="%23154734"/>' +
            '</svg>';
        return L.icon({
            iconUrl: 'data:image/svg+xml,' + encodeURIComponent(svg.replace(/%23/g, '#')),
            iconSize: [28, 40],
            iconAnchor: [14, 40],
            popupAnchor: [0, -36]
        });
    }

    /* ======================================================================
       Map Page
       ====================================================================== */
    function initMap() {
        var mapEl = document.getElementById('mainMap');
        if (!mapEl || typeof L === 'undefined') return;

        var uvmIcon = createUvmIcon();

        var map = L.map('mainMap', {
            zoomControl: false,
            // Standard OSM tiles are dense and colourful, which is exactly what
            // the green-and-gold pins have to compete with. A muted basemap
            // leaves the listings as the only saturated thing on screen.
            scrollWheelZoom: true
        }).setView([CAMPUS.lat, CAMPUS.lon], 14);

        L.tileLayer(TILE_URL, TILE_OPTS).addTo(map);

        L.control.zoom({ position: 'topright' }).addTo(map);
        L.control.scale({ imperial: true, metric: false, position: 'bottomleft' }).addTo(map);

        // Campus is the thing every distance on this site is measured from, so
        // it should be visible rather than implied.
        var campusMarker = L.circleMarker([CAMPUS.lat, CAMPUS.lon], {
            radius: 9,
            color: '#ffffff',
            weight: 3,
            fillColor: '#00313C',
            fillOpacity: 1,
            interactive: true
        }).addTo(map);
        campusMarker.bindTooltip('UVM campus', { direction: 'top', offset: [0, -8] });

        // Leaflet measures its container once at construction, so invalidateSize
        // has to run whatever happens below. Returning early on an empty result
        // set skipped it and left the map rendered at the wrong size — which is
        // exactly the state a filter matching nothing puts the page in.
        setTimeout(function () { map.invalidateSize(); }, 200);
        window.addEventListener('resize', function () { map.invalidateSize(); });

        var sublets = window.MAP_SUBLETS || [];
        if (sublets.length === 0) {
            showMapEmptyState(mapEl);
            return;
        }

        var bounds = L.latLngBounds();

        sublets.forEach(function (sublet) {
            var marker = L.marker([sublet.lat, sublet.lon], { icon: uvmIcon }).addTo(map);
            bounds.extend(marker.getLatLng());

            var popupThumb = sublet.thumbnail_url || sublet.image_url;
            var popupPrice = '$' + Number(sublet.price).toLocaleString() +
                '<span class="price-unit">/mo</span>' +
                (isFlagSet(sublet.price_negotiable) ? '<small class="popup-neg">or best offer</small>' : '');
            // The photo used to be the only way into the listing, which nothing
            // signalled. An explicit button says so; the image still works.
            var popupHtml = '<div class="map-popup">' +
                '<img src="' + escapeHtml(popupThumb) + '" alt="Sublet" data-sublet-id="' + sublet.id + '" onerror="this.style.display=\'none\'">' +
                '<div class="popup-price">' + popupPrice + '</div>' +
                '<div class="popup-address">' + escapeHtml(sublet.address) + '</div>' +
                (sublet.size_summary ? '<div class="popup-size">' + escapeHtml(sublet.size_summary) + '</div>' : '') +
                '<div class="popup-semester">' + escapeHtml(sublet.semester_name || sublet.semester) + '</div>' +
                '<button type="button" class="popup-btn" data-sublet-id="' + sublet.id + '">View listing</button>' +
                '</div>';

            marker.bindPopup(popupHtml, { minWidth: 210, closeButton: true });

            marker.on('popupopen', function () {
                track('map_pin_open', sublet.id, 'map');
                // An open popup is a listing about to be opened: start its
                // first photo now, as a finger on a Browse card does.
                var pinPhotos = parsePhotos(sublet.photos);
                if (pinPhotos.length) preloadPhoto(pinPhotos[0].display);
                var targets = document.querySelectorAll(
                    '.map-popup img[data-sublet-id="' + sublet.id + '"], ' +
                    '.map-popup .popup-btn[data-sublet-id="' + sublet.id + '"]'
                );
                targets.forEach(function (el) {
                    el.addEventListener('click', function () {
                        openModal({
                            id: sublet.id,
                            source: 'map',
                            shareUrl: sublet.share_url || '',
                            price: sublet.price,
                            address: sublet.address,
                            semester: sublet.semester,
                            semesterName: sublet.semester_name,
                            description: sublet.description,
                            username: sublet.username,
                            contactEmail: sublet.contact_email,
                            contactPhone: sublet.contact_phone,
                            image_url: sublet.display_url || sublet.image_url,
                            utility_electric: sublet.utility_electric || '',
                            utility_gas: sublet.utility_gas || '',
                            utility_water: sublet.utility_water || '',
                            utility_internet: sublet.utility_internet || '',
                            utility_cost: sublet.utility_cost || '',
                            amenity_free_parking: String(sublet.amenity_free_parking || 0),
                            amenity_paid_parking: String(sublet.amenity_paid_parking || 0),
                            amenity_laundry_free: String(sublet.amenity_laundry_free || 0),
                            amenity_laundry_paid: String(sublet.amenity_laundry_paid || 0),
                            amenity_dishwasher: String(sublet.amenity_dishwasher || 0),
                            amenity_air_conditioning: String(sublet.amenity_air_conditioning || 0),
                            amenity_pets_allowed: String(sublet.amenity_pets_allowed || 0),
                            amenity_furnished: String(sublet.amenity_furnished || 0),
                            posterName: sublet.poster_name || '',
                            postedAgo: sublet.posted_ago || '',
                            negotiable: String(sublet.price_negotiable || 0),
                            // Labelled server-side in map.php — the vocabulary
                            // lives in includes/listing_fields.php, not here.
                            sizeSummary: sublet.size_summary || '',
                            roommateGender: sublet.roommate_gender_label || '',
                            roommatePreference: sublet.roommate_preference_label || '',
                            distance: sublet.distance_mi || '',
                            lat: sublet.lat,
                            lon: sublet.lon,
                            mapsUrl: sublet.maps_url || '',
                            appleMapsUrl: sublet.apple_maps_url || '',
                            photos: sublet.photos || []
                        });
                    });
                });
            });
        });

        // Campus is part of the frame: fitting to listings alone could push it
        // off-screen and lose the reference point the distances are relative to.
        bounds.extend([CAMPUS.lat, CAMPUS.lon]);

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [60, 60], maxZoom: 16 });
        }
    }

    // An empty map is indistinguishable from a broken one, so say which it is.
    function showMapEmptyState(mapEl) {
        if (mapEl.parentNode.querySelector('.map-empty')) return;
        var note = document.createElement('div');
        note.className = 'map-empty';
        note.innerHTML = '<i class="fa-solid fa-map-location-dot"></i>' +
            '<p>No sublets to show here.</p>' +
            '<p class="map-empty-sub">Widen the filters above, or switch to Browse.</p>';
        mapEl.parentNode.appendChild(note);
    }

    /* ======================================================================
       Post Page — Create/Edit
       ====================================================================== */
    function initPost() {
        initPostMap(initAddressAutocomplete());
        initImageUpload();
        initPostSubmit();
        initRoommateFields();
        initPostShare();
        initSemesterPicker();
    }

    // ---- Semesters: back to back, no gaps ----
    //
    // Each pill's data-key is its place in the calendar (Spring, Summer, Fall,
    // the next Spring…), so back to back means keys one apart. Once one is
    // ticked, only its neighbours can be added, and only the ends of the run
    // can be unticked, so the selection can never have a gap. A semester
    // whose name is not a term and year (no key) can only stand alone.
    // post.php checks the same rule on save.
    function initSemesterPicker() {
        var picker = document.getElementById('semesterPicker');
        if (!picker) return;
        var boxes = Array.prototype.slice.call(picker.querySelectorAll('input[type="checkbox"]'));
        var keyOf = function (box) { return box.dataset.key === '' ? null : parseInt(box.dataset.key, 10); };

        function update() {
            var on = boxes.filter(function (b) { return b.checked; });
            var keys = on.map(keyOf);
            var hasLoose = keys.some(function (k) { return k === null; });
            var min = Math.min.apply(null, keys), max = Math.max.apply(null, keys);
            boxes.forEach(function (box) {
                var k = keyOf(box), ok;
                if (!on.length) ok = true;
                else if (box.checked) ok = on.length === 1 || (!hasLoose && (k === min || k === max));
                else ok = !hasLoose && k !== null && (k === min - 1 || k === max + 1);
                // aria-disabled rather than disabled, so a ticked box that
                // cannot be unticked right now is still sent with the form.
                box.setAttribute('aria-disabled', ok ? 'false' : 'true');
                box.parentNode.classList.toggle('is-unavailable', !ok);
            });
        }

        picker.addEventListener('click', function (e) {
            var box = e.target.closest ? e.target.closest('.semester-option') : null;
            box = box ? box.querySelector('input') : null;
            if (box && box.getAttribute('aria-disabled') === 'true') e.preventDefault();
        });
        picker.addEventListener('keydown', function (e) {
            if (e.key === ' ' && e.target.getAttribute && e.target.getAttribute('aria-disabled') === 'true') e.preventDefault();
        });
        picker.addEventListener('change', update);
        update();
    }

    // Rendered by post.php only after a successful save, so its absence is the
    // normal case rather than an error.
    function initPostShare() {
        var btn = document.getElementById('postShareBtn');
        if (!btn) return;

        var config = window.POST_CONFIG || {};
        btn.addEventListener('click', function () {
            openShareSheet({
                url: config.shareUrl || '',
                price: config.sharePrice || '',
                semester: config.shareSemester || '',
                listingId: config.listingId,
                source: 'post'
            });
        });
    }

    // "Who lives here" and "hoping to sublet to" only mean something when
    // somebody is staying, so hide them at zero roommates. post.php blanks both
    // columns in that case regardless, so this is presentation only.
    function initRoommateFields() {
        var roommates = document.getElementById('roommates');
        var details = document.getElementById('roommateDetails');
        if (!roommates || !details) return;

        function sync() {
            var v = roommates.value.trim();
            details.hidden = (v !== '' && parseInt(v, 10) === 0);
        }

        roommates.addEventListener('input', sync);
        sync();
    }

    // ---- Address search (Nominatim), as an ARIA combobox ----
    //
    // Returns the handle the map uses to place a pin by hand, or null when the
    // page has no address field.
    function initAddressAutocomplete() {
        var input = document.getElementById('address');
        var results = document.getElementById('addressResults');
        var listbox = document.getElementById('addressListbox');
        var message = document.getElementById('addressMessage');
        var status = document.getElementById('addressStatus');
        var latInput = document.getElementById('lat');
        var lonInput = document.getElementById('lon');
        if (!input || !results || !listbox || !latInput || !lonInput) return null;

        var debounceTimer = null;
        var activeIndex = -1;
        var handle = { onPick: null, pinnedByHand: pinnedByHand };

        // lat/lon come from picking a suggestion or from placing the pin by
        // hand, never from typing. Typing over a picked address without picking
        // again used to leave the old coordinates attached to the new text, so
        // the listing mapped to the previous place. So a picked address stays
        // valid only while the text is still the one that was picked.
        //
        // A pin placed by hand is different: the pin is the location and the
        // text is the student's own label for it, so editing the text keeps it.
        //
        // What post.php renders counts as accepted only when it came with
        // coordinates: after a failed save the field can hold text the student
        // typed but never placed, and that must not pass.
        var mode = (latInput.value && lonInput.value) ? 'picked' : 'none';
        var acceptedAddress = mode === 'picked' ? input.value.trim() : '';

        function syncAddressValidity() {
            var text = input.value.trim();
            var ok = text === '' || mode === 'pinned' || (mode === 'picked' && text === acceptedAddress);
            input.setCustomValidity(ok ? '' : 'Pick your address from the suggestions, or tap your place on the map.');
        }
        syncAddressValidity();

        function announce(text) {
            if (status) status.textContent = text;
        }

        function options() {
            return listbox.querySelectorAll('[role="option"]');
        }

        function openList() {
            results.classList.add('open');
            input.setAttribute('aria-expanded', options().length ? 'true' : 'false');
        }

        function closeList() {
            results.classList.remove('open');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        }

        // Searching, no match and lookup-failed are shown in the dropdown, but
        // outside the listbox, so the arrow keys and Enter never land on them.
        function showMessage(text, speak) {
            listbox.innerHTML = '';
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            if (message) {
                message.textContent = text;
                message.hidden = false;
            }
            openList();
            if (speak) announce(text);
        }

        function setActive(index) {
            var items = options();
            activeIndex = index;
            items.forEach(function (item, i) {
                var on = i === index;
                item.classList.toggle('highlighted', on);
                item.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) item.scrollIntoView({ block: 'nearest' });
            });
            if (index >= 0 && items[index]) {
                input.setAttribute('aria-activedescendant', items[index].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function pick(item, label) {
            input.value = label;
            acceptedAddress = label.trim();
            mode = 'picked';
            latInput.value = item.lat;
            lonInput.value = item.lon;
            syncAddressValidity();
            closeList();
            announce('Address set. The pin is on the map.');
            if (handle.onPick) handle.onPick(parseFloat(item.lat), parseFloat(item.lon));
        }

        // Called by the map when the student taps it or drags the pin.
        function pinnedByHand(lat, lon) {
            latInput.value = lat.toFixed(6);
            lonInput.value = lon.toFixed(6);
            mode = 'pinned';
            syncAddressValidity();
            closeList();
            announce(input.value.trim() === ''
                ? 'Pin placed. Type the address you want the listing to show.'
                : 'Pin placed.');
        }

        function renderOptions(data, query) {
            listbox.innerHTML = '';
            if (message) message.hidden = true;

            // Closing the dropdown on no match left the student with a field
            // that refuses to submit and nothing saying why.
            if (!data.length) {
                showMessage('No match for “' + query + '”. Try just the number and street, like “62 King St”, or tap your place on the map.', true);
                return;
            }

            data.forEach(function (item, i) {
                // short_name is the same shortening the cards and map popups
                // apply, so what gets picked here is what everyone else sees.
                var label = item.short_name || item.display_name;
                var opt = document.createElement('div');
                opt.className = 'autocomplete-item';
                opt.id = 'addressOption' + i;
                opt.setAttribute('role', 'option');
                opt.setAttribute('aria-selected', 'false');
                opt.innerHTML = '<i class="fa-solid fa-location-dot" aria-hidden="true"></i> ' + escapeHtml(label);
                // Keep focus in the field, so picking does not blur it first.
                opt.addEventListener('mousedown', function (e) { e.preventDefault(); });
                opt.addEventListener('click', function () { pick(item, label); });
                listbox.appendChild(opt);
            });

            setActive(-1);
            openList();
            announce(data.length + (data.length === 1 ? ' suggestion' : ' suggestions') + '. Use the arrow keys to choose one.');
        }

        input.addEventListener('input', function () {
            syncAddressValidity();
            clearTimeout(debounceTimer);
            var query = input.value.trim();
            if (query.length < 3) {
                closeList();
                return;
            }
            debounceTimer = setTimeout(function () {
                // Said in the dropdown but not announced: it would be read out
                // on every pause in typing.
                showMessage('Searching…', false);
                fetch('api/geocode.php?q=' + encodeURIComponent(query))
                    .then(function (r) {
                        if (!r.ok) throw new Error('lookup failed');
                        return r.json();
                    })
                    .then(function (data) {
                        // A slower answer for an older query must not replace
                        // the message for what is in the box now.
                        if (input.value.trim() !== query) return;
                        renderOptions(Array.isArray(data) ? data : [], query);
                    })
                    .catch(function () {
                        if (input.value.trim() !== query) return;
                        showMessage('Couldn’t search addresses right now. Try again in a moment, or tap your place on the map.', true);
                    });
            }, 350);
        });

        input.addEventListener('keydown', function (e) {
            var items = options();
            var isOpen = results.classList.contains('open');

            if (e.key === 'Escape' && isOpen) {
                e.preventDefault();
                closeList();
                return;
            }
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!isOpen) openList();
                setActive(Math.min(activeIndex + 1, items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (e.key === 'Enter' && isOpen && activeIndex >= 0) {
                e.preventDefault();
                items[activeIndex].click();
            } else if (e.key === 'Tab') {
                closeList();
            }
        });

        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !results.contains(e.target)) closeList();
        });

        return handle;
    }

    // ---- Post map: where the listing goes ----
    //
    // No pin until the listing has a place. A pin parked on campus looked like
    // an answer. Tapping the map (or dragging the pin, with a mouse) places it
    // by hand, which is the way out when the geocoder cannot find an address.
    function initPostMap(address) {
        var mapEl = document.getElementById('postMap');
        if (!mapEl || typeof L === 'undefined') return;

        var config = window.POST_CONFIG || {};
        var hasPlace = typeof config.lat === 'number' && typeof config.lon === 'number';
        var touch = window.matchMedia('(pointer: coarse)').matches;
        var hint = document.getElementById('postMapHintText');

        // On a phone the map belongs under the address field, not below
        // Submit; on a desktop it stays sticky in the side column.
        var box = mapEl.closest('.map-container');
        var slot = document.getElementById('postMapSlot');
        var side = document.querySelector('.post-map-section');
        var phone = window.matchMedia(PHONE_QUERY);
        var map = null;

        function placeMap() {
            if (!box || !slot || !side) return;
            var target = phone.matches ? slot : side;
            if (box.parentNode !== target) target.appendChild(box);
            side.hidden = phone.matches;
            if (map) map.invalidateSize();
        }
        placeMap();
        if (phone.addEventListener) phone.addEventListener('change', placeMap);

        // In the middle of a phone's form, one finger has to scroll the page
        // past the map rather than pan it; two fingers still pan and zoom.
        map = L.map('postMap', { zoomControl: false, dragging: !touch })
            .setView(hasPlace ? [config.lat, config.lon] : [CAMPUS.lat, CAMPUS.lon], hasPlace ? 16 : 14);
        L.tileLayer(TILE_URL, TILE_OPTS).addTo(map);
        L.control.zoom({ position: 'topright' }).addTo(map);

        // Same campus reference as the main map, useful here because the form
        // rejects anything more than 50 miles from it.
        L.circleMarker([CAMPUS.lat, CAMPUS.lon], {
            radius: 7,
            color: '#ffffff',
            weight: 2,
            fillColor: '#00313C',
            fillOpacity: 1
        }).addTo(map).bindTooltip('UVM campus', { direction: 'top', offset: [0, -6] });

        var marker = null;
        var tap = touch ? 'Tap' : 'Click';

        function setHint(text) {
            if (hint) hint.textContent = text;
        }

        function place(lat, lon, recentre) {
            if (!marker) {
                marker = L.marker([lat, lon], { icon: createUvmIcon(), draggable: !touch, keyboard: false }).addTo(map);
                marker.on('dragend', function () {
                    var p = marker.getLatLng();
                    if (address) address.pinnedByHand(p.lat, p.lng);
                });
            } else {
                marker.setLatLng([lat, lon]);
            }
            if (recentre) map.setView([lat, lon], Math.max(map.getZoom(), 16), { animate: true });
            setHint(touch ? 'Tap the map to move the pin. Pinch to zoom.' : 'Drag the pin or click the map to move it.');
        }

        if (hasPlace) {
            place(config.lat, config.lon, false);
        } else {
            setHint('Search your address, or ' + tap.toLowerCase() + ' the map where your place is.');
        }

        map.on('click', function (e) {
            place(e.latlng.lat, e.latlng.lng, false);
            if (address) address.pinnedByHand(e.latlng.lat, e.latlng.lng);
        });

        if (address) {
            address.onPick = function (lat, lon) { place(lat, lon, true); };
        }

        setTimeout(function () { map.invalidateSize(); }, 200);
    }

    // ---- Photos ----
    //
    // Each pick adds to the photos already chosen instead of replacing them,
    // and every new photo can be taken back out before it is uploaded. The
    // browser only submits what is in the file input, so the list is written
    // back into it through a DataTransfer. Where that is unsupported, a pick
    // replaces the set, as it always used to.
    function initImageUpload() {
        var dropZone = document.getElementById('dropZone');
        var fileInput = document.getElementById('imageInput');
        var previews = document.getElementById('imagePreviews');
        var status = document.getElementById('uploadStatus');
        if (!dropZone || !fileInput || !previews) return;

        var config = window.POST_CONFIG || {};
        var canMerge = (function () {
            try { return typeof DataTransfer === 'function' && !!new DataTransfer().items; } catch (e) { return false; }
        })();
        var chosen = [];
        var note = '';

        ['dragenter', 'dragover'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            dropZone.addEventListener(evt, function (e) {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            });
        });

        dropZone.addEventListener('drop', function (e) {
            if (!e.dataTransfer.files.length) return;
            if (!canMerge) fileInput.files = e.dataTransfer.files;
            addFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', function () {
            addFiles(fileInput.files);
        });

        function megabytes(bytes) {
            return (bytes / 1048576).toFixed(bytes < 10485760 ? 1 : 0) + ' MB';
        }

        function addFiles(list) {
            var incoming = Array.from(list);
            var skipped = [];

            if (!canMerge) {
                chosen = incoming;
            } else {
                incoming.forEach(function (file) {
                    // The server checks the bytes either way; this only spares
                    // a wasted upload. HEIC can arrive with no type at all.
                    if (file.type && !/^image\//.test(file.type)) {
                        skipped.push(file.name + ' isn’t a photo');
                        return;
                    }
                    if (config.uploadMaxBytes && file.size > config.uploadMaxBytes) {
                        skipped.push(file.name + ' is over ' + megabytes(config.uploadMaxBytes));
                        return;
                    }
                    var dupe = chosen.some(function (c) {
                        return c.name === file.name && c.size === file.size && c.lastModified === file.lastModified;
                    });
                    if (!dupe) chosen.push(file);
                });

                // PHP drops every file past max_file_uploads without a word.
                var max = config.maxFiles || 20;
                if (chosen.length > max) {
                    skipped.push((chosen.length - max) + ' more than the ' + max + ' that fit in one save');
                    chosen = chosen.slice(0, max);
                }
                writeBack();
            }

            note = skipped.length ? 'Left out: ' + skipped.join('; ') + '.' : '';
            render();
        }

        function writeBack() {
            var dt = new DataTransfer();
            chosen.forEach(function (file) { dt.items.add(file); });
            fileInput.files = dt.files;
        }

        function render() {
            previews.querySelectorAll('.new-preview').forEach(function (el) {
                if (el.dataset.url) URL.revokeObjectURL(el.dataset.url);
                el.remove();
            });

            chosen.forEach(function (file, i) {
                var url = URL.createObjectURL(file);
                var div = document.createElement('div');
                div.className = 'image-preview new-preview';
                div.dataset.url = url;

                var img = document.createElement('img');
                img.alt = 'New photo ' + (i + 1);
                // A HEIC a desktop browser cannot draw still uploads fine (the
                // server converts it), so name it instead of showing a hole.
                img.addEventListener('error', function () {
                    img.remove();
                    var name = document.createElement('span');
                    name.className = 'preview-name';
                    name.textContent = file.name;
                    div.insertBefore(name, div.firstChild);
                });
                img.src = url;
                div.appendChild(img);

                if (canMerge) {
                    var remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'remove-image';
                    remove.setAttribute('aria-label', 'Remove new photo ' + (i + 1));
                    remove.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';
                    remove.addEventListener('click', function () {
                        chosen.splice(chosen.indexOf(file), 1);
                        writeBack();
                        note = '';
                        render();
                    });
                    div.appendChild(remove);
                }

                previews.appendChild(div);
            });

            markCover(previews);
            updateStatus();
        }

        function updateStatus() {
            if (!status) return;
            var total = chosen.reduce(function (n, f) { return n + f.size; }, 0);
            var line = chosen.length
                ? chosen.length + (chosen.length === 1 ? ' new photo' : ' new photos') + ' (' + megabytes(total) + ') will upload when you save.'
                : '';
            status.textContent = [line, note].filter(Boolean).join(' ');
        }

        // Existing photos are deleted on the server straight away, so they ask
        // first. Delegated, because previews are re-rendered around them.
        previews.addEventListener('click', function (e) {
            var btn = e.target.closest('.remove-image[data-image-id]');
            if (!btn) return;
            e.preventDefault();
            if (!confirm('Delete this photo? It comes off your listing right away.')) return;

            fetch('api/images.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_method=DELETE&id=' + encodeURIComponent(btn.dataset.imageId)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        btn.closest('.image-preview').remove();
                        // The server promotes the next photo to the card.
                        markCover(previews);
                        note = '';
                        updateStatus();
                    } else {
                        note = data.error || 'That photo could not be deleted.';
                        updateStatus();
                    }
                })
                .catch(function () {
                    note = 'That photo could not be deleted. Check your connection and try again.';
                    updateStatus();
                });
        });
    }

    // The first photo is the card's cover: the first saved one, or else the
    // first new one. The badge follows it as photos come and go.
    function markCover(previews) {
        previews.querySelectorAll('.image-preview').forEach(function (el, i) {
            el.classList.toggle('is-thumbnail', i === 0);
            var badge = el.querySelector('.thumbnail-badge');
            if (i === 0 && !badge) {
                badge = document.createElement('span');
                badge.className = 'thumbnail-badge';
                badge.textContent = 'Cover';
                el.appendChild(badge);
            } else if (i !== 0 && badge) {
                badge.remove();
            }
        });
    }

    // ---- Submit: say what is happening, and only once ----
    //
    // Posting with photos can take a while on a phone, and with no sign of it
    // a second tap sent the form again. The button is not disabled: a
    // disabled submitter is left out of the form data, and Delete is told
    // apart by its name=action value.
    function initPostSubmit() {
        var form = document.getElementById('postForm');
        if (!form) return;

        var config = window.POST_CONFIG || {};
        var status = document.getElementById('uploadStatus');
        var busy = false;
        var restore = [];

        form.addEventListener('submit', function (e) {
            if (busy) {
                e.preventDefault();
                return;
            }

            var submitter = e.submitter || document.getElementById('postSubmit');
            var deleting = !!submitter && submitter.value === 'delete';
            var input = document.getElementById('imageInput');
            var files = input && input.files ? Array.from(input.files) : [];
            var total = files.reduce(function (n, f) { return n + f.size; }, 0);

            // Over post_max_size PHP receives nothing at all, and the photos
            // would have to be picked again after the error.
            if (!deleting && config.postMaxBytes && total > config.postMaxBytes - 1048576) {
                e.preventDefault();
                if (status) {
                    status.textContent = 'These photos come to ' + (total / 1048576).toFixed(0) +
                        ' MB, more than one save can take. Remove a few, save, then add the rest.';
                    status.scrollIntoView({ block: 'center' });
                }
                return;
            }

            busy = true;
            form.setAttribute('aria-busy', 'true');
            var label = deleting ? 'Deleting…'
                : files.length ? 'Uploading ' + files.length + (files.length === 1 ? ' photo…' : ' photos…')
                : config.isEdit ? 'Saving…' : 'Posting…';
            setBusy(submitter, label);
        });

        function setBusy(btn, label) {
            if (!btn) return;
            var text = btn.querySelector('.btn-label');
            var icon = btn.querySelector('i');
            restore = [btn, text ? text.textContent : '', icon ? icon.className : ''];
            btn.classList.add('is-busy');
            btn.setAttribute('aria-disabled', 'true');
            if (text) text.textContent = label;
            if (icon) icon.className = 'fa-solid fa-spinner fa-spin';
        }

        // A page restored from the back-forward cache comes back mid-submit.
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted || !restore.length) return;
            var btn = restore[0];
            var text = btn.querySelector('.btn-label');
            var icon = btn.querySelector('i');
            if (text) text.textContent = restore[1];
            if (icon) icon.className = restore[2];
            btn.classList.remove('is-busy');
            btn.removeAttribute('aria-disabled');
            form.removeAttribute('aria-busy');
            busy = false;
        });
    }

    /* ======================================================================
       Admin Dashboard
       ====================================================================== */
    function initAdmin() {
        initAdminTabs();
        initSemesterManagement();
        initArchive();
        initImagesTab();
        initAnnouncementManagement();
        initPostManagement();
        initUserManagement();
        initEmailComposer();
        initAllowlistManagement();
    }

    // Access tab: the individually-approved and blocked netid lists.
    //
    // Every action here rewrites app/.htaccess on the server, which is what
    // Apache enforces for the whole /app/ directory. The endpoint verifies the
    // result and rolls itself back on failure, so a non-success response means
    // nothing changed — the message is worth showing in full rather than
    // flattening to a generic "failed", because it says which check failed.
    function initAllowlistManagement() {
        var status = document.getElementById('allowlistStatus');
        if (!status) return; // table missing, or not on this tab's markup

        function setStatus(kind, icon, message) {
            status.innerHTML = '<div class="alert alert-' + kind + '" style="margin-bottom: 1rem;">' +
                '<i class="fa-solid ' + icon + '"></i> ' + escapeHtml(message) + '</div>';
        }

        function post(body, btn, pending, done) {
            if (btn) btn.disabled = true;
            setStatus('info', 'fa-spinner fa-spin', pending);

            fetch('api/allowlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (btn) btn.disabled = false;
                if (data.success) {
                    done(data);
                } else {
                    setStatus('error', 'fa-exclamation-triangle', data.error || 'The change was not applied.');
                }
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                setStatus('error', 'fa-exclamation-triangle', 'Network error — nothing was changed.');
            });
        }

        function wireAdd(kind, uidId, noteId, btnId) {
            var btn = document.getElementById(btnId);
            if (!btn) return;

            btn.addEventListener('click', function () {
                var uidInput = document.getElementById(uidId);
                var noteInput = document.getElementById(noteId);
                var uid = uidInput.value.trim().toLowerCase();

                if (!uid) {
                    setStatus('error', 'fa-exclamation-triangle', 'Enter a netid first.');
                    uidInput.focus();
                    return;
                }

                if (kind === 'block' && !confirm('Block ' + uid + '?\n\nThey will lose access immediately, even if they are a current student.')) {
                    return;
                }

                post(
                    'action=add&kind=' + encodeURIComponent(kind) +
                        '&uid=' + encodeURIComponent(uid) +
                        '&note=' + encodeURIComponent(noteInput.value.trim()),
                    btn,
                    'Updating app/.htaccess and checking the site still loads…',
                    function () { location.reload(); }
                );
            });
        }

        wireAdd('allow', 'allowUid', 'allowNote', 'addAllowBtn');
        wireAdd('block', 'blockUid', 'blockNote', 'addBlockBtn');

        document.querySelectorAll('.remove-allowlist-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Remove ' + btn.dataset.uid + ' from this list?')) return;

                post(
                    'action=remove&id=' + encodeURIComponent(btn.dataset.id),
                    btn,
                    'Updating app/.htaccess and checking the site still loads…',
                    function () { location.reload(); }
                );
            });
        });

        var rebuildBtn = document.getElementById('rebuildAllowlistBtn');
        if (rebuildBtn) {
            rebuildBtn.addEventListener('click', function () {
                post(
                    'action=rebuild',
                    rebuildBtn,
                    'Regenerating app/.htaccess from the database…',
                    function (data) {
                        // No rows changed, so the page does not need reloading —
                        // just show the line that is now in the file.
                        var pre = document.getElementById('allowlistRule');
                        if (pre && data.rule) pre.textContent = data.rule;
                        setStatus('success', 'fa-check', 'app/.htaccess matches the database and the site still loads.');
                    }
                );
            });
        }
    }

    // The open tab is kept in the URL's hash, so a reload (or the Activity
    // tab's range links, which reload the page) comes back to the same tab.
    function initAdminTabs() {
        var tabs = document.querySelectorAll('.admin-tab');

        function show(name) {
            var panel = document.getElementById('tab-' + name);
            if (!panel) return false;
            tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.tab === name); });
            document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.toggle('active', p === panel); });
            return true;
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                if (show(tab.dataset.tab)) history.replaceState(null, '', '#' + tab.dataset.tab);
            });
        });

        if (window.location.hash) show(window.location.hash.slice(1));
    }

    function initSemesterManagement() {
        // Add semester
        var addBtn = document.getElementById('addSemesterBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                var code = document.getElementById('semCode').value.trim();
                var name = document.getElementById('semName').value.trim();
                if (!code || !name) return alert('Both code and name are required');

                fetch('api/semesters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=add&code=' + encodeURIComponent(code) + '&name=' + encodeURIComponent(name)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to add semester');
                    }
                });
            });
        }

        // Toggle/delete semester
        document.querySelectorAll('.toggle-semester').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // Deactivating hides every listing in the semester, so confirm
                // when there are posts that would disappear from the site.
                var postCount = parseInt(btn.dataset.postCount, 10) || 0;
                if (btn.dataset.active === '1' && postCount > 0) {
                    var msg = 'Deactivate ' + (btn.dataset.name || 'this semester') + '?\n\n'
                        + postCount + ' listing' + (postCount === 1 ? '' : 's')
                        + ' will be hidden from Browse and Map. Nothing is deleted — '
                        + 'reactivating brings them back.';
                    if (!confirm(msg)) return;
                }

                fetch('api/semesters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=toggle&id=' + btn.dataset.id
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) location.reload();
                });
            });
        });

        document.querySelectorAll('.delete-semester').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this semester?')) return;
                fetch('api/semesters.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete&id=' + btn.dataset.id
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) location.reload();
                    else alert(data.error || 'Failed to delete');
                });
            });
        });
    }

    // "Archive a semester" (Semesters tab). Archive… on a hidden semester
    // loads the dry run from api/archive.php into #archivePanel; archiving
    // takes the semester code typed back, and nothing else, as confirmation.
    // The server re-checks everything, so this only decides what to show.
    function initArchive() {
        var panel = document.getElementById('archivePanel');
        if (!panel) return;
        var opener = null;

        document.querySelectorAll('.archive-semester').forEach(function (btn) {
            btn.addEventListener('click', function () {
                opener = btn;
                loadPreview(btn.dataset.code, btn.dataset.name);
            });
        });

        function loadPreview(code, name) {
            panel.hidden = false;
            panel.innerHTML = '<p class="admin-note" role="status">Working out what archiving ' + escapeHtml(name) + ' would remove&hellip;</p>';
            panel.scrollIntoView({ block: 'start', behavior: 'smooth' });
            fetch('api/archive.php?action=preview&code=' + encodeURIComponent(code))
                .then(function (r) { return r.json(); })
                .then(function (plan) { renderPreview(plan, code, name); })
                .catch(function () {
                    panel.innerHTML = '<div class="alert alert-error" role="alert">Could not load the preview. Reload the page and try again.</div>';
                });
        }

        function renderPreview(plan, code, name) {
            var t = plan.totals || {};
            var html = '<h4 class="admin-subhead">Archive ' + escapeHtml(name) + ': dry run</h4>'
                + '<p class="admin-note">Nothing has changed yet. This is everything archiving would remove.</p>';

            if (plan.blocking && plan.blocking.length) {
                html += '<div class="alert alert-error" role="alert"><i class="fa-solid fa-ban" aria-hidden="true"></i><span>Can\'t archive it: '
                    + plan.blocking.map(escapeHtml).join(' ') + '</span></div>';
            }
            (plan.warnings || []).forEach(function (w) {
                html += '<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>' + escapeHtml(w) + '</span></div>';
            });

            if (plan.semester) {
                html += '<dl class="archive-facts">'
                    + fact('Listings', t.listings)
                    + fact('Photos', t.photos + ' (' + t.files + ' files with their copies)')
                    + fact('On disk', formatBytes(t.bytes))
                    + fact('Activity events', (plan.events && plan.events.rows) || 0)
                    + fact('Share cards', t.share_cards)
                    + '</dl>';

                html += '<p class="admin-note">' + (plan.tarball
                    ? 'The photos are saved first, to <code>' + escapeHtml(plan.tarball) + '</code> in <code>' + escapeHtml(plan.backup_dir) + '</code>, and the tarball is checked against this list before anything is deleted.'
                    : 'There are no photo files to save, so no tarball is written.') + '</p>';

                html += '<p class="admin-note">The archive keeps: ' + t.listings + ' listing' + (t.listings === 1 ? '' : 's')
                    + (t.taken ? ' (' + t.taken + ' marked taken)' : '')
                    + (t.price_median !== null ? ', median $' + Math.round(t.price_median).toLocaleString()
                        + ' ($' + Math.round(t.price_min).toLocaleString() + '&ndash;$' + Math.round(t.price_max).toLocaleString() + ')' : '')
                    + ', ' + t.views + ' viewed, ' + t.contacts + ' got in touch, ' + t.shares + ' shares, '
                    + t.share_arrivals + ' arrivals from share links.</p>';

                if (plan.listings && plan.listings.length) {
                    html += '<div class="table-scroll"><table class="admin-table"><thead><tr>'
                        + '<th>Listing</th><th>Posted by</th><th class="num">Price</th><th class="num">Photos</th><th class="num">Files</th><th class="num">Size</th>'
                        + '</tr></thead><tbody>';
                    plan.listings.forEach(function (l) {
                        html += '<tr><td>' + escapeHtml(l.address)
                            + (l.status === 'taken' ? ' <span class="semester-meta">Taken</span>' : l.status === 'paused' ? ' <span class="semester-meta">Paused</span>' : '')
                            + '</td><td>' + escapeHtml(l.username) + '</td>'
                            + '<td class="num">$' + Math.round(l.price).toLocaleString() + '</td>'
                            + '<td class="num">' + l.photos + '</td><td class="num">' + l.files + '</td>'
                            + '<td class="num">' + formatBytes(l.bytes) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                // Listings that also run for a later semester: archiving takes
                // this semester off them and leaves them up.
                if (plan.staying && plan.staying.length) {
                    html += '<h4 class="admin-subhead">Staying on the site</h4>'
                        + '<p class="admin-note">' + plan.staying.length + ' listing' + (plan.staying.length === 1 ? ' also runs' : 's also run')
                        + ' for a later semester. ' + (plan.staying.length === 1 ? 'It loses' : 'They lose') + ' ' + escapeHtml(name)
                        + ' and ' + (plan.staying.length === 1 ? 'keeps its' : 'keep their') + ' photos and activity.</p>'
                        + '<div class="table-scroll"><table class="admin-table"><thead><tr><th>Listing</th><th>Posted by</th><th>Still listed for</th></tr></thead><tbody>';
                    plan.staying.forEach(function (s) {
                        html += '<tr><td>' + escapeHtml(s.address) + '</td><td>' + escapeHtml(s.username) + '</td><td>' + escapeHtml(s.keeps) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }
            }

            if (!plan.blocking || !plan.blocking.length) {
                html += '<div class="archive-confirm">'
                    + '<label for="archiveConfirm">To archive ' + escapeHtml(name) + ', type its code, <code>' + escapeHtml(code) + '</code></label>'
                    + '<input type="text" id="archiveConfirm" autocomplete="off" autocapitalize="off" spellcheck="false">'
                    + '<div class="archive-confirm-actions">'
                    + '<button type="button" class="btn btn-danger btn-sm" id="archiveRun" disabled>Archive ' + escapeHtml(name) + '</button>'
                    + '<button type="button" class="btn btn-secondary btn-sm" id="archiveCancel">Cancel</button>'
                    + '</div></div>';
            } else {
                html += '<div class="archive-confirm-actions"><button type="button" class="btn btn-secondary btn-sm" id="archiveCancel">Close</button></div>';
            }
            html += '<div id="archiveResult" role="status"></div>';
            panel.innerHTML = html;

            var cancel = document.getElementById('archiveCancel');
            if (cancel) cancel.addEventListener('click', closePanel);
            var input = document.getElementById('archiveConfirm');
            var run = document.getElementById('archiveRun');
            if (input && run) {
                input.addEventListener('input', function () { run.disabled = input.value.trim() !== code; });
                input.focus();
                run.addEventListener('click', function () {
                    if (input.value.trim() !== code) return;
                    run.disabled = true;
                    input.disabled = true;
                    if (cancel) cancel.disabled = true;
                    run.innerHTML = '<i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Saving ' + t.files + ' files and archiving&hellip;';
                    var body = new URLSearchParams({ action: 'archive', code: code, confirm: input.value.trim() });
                    fetch('api/archive.php', { method: 'POST', body: body })
                        .then(function (r) { return r.json(); })
                        .then(function (res) { showResult(res); })
                        .catch(function () {
                            showResult({ error: 'No answer from the server. Reload the page to see whether it finished before trying again.' });
                        });
                });
            }
        }

        function showResult(res) {
            var out = document.getElementById('archiveResult');
            if (res.success) {
                panel.innerHTML = '<div class="alert alert-success" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>'
                    + escapeHtml(res.semester) + ' is archived: ' + res.listings + ' listing' + (res.listings === 1 ? '' : 's') + ', '
                    + res.photos + ' photos (' + res.files_deleted + ' files, ' + formatBytes(res.bytes) + ') and '
                    + res.events_deleted + ' events removed'
                    + (res.stayed ? '; ' + res.stayed + ' listing' + (res.stayed === 1 ? '' : 's') + ' that also run' + (res.stayed === 1 ? 's' : '') + ' for a later semester stayed up' : '')
                    + (res.tarball ? '. Photos saved to ' + escapeHtml(res.tarball) + ' (' + formatBytes(res.tarball_bytes) + ').' : '.')
                    + '</span></div>'
                    + (res.files_left && res.files_left.length
                        ? '<div class="alert alert-warning"><span>' + res.files_left.length + ' file(s) could not be deleted and will show as orphans in the Images tab: '
                            + res.files_left.map(escapeHtml).join(', ') + '</span></div>'
                        : '')
                    + '<div class="archive-confirm-actions"><a class="btn btn-secondary btn-sm" href="admin.php#semesters">Refresh the page</a></div>';
                panel.focus();
                return;
            }
            var run = document.getElementById('archiveRun');
            var input = document.getElementById('archiveConfirm');
            var cancel = document.getElementById('archiveCancel');
            if (out) out.innerHTML = '<div class="alert alert-error" role="alert"><span>' + escapeHtml(res.error || 'Archiving failed.') + '</span></div>';
            if (input) input.disabled = false;
            if (cancel) cancel.disabled = false;
            if (run) {
                run.innerHTML = 'Try again';
                run.disabled = !input || input.value.trim() === '';
            }
        }

        function closePanel() {
            panel.hidden = true;
            panel.innerHTML = '';
            if (opener) opener.focus();
        }

        function fact(label, value) {
            return '<div><dt>' + escapeHtml(label) + '</dt><dd>' + escapeHtml(value) + '</dd></div>';
        }

        // Deleting a backup is two presses: the first arms the button for a
        // few seconds and says what the second one does.
        document.querySelectorAll('.delete-tarball').forEach(function (btn) {
            var timer = null;
            btn.addEventListener('click', function () {
                if (!btn.classList.contains('is-armed')) {
                    btn.classList.add('is-armed', 'btn-danger');
                    btn.classList.remove('btn-secondary');
                    btn.textContent = 'Delete this backup';
                    timer = setTimeout(disarm, 4000);
                    return;
                }
                clearTimeout(timer);
                btn.disabled = true;
                fetch('api/archive.php', { method: 'POST', body: new URLSearchParams({ action: 'delete_tarball', name: btn.dataset.name }) })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            var li = btn.closest('li');
                            li.textContent = btn.dataset.name + ' deleted.';
                            li.classList.add('semester-meta');
                        } else {
                            btn.disabled = false;
                            disarm();
                            btn.insertAdjacentHTML('afterend', '<span class="archive-inline-error" role="alert">' + escapeHtml(res.error || 'Could not delete it.') + '</span>');
                        }
                    });
            });
            function disarm() {
                btn.classList.remove('is-armed', 'btn-danger');
                btn.classList.add('btn-secondary');
                btn.textContent = 'Delete';
            }
        });
    }

    // Images tab: every listing's photos, storage per semester, and the orphan
    // sweep, drawn from api/images.php?action=inventory the first time the tab
    // opens (it reads every file's size and dimensions, so not on page load).
    // Every change goes to the server and the tab is redrawn from a fresh
    // inventory, so what is shown is always what is stored.
    function initImagesTab() {
        var list = document.getElementById('imagesList');
        if (!list) return;
        var tabBtn = document.querySelector('.admin-tab[data-tab="images"]');
        var summary = document.getElementById('imagesSummary');
        var storage = document.getElementById('imagesStorage');
        var thumbsBox = document.getElementById('imagesThumbs');
        var semSelect = document.getElementById('imagesSemester');
        var hiddenBox = document.getElementById('imagesHidden');
        var only = document.getElementById('imagesOnly');
        var onlyLabel = document.getElementById('imagesOnlyLabel');
        var listingsView = document.getElementById('imagesListingsView');
        var orphansView = document.getElementById('imagesOrphansView');
        var bulk = document.getElementById('imagesBulk');
        var bulkCount = document.getElementById('imagesBulkCount');
        var bulkDelete = document.getElementById('imagesBulkDelete');

        var inventory = null;
        var loading = null;
        var onlyListing = null;
        var selected = {};
        var view = 'listings';

        function post(params) {
            var body = new URLSearchParams();
            Object.keys(params).forEach(function (k) {
                var v = params[k];
                if (Array.isArray(v)) v.forEach(function (x) { body.append(k + '[]', x); });
                else body.append(k, v);
            });
            return fetch('api/images.php', { method: 'POST', body: body }).then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok || data.error) throw new Error(data.error || 'That did not work.');
                    return data;
                });
            });
        }

        function load() {
            if (loading) return loading;
            loading = fetch('api/images.php?action=inventory')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    inventory = data;
                    loading = null;
                    render();
                })
                .catch(function () {
                    loading = null;
                    summary.textContent = 'Could not load the photos. Reload the page to try again.';
                });
            return loading;
        }

        if (tabBtn) tabBtn.addEventListener('click', function () { if (!inventory) load(); });
        if (window.location.hash === '#images') load();

        // From the Posts tab: one listing's photos.
        showListingImages = function (id) {
            onlyListing = id;
            if (tabBtn) tabBtn.click();
            setView('listings');
            (inventory ? Promise.resolve() : load()).then(function () {
                render();
                var group = list.querySelector('[data-listing="' + id + '"]');
                if (group) group.scrollIntoView({ block: 'start' });
            });
        };
        document.getElementById('imagesShowAll').addEventListener('click', function () {
            onlyListing = null;
            render();
        });
        semSelect.addEventListener('change', render);
        hiddenBox.addEventListener('change', render);

        document.querySelectorAll('[data-images-view]').forEach(function (btn) {
            btn.addEventListener('click', function () { setView(btn.dataset.imagesView); });
        });
        function setView(v) {
            view = v;
            document.querySelectorAll('[data-images-view]').forEach(function (b) {
                var on = b.dataset.imagesView === v;
                b.classList.toggle('active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            listingsView.hidden = v !== 'listings';
            orphansView.hidden = v !== 'orphans';
            if (v === 'orphans') loadOrphans();
            updateBulk();
        }

        function render() {
            if (!inventory) return;
            var t = inventory.totals;
            summary.textContent = t.photos + ' photos on ' + t.listings + ' listings, ' + formatBytes(t.bytes_used)
                + ' with their copies. The folder holds ' + t.files_on_disk + ' files, ' + formatBytes(t.bytes_on_disk) + '.';

            // Storage per semester.
            var html = '<div class="table-scroll"><table class="admin-table images-storage"><thead><tr><th>Semester</th><th class="num">Listings</th><th class="num">Photos</th><th class="num">On disk</th></tr></thead><tbody>';
            inventory.semesters.forEach(function (sem) {
                html += '<tr><td>' + escapeHtml(sem.name) + (sem.hidden ? ' <span class="semester-meta">Hidden</span>' : '') + '</td>'
                    + '<td class="num">' + sem.listings + '</td><td class="num">' + sem.photos + '</td><td class="num">' + formatBytes(sem.bytes) + '</td></tr>';
            });
            storage.innerHTML = html + '</tbody></table></div>';

            // Thumbnails the older photos never got.
            if (t.missing_thumbs > 0) {
                thumbsBox.hidden = false;
                thumbsBox.innerHTML = '<div class="alert alert-info"><span>' + t.missing_thumbs + ' photo' + (t.missing_thumbs === 1 ? ' has' : 's have')
                    + ' no thumbnail yet; they are shown here at display size until made.</span>'
                    + '<button type="button" class="btn btn-sm btn-secondary" id="imagesMakeThumbs">Make ' + t.missing_thumbs + ' thumbnail' + (t.missing_thumbs === 1 ? '' : 's') + '</button></div>';
                document.getElementById('imagesMakeThumbs').addEventListener('click', makeThumbs);
            } else {
                thumbsBox.hidden = true;
                thumbsBox.innerHTML = '';
            }

            // The semester filter, rebuilt from what exists.
            var keep = semSelect.value;
            semSelect.innerHTML = '<option value="">All semesters</option>' + inventory.semesters.map(function (sem) {
                return '<option value="' + escapeHtml(sem.code) + '">' + escapeHtml(sem.name) + '</option>';
            }).join('');
            semSelect.value = keep;

            var listings = inventory.listings.filter(function (l) {
                if (onlyListing !== null) return l.id === onlyListing;
                if (semSelect.value && (l.semesters || [l.semester]).indexOf(semSelect.value) < 0) return false;
                if (hiddenBox.checked && !l.hidden && l.status === 'open') return false;
                return true;
            });
            only.hidden = onlyListing === null;
            if (onlyListing !== null) {
                var one = listings[0];
                onlyLabel.textContent = one ? 'Showing ' + one.address + ' only.' : 'That listing has no photos.';
            }

            // Drop selections that are no longer on the page.
            var visible = {};
            listings.forEach(function (l) { l.photos.forEach(function (p) { visible[p.id] = true; }); });
            Object.keys(selected).forEach(function (id) { if (!visible[id]) delete selected[id]; });

            list.innerHTML = listings.length ? listings.map(groupHtml).join('') : '<p class="text-muted">No listings match.</p>';
            updateBulk();
        }

        function groupHtml(l) {
            var n = l.photos.length;
            var html = '<section class="image-group" data-listing="' + l.id + '">'
                + '<header class="image-group-head"><h4>' + escapeHtml(l.address) + '</h4>'
                + '<p class="semester-meta">' + escapeHtml(l.username) + ' &middot; ' + escapeHtml(l.semester_name)
                + (l.hidden ? ' &middot; Hidden' : '')
                + (l.status === 'paused' ? ' &middot; Paused' : l.status === 'taken' ? ' &middot; Taken' : '')
                + ' &middot; ' + n + ' photo' + (n === 1 ? '' : 's') + ' &middot; ' + formatBytes(l.bytes) + '</p>'
                + (l.cover_out_of_step ? '<p class="images-warning">The card shows a different photo from the first one. Moving or deleting any photo puts them back in step.</p>' : '')
                + '</header><div class="image-group-status" role="alert"></div><ol class="image-tiles">';
            l.photos.forEach(function (p, i) {
                var label = 'photo ' + (i + 1) + ' of ' + n;
                var src = p.thumb || p.display;
                html += '<li class="image-tile' + (selected[p.id] ? ' is-selected' : '') + '" data-id="' + p.id + '">'
                    + '<div class="image-frame">'
                    + (p.missing
                        ? '<div class="image-missing"><i class="fa-solid fa-image" aria-hidden="true"></i><span>File missing</span></div>'
                        : '<a href="' + escapeHtml(p.display) + '" target="_blank" rel="noopener"><img src="' + escapeHtml(src) + '" alt="' + escapeHtml(l.address + ', ' + label) + '" loading="lazy" decoding="async"></a>')
                    + (p.cover ? '<span class="image-cover">Cover</span>' : '')
                    + '<label class="image-select"><input type="checkbox" data-select="' + p.id + '"' + (selected[p.id] ? ' checked' : '') + '><span class="sr-only">Select ' + label + '</span></label>'
                    + '</div>'
                    + '<p class="image-meta">' + (p.missing ? escapeHtml(p.name)
                        : ((p.width ? p.width + '&times;' + p.height + ' &middot; ' + escapeHtml(p.format) : 'unreadable image') + ' &middot; ' + formatBytes(p.bytes)))
                    + (p.thumb || p.missing ? '' : ' &middot; no thumbnail') + '</p>'
                    + '<div class="image-actions">'
                    + '<button type="button" class="image-btn" data-act="move" data-dir="-1" aria-label="Move ' + label + ' earlier"' + (i === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>'
                    + '<button type="button" class="image-btn" data-act="move" data-dir="1" aria-label="Move ' + label + ' later"' + (i === n - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>'
                    + (p.cover ? '' : '<button type="button" class="image-btn image-btn-text" data-act="set_cover" aria-label="Make ' + label + ' the cover">Make cover</button>')
                    + '<button type="button" class="image-btn image-btn-text image-btn-delete" data-act="delete" aria-label="Delete ' + label + '"' + (n === 1 ? ' disabled title="A listing needs at least one photo"' : '') + '>Delete</button>'
                    + '</div></li>';
            });
            return html + '</ol></section>';
        }

        list.addEventListener('change', function (e) {
            var id = e.target.dataset ? e.target.dataset.select : null;
            if (!id) return;
            if (e.target.checked) selected[id] = true; else delete selected[id];
            var tile = e.target.closest('.image-tile');
            if (tile) tile.classList.toggle('is-selected', e.target.checked);
            updateBulk();
        });

        list.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-act]');
            if (!btn || btn.disabled) return;
            var tile = btn.closest('.image-tile');
            var group = btn.closest('.image-group');
            var act = btn.dataset.act;
            // Delete is two presses: the first arms it and says so.
            if (act === 'delete' && !btn.classList.contains('is-armed')) {
                btn.classList.add('is-armed');
                btn.textContent = 'Delete?';
                setTimeout(function () {
                    if (btn.isConnected) { btn.classList.remove('is-armed'); btn.textContent = 'Delete'; }
                }, 4000);
                return;
            }
            var params = act === 'delete' ? { _method: 'DELETE', id: tile.dataset.id } : { action: act, id: tile.dataset.id };
            if (act === 'move') params.dir = btn.dataset.dir;
            group.classList.add('is-busy');
            post(params)
                .then(function () { return reloadKeepingPlace(group.dataset.listing); })
                .catch(function (err) {
                    group.classList.remove('is-busy');
                    var status = group.querySelector('.image-group-status');
                    if (status) status.textContent = err.message;
                });
        });

        // Redraw from the server, keeping the edited listing where it was on
        // screen and focus on the same control where possible.
        function reloadKeepingPlace(listingId) {
            var group = list.querySelector('[data-listing="' + listingId + '"]');
            var top = group ? group.getBoundingClientRect().top : null;
            return fetch('api/images.php?action=inventory').then(function (r) { return r.json(); }).then(function (data) {
                inventory = data;
                render();
                var again = list.querySelector('[data-listing="' + listingId + '"]');
                if (again && top !== null) window.scrollBy(0, again.getBoundingClientRect().top - top);
                if (again) {
                    var status = again.querySelector('.image-group-status');
                    if (status) status.textContent = '';
                }
            });
        }

        function updateBulk() {
            var n = Object.keys(selected).length;
            bulk.hidden = view !== 'listings' || n === 0;
            bulkCount.textContent = n + ' photo' + (n === 1 ? '' : 's') + ' selected';
            bulkDelete.classList.remove('is-armed');
            bulkDelete.textContent = 'Delete selected';
        }

        document.getElementById('imagesBulkClear').addEventListener('click', function () {
            selected = {};
            render();
        });

        bulkDelete.addEventListener('click', function () {
            var ids = Object.keys(selected);
            if (!ids.length) return;
            if (!bulkDelete.classList.contains('is-armed')) {
                bulkDelete.classList.add('is-armed');
                bulkDelete.textContent = 'Delete ' + ids.length + ' photo' + (ids.length === 1 ? '' : 's') + '?';
                return;
            }
            bulkDelete.disabled = true;
            post({ action: 'bulk_delete', ids: ids })
                .then(function (res) {
                    selected = {};
                    bulkDelete.disabled = false;
                    return load().then(function () {
                        summary.textContent = 'Deleted ' + res.deleted + ' photo' + (res.deleted === 1 ? '' : 's') + '.'
                            + (res.kept.length ? ' Kept the cover of ' + res.kept.length + ' listing' + (res.kept.length === 1 ? '' : 's') + ' that would otherwise have had no photo.' : '')
                            + ' ' + summary.textContent;
                    });
                })
                .catch(function (err) {
                    bulkDelete.disabled = false;
                    bulkCount.textContent = err.message;
                });
        });

        // Thumbnails, a batch per request until none are left. Ones that fail
        // are skipped, so the loop always ends.
        function makeThumbs() {
            var btn = document.getElementById('imagesMakeThumbs');
            var skip = [];
            var made = 0;
            btn.disabled = true;
            (function next() {
                post({ action: 'make_thumbs', skip: skip }).then(function (res) {
                    made += res.made;
                    skip = skip.concat(res.failed);
                    btn.textContent = 'Made ' + made + '; ' + res.remaining + ' to go';
                    if (res.remaining > 0 && (res.made > 0 || res.failed.length > 0)) return next();
                    return load().then(function () {
                        if (skip.length) summary.textContent = skip.length + ' thumbnail(s) could not be made: ' + skip.join(', ') + '. ' + summary.textContent;
                    });
                }).catch(function (err) {
                    btn.disabled = false;
                    btn.textContent = 'Try again';
                    summary.textContent = err.message;
                });
            })();
        }

        // Orphans: files nothing uses, and rows pointing at files that are gone.
        function loadOrphans() {
            orphansView.innerHTML = '<p class="admin-note" role="status">Checking every file against the database and the source code&hellip;</p>';
            fetch('api/images.php?action=orphans').then(function (r) { return r.json(); }).then(renderOrphans).catch(function () {
                orphansView.innerHTML = '<div class="alert alert-error" role="alert">Could not run the check.</div>';
            });
        }

        function renderOrphans(data) {
            var n = data.orphans.length;
            var html = '<h4 class="admin-subhead">Files nothing uses</h4>'
                + '<p class="admin-note">A file is in use when the database stores its name (a listing\'s photos, its card image or its thumbnail), when it is a thumbnail or display copy of one that is, or when the code names it. '
                + (data.protected.length ? data.protected.length + ' file(s) are kept because the code names them: ' + data.protected.map(escapeHtml).join(', ') + '. ' : '')
                + '</p>';
            if (!n) {
                html += '<p class="archive-schema archive-schema-ok"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> No orphans.</p>';
            } else {
                html += '<p>' + n + ' file' + (n === 1 ? '' : 's') + ', ' + formatBytes(data.bytes) + ':</p><ul class="orphan-list">';
                data.orphans.forEach(function (o) {
                    html += '<li><img src="' + escapeHtml(o.url) + '" alt="" loading="lazy" decoding="async"><span><code>' + escapeHtml(o.name) + '</code><br>'
                        + '<span class="semester-meta">' + escapeHtml(o.kind) + ' &middot; ' + formatBytes(o.bytes) + ' &middot; ' + new Date(o.mtime * 1000).toLocaleDateString() + '</span></span></li>';
                });
                html += '</ul><div class="archive-confirm"><label for="orphanConfirm">To delete ' + (n === 1 ? 'this file' : 'these ' + n + ' files') + ', type ' + n + '</label>'
                    + '<input type="text" id="orphanConfirm" inputmode="numeric" autocomplete="off">'
                    + '<div class="archive-confirm-actions"><button type="button" class="btn btn-danger btn-sm" id="orphanDelete" disabled>Delete ' + n + ' file' + (n === 1 ? '' : 's') + '</button></div>'
                    + '<div id="orphanResult" role="status"></div></div>';
            }
            html += '<h4 class="admin-subhead">Photos whose file is missing</h4>';
            if (!data.missing.length) {
                html += '<p class="text-muted">None.</p>';
            } else {
                html += '<div class="table-scroll"><table class="admin-table"><thead><tr><th>Listing</th><th>Stored in</th><th>File</th></tr></thead><tbody>'
                    + data.missing.map(function (m) {
                        return '<tr><td>' + escapeHtml(m.address || 'Deleted listing') + '</td><td><code>' + escapeHtml(m.column) + '</code></td><td><code>' + escapeHtml(m.name) + '</code></td></tr>';
                    }).join('') + '</tbody></table></div>';
            }
            orphansView.innerHTML = html;

            var input = document.getElementById('orphanConfirm');
            var del = document.getElementById('orphanDelete');
            if (!input || !del) return;
            input.addEventListener('input', function () { del.disabled = input.value.trim() !== String(n); });
            del.addEventListener('click', function () {
                del.disabled = true;
                post({ action: 'delete_orphans', names: data.orphans.map(function (o) { return o.name; }), confirm: input.value.trim() })
                    .then(function (res) {
                        var note = 'Deleted ' + res.deleted + ' orphan file' + (res.deleted === 1 ? '' : 's')
                            + (res.kept.length ? '; kept ' + res.kept.length + ' that came into use since the check.' : '.');
                        loadOrphans();
                        // After the redraw, which rewrites the summary line.
                        load().then(function () { summary.textContent = note + ' ' + summary.textContent; });
                    })
                    .catch(function (err) {
                        del.disabled = false;
                        document.getElementById('orphanResult').textContent = err.message;
                    });
            });
        }
    }

    // "820 KB", "12.4 MB": format_bytes() in includes/format.php.
    function formatBytes(bytes) {
        bytes = Number(bytes) || 0;
        if (bytes < 1024) return bytes + ' B';
        var units = ['KB', 'MB', 'GB', 'TB'];
        var v = bytes / 1024;
        var i = 0;
        while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
        return (v >= 100 || i === 0 ? Math.round(v).toLocaleString() : v.toFixed(1)) + ' ' + units[i];
    }

    function initAnnouncementManagement() {
        var msgInput = document.getElementById('announcementMessage');
        var styleSelect = document.getElementById('announcementStyle');
        var saveBtn = document.getElementById('saveAnnouncementBtn');
        var clearBtn = document.getElementById('clearAnnouncementBtn');
        var status = document.getElementById('announcementStatus');
        var previewWrap = document.getElementById('announcementPreview');
        var previewBanner = document.getElementById('announcementPreviewBanner');

        if (!msgInput || !saveBtn) return;

        // Load current announcement
        fetch('api/announcement.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.active && data.message) {
                    msgInput.value = data.message;
                    styleSelect.value = data.style || 'info';
                    status.innerHTML = '<div class="alert alert-info" style="margin-bottom: 1rem;"><i class="fa-solid fa-bullhorn"></i> Active announcement: "' + formatAnnouncement(data.message) + '"</div>';
                    updatePreview();
                }
            })
            .catch(function () {});

        // Live preview
        function updatePreview() {
            var msg = msgInput.value.trim();
            var style = styleSelect.value;
            if (!msg) {
                previewWrap.style.display = 'none';
                return;
            }
            var icons = { info: 'fa-bullhorn', warning: 'fa-triangle-exclamation', success: 'fa-circle-check' };
            previewBanner.className = 'announcement-banner announcement-' + style;
            previewBanner.style.borderRadius = 'var(--radius-sm)';
            previewBanner.innerHTML = '<div class="announcement-inner"><span class="announcement-text"><i class="fa-solid ' + (icons[style] || 'fa-bullhorn') + '"></i> ' + formatAnnouncement(msg) + '</span></div>';
            previewWrap.style.display = 'block';
        }

        msgInput.addEventListener('input', updatePreview);
        styleSelect.addEventListener('change', updatePreview);

        // Save
        saveBtn.addEventListener('click', function () {
            var msg = msgInput.value.trim();
            if (!msg) {
                status.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-exclamation-triangle"></i> Please enter a message.</div>';
                return;
            }

            saveBtn.disabled = true;
            fetch('api/announcement.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=save&message=' + encodeURIComponent(msg) + '&style=' + encodeURIComponent(styleSelect.value)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveBtn.disabled = false;
                if (data.success) {
                    status.innerHTML = '<div class="alert alert-success" style="margin-bottom: 1rem;"><i class="fa-solid fa-check"></i> Announcement published!</div>';
                } else {
                    status.innerHTML = '<div class="alert alert-error" style="margin-bottom: 1rem;"><i class="fa-solid fa-exclamation-triangle"></i> ' + escapeHtml(data.error || 'Failed to save') + '</div>';
                }
            })
            .catch(function () {
                saveBtn.disabled = false;
                status.innerHTML = '<div class="alert alert-error" style="margin-bottom: 1rem;">Network error</div>';
            });
        });

        // Clear
        clearBtn.addEventListener('click', function () {
            if (!confirm('Clear the announcement? It will be removed from all pages.')) return;

            clearBtn.disabled = true;
            fetch('api/announcement.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=clear'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                clearBtn.disabled = false;
                if (data.success) {
                    msgInput.value = '';
                    previewWrap.style.display = 'none';
                    status.innerHTML = '<div class="alert alert-success" style="margin-bottom: 1rem;"><i class="fa-solid fa-check"></i> Announcement cleared.</div>';
                }
            })
            .catch(function () {
                clearBtn.disabled = false;
                status.innerHTML = '<div class="alert alert-error" style="margin-bottom: 1rem;">Network error</div>';
            });
        });
    }

    function initPostManagement() {
        // Delete post
        document.querySelectorAll('.delete-post-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var username = btn.dataset.username;
                if (!confirm('Delete post by ' + username + '?')) return;

                fetch('api/posts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete&id=' + btn.dataset.postId
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        btn.closest('tr').remove();
                        return;
                    }
                    throw new Error(data.error || 'Could not delete it.');
                })
                .catch(function (err) {
                    // It used to fail without a word.
                    var msg = err instanceof SyntaxError ? 'Could not delete it.' : err.message;
                    btn.insertAdjacentHTML('afterend', '<span class="archive-inline-error" role="alert">' + escapeHtml(msg) + '</span>');
                });
            });
        });

        // Open / Paused / Taken, saved as soon as it changes. A failure puts
        // the select back, so it never shows a status that was not saved.
        document.querySelectorAll('.post-status-select').forEach(function (select) {
            var saved = select.value;
            var msg = select.parentNode.querySelector('.post-status-msg');
            select.addEventListener('change', function () {
                var wanted = select.value;
                select.disabled = true;
                if (msg) { msg.className = 'post-status-msg'; msg.textContent = 'Saving…'; }
                fetch('api/posts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=set_status&id=' + encodeURIComponent(select.dataset.postId) + '&status=' + encodeURIComponent(wanted)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.success) throw new Error(data.error || 'Not saved.');
                    saved = wanted;
                    if (msg) msg.textContent = 'Saved';
                })
                .catch(function (err) {
                    select.value = saved;
                    if (msg) {
                        msg.className = 'post-status-msg is-error';
                        msg.textContent = err instanceof SyntaxError ? 'Not saved.' : err.message;
                    }
                })
                .then(function () { select.disabled = false; });
            });
        });

        // A listing's photo count opens it in the Images tab.
        document.querySelectorAll('.show-listing-images').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (showListingImages) showListingImages(parseInt(btn.dataset.postId, 10));
            });
        });
    }

    function initUserManagement() {
        document.querySelectorAll('.delete-user-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var username = btn.dataset.username;
                if (!confirm('Delete all posts by ' + username + '? This cannot be undone.')) return;

                fetch('api/posts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete_user&username=' + encodeURIComponent(username)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        btn.closest('tr').remove();
                    }
                });
            });
        });
    }

    function initEmailComposer() {
        // Recipient type switching
        var radios = document.querySelectorAll('input[name="recipientType"]');
        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.getElementById('semesterRecipientGroup').style.display =
                    radio.value === 'semester' ? 'block' : 'none';
                document.getElementById('individualRecipientGroup').style.display =
                    radio.value === 'individual' ? 'block' : 'none';
            });
        });

        // Send email
        var sendBtn = document.getElementById('sendEmailBtn');
        if (sendBtn) {
            sendBtn.addEventListener('click', function () {
                var type = document.querySelector('input[name="recipientType"]:checked').value;
                var subject = document.getElementById('emailSubject').value.trim();
                var body = document.getElementById('emailBody').value.trim();

                if (!subject || !body) {
                    alert('Subject and message are required');
                    return;
                }

                var formData = 'type=' + type + '&subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);

                if (type === 'semester') {
                    formData += '&semester=' + encodeURIComponent(document.getElementById('emailSemester').value);
                }

                if (type === 'individual') {
                    var selected = [];
                    document.querySelectorAll('input[name="recipients[]"]:checked').forEach(function (cb) {
                        selected.push(cb.value);
                    });
                    if (selected.length === 0) {
                        alert('Select at least one recipient');
                        return;
                    }
                    formData += '&recipients=' + encodeURIComponent(JSON.stringify(selected));
                }

                var status = document.getElementById('emailStatus');
                status.innerHTML = '<span class="spinner"></span> Sending...';
                sendBtn.disabled = true;

                fetch('api/email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    sendBtn.disabled = false;
                    if (data.success) {
                        status.innerHTML = '<div class="alert alert-success"><i class="fa-solid fa-check"></i> Sent to ' + data.sent + ' recipient(s)</div>';
                        document.getElementById('emailSubject').value = '';
                        document.getElementById('emailBody').value = '';
                    } else {
                        status.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-exclamation-triangle"></i> ' + escapeHtml(data.error || 'Failed to send') + '</div>';
                    }
                })
                .catch(function () {
                    sendBtn.disabled = false;
                    status.innerHTML = '<div class="alert alert-error">Network error</div>';
                });
            });
        }
    }

    /* ======================================================================
       Broken Image Fallback
       ====================================================================== */
    function handleBrokenImage(img) {
        if (img.dataset.broken) return;
        img.dataset.broken = '1';
        img.style.display = 'none';
        if (img.parentNode.querySelector('.img-broken-placeholder')) return;
        var placeholder = document.createElement('div');
        placeholder.className = 'img-broken-placeholder';
        placeholder.innerHTML = '<i class="fa-solid fa-image"></i><span>Image not available</span>';
        img.parentNode.appendChild(placeholder);
    }

    // Replacing an image with the placeholder is a one-way door: handleBrokenImage()
    // hides the element, and a hidden image never intersects the viewport, so a
    // loading="lazy" image that had not fetched yet now never will. That makes it
    // the wrong first response to a failure, because a failure here is often
    // transient -- a dropped connection, or a request that came back as something
    // other than image bytes. Retry once, then give up.
    function imageFailed(img) {
        if (img.dataset.imgRetried) {
            handleBrokenImage(img);
            return;
        }
        img.dataset.imgRetried = '1';
        delete img.dataset.imgError;

        var src = img.getAttribute('src');
        if (!src) {
            handleBrokenImage(img);
            return;
        }
        // Re-assigning the identical string is a no-op, and the failed response
        // may itself be sitting in the HTTP cache, so the retry needs a URL the
        // browser treats as new.
        var retried = src.split('#')[0];
        retried += (retried.indexOf('?') === -1 ? '?' : '&') + '_retry=' + Date.now();
        // Only if the element still wants this image: the gallery reuses one
        // <img>, and a swipe in the meantime must not get the old photo back.
        setTimeout(function () { if (img.getAttribute('src') === src) img.src = retried; }, 150);
    }

    // Attach to the images present on page load. Card images are handled on
    // the grid instead (initIndex), because live filtering replaces them.
    document.querySelectorAll('.modal-gallery img:not(.gallery-under), .map-popup img').forEach(function (img) {
        img.addEventListener('error', function () { imageFailed(img); });
        // A load that failed before this file ran fires no event left to catch.
        // The markup records those with onerror="this.dataset.imgError='1'" -- an
        // inline attribute is in place from parse time, so it cannot miss one.
        // Deliberately not inferred from `complete && naturalWidth === 0`: a lazy
        // image whose request has not been issued reports exactly that state too,
        // and treating it as a failure is what blanked cards that were merely
        // still waiting to load.
        if (img.dataset.imgError) {
            imageFailed(img);
        }
    });

    // #modalImage sits inside .modal-gallery, so the loop above already covers
    // it. It used to get a second listener here as well, and on one error the
    // second call found the first one's retry flag and showed "Image not
    // available" before the retry could load.

    /* ======================================================================
       Place / Roommate Helpers
       ====================================================================== */
    // Flags arrive as "1" from data-* attributes and as 1 from JSON.
    function isFlagSet(v) {
        return v === '1' || v === 1 || v === true;
    }

    function buildPlaceHtml(data) {
        var items = [];

        if (data.sizeSummary) {
            items.push('<span class="modal-place-item"><i class="fa-solid fa-bed"></i> ' + escapeHtml(data.sizeSummary) + '</span>');
        }
        if (data.roommateGender) {
            items.push('<span class="modal-place-item"><i class="fa-solid fa-users"></i> Currently: ' + escapeHtml(data.roommateGender) + '</span>');
        }
        if (data.roommatePreference) {
            items.push('<span class="modal-place-item modal-place-pref"><i class="fa-solid fa-user-group"></i> Hoping to sublet to: ' + escapeHtml(data.roommatePreference) + '</span>');
        }

        if (!items.length) return '';
        return '<div class="modal-place">' + items.join('') + '</div>';
    }

    /* ======================================================================
       Utilities / Amenities Helpers
       ====================================================================== */
    function buildUtilitiesHtml(data) {
        var html = '';
        var hasUtilities = false;
        var hasAmenities = false;

        // Check if any utility data exists
        var utilities = [
            { key: 'electric', label: 'Electric', icon: 'fa-bolt' },
            { key: 'gas', label: 'Gas', icon: 'fa-fire-flame-simple' },
            { key: 'water', label: 'Water', icon: 'fa-droplet' },
            { key: 'internet', label: 'Internet', icon: 'fa-wifi' }
        ];

        var utilItems = [];
        utilities.forEach(function (u) {
            var v = data['utility_' + u.key] || '';
            if (!v) return;
            hasUtilities = true;
            var paidBy = v === 'landlord' ? 'Included' : 'Tenant pays';
            var cls = v === 'landlord' ? 'paid-landlord' : 'paid-tenant';
            utilItems.push('<span class="modal-utility-item ' + cls + '"><i class="fa-solid ' + u.icon + '"></i> ' + escapeHtml(u.label) + ': ' + escapeHtml(paidBy) + '</span>');
        });

        // Amenities
        var amenities = [
            { key: 'free_parking', label: 'Free Parking', icon: 'fa-square-parking' },
            { key: 'paid_parking', label: 'Paid Parking', icon: 'fa-square-parking' },
            { key: 'laundry_free', label: 'In-Unit Laundry (Free)', icon: 'fa-shirt' },
            { key: 'laundry_paid', label: 'In-Unit Laundry (Paid)', icon: 'fa-shirt' },
            { key: 'dishwasher', label: 'Dishwasher', icon: 'fa-sink' },
            { key: 'air_conditioning', label: 'A/C', icon: 'fa-snowflake' },
            { key: 'pets_allowed', label: 'Pets Allowed', icon: 'fa-paw' },
            { key: 'furnished', label: 'Furnished', icon: 'fa-couch' }
        ];

        var amenityItems = [];
        amenities.forEach(function (a) {
            var v = data['amenity_' + a.key] || '';
            if (v === '1' || v === 1 || v === true) {
                hasAmenities = true;
                amenityItems.push('<span class="modal-amenity-item"><i class="fa-solid ' + a.icon + '"></i> ' + escapeHtml(a.label) + '</span>');
            }
        });

        if (!hasUtilities && !hasAmenities && !data.utility_cost) return '';

        html += '<div class="modal-utilities">';

        if (hasUtilities) {
            html += '<h4>Utilities</h4>';
            html += '<div class="modal-utilities-grid">' + utilItems.join('') + '</div>';
        }

        if (data.utility_cost && parseFloat(data.utility_cost) > 0) {
            html += '<p class="modal-utility-cost"><i class="fa-solid fa-receipt"></i> Est. monthly utilities: <strong>$' + Number(data.utility_cost).toLocaleString() + '</strong></p>';
        }

        if (hasAmenities) {
            html += '<h4 style="margin-top: 0.75rem;">Amenities</h4>';
            html += '<div class="modal-amenities-list">' + amenityItems.join('') + '</div>';
        }

        html += '</div>';
        return html;
    }

    /* ======================================================================
       Utilities
       ====================================================================== */
    // Safe for both element content and quoted attribute values. The old
    // textContent -> innerHTML trick escaped < > &, but NOT quotes, and the
    // result is interpolated into data-copy="..." and src="..." attributes —
    // so a quote in an address, email, or phone number could break out of the
    // attribute and inject markup.
    function escapeHtml(text) {
        return String(text === null || text === undefined ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Escape HTML, convert newlines to <br>, and auto-link URLs
    function formatAnnouncement(text) {
        var safe = escapeHtml(text);
        safe = safe.replace(/\n/g, '<br>');
        safe = safe.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" style="color: inherit; text-decoration: underline;">$1</a>');
        return safe;
    }
});
