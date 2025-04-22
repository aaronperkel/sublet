<?php
ob_start();
include 'top.php';

$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'unknown';

$stmtCheck = $pdo->prepare("SELECT * FROM sublets WHERE username = ?");
$stmtCheck->execute([$username]);
$userPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$userPost) {
    header("Location: new_post.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $stmtDelete = $pdo->prepare("DELETE FROM sublets WHERE username = ?");
    $stmtDelete->execute([$username]);

    $to = 'aperkel@uvm.edu';
    $subject = 'Sublet Post Deleted';
    $message = "The sublet post for user $username has been deleted.";
    mail($to, $subject, $message);

    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $price = $_POST['price'] ?? '';
    $address = $_POST['address'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $lat = $_POST['lat'] ?? '';
    $lon = $_POST['lon'] ?? '';
    $description = $_POST['description'] ?? '';

    $subletId = $userPost['id'];

    // Process any additional image uploads
    if (!empty($_FILES['image_url']['name'][0])) {
        $target_dir = "./public/images/";
        foreach ($_FILES['image_url']['name'] as $key => $name) {
            $target_file = $target_dir . basename($name);
            if (move_uploaded_file($_FILES['image_url']['tmp_name'][$key], $target_file)) {
                $stmtImage = $pdo->prepare("INSERT INTO sublet_images (sublet_id, image_url, sort_order) VALUES (?, ?, ?)");
                $stmtImage->execute([$subletId, $target_file, $key]);
            }
        }
    }

    $sql = "UPDATE sublets SET price = ?, address = ?, semester = ?, lat = ?, lon = ?, description = ? WHERE username = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$price, $address, $semester, $lat, $lon, $description, $username])) {
        $msg = <<<HTML
            <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px;">
            Sublet post updated successfully!
            </div>
            HTML;
        echo $msg;

        $to = 'aperkel@uvm.edu';
        $subject = 'Sublet Post Updated';
        $message = "The sublet post for user $username has been edited.";
        mail($to, $subject, $message);

        $stmtCheck->execute([$username]);
        $userPost = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    } else {
        echo "<p>Error updating sublet post.</p>";
    }
}
?>
<main>
    <form method="post" action="edit_post.php" class="new-post-form" enctype="multipart/form-data">
        <div class="flex-row">
            <div>
                <label>Current Thumbnail:</label>
                <img src="<?= $userPost['image_url'] ?>" alt="Current sublet image" style="max-width: 100%;">
                <br>
                <label for="image_url" class="custom-file-upload">Change/Add Images</label>
                <input type="file" id="image_url" name="image_url[]" accept="image/*" multiple>
            </div>
            <div>
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01"
                    value="<?= htmlspecialchars($userPost['price']) ?>" required>
            </div>
        </div>
        <label for="address">Address:</label>
        <input type="text" id="address" name="address" value="<?= htmlspecialchars($userPost['address']) ?>" readonly>
        <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($userPost['lat']) ?>">
        <input type="hidden" id="lon" name="lon" value="<?= htmlspecialchars($userPost['lon']) ?>">
        <label for="semester">Semester:</label>
        <select id="semester" name="semester" required>
            <option value="summer25" <?= $userPost['semester'] === 'summer25' ? 'selected' : '' ?>>Summer 2025</option>
            <option value="fall25" <?= $userPost['semester'] === 'fall25' ? 'selected' : '' ?>>Fall 2025</option>
            <option value="spring26" <?= $userPost['semester'] === 'spring26' ? 'selected' : '' ?>>Spring 2026</option>
        </select>
        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4"
            cols="50"><?= htmlspecialchars($userPost['description']) ?></textarea>
        <input type="submit" value="Update Post">
    </form>
    <br>
    <form method="get" action="edit_post.php" onsubmit="return confirm('Are you sure you want to delete your post?');">
        <input type="hidden" name="action" value="delete">
        <button type="submit"
            style="background-color:#d9534f; color:#fff; border:none; padding:0.6em 1.2em; border-radius:4px; cursor:pointer;">Delete
            Post</button>
    </form>
</main>
<?php include 'footer.php'; ?>