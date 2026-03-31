<?php
// Helper functions – committed to repo, included by all API files

// ── DB-backed session handler (works on any shared hosting) ─────────────────
(function () {
    if (session_status() !== PHP_SESSION_NONE) return; // already started (e.g. session.auto_start)

    $handler = new class implements SessionHandlerInterface {
        private ?PDO $db = null;
        private function db(): PDO {
            if (!$this->db) $this->db = getDB();
            return $this->db;
        }
        public function open(string $path, string $name): bool {
            // Auto-create table if not exists
            try {
                $this->db()->exec(
                    'CREATE TABLE IF NOT EXISTS php_sessions (
                        id VARCHAR(128) NOT NULL PRIMARY KEY,
                        data MEDIUMTEXT NOT NULL,
                        updated_at INT UNSIGNED NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                );
            } catch (\Throwable $e) {}
            return true;
        }
        public function close(): bool { return true; }
        public function read(string $id): string|false {
            try {
                $maxlife = max((int)ini_get('session.gc_maxlifetime'), 86400);
                $s = $this->db()->prepare('SELECT data FROM php_sessions WHERE id=? AND updated_at > ?');
                $s->execute([$id, time() - $maxlife]);
                $row = $s->fetch(PDO::FETCH_ASSOC);
                return $row ? (string)$row['data'] : '';
            } catch (\Throwable $e) { return ''; }
        }
        public function write(string $id, string $data): bool {
            try {
                $this->db()->prepare(
                    'INSERT INTO php_sessions (id, data, updated_at) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE data=VALUES(data), updated_at=VALUES(updated_at)'
                )->execute([$id, $data, time()]);
                return true;
            } catch (\Throwable $e) { return false; }
        }
        public function destroy(string $id): bool {
            try { $this->db()->prepare('DELETE FROM php_sessions WHERE id=?')->execute([$id]); return true; }
            catch (\Throwable $e) { return false; }
        }
        public function gc(int $max): int|false {
            try {
                $s = $this->db()->prepare('DELETE FROM php_sessions WHERE updated_at < ?');
                $s->execute([time() - $max]);
                return $s->rowCount();
            } catch (\Throwable $e) { return false; }
        }
    };
    session_set_save_handler($handler, true);
    session_start();
})();

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
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@besix.cz';

    // Use Brevo API if key is configured (reliable delivery, no SMTP needed)
    if (defined('BREVO_API_KEY') && BREVO_API_KEY !== '') {
        $payload = json_encode([
            'sender'     => ['name' => 'BeSix Board', 'email' => $from],
            'to'         => [['email' => $to]],
            'subject'    => $subject,
            'htmlContent'=> $htmlBody,
        ]);
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'Content-Type: application/json',
                'api-key: ' . BREVO_API_KEY,
            ]),
            'content' => $payload,
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents('https://api.brevo.com/v3/smtp/email', false, $ctx);
        if ($result !== false) {
            $json = json_decode($result, true);
            if (isset($json['messageId'])) return true;
        }
        error_log('Brevo API error: ' . ($result ?: 'no response'));
        return false;
    }

    // Fallback: PHPMailer with local MTA
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isMail();
            $mail->CharSet = 'UTF-8';
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

    $headers  = "From: BeSix Board <$from>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\nMIME-Version: 1.0\r\n";
    return (bool)@mail($to, $subject, $htmlBody, $headers);
}

function emailTemplate(string $title, string $content): string {
    // Replace .btn links with Outlook-compatible table buttons
    $content = preg_replace_callback(
        '/<a class="btn" href="([^"]+)">([^<]+)<\/a>/',
        function($m) {
            $href  = htmlspecialchars($m[1], ENT_QUOTES);
            $label = htmlspecialchars_decode($m[2]);
            return '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="' . $href . '" style="height:44px;v-text-anchor:middle;width:220px;" arcsize="18%" stroke="f" fillcolor="#c9922a"><w:anchorlock/><center><font color="#ffffff" face="Arial" size="3"><b>' . $label . '</b></font></center></v:roundrect><![endif]-->'
                 . '<!--[if !mso]><!--><a href="' . $href . '" style="background-color:#c9922a;border-radius:8px;color:#ffffff !important;display:inline-block;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;padding:13px 30px;text-decoration:none;mso-hide:all;"><span style="color:#ffffff !important;">'. $label .'</span></a><!--<![endif]-->';
        },
        $content
    );

    return <<<HTML
<!DOCTYPE html>
<html lang="cs" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
<style>body,table,td{font-family:Arial,sans-serif}a{color:#c9922a}.btn-link{color:#ffffff !important;text-decoration:none !important}</style>
</head>
<body style="margin:0;padding:0;background-color:#1e2710;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#1e2710;">
  <tr><td align="center" style="padding:32px 16px;">
    <table width="520" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;width:100%;background-color:#1a2a0e;border-radius:10px;border:1px solid #3a4a20;">
      <!-- Header -->
      <tr><td style="background-color:#2a3d10;padding:20px 32px;border-radius:10px 10px 0 0;border-bottom:1px solid #3a4a20;">
        <img src="https://board.besix.cz/assets/besix-logo.png" alt="BeSix" width="48" height="48" style="display:block;margin-bottom:10px;border:0;">
        <span style="color:#d4a830;font-size:20px;font-weight:700;font-family:Arial,sans-serif;">BeSix Board</span>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:32px;">
        <h2 style="color:#e8e8e8;margin:0 0 20px;font-size:17px;font-family:Arial,sans-serif;">$title</h2>
        $content
      </td></tr>
      <!-- Footer -->
      <tr><td style="padding:16px 32px;background-color:#111a08;border-radius:0 0 10px 10px;border-top:1px solid #2a3a10;">
        <span style="color:#555f44;font-size:12px;font-family:Arial,sans-serif;">Pokud jsi tento email neočekával/a, ignoruj ho.</span>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

function emailP(string $text): string {
    return '<p style="color:#b8c8a0;font-size:14px;line-height:1.65;margin:0 0 16px;font-family:Arial,sans-serif;">' . $text . '</p>';
}

function emailBtn(string $href, string $label): string {
    $href = htmlspecialchars($href, ENT_QUOTES);
    return '<p style="margin:20px 0;"><!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="' . $href . '" style="height:44px;v-text-anchor:middle;width:220px;" arcsize="18%" stroke="f" fillcolor="#c9922a"><w:anchorlock/><center><font color="#ffffff" face="Arial" size="3"><b>' . $label . '</b></font></center></v:roundrect><![endif]--><!--[if !mso]><!--><a href="' . $href . '" style="background-color:#c9922a;border-radius:8px;color:#ffffff !important;display:inline-block;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;padding:13px 30px;text-decoration:none;"><span style="color:#ffffff !important;">'. $label .'</span></a><!--<![endif]--></p>';
}
