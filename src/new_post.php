<!-- new_post.php -->
<?php
include 'top.php';

$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form fields
    $image_url   = $_POST['image_url'] ?? '';
    $price       = $_POST['price'] ?? '';
    $address     = $_POST['address'] ?? '';
    $semester    = $_POST['semester'] ?? '';
    $lat         = $_POST['lat'] ?? '';
    $lon         = $_POST['lon'] ?? '';
    $description = $_POST['description'] ?? '';

    // For file uploads, assume the file name is provided and lives in public/images/
    $image_url = './public/images/' . $image_url;

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
    <h2>Add New Sublet Post</h2>
    <div class="new-post-container">
        <div class="form-container">
            <form method="post" action="new_post.php" class="new-post-form">
                <div class="flex-row">
                    <div>
                        <label>Choose Image:</label>
                        <label for="image_url" class="custom-file-upload">UPLOAD</label>
                        <input type="file" id="image_url" name="image_url" required>
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
    let map;
    let marker;
    let geocoder;

    function initMap() {
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: { lat: 44.477435, lng: -73.195323 } // Example: UVM campus center
        });
        geocoder = new google.maps.Geocoder();
        marker = new google.maps.Marker({ map: map });
        marker.setPosition({ lat: 44.477435, lng: -73.195323 });
    }

    // Verify the entered address using the geocoder
    function verifyAddress() {
        const addressInput = document.getElementById("address").value;
        if (!addressInput) {
            return;
        }
        geocoder.geocode({ address: addressInput }, function (results, status) {
            if (status === "OK" && results[0]) {
                // Update map center and marker position
                map.setCenter(results[0].geometry.location);
                marker.setPosition(results[0].geometry.location);
                marker.setMap(map);

                // Update hidden fields with lat and lon
                document.getElementById("lat").value = results[0].geometry.location.lat();
                document.getElementById("lon").value = results[0].geometry.location.lng();

                // Callback for customization
                addressVerifiedCallback(results[0]);
            } else {
                console.log("Geocode error: " + status);
            }
        });
    }

    // Debounce function to limit geocoder requests
    function debounce(func, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Customize this callback function as needed
    function addressVerifiedCallback(result) {
        console.log("Address verified: ", result.formatted_address);
    }

    // Debounced version of verifyAddress to run on every input change.
    const debouncedVerifyAddress = debounce(verifyAddress, 500);

    // Attach the debounced event listener to the address input
    document.getElementById("address").addEventListener("input", debouncedVerifyAddress);
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_api_key; ?>&callback=initMap&v=weekly" async
    defer>
    </script>

<?php include 'footer.php'; ?>