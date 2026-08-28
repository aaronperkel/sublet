<?php
/**
 * Break-glass restore for app/.htaccess.
 *
 * The Access tab in app/admin.php rewrites the `Require ldap-filter` line that
 * controls who can reach /app/. That write is already guarded — it verifies the
 * result over HTTP and rolls itself back if Apache stops serving the directory
 * properly — but the portal doing the guarding lives inside the directory it is
 * editing. If it ever does lock itself out, this page is what puts the last
 * working file back, and the point of the whole feature is that fixing it should
 * not require SSH.
 *
 * So this page depends on as little as possible. It pulls in auth.php and
 * htaccess_allowlist.php and nothing else — in particular NOT db.php, because a
 * database that is down or a table that is missing must not be able to take the
 * recovery path down with it. It carries its own styles for the same reason.
 *
 * Access is enforced twice: recover/.htaccess restricts the directory to
 * aperkel via CAS, and require_admin() checks REMOTE_USER again here. The second
 * check is what fails closed if the .htaccess were ever lost — REMOTE_USER would
 * be empty, is_admin() false, and this 403s rather than offering a restore
 * button to the internet.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/htaccess_allowlist.php';

require_admin();

$message = null;
$messageOk = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_same_origin();

    $name = (string)($_POST['name'] ?? '');
    $error = null;

    if (($_POST['action'] ?? '') !== 'restore') {
        $message = 'Unknown action.';
    } elseif (restore_htaccess_backup($name, $error)) {
        $message = 'Restored ' . $name . '. Check the status below, then reload /app/.';
        $messageOk = true;
    } else {
        $message = $error ?? 'Restore failed.';
    }
}

$detail = '';
$healthy = verify_app_reachable($detail);
$current = @file_get_contents(htaccess_path());
$backups = list_htaccess_backups();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recover &middot; UVM Sublets</title>
<style>
    :root { --green:#154734; --gold:#FFD100; --slate:#3b4a52; --fog:#f4f6f7; --bad:#b3261e; --ok:#1b7f4b; }
    * { box-sizing: border-box; }
    body { margin:0; padding:2rem 1rem; background:var(--fog); color:var(--slate);
           font:16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .wrap { max-width: 900px; margin: 0 auto; }
    h1 { color:var(--green); font-size:1.5rem; margin:0 0 .25rem; }
    h2 { color:var(--green); font-size:1rem; margin:0 0 .75rem; text-transform:uppercase; letter-spacing:.05em; }
    .sub { margin:0 0 1.5rem; font-size:.9rem; opacity:.8; }
    .card { background:#fff; border-radius:10px; padding:1.25rem; margin-bottom:1.25rem;
            box-shadow:0 1px 3px rgba(0,0,0,.08); }
    .status { border-left:4px solid var(--ok); padding-left:1rem; }
    .status.bad { border-left-color:var(--bad); }
    .status strong { display:block; font-size:1.05rem; color:var(--ok); }
    .status.bad strong { color:var(--bad); }
    .flash { padding:.85rem 1rem; border-radius:8px; margin-bottom:1.25rem; font-size:.9rem; }
    .flash.ok { background:#e6f4ec; color:#0f5132; }
    .flash.bad { background:#fce8e6; color:#842029; }
    pre { background:#0f1a16; color:#e8f0ec; padding:1rem; border-radius:8px;
          overflow-x:auto; font-size:.8rem; line-height:1.45; margin:0; }
    table { width:100%; border-collapse:collapse; font-size:.875rem; }
    th, td { text-align:left; padding:.6rem .5rem; border-bottom:1px solid #e6eaec; }
    th { font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; opacity:.65; }
    td.name { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:.8rem; }
    button { background:var(--green); color:#fff; border:0; border-radius:6px;
             padding:.45rem .9rem; font-size:.825rem; cursor:pointer; }
    button:hover { background:#1d5f47; }
    .empty { opacity:.7; font-size:.9rem; margin:0; }
    .foot { font-size:.8rem; opacity:.7; text-align:center; margin-top:2rem; }
    .foot a { color:var(--green); }
</style>
</head>
<body>
<div class="wrap">

    <h1>Recover access configuration</h1>
    <p class="sub">Restores <code>app/.htaccess</code> from a backup taken before each change made in the Access tab.</p>

    <?php if ($message !== null): ?>
        <div class="flash <?= $messageOk ? 'ok' : 'bad' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="status <?= $healthy ? '' : 'bad' ?>">
            <strong><?= $healthy ? 'The app is reachable' : 'The app is NOT reachable' ?></strong>
            <?= $healthy
                ? '/app/ redirects to CAS as it should. Nothing needs recovering.'
                : 'Checking /app/ ' . htmlspecialchars($detail) . '. Restore a backup below.' ?>
        </div>
    </div>

    <div class="card">
        <h2>Current app/.htaccess</h2>
        <?php if ($current === false): ?>
            <p class="empty">Could not read the file.</p>
        <?php else: ?>
            <pre><?= htmlspecialchars($current) ?></pre>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Backups</h2>
        <?php if (empty($backups)): ?>
            <p class="empty">No backups yet. One is taken automatically before each change made in the Access tab.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Taken</th><th>File</th><th>Size</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M j, Y g:i:s a', $b['mtime'])) ?></td>
                        <td class="name"><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= number_format($b['size']) ?> B</td>
                        <td>
                            <form method="post" onsubmit="return confirm('Overwrite app/.htaccess with this backup?');">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="name" value="<?= htmlspecialchars($b['name']) ?>">
                                <button type="submit">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <p class="foot"><a href="/app/admin.php">&larr; Back to the admin dashboard</a></p>

</div>
</body>
</html>
