<?php
include 'top.php';

// Initialize filter arrays
$filters = [];
$params = [];

// Price range filter
if (isset($_GET['min_price']) && isset($_GET['max_price']) && $_GET['min_price'] !== '' && $_GET['max_price'] !== '') {
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

$sql = "SELECT * FROM sublets";
if ($filters) {
    $sql .= " WHERE " . implode(" AND ", $filters);
}
$sql .= " ORDER BY RAND()";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- index.php -->
<main>
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
                <a href="index.php" class="clear-filters"
                    style="display: inline-block; font-size: 0.9em; padding: 0.2em 0.5em; background-color: #f1f1f1; border: 1px solid #ccc; border-radius: 4px; text-decoration: none; color: #333; margin-left: 20px;">Clear
                    Filters</a>
            </div>
        </div>
    </form>



    <div class="grid-container">
        <?php foreach ($sublets as $sublet): ?>
            <div class="grid-item" data-id="<?= $sublet['id'] ?>" data-price="<?= $sublet['price'] ?>"
                data-desc="<?= htmlspecialchars($sublet['description']) ?>" data-semester="<?= $sublet['semester'] ?>"
                data-address="<?= htmlspecialchars($sublet['address']) ?>"
                data-username="<?= htmlspecialchars($sublet['username']) ?>"> <!-- new attribute -->
                <img src="<?= $sublet['image_url'] ?>" alt="Sublet image">
            </div>
        <?php endforeach; ?>
    </div>
    <!-- Modal Structure -->
    <div id="subletModal" class="modal">
        <div class="modal-content">
            <img id="modalImage" src="" alt="Sublet image">
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px;">
                <div style="display: flex; align-items: center;">
                    <h2 id="modalUsername" style="padding: 0; margin-right: 10px;"></h2>
                </div>
                <div>
                    <?php if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] === 'aperkel'): ?>  
                        <a id="modalDelete"
                            style="padding: 0.5em 1em; background-color: red; color: var(--secondary-bg); text-decoration: none; border-radius: 4px; cursor: pointer; margin-right: 5%;">
                            Delete
                        </a>
                    <?php endif; ?>
                    <a id="modalContact" href="#"
                        style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); text-decoration: none; border-radius: 4px;">
                        Contact
                    </a>
                </div>
            </div>
            <hr>
            <p id="modalPrice"></p>
            <p id="modalAddress"></p>
            <p id="modalSemester"></p>
            <p id="modalDesc"></p>
            <span class="close" style="cursor:pointer;">&times;</span>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>