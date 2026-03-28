<?php
// Helper functions – committed to repo, included by all API files

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Nejsi přihlášen'], 401);
    }
    $db   = getDB();
    $stmt = $db->prepare('SELECT id, name, email, avatar_color FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        jsonResponse(['error' => 'Uživatel nenalezen'], 401);
    }
    return $user;
}

function requireProjectRole(int $projectId, string $minRole): array {
    $user  = requireAuth();
    $roles = ['viewer' => 0, 'member' => 1, 'admin' => 2, 'owner' => 3];

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT pm.role FROM project_members pm
         JOIN projects p ON p.id = pm.project_id
         WHERE pm.project_id = ? AND pm.user_id = ? AND p.is_active = 1'
    );
    $stmt->execute([$projectId, $user['id']]);
    $row = $stmt->fetch();

    if (!$row || ($roles[$row['role']] ?? -1) < ($roles[$minRole] ?? 99)) {
        jsonResponse(['error' => 'Nemáš oprávnění'], 403);
    }
    return array_merge($user, ['role' => $row['role']]);
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function sendMail(string $to, string $subject, string $htmlBody): bool {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $from     = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@besix.cz';

    if (file_exists($autoload)) {
        require_once $autoload;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.cesky-hosting.cz';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USER') ? SMTP_USER : $from;
            $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $mail->CharSet    = 'UTF-8';
            $mail->SMTPDebug  = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log('SMTP[' . $level . ']: ' . $str);
            };
            $mail->setFrom($from, 'BeSix Board');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('PHPMailer error to ' . $to . ': ' . $mail->ErrorInfo);
            return false;
        }
    }

    error_log('sendMail: vendor/autoload.php not found, falling back to mail()');
    // Fallback: php mail() (works only if server allows it)
    $headers  = "From: BeSix Board <$from>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    return (bool)@mail($to, $subject, $htmlBody, $headers);
}

function emailTemplate(string $title, string $content): string {
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>body{font-family:Arial,sans-serif;background:#1e2710;margin:0;padding:0}
.wrap{max-width:520px;margin:40px auto;background:#1a2a0e;border-radius:10px;overflow:hidden;border:1px solid rgba(200,165,60,0.2)}
.hdr{background:linear-gradient(135deg,#2a3d10 0%,#1a2a0e 100%);padding:28px 32px;border-bottom:1px solid rgba(200,165,60,0.15)}
.hdr h1{color:#d4a830;margin:0;font-size:20px;font-weight:700;letter-spacing:-0.3px}
.body{padding:32px}h2{color:rgba(255,255,255,0.88);margin:0 0 16px;font-size:16px}
p{color:rgba(255,255,255,0.6);line-height:1.65;margin:0 0 16px;font-size:14px}
.btn{display:inline-block;background:linear-gradient(180deg,#c9922a 0%,#a87420 100%);color:#fff;padding:13px 30px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px}
.foot{padding:20px 32px;background:rgba(0,0,0,0.2);font-size:12px;color:rgba(255,255,255,0.3);border-top:1px solid rgba(255,255,255,0.06)}</style>
</head><body><div class="wrap">
<div class="hdr"><h1>BeSix Board</h1></div>
<div class="body"><h2>$title</h2>$content</div>
<div class="foot">Pokud jsi tento email neočekával/a, ignoruj ho.</div>
</div></body></html>
HTML;
}
