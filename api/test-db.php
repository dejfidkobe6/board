<?php
require_once 'config.php';
echo "HOST: " . DB_HOST . "<br>";
echo "NAME: " . DB_NAME . "<br>";
echo "USER: " . DB_USER . "<br>";
try {
    $pdo = getDB();
    echo "<b style='color:green'>✅ Připojení OK!</b>";
} catch(Exception $e) {
    echo "<b style='color:red'>❌ Chyba: " . $e->getMessage() . "</b>";
}
?>
