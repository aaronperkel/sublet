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
?>
<nav class="bg-white shadow">
  <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="index.php" class="flex items-center space-x-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24"
        stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-10l2 2" />
      </svg>
      <span class="text-2xl font-bold text-gray-800">UVM Sublets</span>
    </a>
    <div class="space-x-6 flex items-center">
      <a href="index.php" class="text-gray-600 hover:text-blue-600">Home</a>
      <a href="map.php" class="text-gray-600 hover:text-blue-600">Map</a>
      <a href="<?= $buttonLink ?>" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
        <?= $buttonText ?>
      </a>
    </div>
    <span class="text-gray-800 font-medium">Hello, <?= $_SERVER['REMOTE_USER'] ?></span>
  </div>
</nav>