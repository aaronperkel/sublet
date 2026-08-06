/* ==========================================================================
   UVM Sublets — Client-Side Application
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    const page = document.body.dataset.page;
    const currentUser = document.body.dataset.user || 'Guest';
    const isAdmin = document.body.dataset.admin === '1';

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
        if (!config) return;

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

    /* ======================================================================
       Modal
       ====================================================================== */
    var modalImages = [];
    var modalIndex = 0;
    var currentPostId = null;

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
            body: 'post_id=' + encodeURIComponent(data.id) + '&contact_type=' + encodeURIComponent(type) + '&poster_username=' + encodeURIComponent(data.username)
        }).catch(function () {});

        if (type === 'email') {
            var email = data.contactEmail || (data.username + '@uvm.edu');
            var subject = 'Interested in Your Sublet Posting';
            var draftBody = 'Hello!\n\nI\'m interested in your sublet at ' + data.address + '. Could you send me more details?\n\nThanks,\n' + currentUser;
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
        document.getElementById('modalPrice').textContent = '$' + Number(data.price).toLocaleString();
        document.getElementById('modalAddress').textContent = data.address;
        document.getElementById('modalSemester').textContent = data.semesterName || data.semester;
        document.getElementById('modalDescription').textContent = data.description || 'No description provided.';

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

        document.getElementById('modalPoster').textContent = 'Posted by ' + data.username;

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
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        var overlay = document.getElementById('modal');
        if (overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
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
                    amenity_furnished: card.dataset.amenityFurnished || '0'
                });
            });
        });

        // Instant client-side sorting
        var sortSelect = document.getElementById('sortFilter');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                var grid = document.querySelector('.listings-grid');
                if (!grid) return;
                var cards = Array.from(grid.querySelectorAll('.listing-card'));
                var sortVal = sortSelect.value;

                cards.sort(function (a, b) {
                    switch (sortVal) {
                        case 'price_asc':
                            return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                        case 'price_desc':
                            return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
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

        var map = L.map('mainMap').setView([44.477435, -73.195323], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '\u00a9 OpenStreetMap contributors'
        }).addTo(map);

        var sublets = window.MAP_SUBLETS || [];
        if (sublets.length === 0) return;

        var bounds = L.latLngBounds();

        sublets.forEach(function (sublet) {
            var marker = L.marker([sublet.lat, sublet.lon], { icon: uvmIcon }).addTo(map);
            bounds.extend(marker.getLatLng());

            var popupThumb = sublet.thumbnail_url || sublet.image_url;
            var popupHtml = '<div class="map-popup">' +
                '<img src="' + escapeHtml(popupThumb) + '" alt="Sublet" data-sublet-id="' + sublet.id + '" onerror="this.style.display=\'none\'">' +
                '<div class="popup-price">$' + Number(sublet.price).toLocaleString() + '</div>' +
                '<div class="popup-address">' + escapeHtml(sublet.address) + '</div>' +
                '</div>';

            marker.bindPopup(popupHtml);

            marker.on('popupopen', function () {
                var popupImg = document.querySelector('.map-popup img[data-sublet-id="' + sublet.id + '"]');
                if (popupImg) {
                    popupImg.addEventListener('click', function () {
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
                            amenity_furnished: String(sublet.amenity_furnished || 0)
                        });
                    });
                }
            });
        });

        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [50, 50] });
        }

        // Fix map sizing
        setTimeout(function () { map.invalidateSize(); }, 200);
        window.addEventListener('resize', function () { map.invalidateSize(); });
    }

    /* ======================================================================
       Post Page — Create/Edit
       ====================================================================== */
    function initPost() {
        initAddressAutocomplete();
        initPostMap();
        initImageUpload();
        initExistingImageDelete();
    }

    // ---- Nominatim Address Autocomplete ----
    function initAddressAutocomplete() {
        var input = document.getElementById('address');
        var results = document.getElementById('addressResults');
        if (!input || !results) return;

        var debounceTimer = null;
        var highlightedIndex = -1;

        input.addEventListener('input', function () {
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
                var div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + escapeHtml(item.display_name);
                div.addEventListener('click', function () {
                    input.value = item.display_name;
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
        var lat = config.lat || 44.477435;
        var lon = config.lon || -73.195323;

        window._postMap = L.map('postMap').setView([lat, lon], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '\u00a9 OpenStreetMap'
        }).addTo(window._postMap);

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
                                    if (data.success) div.remove();
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

                    // Pagination
                    var pagDiv = document.getElementById('contactLogPagination');
                    if (pagDiv && data.pages > 1) {
                        pagDiv.innerHTML = '';
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
        var placeholder = document.createElement('div');
        placeholder.className = 'img-broken-placeholder';
        placeholder.innerHTML = '<i class="fa-solid fa-image"></i><span>Image not available</span>';
        img.parentNode.appendChild(placeholder);
    }

    // Attach to all images on page load
    document.querySelectorAll('.card-image img, .modal-gallery img, .map-popup img').forEach(function (img) {
        img.addEventListener('error', function () { handleBrokenImage(img); });
        // If already broken (cached)
        if (img.complete && img.naturalWidth === 0 && img.src) {
            handleBrokenImage(img);
        }
    });

    // Also handle modal image errors
    var modalImg = document.getElementById('modalImage');
    if (modalImg) {
        modalImg.addEventListener('error', function () { handleBrokenImage(modalImg); });
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

    function buildCardTags(data) {
        var tags = [];

        // Show a few key amenities as small tags
        if (data.amenity_free_parking === '1') tags.push('<span class="utility-tag tag-included"><i class="fa-solid fa-square-parking"></i> Free Parking</span>');
        if (data.amenity_paid_parking === '1') tags.push('<span class="utility-tag tag-tenant"><i class="fa-solid fa-square-parking"></i> Paid Parking</span>');
        if (data.amenity_laundry_free === '1') tags.push('<span class="utility-tag tag-included"><i class="fa-solid fa-shirt"></i> Laundry</span>');
        if (data.amenity_laundry_paid === '1') tags.push('<span class="utility-tag tag-tenant"><i class="fa-solid fa-shirt"></i> Laundry (Paid)</span>');
        if (data.amenity_pets_allowed === '1') tags.push('<span class="utility-tag tag-included"><i class="fa-solid fa-paw"></i> Pets OK</span>');
        if (data.amenity_furnished === '1') tags.push('<span class="utility-tag tag-included"><i class="fa-solid fa-couch"></i> Furnished</span>');
        if (data.amenity_air_conditioning === '1') tags.push('<span class="utility-tag tag-included"><i class="fa-solid fa-snowflake"></i> A/C</span>');
        if (data.amenity_dishwasher === '1') tags.push('<span class="utility-tag tag-included"><i class="fa-solid fa-sink"></i> Dishwasher</span>');

        // Utility cost
        if (data.utility_cost && parseFloat(data.utility_cost) > 0) {
            tags.push('<span class="utility-tag"><i class="fa-solid fa-receipt"></i> ~$' + Number(data.utility_cost).toLocaleString() + '/mo utils</span>');
        }

        return tags.join('');
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
