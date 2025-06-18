<?php
include 'top.php';
// $pdo is available from connect-db.php (via top.php)
// $_GET will contain filter parameters from the form in filters.php
// filters.php is included below but it only sets up the form,
// it does not fetch data anymore.
$sublets = getAllSublets($pdo, $_GET);
?>
<main>
    <?php include 'filters.php'; ?>
    <div class="grid-container">
        <?php foreach ($sublets as $sublet): ?>
            <div class="grid-item" data-id="<?= $sublet['id'] ?>" data-price="<?= $sublet['price'] ?>"
                data-desc="<?= htmlspecialchars($sublet['description']) ?>" data-semester="<?= $sublet['semester'] ?>"
                data-address="<?= htmlspecialchars($sublet['address']) ?>"
                data-username="<?= htmlspecialchars($sublet['username']) ?>">
                <img src="<?= htmlspecialchars(!empty($sublet['first_image_url']) ? $sublet['first_image_url'] : './public/images/default_sublet_image.png') ?>" alt="Sublet image">
            </div>
        <?php endforeach; ?>
    </div>
    <!-- Modal Structure -->
    <div id="subletModal" class="modal">
        <div class="modal-content">
            <!-- Container for slider images -->
            <div class="modal-image-slider"></div>
            <button class="prev"
                style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); border: none; border-radius: 4px; cursor: pointer; display: none;">Prev</button>
            <button class="next"
                style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); border: none; border-radius: 4px; cursor: pointer; display: none;">Next</button>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px;">
                <div style="display: flex; align-items: center;">
                    <h2 id="modalUsername" style="padding: 0; margin-right: 10px;"></h2>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <a id="modalDelete" style="padding: 0.5em 1em; background-color: red; color: var(--secondary-bg); text-decoration: none; border-radius: 4px; cursor: pointer; display: none;">Delete</a>
                    <a id="modalContact" href="#"
                        style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); text-decoration: none; border-radius: 4px;">Contact</a>
                    <a id="modalEdit" href="#"
                        style="padding: 0.5em 1em; background-color: lightgreen; color: var(--secondary-bg); text-decoration: none; border-radius: 4px; display: none;">Edit</a>
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

<script>
    var currentUser = document.body.getAttribute('data-user') || "Guest";
</script>
<?php include 'footer.php'; ?>