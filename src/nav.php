<!-- nav.php -->
<header>
    <h1>Sublet Website</h1>
</header>

<nav>
    <a class="<?php
    if ($pathParts['filename'] == 'index') {
        print 'activePage';
    }
    ?>" href="index.php">Home</a>

    <a class="<?php
    if ($pathParts['filename'] == 'map') {
        print 'activePage';
    }
    ?>" href="map.php">Map</a>

    <a class="<?php
    if ($pathParts['filename'] == 'new_post') {
        print 'activePage';
    }
    ?>" href="new_post.php">New Post</a>

    <p>Hello, aperkel</p>
</nav>