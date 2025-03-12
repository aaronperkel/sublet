<!-- index.php -->
<?php
include 'top.php';
$sql = "SELECT * FROM sublets";
$stmt = $pdo->query($sql);
$sublets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="filters">
    <!-- Add your search bar and filter controls here (e.g., price slider, semester dropdown, etc.) -->
    <input type="text" id="searchBar" placeholder="Search by address...">
    <!-- For a price slider, you might use a library like noUiSlider or jQuery UI -->
</div>

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
</script>
<?php include 'footer.php'; ?>