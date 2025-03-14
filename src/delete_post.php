<?php
include 'connect-db.php';

// Only allow admin (aperkel) to delete posts
if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}

if (!isset($_GET['id'])) {
    die("Post ID not specified.");
}

$id = intval($_GET['id']);
$stmt = $pdo->prepare("DELETE FROM sublets WHERE id = ?");
if ($stmt->execute([$id])) {
    header("Location: index.php");
    exit;
} else {
    echo "Error deleting post.";
}
?>