<?php
include 'top.php';
?>

<!-- map.php -->
<main>
    <?php include 'filters.php'; ?>
    <div class="map-wrapper" style="width:100%; height:500px; position:relative;">
        <div id="map" style="width:100%; height:100%;"></div>
    </div>

    <!-- Modal Structure -->
    <div id="subletModal" class="modal">
        <div class="modal-content">
            <img id="modalImage" src="" alt="Sublet image">
            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px;">
                <h2 id="modalUsername" style="padding: 0"></h2>
                <a id="modalContact" href="#"
                    style="padding: 0.5em 1em; background-color: var(--accent-color); color: var(--secondary-bg); text-decoration: none; border-radius: 4px;">Contact</a>
            </div>
            <hr>
            <p id="modalPrice"></p>
            <p id="modalAddress"></p>
            <p id="modalSemester"></p>
            <p id="modalDesc"></p>
            <span class="close" style="cursor:pointer;">&times;</span>
        </div>
    </div>

    <!-- Load Leaflet CSS and JS -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        window.sublets = <?php echo json_encode($sublets); ?>;
    </script>
</main>
<?php include 'footer.php'; ?>