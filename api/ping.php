<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$checks = [];

$checks['config_exists'] = file_exists(__DIR__ . '/config.php');
$checks['auth_exists'] = file_exists(__DIR__ . '/auth.php');
$checks['php_version'] = PHP_VERSION;

if ($checks['config_exists']) {
    require_once 'config.php';
    $checks['db_host'] = defined('DB_HOST') ? (DB_HOST ?: 'EMPTY') : 'NOT_DEFINED';
    $checks['db_name'] = defined('DB_NAME') ? (DB_NAME ?: 'EMPTY') : 'NOT_DEFINED';
    $checks['db_user'] = defined('DB_USER') ? (DB_USER ?: 'EMPTY') : 'NOT_DEFINED';
    $checks['db_pass_set'] = defined('DB_PASS') && DB_PASS !== '' ? 'YES' : 'NO';

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS
        );
        $checks['db_connect'] = 'OK';

        // Try inserting a test to check write access
        $checks['tables'] = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $checks['db_connect'] = 'FAIL: ' . $e->getMessage();
    }

    // Try to load auth.php and catch any parse/syntax errors
    $checks['auth_syntax'] = 'checking...';
    ob_start();
    $result = @include_once __DIR__ . '/auth.php';
    $output = ob_get_clean();
    $checks['auth_output_length'] = strlen($output);
    $checks['auth_output_preview'] = substr($output, 0, 300);
}

echo json_encode($checks, JSON_PRETTY_PRINT);
