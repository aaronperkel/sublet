<?php include 'top.php'; ?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <?php include 'filters.php'; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
        <?php foreach ($sublets as $s): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition overflow-hidden flex flex-col">
                <img src="<?= $s['image_url'] ?>" alt="" class="h-48 w-full object-cover">
                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex justify-between items-center">
                        <span class="text-lg font-semibold text-gray-800">$<?= $s['price'] ?></span>
                        <span class="text-sm text-gray-500">
                            <?= [
                                'summer25' => 'Summer 2025',
                                'fall25' => 'Fall 2025',
                                'spring26' => 'Spring 2026'
                            ][$s['semester']] ?>
                        </span>
                    </div>
                    <p class="text-gray-600 mt-2 flex-1 line-clamp-2">
                        <?= htmlspecialchars($s['address']) ?>
                    </p>
                    <button onclick="openModal(<?= $s['id'] ?>)"
                        class="mt-4 w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition">
                        View Details
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php include 'footer.php'; ?>