<?php
include 'connect-db.php';
$subletId = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order ASC");
$stmt->execute([$subletId]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($images);
?>