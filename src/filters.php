<!-- filters.php -->
<?php
// Initialize filter arrays
$filters = [];
$params = [];

// Price range filter
if (isset($_GET['min_price'], $_GET['max_price']) && $_GET['min_price'] !== '' && $_GET['max_price'] !== '') {
    $filters[] = "price BETWEEN ? AND ?";
    $params[] = $_GET['min_price'];
    $params[] = $_GET['max_price'];
}

// Semester filter
if (isset($_GET['semester']) && $_GET['semester'] !== '') {
    $filters[] = "semester = ?";
    $params[] = $_GET['semester'];
}

// Distance filter (in miles)
if (isset($_GET['max_distance']) && $_GET['max_distance'] !== '') {
    $filters[] = "3959 * acos(cos(radians(44.477435)) * cos(radians(lat)) * cos(radians(lon) - radians(-73.195323)) + sin(radians(44.477435)) * sin(radians(lat))) <= ?";
    $params[] = $_GET['max_distance'];
}

// The $filters array and $params array construction based on $_GET
// is still useful for other potential uses or if we decide to pass this to getAllSublets directly.
// However, the actual fetching of $sublets is removed from this file.
// $sublets will be fetched in index.php using getAllSublets($pdo, $_GET).
// Variables like $maxPriceRounded, $semesters, $maxDistanceRounded are available from top.php.
?>

<form id="filterForm" method="get" class="filters">
    <div class="filter-item">
        <label for="price-slider">Price Range:</label>
        <div id="price-slider"></div>
        <div id="price-range-value"></div>
        <!-- Hidden inputs to store slider values -->
        <input type="hidden" name="min_price" id="min_price">
        <input type="hidden" name="max_price" id="max_price">
    </div>
    <div class="filter-item">
        <label for="semester-filter">Semester:</label>
        <select id="semester-filter" name="semester">
            <option value="" <?php if (!isset($_GET['semester']) || $_GET['semester'] === '')
                echo 'selected'; ?>>All
            </option>
            <?php
            // Optional friendly name mapping
            $semesterMapping = [
                'summer25' => 'Summer 2025',
                'fall25' => 'Fall 2025',
                'spring26' => 'Spring 2026'
            ];
            foreach ($semesters as $semester): ?>
                <option value="<?= htmlspecialchars($semester) ?>" <?php if (isset($_GET['semester']) && $_GET['semester'] == $semester)
                      echo 'selected'; ?>>
                    <?= isset($semesterMapping[$semester]) ? $semesterMapping[$semester] : htmlspecialchars($semester) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-item">
        <label for="distance-slider">Distance from Campus:</label>
        <div id="distance-slider"></div>
        <div id="distance-value"></div>
        <input type="hidden" name="max_distance" id="max_distance">
    </div>
    <div class="filter-buttons" style="display: flex; flex-direction: column; align-items: center">
        <div class="filter-item button" style="width: 100%; max-width: 300px;">
            <button type="submit" style="width: 100%; padding: 0.8em;">Apply Filters</button>
        </div>
        <div class="button">
            <a href="<?php 
            if ($pathParts['filename'] == 'map') { 
                echo 'map.php';
             } else { 
                echo 'index.php';
            } ?>" 
                class="clear-filters"
                style="display: inline-block; font-size: 0.9em; padding: 0.2em 0.5em; background-color: #f1f1f1; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333; margin-left: 20px;">Clear
                Filters</a>
        </div>
    </div>
</form>
