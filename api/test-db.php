<?php
$host = getenv('DB_HOST') ?: 'hodnota-nebyla-nastavena';
$name = getenv('DB_NAME') ?: 'hodnota-nebyla-nastavena';
$user = getenv('DB_USER') ?: 'hodnota-nebyla-nastavena';

echo "HOST: " . $host . "<br>";
echo "NAME: " . $name . "<br>";
echo "USER: " . $user . "<br>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, getenv('DB_PASS'));
    echo "<b style='color:green'>✅ Připojení OK!</b>";
} catch(Exception $e) {
    echo "<b style='color:red'>❌ Chyba: " . $e->getMessage() . "</b>";
}
?>
