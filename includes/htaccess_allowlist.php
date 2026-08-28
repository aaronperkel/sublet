<?php
/**
 * Generating and safely replacing the access-control block in app/.htaccess.
 *
 * Who can reach /app/ is decided by a single `Require ldap-filter` line. The
 * eduPersonAffiliation=Student half admits current students automatically; the
 * uid list beside it is the manual exception for people who should have access
 * but no longer carry that affiliation — alumni, someone on a gap year, a grad
 * student. The Access tab in app/admin.php edits that list, and this file is
 * what turns it back into Apache configuration.
 *
 * Two constraints shape everything here.
 *
 * First, the line cannot live anywhere else. The vhost grants
 * `AllowOverride Options AuthConfig FileInfo Indexes Limit`, which permits
 * `Require ldap-filter` in .htaccess, but Apache never allows `Include` in an
 * .htaccess context at all — so the whole rule has to be written into the file
 * itself rather than pulled in from somewhere safer.
 *
 * Second, app/.htaccess governs app/admin.php. A malformed rule 500s the entire
 * /app/ directory, taking down the portal that would be used to fix it. So the
 * write path here validates first, keeps a backup outside the web root, swaps
 * the file atomically, then asks Apache whether the result actually works and
 * puts the old file back if it doesn't. /recover/ is the last resort if even
 * that fails, which is why this file deliberately does not require db.php:
 * recovery has to work when the database does not.
 */

require_once __DIR__ . '/auth.php';

/**
 * Only the text between these markers is ever rewritten. Everything else in
 * app/.htaccess — DirectoryIndex, AuthType CAS, the four asset RewriteRules —
 * survives by not being touched, rather than by being regenerated from a
 * template that could drift from whatever the file actually needs.
 */
define('ALLOWLIST_BEGIN', '# BEGIN MANAGED ACCESS');
define('ALLOWLIST_END', '# END MANAGED ACCESS');

/**
 * The LDAP condition that admits current students without anyone being listed.
 *
 * Written without surrounding parentheses, because mod_authnz_ldap wraps the
 * value of `Require ldap-filter` in its own parens when it builds the query —
 * roughly (&(uid=<user>)(<filter>)). A filter that arrives already parenthesised
 * would produce a doubled ((...)) and fail. Every expression built below keeps
 * that same unparenthesised outer form.
 */
define('ALLOWLIST_BASE_FILTER', 'eduPersonAffiliation=Student');

/**
 * Canonical URL used to check that Apache still accepts the file.
 *
 * A constant rather than something derived from HTTP_HOST, so that a spoofed
 * Host header cannot aim the check at a URL that would pass. The defined()
 * guard exists only so a test harness running before this file is included can
 * point the check somewhere that fails on purpose and exercise the rollback —
 * a request cannot reach that, since it would have to run code first.
 */
if (!defined('ALLOWLIST_SELF_TEST_URL')) {
    define('ALLOWLIST_SELF_TEST_URL', 'https://sublet.aperkel.w3.uvm.edu/app/');
}

/** How many rolled-off copies of app/.htaccess to keep. */
define('ALLOWLIST_BACKUP_KEEP', 10);

function htaccess_path(): string {
    return dirname(__DIR__) . '/app/.htaccess';
}

/**
 * Backups live one level above the web root, next to .env.
 *
 * Anything inside the document root is web-reachable, and these files spell out
 * the site's access rules.
 */
function htaccess_backup_dir(): string {
    return dirname(dirname(__DIR__)) . '/sublet-htaccess-backups';
}

/**
 * Whether a string is safe to write into an LDAP filter inside an Apache config.
 *
 * This single check is the security boundary of the whole feature, and it is
 * guarding against two different things. A uid containing ( or ) unbalances the
 * filter and 500s /app/. A uid containing a newline is worse: it injects
 * arbitrary directives into a file Apache executes, so `Require all granted` in
 * a username would open the site to everyone.
 *
 * The pattern ends in \z, not $, precisely because of the second case — $ also
 * matches immediately before a trailing newline, so /^[a-z0-9]+$/ would happily
 * accept "aperkel\nRequire all granted\n". \z is the true end of the subject.
 *
 * Invalid input is rejected, never trimmed or escaped into shape: there is no
 * legitimate UVM netid this excludes, so anything that fails is a bug or an
 * attack, and neither should be silently repaired.
 */
function valid_uid(string $uid): bool {
    return preg_match('/^[a-z][a-z0-9]{0,15}\z/', $uid) === 1;
}

/**
 * Validate, de-duplicate and sort a set of uids.
 *
 * Throws rather than filtering. Dropping an invalid entry from the allow list
 * would merely deny someone access, but silently dropping one from the block
 * list would restore access to a person who was deliberately removed — so a bad
 * row has to stop the write entirely rather than change its meaning.
 *
 * @throws InvalidArgumentException
 */
function normalize_uid_set(array $uids): array {
    $set = [];
    foreach ($uids as $uid) {
        $uid = (string)$uid;
        if (!valid_uid($uid)) {
            // json_encode so a newline in the offending value shows up as \n in
            // the message instead of breaking the line it is reported on.
            throw new InvalidArgumentException('Refusing to write an invalid uid: ' . json_encode($uid));
        }
        $set[$uid] = true;
    }

    $out = array_keys($set);
    sort($out, SORT_STRING);
    return $out;
}

/**
 * Build the value of the `Require ldap-filter` directive.
 *
 * Four shapes, depending on which lists have entries:
 *
 *   neither   eduPersonAffiliation=Student
 *   allow     |(eduPersonAffiliation=Student)(uid=a)(uid=b)
 *   block     &(eduPersonAffiliation=Student)(!(uid=x))
 *   both      &(|(eduPersonAffiliation=Student)(uid=a))(!(uid=x))
 *
 * Blocking wins over allowing, because the negations are ANDed across the whole
 * expression rather than against the student clause alone.
 *
 * The allow list uses a flat n-ary |(A)(B)(C) instead of the nested |(A)(|(B)(C))
 * the file was originally written with. The two are equivalent in LDAP, and the
 * flat form has no empty-(|) case to special-case when the list is short.
 *
 * ADMIN_UID is forced into allow and out of block here, in the generator, rather
 * than being checked in the UI. Locking the admin out of the portal that edits
 * this file is then impossible by construction, whatever the database contains
 * and whichever caller is asking.
 *
 * @throws InvalidArgumentException on any uid that fails valid_uid()
 */
function build_require_line(array $allow, array $block): string {
    $allow[] = ADMIN_UID;

    $allow = normalize_uid_set($allow);
    $block = normalize_uid_set($block);

    $block = array_values(array_diff($block, [ADMIN_UID]));

    // A uid in both lists is contradictory. The UNIQUE key on allowed_users.uid
    // makes it unreachable from the portal, but the generator should not lean on
    // a database constraint to stay correct: block wins, so the allow entry is
    // the one that goes.
    $allow = array_values(array_diff($allow, $block));

    $expr = ALLOWLIST_BASE_FILTER;

    if ($allow) {
        $terms = '';
        foreach ($allow as $uid) {
            $terms .= '(uid=' . $uid . ')';
        }
        $expr = '|(' . ALLOWLIST_BASE_FILTER . ')' . $terms;
    }

    if ($block) {
        $negations = '';
        foreach ($block as $uid) {
            $negations .= '(!(uid=' . $uid . '))';
        }
        $expr = '&(' . $expr . ')' . $negations;
    }

    return $expr;
}

/**
 * Parenthesis balance check, used as a last assertion before the file is written.
 *
 * build_require_line() is already the thing that makes the output well-formed;
 * this exists so that a future edit to it cannot produce an unbalanced filter
 * without something noticing before Apache does.
 */
function filter_parens_balanced(string $filter): bool {
    $depth = 0;
    for ($i = 0, $len = strlen($filter); $i < $len; $i++) {
        if ($filter[$i] === '(') {
            $depth++;
        } elseif ($filter[$i] === ')') {
            $depth--;
            if ($depth < 0) {
                return false;
            }
        }
    }
    return $depth === 0;
}

/**
 * The full replacement text for the managed region, markers included.
 *
 * @throws InvalidArgumentException
 */
function render_managed_block(array $allow, array $block): string {
    $filter = build_require_line($allow, $block);

    if (!filter_parens_balanced($filter)) {
        throw new InvalidArgumentException('Generated an unbalanced LDAP filter; refusing to write it.');
    }

    return ALLOWLIST_BEGIN . "\n"
        . "# Generated from the allowed_users table by the Access tab in app/admin.php.\n"
        . "# Do not edit by hand: the next change made in the portal overwrites it.\n"
        . "# Last written " . date('Y-m-d H:i:s') . ".\n"
        . 'Require ldap-filter ' . $filter . "\n"
        . ALLOWLIST_END;
}

/**
 * Splice a new managed block into the file's existing contents.
 *
 * Returns null and sets $error if the markers are missing, duplicated or out of
 * order. Failing closed matters here: without both markers there is no way to
 * tell which line is the managed one, and guessing would mean rewriting a rule
 * somebody added by hand.
 */
function replace_managed_block(string $contents, string $newBlock, ?string &$error): ?string {
    $begin = strpos($contents, ALLOWLIST_BEGIN);
    $end = strpos($contents, ALLOWLIST_END);

    if ($begin === false || $end === false) {
        $error = 'app/.htaccess is missing the "' . ALLOWLIST_BEGIN . '" / "' . ALLOWLIST_END
            . '" markers, so there is no managed region to replace. Restore them by hand or from /recover/.';
        return null;
    }

    if (strpos($contents, ALLOWLIST_BEGIN, $begin + 1) !== false
        || strpos($contents, ALLOWLIST_END, $end + 1) !== false) {
        $error = 'app/.htaccess contains more than one managed region. Fix it by hand or from /recover/.';
        return null;
    }

    if ($end < $begin) {
        $error = 'The managed region markers in app/.htaccess are out of order.';
        return null;
    }

    return substr($contents, 0, $begin) . $newBlock . substr($contents, $end + strlen(ALLOWLIST_END));
}

/**
 * The `Require ldap-filter` line currently inside the managed region, for display.
 */
function read_managed_require_line(): ?string {
    $contents = @file_get_contents(htaccess_path());
    if ($contents === false) {
        return null;
    }

    $begin = strpos($contents, ALLOWLIST_BEGIN);
    $end = strpos($contents, ALLOWLIST_END);
    if ($begin === false || $end === false || $end < $begin) {
        return null;
    }

    $region = substr($contents, $begin, $end - $begin);
    foreach (explode("\n", $region) as $line) {
        $line = trim($line);
        if (stripos($line, 'Require ') === 0) {
            return $line;
        }
    }

    return null;
}

/**
 * Copy the current app/.htaccess into the backup directory.
 *
 * Returns the backup path, or null with $error set. The timestamp format sorts
 * lexicographically in chronological order, and the pid suffix keeps two writes
 * in the same second from colliding.
 */
function htaccess_backup(?string &$error): ?string {
    $dir = htaccess_backup_dir();

    // 0700: these spell out the site's access rules and only ever need to be
    // read by the same account PHP runs as.
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        $error = 'Could not create the backup directory at ' . $dir . '.';
        return null;
    }

    $backup = $dir . '/app.htaccess.' . date('Ymd-His') . '.' . getmypid();
    if (!@copy(htaccess_path(), $backup)) {
        $error = 'Could not back up app/.htaccess, so nothing was changed.';
        return null;
    }

    prune_htaccess_backups();
    return $backup;
}

function prune_htaccess_backups(): void {
    $backups = list_htaccess_backups();
    foreach (array_slice($backups, ALLOWLIST_BACKUP_KEEP) as $old) {
        @unlink($old['path']);
    }
}

/**
 * Every backup, newest first.
 */
function list_htaccess_backups(): array {
    $dir = htaccess_backup_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $out = [];
    foreach ((array)@scandir($dir) as $name) {
        if (!valid_backup_name((string)$name)) {
            continue;
        }
        $path = $dir . '/' . $name;
        $out[] = [
            'name' => $name,
            'path' => $path,
            'mtime' => (int)@filemtime($path),
            'size' => (int)@filesize($path),
        ];
    }

    usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
    return $out;
}

/**
 * Whether a string is one of our own backup filenames.
 *
 * /recover/ takes this name from a form, so it is also what stops a request
 * naming ../../includes/db.php and having it copied over app/.htaccess.
 */
function valid_backup_name(string $name): bool {
    return preg_match('/^app\.htaccess\.[0-9]{8}-[0-9]{6}\.[0-9]+\z/', $name) === 1;
}

/**
 * Copy a named backup back over app/.htaccess. Used only by /recover/.
 *
 * Deliberately does no verification afterwards: this runs when things are
 * already broken, and the operator can see the result by reloading /app/.
 */
function restore_htaccess_backup(string $name, ?string &$error): bool {
    if (!valid_backup_name($name)) {
        $error = 'Not a valid backup name.';
        return false;
    }

    $path = htaccess_backup_dir() . '/' . $name;
    if (!is_file($path)) {
        $error = 'That backup no longer exists.';
        return false;
    }

    if (!@copy($path, htaccess_path())) {
        $error = 'Could not write app/.htaccess.';
        return false;
    }

    return true;
}

/**
 * Ask Apache whether /app/ still works, over real HTTP.
 *
 * A healthy protected directory answers an unauthenticated request with a 302 to
 * idp.uvm.edu. Requiring exactly that, rather than merely "not a 500", also
 * catches a filter that parses cleanly but matches nobody — that denies everyone
 * with a 403, which is just as much a lockout as a syntax error.
 *
 * The URL is a constant rather than being derived from HTTP_HOST so that a
 * spoofed Host header cannot point the check at something that would pass.
 */
function verify_app_reachable(?string &$detail): bool {
    $ch = curl_init(ALLOWLIST_SELF_TEST_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
    ]);

    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') {
        // Inconclusive counts as failure. If we cannot confirm the new rule
        // works, we do not get to keep it.
        $detail = 'could not reach the site to check: ' . $curlError;
        return false;
    }

    if ($code === 302 && stripos($location, 'idp.uvm.edu') !== false) {
        return true;
    }

    if ($code === 500) {
        $detail = 'Apache returned 500 — the rule is not valid configuration';
        return false;
    }

    $detail = 'expected a redirect to CAS, got HTTP ' . $code
        . ($location !== '' ? ' to ' . $location : '');
    return false;
}

/**
 * Write a new allow/block list into app/.htaccess, or change nothing at all.
 *
 * validate -> back up -> atomic swap -> verify over HTTP -> roll back on failure.
 *
 * Returns false with $error set if any step fails, in which case app/.htaccess
 * is left as it was found.
 */
function write_htaccess_block(array $allow, array $block, ?string &$error): bool {
    $path = htaccess_path();

    try {
        $newBlock = render_managed_block($allow, $block);
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        return false;
    }

    $current = @file_get_contents($path);
    if ($current === false) {
        $error = 'Could not read app/.htaccess.';
        return false;
    }

    $updated = replace_managed_block($current, $newBlock, $error);
    if ($updated === null) {
        return false;
    }

    // Compare everything except the "Last written" timestamp line, so that a
    // rebuild that changes nothing real does not burn a backup slot.
    $changed = strip_generated_timestamp($updated) !== strip_generated_timestamp($current);
    $backup = null;

    if ($changed) {
        $backup = htaccess_backup($error);
        if ($backup === null) {
            return false;
        }

        // Write beside the target and rename, so a reader never sees a partial
        // file. The temp name starts with .ht, which the root .htaccess already
        // denies over HTTP, so it is not exposed even mid-write.
        $tmp = dirname($path) . '/.htaccess.tmp-' . getmypid();
        if (@file_put_contents($tmp, $updated) !== strlen($updated)) {
            @unlink($tmp);
            $error = 'Could not write the temporary file; app/.htaccess is unchanged.';
            return false;
        }
        @chmod($tmp, 0644);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            $error = 'Could not replace app/.htaccess; it is unchanged.';
            return false;
        }
    }

    $detail = '';
    if (verify_app_reachable($detail)) {
        return true;
    }

    if (!$changed) {
        $error = 'app/.htaccess was already correct, but /app/ is not responding properly: ' . $detail . '.';
        return false;
    }

    if (@copy($backup, $path)) {
        $error = 'The new rule did not verify (' . $detail . '), so it was rolled back. Nothing changed.';
    } else {
        $error = 'The new rule did not verify (' . $detail . ') AND the rollback failed. '
            . 'Go to /recover/ and restore ' . basename($backup) . '.';
    }

    return false;
}

/**
 * Drop the generated "Last written" line so two renderings can be compared for
 * a real difference.
 */
function strip_generated_timestamp(string $contents): string {
    return preg_replace('/^# Last written .*$\n?/m', '', $contents) ?? $contents;
}

/**
 * The uids currently named in the managed region, read back out of the file.
 *
 * Two things need this. The Access tab uses it to generate the seed INSERTs
 * shown alongside the CREATE TABLE, so that populating the table for the first
 * time cannot silently drop the people already listed — an empty table would
 * otherwise regenerate the file down to ADMIN_UID alone. It is also what lets
 * the tab notice that the file and the table have drifted apart, which is what
 * a git checkout reverting app/.htaccess looks like.
 *
 * Returns ['allow' => [...], 'block' => [...]], both sorted, or null if there is
 * no readable managed region.
 */
function parse_managed_uids(): ?array {
    $line = read_managed_require_line();
    if ($line === null) {
        return null;
    }

    // Negated terms first, then everything else is an allow: the two patterns
    // overlap, since (!(uid=x)) contains a (uid=x).
    preg_match_all('/\(!\(uid=([a-z][a-z0-9]{0,15})\)\)/', $line, $blockMatches);
    preg_match_all('/\(uid=([a-z][a-z0-9]{0,15})\)/', $line, $allMatches);

    $block = array_values(array_unique($blockMatches[1]));
    $allow = array_values(array_diff(array_unique($allMatches[1]), $block));

    sort($allow, SORT_STRING);
    sort($block, SORT_STRING);

    return ['allow' => $allow, 'block' => $block];
}

/**
 * The SQL to create and seed allowed_users, for pasting into phpMyAdmin.
 *
 * The database account this app connects as has no CREATE grant, so the table
 * cannot be made from the portal. The seed rows matter as much as the DDL: the
 * table becomes the source of truth the moment it exists, and an empty one would
 * regenerate app/.htaccess down to ADMIN_UID alone — revoking everyone already
 * listed. Building the INSERTs from the live file keeps that from happening.
 *
 * Values are interpolated rather than bound because there is no connection here
 * to bind against. Every uid has already been through valid_uid() (letters and
 * digits only) and everything else is a literal, so there is nothing quotable to
 * escape.
 */
function allowlist_bootstrap_sql(?array $fileUids): string {
    $fileUids = $fileUids ?? ['allow' => [], 'block' => []];

    $sql = "CREATE TABLE `allowed_users` (\n"
        . "  `id` int NOT NULL AUTO_INCREMENT,\n"
        . "  `uid` varchar(16) NOT NULL,\n"
        . "  `kind` enum('allow','block') NOT NULL DEFAULT 'allow',\n"
        . "  `note` varchar(255) DEFAULT NULL,\n"
        . "  `added_by` varchar(50) NOT NULL,\n"
        . "  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (`id`),\n"
        . "  UNIQUE KEY `uid` (`uid`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;\n";

    $rows = [];
    foreach (['allow', 'block'] as $kind) {
        foreach ($fileUids[$kind] ?? [] as $uid) {
            if (!valid_uid((string)$uid)) {
                continue;
            }
            $rows[] = "  ('" . $uid . "', '" . $kind . "', 'migrated from app/.htaccess', '" . ADMIN_UID . "')";
        }
    }

    if ($rows) {
        $sql .= "\nINSERT INTO `allowed_users` (`uid`, `kind`, `note`, `added_by`) VALUES\n"
            . implode(",\n", $rows) . ";\n";
    }

    return $sql;
}
