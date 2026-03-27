<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Zapomenuté heslo – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{width:100%;max-width:420px;background:#16200a;border-radius:16px;padding:44px 40px;border:1px solid rgba(255,255,255,0.07)}
  .logo{margin-bottom:32px}.besix-logo-sm{height:34px;width:auto;display:block;margin-bottom:32px}
  .logo span{color:rgba(200,160,50,0.9)}
  h1{font-family:'Montserrat',sans-serif;font-size:24px;font-weight:800;color:#fff;margin-bottom:6px}
  .sub{font-size:14px;color:rgba(210,175,80,0.5);margin-bottom:28px;line-height:1.5}
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
  .notice.success{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.3);color:rgba(210,185,70,0.9)}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;font-weight:600;color:rgba(210,175,80,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .field input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color 0.15s}
  .field input::placeholder{color:rgba(255,255,255,0.25)}
  .field input:focus{border-color:rgba(107,128,60,0.6)}
  .btn-main{width:100%;padding:13px;background:linear-gradient(180deg,#6b8040 0%,#506030 100%);border:none;border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;margin-top:8px;box-shadow:0 2px 12px rgba(60,85,30,0.35);transition:opacity 0.15s}
  .btn-main:hover{opacity:0.92}
  .back{margin-top:20px;text-align:center;font-size:13px;color:rgba(210,175,80,0.45)}
  .back a{color:rgba(107,128,60,0.9);text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="card">
  <img src="/assets/besix-logo.png" class="besix-logo-sm">
  <h1>Zapomenuté heslo</h1>
  <div class="sub">Zadej svůj email a pošleme ti odkaz pro reset hesla.</div>

  <div id="msg"></div>

  <div class="field">
    <label>E-mail</label>
    <input type="email" id="email" placeholder="jan@firma.cz" autocomplete="email">
  </div>
  <button class="btn-main" id="btnSend" onclick="doForgot()">Odeslat odkaz</button>

  <div class="back"><a href="login.php">← Zpět na přihlášení</a></div>
</div>

<script>
  function showMsg(text, type = 'error') {
    document.getElementById('msg').innerHTML = `<div class="notice ${type}">${text}</div>`;
  }

  document.addEventListener('keydown', e => { if (e.key === 'Enter') doForgot(); });

  async function doForgot() {
    const email = document.getElementById('email').value.trim();
    if (!email) { showMsg('Zadej email.'); return; }

    const btn = document.getElementById('btnSend');
    btn.textContent = 'Odesílám…';
    btn.disabled = true;

    try {
      const res = await fetch('/api/auth.php?action=forgot', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ email })
      });
      const data = await res.json();
      showMsg(data.message || 'Odkaz odeslán.', 'success');
      btn.textContent = 'Odesláno';
    } catch {
      showMsg('Chyba připojení.');
      btn.textContent = 'Odeslat odkaz';
      btn.disabled = false;
    }
  }
</script>
</body>
</html>
