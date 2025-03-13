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
            <option value="summer25" <?php if (isset($_GET['semester']) && $_GET['semester'] === 'summer25')
                echo 'selected'; ?>>Summer 2025</option>
            <option value="fall25" <?php if (isset($_GET['semester']) && $_GET['semester'] === 'fall25')
                echo 'selected'; ?>>Fall 2025</option>
            <option value="spring26" <?php if (isset($_GET['semester']) && $_GET['semester'] === 'spring26')
                echo 'selected'; ?>>Spring 2026</option>
        </select>
    </div>
    <div class="filter-item">
        <label for="distance-slider">Distance from Campus:</label>
        <div id="distance-slider"></div>
        <div id="distance-value"></div>
        <!-- Optional hidden input if using a distance filter -->
        <input type="hidden" name="max_distance" id="max_distance">
    </div>
    <div class="filter-item button">
        <button type="submit">Apply Filters</button>
    </div>
</form>



<div class="grid-container">
    <?php foreach ($sublets as $sublet): ?>
        <div class="grid-item" data-id="<?= $sublet['id'] ?>" data-price="<?= $sublet['price'] ?>"
            data-semester="<?= $sublet['semester'] ?>" data-address="<?= htmlspecialchars($sublet['address']) ?>">
            <img src="<?= $sublet['image_url'] ?>" alt="Sublet image">
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Structure -->
<div id="subletModal" class="modal">
    <div class="modal-content">
        <span class="close" style="cursor:pointer;">&times;</span>
        <img id="modalImage" src="" alt="Sublet">
        <p id="modalPrice"></p>
        <p id="modalAddress"></p>
        <p id="modalSemester"></p>
    </div>
</div>

<script>
    // Basic modal functionality
    var modal = document.getElementById('subletModal');

    document.querySelectorAll('.grid-item').forEach(function (item) {
        item.addEventListener('click', function () {
            document.getElementById('modalImage').src = item.querySelector('img').src;
            document.getElementById('modalPrice').textContent = "Price: $" + item.getAttribute('data-price');
            document.getElementById('modalAddress').textContent = "Address: " + item.getAttribute('data-address');
            document.getElementById('modalSemester').textContent = "Semester: " + item.getAttribute('data-semester');
            modal.style.display = "block";
        });
    });
    // Close modal when the close button is clicked
    document.querySelector('.close').addEventListener('click', function () {
        document.getElementById('subletModal').style.display = "none";
    });
    // Close modal when clicking outside the modal content
    window.addEventListener('click', event => {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });

    function resizeGridItem(item) {
        const grid = document.querySelector('.grid-container');
        const rowHeight = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-auto-rows'));
        const rowGap = parseInt(window.getComputedStyle(grid).getPropertyValue('grid-gap'));
        const img = item.querySelector('img');
        // Measure the image's height accurately
        const height = img.getBoundingClientRect().height;
        // Calculate row span including the gap
        const rowSpan = Math.ceil((height + rowGap) / (rowHeight + rowGap));
        item.style.gridRowEnd = "span " + rowSpan;
    }

    // Resize grid items when images load
    const gridImages = document.querySelectorAll('.grid-item img');
    gridImages.forEach(img => {
        if (img.complete) {
            resizeGridItem(img.parentElement);
        } else {
            img.addEventListener('load', () => {
                resizeGridItem(img.parentElement);
            });
        }
    });

    // Also recalc on window resize
    window.addEventListener('resize', () => {
        document.querySelectorAll('.grid-item').forEach(item => resizeGridItem(item));
    });
</script>
<?php include 'footer.php'; ?>