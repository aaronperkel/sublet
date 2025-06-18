<?php
require_once 'auth.php';
start_session();
require_login();
require_once 'connect-db.php';
require_once 'db_operations.php'; // Ensure this is included

if (!isset($_GET['id'])) {
    header("Location: index.php?error=delete_id_missing");
    exit;
}
$post_id = intval($_GET['id']);
$current_user = get_current_user();

$result = deleteSublet($pdo, $post_id, $current_user);

if ($result['success']) {
    if (!empty($result['images_to_delete'])) {
        foreach ($result['images_to_delete'] as $image_path) {
            // Check if directory is writable before attempting to delete file
            if (file_exists($image_path) && is_writable(dirname($image_path))) {
                @unlink($image_path); // Suppress error if file already gone or other minor issues
            } else {
                // Log error: "Failed to delete image file or directory not writable: $image_path"
                // error_log("Failed to delete image file or directory not writable: " . $image_path . " for post ID " . $post_id);
            }
        }
    }
    // Email notification (can be re-enabled if necessary, using $current_user and $post_id)
    // $to = 'aperkel@uvm.edu';
    // $subject = 'Sublet Post Deleted';
    // $message_body = "The sublet post (ID: $post_id) formerly owned by $current_user has been deleted by the owner.";
    // mail($to, $subject, $message_body);

    header("Location: index.php?status=deleted_successfully");
    exit;
} else {
    $error_message = urlencode($result['message'] ?? 'delete_failed_unknown_reason');
    // error_log("Delete failed for post ID $post_id by user $current_user: " . ($result['message'] ?? 'Unknown reason'));
    header("Location: index.php?error=" . $error_message);
    exit;
}
?>