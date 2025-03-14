<?php
include 'top.php';
$sql = "SELECT * FROM sublets";
$stmt = $pdo->query($sql);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- map.php -->
<main>
    <div id="map" style="height: 600px; padding: 1%"></div>
    <!-- Load Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

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


    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        // Initialize the map centered at UVM campus (this initial center and zoom are temporary)
        var map = L.map('map').setView([44.477435, -73.195323], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // PHP to JSON-encode your sublets
        var sublets = <?php echo json_encode($sublets); ?>;

        // Create an empty LatLngBounds object
        var bounds = L.latLngBounds();

        sublets.forEach(function (sublet) {
            var marker = L.marker([sublet.lat, sublet.lon]).addTo(map);
            marker.bindPopup(
                `<div class="popup-content" style="text-align:center;">
      <img src="${sublet.image_url}" 
           style="width:200px; cursor:pointer; display:block; margin:0 auto; border-radius:8px; box-shadow:0 4px 8px rgba(0,0,0,0.1);" 
           onclick='openSubletModal(${JSON.stringify(sublet)})'>
      <p style="margin:10px 0 0; font-size:0.9em; color:#555;">Click image for details</p>
      <div style="margin-top:10px;">
         Price: $${sublet.price}<br>
         ${sublet.address}
      </div>
  </div>`
            );
            // Extend the bounds to include this marker's position
            bounds.extend(marker.getLatLng());
        });

        // If we have any markers, adjust the map's bounds to show them all
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [50, 50] }); // Optional padding for a bit of space around markers
        }

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

            // Setup contact button with email info
            var currentUser = document.body.getAttribute('data-user') || "Guest";
            var toEmail = sublet.username + "@uvm.edu";
            var subject = "Interested in Your " + friendlySemester + " Sublet Posting";
            var body = "Hello!\n\nI’m interested in your sublet posting for " + friendlySemester +
                " at " + sublet.address +
                ". Could you send me more details when you have a moment?\n\nThanks,\n" + currentUser;
            var mailtoLink = "mailto:" + encodeURIComponent(toEmail) +
                "?subject=" + encodeURIComponent(subject) +
                "&body=" + encodeURIComponent(body);
            document.getElementById('modalContact').setAttribute('href', mailtoLink);

            // Finally, display the modal
            document.getElementById('subletModal').style.display = "block";
        }
    </script>
</main>
<?php include 'footer.php'; ?>