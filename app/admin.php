<?php
$basePath = '../';
// Authorize before header.php runs — it opens <body> and renders the nav, so
// checking afterwards showed a non-admin half a page before the 403.
require_once '../includes/auth.php';
require_admin();
require_once '../includes/header.php';

// Stats — totals across everything, since admin sees hidden listings too.
$totalPosts = $pdo->query("SELECT COUNT(*) FROM sublets")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(DISTINCT username) FROM sublets")->fetchColumn();
$totalImages = $pdo->query("SELECT COUNT(*) FROM sublet_images")->fetchColumn();

// Recipients for a bulk "all users" email. Deliberately narrower than
// $totalUsers: someone whose only listing sits in a deactivated semester is not
// currently on the site, so they should not be swept into a broadcast. Must
// stay in step with the type=all query in api/email.php or the count lies.
$emailableUsers = (int)$pdo->query("SELECT COUNT(DISTINCT s.username) FROM sublets s " . VISIBLE_SEMESTER_JOIN . " WHERE " . VISIBLE_SEMESTER_WHERE)->fetchColumn();

// All semesters
$allSemesters = $pdo->query("SELECT s.*, (SELECT COUNT(*) FROM sublets WHERE semester = s.code) as post_count FROM semesters s ORDER BY s.sort_order, s.code")->fetchAll(PDO::FETCH_ASSOC);

// All posts with images. Admin sees every listing including ones hidden from
// the public site, so is_hidden is selected to flag them in the table.
$allPosts = $pdo->query("SELECT s.*, COALESCE(sem.name, s.semester) as semester_name, NOT (" . VISIBLE_SEMESTER_WHERE . ") as is_hidden, (SELECT COUNT(*) FROM sublet_images WHERE sublet_id = s.id) as image_count FROM sublets s " . VISIBLE_SEMESTER_JOIN . " ORDER BY s.posted_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$hiddenCount = count(array_filter($allPosts, fn($p) => $p['is_hidden']));

// All users
$allUsers = $pdo->query("SELECT username, COUNT(*) as post_count, MAX(posted_at) as last_post FROM sublets GROUP BY username ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
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
                <div class="stat-label">Images</div>
            </div>
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
        <button class="admin-tab" data-tab="users">
            <i class="fa-solid fa-users"></i> Users
        </button>
        <button class="admin-tab" data-tab="email">
            <i class="fa-solid fa-envelope"></i> Email
        </button>
        <button class="admin-tab" data-tab="contact-log">
            <i class="fa-solid fa-address-book"></i> Contact Log
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
                    <div class="semester-item" data-id="<?= $sem['id'] ?>">
                        <div class="semester-info">
                            <span class="semester-status <?= $sem['active'] ? '' : 'inactive' ?>"></span>
                            <div>
                                <strong><?= htmlspecialchars($sem['name']) ?></strong>
                                <span class="text-muted" style="font-size: 0.8rem; margin-left: 0.5rem;"><?= htmlspecialchars($sem['code']) ?></span>
                                <span class="text-muted" style="font-size: 0.8rem; margin-left: 0.5rem;">(<?= $sem['post_count'] ?> posts)</span>
                            </div>
                        </div>
                        <div class="semester-actions">
                            <button class="btn btn-sm btn-secondary toggle-semester"
                                    data-id="<?= $sem['id'] ?>"
                                    data-active="<?= $sem['active'] ?>"
                                    data-name="<?= htmlspecialchars($sem['name']) ?>"
                                    data-post-count="<?= $sem['post_count'] ?>">
                                <?= $sem['active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                            <?php if ($sem['post_count'] == 0): ?>
                                <button class="btn btn-sm btn-danger delete-semester" data-id="<?= $sem['id'] ?>">Delete</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="add-form" id="addSemesterForm">
                <div class="form-group">
                    <label>Code</label>
                    <input type="text" id="semCode" placeholder="e.g. fall26">
                </div>
                <div class="form-group">
                    <label>Display Name</label>
                    <input type="text" id="semName" placeholder="e.g. Fall 2026">
                </div>
                <button class="btn btn-primary btn-sm" id="addSemesterBtn">
                    <i class="fa-solid fa-plus"></i> Add
                </button>
            </div>
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
                <textarea id="announcementMessage" rows="4" placeholder="e.g. Welcome to UVM Sublets!&#10;Check out the roadmap: https://sublet.aperkel.w3.uvm.edu"></textarea>
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
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Address</th>
                            <th>Price</th>
                            <th>Semester</th>
                            <th>Images</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allPosts as $post): ?>
                            <tr data-post-id="<?= $post['id'] ?>">
                                <td><?= htmlspecialchars($post['username']) ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars($post['address']) ?>
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
                                    <button class="btn btn-sm btn-secondary manage-images-btn" data-post-id="<?= $post['id'] ?>">
                                        <?= $post['image_count'] ?> <i class="fa-solid fa-images"></i>
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
            <?php endif; ?>
        </div>

        <!-- Image management modal -->
        <div class="modal-overlay" id="imageModal">
            <div class="modal-container" style="max-width: 500px;">
                <button class="modal-close" id="imageModalClose">&times;</button>
                <div class="modal-details" style="padding: 1.5rem;">
                    <h3 style="margin-bottom: 1rem;">Manage Images</h3>
                    <div class="admin-images" id="adminImageGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Tab -->
    <div class="tab-panel" id="tab-users">
        <div class="admin-card">
            <h3>Users with Listings</h3>
            <?php if (empty($allUsers)): ?>
                <p class="text-muted">No users yet.</p>
            <?php else: ?>
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
                                <td><?= htmlspecialchars($user['username']) ?></td>
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
            <?php endif; ?>
        </div>
    </div>

    <!-- Contact Log Tab -->
    <div class="tab-panel" id="tab-contact-log">
        <div class="admin-card">
            <h3>Contact Log</h3>
            <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.85rem;">Logged whenever a user clicks Email or Call on a listing.</p>
            <div id="contactLogContent">
                <p class="text-muted">Loading...</p>
            </div>
            <div id="contactLogPagination" style="margin-top: 1rem; display: flex; gap: 0.5rem; justify-content: center;"></div>
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
                            <span>All users with visible listings (<?= $emailableUsers ?>)</span>
                        </label>
                        <label class="recipient-option">
                            <input type="radio" name="recipientType" value="semester">
                            <span>Users posting in a specific semester</span>
                        </label>
                        <label class="recipient-option">
                            <input type="radio" name="recipientType" value="individual">
                            <span>Individual users</span>
                        </label>
                    </div>

                    <!-- Semester selector (hidden by default) -->
                    <div id="semesterRecipientGroup" style="display:none; margin-top: 0.5rem;">
                        <select id="emailSemester" class="form-group" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius-xs);">
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
                                    <?= htmlspecialchars($user['username']) ?>
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
                </div>

                <button class="btn btn-primary" id="sendEmailBtn">
                    <i class="fa-solid fa-paper-plane"></i> Send Email
                </button>
                <div id="emailStatus"></div>
            </div>
        </div>
    </div>
</div>

<script src="./js/app.js?v=<?= filemtime(ROOT_DIR . '/js/app.js') ?>"></script>

<?php require_once '../includes/footer.php'; ?>
