<?php
include 'top.php';

$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'unknown';
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = $_POST['price'] ?? '';
    $address = $_POST['address'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $lat = $_POST['lat'] ?? '';
    $lon = $_POST['lon'] ?? '';
    $description = $_POST['description'] ?? '';

    function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 3959)
    {
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    $campusLat = 44.477435;
    $campusLon = -73.195323;
    $lat = (float) $lat;
    $lon = (float) $lon;
    $distance = haversineGreatCircleDistance($campusLat, $campusLon, $lat, $lon);
    if ($distance > 50) {
        $error_message .= "<p>Error: The location is more than 50 miles from campus (calculated distance: " . round($distance, 2) . " miles).</p>";
    }

    if (empty($_FILES['image_url']['name'][0])) {
        $error_message .= "<p>Please upload at least one image.</p>";
    }

    if (empty($error_message)) {
        $target_dir = "./public/images/";
        // Process the first image as the thumbnail.
        $firstImage = $_FILES['image_url']['name'][0];
        $fileType = pathinfo($firstImage, PATHINFO_EXTENSION);
        $target_file = $target_dir . $username . '_0.' . $fileType;
        if (!move_uploaded_file($_FILES['image_url']['tmp_name'][0], $target_file)) {
            $error_message .= "<p>Error uploading first image. Error code: " . $_FILES['image_url']['error'][0] . "</p>";
        } else {
            $thumbnail = $target_file;
        }
    }

    if (empty($error_message)) {
        // Insert into sublets table (store the thumbnail image URL).
        $sql = "INSERT INTO sublets (image_url, price, address, semester, lat, lon, description, username)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$thumbnail, $price, $address, $semester, $lat, $lon, $description, $username])) {
            $subletId = $pdo->lastInsertId();

            // Insert the thumbnail into sublet_images with sort_order 0.
            $stmtImage = $pdo->prepare("INSERT INTO sublet_images (sublet_id, image_url, sort_order) VALUES (?, ?, ?)");
            $stmtImage->execute([$subletId, $thumbnail, 0]);

            // Process additional images (if any)
            for ($i = 1; $i < count($_FILES['image_url']['name']); $i++) {
                $imageName = $_FILES['image_url']['name'][$i];
                $fileType = pathinfo($imageName, PATHINFO_EXTENSION);
                $target_file = $target_dir . $username . '_' . $i . '.' . $fileType;
                if (move_uploaded_file($_FILES['image_url']['tmp_name'][$i], $target_file)) {
                    $stmtImage->execute([$subletId, $target_file, $i]);
                }
            }

            $to = 'aperkel@uvm.edu';
            $subject = 'New Sublet Post Created';
            $message = "A new sublet post has been created by $username.\nPrice: $price\nAddress: $address";
            mail($to, $subject, $message);
            header("Location: edit_post.php");
            exit;
        } else {
            $error_message .= "<p>Error adding sublet post.</p>";
        }
    }
}
?>

<script>
    function initMap() {
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: { lat: 44.477435, lng: -73.195323 },
            mapId: '3bd5c9ae8c849605' // Replace YOUR_MAP_ID with your actual Map ID
        });
        geocoder = new google.maps.Geocoder();

        // Create an Advanced Marker Element (requires the marker library)
        marker = new google.maps.marker.AdvancedMarkerElement({
            map: map,
            position: { lat: 44.477435, lng: -73.195323 },
            title: "Sublet Location"
        });

        let autocomplete = new google.maps.places.Autocomplete(document.getElementById('address'), {
            types: ['geocode']
        });
        autocomplete.addListener('place_changed', function () {
            let place = autocomplete.getPlace();
            if (place.geometry) {
                document.getElementById('lat').value = place.geometry.location.lat();
                document.getElementById('lon').value = place.geometry.location.lng();
                // Update the marker position using AdvancedMarkerElement:
                marker.position = place.geometry.location;
                map.setCenter(place.geometry.location);
            }
            verifyAddress();
        });
    }
</script>

<main>
    <?php if (!empty($error_message)): ?>
        <div
            style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <div class="new-post-container">
        <div class="form-container">
            <form method="post" action="new_post.php" class="new-post-form" enctype="multipart/form-data">
                <div class="flex-row">
                    <div>
                        <label>Choose Images:</label>
                        <label for="image_url" class="custom-file-upload">UPLOAD</label>
                        <input type="file" id="image_url" name="image_url[]" accept="image/*" required multiple>
                    </div>
                    <div>
                        <label for="price">Price:</label>
                        <input type="number" id="price" name="price" step="0.01" required>
                    </div>
                </div>
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" placeholder="Enter a valid address" required>
                <input type="hidden" id="lat" name="lat">
                <input type="hidden" id="lon" name="lon">
                <label for="semester">Semester:</label>
                <select id="semester" name="semester" required>
                    <option value="" disabled selected>Select your option</option>
                    <option value="summer25">Summer 2025</option>
                    <option value="fall25">Fall 2025</option>
                    <option value="spring26">Spring 2026</option>
                </select>
                <label for="description">Description:</label>
                <textarea id="description" name="description" rows="4" cols="50"
                    placeholder="Describe your listing here!"></textarea>
                <input type="submit" value="Add Post">
            </form>
        </div>
        <div class="map-container">
            <div id="map"></div>
        </div>
    </div>
</main>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_api_key ?>&libraries=places,marker&callback=initMap"
    async defer>
    </script>
<?php include 'footer.php'; ?>