<?php
include 'top.php';
?>
<main>
    <?php include 'filters.php'; ?>
    <div class="grid-container">
        <?php foreach ($sublets as $sublet): ?>
            <div class="grid-item" data-id="<?= $sublet['id'] ?>" data-price="<?= $sublet['price'] ?>"
                data-desc="<?= htmlspecialchars($sublet['description']) ?>" data-semester="<?= $sublet['semester'] ?>"
                data-address="<?= htmlspecialchars($sublet['address']) ?>"
                data-username="<?= htmlspecialchars($sublet['username']) ?>">
                <img src="<?= $sublet['image_url'] ?>" alt="Sublet image">
            </div>
        <?php endforeach; ?>
    </div>
    <!-- Modal Structure -->
    <div id="subletModal" class="modal">
        <div class="modal-content">
            <!-- Container for slider images -->
            <div class="modal-image-slider"></div>
            <button class="prev" style="display:none;">Prev</button>
            <button class="next" style="display:none;">Next</button>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px;">
                <div style="display: flex; align-items: center;">
                    <h2 id="modalUsername" style="padding: 0; margin-right: 10px;"></h2>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] === 'aperkel'): ?>
                        <a id="modalDelete"
                            style="padding: 0.5em 1em; background-color: red; color: var(--secondary-bg); text-decoration: none; border-radius: 4px; cursor: pointer;">Delete</a>
                    <?php endif; ?>
                    <a id="modalContact" href="#"
                        style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); text-decoration: none; border-radius: 4px;">Contact</a>
                    <a id="modalEdit" href="edit_post.php"
                        style="padding: 0.5em 1em; background-color: lightgreen; color: var(--secondary-bg); text-decoration: none; border-radius: 4px;">Edit</a>
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