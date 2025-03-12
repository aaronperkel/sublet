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
    <form method="post" action="new_post.php">
        <label for="image_url">Image URL:</label><br>
        <input type="text" id="image_url" name="image_url" required><br><br>

        <label for="price">Price:</label><br>
        <input type="number" id="price" name="price" step="0.01" required><br><br>

        <label for="address">Address:</label><br>
        <input type="text" id="address" name="address" placeholder="Enter a valid address" required><br><br>

        <!-- Hidden fields for latitude and longitude -->
        <input type="hidden" id="lat" name="lat">
        <input type="hidden" id="lon" name="lon">

        <label for="semester">Semester:</label><br>
        <input type="text" id="semester" name="semester" required><br><br>

        <label for="description">Description:</label><br>
        <textarea id="description" name="description" rows="4" cols="50"></textarea><br><br>

        <input type="submit" value="Add Post">
    </form>
    <!-- Map container for address verification -->
    <div id="map" style="height: 400px; width: 400px; margin-top: 20px;"></div>
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
    geocoder.geocode({ address: addressInput }, function(results, status) {
      if (status === "OK" && results[0]) {
        // Update map center and marker position
        map.setCenter(results[0].geometry.location);
        marker.setPosition(results[0].geometry.location);
        marker.setMap(map);

        // Update hidden fields with lat and lon
        document.getElementById("lat").value = results[0].geometry.location.lat();
        document.getElementById("lon").value = results[0].geometry.location.lng();

        // Call callback function (customize as needed)
        addressVerifiedCallback(results[0]);
      } else {
        console.log("Geocode error: " + status);
      }
    });
  }

  // Debounce function to limit geocoder requests
  function debounce(func, delay) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), delay);
    };
  }

  // Customize this callback function as needed
  function addressVerifiedCallback(result) {
    console.log("Address verified: ", result.formatted_address);
    // Additional customization can go here.
  }

  // Debounced version of verifyAddress to run on every input change.
  const debouncedVerifyAddress = debounce(verifyAddress, 500);

  // Attach the debounced event listener to the address input
  document.getElementById("address").addEventListener("input", debouncedVerifyAddress);
</script>

<script
  src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_api_key; ?>&callback=initMap&v=weekly"
  async
  defer>
</script>

<?php include 'footer.php'; ?>