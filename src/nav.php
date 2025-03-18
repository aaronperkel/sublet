<?php
// Assuming $pdo is already defined (from connect-db.php) and REMOTE_USER is set
$buttonText = "New Post";
$buttonLink = "new_post.php";

if (isset($_SERVER['REMOTE_USER'])) {
  $stmt = $pdo->prepare("SELECT id FROM sublets WHERE username = ?");
  $stmt->execute([$_SERVER['REMOTE_USER']]);
  if ($stmt->rowCount() > 0) {
    $buttonText = "My Post";
    $buttonLink = "edit_post.php";
  }
}
?>
<!-- nav.php -->
<nav class="main-nav">
  <div class="nav-left">
    <a href='index.php' style="padding: 0; margin: 0">
      <h1>UVM Sublets</h1>
    </a>
  </div>
  <div class="nav-right">
    <div class="nav-links">
      <a class="<?php if ($pathParts['filename'] == 'index') {
        echo 'activePage';
      } ?>" href="index.php">Home</a>
      <a class="<?php if ($pathParts['filename'] == 'map') {
        echo 'activePage';
      } ?>" href="map.php">Map</a>
      <a class="<?php if ($pathParts['filename'] == 'new_post' || $pathParts['filename'] == 'edit_post') {
        echo 'activePage';
      } ?>" href="<?= $buttonLink; ?>"><?= $buttonText; ?></a>
    </div>
    <p class="nav-user">Hello, <?= $_SERVER['REMOTE_USER'] ?? 'Guest'; ?></p>
  </div>
</nav>

<!-- <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">
      The service we use to display our map is currently down.
</div> -->