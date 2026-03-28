<?php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// ─── Rate limiting (max 5 login attempts per 15 min) ──────────────────────
function checkRateLimit(string $key): void {
    $now     = time();
    $window  = 15 * 60;
    $maxTry  = 5;
    $hits    = $_SESSION['rl'][$key] ?? [];
    $hits    = array_filter($hits, fn($t) => ($now - $t) < $window);
    if (count($hits) >= $maxTry) {
        jsonResponse(['error' => 'Příliš mnoho pokusů. Zkus to za 15 minut.'], 429);
    }
    $hits[] = $now;
    $_SESSION['rl'][$key] = array_values($hits);
}

function clearRateLimit(string $key): void {
    unset($_SESSION['rl'][$key]);
}

// ─── Send email helper ─────────────────────────────────────────────────────
function sendMail(string $to, string $subject, string $body): void {
    $headers  = "From: BeSix Board <" . MAIL_FROM . ">\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    @mail($to, $subject, $body, $headers);
}

function emailTemplate(string $title, string $content): string {
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
.wrap{max-width:520px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden}
.hdr{background:#4A5340;padding:28px 32px}
.hdr h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.body{padding:32px}p{color:#444;line-height:1.6;margin:0 0 16px}
.btn{display:inline-block;background:#4A5340;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600}
.foot{padding:20px 32px;background:#f9f9f9;font-size:12px;color:#999}</style>
</head><body><div class="wrap">
<div class="hdr"><h1>BeSix Board</h1></div>
<div class="body"><h2 style="margin:0 0 16px;color:#1a1a1a">$title</h2>$content</div>
<div class="foot">Pokud jsi tento email neočekával/a, ignoruj ho.</div>
</div></body></html>
HTML;
}

// ══════════════════════════════════════════════════════════════════════════
if ($action === 'register') {
(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

    $body = getBody();
    $name     = sanitize($body['name']             ?? '');
    $email    = strtolower(trim($body['email']     ?? ''));
    $pass     = $body['password']                  ?? '';
    $passConf = $body['password_confirm']          ?? '';
    $invite   = sanitize($body['invite_token']     ?? '');

    if (!$name)                               jsonResponse(['error' => 'Jméno je povinné'], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'Neplatný email'], 422);
    if (strlen($pass) < 8)                    jsonResponse(['error' => 'Heslo musí mít alespoň 8 znaků'], 422);
    if ($pass !== $passConf)                  jsonResponse(['error' => 'Hesla se neshodují'], 422);

    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) jsonResponse(['error' => 'Email je již registrován'], 409);

    $hash  = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $token = bin2hex(random_bytes(32));
    $colors = ['#4A5340','#3a4d1a','#1e3a2e','#1e2d3a','#2a1e3a','#3a1e2a','#2d2010'];
    $color = $colors[array_rand($colors)];

    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, avatar_color, is_verified, verification_token)
         VALUES (?, ?, ?, ?, 0, ?)'
    );
    $stmt->execute([$name, $email, $hash, $color, $token]);
    $userId = (int)$db->lastInsertId();

    // Handle invite token
    if ($invite) {
        $si = $db->prepare(
            'SELECT i.*, p.name AS project_name FROM invitations i
             JOIN projects p ON p.id = i.project_id
             WHERE i.token = ? AND i.status = "pending" AND i.expires_at > NOW()'
        );
        $si->execute([$invite]);
        $inv = $si->fetch();
        if ($inv && strtolower($inv['invited_email']) === $email) {
            $db->prepare(
                'INSERT IGNORE INTO project_members (project_id, user_id, role, invited_by) VALUES (?, ?, ?, ?)'
            )->execute([$inv['project_id'], $userId, $inv['role'], $inv['invited_by']]);
            $db->prepare('UPDATE invitations SET status="accepted" WHERE id=?')->execute([$inv['id']]);
        }
    }

    $verifyUrl = APP_URL . '/api/auth.php?action=verify&token=' . urlencode($token);
    $body = emailTemplate('Ověření emailu', "
        <p>Ahoj <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>Klikni na tlačítko níže pro ověření tvého účtu:</p>
        <p><a class=\"btn\" href=\"$verifyUrl\">Ověřit email</a></p>
        <p style=\"font-size:13px;color:#888\">Odkaz je platný 7 dní.</p>
    ");
    sendMail($email, 'Ověření účtu – BeSix Board', $body);

    jsonResponse(['success' => true, 'message' => 'Registrace proběhla. Zkontroluj email pro ověření.']);
})();
} elseif ($action === 'verify') {
(function () {
    $token = trim($_GET['token'] ?? '');
    if (!$token) { header('Location: /login.php?error=invalid_token'); exit; }

    $db   = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE verification_token = ? AND is_verified = 0');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) { header('Location: /login.php?error=invalid_token'); exit; }

    $db->prepare('UPDATE users SET is_verified=1, verification_token=NULL WHERE id=?')
       ->execute([$user['id']]);

    header('Location: /login.php?verified=1');
    exit;
})();
} elseif ($action === 'login') {
(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

    $body  = getBody();
    $email = strtolower(trim($body['email'] ?? ''));
    $pass  = $body['password'] ?? '';

    checkRateLimit('login_' . $email);

    if (!$email || !$pass) jsonResponse(['error' => 'Vyplň email a heslo'], 422);

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, password_hash, avatar_color, is_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        jsonResponse(['error' => 'Nesprávný email nebo heslo'], 401);
    }
    if (!$user['is_verified']) {
        jsonResponse(['error' => 'Účet není ověřen. Zkontroluj email.', 'unverified' => true], 403);
    }

    clearRateLimit('login_' . $email);
    session_regenerate_id(true);

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['avatar_color'] = $user['avatar_color'];

    jsonResponse(['success' => true, 'user' => [
        'id'           => $user['id'],
        'name'         => $user['name'],
        'email'        => $user['email'],
        'avatar_color' => $user['avatar_color'],
    ]]);
})();
} elseif ($action === 'logout') {
(function () {
    session_destroy();
    jsonResponse(['success' => true]);
})();
} elseif ($action === 'me') {
(function () {
    $user = requireAuth();
    jsonResponse(['success' => true, 'user' => $user]);
})();
} elseif ($action === 'forgot') {
(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

    $body  = getBody();
    $email = strtolower(trim($body['email'] ?? ''));

    // Always return success to prevent email enumeration
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => true, 'message' => 'Pokud účet existuje, pošleme odkaz.']);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        $db->prepare('UPDATE users SET reset_token=?, reset_token_expires=? WHERE id=?')
           ->execute([$token, $expires, $user['id']]);

        $resetUrl = APP_URL . '/reset.php?token=' . urlencode($token);
        $body = emailTemplate('Reset hesla', "
            <p>Ahoj <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
            <p>Pro reset hesla klikni na odkaz níže:</p>
            <p><a class=\"btn\" href=\"$resetUrl\">Resetovat heslo</a></p>
            <p style=\"font-size:13px;color:#888\">Odkaz je platný 1 hodinu.</p>
        ");
        sendMail($email, 'Reset hesla – BeSix Board', $body);
    }

    jsonResponse(['success' => true, 'message' => 'Pokud účet existuje, pošleme odkaz.']);
})();
} elseif ($action === 'reset') {
(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

    $body     = getBody();
    $token    = trim($body['token']            ?? '');
    $pass     = $body['password']              ?? '';
    $passConf = $body['password_confirm']      ?? '';

    if (!$token)              jsonResponse(['error' => 'Chybí token'], 422);
    if (strlen($pass) < 8)   jsonResponse(['error' => 'Heslo musí mít alespoň 8 znaků'], 422);
    if ($pass !== $passConf) jsonResponse(['error' => 'Hesla se neshodují'], 422);

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()'
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) jsonResponse(['error' => 'Token je neplatný nebo vypršel'], 422);

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare('UPDATE users SET password_hash=?, reset_token=NULL, reset_token_expires=NULL WHERE id=?')
       ->execute([$hash, $user['id']]);

    jsonResponse(['success' => true, 'message' => 'Heslo bylo změněno. Můžeš se přihlásit.']);
})();
} else {
    jsonResponse(['error' => 'Neznámá akce'], 400);
}
