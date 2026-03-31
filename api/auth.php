<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

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

// sendMail() and emailTemplate() are defined in functions.php

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
    $nameEsc = htmlspecialchars($name);
    $body = emailTemplate('Ověření emailu',
        emailP("Ahoj <strong style='color:#e8e8e8'>$nameEsc</strong>,") .
        emailP('Klikni na tlačítko níže pro ověření tvého účtu:') .
        emailBtn($verifyUrl, 'Ověřit email') .
        emailP("<span style='font-size:12px;color:#666'>Odkaz je platný 7 dní.</span>")
    );
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

    if (!$user) {
        jsonResponse(['error' => 'Nesprávný email nebo heslo'], 401);
    }
    if ($user['password_hash'] === '!google') {
        jsonResponse(['error' => 'Tento účet používá přihlášení přes Google. Použij tlačítko "Přihlásit se přes Google".'], 401);
    }
    if (!password_verify($pass, $user['password_hash'])) {
        jsonResponse(['error' => 'Nesprávný email nebo heslo'], 401);
    }
    // Email verification temporarily disabled
    // if (!$user['is_verified']) {
    //     jsonResponse(['error' => 'Účet není ověřen. Zkontroluj email.', 'unverified' => true], 403);
    // }

    clearRateLimit('login_' . $email);

    $_SESSION['user_id']      = (int)$user['id'];
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
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 86400, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
    jsonResponse(['success' => true]);
})();
} elseif ($action === 'me') {
(function () {
    $user = requireAuth();
    jsonResponse(['success' => true, 'user' => $user]);
})();
} elseif ($action === 'session_debug') {
(function () {
    // Test DB write
    $dbOk = false; $dbErr = '';
    try {
        $db = getDB();
        $db->query('SELECT 1 FROM php_sessions LIMIT 1');
        $dbOk = true;
    } catch (\Throwable $e) { $dbErr = $e->getMessage(); }
    jsonResponse([
        'session_id'   => session_id(),
        'session_status' => session_status(),
        'user_id'      => $_SESSION['user_id'] ?? null,
        'session_keys' => array_keys($_SESSION),
        'cookie_sent'  => isset($_COOKIE[session_name()]),
        'db_sessions_ok' => $dbOk,
        'db_error'     => $dbErr,
        'open_basedir' => ini_get('open_basedir'),
    ]);
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
        $userNameEsc = htmlspecialchars($user['name']);
        $body = emailTemplate('Reset hesla',
            emailP("Ahoj <strong style='color:#e8e8e8'>$userNameEsc</strong>,") .
            emailP('Pro reset hesla klikni na odkaz níže:') .
            emailBtn($resetUrl, 'Resetovat heslo') .
            emailP("<span style='font-size:12px;color:#666'>Odkaz je platný 1 hodinu.</span>")
        );
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
} elseif ($action === 'google_redirect') {
(function () {
    if (!defined('GOOGLE_CLIENT_ID') || !GOOGLE_CLIENT_ID) {
        header('Location: /login.php?error=google_not_configured'); exit;
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => APP_URL . '/api/auth.php?action=google_callback',
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
})();
} elseif ($action === 'google_callback') {
(function () {
    $code  = trim($_GET['code']  ?? '');
    $state = trim($_GET['state'] ?? '');

    if (!empty($_GET['error']) || !$code) {
        header('Location: /login.php?error=google_denied'); exit;
    }
    if (!$state || $state !== ($_SESSION['oauth_state'] ?? '')) {
        header('Location: /login.php?error=google_state'); exit;
    }
    unset($_SESSION['oauth_state']);

    $redirectUri = APP_URL . '/api/auth.php?action=google_callback';

    // ── Exchange code → access token ────────────────────────────────────────
    $postData = http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]);
    $tokenData = googleOAuthPost('https://oauth2.googleapis.com/token', $postData);
    $accessToken = $tokenData['access_token'] ?? '';
    if (!$accessToken) { header('Location: /login.php?error=google_token'); exit; }

    // ── Fetch user profile ──────────────────────────────────────────────────
    $gUser = googleOAuthGet('https://www.googleapis.com/oauth2/v3/userinfo', $accessToken);
    $googleId = $gUser['sub']   ?? '';
    $email    = strtolower(trim($gUser['email'] ?? ''));
    $name     = trim($gUser['name'] ?? '');
    if (!$googleId || !$email) { header('Location: /login.php?error=google_userinfo'); exit; }

    // ── Find or create user ─────────────────────────────────────────────────
    $db = getDB();

    $stmt = $db->prepare('SELECT id, name, avatar_color FROM users WHERE google_id = ?');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $db->prepare('SELECT id, name, avatar_color FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            // Link Google ID to existing account
            $db->prepare('UPDATE users SET google_id=?, is_verified=1 WHERE id=?')
               ->execute([$googleId, $user['id']]);
        }
    }

    if (!$user) {
        // Create new Google account
        $colors = ['#4A5340','#3a4d1a','#1e3a2e','#1e2d3a','#2a1e3a','#3a1e2a','#2d2010'];
        $color  = $colors[array_rand($colors)];
        $db->prepare(
            'INSERT INTO users (name, email, password_hash, avatar_color, google_id, is_verified)
             VALUES (?,?,?,?,?,1)'
        )->execute([$name ?: $email, $email, '!google', $color, $googleId]);
        $user = [
            'id'           => (int)$db->lastInsertId(),
            'name'         => $name ?: $email,
            'avatar_color' => $color,
        ];
    }

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['avatar_color'] = $user['avatar_color'];
    session_write_close();

    header('Location: /dashboard.php');
    exit;
})();
} else {
    jsonResponse(['error' => 'Neznámá akce'], 400);
}

// ── Google OAuth HTTP helpers ──────────────────────────────────────────────
function googleOAuthPost(string $url, string $postData): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res = curl_exec($ch); curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => $postData,
            'ignore_errors' => true,
            'timeout'       => 10,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
    }
    return $res ? (json_decode($res, true) ?? []) : [];
}

function googleOAuthGet(string $url, string $token): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res = curl_exec($ch); curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'header'        => "Authorization: Bearer $token\r\n",
            'ignore_errors' => true,
            'timeout'       => 10,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
    }
    return $res ? (json_decode($res, true) ?? []) : [];
}
