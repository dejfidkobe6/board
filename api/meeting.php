<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Auto-create table
(function () {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS project_meeting_state (
        project_id       INT UNSIGNED NOT NULL PRIMARY KEY,
        living_meeting   MEDIUMTEXT   NOT NULL DEFAULT '{}',
        meeting_versions MEDIUMTEXT   NOT NULL DEFAULT '[]',
        phases           MEDIUMTEXT   NOT NULL DEFAULT '[]',
        schedule         MEDIUMTEXT   NOT NULL DEFAULT '{}',
        updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_ms_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
})();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'load') {
(function () {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    requireProjectRole($projectId, 'viewer');

    $db   = getDB();
    $stmt = $db->prepare('SELECT living_meeting, meeting_versions, phases, schedule FROM project_meeting_state WHERE project_id = ?');
    $stmt->execute([$projectId]);
    $row  = $stmt->fetch();

    if ($row) {
        jsonResponse(['success' => true, 'data' => [
            'livingMeeting'   => json_decode($row['living_meeting'],   true) ?: (object)[],
            'meetingVersions' => json_decode($row['meeting_versions'], true) ?: [],
            'phases'          => json_decode($row['phases'],           true) ?: [],
            'schedule'        => json_decode($row['schedule'],         true) ?: (object)[],
        ]]);
    } else {
        jsonResponse(['success' => true, 'data' => null]);
    }
})();
} elseif ($action === 'save') {
(function () use ($method) {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    requireProjectRole($projectId, 'member');

    $body = getBody();
    $livingMeeting   = json_encode($body['livingMeeting']   ?? [], JSON_UNESCAPED_UNICODE);
    $meetingVersions = json_encode($body['meetingVersions'] ?? [], JSON_UNESCAPED_UNICODE);
    $phases          = json_encode($body['phases']          ?? [], JSON_UNESCAPED_UNICODE);
    $schedule        = json_encode($body['schedule']        ?? [], JSON_UNESCAPED_UNICODE);

    $db = getDB();
    $db->prepare(
        'INSERT INTO project_meeting_state (project_id, living_meeting, meeting_versions, phases, schedule)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           living_meeting   = VALUES(living_meeting),
           meeting_versions = VALUES(meeting_versions),
           phases           = VALUES(phases),
           schedule         = VALUES(schedule),
           updated_at       = NOW()'
    )->execute([$projectId, $livingMeeting, $meetingVersions, $phases, $schedule]);

    jsonResponse(['success' => true]);
})();
} else {
    jsonResponse(['error' => 'Neznámá akce'], 400);
}
