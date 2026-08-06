<?php
require __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/visibility.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Absolute path to the web root — use for all filesystem operations
define('ROOT_DIR', realpath(__DIR__ . '/..'));

/**
 * Convert a DB-stored relative path (e.g. ./public/images/x.jpg) to an absolute filesystem path.
 */
function resolve_path(string $path): string {
    $clean = ltrim($path, './');
    return ROOT_DIR . '/' . $clean;
}

$pdo = new PDO(
    'mysql:host=webdb.uvm.edu;dbname=' . $_ENV['DBNAME'],
    $_ENV['DBUSER'],
    $_ENV['DBPASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
