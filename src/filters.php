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

// Build SQL query
$sql = "SELECT * FROM sublets";
if ($filters) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}
$sql .= " ORDER BY RAND()";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<form id="filterForm" method="get"
    class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Price -->
    <div class="space-y-2">
        <label for="price-slider" class="block font-semibold text-gray-700 dark:text-gray-300">Price Range</label>
        <div id="price-slider"></div>
        <div id="price-range-value" class="text-sm text-gray-600 dark:text-gray-400"></div>
        <input type="hidden" name="min_price" id="min_price">
        <input type="hidden" name="max_price" id="max_price">
    </div>

    <!-- Semester -->
    <div class="space-y-2">
        <label for="semester-filter" class="block font-semibold text-gray-700 dark:text-gray-300">Semester</label>
        <select id="semester-filter" name="semester"
            class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded text-gray-800 dark:text-gray-100">
            <option value="" <?= (!isset($_GET['semester']) || $_GET['semester'] === '') ? 'selected' : '' ?>>All</option>
            <?php foreach ($semesters as $sem): ?>
                <option value="<?= htmlspecialchars($sem) ?>"
                    <?= (isset($_GET['semester']) && $_GET['semester'] === $sem) ? 'selected' : '' ?>>
                    <?= [
                        'summer25' => 'Summer 2025',
                        'fall25' => 'Fall 2025',
                        'spring26' => 'Spring 2026'
                    ][$sem] ?? htmlspecialchars($sem) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Distance -->
    <div class="space-y-2">
        <label for="distance-slider" class="block font-semibold text-gray-700 dark:text-gray-300">
            Distance (mi)
        </label>
        <div id="distance-slider"></div>
        <div id="distance-value" class="text-sm text-gray-600 dark:text-gray-400"></div>
        <input type="hidden" name="max_distance" id="max_distance">
    </div>

    <!-- Actions -->
    <div class="md:col-span-3 flex space-x-4 justify-end">
        <button type="submit"
            class="px-6 py-2 bg-blue-600 dark:bg-blue-500 text-white rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition">
            Apply
        </button>
        <a href="<?= ($pathParts['filename'] === 'map' ? 'map.php' : 'index.php') ?>"
            class="px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-300 dark:hover:bg-gray-600 transition">
            Clear
        </a>
    </div>
</form>