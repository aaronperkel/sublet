<!-- nav.php -->
<nav class="main-nav">
  <div class="nav-left">
    <h1>UVM Sublets</h1>
  </div>
  <div class="nav-right">
    <div class="nav-links">
      <a class="<?php if ($pathParts['filename'] == 'index') { print 'activePage'; } ?>" href="index.php">Home</a>
      <a class="<?php if ($pathParts['filename'] == 'map') { print 'activePage'; } ?>" href="map.php">Map</a>
      <a class="<?php if ($pathParts['filename'] == 'new_post') { print 'activePage'; } ?>" href="new_post.php">New Post</a>
    </div>
    <p class="nav-user">Hello, <?php print $_SERVER['REMOTE_USER'] ?? 'Guest'; ?></p>
  </div>
</nav>