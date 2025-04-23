<!-- src/index.php -->

<?php include 'top.php'; ?>
<main class="max-w-6xl mx-auto px-4 py-8">
    <?php include 'filters.php'; ?>
    <script>window.sublets = <?= json_encode($sublets) ?>;</script>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($sublets as $s): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow flex flex-col overflow-hidden">
                <img src="<?= $s['image_url'] ?>" class="h-48 w-full object-cover">
                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-800 dark:text-gray-100">$<?= $s['price'] ?></span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            <?= [
                                'summer25' => 'Summer 2025',
                                'fall25' => 'Fall 2025',
                                'spring26' => 'Spring 2026'
                            ][$s['semester']] ?>
                        </span>
                    </div>
                    <p class="mt-2 text-gray-600 dark:text-gray-300 flex-1 line-clamp-2">
                        <?= htmlspecialchars($s['address']) ?>
                    </p>
                    <button onclick="openModal(<?= $s['id'] ?>)"
                        class="mt-4 bg-blue-600 dark:bg-blue-500 text-white py-2 rounded hover:bg-blue-700 dark:hover:bg-blue-600 transition">
                        View Details
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Detail Modal -->
<div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 relative"> 
        <button id="modalClose"
            class="absolute top-3 right-3 text-gray-600 dark:text-gray-300 text-2xl">&times;</button>
        <h2 id="modalPrice" class="text-xl font-bold text-gray-800 dark:text-gray-100"></h2>
        <p id="modalAddress" class="mt-2 text-gray-600 dark:text-gray-300"></p>
        <p id="modalSemester" class="mt-1 text-gray-600 dark:text-gray-300"></p>
        <div id="modalActions" class="mt-4 space-x-2"></div>
    </div>
</div>

<?php include 'footer.php'; ?>