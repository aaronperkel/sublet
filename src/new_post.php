<?php
include 'top.php';
$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'Guest';
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
<main class="max-w-md mx-auto px-4 py-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">Post a New Sublet</h2>

        <?php if ($error_message): ?>
            <div class="mb-4 p-3 bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-300 rounded">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Images</label>
                <input type="file" name="image_url[]" multiple
                    class="block w-full text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded" />
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Price</label>
                <input type="number" name="price" step="0.01" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Address</label>
                <input type="text" id="address" name="address" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Semester</label>
                <select name="semester" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                    <option value="">Select…</option>
                    <option value="summer25">Summer 2025</option>
                    <option value="fall25">Fall 2025</option>
                    <option value="spring26">Spring 2026</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-300 mb-1">Description</label>
                <textarea name="description" rows="4"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"></textarea>
            </div>
            <button type="submit"
                class="w-full bg-blue-600 dark:bg-blue-500 text-white py-2 rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition">
                Add Post
            </button>
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