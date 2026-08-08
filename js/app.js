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
    const TILE_URL = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
    const TILE_OPTS = {
        attribution: '© OpenStreetMap © CARTO',
        subdomains: 'abcd',
        maxZoom: 20,
        detectRetina: true
    };

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

    /* ======================================================================
       Navigation
       ====================================================================== */
    function initNav() {
        const toggle = document.getElementById('navToggle');
        const menu = document.getElementById('navMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function () {
            menu.classList.toggle('open');
        });

        // Close menu on link click (mobile)
        menu.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                menu.classList.remove('open');
            });
        });
    }

    /* ======================================================================
       Filters (noUiSlider)
       ====================================================================== */
    function initFilters() {
        const config = window.SUBLET_CONFIG;

        // There is no Apply button any more, so auto-apply is the only way to
        // filter — it must be bound even if the sliders fail to build. They
        // depend on noUiSlider, which comes from a CDN; without this guard a
        // blocked CDN would leave the whole filter bar inert.
        try {
            if (config && typeof noUiSlider !== 'undefined') {
                buildSliders(config);
            }
        } catch (e) {
            /* checkboxes and the semester select still work */
        }

        initAutoApply();
    }

    function buildSliders(config) {
        // Price slider
        const priceEl = document.getElementById('priceSlider');
        if (priceEl) {
            noUiSlider.create(priceEl, {
                start: [config.initialMinPrice, config.initialMaxPrice],
                connect: true,
                step: 50,
                range: { min: 0, max: config.maxPrice },
                format: {
                    to: function (v) { return '$' + Math.round(v); },
                    from: function (v) { return Number(v.replace('$', '')); }
                }
            });

            priceEl.noUiSlider.on('update', function (values) {
                document.getElementById('priceValue').textContent = values[0] + ' \u2013 ' + values[1];
                document.getElementById('minPrice').value = Math.round(parseFloat(values[0].replace('$', '')));
                document.getElementById('maxPrice').value = Math.round(parseFloat(values[1].replace('$', '')));
            });
        }

        // Distance slider
        var distEl = document.getElementById('distanceSlider');
        if (distEl) {
            noUiSlider.create(distEl, {
                start: [config.initialDistance],
                connect: [true, false],
                step: 0.5,
                range: { min: 0.5, max: config.maxDistance },
                format: {
                    to: function (v) { return v.toFixed(1); },
                    from: function (v) { return parseFloat(v); }
                }
            });

            distEl.noUiSlider.on('update', function (values) {
                document.getElementById('distanceValue').textContent = '< ' + values[0] + ' mi';
                document.getElementById('maxDistance').value = parseFloat(values[0]);
            });
        }
    }

    /* Apply filters as they change, instead of making people find the button.
       The form still submits normally — the server stays the single source of
       truth for what matches, the URL stays shareable, and the no-JS path is
       untouched (the Apply button is only hidden once this runs). */
    function initAutoApply() {
        var form = document.getElementById('filterForm');
        if (!form) return;

        var timer = null;
        var submitted = false;

        function apply() {
            if (submitted) return;
            clearTimeout(timer);
            // Enough of a pause to collect a burst of chip clicks into one
            // navigation, short enough that a single click still feels direct.
            timer = setTimeout(function () {
                submitted = true;
                document.body.classList.add('filters-applying');
                try {
                    sessionStorage.setItem('filterScroll', String(window.scrollY));
                } catch (e) { /* private mode — losing scroll position is fine */ }
                form.submit();
            }, 350);
        }

        form.querySelectorAll('input[type="checkbox"], select').forEach(function (el) {
            el.addEventListener('change', apply);
        });

        // noUiSlider fires 'change' once on release, unlike the continuous
        // 'update' the readouts above use — one navigation per drag, not one
        // per pixel.
        ['priceSlider', 'distanceSlider'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.noUiSlider) el.noUiSlider.on('change', apply);
        });

        // A reload would otherwise drop the user back at the top of the page.
        try {
            var y = sessionStorage.getItem('filterScroll');
            if (y !== null) {
                sessionStorage.removeItem('filterScroll');
                window.scrollTo(0, parseInt(y, 10) || 0);
            }
        } catch (e) { /* nothing to restore */ }
    }

    /* ======================================================================
       Modal
       ====================================================================== */
    var modalImages = [];
    var modalIndex = 0;
    var currentPostId = null;
    var lastFocused = null;

    function initModal() {
        var overlay = document.getElementById('modal');
        if (!overlay) return;

        document.getElementById('modalClose').addEventListener('click', closeModal);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (!overlay.classList.contains('open')) return;
            if (e.key === 'Escape') closeModal();
            if (e.key === 'ArrowLeft') navigateGallery(-1);
            if (e.key === 'ArrowRight') navigateGallery(1);
        });

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

        // Log the contact action
        fetch('api/contact_log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            // No poster_username: the endpoint reads it from the post itself so
            // the log cannot be attributed to someone who never posted.
            body: 'post_id=' + encodeURIComponent(data.id) + '&contact_type=' + encodeURIComponent(type)
        }).catch(function () {});

        if (type === 'email') {
            var email = data.contactEmail || (data.username + '@uvm.edu');
            var subject = 'Interested in Your Sublet Posting';
            // Address the poster by the name they chose, and sign with the
            // sender's own — both fall back to the NetID.
            var greetName = data.posterName || data.username;
            var signName = document.body.dataset.userName || currentUser;
            var draftBody = 'Hi ' + greetName + ',\n\nI\'m interested in your sublet at ' + data.address + '. Could you send me more details?\n\nThanks,\n' + signName;
            var mailtoUrl = 'mailto:' + encodeURIComponent(email) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(draftBody);

            title.textContent = 'Send Email';
            body.innerHTML =
                '<div class="contact-field">' +
                    '<label>To</label>' +
                    '<div class="contact-value-row">' +
                        '<span class="contact-value">' + escapeHtml(email) + '</span>' +
                        '<button class="btn btn-secondary btn-sm contact-copy" data-copy="' + escapeHtml(email) + '"><i class="fa-solid fa-copy"></i> Copy</button>' +
                    '</div>' +
                '</div>' +
                '<div class="contact-field">' +
                    '<label>Subject</label>' +
                    '<span class="contact-value">' + escapeHtml(subject) + '</span>' +
                '</div>' +
                '<div class="contact-field">' +
                    '<label>Draft Message</label>' +
                    '<div class="contact-draft">' + escapeHtml(draftBody) + '</div>' +
                '</div>' +
                '<div class="contact-actions">' +
                    '<a href="' + mailtoUrl + '" class="btn btn-primary"><i class="fa-solid fa-envelope"></i> Open Email Client</a>' +
                    '<button class="btn btn-secondary contact-copy" data-copy="' + escapeHtml(draftBody) + '"><i class="fa-solid fa-copy"></i> Copy Message</button>' +
                '</div>';
        } else if (type === 'phone') {
            var phone = data.contactPhone;
            title.textContent = 'Call or Text';
            body.innerHTML =
                '<div class="contact-field">' +
                    '<label>Phone Number</label>' +
                    '<div class="contact-value-row">' +
                        '<span class="contact-value" style="font-size: 1.2rem; font-weight: 600;">' + escapeHtml(phone) + '</span>' +
                        '<button class="btn btn-secondary btn-sm contact-copy" data-copy="' + escapeHtml(phone) + '"><i class="fa-solid fa-copy"></i> Copy</button>' +
                    '</div>' +
                '</div>' +
                '<div class="contact-actions">' +
                    '<a href="tel:' + encodeURIComponent(phone) + '" class="btn btn-primary"><i class="fa-solid fa-phone"></i> Call</a>' +
                    '<a href="sms:' + encodeURIComponent(phone) + '" class="btn btn-secondary"><i class="fa-solid fa-message"></i> Text</a>' +
                '</div>';
        }

        // Attach copy handlers
        body.querySelectorAll('.contact-copy').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.dataset.copy;
                navigator.clipboard.writeText(text).then(function () {
                    var original = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                    btn.classList.add('copied');
                    setTimeout(function () {
                        btn.innerHTML = original;
                        btn.classList.remove('copied');
                    }, 1500);
                });
            });
        });

        details.classList.add('contact-open');
        var container = details.closest('.modal-container');
        if (container) container.classList.add('contact-active');
    }

    function openModal(data) {
        currentPostId = data.id;

        var priceEl = document.getElementById('modalPrice');
        priceEl.textContent = '$' + Number(data.price).toLocaleString();
        if (isFlagSet(data.negotiable)) {
            var neg = document.createElement('small');
            neg.className = 'modal-price-neg';
            neg.textContent = 'or best offer';
            priceEl.appendChild(neg);
        }

        document.getElementById('modalAddress').textContent = data.address;
        document.getElementById('modalSemester').textContent = data.semesterName || data.semester;
        document.getElementById('modalDescription').textContent = data.description || 'No description provided.';

        // Bedrooms / bathrooms / roommates. Absent on listings that predate
        // those fields, and on the demo site, so the block is only built when
        // there is something to put in it.
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

        document.getElementById('modalPoster').textContent = 'Posted by ' + (data.posterName || data.username);

        // Email button
        var emailBtn = document.getElementById('modalEmailBtn');
        if (emailBtn) {
            if (currentUser === data.username) {
                emailBtn.style.display = 'none';
            } else {
                emailBtn.style.display = '';
                emailBtn.onclick = function () { showContactPopup('email', data); };
            }
        }

        // Phone button
        var phoneBtn = document.getElementById('modalPhoneBtn');
        if (phoneBtn) {
            if (currentUser === data.username || !data.contactPhone) {
                phoneBtn.style.display = 'none';
            } else {
                phoneBtn.style.display = '';
                phoneBtn.onclick = function () { showContactPopup('phone', data); };
            }
        }

        // Edit button
        var editBtn = document.getElementById('modalEdit');
        if (editBtn) {
            editBtn.style.display = (currentUser === data.username) ? '' : 'none';
        }

        // Load images
        modalImages = [data.image_url || data.imageUrl];
        modalIndex = 0;
        renderGallery();

        // Fetch all images
        fetch('api/images.php?sublet_id=' + data.id)
            .then(function (r) { return r.json(); })
            .then(function (images) {
                if (Array.isArray(images) && images.length > 0) {
                    modalImages = images.map(function (img) { return img.image_url; });
                    modalIndex = 0;
                    renderGallery();
                }
            })
            .catch(function () {});

        var overlay = document.getElementById('modal');
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        // Remember where focus came from so Escape returns the user to the card
        // they opened, rather than dumping them at the top of the document.
        lastFocused = document.activeElement;
        var closeBtn = document.getElementById('modalClose');
        if (closeBtn) closeBtn.focus();
    }

    function closeModal() {
        var overlay = document.getElementById('modal');
        if (overlay) {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
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

    function renderGallery() {
        var img = document.getElementById('modalImage');
        if (img && modalImages.length > 0) {
            // Reset broken state
            img.style.display = '';
            delete img.dataset.broken;
            var oldPlaceholder = img.parentNode.querySelector('.img-broken-placeholder');
            if (oldPlaceholder) oldPlaceholder.remove();
            img.src = modalImages[modalIndex];
        }

        // Navigation arrows
        var prevBtn = document.getElementById('galleryPrev');
        var nextBtn = document.getElementById('galleryNext');
        if (prevBtn) prevBtn.style.display = modalIndex > 0 ? '' : 'none';
        if (nextBtn) nextBtn.style.display = modalIndex < modalImages.length - 1 ? '' : 'none';

        // Dots
        var dotsContainer = document.getElementById('galleryDots');
        if (dotsContainer) {
            dotsContainer.innerHTML = '';
            if (modalImages.length > 1) {
                for (var i = 0; i < modalImages.length; i++) {
                    var dot = document.createElement('button');
                    dot.className = 'gallery-dot' + (i === modalIndex ? ' active' : '');
                    dot.dataset.index = i;
                    dot.addEventListener('click', function () {
                        modalIndex = parseInt(this.dataset.index);
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

    // Expose for map popups
    window.openSubletModal = function (data) {
        openModal(data);
    };

    /* ======================================================================
       Index Page — Listing Cards
       ====================================================================== */
    function initIndex() {
        document.querySelectorAll('.listing-card').forEach(function (card) {
            // The card carries role="button", so it has to answer Enter and
            // Space the way a real button would.
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault();
                    card.click();
                }
            });

            card.addEventListener('click', function () {
                var imgEl = card.querySelector('.card-image img');
                openModal({
                    id: card.dataset.id,
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
                    negotiable: card.dataset.negotiable || '0',
                    sizeSummary: card.dataset.sizeSummary || '',
                    roommateGender: card.dataset.roommateGender || '',
                    roommatePreference: card.dataset.roommatePreference || ''
                });
            });
        });

        // Instant client-side sorting. The hidden `sort` field on the filter
        // form is kept in step so that applying a filter — which reloads the
        // page — comes back sorted the same way rather than snapping to Newest.
        var sortSelect = document.getElementById('sortFilter');
        var sortInput = document.getElementById('sortInput');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                var sortVal = sortSelect.value;
                if (sortInput) sortInput.value = sortVal;

                var grid = document.querySelector('.listings-grid');
                if (!grid) return;
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
                var targets = document.querySelectorAll(
                    '.map-popup img[data-sublet-id="' + sublet.id + '"], ' +
                    '.map-popup .popup-btn[data-sublet-id="' + sublet.id + '"]'
                );
                targets.forEach(function (el) {
                    el.addEventListener('click', function () {
                        openModal({
                            id: sublet.id,
                            price: sublet.price,
                            address: sublet.address,
                            semester: sublet.semester,
                            semesterName: sublet.semester_name,
                            description: sublet.description,
                            username: sublet.username,
                            contactEmail: sublet.contact_email,
                            contactPhone: sublet.contact_phone,
                            image_url: sublet.image_url,
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
                            negotiable: String(sublet.price_negotiable || 0),
                            // Labelled server-side in map.php — the vocabulary
                            // lives in includes/listing_fields.php, not here.
                            sizeSummary: sublet.size_summary || '',
                            roommateGender: sublet.roommate_gender_label || '',
                            roommatePreference: sublet.roommate_preference_label || ''
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
        initAddressAutocomplete();
        initPostMap();
        initImageUpload();
        initExistingImageDelete();
        initRoommateFields();
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

    // ---- Nominatim Address Autocomplete ----
    function initAddressAutocomplete() {
        var input = document.getElementById('address');
        var results = document.getElementById('addressResults');
        if (!input || !results) return;

        var debounceTimer = null;
        var highlightedIndex = -1;

        // lat/lon are only ever set by picking a suggestion. Typing over the
        // address without picking again used to leave the old coordinates
        // attached to the new text, so the listing mapped to the previous
        // place. Track the last address the coordinates actually belong to and
        // block submission while the two disagree.
        var acceptedAddress = input.value.trim();

        function syncAddressValidity() {
            if (input.value.trim() === acceptedAddress) {
                input.setCustomValidity('');
            } else {
                input.setCustomValidity('Choose an address from the dropdown suggestions so your listing lands in the right place on the map.');
            }
        }
        syncAddressValidity();

        input.addEventListener('input', function () {
            syncAddressValidity();
            clearTimeout(debounceTimer);
            var query = input.value.trim();
            if (query.length < 3) {
                results.classList.remove('open');
                return;
            }
            debounceTimer = setTimeout(function () {
                fetch('api/geocode.php?q=' + encodeURIComponent(query))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        renderAutocomplete(data);
                    })
                    .catch(function () {
                        results.classList.remove('open');
                    });
            }, 350);
        });

        input.addEventListener('keydown', function (e) {
            var items = results.querySelectorAll('.autocomplete-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
                updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = Math.max(highlightedIndex - 1, 0);
                updateHighlight(items);
            } else if (e.key === 'Enter' && highlightedIndex >= 0) {
                e.preventDefault();
                items[highlightedIndex].click();
            }
        });

        function updateHighlight(items) {
            items.forEach(function (item, i) {
                item.classList.toggle('highlighted', i === highlightedIndex);
            });
        }

        function renderAutocomplete(data) {
            results.innerHTML = '';
            highlightedIndex = -1;

            if (!data.length) {
                results.classList.remove('open');
                return;
            }

            data.forEach(function (item) {
                // short_name is the same shortening the cards and map popups
                // apply, so what gets picked here is what everyone else sees.
                var label = item.short_name || item.display_name;
                var div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + escapeHtml(label);
                div.addEventListener('click', function () {
                    input.value = label;
                    acceptedAddress = label.trim();
                    syncAddressValidity();
                    document.getElementById('lat').value = item.lat;
                    document.getElementById('lon').value = item.lon;
                    results.classList.remove('open');
                    updatePostMapMarker(parseFloat(item.lat), parseFloat(item.lon));
                });
                results.appendChild(div);
            });

            results.classList.add('open');
        }

        // Close on click outside
        document.addEventListener('click', function (e) {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.classList.remove('open');
            }
        });
    }

    // ---- Post Map Preview ----
    function initPostMap() {
        var mapEl = document.getElementById('postMap');
        if (!mapEl || typeof L === 'undefined') return;

        var uvmIcon = createUvmIcon();
        var config = window.POST_CONFIG || {};
        var lat = config.lat || CAMPUS.lat;
        var lon = config.lon || CAMPUS.lon;

        window._postMap = L.map('postMap', { zoomControl: false }).setView([lat, lon], 15);
        L.tileLayer(TILE_URL, TILE_OPTS).addTo(window._postMap);
        L.control.zoom({ position: 'topright' }).addTo(window._postMap);

        // Same campus reference as the main map \u2014 useful here because the form
        // rejects anything more than 50 miles from it.
        L.circleMarker([CAMPUS.lat, CAMPUS.lon], {
            radius: 7,
            color: '#ffffff',
            weight: 2,
            fillColor: '#00313C',
            fillOpacity: 1
        }).addTo(window._postMap).bindTooltip('UVM campus', { direction: 'top', offset: [0, -6] });

        window._postMarker = L.marker([lat, lon], { icon: uvmIcon }).addTo(window._postMap);

        setTimeout(function () { window._postMap.invalidateSize(); }, 200);
    }

    function updatePostMapMarker(lat, lon) {
        if (!window._postMap || !window._postMarker) return;
        var latlng = L.latLng(lat, lon);
        window._postMarker.setLatLng(latlng);
        window._postMap.setView(latlng, 16, { animate: true });
        setTimeout(function () { window._postMap.invalidateSize(); }, 50);
    }

    // ---- Drag & Drop Image Upload ----
    function initImageUpload() {
        var dropZone = document.getElementById('dropZone');
        var fileInput = document.getElementById('imageInput');
        var previewContainer = document.getElementById('imagePreviews');
        if (!dropZone || !fileInput) return;

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
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                showNewPreviews(fileInput.files);
            }
        });

        fileInput.addEventListener('change', function () {
            showNewPreviews(fileInput.files);
        });

        function showNewPreviews(files) {
            // Remove old "new" previews
            previewContainer.querySelectorAll('.new-preview').forEach(function (el) { el.remove(); });

            Array.from(files).forEach(function (file, i) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var div = document.createElement('div');
                    div.className = 'image-preview new-preview';
                    var existingCount = previewContainer.querySelectorAll('.image-preview:not(.new-preview)').length;
                    if (existingCount === 0 && i === 0) {
                        div.classList.add('is-thumbnail');
                        div.innerHTML = '<img src="' + e.target.result + '" alt="Preview">' +
                            '<span class="thumbnail-badge">Thumbnail</span>';
                    } else {
                        div.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                    }
                    previewContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }
    }

    // ---- Delete Existing Images (edit mode) ----
    function initExistingImageDelete() {
        document.querySelectorAll('.remove-image').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var imageId = btn.dataset.imageId;
                if (!confirm('Delete this image?')) return;

                fetch('api/images.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_method=DELETE&id=' + imageId
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        btn.closest('.image-preview').remove();
                    } else {
                        alert(data.error || 'Failed to delete image');
                    }
                });
            });
        });
    }

    /* ======================================================================
       Admin Dashboard
       ====================================================================== */
    function initAdmin() {
        initAdminTabs();
        initSemesterManagement();
        initAnnouncementManagement();
        initPostManagement();
        initUserManagement();
        initEmailComposer();
        initContactLog();
    }

    function initAdminTabs() {
        document.querySelectorAll('.admin-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.admin-tab').forEach(function (t) { t.classList.remove('active'); });
                document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
                tab.classList.add('active');
                var panel = document.getElementById('tab-' + tab.dataset.tab);
                if (panel) panel.classList.add('active');
            });
        });
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
                    }
                });
            });
        });

        // Manage images
        document.querySelectorAll('.manage-images-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var postId = btn.dataset.postId;
                var modal = document.getElementById('imageModal');
                var grid = document.getElementById('adminImageGrid');

                grid.innerHTML = '<div class="spinner"></div>';
                modal.classList.add('open');
                document.body.style.overflow = 'hidden';

                fetch('api/images.php?sublet_id=' + postId)
                    .then(function (r) { return r.json(); })
                    .then(function (images) {
                        grid.innerHTML = '';
                        if (images.length === 0) {
                            grid.innerHTML = '<p class="text-muted">No images</p>';
                            return;
                        }
                        images.forEach(function (img) {
                            var div = document.createElement('div');
                            div.className = 'admin-image';
                            div.innerHTML = '<img src="' + escapeHtml(img.image_url) + '" alt="Image">' +
                                '<button class="delete-image-btn" data-image-id="' + img.id + '"><i class="fa-solid fa-trash"></i></button>';
                            div.querySelector('.delete-image-btn').addEventListener('click', function () {
                                if (!confirm('Delete this image?')) return;
                                fetch('api/images.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: '_method=DELETE&id=' + img.id
                                })
                                .then(function (r) { return r.json(); })
                                .then(function (data) {
                                    // Surface the reason: the endpoint refuses
                                    // to remove a listing's last photo, and
                                    // silently doing nothing looked like a bug.
                                    if (data.success) div.remove();
                                    else alert(data.error || 'Failed to delete image');
                                });
                            });
                            grid.appendChild(div);
                        });
                    });
            });
        });

        // Close image modal
        var imageModalClose = document.getElementById('imageModalClose');
        if (imageModalClose) {
            imageModalClose.addEventListener('click', function () {
                document.getElementById('imageModal').classList.remove('open');
                document.body.style.overflow = '';
            });
        }

        var imageModal = document.getElementById('imageModal');
        if (imageModal) {
            imageModal.addEventListener('click', function (e) {
                if (e.target === imageModal) {
                    imageModal.classList.remove('open');
                    document.body.style.overflow = '';
                }
            });
        }
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

    function initContactLog() {
        var container = document.getElementById('contactLogContent');
        if (!container) return;

        function loadLogs(page) {
            fetch('api/contact_log.php?page=' + (page || 1))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.logs || data.logs.length === 0) {
                        container.innerHTML = '<p class="text-muted">No contact events logged yet.</p>';
                        return;
                    }
                    var html = '<table class="admin-table"><thead><tr>' +
                        '<th>Date</th><th>Contacted By</th><th>Poster</th><th>Address</th><th>Type</th>' +
                        '</tr></thead><tbody>';
                    data.logs.forEach(function (log) {
                        var date = new Date(log.created_at);
                        var dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
                            ' ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                        var typeIcon = log.contact_type === 'email' ? '<i class="fa-solid fa-envelope"></i>' : '<i class="fa-solid fa-phone"></i>';
                        html += '<tr>' +
                            '<td>' + escapeHtml(dateStr) + '</td>' +
                            '<td>' + escapeHtml(log.contacted_by) + '</td>' +
                            '<td>' + escapeHtml(log.poster_username) + '</td>' +
                            '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(log.address || '(deleted)') + '</td>' +
                            '<td>' + typeIcon + ' ' + escapeHtml(log.contact_type) + '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';
                    container.innerHTML = html;

                    // Pagination. Cleared unconditionally: leaving the old
                    // buttons up when a reload drops to a single page left
                    // controls pointing at pages that no longer exist.
                    var pagDiv = document.getElementById('contactLogPagination');
                    if (pagDiv) pagDiv.innerHTML = '';
                    if (pagDiv && data.pages > 1) {
                        for (var i = 1; i <= data.pages; i++) {
                            var btn = document.createElement('button');
                            btn.className = 'btn btn-sm ' + (i === data.page ? 'btn-primary' : 'btn-secondary');
                            btn.textContent = i;
                            btn.dataset.page = i;
                            btn.addEventListener('click', function () {
                                loadLogs(parseInt(this.dataset.page));
                            });
                            pagDiv.appendChild(btn);
                        }
                    }
                })
                .catch(function () {
                    container.innerHTML = '<p class="text-muted">Failed to load contact logs.</p>';
                });
        }

        // Load on tab click
        document.querySelectorAll('.admin-tab').forEach(function (tab) {
            if (tab.dataset.tab === 'contact-log') {
                tab.addEventListener('click', function () { loadLogs(1); });
            }
        });
    }

    /* ======================================================================
       Broken Image Fallback
       ====================================================================== */
    function handleBrokenImage(img) {
        if (img.dataset.broken) return;
        img.dataset.broken = '1';
        img.style.display = 'none';
        // The card markup carries its own inline onerror=, which fires before
        // this file has loaded and appends a placeholder of its own. Don't
        // stack a second one underneath it.
        if (img.parentNode.querySelector('.img-broken-placeholder')) return;
        var placeholder = document.createElement('div');
        placeholder.className = 'img-broken-placeholder';
        placeholder.innerHTML = '<i class="fa-solid fa-image"></i><span>Image not available</span>';
        img.parentNode.appendChild(placeholder);
    }

    // Attach to all images on page load
    document.querySelectorAll('.card-image img, .modal-gallery img, .map-popup img').forEach(function (img) {
        img.addEventListener('error', function () { handleBrokenImage(img); });
        // A load that already failed before this script ran fires no event we
        // can still catch, so test for that state directly. `complete` alone
        // does not mean "finished": a loading="lazy" image whose request has
        // not been issued yet also reports complete with naturalWidth 0.
        // currentSrc is what separates the two -- it stays empty until the
        // browser actually picks a URL and requests it. Without that guard the
        // newest cards (the only ones not already in the HTTP cache) get
        // declared broken here, and hiding an image stops it ever intersecting
        // the viewport, so its lazy load never fires and it stays broken until
        // a reload warms the cache.
        if (img.complete && img.naturalWidth === 0 && img.src && img.currentSrc) {
            handleBrokenImage(img);
        }
    });

    // Also handle modal image errors
    var modalImg = document.getElementById('modalImage');
    if (modalImg) {
        modalImg.addEventListener('error', function () { handleBrokenImage(modalImg); });
    }

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
