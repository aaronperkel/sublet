document.addEventListener("DOMContentLoaded", function () {
    // Global variable for current user (assumes a "data-user" attribute on the <body>)
    var currentUser = document.body.getAttribute('data-user') || "Guest";

    // Global variables for image slider
    let currentIndex = 0;
    let images = [];

    // Renders the image slider if available
    function renderImage() {
        const slider = document.querySelector('.modal-image-slider');
        if (slider && images.length > 0) {
            slider.innerHTML = `<img src="${images[currentIndex]}" alt="Sublet image">`;
            const prevBtn = document.querySelector('.prev');
            const nextBtn = document.querySelector('.next');
            if (prevBtn) {
                prevBtn.style.display = currentIndex > 0 ? 'block' : 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = currentIndex < images.length - 1 ? 'block' : 'none';
            }
        }
    }

    var modalDelete = document.getElementById('modalDelete');
    if (modalDelete) {
        modalDelete.addEventListener('click', function (e) {
            e.preventDefault();
            if (confirm("Are you sure you want to delete this post?")) {
                window.location.href = "delete_post.php?id=" + window.currentPostId;
            }
        });
    }

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
    function verifyAddress() {
        const addressInput = document.getElementById("address");
        if (!addressInput) return;
        geocoder.geocode({ address: addressInput.value }, function (results, status) {
            if (status === "OK" && results[0]) {
                const location = results[0].geometry.location;
                document.getElementById("lat").value = location.lat();
                document.getElementById("lon").value = location.lng();
                if (marker) marker.setPosition(location);
                if (map) map.setCenter(location);
            }
        });
    }

    // Set up debounced version of verifyAddress
    const debouncedVerifyAddress = debounce(verifyAddress, 300);

    // Update upload button text
    const imageInput = document.getElementById('image_url');
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
    document.querySelectorAll('.grid-item').forEach(item => {
        item.addEventListener('click', () => {
            const postId = item.getAttribute('data-id');
            window.currentPostId = postId;
            const posterUsername = item.getAttribute('data-username');
            const price = item.getAttribute('data-price');
            const address = item.getAttribute('data-address');
            const semesterCode = item.getAttribute('data-semester');
            const desc = item.getAttribute('data-desc');

            // Set modal content
            document.getElementById('modalPrice').textContent = "Price: $" + price;
            document.getElementById('modalAddress').textContent = "Address: " + address;
            document.getElementById('modalSemester').textContent = "Semester: " +
                (semesterCode === 'summer25' ? 'Summer 2025' :
                    semesterCode === 'fall25' ? 'Fall 2025' :
                        semesterCode === 'spring26' ? 'Spring 2026' : semesterCode);
            document.getElementById('modalUsername').textContent = "Posted by: " + posterUsername;
            document.getElementById('modalDesc').innerHTML = "<br>Description: <br>" + desc;

            // Set images (using the grid image as default)
            images = [item.querySelector('img').src];
            currentIndex = 0;
            renderImage();

            // Toggle buttons based on user identity
            const modalContact = document.getElementById('modalContact');
            const modalEdit = document.getElementById('modalEdit');
            if (currentUser === posterUsername) {
                if (modalContact) modalContact.style.display = "none";
                if (modalEdit) modalEdit.style.display = "inline-block";
            } else {
                if (modalEdit) modalEdit.style.display = "none";
                if (modalContact) {
                    modalContact.style.display = "inline-block";
                    const toEmail = posterUsername + "@uvm.edu";
                    const subject = "Interested in Your Sublet Posting";
                    const body = "Hello!\n\nI'm interested in your sublet posting at " + address +
                        ". Could you send me more details?\n\nThanks,\n" + currentUser;
                    const mailtoLink = "mailto:" + encodeURIComponent(toEmail) +
                        "?subject=" + encodeURIComponent(subject) +
                        "&body=" + encodeURIComponent(body);
                    modalContact.setAttribute('href', mailtoLink);
                }
            }

            // Fetch additional images via AJAX.
            fetch('get_sublet_images.php?id=' + window.currentPostId)
                .then(response => response.json())
                .then(fetchedImages => {
                    if (Array.isArray(fetchedImages) && fetchedImages.length > 0) {
                        images = fetchedImages;
                        currentIndex = 0;
                        renderImage();
                        // Reset scroll after images are rendered.
                        setTimeout(() => {
                            const modalContent = document.querySelector('.modal-content');
                            if (modalContent) modalContent.scrollTop = 0;
                        }, 50);
                    }
                })
                .catch(error => console.error('Error fetching images:', error));

            openModal();
        });
    });

    // Expose function for map markers to use
    window.openSubletModal = function (sublet) {
        document.getElementById('modalPrice').textContent = "Price: $" + sublet.price;
        document.getElementById('modalAddress').textContent = "Address: " + sublet.address;
        document.getElementById('modalSemester').textContent = "Semester: " +
            (sublet.semester === 'summer25' ? 'Summer 2025' :
                sublet.semester === 'fall25' ? 'Fall 2025' :
                    sublet.semester === 'spring26' ? 'Spring 2026' : sublet.semester);
        document.getElementById('modalUsername').textContent = "Posted by: " + sublet.username;
        document.getElementById('modalDesc').innerHTML = "<br>Description: <br>" + sublet.description;

        // Set images from marker (if applicable)
        images = [sublet.image_url];
        currentIndex = 0;
        renderImage();

        openModal();
    };

    // Slider navigation buttons
    const prevBtn = document.querySelector('.prev');
    const nextBtn = document.querySelector('.next');
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) {
                currentIndex--;
                renderImage();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentIndex < images.length - 1) {
                currentIndex++;
                renderImage();
            }
        });
    }

    // Grid resizing function
    function resizeGridItem(item) {
        const grid = document.querySelector('.grid-container');
        const rowHeight = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-auto-rows'));
        const rowGap = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-gap'));
        const img = item.querySelector('img');
        const height = img.getBoundingClientRect().height;
        const rowSpan = Math.ceil((height + rowGap) / (rowHeight + rowGap)) - 1;
        item.style.gridRowEnd = "span " + rowSpan;
    }

    const gridImages = document.querySelectorAll('.grid-item img');
    gridImages.forEach(function (img) {
        if (img.complete) {
            resizeGridItem(img.parentElement);
        } else {
            img.addEventListener('load', function () {
                resizeGridItem(img.parentElement);
            });
        }
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('.grid-item').forEach(function (item) {
            resizeGridItem(item);
        });
    });

    if (document.body.classList.contains('new_post')) {
        function verifyAddress() {
            const addressInput = document.getElementById("address");
            geocoder.geocode({ address: addressInput.value }, function (results, status) {
                if (status === "OK" && results[0]) {
                    const location = results[0].geometry.location;
                    document.getElementById("lat").value = location.lat();
                    document.getElementById("lon").value = location.lng();
                    // Update the marker's position and recenter the map
                    marker.setPosition(location);
                    map.setCenter(location);
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function () {
            // Update upload button text
            const imageInput = document.getElementById('image_url');
            if (imageInput) {
                imageInput.addEventListener('change', function () {
                    let fileName = this.files[0]?.name || 'UPLOAD';
                    document.querySelector('label[for="image_url"]').textContent = fileName;
                });
            }

            const addressInput = document.getElementById("address");
            if (addressInput) {
                addressInput.addEventListener("input", debouncedVerifyAddress);
            }
        });
    }

    // Map-specific code:
    if (document.body.classList.contains("map")) {
        const mapElem = document.getElementById('map');
        if (mapElem) {
            // Initialize Leaflet map
            const leafletMap = L.map('map').setView([44.477435, -73.195323], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(leafletMap);

            // Use a global 'sublets' variable (set in map.php) if it exists
            if (window.sublets && Array.isArray(window.sublets)) {
                const bounds = L.latLngBounds();
                window.sublets.forEach(function (sublet) {
                    const marker = L.marker([sublet.lat, sublet.lon]).addTo(leafletMap);
                    marker.bindPopup(`
          <div class="popup-content" style="text-align:center;">
            <img src="${sublet.image_url}" style="width:200px; cursor:pointer; display:block; margin:0 auto; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1);"
                 data-sublet="${b64EncodeUnicode(JSON.stringify(sublet))}"
                 onclick="openSubletModal(JSON.parse(atob(this.getAttribute('data-sublet'))))">
            <p style="margin:10px 0 0; font-size:0.9em; color:#555;">Click image for details</p>
            <div style="margin-top:10px;">
               Price: $${sublet.price}<br>
               ${sublet.address}
            </div>
          </div>
        `);
                    bounds.extend(marker.getLatLng());
                    marker.on('click', function (e) {
                        leafletMap.setView(new L.LatLng(e.latlng.lat + 0.005, e.latlng.lng), leafletMap.getZoom());
                    });
                });
                if (bounds.isValid()) {
                    leafletMap.fitBounds(bounds, { padding: [50, 50] });
                }
            }

            // Invalidate map size after load/resize
            window.addEventListener('load', () => {
                setTimeout(() => leafletMap.invalidateSize(), 100);
            });
            window.addEventListener('resize', () => leafletMap.invalidateSize());

            // Utility function
            function b64EncodeUnicode(str) {
                return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g,
                    (match, p1) => String.fromCharCode('0x' + p1)));
            }
        }

        // Modify openSubletModal to support both index & map modals
        window.openSubletModal = function (sublet) {
            document.getElementById('modalPrice').textContent = "Price: $" + sublet.price;
            document.getElementById('modalAddress').textContent = "Address: " + sublet.address;
            document.getElementById('modalSemester').textContent =
                "Semester: " + (sublet.semester === 'summer25' ? 'Summer 2025' :
                    sublet.semester === 'fall25' ? 'Fall 2025' :
                        sublet.semester === 'spring26' ? 'Spring 2026' : sublet.semester);
            document.getElementById('modalUsername').textContent = "Posted by: " + sublet.username;
            document.getElementById('modalDesc').innerHTML = "<br>Description: <br>" + sublet.description;

            // For map page, check if modal image exists; otherwise use image slider logic
            const modalImg = document.getElementById('modalImage');
            if (modalImg) {
                modalImg.src = sublet.image_url;
            } else if (document.querySelector('.modal-image-slider')) {
                window.images = [sublet.image_url];
                window.currentIndex = 0;
                renderImage();
            }
            openModal();
        }
    };
});