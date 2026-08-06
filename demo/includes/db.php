<?php
require __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/../../includes/visibility.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

define('DEMO_MODE', true);
$SUBLET_TABLE = 'sublets_demo';
$IMAGES_TABLE = 'sublet_images_demo';

$pdo = new PDO(
    'mysql:host=webdb.uvm.edu;dbname=' . $_ENV['DBNAME'],
    $_ENV['DBUSER'],
    $_ENV['DBPASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
