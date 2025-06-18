<?php
require_once 'auth.php';
start_session();
require_login();
// It's important that top.php is included after session functions and auth checks,
// especially if top.php produces any output or relies on session state.
// connect-db.php is included within top.php, so $pdo will be available after top.php.
include 'top.php'; // This includes connect-db.php and db_operations.php

$current_user = get_current_user(); // From auth.php
$google_api_key = $_ENV['GOOGLE_API'];

$form_data = $_POST; // Keep original POST data for repopulating form if errors
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // File upload for thumbnail (main image_url)
    $thumbnail_path = null;
    $target_dir = "./public/images/"; // Ensure this directory exists and is writable

    if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if (validateUploadedFile($_FILES['thumbnail_image'], $errors, 'thumbnail_image')) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true); // Ensure dir exists, create if not
            }
            $unique_thumb_name = generateUniqueFilename($_FILES['thumbnail_image']['name'], $current_user, null, 'thumb');
            $thumbnail_path = $target_dir . $unique_thumb_name;
            if (!move_uploaded_file($_FILES['thumbnail_image']['tmp_name'], $thumbnail_path)) {
                $errors['thumbnail_image'] = "Failed to move uploaded thumbnail image.";
                $thumbnail_path = null;
            }
        }
        // If validateUploadedFile is false, $errors['thumbnail_image'] is already set by the function.
    } else {
        // This means thumbnail_image input was present but no file was uploaded, or an error occurred that wasn't UPLOAD_ERR_OK
        if (!isset($_FILES['thumbnail_image']) || $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_NO_FILE) {
             $errors['thumbnail_image'] = "Thumbnail image is required.";
        } else {
            // If error is something else, validateUploadedFile would have caught it if called.
            // Or, it's already handled if we call validateUploadedFile universally.
            // For safety, let's ensure validateUploadedFile is called for any real upload attempt.
            // The current structure with nested ifs implies validateUploadedFile is only called if error is not NO_FILE.
            // The plan's logic is slightly different, let's stick to the plan:
            // validateUploadedFile handles UPLOAD_ERR_OK. If not OK and not NO_FILE, it adds error.
            // Then we add "required" error if it IS NO_FILE.
            // This seems fine.
        }
    }

    // Prepare data for validation and insertion
    $data_to_validate = $_POST; // price, address, semester, lat, lon, description
    $data_to_validate['thumbnail_url'] = $thumbnail_path;

    // Server-side validation using the new function
    $validation_errors = validateSubletData($data_to_validate, true, $pdo); // true for new post
    $errors = array_merge($errors, $validation_errors);

    // Distance validation (example, could be part of validateSubletData if $pdo is passed and used there)
    if (!empty($data_to_validate['lat']) && !empty($data_to_validate['lon'])) {
        $campusLat = 44.477435;
        $campusLon = -73.195323;
        // Basic Haversine function - consider moving to db_operations or a utility file if used elsewhere
        $latFrom = deg2rad($campusLat); $lonFrom = deg2rad($campusLon);
        $latTo = deg2rad(floatval($data_to_validate['lat'])); $lonTo = deg2rad(floatval($data_to_validate['lon']));
        $latDelta = $latTo - $latFrom; $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        $distance = $angle * 3959; // Earth radius in miles
        if ($distance > 50) { // Max 50 miles
            $errors['distance'] = "The location is more than 50 miles from campus (calculated: " . round($distance, 2) . " miles).";
        }
    }


    if (empty($errors)) {
        $add_result = addSublet($pdo, $data_to_validate, $current_user);
        if ($add_result['success']) {
            $subletId = $add_result['sublet_id'];

            // Handle additional images
            if (isset($_FILES['additional_images'])) {
                $sort_order_counter = 1;
                foreach ($_FILES['additional_images']['name'] as $key => $name) {
                    // Skip if no file was uploaded for this specific input in the array
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
                        // $target_dir should already be defined and checked/created with thumbnail
                        if (!is_dir($target_dir)) { // Double check just in case, though should not be needed
                           mkdir($target_dir, 0755, true);
                        }
                        $unique_add_img_name = generateUniqueFilename($name, $current_user, $subletId, 'add');
                        $add_img_path = $target_dir . $unique_add_img_name;
                        if (move_uploaded_file($file_data['tmp_name'], $add_img_path)) {
                            addSubletImage($pdo, $subletId, $add_img_path, $sort_order_counter++);
                        } else {
                            $errors[$additional_image_field_name] = "Failed to move additional image " . htmlspecialchars($name) . ".";
                        }
                    }
                    // If validateUploadedFile returned false, $errors array is already updated by the function.
                }
            }
            // Email notification (optional)
            // $to = 'aperkel@uvm.edu'; $subject = 'New Sublet Post Created';
            // $message = "A new sublet post (ID: $subletId) has been created by $current_user.";
            // mail($to, $subject, $message);

            header("Location: index.php?status=post_created&id=" . $subletId);
            exit;
        } else {
            $errors = array_merge($errors, $add_result['errors'] ?? ['database' => 'An unknown database error occurred.']);
            // If thumbnail was uploaded but DB insert failed, consider deleting the uploaded thumbnail.
            if ($thumbnail_path && file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        }
    } else {
        // If validation errors occurred and thumbnail was uploaded, delete it.
        if ($thumbnail_path && file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
    }
    // If execution reaches here, it's because of errors.
    // $form_data (from $_POST) and $errors are available to the HTML form below.
}
?>

<main>
    <h1>Create New Sublet Post</h1>

    <?php if (!empty($errors)): ?>
        <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $field => $error_message): ?>
                    <li><?= htmlspecialchars(is_string($field) ? ucfirst(str_replace('_', ' ', $field)) : 'Error') ?>: <?= htmlspecialchars($error_message) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="new-post-container">
        <div class="form-container">
            <form method="post" action="new_post.php" class="new-post-form" enctype="multipart/form-data">
                <div>
                    <label for="thumbnail_image">Main Image (Thumbnail):</label>
                    <input type="file" id="thumbnail_image" name="thumbnail_image" accept="image/*" required>
                    <?php if (isset($errors['thumbnail_image'])): ?><p class="error-text"><?= htmlspecialchars($errors['thumbnail_image']) ?></p><?php endif; ?>
                </div>

                <div class="flex-row">
                    <div>
                        <label for="price">Price (per month):</label>
                        <input type="number" id="price" name="price" step="0.01" value="<?= htmlspecialchars($form_data['price'] ?? '') ?>" required>
                        <?php if (isset($errors['price'])): ?><p class="error-text"><?= htmlspecialchars($errors['price']) ?></p><?php endif; ?>
                    </div>
                    <div>
                        <label for="semester">Semester:</label>
                        <select id="semester" name="semester" required>
                            <option value="" disabled <?= !isset($form_data['semester']) ? 'selected' : '' ?>>Select semester</option>
                            <option value="summer25" <?= ($form_data['semester'] ?? '') === 'summer25' ? 'selected' : '' ?>>Summer 2025</option>
                            <option value="fall25" <?= ($form_data['semester'] ?? '') === 'fall25' ? 'selected' : '' ?>>Fall 2025</option>
                            <option value="spring26" <?= ($form_data['semester'] ?? '') === 'spring26' ? 'selected' : '' ?>>Spring 2026</option>
                            <option value="summer26" <?= ($form_data['semester'] ?? '') === 'summer26' ? 'selected' : '' ?>>Summer 2026</option>
                            <option value="fall26" <?= ($form_data['semester'] ?? '') === 'fall26' ? 'selected' : '' ?>>Fall 2026</option>
                            <option value="spring27" <?= ($form_data['semester'] ?? '') === 'spring27' ? 'selected' : '' ?>>Spring 2027</option>
                        </select>
                        <?php if (isset($errors['semester'])): ?><p class="error-text"><?= htmlspecialchars($errors['semester']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div>
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" placeholder="Enter a valid address" value="<?= htmlspecialchars($form_data['address'] ?? '') ?>" required>
                    <?php if (isset($errors['address'])): ?><p class="error-text"><?= htmlspecialchars($errors['address']) ?></p><?php endif; ?>
                </div>
                <input type="hidden" id="lat" name="lat" value="<?= htmlspecialchars($form_data['lat'] ?? '') ?>">
                <input type="hidden" id="lon" name="lon" value="<?= htmlspecialchars($form_data['lon'] ?? '') ?>">
                <?php if (isset($errors['lat'])): ?><p class="error-text"><?= htmlspecialchars($errors['lat']) ?></p><?php endif; ?>
                <?php if (isset($errors['lon'])): ?><p class="error-text"><?= htmlspecialchars($errors['lon']) ?></p><?php endif; ?>
                 <?php if (isset($errors['distance'])): ?><p class="error-text"><?= htmlspecialchars($errors['distance']) ?></p><?php endif; ?>


                <div>
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="4" cols="50" placeholder="Describe your listing here!"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                    <?php if (isset($errors['description'])): ?><p class="error-text"><?= htmlspecialchars($errors['description']) ?></p><?php endif; ?>
                </div>

                <div>
                    <label for="additional_images">Additional Images (Optional):</label>
                    <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple>
                    <?php if (isset($errors['additional_images'])): ?><p class="error-text"><?= htmlspecialchars(is_array($errors['additional_images']) ? implode(', ',$errors['additional_images']) : $errors['additional_images']) ?></p><?php endif; ?>
                </div>

                <input type="submit" value="Add Post">
            </form>
        </div>
        <div class="map-container">
            <div id="map"></div> <!-- Map for address picking -->
        </div>
    </div>
</main>

<script>
    // Ensure this initMap is correctly scoped or called after Google Maps API loads
    var map, geocoder, marker; // Declare globally for this script block or attach to window
            zoom: 15,
            center: { lat: 44.477435, lng: -73.195323 }, // UVM Coordinates
            mapId: '3bd5c9ae8c849605' // Replace with your actual Map ID if you have one
        });
        geocoder = new google.maps.Geocoder();

        marker = new google.maps.marker.AdvancedMarkerElement({
            map: map,
            position: { lat: 44.477435, lng: -73.195323 },
            title: "Sublet Location (Drag or type address)"
            // draggable: true // If you want marker to be draggable
        });

        // Listener for address autocomplete
        let autocomplete = new google.maps.places.Autocomplete(document.getElementById('address'), {
            types: ['geocode']
        });
        autocomplete.addListener('place_changed', function () {
            let place = autocomplete.getPlace();
            if (place.geometry && place.geometry.location) {
                document.getElementById('lat').value = place.geometry.location.lat();
                document.getElementById('lon').value = place.geometry.location.lng();
                marker.position = place.geometry.location;
                map.setCenter(place.geometry.location);
            } else {
                // Handle case where address is typed but not selected from suggestions
                // Or if place has no geometry
                geocodeAddress(geocoder, map, marker);
            }
        });
        // Optional: geocode on address input blur if not selected from autocomplete
        // document.getElementById('address').addEventListener('blur', function() {
        //    geocodeAddress(geocoder, map, marker);
        // });
    }

    function geocodeAddress(geocoder, resultsMap, resultsMarker) {
        const address = document.getElementById("address").value;
        geocoder.geocode({ address: address }, (results, status) => {
            if (status === "OK") {
                resultsMap.setCenter(results[0].geometry.location);
                resultsMarker.position = results[0].geometry.location;
                document.getElementById('lat').value = results[0].geometry.location.lat();
                document.getElementById('lon').value = results[0].geometry.location.lng();
            } else {
                // alert("Geocode was not successful for the following reason: " + status);
                // Clear lat/lon if geocode failed? Or keep old values?
                // document.getElementById('lat').value = '';
                // document.getElementById('lon').value = '';
            }
        });
    }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_api_key; ?>&libraries=places,marker&callback=initMap"></script>

<?php include 'footer.php'; ?>