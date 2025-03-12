<!-- new_post.php -->
<?php
include 'top.php';

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

    // Prepare SQL statement
    $sql = "INSERT INTO sublets (image_url, price, address, semester, lat, lon, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$image_url, $price, $address, $semester, $lat, $lon, $description])) {
        echo "<p>Sublet post added successfully!</p>";
    } else {
        echo "<p>Error adding sublet post.</p>";
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
        <!-- This input will use Google Places Autocomplete -->
        <input type="text" id="address" name="address" placeholder="Enter a valid address" required><br><br>

        <label for="semester">Semester:</label><br>
        <input type="text" id="semester" name="semester" required><br><br>

        <!-- Hidden fields for latitude and longitude -->
        <input type="hidden" id="lat" name="lat">
        <input type="hidden" id="lon" name="lon">

        <label for="semester">Semester:</label><br>
        <input type="text" id="semester" name="semester" required><br><br>

        <label for="description">Description:</label><br>
        <textarea id="description" name="description" rows="4" cols="50"></textarea><br><br>

        <input type="submit" value="Add Post">
    </form>
</main>

<!-- Include the Google Maps JavaScript API with the Places library -->
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>
<script>
  // Initialize the autocomplete functionality on the address field.
  var autocomplete = new google.maps.places.Autocomplete(document.getElementById('address'), {
    types: ['geocode']
  });

  autocomplete.addListener('place_changed', function() {
    var place = autocomplete.getPlace();
    if (place.geometry) {
      document.getElementById('lat').value = place.geometry.location.lat();
      document.getElementById('lon').value = place.geometry.location.lng();
    } else {
      // If no geometry is returned, clear any existing values
      document.getElementById('lat').value = '';
      document.getElementById('lon').value = '';
    }
  });
</script>

<?php include 'footer.php'; ?>