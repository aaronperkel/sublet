<?php
/**
 * The listing filters, shared by Browse (app/index.php) and Map (app/map.php).
 *
 * One copy so the two pages cannot offer different controls: the form used to
 * be pasted into both. Expects, from the including page and header.php:
 *   $currentPage        'index' or 'map'
 *   $filters            build_listing_filters() result for this request
 *   $columns            table_columns($pdo, 'sublets')
 *   $availableSemesters semesters with visible listings (header.php)
 *   $sort               the active sort key (Browse only)
 *
 * On a desktop the form is an inline panel. At 768px and below it becomes a
 * bottom sheet behind the sticky bar rendered first here, so a phone opens on
 * listings instead of on a screen of controls. The same markup serves both;
 * css/style.css and initFilters() in app.js do the rest.
 */
$isMapPage = $currentPage === 'map';

// The filter part of this request's query, carried by the List/Map switch so
// changing view keeps the filters. app.js rewrites these hrefs after every
// live update (any link with data-carry-filters).
$carryQuery = listing_filter_query($_GET);
$carrySuffix = $carryQuery !== '' ? '?' . $carryQuery : '';
?>
<div class="filter-bar" id="filterBar">
    <button type="button" class="filter-bar-toggle" id="filterSheetOpen" aria-controls="filterForm" aria-expanded="false">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
        Filters
        <span class="filter-bar-count" id="filterCount"<?= $filters['count'] ? '' : ' hidden' ?>><?= (int)$filters['count'] ?></span>
    </button>
    <nav class="view-switch" aria-label="View listings as">
        <a href="index.php<?= htmlspecialchars($carrySuffix) ?>" data-carry-filters="index.php" class="view-switch-link<?= $isMapPage ? '' : ' active' ?>"<?= $isMapPage ? '' : ' aria-current="page"' ?>>
            <i class="fa-solid fa-table-cells-large" aria-hidden="true"></i> List
        </a>
        <a href="map.php<?= htmlspecialchars($carrySuffix) ?>" data-carry-filters="map.php" class="view-switch-link<?= $isMapPage ? ' active' : '' ?>"<?= $isMapPage ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid fa-map" aria-hidden="true"></i> Map
        </a>
    </nav>
</div>

<div class="filter-sheet-backdrop" id="filterSheetBackdrop" hidden></div>

<form id="filterForm" method="get" action="<?= $isMapPage ? 'map.php' : 'index.php' ?>" class="filters"
      aria-labelledby="filterSheetTitle" data-live="<?= $isMapPage ? '0' : '1' ?>">
    <div class="filter-sheet-head">
        <h2 class="filter-sheet-title" id="filterSheetTitle">Filters</h2>
        <button type="button" class="filter-sheet-close" id="filterSheetClose" aria-label="Close filters">&times;</button>
    </div>

    <div class="filters-row">
        <div class="filter-group">
            <div class="filter-label">
                <span id="priceLabel">Price per month</span>
                <span class="slider-value" id="priceValue" aria-hidden="true"></span>
            </div>
            <div id="priceSlider" data-labelledby="priceLabel"></div>
            <input type="hidden" name="min_price" id="minPrice">
            <input type="hidden" name="max_price" id="maxPrice">
        </div>
        <div class="filter-group">
            <label class="filter-label" for="semesterFilter">Semester</label>
            <select name="semester" id="semesterFilter">
                <option value="">All semesters</option>
                <?php foreach ($availableSemesters as $sem): ?>
                    <option value="<?= htmlspecialchars($sem['semester']) ?>" <?= (isset($_GET['semester']) && $_GET['semester'] === $sem['semester']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sem['name']) ?>
                    </option>
                <?php endforeach; ?>
                <?php foreach ($emptySemesters as $sem): ?>
                    <option value="<?= htmlspecialchars($sem['code']) ?>" disabled><?= htmlspecialchars($sem['name']) ?> (<?= !empty($sem['has_listings']) ? 'none up now' : 'none yet' ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php /* Only places that welcome the searcher: one choice of who they
                 are, rather than exclude boxes (LISTING_OPEN_TO_FILTERS). */ ?>
        <?php if (isset($columns['roommate_preference'])): ?>
            <div class="filter-group">
                <label class="filter-label" for="openToFilter">Roommate preference</label>
                <select name="open_to" id="openToFilter">
                    <option value="">Show all</option>
                    <?php foreach (LISTING_OPEN_TO_FILTERS as $key => $openTo): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $filters['open_to'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($openTo['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="filter-group">
            <div class="filter-label">
                <span id="distanceLabel">Distance from campus</span>
                <span class="slider-value" id="distanceValue" aria-hidden="true"></span>
            </div>
            <div id="distanceSlider" data-labelledby="distanceLabel"></div>
            <input type="hidden" name="max_distance" id="maxDistance">
        </div>
    </div>

    <?php /* Rendered from LISTING_AMENITY_FILTERS so Browse and Map cannot
             offer different sets. */ ?>
    <div class="filter-chips" role="group" aria-labelledby="mustHaveLabel">
        <span class="filter-chips-label" id="mustHaveLabel">Must have</span>
        <?php foreach (LISTING_AMENITY_FILTERS as $key => $amenity): ?>
            <label class="filter-chip">
                <input type="checkbox" name="amenities[]" value="<?= htmlspecialchars($key) ?>"
                       <?= in_array($key, $filters['amenities'], true) ? 'checked' : '' ?>>
                <span><i class="fa-solid <?= htmlspecialchars($amenity['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($amenity['label']) ?></span>
            </label>
        <?php endforeach; ?>
        <?php if (isset($columns['price_negotiable'])): ?>
            <label class="filter-chip">
                <input type="checkbox" name="negotiable" value="1" <?= !empty($_GET['negotiable']) ? 'checked' : '' ?>>
                <span><i class="fa-solid fa-tag" aria-hidden="true"></i> Price negotiable</span>
            </label>
        <?php endif; ?>
        <?php /* Always rendered so a live update can show or hide it. */ ?>
        <a href="<?= $isMapPage ? 'map.php' : 'index.php' ?>" class="filter-clear" id="filterClear"<?= $filters['active'] ? '' : ' hidden' ?>>
            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear filters
        </a>
    </div>

    <?php if (!$isMapPage): ?>
        <?php /* Sort travels with the filters so applying one keeps the order. */ ?>
        <input type="hidden" name="sort" id="sortInput" value="<?= htmlspecialchars($sort) ?>">
    <?php endif; ?>

    <div class="filter-sheet-foot">
        <button type="submit" class="btn btn-primary filter-sheet-apply" id="filterSheetApply">
            <?= $isMapPage ? 'Show on map' : 'Show results' ?>
        </button>
    </div>
</form>
