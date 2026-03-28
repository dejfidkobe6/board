<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$checks = [];

// Check config exists
$checks['config_exists'] = file_exists(__DIR__ . '/config.php');

if ($checks['config_exists']) {
    require_once 'config.php';
    $checks['db_host'] = defined('DB_HOST') ? (DB_HOST ?: 'EMPTY') : 'NOT_DEFINED';
    $checks['db_name'] = defined('DB_NAME') ? (DB_NAME ?: 'EMPTY') : 'NOT_DEFINED';
    $checks['db_user'] = defined('DB_USER') ? (DB_USER ?: 'EMPTY') : 'NOT_DEFINED';
    $checks['db_pass_set'] = defined('DB_PASS') && DB_PASS !== '' ? 'YES' : 'NO';

    // Try DB connection
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS
        );
        $checks['db_connect'] = 'OK';
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $checks['tables'] = $tables;
    } catch (Exception $e) {
        $checks['db_connect'] = 'FAIL: ' . $e->getMessage();
    }
}

echo json_encode($checks, JSON_PRETTY_PRINT);
