<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'load') {
(function () {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    requireProjectRole($projectId, 'viewer');

    $db   = getDB();
    $stmt = $db->prepare('SELECT state_json FROM project_kanban_state WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $row  = $stmt->fetch();

    if ($row) {
        $state = json_decode($row['state_json'], true);
        jsonResponse(['success' => true, 'state' => $state]);
    } else {
        jsonResponse(['success' => true, 'state' => null]);
    }
})();
} elseif ($action === 'save') {
(function () use ($method) {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    requireProjectRole($projectId, 'member');

    $body = getBody();
    if (!isset($body['state'])) jsonResponse(['error' => 'Chybí state'], 422);

    $stateJson = json_encode($body['state'], JSON_UNESCAPED_UNICODE);

    $db = getDB();
    $db->prepare(
        'INSERT INTO project_kanban_state (project_id, state_json)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = NOW()'
    )->execute([$projectId, $stateJson]);

    jsonResponse(['success' => true]);
})();
} else {
    jsonResponse(['error' => 'Neznámá akce'], 400);
}
