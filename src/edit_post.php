<?php
require_once 'auth.php';
start_session(); // Start session early
require_login();
// db_operations.php is included via top.php
// connect-db.php is included via top.php
// $pdo should be available after top.php, but we need it before for initial post fetch.
// Let's include connect-db and db_operations explicitly here before top.php for this page's structure.
require_once 'connect-db.php';
require_once 'db_operations.php';


$current_user = get_current_user();
$post_id = null;
$userPost = null; // This will hold the post data fetched by getSubletById
$errors = [];   // Using $errors array for consistency, was $update_message

if (isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $userPost = getSubletById($pdo, $post_id, $current_user); // Fetch using new function
}

if (!$userPost) {
    header("Location: index.php?error=post_not_found_or_unauthorized");
    exit;
}

$form_data = $userPost; // For repopulating form, merge with POST on error
// $errors array is already initialized from the restored code.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['post_id_hidden']) || intval($_POST['post_id_hidden']) !== $post_id) {
        $errors['form'] = "Error: Form submission mismatch.";
    } else {
        $target_dir = "./public/images/"; // Define once at the start of POST handling
        $form_data = array_merge($form_data, $_POST); // Update form_data with new attempts
        $validation_errors = validateSubletData($_POST, false, $pdo, $post_id); // Pass false for is_new_post
        $errors = array_merge($errors, $validation_errors);

        // (Stretch Goal) Handle New Thumbnail Upload for Edit
        // This code runs regardless of $validation_errors for other fields,
        // as user might want to just change image even if other data is not valid yet for updateSublet.
        // However, $errors from validateUploadedFile will be added to $errors array.
        if (isset($_FILES['new_thumbnail_image']) && $_FILES['new_thumbnail_image']['error'] === UPLOAD_ERR_OK) {
            if (validateUploadedFile($_FILES['new_thumbnail_image'], $errors, 'new_thumbnail_image')) {
                // $target_dir defined above
                if (!is_dir($target_dir)) {mkdir($target_dir, 0755, true);}

                $new_unique_thumb_name = generateUniqueFilename($_FILES['new_thumbnail_image']['name'], $current_user, $post_id, 'thumb_edit');
                $new_thumbnail_path = $target_dir . $new_unique_thumb_name;

                if (move_uploaded_file($_FILES['new_thumbnail_image']['tmp_name'], $new_thumbnail_path)) {
                    $old_thumbnail_path = $userPost['image_url']; // Get old path from pre-loaded $userPost
                    if (updateSubletThumbnail($pdo, $post_id, $new_thumbnail_path, $current_user)) {
                        if ($old_thumbnail_path && file_exists($old_thumbnail_path) && $old_thumbnail_path !== $new_thumbnail_path) {
                            if (strpos($old_thumbnail_path, 'default') === false && is_writable($old_thumbnail_path)) {
                                 @unlink($old_thumbnail_path);
                            }
                        }
                        $userPost['image_url'] = $new_thumbnail_path; // Update $userPost for current page display
                        // Add to success messages, or create a dedicated one.
                        $errors['success_thumbnail'] = ($errors['success_thumbnail'] ?? "") . " Main image updated successfully.";
                    } else {
                        $errors['new_thumbnail_image'] = "Failed to update main image in database.";
                        if (file_exists($new_thumbnail_path)) {@unlink($new_thumbnail_path);}
                    }
                } else {
                    $errors['new_thumbnail_image'] = "Failed to move new main image.";
                }
            }
        } // If new_thumbnail_image error but not NO_FILE, validateUploadedFile would set an error.

        // Additional Images Upload
        if (isset($_FILES['additional_images'])) {
            $stmtMaxSort = $pdo->prepare("SELECT MAX(sort_order) as max_so FROM sublet_images WHERE sublet_id = ?");
            $stmtMaxSort->execute([$post_id]);
            $max_so = $stmtMaxSort->fetchColumn() ?? 0;
                    // $target_dir defined above

            foreach ($_FILES['additional_images']['name'] as $key => $name) {
                if (!isset($_FILES['additional_images']['error'][$key]) || $_FILES['additional_images']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file_data = [
                    'name' => $_FILES['additional_images']['name'][$key],
                    'type' => $_FILES['additional_images']['type'][$key],
                    'tmp_name' => $_FILES['additional_images']['tmp_name'][$key],
                    'error' => $_FILES['additional_images']['error'][$key],
                    'size' => $_FILES['additional_images']['size'][$key]
                ];
                $additional_image_field_name = 'additional_images_' . $key;
                if (validateUploadedFile($file_data, $errors, $additional_image_field_name)) {
                    if (!is_dir($target_dir)) {mkdir($target_dir, 0755, true);}
                    $unique_add_img_name = generateUniqueFilename($name, $current_user, $post_id, 'add_edit');
                    $add_img_path = $target_dir . $unique_add_img_name;
                    if (move_uploaded_file($file_data['tmp_name'], $add_img_path)) {
                        addSubletImage($pdo, $post_id, $add_img_path, ++$max_so);
                    } else {
                         $errors[$additional_image_field_name] = "Failed to move additional image " . htmlspecialchars($name) . ".";
                    }
                }
            }
        }

        // Update other sublet data only if there were no validation errors for them
        if (empty($validation_errors)) { // Check only $validation_errors, not $errors which might have image upload issues
            $update_result = updateSublet($pdo, $post_id, $_POST, $current_user);
            if ($update_result['success']) {
                $success_message = $update_result['message'] ?? 'Post details (price, semester, description) updated successfully!';
                // Add to success messages, or create a dedicated one.
                $errors['success_details'] = ($errors['success_details'] ?? "") . " " . $success_message;
            } else {
                $errors = array_merge($errors, $update_result['errors'] ?? ['database_details' => 'An unknown error occurred during details update.']);
            }
        }

        // After all operations, re-fetch userPost to reflect all changes on the page
        if(empty($errors) || isset($errors['success_thumbnail']) || isset($errors['success_details'])) {
            // only re-fetch if there were no fatal errors preventing updates or if some success occurred.
            $userPost = getSubletById($pdo, $post_id, $current_user); // Use the new function
            $form_data = $userPost; // Update form data with fresh DB values
        }
        // Consolidate success messages if multiple parts succeeded
        $final_success_messages = [];
        if(isset($errors['success_thumbnail'])) $final_success_messages[] = $errors['success_thumbnail'];
        if(isset($errors['success_details'])) $final_success_messages[] = $errors['success_details'];
        if(!empty($final_success_messages)){
            $errors['success'] = implode(" ", $final_success_messages);
            unset($errors['success_thumbnail'], $errors['success_details']); // cleanup individual success flags
        }


    }
}
$google_api_key = $_ENV['GOOGLE_API']; // Definition before top.php if top.php needs it
include 'top.php'; // This now correctly comes after initial data fetch and POST logic
?>
<main>
    <?php if (!empty($errors['form'])): ?>
        <p style='color:red;'><?= htmlspecialchars($errors['form']) ?></p>
    <?php elseif (!empty($errors) && !isset($errors['success'])): // General errors if not form error and not success ?>
        <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $field => $error_message): if($field === 'success' || $field === 'success_thumbnail' || $field === 'success_details') continue; ?>
                    <li><?= htmlspecialchars(is_string($field) ? ucfirst(str_replace('_', ' ', $field)) : 'Error') ?>: <?= htmlspecialchars($error_message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors['success'])): ?>
        <p style='color:green;'><?= htmlspecialchars($errors['success']) ?></p>
    <?php endif; ?>

    <form method="post" action="edit_post.php?id=<?= htmlspecialchars($userPost['id']) ?>" class="new-post-form" enctype="multipart/form-data">
        <input type="hidden" name="post_id_hidden" value="<?= htmlspecialchars($userPost['id']) ?>">

        <div class="flex-row">
            <div>
                <label>Current Thumbnail:</label>
                <img src="<?= htmlspecialchars($userPost['image_url'] ?? './public/images/default_sublet_image.png') ?>" alt="Current sublet image" style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">

                <label for="new_thumbnail_image">Change Main Image (Optional):</label>
                <input type="file" id="new_thumbnail_image" name="new_thumbnail_image" accept="image/*">
                <?php if (isset($errors['new_thumbnail_image'])): ?><p class="error-text" style="color:red;"><?= htmlspecialchars($errors['new_thumbnail_image']) ?></p><?php endif; ?>

                <label for="additional_images" class="custom-file-upload" style="margin-top:10px;">Add More Images</label>
                <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                <?php
                // Loop through potential additional image errors
                foreach ($errors as $field => $error_msg) {
                    if (strpos($field, 'additional_images_') === 0) {
                        echo '<p class="error-text" style="color:red;">' . htmlspecialchars($error_msg) . '</p>';
                    }
                }
                ?>
            </div>
            <div>
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01"
                    value="<?= htmlspecialchars($form_data['price'] ?? '') ?>" required>
                <?php if (isset($errors['price'])): ?><p class="error-text" style="color:red;"><?= htmlspecialchars($errors['price']) ?></p><?php endif; ?>
            </div>
        </div>
        <label for="address">Address: (Read-only)</label>
        <input type="text" id="address" name="address" value="<?= htmlspecialchars($form_data['address'] ?? '') ?>" readonly>
        <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($form_data['lat'] ?? '') ?>">
        <input type="hidden" id="lon" name="lon" value="<?= htmlspecialchars($form_data['lon'] ?? '') ?>">

        <label for="semester">Semester:</label>
        <select id="semester" name="semester" required>
            <?php
            $semesters_available = ['summer25', 'fall25', 'spring26', 'summer26', 'fall26', 'spring27']; // Match validation
            foreach ($semesters_available as $sem_val): ?>
                <option value="<?= $sem_val ?>" <?= ($form_data['semester'] ?? '') === $sem_val ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace(['summer', 'fall', 'spring'], ['Summer ', 'Fall ', 'Spring '], $sem_val)) // Basic formatting ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['semester'])): ?><p class="error-text" style="color:red;"><?= htmlspecialchars($errors['semester']) ?></p><?php endif; ?>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4"
            cols="50"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
        <?php if (isset($errors['description'])): ?><p class="error-text" style="color:red;"><?= htmlspecialchars($errors['description']) ?></p><?php endif; ?>

        <div style="display:flex; gap:1em; margin-top:1em; justify-content: space-between;">
            <input type="submit" value="Update Post">
        </div>
</main>
<?php include 'footer.php'; ?>
                $userPost = getSubletById($pdo, $post_id, $current_user); // Use the new function
                $form_data = $userPost; // Update form data with fresh DB values
                $errors['success'] = $success_message; // Use errors array to pass success message too
            } else {
                $errors = array_merge($errors, $update_result['errors'] ?? ['database' => 'An unknown error occurred during update.']);
            }
        }
    }
}
$google_api_key = $_ENV['GOOGLE_API']; // Definition before top.php if top.php needs it
include 'top.php'; // This now correctly comes after initial data fetch and POST logic
?>
<main>
    <?php if (!empty($errors['form'])): ?>
        <p style='color:red;'><?= htmlspecialchars($errors['form']) ?></p>
    <?php elseif (!empty($errors) && !isset($errors['success'])): // General errors if not form error and not success ?>
        <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $field => $error_message): if($field === 'success') continue; ?>
                    <li><?= htmlspecialchars(is_string($field) ? ucfirst(str_replace('_', ' ', $field)) : 'Error') ?>: <?= htmlspecialchars($error_message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors['success'])): ?>
        <p style='color:green;'><?= htmlspecialchars($errors['success']) ?></p>
    <?php endif; ?>

    <form method="post" action="edit_post.php?id=<?= htmlspecialchars($userPost['id']) ?>" class="new-post-form" enctype="multipart/form-data">
        <input type="hidden" name="post_id_hidden" value="<?= htmlspecialchars($userPost['id']) ?>">

        <div class="flex-row">
            <div>
                <label>Current Thumbnail:</label>
                <img src="<?= htmlspecialchars($userPost['image_url'] ?? './public/images/default_sublet_image.png') ?>" alt="Current sublet image" style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">
                <label for="additional_images" class="custom-file-upload">Add More Images</label>
                <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple>
            </div>
            <div>
                <label for="price">Price:</label>
                <input type="number" id="price" name="price" step="0.01"
                    value="<?= htmlspecialchars($form_data['price'] ?? '') ?>" required>
            </div>
        </div>
        <label for="address">Address: (Read-only)</label>
        <input type="text" id="address" name="address" value="<?= htmlspecialchars($form_data['address'] ?? '') ?>" readonly>
        <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($form_data['lat'] ?? '') ?>">
        <input type="hidden" id="lon" name="lon" value="<?= htmlspecialchars($form_data['lon'] ?? '') ?>">

        <label for="semester">Semester:</label>
        <select id="semester" name="semester" required>
            <?php
            $semesters_available = ['summer25', 'fall25', 'spring26', 'summer26', 'fall26', 'spring27']; // Match validation
            foreach ($semesters_available as $sem_val): ?>
                <option value="<?= $sem_val ?>" <?= ($form_data['semester'] ?? '') === $sem_val ? 'selected' : '' ?>>
                    <?= ucfirst(str_replace(['summer', 'fall', 'spring'], ['Summer ', 'Fall ', 'Spring '], $sem_val)) // Basic formatting ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4"
            cols="50"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>

        <div style="display:flex; gap:1em; margin-top:1em; justify-content: space-between;">
            <input type="submit" value="Update Post">
        </div>
</main>
<?php include 'footer.php'; ?>