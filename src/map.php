<?php
include 'top.php';

// Build filter conditions and parameters
$filters = [];
$params = [];

// Price range filter
if (isset($_GET['min_price'], $_GET['max_price']) && $_GET['min_price'] !== '' && $_GET['max_price'] !== '') {
    $filters[] = "price BETWEEN ? AND ?";
    $params[] = $_GET['min_price'];
    $params[] = $_GET['max_price'];
}

// Semester filter
if (isset($_GET['semester']) && $_GET['semester'] !== '') {
    $filters[] = "semester = ?";
    $params[] = $_GET['semester'];
}

// Distance filter (in miles)
if (isset($_GET['max_distance']) && $_GET['max_distance'] !== '') {
    $filters[] = "3959 * acos(cos(radians(44.477435)) * cos(radians(lat)) * cos(radians(lon) - radians(-73.195323)) + sin(radians(44.477435)) * sin(radians(lat))) <= ?";
    $params[] = $_GET['max_distance'];
}

// Build SQL query with filters
$sql = "SELECT * FROM sublets";
if ($filters) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}
$sql .= " ORDER BY RAND()";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- map.php -->
<main>
    <div class="filter-wrapper">
        <form id="filterForm" method="get" class="filters">
            <div class="filter-item">
                <label for="price-slider">Price Range:</label>
                <div id="price-slider"></div>
                <div id="price-range-value"></div>
                <!-- Hidden inputs to store slider values -->
                <input type="hidden" name="min_price" id="min_price">
                <input type="hidden" name="max_price" id="max_price">
            </div>
            <div class="filter-item">
                <label for="semester-filter">Semester:</label>
                <select id="semester-filter" name="semester">
                    <option value="" <?php if (!isset($_GET['semester']) || $_GET['semester'] === '')
                        echo 'selected'; ?>>All
                    </option>
                    <?php
                    // Optional friendly name mapping
                    $semesterMapping = [
                        'summer25' => 'Summer 2025',
                        'fall25' => 'Fall 2025',
                        'spring26' => 'Spring 2026'
                    ];
                    foreach ($semesters as $semester): ?>
                        <option value="<?= htmlspecialchars($semester) ?>" <?php if (isset($_GET['semester']) && $_GET['semester'] == $semester)
                              echo 'selected'; ?>>
                            <?= isset($semesterMapping[$semester]) ? $semesterMapping[$semester] : htmlspecialchars($semester) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label for="distance-slider">Distance from Campus:</label>
                <div id="distance-slider"></div>
                <div id="distance-value"></div>
                <input type="hidden" name="max_distance" id="max_distance">
            </div>
            <div class="filter-buttons" style="display: flex; flex-direction: column; align-items: center">
                <div class="filter-item button" style="width: 100%; max-width: 300px;">
                    <button type="submit" style="width: 100%; padding: 0.8em;">Apply Filters</button>
                </div>
                <div class="button">
                    <a href="map.php" class="clear-filters"
                        style="display: inline-block; font-size: 0.9em; padding: 0.2em 0.5em; background-color: #f1f1f1; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333; margin-left: 20px;">Clear
                        Filters</a>
                </div>
            </div>
        </form>
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

                // Display the modal
                document.getElementById('subletModal').style.display = "block";
            }
        </script>
</main>
<?php include 'footer.php'; ?>