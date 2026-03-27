<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nové heslo – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{width:100%;max-width:420px;background:#16200a;border-radius:16px;padding:44px 40px;border:1px solid rgba(255,255,255,0.07)}
  .logo{margin-bottom:32px}.besix-logo-sm{height:34px;width:auto;display:block;margin-bottom:32px}
  .logo span{color:rgba(130,165,75,0.9)}
  h1{font-family:'Montserrat',sans-serif;font-size:24px;font-weight:800;color:#fff;margin-bottom:6px}
  .sub{font-size:14px;color:rgba(162,188,130,0.5);margin-bottom:28px}
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
  .notice.success{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.3);color:rgba(145,180,105,0.9)}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;font-weight:600;color:rgba(162,188,130,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .field input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color 0.15s}
  .field input::placeholder{color:rgba(255,255,255,0.25)}
  .field input:focus{border-color:rgba(107,128,60,0.6)}
  .btn-main{width:100%;padding:13px;background:linear-gradient(180deg,#6b8040 0%,#506030 100%);border:none;border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;margin-top:8px;box-shadow:0 2px 12px rgba(60,85,30,0.35);transition:opacity 0.15s}
  .btn-main:hover{opacity:0.92}
  .back{margin-top:20px;text-align:center;font-size:13px;color:rgba(162,188,130,0.45)}
  .back a{color:rgba(107,128,60,0.9);text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="card">
  <img src="/assets/besix-logo.png" class="besix-logo-sm">
  <h1>Nové heslo</h1>
  <div class="sub">Zadej nové heslo pro svůj účet.</div>

  <div id="msg"></div>

  <div id="form">
    <div class="field">
      <label>Nové heslo</label>
      <input type="password" id="pass" placeholder="Min. 8 znaků" autocomplete="new-password">
    </div>
    <div class="field">
      <label>Potvrzení hesla</label>
      <input type="password" id="passConf" placeholder="••••••••" autocomplete="new-password">
    </div>
    <button class="btn-main" id="btnReset" onclick="doReset()">Uložit nové heslo</button>
  </div>

  <div class="back"><a href="login.php">← Zpět na přihlášení</a></div>
</div>

<script>
  const token = new URLSearchParams(location.search).get('token') || '';

  if (!token) {
    document.getElementById('msg').innerHTML = '<div class="notice error">Chybí token. Použij odkaz z emailu.</div>';
    document.getElementById('form').style.display = 'none';
  }

  function showMsg(text, type = 'error') {
    document.getElementById('msg').innerHTML = `<div class="notice ${type}">${text}</div>`;
  }

  async function doReset() {
    const pass     = document.getElementById('pass').value;
    const passConf = document.getElementById('passConf').value;
    if (!pass || !passConf) { showMsg('Vyplň obě pole.'); return; }

    const btn = document.getElementById('btnReset');
    btn.textContent = 'Ukládám…';
    btn.disabled = true;

    try {
      const res = await fetch('/api/auth.php?action=reset', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ token, password: pass, password_confirm: passConf })
      });
      const data = await res.json();
      if (data.success) {
        showMsg('Heslo změněno. Přesměruji na přihlášení…', 'success');
        document.getElementById('form').style.display = 'none';
        setTimeout(() => location.href = '/login.php', 2000);
      } else {
        showMsg(data.error || 'Chyba.');
        btn.textContent = 'Uložit nové heslo';
        btn.disabled = false;
      }
    } catch {
      showMsg('Chyba připojení.');
      btn.textContent = 'Uložit nové heslo';
      btn.disabled = false;
    }
  }
</script>
</body>
</html>
