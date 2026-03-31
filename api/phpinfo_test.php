<?php
// TEMPORARY - delete after diagnosis
header('Content-Type: application/json');
$errors = [];
// Try including functions.php and catch any output
ob_start();
$ok = false;
try {
    require_once __DIR__ . '/config.php';
    $errors[] = 'config ok';
    require_once __DIR__ . '/functions.php';
    $errors[] = 'functions ok';
    $ok = true;
} catch (\Throwable $e) {
    $errors[] = 'exception: ' . $e->getMessage();
}
$out = ob_get_clean();
if ($out) $errors[] = 'stray_output: ' . substr($out, 0, 300);

echo json_encode([
    'php'      => PHP_VERSION,
    'ok'       => $ok,
    'errors'   => $errors,
    'session'  => session_status(),
    'user_id'  => $_SESSION['user_id'] ?? null,
]);
