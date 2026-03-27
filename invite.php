<?php
// invite.php – Landing page for invitation links
// Also serves JSON data for register.php (?json=1)

// Start session for auth check
session_name('BESIX_SESS');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/api/config.php';

$token   = trim($_GET['token'] ?? '');
$jsonMode = isset($_GET['json']);

$inv     = null;
$project = null;
$error   = null;

if ($token) {
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT i.*, p.name AS project_name, p.id AS project_id,
                    u.name AS inviter_name, a.app_name
             FROM invitations i
             JOIN projects p ON p.id = i.project_id
             JOIN users u ON u.id = i.invited_by
             JOIN apps a ON a.id = p.app_id
             WHERE i.token = ? AND i.status = "pending" AND i.expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $inv = $stmt->fetch();
        if (!$inv) $error = 'Pozvánka je neplatná nebo vypršela.';
    } catch (Exception $e) {
        $error = 'Chyba serveru.';
    }
} else {
    $error = 'Chybí token.';
}

// JSON mode for register.php AJAX
if ($jsonMode) {
    header('Content-Type: application/json');
    if ($inv) echo json_encode(['project_name' => $inv['project_name']]);
    else      echo json_encode(['error' => $error]);
    exit;
}

// Handle logged-in user → redirect to accept
if ($inv && !empty($_SESSION['user_id'])) {
    header('Location: /api/invitations.php?action=accept&token=' . urlencode($token));
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pozvánka – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{width:100%;max-width:460px;background:#16200a;border-radius:20px;padding:48px 44px;border:1px solid rgba(255,255,255,0.07);text-align:center}
  .logo{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;margin-bottom:40px}
  .logo span{color:rgba(170,205,80,0.9)}
  .invite-icon{width:72px;height:72px;background:rgba(143,165,69,0.15);border:1px solid rgba(143,165,69,0.3);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:32px}
  h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:8px}
  .sub{font-size:14px;color:rgba(190,215,130,0.55);line-height:1.6;margin-bottom:8px}
  .project-name{font-size:20px;font-weight:700;color:rgba(170,205,80,0.9);font-family:'Syne',sans-serif;margin-bottom:6px}
  .inviter{font-size:13px;color:rgba(190,215,130,0.45);margin-bottom:32px}
  .role-badge{display:inline-block;background:rgba(143,165,69,0.15);border:1px solid rgba(143,165,69,0.3);color:rgba(170,205,80,0.9);font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;margin-bottom:32px;text-transform:capitalize}
  .btns{display:flex;flex-direction:column;gap:10px}
  .btn{display:block;padding:13px;border-radius:9px;font-size:15px;font-family:'Syne',sans-serif;font-weight:700;text-decoration:none;transition:opacity 0.15s}
  .btn-primary{background:linear-gradient(180deg,#8fa545 0%,#6d8030 100%);color:#fff;box-shadow:0 2px 12px rgba(80,110,20,0.35)}
  .btn-secondary{background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.8);border:1px solid rgba(255,255,255,0.1)}
  .btn:hover{opacity:0.88}
  .error{color:#ff6b60;font-size:15px;margin-top:16px}
  .app-label{font-size:12px;color:rgba(190,215,130,0.35);margin-top:24px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">BESIX<span>BOARD</span></div>

  <?php if ($error): ?>
    <div class="invite-icon">✉</div>
    <h1>Pozvánka nenalezena</h1>
    <p class="error"><?= htmlspecialchars($error) ?></p>
    <div style="margin-top:28px">
      <a href="/login.php" class="btn btn-primary">Přihlásit se</a>
    </div>
  <?php else: ?>
    <div class="invite-icon">🔗</div>
    <h1>Byl/a jsi pozván/a</h1>
    <div class="sub">do projektu</div>
    <div class="project-name"><?= htmlspecialchars($inv['project_name']) ?></div>
    <div class="inviter">Pozval/a tě <?= htmlspecialchars($inv['inviter_name']) ?></div>
    <div class="role-badge"><?= htmlspecialchars($inv['role']) ?></div>
    <div class="btns">
      <a href="/login.php?redirect=<?= urlencode('/api/invitations.php?action=accept&token=' . $token) ?>" class="btn btn-primary">Přihlásit se a přijmout</a>
      <a href="/register.php?invite=<?= urlencode($token) ?>" class="btn btn-secondary">Zaregistrovat se</a>
    </div>
    <div class="app-label"><?= htmlspecialchars($inv['app_name']) ?></div>
  <?php endif; ?>
</div>
</body>
</html>
