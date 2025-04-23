<?php include 'top.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <?php include 'filters.php'; ?>

    <div id="map" class="w-full h-[500px] rounded-lg shadow-lg"></div>
</main>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>window.sublets = <?= json_encode($sublets) ?>;</script>
<?php include 'footer.php'; ?>