<?php
$buttonText = "New Post";
$buttonLink = "new_post.php";
if ($_SERVER['REMOTE_USER'] !== 'Guest') {
  $stmt = $pdo->prepare("SELECT id FROM sublets WHERE username = ?");
  $stmt->execute([$_SERVER['REMOTE_USER']]);
  if ($stmt->rowCount()) {
    $buttonText = "My Post";
    $buttonLink = "edit_post.php";
  }
}
$isAdmin = ($_SERVER['REMOTE_USER'] === 'aperkel');
?>
<nav class="bg-white dark:bg-gray-800 shadow">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="index.php" class="flex items-center space-x-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500 dark:text-blue-400" fill="none"
        viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-10l2 2" />
      </svg>
      <span class="text-2xl font-bold text-gray-800 dark:text-gray-100">UVM Sublets</span>
    </a>
    <div class="space-x-6 flex items-center">
      <a href="index.php" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Home</a>
      <a href="map.php" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Map</a>
      <?php if ($isAdmin): ?>
        <a href="send_mail.php" class="text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
          Send Mail
        </a>
      <?php endif; ?>
      <a href="<?= $buttonLink ?>"
        class="px-4 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition">
        <?= $buttonText ?>
      </a>
    </div>
    <span class="text-gray-800 dark:text-gray-100 font-medium">Hello, <?= $_SERVER['REMOTE_USER'] ?></span>
  </div>
</nav>