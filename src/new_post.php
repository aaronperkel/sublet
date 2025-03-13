<?php
include 'top.php';

$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'unknown';

// Check if user has an existing post
$stmtCheck = $pdo->prepare("SELECT * FROM sublets WHERE username = ?");
$stmtCheck->execute([$username]);
$userPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);

// Handle a deletion request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $stmtDelete = $pdo->prepare("DELETE FROM sublets WHERE username = ?");
    $stmtDelete->execute([$username]);
    // Redirect to the home page after deletion
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form fields
    $image_url = $_FILES['image_url']['name'] ?? '';
    $price = $_POST['price'] ?? '';
    $address = $_POST['address'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $lat = $_POST['lat'] ?? '';
    $lon = $_POST['lon'] ?? '';
    $description = $_POST['description'] ?? '';

    // Process file upload (if any)
    if (!empty($image_url)) {
        $target_dir = "./public/images/";
        $target_file = $target_dir . basename($image_url);
        if (move_uploaded_file($_FILES['image_url']['tmp_name'], $target_file)) {
            $image_url = $target_file;
        } else {
            echo "<p>Error uploading file.</p>";
        }
    } else {
        // If no new file is uploaded during editing, keep the existing image
        $image_url = $userPost['image_url'] ?? '';
    }

    if ($userPost) {
        // Update the existing post
        $sql = "UPDATE sublets SET image_url = ?, price = ?, address = ?, semester = ?, lat = ?, lon = ?, description = ? WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$image_url, $price, $address, $semester, $lat, $lon, $description, $username])) {
            echo "<p>Sublet post updated successfully!</p>";
            // Refresh the user post data
            $stmtCheck->execute([$username]);
            $userPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        } else {
            echo "<p>Error updating sublet post.</p>";
        }
    } else {
        // Insert a new post if none exists
        $sql = "INSERT INTO sublets (image_url, price, address, semester, lat, lon, description, username)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$image_url, $price, $address, $semester, $lat, $lon, $description, $username])) {
            echo "<p>Sublet post added successfully!</p>";
            // Set the new post data so that the edit form will be shown next time
            $stmtCheck->execute([$username]);
            $userPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        } else {
            echo "<p>Error adding sublet post.</p>";
        }
    }
}
?>

<main>
    <?php if ($userPost): ?>
        <!-- Show the form pre-filled with the user's post data for editing -->
        <form method="post" action="new_post.php" class="new-post-form" enctype="multipart/form-data">
            <!-- You may want to display the current image, and allow the user to upload a new one -->
            <div class="flex-row">
                <div>
                    <label>Current Image:</label>
                    <img src="<?= $userPost['image_url'] ?>" alt="Current sublet image" style="max-width: 100%;">
                    <br>
                    <label for="image_url" class="custom-file-upload">Change Image</label>
                    <input type="file" id="image_url" name="image_url" accept="image/*">
                </div>
                <div>
                    <label for="price">Price:</label>
                    <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($userPost['price']) ?>" required>
                </div>
            </div>
            <label for="address">Address:</label>
            <input type="text" id="address" name="address" placeholder="Enter a valid address" value="<?= htmlspecialchars($userPost['address']) ?>" readonly>
            <!-- Hidden fields for latitude and longitude -->
            <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($userPost['lat']) ?>">
            <input type="hidden" id="lon" name="lon" value="<?= htmlspecialchars($userPost['lon']) ?>">
            <label for="semester">Semester:</label>
            <select id="semester" name="semester" required>
                <option value="summer25" <?= $userPost['semester'] === 'summer25' ? 'selected' : '' ?>>Summer 2025</option>
                <option value="fall25" <?= $userPost['semester'] === 'fall25' ? 'selected' : '' ?>>Fall 2025</option>
                <option value="spring26" <?= $userPost['semester'] === 'spring26' ? 'selected' : '' ?>>Spring 2026</option>
            </select>
            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="4" cols="50"><?= htmlspecialchars($userPost['description']) ?></textarea>
            <input type="submit" value="Update Post">
        </form>
        <br>
        <!-- Delete button -->
        <form method="get" action="new_post.php" onsubmit="return confirm('Are you sure you want to delete your post?');">
            <input type="hidden" name="action" value="delete">
            <button type="submit" style="background-color:#d9534f; color:#fff; border:none; padding:0.6em 1.2em; border-radius:4px; cursor:pointer;">Delete Post</button>
        </form>
    <?php else: ?>
        <h2>Create a New Sublet Post</h2>
        <!-- New post form -->
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
    <?php endif; ?>
</main>

<script>
// (The rest of your JavaScript for map initialization and modal functionality remains unchanged)
</script>

<?php include 'footer.php'; ?>