<?php
ob_start();
include 'top.php';
$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'Guest';

// fetch $userPost, handle POST & delete logic as before…
?>
<main class="max-w-md mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6 space-y-4">
        <h2 class="text-2xl font-bold">Edit Your Sublet</h2>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 mb-1">Current Thumbnail</label>
                <img src="<?= $userPost['image_url'] ?>" alt="" class="w-full h-48 object-cover rounded mb-2">
                <label class="block text-gray-700 mb-1">Change/Add Images</label>
                <input type="file" name="image_url[]" multiple class="block w-full text-gray-700" />
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Price</label>
                <input type="number" name="price" step="0.01" required
                    value="<?= htmlspecialchars($userPost['price']) ?>"
                    class="w-full border border-gray-300 rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Address</label>
                <input type="text" readonly value="<?= htmlspecialchars($userPost['address']) ?>"
                    class="w-full bg-gray-100 border border-gray-300 rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Semester</label>
                <select name="semester" required class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="summer25" <?= $userPost['semester'] === 'summer25' ? 'selected' : '' ?>>Summer 2025</option>
                    <option value="fall25" <?= $userPost['semester'] === 'fall25' ? 'selected' : '' ?>>Fall 2025</option>
                    <option value="spring26" <?= $userPost['semester'] === 'spring26' ? 'selected' : '' ?>>Spring 2026</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="4"
                    class="w-full border border-gray-300 rounded px-3 py-2"><?= htmlspecialchars($userPost['description']) ?></textarea>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded hover:bg-green-700 transition">
                    Save Changes
                </button>
                <a href="?action=delete" onclick="return confirm('Delete this post?')"
                    class="flex-1 text-center bg-red-600 text-white py-2 rounded hover:bg-red-700 transition">
                    Delete
                </a>
            </div>
        </form>
    </div>
</main>

<script>
    function initMap() { /* same verification/map code if needed */ }
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= $google_api_key ?>&libraries=places,marker&callback=initMap"
    async defer></script>
<?php include 'footer.php'; ?>