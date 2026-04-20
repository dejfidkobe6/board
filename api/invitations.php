<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// emailTemplate() and sendMail() are provided by functions.php

if ($action === 'send') {
(function () use ($method) {
    if ($method !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $body      = getBody();
    $projectId = (int)($body['project_id'] ?? 0);
    $email     = strtolower(trim($body['email'] ?? ''));
    $role      = sanitize($body['role'] ?? 'member');

    if (!$projectId) jsonResponse(['error' => 'Chybí project_id'], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Neplatný email'], 422);
    if (!in_array($role, ['admin','member','viewer'], true)) jsonResponse(['error' => 'Neplatná role'], 422);

    $actor = requireProjectRole($projectId, 'admin');

    $db = getDB();

    // Get project info
    $stmt = $db->prepare('SELECT p.name, a.app_name FROM projects p JOIN apps a ON a.id=p.app_id WHERE p.id=?');
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    if (!$project) jsonResponse(['error' => 'Projekt nenalezen'], 404);

    // Check if user already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // Check if already member
        $stmt = $db->prepare('SELECT id FROM project_members WHERE project_id=? AND user_id=?');
        $stmt->execute([$projectId, $existingUser['id']]);
        if ($stmt->fetch()) jsonResponse(['error' => 'Uživatel je již členem projektu'], 409);

        // Add directly
        $db->prepare(
            'INSERT INTO project_members (project_id, user_id, role, invited_by) VALUES (?,?,?,?)'
        )->execute([$projectId, $existingUser['id'], $role, $actor['id']]);

        // Record in invitations history so it appears in the panel
        $db->prepare(
            'INSERT INTO invitations (project_id, invited_email, invited_by, token, role, status, expires_at)
             VALUES (?,?,?,?,?,"accepted", NOW())'
        )->execute([$projectId, $email, $actor['id'], bin2hex(random_bytes(16)), $role]);

        jsonResponse(['success' => true, 'message' => 'Uživatel přidán do projektu.']);
    }

    // Non-existing user: create pending invitation record (no email sent)
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 365 * 86400); // 1 year

    $db->prepare(
        'INSERT INTO invitations (project_id, invited_email, invited_by, token, role, expires_at)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE token=VALUES(token), expires_at=VALUES(expires_at), status="pending"'
    )->execute([$projectId, $email, $actor['id'], $token, $role, $expires]);

    jsonResponse(['success' => true, 'message' => 'Pozvánka vytvořena pro ' . htmlspecialchars($email) . '. Uživatel ji uvidí po registraci.']);
})();
} elseif ($action === 'accept') {
(function () {
    $token = trim($_GET['token'] ?? '');
    if (!$token) { header('Location: /login.php?error=invalid_token'); exit; }

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT i.*, p.name AS project_name FROM invitations i
         JOIN projects p ON p.id = i.project_id
         WHERE i.token = ? AND i.status = "pending" AND i.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $inv = $stmt->fetch();

    if (!$inv) { header('Location: /login.php?error=invite_expired'); exit; }

    // Must be logged in
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php?redirect=' . urlencode('/api/invitations.php?action=accept&token=' . $token));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    // Check email matches
    $stmt = $db->prepare('SELECT email FROM users WHERE id=?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (strtolower($user['email']) !== strtolower($inv['invited_email'])) {
        header('Location: /dashboard.php?error=invite_wrong_account');
        exit;
    }

    // Add to project
    $db->prepare(
        'INSERT IGNORE INTO project_members (project_id, user_id, role, invited_by) VALUES (?,?,?,?)'
    )->execute([$inv['project_id'], $userId, $inv['role'], $inv['invited_by']]);
    $db->prepare('UPDATE invitations SET status="accepted" WHERE id=?')->execute([$inv['id']]);

    header('Location: /dashboard.php?joined=' . $inv['project_id']);
    exit;
})();
} elseif ($action === 'join') {
(function () {
    $user       = requireAuth();
    $inviteCode = sanitize($_GET['invite_code'] ?? '');
    if (!$inviteCode) jsonResponse(['error' => 'Chybí invite_code'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM projects WHERE invite_code=? AND is_active=1');
    $stmt->execute([$inviteCode]);
    $project = $stmt->fetch();
    if (!$project) jsonResponse(['error' => 'Neplatný kód projektu'], 404);

    // Already member?
    $stmt = $db->prepare('SELECT role FROM project_members WHERE project_id=? AND user_id=?');
    $stmt->execute([$project['id'], $user['id']]);
    $existing = $stmt->fetch();
    if ($existing) jsonResponse(['success' => true, 'project' => $project, 'role' => $existing['role'], 'already_member' => true]);

    $db->prepare(
        'INSERT INTO project_members (project_id, user_id, role) VALUES (?,?,"member")'
    )->execute([$project['id'], $user['id']]);

    jsonResponse(['success' => true, 'project' => $project, 'role' => 'member']);
})();
} elseif ($action === 'cancel') {
(function () use ($method) {
    if ($method !== 'DELETE') jsonResponse(['error' => 'Method not allowed'], 405);
    $inviteId  = (int)($_GET['invite_id']  ?? 0);
    $projectId = (int)($_GET['project_id'] ?? 0);
    if (!$inviteId || !$projectId) jsonResponse(['error' => 'Chybí parametry'], 422);

    requireProjectRole($projectId, 'admin');

    $db = getDB();
    $db->prepare(
        'DELETE FROM invitations WHERE id = ? AND project_id = ? AND status = "pending"'
    )->execute([$inviteId, $projectId]);

    jsonResponse(['success' => true]);
})();
} else {
    jsonResponse(['error' => 'Neznámá akce'], 400);
}
