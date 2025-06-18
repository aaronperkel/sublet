<?php
// connect-db.php is expected to be included by the calling script (e.g., top.php)
// or explicitly included here if functions are called from contexts where $pdo isn't globally available.
// For now, assume $pdo will be passed or available.

/**
 * Fetches a single sublet by its ID.
 * If username is provided, it also checks if the sublet belongs to that user.
 */
function getSubletById(PDO $pdo, int $id, string $username = null) {
    $sql = "SELECT * FROM sublets WHERE id = ?";
    $params = [$id];
    if ($username !== null) {
        $sql .= " AND username = ?";
        $params[] = $username;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Fetches all sublets, optionally applying filters.
 */
function getAllSublets(PDO $pdo, array $filters = []) {
    $sql = "SELECT s.*, si.image_url as first_image_url "; // Get first image for grid
    $sql .= "FROM sublets s ";
    $sql .= "LEFT JOIN (SELECT sublet_id, image_url, ROW_NUMBER() OVER(PARTITION BY sublet_id ORDER BY sort_order ASC) as rn FROM sublet_images) si ON s.id = si.sublet_id AND si.rn = 1 ";

    $conditions = [];
    $params = [];

    // Campus coordinates for distance calculation
    $campusLat = 44.477435;
    $campusLon = -73.195323;

    // Distance filter: Haversine formula
    // Note: This filters AFTER fetching all results if not done carefully.
    // For performance on large datasets, this might need to be part of the WHERE clause directly.
    // However, PDO doesn't easily support dynamic parts of formulas in prepared statements like this.
    // Let's build it into the SQL string for now, ensuring inputs are safe.

    $distanceSubQuery = sprintf(
        "3959 * acos(cos(radians(%f)) * cos(radians(s.lat)) * cos(radians(s.lon) - radians(%f)) + sin(radians(%f)) * sin(radians(s.lat)))",
        $campusLat, $campusLon, $campusLat
    );

    if (!empty($filters['min_price'])) {
        $conditions[] = "s.price >= ?";
        $params[] = $filters['min_price'];
    }
    if (!empty($filters['max_price'])) {
        $conditions[] = "s.price <= ?";
        $params[] = $filters['max_price'];
    }
    if (!empty($filters['semester']) && $filters['semester'] !== 'all') {
        $conditions[] = "s.semester = ?";
        $params[] = $filters['semester'];
    }
    // Max distance filter
    if (!empty($filters['max_distance'])) {
        // Ensure max_distance is numeric before using in SQL
        if (is_numeric($filters['max_distance'])) {
             $conditions[] = $distanceSubQuery . " <= ?";
             $params[] = (float)$filters['max_distance'];
        }
    }


    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY s.posted_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no specific first_image_url was found from sublet_images, use the sublets.image_url as fallback
    foreach ($results as $key => $row) {
        if (empty($row['first_image_url']) && !empty($row['image_url'])) {
            $results[$key]['first_image_url'] = $row['image_url'];
        } elseif (empty($row['first_image_url'])) {
             $results[$key]['first_image_url'] = './public/images/default_sublet_image.png'; // Provide a default
        }
    }
    return $results;
}


/**
 * Fetches data needed for filters (max price, max distance, distinct semesters).
 */
function getFilterData(PDO $pdo) {
    $data = [];

    $stmtMaxPrice = $pdo->query("SELECT MAX(price) as max_price FROM sublets");
    $maxPriceResult = $stmtMaxPrice->fetch(PDO::FETCH_ASSOC);
    $maxPrice = $maxPriceResult['max_price'] ?? 3000;
    $data['maxPriceRounded'] = ceil($maxPrice / 50) * 50;

    // Campus coordinates for distance calculation
    $campusLat = 44.477435;
    $campusLon = -73.195323;
    $distanceQuery = sprintf(
        "SELECT MAX(3959 * acos(cos(radians(%f)) * cos(radians(lat)) * cos(radians(lon) - radians(%f)) + sin(radians(%f)) * sin(radians(lat)))) as max_distance FROM sublets",
        $campusLat, $campusLon, $campusLat
    );
    $stmtDistance = $pdo->query($distanceQuery);
    $distanceResult = $stmtDistance->fetch(PDO::FETCH_ASSOC);
    $maxDistance = $distanceResult['max_distance'] ?? 20; // Default max distance if table is empty
    $data['maxDistanceRounded'] = ceil($maxDistance * 2) / 2; // Round to nearest 0.5

    $stmtSem = $pdo->query("SELECT DISTINCT semester FROM sublets ORDER BY semester");
    $data['semesters'] = $stmtSem->fetchAll(PDO::FETCH_COLUMN);

    return $data;
}

/**
 * Fetches all images for a given sublet_id from the sublet_images table.
 */
function getSubletImages(PDO $pdo, int $sublet_id) {
    $stmt = $pdo->prepare("SELECT image_url FROM sublet_images WHERE sublet_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$sublet_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Validates sublet data.
 * Returns an array of errors. Empty if valid.
 */
function validateSubletData(array $data, bool $is_new_post = true, PDO $pdo = null, ?int $sublet_id_to_ignore = null) {
    $errors = [];

    if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
        $errors['price'] = "Price must be a positive number.";
    }
    if (empty(trim($data['address']))) {
        $errors['address'] = "Address is required.";
    }
    // Example allowed semesters - this could be fetched from DB or config
    $allowed_semesters = ['summer25', 'fall25', 'spring26', 'summer26', 'fall26', 'spring27']; // Expand as needed
    if (empty($data['semester']) || !in_array($data['semester'], $allowed_semesters)) {
        $errors['semester'] = "Please select a valid semester.";
    }
    if (empty($data['lat']) || !is_numeric($data['lat']) || $data['lat'] < -90 || $data['lat'] > 90) {
        $errors['lat'] = "Invalid latitude.";
    }
    if (empty($data['lon']) || !is_numeric($data['lon']) || $data['lon'] < -180 || $data['lon'] > 180) {
        $errors['lon'] = "Invalid longitude.";
    }
    if (empty(trim($data['description']))) {
        $errors['description'] = "Description is required.";
    }
    // For new posts, thumbnail_url is essential (actual file existence check is in new_post.php)
    if ($is_new_post && empty($data['thumbnail_url'])) {
        // This validation might be tricky here if path isn't set yet.
        // Consider validating file upload success in the controller (new_post.php)
        // and passing the path for storage here.
        // For now, assume 'thumbnail_url' key presence means it's handled.
    }
    return $errors;
}

/**
 * Adds a new sublet to the database.
 */
function addSublet(PDO $pdo, array $data, string $username) {
    // Note: Thumbnail URL is expected in $data['thumbnail_url']
    // Validation should be done before calling this function using validateSubletData.
    $sql = "INSERT INTO sublets (image_url, price, address, semester, lat, lon, description, username)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['thumbnail_url'], // This is the main display image
            $data['price'],
            $data['address'],
            $data['semester'],
            $data['lat'],
            $data['lon'],
            $data['description'],
            $username
        ]);
        return ['success' => true, 'sublet_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        // Log error $e->getMessage();
        return ['success' => false, 'errors' => ['database' => 'Failed to add sublet. ' . $e->getMessage()]];
    }
}

/**
 * Updates an existing sublet.
 */
function updateSublet(PDO $pdo, int $sublet_id, array $data, string $username) {
    // Validation should be done before calling this function.
    // For this version, address, lat, lon are not updatable via edit form to keep it simple.
    // Thumbnail (sublets.image_url) is also not updated here, only additional images.
    $sql = "UPDATE sublets SET price = ?, semester = ?, description = ?
            WHERE id = ? AND username = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $data['price'],
            $data['semester'],
            $data['description'],
            $sublet_id,
            $username
        ]);
        if ($stmt->rowCount() > 0) {
             return ['success' => true];
        } else {
             // Either no rows updated (data was same) or ID/user mismatch not caught by prior checks
             // Could also mean the user doesn't own the post or post ID is wrong.
             // The calling script (edit_post.php) should already verify ownership before calling updateSublet.
             return ['success' => true, 'message' => 'No changes made or post not found for user.'];
        }
    } catch (PDOException $e) {
        // Log error $e->getMessage();
        return ['success' => false, 'errors' => ['database' => 'Failed to update sublet. ' . $e->getMessage()]];
    }
}

/**
 * Deletes a sublet from the database.
 * Returns paths of images to be deleted from filesystem.
 */
function deleteSublet(PDO $pdo, int $sublet_id, string $username) {
    // First, verify ownership and get the main image_url
    $postDetails = getSubletById($pdo, $sublet_id, $username);
    if (!$postDetails) {
        return ['success' => false, 'message' => 'Post not found or user does not own the post.'];
    }

    $images_to_delete = [];
    if (!empty($postDetails['image_url'])) {
        $images_to_delete[] = $postDetails['image_url'];
    }

    // Get additional images from sublet_images
    $additional_images = getSubletImages($pdo, $sublet_id);
    $images_to_delete = array_merge($images_to_delete, $additional_images);
    $images_to_delete = array_unique($images_to_delete); // Remove duplicates

    // Database deletion (CASCADE should handle sublet_images table if FK is set up correctly)
    $sql = "DELETE FROM sublets WHERE id = ? AND username = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sublet_id, $username]);
        if ($stmt->rowCount() > 0) {
            // If cascade delete is not reliable for sublet_images, delete them explicitly here.
            // $stmtDeleteImages = $pdo->prepare("DELETE FROM sublet_images WHERE sublet_id = ?");
            // $stmtDeleteImages->execute([$sublet_id]);
            return ['success' => true, 'images_to_delete' => $images_to_delete];
        } else {
            return ['success' => false, 'message' => 'Failed to delete post from database (already deleted or permission issue).'];
        }
    } catch (PDOException $e) {
        // Log error $e->getMessage();
        return ['success' => false, 'message' => 'Database error during deletion: ' . $e->getMessage()];
    }
}

/**
 * Adds an image record to the sublet_images table.
 */
function addSubletImage(PDO $pdo, int $sublet_id, string $image_url, int $sort_order) {
    $sql = "INSERT INTO sublet_images (sublet_id, image_url, sort_order) VALUES (?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sublet_id, $image_url, $sort_order]);
        return ['success' => true, 'image_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        // Log error $e->getMessage();
        return ['success' => false, 'error' => 'Failed to add image: ' . $e->getMessage()];
    }
}

/**
 * Deletes a specific image from sublet_images table and filesystem.
 * Typically used if user wants to remove one of several images during edit.
 * Not fully implemented here, but a placeholder for future.
 */
// function deleteSpecificSubletImage(PDO $pdo, int $image_id, string $username) { ... }

?>
