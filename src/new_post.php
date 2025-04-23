<?php
include 'top.php';
$google_api_key = $_ENV['GOOGLE_API'];
$username = $_SERVER['REMOTE_USER'] ?? 'Guest';
$error_message = "";

// (your POST-handling remains here…)

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