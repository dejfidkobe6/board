<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function inviteEmailBody(string $inviterName, string $projectName, string $acceptUrl): string {
    $inviterEsc = htmlspecialchars($inviterName);
    $projectEsc = htmlspecialchars($projectName);
    $content = "
        <p><strong>$inviterEsc</strong> tě zve do projektu <strong>$projectEsc</strong> na BeSix Board.</p>
        <p>Klikni pro přijetí pozvánky:</p>
        <p><a class=\"btn\" href=\"$acceptUrl\">Přijmout pozvánku</a></p>
        <p style=\"font-size:13px;color:#888\">Pozvánka je platná 7 dní.</p>
    ";
    // reuse emailTemplate from auth.php context — define inline
    $headers = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0}
.wrap{max-width:520px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden}
.hdr{background:#4A5340;padding:28px 32px}.hdr h1{color:#fff;margin:0;font-size:22px}
.body{padding:32px}p{color:#444;line-height:1.6;margin:0 0 16px}
.btn{display:inline-block;background:#4A5340;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600}
.foot{padding:20px 32px;background:#f9f9f9;font-size:12px;color:#999}</style>
</head><body><div class="wrap">
<div class="hdr"><h1>BeSix Board</h1></div>
<div class="body"><h2 style="margin:0 0 16px;color:#1a1a1a">Pozvánka do projektu</h2>$content</div>
<div class="foot">Pokud jsi tuto pozvánku neočekával/a, ignoruj email.</div>
</div></body></html>
HTML;
    return $headers;
}

match ($action) {

// ─── SEND invitation ──────────────────────────────────────────────────────
'send' => (function () use ($method) {
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

        jsonResponse(['success' => true, 'message' => 'Uživatel přidán přímo do projektu.']);
    }

    // Create invitation for non-existing user
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 7 * 86400);

    $db->prepare(
        'INSERT INTO invitations (project_id, invited_email, invited_by, token, role, expires_at)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE token=VALUES(token), expires_at=VALUES(expires_at), status="pending"'
    )->execute([$projectId, $email, $actor['id'], $token, $role, $expires]);

    $acceptUrl = APP_URL . '/invite.php?token=' . urlencode($token);
    $emailBody = inviteEmailBody($actor['name'], $project['name'], $acceptUrl);
    @mail(
        $email,
        'Pozvánka do projektu ' . $project['name'] . ' – BeSix Board',
        $emailBody,
        "From: BeSix Board <" . MAIL_FROM . ">\r\nContent-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n"
    );

    jsonResponse(['success' => true, 'message' => 'Pozvánka odeslána na ' . htmlspecialchars($email)]);
})(),

// ─── ACCEPT invitation (via token link) ───────────────────────────────────
'accept' => (function () {
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
})(),

// ─── JOIN via invite_code ─────────────────────────────────────────────────
'join' => (function () {
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
})(),

default => jsonResponse(['error' => 'Neznámá akce'], 400),
};
