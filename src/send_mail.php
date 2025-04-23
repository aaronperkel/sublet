<?php
include 'top.php';
if ($_SERVER['REMOTE_USER'] !== 'aperkel')
    die("Access denied.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // your existing mail-sending logic…
}
?>
<main class="max-w-lg mx-auto px-4 py-8">
    <h2 class="text-2xl font-bold mb-4">Send Mail to All Users</h2>
    <form method="post" class="space-y-4">
        <div>
            <label class="block text-gray-700 mb-1">Subject</label>
            <input type="text" name="subject" required class="w-full border border-gray-300 rounded px-3 py-2" />
        </div>
        <div>
            <label class="block text-gray-700 mb-1">Message</label>
            <textarea name="message" rows="6" required
                class="w-full border border-gray-300 rounded px-3 py-2"></textarea>
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition">
            Send Mail
        </button>
    </form>
</main>
<?php include 'footer.php'; ?>