<?php
// Suppress JSON header from config.php
header_remove('Content-Type');
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config.php';

$host = defined('DB_HOST') ? DB_HOST : 'konstanta-neni-definovana';
$name = defined('DB_NAME') ? DB_NAME : 'konstanta-neni-definovana';
$user = defined('DB_USER') ? DB_USER : 'konstanta-neni-definovana';

echo "HOST: " . htmlspecialchars($host) . "<br>";
echo "NAME: " . htmlspecialchars($name) . "<br>";
echo "USER: " . htmlspecialchars($user) . "<br>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, DB_PASS);
    echo "<b style='color:green'>✅ Připojení OK!</b>";
} catch(Exception $e) {
    echo "<b style='color:red'>❌ Chyba: " . htmlspecialchars($e->getMessage()) . "</b>";
}
?>
