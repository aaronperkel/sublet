<?php
ob_start();
include 'top.php';
$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'Guest';

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
<main class="max-w-md mx-auto px-4 py-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 space-y-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Edit Your Sublet</h2>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Current Thumbnail</label>
                <img src="<?= $userPost['image_url'] ?>" alt="" class="w-full h-48 object-cover rounded mb-2">
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Change/Add Images</label>
                <input type="file" name="image_url[]" multiple
                    class="block w-full text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded" />
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Price</label>
                <input type="number" name="price" step="0.01" required
                    value="<?= htmlspecialchars($userPost['price']) ?>"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Address</label>
                <input type="text" readonly value="<?= htmlspecialchars($userPost['address']) ?>"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Semester</label>
                <select name="semester" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                    <option value="summer25" <?= $userPost['semester'] === 'summer25' ? 'selected' : '' ?>>Summer 2025
                    </option>
                    <option value="fall25" <?= $userPost['semester'] === 'fall25' ? 'selected' : '' ?>>Fall 2025</option>
                    <option value="spring26" <?= $userPost['semester'] === 'spring26' ? 'selected' : '' ?>>Spring 2026
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea name="description" rows="4"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"><?= htmlspecialchars($userPost['description']) ?></textarea>
            </div>
            <div class="flex space-x-2">
                <button type="submit"
                    class="flex-1 bg-green-600 dark:bg-green-500 text-white py-2 rounded hover:bg-green-700 dark:hover:bg-green-600 transition">
                    Save Changes
                </button>
                <a href="?action=delete" onclick="return confirm('Delete this post?')"
                    class="flex-1 text-center bg-red-600 dark:bg-red-500 text-white py-2 rounded hover:bg-red-700 dark:hover:bg-red-600 transition">
                    Delete
                </a>
            </div>
        </form>
    </div>
</main>

<script>
    function initMap() { /* … */ }
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= $google_api_key ?>&libraries=places,marker&callback=initMap"
    async defer></script>
<?php include 'footer.php'; ?>