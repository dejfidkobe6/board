<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'list') {
(function () {
    $user   = requireAuth();
    $appKey = sanitize($_GET['app_key'] ?? '');

    $db = getDB();

    $sql = '
        SELECT p.id, p.name, p.description, p.invite_code, p.bg_color, p.created_at,
               pm.role,
               a.app_key, a.app_name,
               (SELECT COUNT(*) FROM project_members pm2 WHERE pm2.project_id = p.id) AS member_count
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = ?
        JOIN apps a ON a.id = p.app_id
        WHERE p.is_active = 1
    ';
    $params = [$user['id']];

    if ($appKey) {
        $sql .= ' AND a.app_key = ?';
        $params[] = $appKey;
    }
    $sql .= ' ORDER BY a.app_key, p.created_at DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['success' => true, 'projects' => $stmt->fetchAll()]);
})();
} elseif ($action === 'create') {
(function () use ($method) {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $user = requireAuth();
    $body = getBody();

    $appKey = sanitize($body['app_key']     ?? '');
    $name   = sanitize($body['name']        ?? '');
    $desc   = sanitize($body['description'] ?? '');
    $bgColor= sanitize($body['bg_color']    ?? '#4a5240');

    if (!$appKey) jsonResponse(['error' => 'app_key je povinný'], 422);
    if (!$name)   jsonResponse(['error' => 'Název projektu je povinný'], 422);

    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM apps WHERE app_key = ?');
    $stmt->execute([$appKey]);
    $app = $stmt->fetch();
    if (!$app) jsonResponse(['error' => 'Neznámá aplikace'], 404);

    $inviteCode = bin2hex(random_bytes(16));

    $db->prepare(
        'INSERT INTO projects (app_id, name, description, created_by, invite_code, bg_color)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$app['id'], $name, $desc, $user['id'], $inviteCode, $bgColor]);
    $projectId = (int)$db->lastInsertId();

    // Creator becomes owner
    $db->prepare(
        'INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, "owner")'
    )->execute([$projectId, $user['id']]);

    $stmt = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    $project['role'] = 'owner';

    jsonResponse(['success' => true, 'project' => $project]);
})();
} elseif ($action === 'detail') {
(function () {
    $projectId = (int)($_GET['id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí id'], 422);
    $member = requireProjectRole($projectId, 'viewer');

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT p.*, a.app_key, a.app_name FROM projects p
         JOIN apps a ON a.id = p.app_id WHERE p.id = ?'
    );
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) jsonResponse(['error' => 'Projekt nenalezen'], 404);
    $project['my_role'] = $member['role'];

    jsonResponse(['success' => true, 'project' => $project]);
})();
} elseif ($action === 'members') {
(function () {
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    requireProjectRole($projectId, 'viewer');

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.email, u.avatar_color, pm.role, pm.joined_at
         FROM project_members pm JOIN users u ON u.id = pm.user_id
         WHERE pm.project_id = ? ORDER BY pm.joined_at ASC'
    );
    $stmt->execute([$projectId]);
    jsonResponse(['success' => true, 'members' => $stmt->fetchAll()]);
})();
} elseif ($action === 'update_role') {
(function () use ($method) {
    if ($method !== 'PUT') jsonResponse(['error' => 'Method not allowed'], 405);
    $body      = getBody();
    $projectId = (int)($body['project_id'] ?? 0);
    $targetId  = (int)($body['user_id']    ?? 0);
    $newRole   = sanitize($body['role']    ?? '');

    if (!in_array($newRole, ['admin','member','viewer'], true))
        jsonResponse(['error' => 'Neplatná role'], 422);

    $actor = requireProjectRole($projectId, 'admin');

    $db   = getDB();
    $stmt = $db->prepare('SELECT role FROM project_members WHERE project_id=? AND user_id=?');
    $stmt->execute([$projectId, $targetId]);
    $target = $stmt->fetch();
    if (!$target) jsonResponse(['error' => 'Člen nenalezen'], 404);
    if ($target['role'] === 'owner') jsonResponse(['error' => 'Owner nelze degradovat'], 403);

    $db->prepare('UPDATE project_members SET role=? WHERE project_id=? AND user_id=?')
       ->execute([$newRole, $projectId, $targetId]);
    jsonResponse(['success' => true]);
})();
} elseif ($action === 'remove_member') {
(function () use ($method) {
    if ($method !== 'DELETE') jsonResponse(['error' => 'Method not allowed'], 405);
    $projectId = (int)($_GET['project_id'] ?? 0);
    $targetId  = (int)($_GET['user_id']    ?? 0);

    $actor = requireProjectRole($projectId, 'admin');

    $db   = getDB();
    $stmt = $db->prepare('SELECT role FROM project_members WHERE project_id=? AND user_id=?');
    $stmt->execute([$projectId, $targetId]);
    $target = $stmt->fetch();
    if (!$target) jsonResponse(['error' => 'Člen nenalezen'], 404);
    if ($target['role'] === 'owner') jsonResponse(['error' => 'Owner nemůže být odebrán'], 403);

    $db->prepare('DELETE FROM project_members WHERE project_id=? AND user_id=?')
       ->execute([$projectId, $targetId]);
    jsonResponse(['success' => true]);
})();
} else {
    jsonResponse(['error' => 'Neznámá akce'], 400);
}
