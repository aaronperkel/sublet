<!-- map.php -->
<?php
include 'top.php';
$sql = "SELECT * FROM sublets";
$stmt = $pdo->query($sql);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div id="map" style="height: 600px; padding: 1%"></div>
<!-- Load Leaflet CSS and JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
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
        marker.bindPopup("<img src='" + sublet.image_url + "' width='100'><br>" +
            "Price: $" + sublet.price + "<br>" +
            sublet.address);
        // Extend the bounds to include this marker's position
        bounds.extend(marker.getLatLng());
    });

    // If we have any markers, adjust the map's bounds to show them all
    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [50, 50] }); // Optional padding for a bit of space around markers
    }
</script>
<?php include 'footer.php'; ?>