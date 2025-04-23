<?php
include 'top.php';
if ($_SERVER['REMOTE_USER'] !== 'aperkel')
    die("Access denied.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // your existing mail logic…
}
?>
<main class="max-w-lg mx-auto px-4 py-8">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">Send Mail to All Users</h2>
    <form method="post" class="space-y-4">
        <div>
            <label class="block text-gray-700 dark:text-gray-300 mb-1">Subject</label>
            <input type="text" name="subject" required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100" />
        </div>
        <div>
            <label class="block text-gray-700 dark:text-gray-300 mb-1">Message</label>
            <textarea name="message" rows="6" required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"></textarea>
        </div>
        <button type="submit"
            class="w-full bg-blue-600 dark:bg-blue-500 text-white py-2 rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition">
            Send Mail
        </button>
    </form>
</main>
<?php include 'footer.php'; ?>