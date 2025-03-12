<!-- connect-db.php -->
<?php
require '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();

$databaseName = $_ENV['DBNAME'];
$dsn = 'mysql:host=webdb.uvm.edu;dbname=' . $databaseName;
$username = $_ENV['DBUSER'];
$password = $_ENV['DBPASS'];

print '<!-- Connecting -->';
$pdo = new PDO($dsn, $username, $password);
print '<!-- Connection Complete -->';
?>