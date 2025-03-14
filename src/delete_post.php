<?php
include 'connect-db.php';

// Only allow admin (aperkel) to delete posts
if (!isset($_SERVER['REMOTE_USER']) || $_SERVER['REMOTE_USER'] !== 'aperkel') {
    die("Access denied.");
}

if (!isset($_GET['id'])) {
    die("Post ID not specified.");
}
;

$id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT username FROM sublets WHERE id = ?");
$stmt->execute([$id]);
$username = $stmt->fetchColumn();

$stmt = $pdo->prepare("DELETE FROM sublets WHERE id = ?");
if ($stmt->execute([$id])) {

    $to = 'aperkel@uvm.edu';
    $subject = 'Sublet Post Deleted';
    $message = "The sublet post for user $username has been deleted.";
    mail($to, $subject, $message);

    header("Location: index.php");
    exit;
} else {
    echo "Error deleting post.";
}
?>