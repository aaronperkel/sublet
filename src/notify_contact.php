<?php
include 'connect-db.php';

$postId = intval($_GET['post_id']);
$owner  = $_GET['owner'] ?? '';
$from   = $_SERVER['REMOTE_USER'] ?? 'Guest';

$to      = 'aperkel@uvm.edu';
$subject = "UVM Sublets: Contact clicked on post #{$postId}";
$message = "User {$from} clicked ‘Contact’ on post #{$postId} (owned by {$owner}).";

// send the notification
mail($to, $subject, $message);