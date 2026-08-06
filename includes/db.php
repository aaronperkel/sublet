<?php
require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/visibility.php';
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/listing_fields.php';
require_once __DIR__ . '/listing_query.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Absolute path to the web root — use for all filesystem operations
define('ROOT_DIR', realpath(__DIR__ . '/..'));

/**
 * Convert a DB-stored relative path (e.g. ./public/images/x.jpg) to an absolute filesystem path.
 *
 * The result is handed to unlink() by delete_image_files(), so a stored path
 * carrying a traversal segment must not be able to walk out of the web root.
 * Every path the app writes is built from basename() and so can never contain
 * one — this is the backstop for a row that got there some other way.
 */
function resolve_path(string $path): string {
    $clean = ltrim($path, './');

    // Returning the root directory itself makes is_file() false and unlink() a
    // no-op, so a bad path fails closed instead of deleting something.
    if (str_contains($clean, '..')) {
        return ROOT_DIR . '/';
    }

    return ROOT_DIR . '/' . $clean;
}

$pdo = new PDO(
    'mysql:host=webdb.uvm.edu;dbname=' . $_ENV['DBNAME'],
    $_ENV['DBUSER'],
    $_ENV['DBPASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/**
 * Names of the columns that actually exist on a table, as a lookup set.
 *
 * There is no migration runner in this project — schema changes are pasted into
 * phpMyAdmin by hand, which means the code and the database are briefly out of
 * step. Reads survive that on their own (the queries are SELECT s.*, so a
 * missing column is just an absent array key), but an INSERT or UPDATE that
 * names a column the database does not have is a fatal error on a live site.
 * Writers build their column list through this so they work either way.
 *
 * One SHOW COLUMNS per table per request, cached for the rest of it.
 */
function table_columns(PDO $pdo, string $table): array {
    static $cache = [];

    if (!isset($cache[$table])) {
        // Interpolated, so keep it to identifiers this app actually owns.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return [];
        }
        $cache[$table] = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $row) {
            $cache[$table][$row['Field']] = true;
        }
    }

    return $cache[$table];
}
