<?php
include 'top.php';
$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'Guest';
$error_message = "";

// your existing POST-handling logic here…

?>
<main class="max-w-md mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Post a New Sublet</h2>

        <?php if ($error_message): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 mb-1">Images</label>
                <input type="file" name="image_url[]" multiple class="block w-full text-gray-700" />
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Price</label>
                <input type="number" name="price" step="0.01" required
                    class="w-full border border-gray-300 rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Address</label>
                <input type="text" id="address" name="address" required
                    class="w-full border border-gray-300 rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Semester</label>
                <select name="semester" required class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="">Select…</option>
                    <option value="summer25">Summer 2025</option>
                    <option value="fall25">Fall 2025</option>
                    <option value="spring26">Spring 2026</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="4"
                    class="w-full border border-gray-300 rounded px-3 py-2"></textarea>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition">
                Add Post
            </button>
        </form>
    </div>
</main>

<script>
    function initMap() { /* your existing map init */ }
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key=<?= $google_api_key ?>&libraries=places,marker&callback=initMap"
    async defer></script>
<?php include 'footer.php'; ?>