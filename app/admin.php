<?php
$basePath = '../';
// Authorize before header.php runs — it opens <body> and renders the nav, so
// checking afterwards showed a non-admin half a page before the 403.
require_once '../includes/auth.php';
require_admin();
require_once '../includes/header.php';
require_once '../includes/htaccess_allowlist.php';
require_once '../includes/events.php';
require_once '../includes/archive.php';
require_once '../includes/image_admin.php';

// Stats — totals across everything, since admin sees hidden listings too.
$totalPosts = $pdo->query("SELECT COUNT(*) FROM sublets")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(DISTINCT username) FROM sublets")->fetchColumn();
$totalImages = $pdo->query("SELECT COUNT(*) FROM sublet_images")->fetchColumn();
// What the photos weigh on disk, copies and orphans included: the Images tab
// breaks it down. The row count above is photos, not files.
$imageDisk = image_folder_usage();

// Recipients for a bulk "all users" email. Deliberately narrower than
// $totalUsers: someone whose only listing sits in a deactivated semester, or
// is paused or taken, is not currently on the site, so they should not be
// swept into a broadcast. Must stay in step with the type=all query in
// api/email.php or the count lies.
$emailableUsers = (int)$pdo->query("SELECT COUNT(DISTINCT s.username) FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . PUBLIC_LISTING_WHERE)->fetchColumn();

// All semesters
$allSemesters = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM sublets WHERE semester = s.code) as post_count FROM semesters s ORDER BY s.sort_order, s.code")->fetchAll(PDO::FETCH_ASSOC);

// All posts with images. Admin sees every listing including ones hidden from
// the public site, so is_hidden is selected to flag them in the table.
$allPosts = $pdo->query("SELECT s.*, COALESCE(sem.name, s.semester) as semester_name, NOT (" . VISIBLE_SEMESTER_WHERE . ") as is_hidden, (SELECT COUNT(*) FROM sublet_images WHERE sublet_id = s.id) as image_count FROM sublets s " . VISIBLE_SEMESTER_JOIN . " ORDER BY s.posted_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$hiddenCount = count(array_filter($allPosts, fn($p) => $p['is_hidden']));
// Listings their posters took off the board (visibility.php). Taken is the
// outcome the site exists for, so it gets a number of its own.
$takenCount = count(array_filter($allPosts, fn($p) => ($p['status'] ?? 'open') === 'taken'));

// All users. display_name may not exist yet (see table_columns in db.php), so
// select it only when it does — MAX() picks the single row per user, since
// sublets is effectively one row per username anyway.
$adminColumns = table_columns($pdo, 'sublets');
$nameSelect = isset($adminColumns['display_name']) ? ', MAX(display_name) as display_name' : '';
$allUsers = $pdo->query("SELECT username$nameSelect, COUNT(*) as post_count, MAX(posted_at) as last_post FROM sublets GROUP BY username ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Activity tab (includes/events.php). Counts only: the table stores a keyed
// hash for each person, never a NetID, and nothing here tries to say who.
$activityRanges = ['open' => 'Open semesters', '30d' => 'Last 30 days', 'all' => 'All time'];
$activityRange = isset($activityRanges[$_GET['range'] ?? '']) ? $_GET['range'] : 'open';
$activityReady = table_exists($pdo, 'listing_events');
$activityRows = [];
$activityTotals = ['views' => 0, 'contacts' => 0, 'converted' => 0, 'shares' => 0, 'arrivals' => 0];
$shareTargetCounts = [];
$viewSourceCounts = [];
$dailyActivity = [];
if ($activityReady) {
    [$activityWhere, $activityParams] = activity_window($activityRange);
    $activity = listing_activity($pdo, $activityWhere, $activityParams);
    $shareTargetCounts = share_target_counts($pdo, $activityWhere, $activityParams);
    $viewSourceCounts = view_source_counts($pdo, $activityWhere, $activityParams);
    $dailyActivity = daily_activity($pdo, $activityRange === '30d' ? 30 : 60);

    // Every listing on the site shows up, with zeros, so a listing nobody is
    // opening is visible too; listings since deleted show up only if counted.
    // Paused and taken listings stay in with what they were counted while they
    // were up, labelled: a taken listing is where the conversions are, and
    // dropping it would make the totals fall the moment a poster succeeds.
    $postsById = array_column($allPosts, null, 'id');
    $zero = ['views' => 0, 'contacts' => 0, 'converted' => 0, 'shares' => 0, 'arrivals' => 0];
    foreach ($allPosts as $post) {
        if (!$post['is_hidden'] && ($post['status'] ?? 'open') === 'open' && !isset($activity[(int)$post['id']])) {
            $activity[(int)$post['id']] = $zero;
        }
    }
    foreach ($activity as $listingId => $counts) {
        $post = $postsById[$listingId] ?? null;
        $activityRows[] = $counts + [
            'id' => $listingId,
            'label' => $post ? format_address($post['address']) : 'Deleted listing',
            'semester' => $post['semester_name'] ?? '',
            'hidden' => $post ? (bool)$post['is_hidden'] : false,
            'status' => $post['status'] ?? 'open',
        ];
        foreach ($activityTotals as $k => $v) {
            $activityTotals[$k] += $counts[$k];
        }
    }
    usort($activityRows, static fn($a, $b) => [$b['views'], $b['contacts']] <=> [$a['views'], $a['contacts']]);
}
$shareTargetLabels = ['native' => 'Share to…', 'instagram' => 'Instagram story', 'snapchat' => 'Snapchat', 'text' => 'Text', 'email' => 'Email', 'x' => 'X', 'copy' => 'Copied the link'];
$viewSourceLabels = ['browse' => 'Browse', 'map' => 'Map', 'share-link' => 'Share links', 'deeplink' => 'Direct links', 'unknown' => 'Unknown'];

// Archiving (includes/archive.php). The schema check compares the hand-made
// Phase 5 tables with the plan's DDL, so this page can say whether they match.
$archiveSchema = archive_schema_report($pdo);
$archiveHistory = archive_history($pdo);
$archiveTarballs = archive_tarballs();
$archiveHasPhotos = table_exists($pdo, 'semester_archives') && isset(table_columns($pdo, 'semester_archives')['photos']);

// Individually approved / blocked netids for the Access tab. allowed_users is
// created by hand (the app's DB user has no CREATE grant), so its absence has to
// render as instructions rather than a fatal error.
$allowlistReady = table_exists($pdo, 'allowed_users');
$allowEntries = [];
$blockEntries = [];
if ($allowlistReady) {
    foreach ($pdo->query("SELECT * FROM allowed_users ORDER BY uid") as $row) {
        if ($row['kind'] === 'block') {
            $blockEntries[] = $row;
        } else {
            $allowEntries[] = $row;
        }
    }
}

// What app/.htaccess actually says right now. Compared against the table below
// so the tab can flag drift — which is what a git checkout reverting the file
// looks like — and used to build the seed INSERTs when the table doesn't exist
// yet, so that populating it can't silently revoke the people already listed.
$currentRule = read_managed_require_line();
$parsedUids = parse_managed_uids();
$fileUids = $parsedUids ?? ['allow' => [], 'block' => []];

$allowlistDrifted = false;
if ($allowlistReady && $parsedUids !== null) {
    $tableAllow = array_column($allowEntries, 'uid');
    $tableBlock = array_column($blockEntries, 'uid');
    sort($tableAllow, SORT_STRING);
    sort($tableBlock, SORT_STRING);
    // ADMIN_UID is force-added to the file by the generator whether or not it is
    // a row, so it must not count as a difference on its own.
    $allowlistDrifted = array_values(array_diff($fileUids['allow'], [ADMIN_UID])) !== array_values(array_diff($tableAllow, [ADMIN_UID]))
        || $fileUids['block'] !== $tableBlock;
}
?>

<div class="admin-container">
    <div class="admin-header">
        <h1><i class="fa-solid fa-shield-halved"></i> Admin Dashboard</h1>
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $totalPosts ?></div>
                <div class="stat-label">Posts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalUsers ?></div>
                <div class="stat-label">Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalImages ?></div>
                <div class="stat-label">Photos</div>
            </div>
            <div class="stat-card" title="<?= (int)$imageDisk['files'] ?> files in public/images, including each photo's thumbnail and display copies">
                <div class="stat-number"><?= htmlspecialchars(format_bytes($imageDisk['bytes'])) ?></div>
                <div class="stat-label">On disk</div>
            </div>
            <?php if ($hiddenCount > 0): ?>
                <?php /* Posts still in the database but not on the public site,
                         because their semester is deactivated. Easy to forget
                         about otherwise — the Posts tab flags them individually. */ ?>
                <div class="stat-card stat-card-muted" title="In a deactivated semester — not visible on Browse or Map, and excluded from an 'all users' email.">
                    <div class="stat-number"><?= $hiddenCount ?></div>
                    <div class="stat-label">Hidden</div>
                </div>
            <?php endif; ?>
            <?php if ($takenCount > 0): ?>
                <div class="stat-card" title="Marked taken by their posters: off Browse and Map until put back up.">
                    <div class="stat-number"><?= $takenCount ?></div>
                    <div class="stat-label">Taken</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="admin-tabs">
        <button class="admin-tab active" data-tab="semesters">
            <i class="fa-solid fa-calendar"></i> Semesters
        </button>
        <button class="admin-tab" data-tab="announcement">
            <i class="fa-solid fa-bullhorn"></i> Announcement
        </button>
        <button class="admin-tab" data-tab="posts">
            <i class="fa-solid fa-list"></i> Posts
        </button>
        <button class="admin-tab" data-tab="images">
            <i class="fa-solid fa-images"></i> Images
        </button>
        <button class="admin-tab" data-tab="users">
            <i class="fa-solid fa-users"></i> Users
        </button>
        <button class="admin-tab" data-tab="email">
            <i class="fa-solid fa-envelope"></i> Email
        </button>
        <button class="admin-tab" data-tab="activity">
            <i class="fa-solid fa-chart-simple"></i> Activity
        </button>
        <button class="admin-tab" data-tab="access">
            <i class="fa-solid fa-key"></i> Access
        </button>
    </div>

    <!-- Semesters Tab -->
    <div class="tab-panel active" id="tab-semesters">
        <div class="admin-card">
            <h3>Manage Semesters</h3>
            <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">
                Deactivating a semester hides all of its listings from Browse and Map and removes it
                from the post form. Nothing is deleted &mdash; reactivating brings the listings back.
            </p>
            <div id="semesterList">
                <?php if (empty($allSemesters)): ?>
                    <p class="text-muted" style="padding: 1rem;">No semesters configured. Add one below, or run the migration script.</p>
                <?php endif; ?>
                <?php foreach ($allSemesters as $sem): ?>
                    <?php $archivedAt = $sem['archived_at'] ?? null; ?>
                    <div class="semester-item" data-id="<?= $sem['id'] ?>">
                        <div class="semester-info">
                            <span class="semester-status <?= $sem['active'] ? '' : 'inactive' ?>"></span>
                            <div>
                                <strong><?= htmlspecialchars($sem['name']) ?></strong>
                                <span class="semester-meta"><?= htmlspecialchars($sem['code']) ?></span>
                                <span class="semester-meta">(<?= $sem['post_count'] ?> posts)</span>
                                <?php if ($archivedAt): ?>
                                    <span class="semester-meta">Archived <?= htmlspecialchars(date('M j, Y', strtotime($archivedAt))) ?></span>
                                <?php elseif (!$sem['active']): ?>
                                    <span class="semester-meta">Hidden</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="semester-actions">
                            <?php /* An archived semester has no listings left and stays
                                     off; api/semesters.php refuses to reactivate it. */ ?>
                            <?php if (!$archivedAt): ?>
                                <button class="btn btn-sm btn-secondary toggle-semester"
                                        data-id="<?= $sem['id'] ?>"
                                        data-active="<?= $sem['active'] ?>"
                                        data-name="<?= htmlspecialchars($sem['name']) ?>"
                                        data-post-count="<?= $sem['post_count'] ?>">
                                    <?= $sem['active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            <?php endif; ?>
                            <?php if (!$sem['active'] && !$archivedAt): ?>
                                <button class="btn btn-sm btn-secondary archive-semester"
                                        data-code="<?= htmlspecialchars($sem['code']) ?>"
                                        data-name="<?= htmlspecialchars($sem['name']) ?>"
                                        aria-controls="archivePanel">
                                    Archive&hellip;
                                </button>
                            <?php endif; ?>
                            <?php if ($sem['post_count'] == 0): ?>
                                <button class="btn btn-sm btn-danger delete-semester" data-id="<?= $sem['id'] ?>">Delete</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="add-form" id="addSemesterForm">
                <div class="form-group">
                    <label for="semCode">Code</label>
                    <input type="text" id="semCode" placeholder="e.g. fall26">
                </div>
                <div class="form-group">
                    <label for="semName">Display Name</label>
                    <input type="text" id="semName" placeholder="e.g. Fall 2026">
                </div>
                <button class="btn btn-primary btn-sm" id="addSemesterBtn">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
        </div>

        <?php /* Archiving: the last step for a semester that is over. The
                 dry run and the archive itself are app/api/archive.php; the
                 panel is filled in by initArchive() in app.js. */ ?>
        <div class="admin-card" id="archiveCard">
            <h3>Archive a semester</h3>
            <p class="admin-note">
                For a semester that is over. Deactivate it first, which hides its listings, then archive it to remove them for good.
                Its photos are saved to a tarball outside the website first, in <code><?= htmlspecialchars(archive_backup_dir()) ?></code>, and checked.
                The archive keeps totals (listings, prices, views, contacts, shares), never who. Its raw activity events are deleted with it.
            </p>

            <?php if ($archiveSchema['matches']): ?>
                <p class="archive-schema archive-schema-ok">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Schema check: <code>semesters.archived_at</code> and <code>semester_archives</code> match the Phase 5 plan.
                </p>
            <?php else: ?>
                <div class="alert alert-<?= $archiveSchema['ready'] ? 'warning' : 'error' ?>">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>
                        Schema check: the Phase 5 tables differ from the plan<?= $archiveSchema['ready'] ? '. Archiving can still run, since every column it writes exists.' : ', and archiving is off until they are fixed.' ?>
                    </span>
                </div>
            <?php endif; ?>
            <?php foreach ($archiveSchema['notes'] as $note): ?>
                <p class="admin-note"><?= htmlspecialchars($note) ?></p>
            <?php endforeach; ?>
            <details class="archive-schema-details"<?= $archiveSchema['matches'] ? '' : ' open' ?>>
                <summary>Column by column</summary>
                <div class="table-scroll">
                    <table class="admin-table">
                        <thead><tr><th>What</th><th>Planned</th><th>In the database</th><th><span class="sr-only">Match</span></th></tr></thead>
                        <tbody>
                            <?php foreach ($archiveSchema['rows'] as [$what, $expected, $actual, $ok]): ?>
                                <tr class="<?= $ok ? '' : 'archive-schema-diff' ?>">
                                    <td><code><?= htmlspecialchars($what) ?></code></td>
                                    <td><?= htmlspecialchars($expected) ?></td>
                                    <td><?= htmlspecialchars($actual) ?></td>
                                    <td><?= $ok ? 'Matches' : '<strong>Differs</strong>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <div class="archive-panel" id="archivePanel" hidden tabindex="-1"></div>

            <h4 class="admin-subhead">Archived semesters</h4>
            <?php if (!$archiveHistory): ?>
                <p class="text-muted">None yet.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="admin-table archive-history">
                        <thead>
                            <tr>
                                <th>Semester</th><th>Archived</th><th class="num">Listings</th><th class="num">Median price</th>
                                <th class="num">Views</th><th class="num">Got in touch</th><th class="num">Shares</th><th class="num">From share links</th>
                                <?php if ($archiveHasPhotos): ?><th class="num">Photos</th><?php endif; ?>
                                <th class="num">Size</th><th>Backup</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archiveHistory as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['semester_name']) ?> <span class="semester-meta"><?= htmlspecialchars($a['semester_code']) ?></span></td>
                                    <td><?= htmlspecialchars(date('M j, Y', strtotime($a['archived_at']))) ?></td>
                                    <td class="num"><?= (int)$a['listings'] ?><?= (int)$a['taken'] > 0 ? ' <span class="semester-meta">' . (int)$a['taken'] . ' taken</span>' : '' ?></td>
                                    <td class="num"><?= $a['price_median'] !== null
                                        ? '$' . number_format((float)$a['price_median']) . ' <span class="semester-meta">$' . number_format((float)$a['price_min']) . '&ndash;$' . number_format((float)$a['price_max']) . '</span>'
                                        : '&mdash;' ?></td>
                                    <td class="num"><?= (int)$a['views'] ?></td>
                                    <td class="num"><?= (int)$a['contacts'] ?></td>
                                    <td class="num"><?= (int)$a['shares'] ?></td>
                                    <td class="num"><?= (int)$a['share_arrivals'] ?></td>
                                    <?php if ($archiveHasPhotos): ?><td class="num"><?= (int)$a['photos'] ?></td><?php endif; ?>
                                    <td class="num"><?= htmlspecialchars(format_bytes((int)$a['bytes'])) ?></td>
                                    <td><?= $a['tarball'] ? '<code>' . htmlspecialchars($a['tarball']) . '</code>' : 'No photos' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h4 class="admin-subhead">Photo backups</h4>
            <?php if (!$archiveTarballs): ?>
                <p class="text-muted">No archive tarballs. Other backups in that folder, such as the one from before the October 2026 image backfill, are not listed and cannot be deleted from here.</p>
            <?php else: ?>
                <p class="admin-note">Only the tarballs written by archiving are listed. Deleting one removes the only copy of that semester's photos.</p>
                <ul class="archive-tarballs" id="archiveTarballs">
                    <?php foreach ($archiveTarballs as $t): ?>
                        <li>
                            <code><?= htmlspecialchars($t['name']) ?></code>
                            <span class="semester-meta"><?= htmlspecialchars(format_bytes($t['bytes'])) ?> &middot; <?= htmlspecialchars(date('M j, Y g:ia', $t['mtime'])) ?></span>
                            <button type="button" class="btn btn-sm btn-secondary delete-tarball" data-name="<?= htmlspecialchars($t['name']) ?>">Delete</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Announcement Tab -->
    <div class="tab-panel" id="tab-announcement">
        <div class="admin-card">
            <h3>Site Announcement</h3>
            <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">
                Post a banner that appears at the top of every page. Users can dismiss it, but it will reappear on next page load.
            </p>

            <div id="announcementStatus"></div>

            <div class="form-group">
                <label for="announcementStyle">Banner Style</label>
                <select id="announcementStyle">
                    <option value="info">Info (blue)</option>
                    <option value="success">Success (green)</option>
                    <option value="warning">Warning (yellow)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="announcementMessage">Message</label>
                <textarea id="announcementMessage" rows="4" placeholder="e.g. Welcome to UVM Sublets!&#10;Check out the roadmap: https://go.uvm.edu/sublet"></textarea>
                <p class="text-muted" style="font-size: 0.8rem; margin-top: 0.35rem;">Line breaks are preserved. URLs are automatically linked.</p>
            </div>

            <div id="announcementPreview" style="display: none; margin-bottom: 1rem;">
                <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; display: block;">Preview</label>
                <div id="announcementPreviewBanner"></div>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-primary" id="saveAnnouncementBtn">
                    <i class="fa-solid fa-bullhorn"></i> Publish Announcement
                </button>
                <button class="btn btn-danger" id="clearAnnouncementBtn">
                    <i class="fa-solid fa-xmark"></i> Clear Announcement
                </button>
            </div>
        </div>
    </div>

    <!-- Posts Tab -->
    <div class="tab-panel" id="tab-posts">
        <div class="admin-card">
            <h3>All Listings</h3>
            <?php if (empty($allPosts)): ?>
                <p class="text-muted">No posts yet.</p>
            <?php else: ?>
                <?php /* Six columns will not fit a phone. Scrolling the table
                         inside its own box keeps the page itself from scrolling
                         sideways. */ ?>
                <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Address</th>
                            <th>Price</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Images</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPosts as $post): ?>
                            <tr data-post-id="<?= $post['id'] ?>">
                                <td><?= htmlspecialchars($post['username']) ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    title="<?= htmlspecialchars($post['address']) ?>">
                                    <?= htmlspecialchars(format_address($post['address'])) ?>
                                </td>
                                <td>$<?= number_format($post['price']) ?></td>
                                <td>
                                    <?= htmlspecialchars($post['semester_name']) ?>
                                    <?php if ($post['is_hidden']): ?>
                                        <span class="utility-tag" title="This semester is deactivated, so the listing is hidden from the public site.">
                                            <i class="fa-solid fa-eye-slash"></i> Hidden
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php /* The poster sets this on post.php; the admin can too,
                                             for a poster who asks on Instagram. Saved on change
                                             through api/posts.php. */ ?>
                                    <select class="post-status-select" data-post-id="<?= $post['id'] ?>" aria-label="Status of the listing at <?= htmlspecialchars(format_address($post['address'])) ?>">
                                        <?php foreach (LISTING_STATUSES as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= ($post['status'] ?? 'open') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="post-status-msg" role="status"></span>
                                </td>
                                <td>
                                    <?php /* Opens this listing in the Images tab. */ ?>
                                    <button class="btn btn-sm btn-secondary show-listing-images" data-post-id="<?= $post['id'] ?>" aria-label="<?= (int)$post['image_count'] ?> photos: show in the Images tab">
                                        <?= $post['image_count'] ?> <i class="fa-solid fa-images" aria-hidden="true"></i>
                                    </button>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-danger delete-post-btn" data-post-id="<?= $post['id'] ?>" data-username="<?= htmlspecialchars($post['username']) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php /* Images: every listing's photos, storage, and the orphan sweep.
             Filled in by initImagesTab() in app.js from api/images.php when
             the tab is first opened, since it reads every file's size and
             dimensions. Replaces the per-listing "Manage Images" dialog. */ ?>
    <div class="tab-panel" id="tab-images">
        <div class="admin-card">
            <div class="activity-head">
                <h3>Images</h3>
                <nav class="activity-range" aria-label="View">
                    <button type="button" class="activity-range-link active" data-images-view="listings" aria-pressed="true">Listings</button>
                    <button type="button" class="activity-range-link" data-images-view="orphans" aria-pressed="false">Orphans &amp; missing</button>
                </nav>
            </div>
            <p class="admin-note" id="imagesSummary" role="status">Loading&hellip;</p>
            <p class="admin-note">Each listing's first photo is its card on Browse and Map and its share preview, so the arrows and Make cover change what students see.</p>
            <div id="imagesStorage"></div>
            <div id="imagesThumbs" hidden></div>

            <div id="imagesListingsView">
                <div class="images-filters">
                    <label class="images-filter">
                        <span>Semester</span>
                        <select id="imagesSemester"><option value="">All semesters</option></select>
                    </label>
                    <label class="images-check">
                        <input type="checkbox" id="imagesHidden"> Off the board only <span class="label-aside">(hidden, paused or taken)</span>
                    </label>
                    <p class="images-only" id="imagesOnly" hidden>
                        <span id="imagesOnlyLabel"></span>
                        <button type="button" class="btn btn-sm btn-secondary" id="imagesShowAll">Show all listings</button>
                    </p>
                </div>
                <div id="imagesList"></div>
            </div>

            <div id="imagesOrphansView" hidden></div>
        </div>

        <div class="images-bulkbar" id="imagesBulk" hidden>
            <span id="imagesBulkCount" role="status"></span>
            <button type="button" class="btn btn-sm btn-secondary" id="imagesBulkClear">Clear</button>
            <button type="button" class="btn btn-sm btn-danger" id="imagesBulkDelete">Delete selected</button>
        </div>
    </div>

    <!-- Users Tab -->
    <div class="tab-panel" id="tab-users">
        <div class="admin-card">
            <h3>Users with Listings</h3>
            <?php if (empty($allUsers)): ?>
                <p class="text-muted">No users yet.</p>
            <?php else: ?>
                <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Posts</th>
                            <th>Last Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <?php /* NetID stays visible — it is the identifier every
                                         other admin action keys off. */ ?>
                                <td>
                                    <?php if (!empty($user['display_name'])): ?>
                                        <?= htmlspecialchars($user['display_name']) ?>
                                        <span class="text-muted">(<?= htmlspecialchars($user['username']) ?>)</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($user['username']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $user['post_count'] ?></td>
                                <td><?= date('M j, Y', strtotime($user['last_post'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger delete-user-btn" data-username="<?= htmlspecialchars($user['username']) ?>">
                                        <i class="fa-solid fa-trash"></i> Remove Posts
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Tab -->
    <?php
        // Two bars per day, side by side: views in green, contacts in blue, each
        // against the white card (so each clears 3:1 on its own). Drawn here as
        // SVG; there is no chart library on this site.
        // array_values: the days are keyed by date, and spreading string keys
        // into max() passes them as named arguments, which it rejects.
        $chartMax = max(1, ...array_values(array_map(static fn($d) => max($d['views'], $d['contacts']), $dailyActivity ?: [['views' => 0, 'contacts' => 0]])));
        $chartDays = count($dailyActivity);
        $chartW = 600;
        $chartH = 140;
        $slot = $chartDays ? $chartW / $chartDays : $chartW;
        $chartViews = array_sum(array_column($dailyActivity, 'views'));
        $chartContacts = array_sum(array_column($dailyActivity, 'contacts'));
        $pct = static fn(int $part, int $whole) => $whole > 0 ? round(100 * $part / $whole) . '%' : '—';
    ?>
    <div class="tab-panel" id="tab-activity">
        <div class="admin-card">
            <div class="activity-head">
                <h3>Activity</h3>
                <nav class="activity-range" aria-label="Range">
                    <?php foreach ($activityRanges as $key => $label): ?>
                        <a href="admin.php?range=<?= $key ?>#activity" class="activity-range-link<?= $key === $activityRange ? ' active' : '' ?>"<?= $key === $activityRange ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <p class="activity-note">
                Counts of people, not taps: each person counts once per listing. Views and contacts by posters on their own listing, and by you, are never recorded.
                Each person is stored as a keyed hash, never a NetID, and raw events are deleted when their semester is archived.
            </p>

            <?php if (!$activityReady): ?>
                <p class="text-muted">The <code>listing_events</code> table does not exist yet.</p>
            <?php else: ?>
                <div class="admin-stats activity-totals">
                    <div class="stat-card">
                        <div class="stat-number"><?= $activityTotals['views'] ?></div>
                        <div class="stat-label">Views</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $activityTotals['contacts'] ?></div>
                        <div class="stat-label">Got in touch</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $pct($activityTotals['converted'], $activityTotals['views']) ?></div>
                        <div class="stat-label">Conversion</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $activityTotals['shares'] ?></div>
                        <div class="stat-label">Shares</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $activityTotals['arrivals'] ?></div>
                        <div class="stat-label">From share links</div>
                    </div>
                </div>

                <h4 class="activity-subhead">Last <?= $chartDays ?> days</h4>
                <svg class="activity-chart" viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" preserveAspectRatio="none" role="img"
                     aria-label="Daily views and contacts, last <?= $chartDays ?> days: <?= $chartViews ?> views and <?= $chartContacts ?> contacts in all.">
                    <line class="activity-chart-axis" vector-effect="non-scaling-stroke" x1="0" y1="<?= $chartH ?>" x2="<?= $chartW ?>" y2="<?= $chartH ?>"></line>
                    <?php $i = 0; foreach ($dailyActivity as $day => $d): ?>
                        <?php
                            $x = $i * $slot;
                            $vh = $d['views'] / $chartMax * ($chartH - 12);
                            $ch = $d['contacts'] / $chartMax * ($chartH - 12);
                        ?>
                        <g>
                            <title><?= htmlspecialchars(date('M j', strtotime($day))) ?>: <?= $d['views'] ?> views, <?= $d['contacts'] ?> contacts</title>
                            <rect class="activity-chart-hit" x="<?= round($x, 2) ?>" y="0" width="<?= round($slot, 2) ?>" height="<?= $chartH ?>"></rect>
                            <?php if ($vh > 0): ?><rect class="activity-chart-views" x="<?= round($x + $slot * 0.1, 2) ?>" y="<?= round($chartH - $vh, 2) ?>" width="<?= round($slot * 0.38, 2) ?>" height="<?= round($vh, 2) ?>"></rect><?php endif; ?>
                            <?php if ($ch > 0): ?><rect class="activity-chart-contacts" x="<?= round($x + $slot * 0.52, 2) ?>" y="<?= round($chartH - $ch, 2) ?>" width="<?= round($slot * 0.38, 2) ?>" height="<?= round($ch, 2) ?>"></rect><?php endif; ?>
                        </g>
                    <?php $i++; endforeach; ?>
                </svg>
                <?php /* Axis labels in HTML: text inside the SVG would scale with
                         its width, to about 7px on a phone. */ ?>
                <div class="activity-chart-scale" aria-hidden="true">
                    <span><?= $chartDays ? htmlspecialchars(date('M j', strtotime(array_key_first($dailyActivity)))) : '' ?></span>
                    <span>Tallest bar: <?= $chartMax ?></span>
                    <span>Today</span>
                </div>
                <p class="activity-legend">
                    <span class="activity-swatch activity-swatch-views" aria-hidden="true"></span> Views
                    <span class="activity-swatch activity-swatch-contacts" aria-hidden="true"></span> Got in touch
                </p>

                <div class="activity-breakdowns">
                    <div>
                        <h4 class="activity-subhead">Where views start</h4>
                        <?php if (!$viewSourceCounts): ?>
                            <p class="text-muted">No views yet.</p>
                        <?php else: $srcMax = max($viewSourceCounts); ?>
                            <ul class="activity-bars">
                                <?php foreach ($viewSourceCounts as $key => $count): ?>
                                    <li>
                                        <span class="activity-bars-label"><?= htmlspecialchars($viewSourceLabels[$key] ?? $key) ?></span>
                                        <span class="activity-bars-track"><span class="activity-bars-fill" style="--share: <?= round(100 * $count / $srcMax) ?>%"></span></span>
                                        <span class="activity-bars-count"><?= $count ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="activity-subhead">Where shares go</h4>
                        <?php if (!$shareTargetCounts): ?>
                            <p class="text-muted">No shares yet.</p>
                        <?php else: $tgtMax = max($shareTargetCounts); ?>
                            <ul class="activity-bars">
                                <?php foreach ($shareTargetCounts as $key => $count): ?>
                                    <li>
                                        <span class="activity-bars-label"><?= htmlspecialchars($shareTargetLabels[$key] ?? $key) ?></span>
                                        <span class="activity-bars-track"><span class="activity-bars-fill" style="--share: <?= round(100 * $count / $tgtMax) ?>%"></span></span>
                                        <span class="activity-bars-count"><?= $count ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <h4 class="activity-subhead">By listing</h4>
                <div class="table-scroll">
                    <table class="admin-table activity-table">
                        <thead>
                            <tr>
                                <th scope="col">Listing</th>
                                <th scope="col">Semester</th>
                                <th scope="col" class="num">Views</th>
                                <th scope="col" class="num">Got in touch</th>
                                <th scope="col" class="num">Conversion</th>
                                <th scope="col" class="num">Shares</th>
                                <th scope="col" class="num">From share links</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activityRows as $row): ?>
                                <tr>
                                    <td class="activity-listing" title="<?= htmlspecialchars($row['label']) ?>"><?= htmlspecialchars($row['label']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['semester']) ?>
                                        <?php if ($row['hidden']): ?><span class="utility-tag"><i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Hidden</span><?php endif; ?>
                                        <?php if ($row['status'] !== 'open'): ?><span class="utility-tag"><?= htmlspecialchars(LISTING_STATUSES[$row['status']] ?? $row['status']) ?></span><?php endif; ?>
                                    </td>
                                    <td class="num"><?= $row['views'] ?></td>
                                    <td class="num"><?= $row['contacts'] ?></td>
                                    <td class="num"><?= $pct($row['converted'], $row['views']) ?></td>
                                    <td class="num"><?= $row['shares'] ?></td>
                                    <td class="num"><?= $row['arrivals'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="activity-note">
                    Conversion is the share of people who viewed a listing and then got in touch.
                    Contacts logged before <?= htmlspecialchars(date('M j, Y', strtotime(LISTING_EVENTS_SINCE))) ?> have no view to match, so they count toward &ldquo;Got in touch&rdquo; but not conversion.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Email Tab -->
    <div class="tab-panel" id="tab-email">
        <div class="admin-card">
            <h3>Send Email</h3>
            <div class="email-composer">
                <div class="form-group">
                    <label>Recipients</label>
                    <div class="recipient-selector">
                        <label class="recipient-option">
                            <input type="radio" name="recipientType" value="all" checked>
                            <span>All users with listings on the board (<?= $emailableUsers ?>) <span class="label-aside">&middot; not hidden, paused or taken ones</span></span>
                        </label>
                        <label class="recipient-option">
                            <input type="radio" name="recipientType" value="semester">
                            <span>Users posting in a specific semester <span class="label-aside">&middot; everyone in it</span></span>
                        </label>
                        <label class="recipient-option">
                            <input type="radio" name="recipientType" value="individual">
                            <span>Individual users</span>
                        </label>
                    </div>

                    <!-- Semester selector (hidden by default) -->
                    <div id="semesterRecipientGroup" style="display:none; margin-top: 0.5rem;">
                        <select id="emailSemester" aria-label="Semester" class="form-group" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius-xs);">
                            <?php foreach ($allSemesters as $sem): ?>
                                <option value="<?= htmlspecialchars($sem['code']) ?>"><?= htmlspecialchars($sem['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Individual user selector (hidden by default) -->
                    <div id="individualRecipientGroup" style="display:none;">
                        <div class="user-checkboxes">
                            <?php
                            $usernames = array_column($allUsers, 'username');
                            if (!in_array('aperkel', $usernames)):
                            ?>
                                <label class="user-checkbox">
                                    <input type="checkbox" name="recipients[]" value="aperkel">
                                    aperkel (admin)
                                </label>
                            <?php endif; ?>
                            <?php foreach ($allUsers as $user): ?>
                                <label class="user-checkbox">
                                    <input type="checkbox" name="recipients[]" value="<?= htmlspecialchars($user['username']) ?>">
                                    <?= htmlspecialchars(poster_name($user)) ?><?php if (!empty($user['display_name'])): ?> <span class="text-muted">(<?= htmlspecialchars($user['username']) ?>)</span><?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="emailSubject">Subject</label>
                    <input type="text" id="emailSubject" placeholder="Email subject...">
                </div>

                <div class="form-group">
                    <label for="emailBody">Message</label>
                    <textarea id="emailBody" rows="8" placeholder="Write your message here..."></textarea>
                    <p class="field-hint">
                        <i class="fa-solid fa-circle-info"></i>
                        Each email opens with &ldquo;Hi &lt;name&gt;,&rdquo; automatically. Write
                        <code>{name}</code> anywhere in the message to place it yourself instead.
                        People who haven&rsquo;t set a name still get their NetID.
                    </p>
                </div>

                <button class="btn btn-primary" id="sendEmailBtn">
                    <i class="fa-solid fa-paper-plane"></i> Send Email
                </button>
                <div id="emailStatus"></div>
            </div>
        </div>
    </div>

    <!-- Access Tab -->
    <div class="tab-panel" id="tab-access">
        <?php if (!$allowlistReady): ?>
            <?php /* The database user this app connects as has SELECT/INSERT/
                     UPDATE/DELETE but no CREATE grant, so the table cannot be
                     created from here. Show the exact SQL instead — including
                     seed rows built from whatever app/.htaccess currently says,
                     because an empty table would regenerate the file down to
                     the admin alone and quietly revoke everyone else. */ ?>
            <div class="admin-card">
                <h3>Manage Access</h3>
                <div class="alert alert-error" style="margin-bottom: 1rem;">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    The <code>allowed_users</code> table doesn&rsquo;t exist yet, so this tab is read-only.
                </div>
                <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">
                    Run this once in phpMyAdmin against <code>APERKEL_sublet</code>, then reload this page.
                    It creates the table and seeds it with the <?= count($fileUids["allow"]) ?> netid<?= count($fileUids["allow"]) === 1 ? '' : 's' ?>
                    already in <code>app/.htaccess</code>, so nobody loses access in the process.
                </p>
                <pre style="background: #0f1a16; color: #e8f0ec; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.78rem; line-height: 1.5;"><?php
                    echo htmlspecialchars(allowlist_bootstrap_sql($fileUids));
                ?></pre>
            </div>
        <?php else: ?>
            <div id="allowlistStatus"></div>

            <?php if ($allowlistDrifted): ?>
                <div class="alert alert-warning" style="margin-bottom: 1rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <code>app/.htaccess</code> doesn&rsquo;t match the lists below &mdash; usually because a
                    <code>git checkout</code> reverted it. Use <strong>Rebuild from database</strong> to resync.
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <h3>Individually approved</h3>
                <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">
                    Current students get in automatically through their UVM affiliation. These are the
                    exceptions &mdash; alumni, someone on a gap year, a grad student &mdash; who keep access
                    without it. Each change rewrites <code>app/.htaccess</code> and verifies the site still
                    loads before keeping it.
                </p>
                <div id="allowList">
                    <?php if (empty($allowEntries)): ?>
                        <p class="text-muted" style="padding: 1rem;">Nobody individually approved yet.</p>
                    <?php endif; ?>
                    <?php foreach ($allowEntries as $entry): ?>
                        <div class="semester-item" data-id="<?= $entry['id'] ?>">
                            <div class="semester-info">
                                <span class="semester-status"></span>
                                <div>
                                    <strong><?= htmlspecialchars($entry['uid']) ?></strong>
                                    <?php if (!empty($entry['note'])): ?>
                                        <span class="text-muted" style="font-size: 0.8rem; margin-left: 0.5rem;"><?= htmlspecialchars($entry['note']) ?></span>
                                    <?php endif; ?>
                                    <span class="text-muted" style="font-size: 0.8rem; margin-left: 0.5rem;">
                                        (added <?= htmlspecialchars(date('M j, Y', strtotime($entry['added_at']))) ?>)
                                    </span>
                                </div>
                            </div>
                            <div class="semester-actions">
                                <?php if ($entry['uid'] === ADMIN_UID): ?>
                                    <span class="text-muted" style="font-size: 0.8rem;">always allowed</span>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-danger remove-allowlist-btn"
                                            data-id="<?= $entry['id'] ?>"
                                            data-uid="<?= htmlspecialchars($entry['uid']) ?>">Remove</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="add-form" id="addAllowForm">
                    <div class="form-group">
                        <label for="allowUid">NetID</label>
                        <input type="text" id="allowUid" placeholder="e.g. ocongdon" autocapitalize="off" autocomplete="off" spellcheck="false">
                    </div>
                    <div class="form-group">
                        <label for="allowNote">Note (optional)</label>
                        <input type="text" id="allowNote" placeholder="e.g. gap year, back Fall 2026">
                    </div>
                    <button class="btn btn-primary btn-sm" id="addAllowBtn">
                        <i class="fa-solid fa-plus"></i> Approve
                    </button>
                </div>
            </div>

            <div class="admin-card">
                <h3>Blocked</h3>
                <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">
                    Keeps someone out even if they are a current student. Blocking wins over approval,
                    so a netid here is denied regardless of anything above.
                </p>
                <div id="blockList">
                    <?php if (empty($blockEntries)): ?>
                        <p class="text-muted" style="padding: 1rem;">Nobody blocked.</p>
                    <?php endif; ?>
                    <?php foreach ($blockEntries as $entry): ?>
                        <div class="semester-item" data-id="<?= $entry['id'] ?>">
                            <div class="semester-info">
                                <span class="semester-status inactive"></span>
                                <div>
                                    <strong><?= htmlspecialchars($entry['uid']) ?></strong>
                                    <?php if (!empty($entry['note'])): ?>
                                        <span class="text-muted" style="font-size: 0.8rem; margin-left: 0.5rem;"><?= htmlspecialchars($entry['note']) ?></span>
                                    <?php endif; ?>
                                    <span class="text-muted" style="font-size: 0.8rem; margin-left: 0.5rem;">
                                        (added <?= htmlspecialchars(date('M j, Y', strtotime($entry['added_at']))) ?>)
                                    </span>
                                </div>
                            </div>
                            <div class="semester-actions">
                                <button class="btn btn-sm btn-secondary remove-allowlist-btn"
                                        data-id="<?= $entry['id'] ?>"
                                        data-uid="<?= htmlspecialchars($entry['uid']) ?>">Unblock</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="add-form" id="addBlockForm">
                    <div class="form-group">
                        <label for="blockUid">NetID</label>
                        <input type="text" id="blockUid" placeholder="e.g. bkamont" autocapitalize="off" autocomplete="off" spellcheck="false">
                    </div>
                    <div class="form-group">
                        <label for="blockNote">Note (optional)</label>
                        <input type="text" id="blockNote" placeholder="e.g. repeated fake listings">
                    </div>
                    <button class="btn btn-danger btn-sm" id="addBlockBtn">
                        <i class="fa-solid fa-ban"></i> Block
                    </button>
                </div>
            </div>

            <div class="admin-card">
                <h3>Live Apache rule</h3>
                <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">
                    The line currently in <code>app/.htaccess</code>. If a change ever leaves the app
                    returning an error, <a href="/recover/">/recover/</a> restores the previous file &mdash;
                    it lives outside <code>app/</code> so it keeps working when this page doesn&rsquo;t.
                </p>
                <pre id="allowlistRule" style="background: #0f1a16; color: #e8f0ec; padding: 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.78rem; line-height: 1.5; margin-bottom: 1rem;"><?= htmlspecialchars($currentRule ?? 'Could not read the managed block from app/.htaccess.') ?></pre>
                <button class="btn btn-secondary btn-sm" id="rebuildAllowlistBtn">
                    <i class="fa-solid fa-rotate"></i> Rebuild from database
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
