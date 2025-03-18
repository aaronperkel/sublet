<?php
include 'top.php';
?>

<!-- map.php -->
<main>
    <?php include 'filters.php'; ?>
    <div class="map-wrapper" style="width:100%; height:500px; position:relative;">
        <div id="map" style="width:100%; height:100%;"></div>
    </div>

    <!-- Modal Structure -->
    <div id="subletModal" class="modal">
        <div class="modal-content">
            <img id="modalImage" src="" alt="Sublet image">
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px;">
                <h2 id="modalUsername" style="padding: 0"></h2>
                <a id="modalContact" href="#"
                    style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); text-decoration: none; border-radius: 4px;">Contact</a>
            </div>
            <hr>
            <p id="modalPrice"></p>
            <p id="modalAddress"></p>
            <p id="modalSemester"></p>
            <p id="modalDesc"></p>
            <span class="close" style="cursor:pointer;">&times;</span>
        </div>
    </div>

    <!-- Load Leaflet CSS and JS -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        // Initialize the map centered at UVM campus
        var leafletMap = L.map('map').setView([44.477435, -73.195323], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(leafletMap);

        // Pass filtered sublets to JavaScript
        var sublets = <?php echo json_encode($sublets); ?>;
        var bounds = L.latLngBounds();

        sublets.forEach(function (sublet) {
            var marker = L.marker([sublet.lat, sublet.lon]).addTo(leafletMap);
            marker.bindPopup(
                `<div class="popup-content" style="text-align:center;">
              <img src="${sublet.image_url}" style="width:200px; cursor:pointer; display:block; margin:0 auto; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1);" 
                   data-sublet="${btoa(JSON.stringify(sublet))}"
                   onclick="openSubletModal(JSON.parse(atob(this.getAttribute('data-sublet'))))">
              <p style="margin:10px 0 0; font-size:0.9em; color:#555;">Click image for details</p>
              <div style="margin-top:10px;">
                 Price: $${sublet.price}<br>
                 ${sublet.address}
              </div>
          </div>`
            );
            bounds.extend(marker.getLatLng());
        });

        if (bounds.isValid()) {
            leafletMap.fitBounds(bounds, { padding: [50, 50] });
        }

        window.addEventListener('load', function () {
            setTimeout(function () {
                leafletMap.invalidateSize();
            }, 100);
        });
        window.addEventListener('resize', function () {
            leafletMap.invalidateSize();
        });

        function openSubletModal(sublet) {
            // Convert semester code to a friendly string
            const semesterMapping = {
                "summer25": "Summer 2025",
                "fall25": "Fall 2025",
                "spring26": "Spring 2026"
            };
            const friendlySemester = semesterMapping[sublet.semester] || sublet.semester;
            document.getElementById('modalImage').src = sublet.image_url;
            document.getElementById('modalPrice').textContent = "Price: $" + sublet.price;
            document.getElementById('modalAddress').textContent = "Address: " + sublet.address;
            document.getElementById('modalSemester').textContent = "Semester: " + friendlySemester;
            document.getElementById('modalDesc').innerHTML = "<br>Description: <br>" + sublet.description;
            document.getElementById('modalUsername').textContent = "Posted by: " + sublet.username;

            // Get the current user from the body data attribute.
            var currentUser = document.body.getAttribute('data-user') || "Guest";

            // If the current user is the same as the poster, hide the Contact button.
            if (currentUser === sublet.username) {
                document.getElementById('modalContact').style.display = "none";
            } else {
                // Otherwise, show it and set up the mailto link.
                document.getElementById('modalContact').style.display = "inline-block";
                var toEmail = sublet.username + "@uvm.edu";
                var subject = "Interested in Your " + friendlySemester + " Sublet Posting";
                var body = "Hello!\n\nI’m interested in your sublet posting for " + friendlySemester +
                    " at " + sublet.address +
                    ". Could you send me more details when you have a moment?\n\nThanks,\n" + currentUser;
                var mailtoLink = "mailto:" + encodeURIComponent(toEmail) +
                    "?subject=" + encodeURIComponent(subject) +
                    "&body=" + encodeURIComponent(body);
                document.getElementById('modalContact').setAttribute('href', mailtoLink);
            }

            // Display the modal
            document.getElementById('subletModal').style.display = "block";
        }

        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('subletModal');
            var closeBtn = document.querySelector('#subletModal .close');
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });
            window.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

    </script>
</main>
<?php include 'footer.php'; ?>