<!-- connect-db.php -->
<?php
require __DIR__ . '/./vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$databaseName = $_ENV['DBNAME'];
$dsn = 'mysql:host=webdb.uvm.edu;dbname=' . $databaseName;
$username = $_ENV['DBUSER'];
$password = $_ENV['DBPASS'];

print '<!-- Connecting -->';
$pdo = new PDO($dsn, $username, $password);
print '<!-- Connection Complete -->';

// Query the maximum price from sublets
$stmt = $pdo->query("SELECT MAX(price) as max_price FROM sublets");
$maxPriceResult = $stmt->fetch(PDO::FETCH_ASSOC);
$maxPrice = $maxPriceResult['max_price'] ?? 3000; // default if no data
$maxPriceRounded = ceil($maxPrice / 50) * 50;
?>