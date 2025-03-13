<?php
// Assuming $pdo is already defined (from connect-db.php) and REMOTE_USER is set
$buttonText = "New Post";
$_SERVER['REMOTE_USER'] = 'bkamont';
if (isset($_SERVER['REMOTE_USER'])) {
    $stmt = $pdo->prepare("SELECT id FROM sublets WHERE username = ?");
    $stmt->execute([$_SERVER['REMOTE_USER']]);
    if ($stmt->rowCount() > 0) {
        $buttonText = "My Post";
    }
}
?>
<nav class="main-nav">
  <div class="nav-left">
    <h1>UVM Sublets</h1>
  </div>
  <div class="nav-right">
    <div class="nav-links">
      <a class="<?php if ($pathParts['filename'] == 'index') { print 'activePage'; } ?>" href="index.php">Home</a>
      <a class="<?php if ($pathParts['filename'] == 'map') { print 'activePage'; } ?>" href="map.php">Map</a>
      <a class="<?php if ($pathParts['filename'] == 'new_post') { print 'activePage'; } ?>" href="new_post.php"><?php echo $buttonText; ?></a>
    </div>
    <p class="nav-user">Hello, <?php print $_SERVER['REMOTE_USER'] ?? 'Guest'; ?></p>
  </div>
</nav>