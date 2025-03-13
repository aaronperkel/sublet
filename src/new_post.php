<!-- new_post.php -->
<?php
include 'top.php';

$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form fields
    // Using $_FILES for file upload handling
    $image_url = $_FILES['image_url']['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $address = $_POST['address'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $lat = $_POST['lat'] ?? '';
    $lon = $_POST['lon'] ?? '';
    $description = $_POST['description'] ?? '';

    // Process file upload: move file to public/images/ and set image_url accordingly.
    if (!empty($image_url)) {
        $target_dir = "./public/images/";
        $target_file = $target_dir . basename($image_url);
        if (move_uploaded_file($_FILES['image_url']['tmp_name'], $target_file)) {
            $image_url = $target_file;
        } else {
            echo "<p>Error uploading file.</p>";
        }
    }

    // Check if the user already has a post
    $sqlCheck = "SELECT id FROM sublets WHERE username = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$username]);

    if ($stmtCheck->rowCount() > 0) {
        echo "<p>You already have a post. You can only have one post per user.</p>";
    } else {
        // Prepare SQL statement including the username column
        $sql = "INSERT INTO sublets (image_url, price, address, semester, lat, lon, description, username)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$image_url, $price, $address, $semester, $lat, $lon, $description, $username])) {
            echo "<p>Sublet post added successfully!</p>";
        } else {
            echo "<p>Error adding sublet post.</p>";
        }
    }
}
?>

<main>
    <div class="new-post-container">
        <div class="form-container">
            <form method="post" action="new_post.php" class="new-post-form" enctype="multipart/form-data">
                <div class="flex-row">
                    <div>
                        <label>Choose Image:</label>
                        <label for="image_url" class="custom-file-upload">UPLOAD</label>
                        <input type="file" id="image_url" name="image_url" accept="image/*" required>
                    </div>
                    <div>
                        <label for="price">Price:</label>
                        <input type="number" id="price" name="price" step="0.01" required>
                    </div>
                </div>

                <label for="address">Address:</label>
                <input type="text" id="address" name="address" placeholder="Enter a valid address" required>

                <!-- Hidden fields for latitude and longitude -->
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
                <textarea id="description" name="description" rows="4" cols="50"></textarea>

                <input type="submit" value="Add Post">
            </form>
        </div>
        <div class="map-container">
            <!-- Map container for address verification -->
            <div id="map"></div>
        </div>
    </div>
</main>

<script>
    // Move these to global scope
    function debounce(func, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

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
        document.getElementById('image_url').addEventListener('change', function () {
            let fileName = this.files[0]?.name || 'UPLOAD';
            document.querySelector('label[for="image_url"]').textContent = fileName;
        });

        const debouncedVerifyAddress = debounce(verifyAddress, 500);
        document.getElementById("address").addEventListener("input", debouncedVerifyAddress);
    });

    function initMap() {
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: { lat: 44.477435, lng: -73.195323 }
        });
        geocoder = new google.maps.Geocoder();
        marker = new google.maps.Marker({ map: map });
        marker.setPosition({ lat: 44.477435, lng: -73.195323 });

        let autocomplete = new google.maps.places.Autocomplete(document.getElementById('address'), {
            types: ['geocode']
        });
        autocomplete.addListener('place_changed', function () {
            let place = autocomplete.getPlace();
            if (place.geometry) {
                document.getElementById('lat').value = place.geometry.location.lat();
                document.getElementById('lon').value = place.geometry.location.lng();
                // Update the marker position immediately:
                marker.setPosition(place.geometry.location);
                map.setCenter(place.geometry.location);
            }
            // Optionally, call verifyAddress to ensure consistency
            verifyAddress();
        });
    }
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_api_key; ?>&libraries=places&callback=initMap"
    async defer>
    </script>

<?php include 'footer.php'; ?>