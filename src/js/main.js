(function() {
    'use strict';

    var currentUser; // Defined in IIFE scope

    var currentModalPostId = null;
    var modalImages = [];
    var modalImageCurrentIndex = 0;

    document.addEventListener("DOMContentLoaded", function () {
        currentUser = document.body.getAttribute('data-user') || "Guest"; // Assigned on DOMContentLoaded

    // Renders the image slider if available
    function renderImage() {
        const slider = document.querySelector('.modal-image-slider');
        if (slider && modalImages.length > 0) {
            slider.innerHTML = `<img src="${modalImages[modalImageCurrentIndex]}" alt="Sublet image">`;
            const prevBtn = document.querySelector('.prev');
            const nextBtn = document.querySelector('.next');
            if (prevBtn) {
                prevBtn.style.display = modalImageCurrentIndex > 0 ? 'block' : 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = modalImageCurrentIndex < modalImages.length - 1 ? 'block' : 'none';
            }
        }
    }

    // Removed old global modalDelete listener, it's handled in grid-item click

    // Google Maps related vars
    var googleMapInstance, geocoderInstance, markerInstance;

    // Utility function b64EncodeUnicode, moved to IIFE scope
    function b64EncodeUnicode(str) {
        return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g,
            (match, p1) => String.fromCharCode('0x' + p1)));
    }

    // Global definition for openSubletModal
    window.openSubletModal = function (subletData) {
        const modalPriceEl = document.getElementById('modalPrice');
        const modalAddressEl = document.getElementById('modalAddress');
        const modalSemesterEl = document.getElementById('modalSemester');
        const modalUsernameEl = document.getElementById('modalUsername');
        const modalDescEl = document.getElementById('modalDesc');

        if(modalPriceEl) modalPriceEl.textContent = "Price: $" + subletData.price;
        if(modalAddressEl) modalAddressEl.textContent = "Address: " + subletData.address;
        if(modalSemesterEl) {
            const semesterMap = {
                'summer25': 'Summer 2025', 'fall25': 'Fall 2025', 'spring26': 'Spring 2026',
                'summer26': 'Summer 2026', 'fall26': 'Fall 2026', 'spring27': 'Spring 2027'
            };
            modalSemesterEl.textContent = "Semester: " + (semesterMap[subletData.semester] || subletData.semester);
        }
        if(modalUsernameEl) modalUsernameEl.textContent = "Posted by: " + subletData.username;
        if(modalDescEl) modalDescEl.innerHTML = "<br>Description: <br>" + (subletData.description || "Not provided.");

        currentModalPostId = subletData.id;

        if (subletData.all_images && subletData.all_images.length > 0) {
             modalImages = subletData.all_images;
        } else if (subletData.image_url) {
            modalImages = [subletData.image_url];
        } else if (subletData.first_image_url) {
             modalImages = [subletData.first_image_url];
        } else {
            modalImages = ['./public/images/default_sublet_image.png'];
        }
        modalImageCurrentIndex = 0;
        renderModalImage();

        openModal();
    };

    // Global initMap for Google Maps API callback
    window.initMap = function() {
        const mapDiv = document.getElementById('map');
        if (!mapDiv) return;

        googleMapInstance = new google.maps.Map(mapDiv, {
            zoom: 15,
            center: { lat: 44.477435, lng: -73.195323 },
        });
        geocoderInstance = new google.maps.Geocoder();

        let initialPosition = { lat: 44.477435, lng: -73.195323 };
        const latInput = document.getElementById('lat');
        const lonInput = document.getElementById('lon');
        if (latInput && lonInput && latInput.value && lonInput.value) {
            const latVal = parseFloat(latInput.value);
            const lonVal = parseFloat(lonInput.value);
            if (!isNaN(latVal) && !isNaN(lonVal)) {
                initialPosition = { lat: latVal, lng: lonVal };
                googleMapInstance.setCenter(initialPosition);
            }
        }

        markerInstance = new google.maps.marker.AdvancedMarkerElement({
            map: googleMapInstance,
            position: initialPosition,
            title: "Sublet Location"
        });

        const addressInputForAutocomplete = document.getElementById('address');
        if (addressInputForAutocomplete) {
            let autocomplete = new google.maps.places.Autocomplete(addressInputForAutocomplete, { types: ['geocode'] });
            autocomplete.addListener('place_changed', function () {
                let place = autocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    const location = place.geometry.location;
                    if(latInput) latInput.value = location.lat();
                    if(lonInput) lonInput.value = location.lng();
                    if (markerInstance) markerInstance.position = location;
                    if (googleMapInstance) googleMapInstance.setCenter(location);
                } else {
                    verifyAddress();
                }
            });
             addressInputForAutocomplete.addEventListener('blur', verifyAddress);
        }
    };

    // Opens the modal and resets the scroll position
    function openModal() {
        const modalContent = document.querySelector('.modal-content');
        setTimeout(() => {
            if (modalContent) {
                modalContent.scrollTop = 0;
            }
        }, 50);
        document.body.classList.add('modal-open');
        document.getElementById('subletModal').style.display = 'block';
    }

    // Closes the modal
    function closeModal() {
        document.body.classList.remove('modal-open');
        document.getElementById('subletModal').style.display = 'none';
    }

    // Debounce utility function
    function debounce(func, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Verify address function (uses Google Maps geocoder)
    // Consolidated verifyAddress function
    function verifyAddress() {
        const addressInput = document.getElementById("address");
        // Uses IIFE-scoped geocoderInstance, markerInstance, googleMapInstance
        if (!geocoderInstance || !addressInput || !addressInput.value) return;

        geocoderInstance.geocode({ address: addressInput.value }, function (results, status) {
            if (status === "OK" && results[0] && results[0].geometry && results[0].geometry.location) {
                const location = results[0].geometry.location;
                const latInput = document.getElementById("lat");
                const lonInput = document.getElementById("lon");
                if(latInput) latInput.value = location.lat();
                if(lonInput) lonInput.value = location.lng();

                if (markerInstance) { // Check if markerInstance is initialized
                     // For google.maps.marker.AdvancedMarkerElement, setting position is enough.
                    markerInstance.position = location;
                }
                if (googleMapInstance) { // Check if googleMapInstance is initialized
                    googleMapInstance.setCenter(location);
                }
            } else {
                // console.warn("Geocode was not successful for: " + addressInput.value + " due to: " + status);
            }
        });
    }

    const debouncedVerifyAddress = debounce(verifyAddress, 300); // Uses the consolidated verifyAddress

    // Update upload button text - will be moved into DOMContentLoaded
    // const imageInput = document.getElementById('image_url');
    if (imageInput) {
        imageInput.addEventListener('change', function () {
            let fileName = this.files[0]?.name || 'UPLOAD';
            const label = document.querySelector('label[for="image_url"]');
            if (label) label.textContent = fileName;
        });
    }

    // Add input event listener for address
    const addressInput = document.getElementById("address");
    if (addressInput) {
        addressInput.addEventListener("input", debouncedVerifyAddress);
    }

    // Modal close events
    const modal = document.getElementById('subletModal');
    if (modal) {
        const closeBtn = modal.querySelector('.close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        window.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    // Event listeners for grid items (for index page)
    // Restored and ensuring it's within DOMContentLoaded
    document.querySelectorAll('.grid-item').forEach(item => {
        item.addEventListener('click', () => {
            const postId = item.getAttribute('data-id');
            currentModalPostId = postId; // Use local variable
            const posterUsername = item.getAttribute('data-username');
            const price = item.getAttribute('data-price');
            const address = item.getAttribute('data-address');
            const semesterCode = item.getAttribute('data-semester');
            const desc = item.getAttribute('data-desc');
            const firstImage = item.querySelector('img') ? item.querySelector('img').src : './public/images/default_sublet_image.png';

            const modalPriceEl = document.getElementById('modalPrice');
            const modalAddressEl = document.getElementById('modalAddress');
            const modalSemesterEl = document.getElementById('modalSemester');
            const modalUsernameEl = document.getElementById('modalUsername');
            const modalDescEl = document.getElementById('modalDesc');

            if(modalPriceEl) modalPriceEl.textContent = "Price: $" + price;
            if(modalAddressEl) modalAddressEl.textContent = "Address: " + address;
            if(modalSemesterEl) {
                 const semesterMap = {
                    'summer25': 'Summer 2025', 'fall25': 'Fall 2025', 'spring26': 'Spring 2026',
                    'summer26': 'Summer 2026', 'fall26': 'Fall 2026', 'spring27': 'Spring 2027'
                };
                modalSemesterEl.textContent = "Semester: " + (semesterMap[semesterCode] || semesterCode);
            }
            if(modalUsernameEl) modalUsernameEl.textContent = "Posted by: " + posterUsername;
            if(modalDescEl) modalDescEl.innerHTML = "<br>Description: <br>" + (desc || "Not provided.");

            modalImages = [firstImage]; // Start with the grid image
            modalImageCurrentIndex = 0;
            renderImage(); // Render immediately with grid image

            const modalContactBtn = document.getElementById('modalContact');
            const modalEditBtn = document.getElementById('modalEdit');
            const modalDeleteBtn = document.getElementById('modalDelete');

            const canModify = currentUser && currentUser !== "Guest" && currentUser === posterUsername;

            if (canModify) {
                if (modalContactBtn) modalContactBtn.style.display = "none";
                if (modalEditBtn) {
                    modalEditBtn.style.display = "inline-block";
                    modalEditBtn.setAttribute('href', 'edit_post.php?id=' + postId);
                }
                if (modalDeleteBtn) {
                    modalDeleteBtn.style.display = "inline-block";
                    modalDeleteBtn.setAttribute('href', 'delete_post.php?id=' + postId);
                    modalDeleteBtn.onclick = function(e) {
                        e.preventDefault();
                        if (confirm("Are you sure you want to delete this post? This action cannot be undone.")) {
                            window.location.href = this.href;
                        }
                    };
                }
            } else {
                if (modalEditBtn) modalEditBtn.style.display = "none";
                if (modalDeleteBtn) modalDeleteBtn.style.display = "none";
                if (modalContactBtn) {
                    modalContactBtn.style.display = "inline-block";
                    const toEmail = posterUsername + "@uvm.edu";
                    const subject = "Interested in Your Sublet Posting";
                    const body = "Hello!\n\nI'm interested in your sublet posting at " + address +
                                 ". Could you send me more details?\n\nThanks,\n" + (currentUser && currentUser !== "Guest" ? currentUser : "An interested party");
                    const mailtoLink = "mailto:" + encodeURIComponent(toEmail) +
                                     "?subject=" + encodeURIComponent(subject) +
                                     "&body=" + encodeURIComponent(body);
                    modalContactBtn.setAttribute('href', mailtoLink);
                    modalContactBtn.onclick = () => {
                       fetch(`notify_contact.php?post_id=${postId}&owner=${encodeURIComponent(posterUsername)}`).catch(console.error);
                    };
                }
            }

            fetch('get_sublet_images.php?id=' + currentModalPostId)
                .then(response => response.json()) // Expect JSON
                .then(fetchedImages => {
                    if (Array.isArray(fetchedImages) && fetchedImages.length > 0) {
                        modalImages = fetchedImages;
                    } // If empty, modalImages retains the grid image
                    modalImageCurrentIndex = 0;
                    renderImage();
                    const modal = document.getElementById('subletModal');
                    setTimeout(() => {
                        const mc = modal ? modal.querySelector('.modal-content') : null;
                        if (mc) mc.scrollTop = 0;
                    }, 50);
                })
                .catch(error => {
                    console.error('Error fetching images:', error);
                    renderImage(); // Ensure slider is updated even on fetch error
                });
            openModal();
        });
    });


    // Slider navigation buttons
    // const prevBtn, nextBtn listeners are correctly inside DOMContentLoaded from prior successful diff.
    // The modal variable is also obtained within DOMContentLoaded.
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (modalImageCurrentIndex > 0) { // Use local variable
                modalImageCurrentIndex--; // Use local variable
                renderImage();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (modalImageCurrentIndex < modalImages.length - 1) { // Use local variables
                modalImageCurrentIndex++; // Use local variable
                renderImage();
            }
        });
    }

    // Grid resizing function and its listeners are moved into DOMContentLoaded.
    function resizeGridItem(item) {
        const grid = document.querySelector('.grid-container');
        if (!grid || !item) return; // Basic safety checks
        // Ensure styles are loaded and rowHeight/rowGap are numbers
        const gridStyles = window.getComputedStyle(grid);
        const rowHeight = parseInt(gridStyles.getPropertyValue('grid-auto-rows'));
        const rowGap = parseInt(gridStyles.getPropertyValue('grid-gap'));
        if (isNaN(rowHeight) || isNaN(rowGap)) return;

        const img = item.querySelector('img');
        if (!img) return;

        // Ensure image is loaded before getting height for more accuracy
        if (!img.complete && img.src) { // Check img.src to ensure it's a valid image element that can load
            img.addEventListener('load', () => resizeGridItem(item)); // Pass item (parent)
            return;
        }
        // If image is already loaded or has no src, proceed. If no src, height might be 0.
        const height = img.getBoundingClientRect().height;
        if (height === 0 && img.src) return; // Likely not rendered yet if height is 0 but has a source

        const rowSpan = Math.ceil((height + rowGap) / (rowHeight + rowGap)) -1;
        item.style.gridRowEnd = "span " + Math.max(1, rowSpan);
    }

    const gridImages = document.querySelectorAll('.grid-item img');
    gridImages.forEach(function (img) {
       resizeGridItem(img.parentElement); // Call directly, function handles load if needed
    });

    window.addEventListener('resize', debounce(function () { // Debounce resize
        document.querySelectorAll('.grid-item').forEach(function (item) {
            resizeGridItem(item);
        });
    }, 200));

    // Removed duplicate verifyAddress and nested DOMContentLoaded for new_post page.
    // The main verifyAddress and DOMContentLoaded will handle necessary initializations.

    // Address input listener for pages like new_post/edit_post, if initMap isn't handling it
    const addressInputForDebounce = document.getElementById("address");
    if (addressInputForDebounce && (document.body.classList.contains('new_post') || document.body.classList.contains('edit_post'))) {
        // initMap should handle listeners for address input it enhances.
        // This is for cases where initMap might not run or for other address fields.
        // verifyAddress itself checks for geocoderInstance.
        addressInputForDebounce.addEventListener("input", debouncedVerifyAddress);
    }

    // Image input listener for upload button text (thumbnail)
    const thumbInput = document.getElementById('thumbnail_image');
    if (thumbInput) {
        thumbInput.addEventListener('change', function() {
            let fileName = (this.files && this.files.length > 0) ? this.files[0].name : 'Choose Thumbnail';
            let label = document.querySelector('label[for="thumbnail_image"]');
            // This is a simple way; ideally, the label would have a specific span for the filename,
            // or if the label itself is the button.
            if (label && label.classList.contains('custom-file-upload')) {
                label.textContent = fileName;
            } else if (label) {
                // Fallback if label is not the button itself but a standard label.
                // You might want a more specific way to update, e.g., a dedicated span.
                // For now, just updating if it's a custom upload button.
            }
        });
    }
    // Add similar for additional_images if needed for its label.

    // Map-specific code (Leaflet)
    // Restored and ensuring it's within DOMContentLoaded
    if (document.body.classList.contains("map")) {
        const mapElem = document.getElementById('map');
        if (mapElem && typeof L !== 'undefined') { // Check if Leaflet is loaded
            const leafletMap = L.map('map').setView([44.477435, -73.195323], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(leafletMap);

            if (window.sublets && Array.isArray(window.sublets)) {
                const bounds = L.latLngBounds();
                window.sublets.forEach(function (subletEntry) {
                    const leafletMarker = L.marker([subletEntry.lat, subletEntry.lon]).addTo(leafletMap);
                    leafletMarker.bindPopup(`
                        <div class="popup-content" style="text-align:center;">
                            <img src="${subletEntry.image_url}" style="width:200px; cursor:pointer; display:block; margin:0 auto; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1);"
                                 data-sublet="${b64EncodeUnicode(JSON.stringify(subletEntry))}"
                                 onclick="window.openSubletModal(JSON.parse(atob(this.getAttribute('data-sublet'))))">
                            <p style="margin:10px 0 0; font-size:0.9em; color:#555;">Click image for details</p>
                            <div style="margin-top:10px;">
                               Price: $${subletEntry.price}<br>
                               ${subletEntry.address}
                            </div>
                        </div>
                    `);
                    bounds.extend(leafletMarker.getLatLng());
                    leafletMarker.on('click', function (e) {
                        leafletMap.setView(new L.LatLng(e.latlng.lat, e.latlng.lng), leafletMap.getZoom());
                    });
                });
                if (bounds.isValid()) {
                    leafletMap.fitBounds(bounds, { padding: [50, 50] });
                }
            }
            // Invalidate map size after load/resize - moved to a general window event listener
            // window.addEventListener('load', () => {
            //     setTimeout(() => leafletMap.invalidateSize(), 100);
            // });
            // window.addEventListener('resize', () => leafletMap.invalidateSize()); // Already global
        }
    } // End map specific code
    // The single global window.openSubletModal definition is already outside DOMContentLoaded,
    // taken from the more comprehensive version that was inside the "map" block.
    // Ensure its parameter is 'subletData' for clarity if it was 'sublet' before.

    // Utility function b64EncodeUnicode, if only used by map code, can be inside that block.
    // Or defined once in IIFE scope if used elsewhere. For now, assuming it's map-specific.
    // It was defined inside the "map" block in the original read_files output.
}); // End DOMContentLoaded

})(); // End IIFE