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
            $to = 'aperkel@uvm.edu';
            $subject = 'New Sublet Post Created';
            $message = "A new sublet post has been created by $username.\nPrice: $price\nAddress: $address";
            mail($to, $subject, $message);
            
            header("Location: edit_post.php");
            exit;
        } else {
            echo "<p>Error adding sublet post.</p>";
        }
    }
}
?>

<!-- new_post.php -->
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
<script src = "https://maps.googleapis.com/maps/api/js?key=<?php echo $google_api_key ?>&libraries=places,marker&callback=initMap" async defer></script>


<?php include 'footer.php'; ?>