<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Auto-create tables on first use
(function () {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS `project_kanban_state` (
        `project_id` INT UNSIGNED NOT NULL,
        `state_json` MEDIUMTEXT NOT NULL DEFAULT '{}',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`project_id`),
        CONSTRAINT `fk_kanban_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Backup table — keeps last 20 snapshots per project
    $db->exec("CREATE TABLE IF NOT EXISTS `project_kanban_backups` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT UNSIGNED NOT NULL,
        `state_json` MEDIUMTEXT NOT NULL,
        `columns_count` SMALLINT NOT NULL DEFAULT 0,
        `cards_count` SMALLINT NOT NULL DEFAULT 0,
        `saved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_project_saved` (`project_id`, `saved_at`)
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

    // Accept FormData (multipart) OR raw JSON body
    if (isset($_POST['state'])) {
        $newState = json_decode($_POST['state'], true);
    } else {
        $body = getBody();
        $newState = $body['state'] ?? null;
    }
    if (!is_array($newState)) jsonResponse(['error' => 'Chybí state'], 422);
    $cols  = count($newState['columns'] ?? []);
    $cards = count($newState['cards']   ?? []);

    // Refuse to overwrite non-empty data with empty data (safety guard)
    $db = getDB();
    $existing = $db->prepare('SELECT state_json FROM project_kanban_state WHERE project_id = ?');
    $existing->execute([$projectId]);
    $row = $existing->fetch();
    if ($row) {
        $old     = json_decode($row['state_json'], true);
        $oldCols  = count($old['columns'] ?? []);
        $oldCards = count($old['cards']   ?? []);
        // Reject: would wipe non-empty kanban data with empty payload
        // (but allow save if payload has meeting/phase/schedule data)
        $hasExtra = !empty($newState['livingMeeting']['agenda'])
                 || !empty($newState['meetingVersions'])
                 || !empty($newState['phases'])
                 || !empty($newState['schedule']);
        if (($oldCols > 0 || $oldCards > 0) && $cols === 0 && $cards === 0 && !$hasExtra) {
            jsonResponse(['success' => true, 'skipped' => 'empty_guard']);
        }
    }

    $stateJson = json_encode($newState, JSON_UNESCAPED_UNICODE);

    // Save backup snapshot (keep last 20 per project)
    if ($cols > 0 || $cards > 0) {
        $db->prepare(
            'INSERT INTO project_kanban_backups (project_id, state_json, columns_count, cards_count)
             VALUES (?, ?, ?, ?)'
        )->execute([$projectId, $stateJson, $cols, $cards]);
        // Prune old backups — keep newest 20
        $db->prepare(
            'DELETE FROM project_kanban_backups WHERE project_id = ? AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM project_kanban_backups WHERE project_id = ?
                    ORDER BY saved_at DESC LIMIT 20
                ) t
            )'
        )->execute([$projectId, $projectId]);
    }

    $db->prepare(
        'INSERT INTO project_kanban_state (project_id, state_json)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = NOW()'
    )->execute([$projectId, $stateJson]);

    jsonResponse(['success' => true]);
})();
} elseif ($action === 'backups') {
(function () {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    requireProjectRole($projectId, 'admin');
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT id, saved_at, columns_count, cards_count
         FROM project_kanban_backups WHERE project_id = ?
         ORDER BY saved_at DESC LIMIT 20'
    );
    $stmt->execute([$projectId]);
    jsonResponse(['success' => true, 'backups' => $stmt->fetchAll()]);
})();
} elseif ($action === 'restore') {
(function () use ($method) {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $projectId = (int)($_GET['project_id'] ?? 0);
    $backupId  = (int)($_GET['backup_id']  ?? 0);
    if (!$projectId || !$backupId) jsonResponse(['error' => 'Chybí parametry'], 422);
    requireProjectRole($projectId, 'admin');
    $db = getDB();
    $stmt = $db->prepare('SELECT state_json FROM project_kanban_backups WHERE id = ? AND project_id = ?');
    $stmt->execute([$backupId, $projectId]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(['error' => 'Záloha nenalezena'], 404);
    $db->prepare(
        'INSERT INTO project_kanban_state (project_id, state_json)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = NOW()'
    )->execute([$projectId, $row['state_json']]);
    $state = json_decode($row['state_json'], true);
    jsonResponse(['success' => true, 'state' => $state]);
})();
} else {
    jsonResponse(['error' => 'Neznámá akce', 'received_action' => $action, 'method' => $method, 'get_keys' => array_keys($_GET)], 400);
}
