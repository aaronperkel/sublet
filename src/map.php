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
    // Initialize the map centered at UVM campus (example coordinates)
    var map = L.map('map').setView([44.4759, -73.2121], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // PHP to JSON-encode your sublets
    var sublets = <?php echo json_encode($sublets); ?>;

    sublets.forEach(function (sublet) {
        var marker = L.marker([sublet.lat, sublet.lon]).addTo(map);
        marker.bindPopup("<img src='" + sublet.image_url + "' width='100'><br>" +
            "Price: $" + sublet.price + "<br>" +
            sublet.address);
    });
</script>
<?php include 'footer.php'; ?>