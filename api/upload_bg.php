<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$user      = requireAuth();
$projectId = (int)($_POST['project_id'] ?? 0);
if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);

requireProjectRole($projectId, 'admin');

$file = $_FILES['bg_image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['error' => 'Soubor se nepodařilo nahrát'], 422);
}

// Validate MIME type via finfo (not trusting extension/browser header)
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo        = new finfo(FILEINFO_MIME_TYPE);
$mimeType     = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowedMimes, true)) {
    jsonResponse(['error' => 'Nepodporovaný formát. Povoleno: JPG, PNG, GIF, WEBP'], 422);
}

// Max 8 MB
if ($file['size'] > 8 * 1024 * 1024) {
    jsonResponse(['error' => 'Soubor je příliš velký (max 8 MB)'], 422);
}

$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext    = $extMap[$mimeType];

$dir = __DIR__ . '/../assets/project_bg/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Delete previous bg image for this project
$db   = getDB();

// Auto-create bg_image column if missing (safe on first use)
try {
    $db->exec("ALTER TABLE projects ADD COLUMN bg_image VARCHAR(500) NULL DEFAULT NULL");
} catch (PDOException $e) {
    // Column already exists – ignore
}

$stmt = $db->prepare('SELECT bg_image FROM projects WHERE id = ?');
$stmt->execute([$projectId]);
$oldProj = $stmt->fetch();
if ($oldProj && $oldProj['bg_image']) {
    $oldPath = __DIR__ . '/..' . $oldProj['bg_image'];
    if (file_exists($oldPath)) {
        unlink($oldPath);
    }
}

$filename = 'proj_' . $projectId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$filepath = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    jsonResponse(['error' => 'Nepodařilo se uložit soubor na disk'], 500);
}

$url = '/assets/project_bg/' . $filename;
$db->prepare('UPDATE projects SET bg_image = ? WHERE id = ?')->execute([$url, $projectId]);

jsonResponse(['success' => true, 'url' => $url]);
