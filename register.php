<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrace – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .wrap{width:100%;max-width:420px;display:flex;flex-direction:column;align-items:center;gap:24px}
  .logo-wrap{display:flex;flex-direction:column;align-items:center;gap:14px}
  .besix-logo{height:56px;width:auto;display:block}
  .app-title{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px}
  .app-title span{color:rgba(200,160,50,0.9)}
  .card{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:36px}
  h1{font-size:20px;font-weight:800;color:#fff;margin-bottom:4px;letter-spacing:-0.4px}
  .sub{font-size:13px;color:rgba(210,175,80,0.5);margin-bottom:24px}
  .invite-banner{background:rgba(107,128,60,0.12);border:1px solid rgba(107,128,60,0.3);border-radius:9px;padding:12px 14px;margin-bottom:20px;font-size:13px;color:rgba(210,185,70,0.9)}
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
  .notice.success{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.3);color:rgba(210,185,70,0.9)}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;font-weight:600;color:rgba(210,175,80,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .field input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;transition:border-color 0.15s;outline:none}
  .field input::placeholder{color:rgba(255,255,255,0.25)}
  .field input:focus{border-color:rgba(107,128,60,0.6);background:rgba(255,255,255,0.09)}
  .btn-main{width:100%;padding:13px;background:linear-gradient(180deg,#c9922a 0%,#a87420 100%);border:none;border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;margin-top:8px;transition:opacity 0.15s,transform 0.1s;box-shadow:0 2px 12px rgba(160,100,10,0.35)}
  .btn-main:hover{opacity:0.92;transform:translateY(-1px)}
  .links{margin-top:20px;text-align:center;font-size:13px;color:rgba(210,175,80,0.45)}
  .links a{color:rgba(200,155,40,0.95);text-decoration:none;font-weight:600}
  .links a:hover{color:#d4a830}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo-wrap">
    <img src="/assets/besix-logo.png" class="besix-logo" alt="BeSix">
    <div class="app-title">BeSix <span>Board</span></div>
  </div>
  <div class="card">
    <h1>Vytvořit účet</h1>
    <div class="sub">Registrace je zdarma</div>

    <div id="inviteBanner" style="display:none" class="invite-banner"></div>
    <div id="msg"></div>

    <div class="field">
      <label>Jméno</label>
      <input type="text" id="name" placeholder="Jan Novák" autocomplete="name">
    </div>
    <div class="field">
      <label>E-mail</label>
      <input type="email" id="email" placeholder="jan@firma.cz" autocomplete="email">
    </div>
    <div class="field">
      <label>Heslo</label>
      <input type="password" id="pass" placeholder="Min. 8 znaků" autocomplete="new-password">
    </div>
    <div class="field">
      <label>Potvrzení hesla</label>
      <input type="password" id="passConf" placeholder="••••••••" autocomplete="new-password">
    </div>
    <button class="btn-main" id="btnReg" onclick="doRegister()">Zaregistrovat se</button>

    <div class="links">
      Máš účet? <a href="login.php">Přihlásit se</a>
    </div>
  </div>
</div>

<script>
  const params      = new URLSearchParams(location.search);
  const inviteToken = params.get('invite') || '';

  if (inviteToken) {
    fetch('/invite.php?token=' + encodeURIComponent(inviteToken) + '&json=1')
      .then(r => r.json())
      .then(d => {
        if (d.project_name) {
          const b = document.getElementById('inviteBanner');
          b.style.display = 'block';
          b.textContent = '🔗 Přijmout pozvánku do projektu: ' + d.project_name;
        }
      }).catch(() => {});
  }

  function showMsg(text, type = 'error') {
    document.getElementById('msg').innerHTML = `<div class="notice ${type}">${text}</div>`;
  }

  async function doRegister() {
    const name     = document.getElementById('name').value.trim();
    const email    = document.getElementById('email').value.trim();
    const pass     = document.getElementById('pass').value;
    const passConf = document.getElementById('passConf').value;

    if (!name || !email || !pass || !passConf) { showMsg('Vyplň všechna pole.'); return; }

    const btn = document.getElementById('btnReg');
    btn.textContent = 'Registruji…';
    btn.disabled = true;

    try {
      const res = await fetch('/api/auth.php?action=register', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ name, email, password: pass, password_confirm: passConf, invite_token: inviteToken })
      });
      const data = await res.json();
      if (data.success) {
        showMsg('Registrace proběhla! Zkontroluj svůj email a klikni na ověřovací odkaz.', 'success');
        btn.textContent = 'Hotovo';
      } else {
        showMsg(data.error || 'Chyba registrace.');
        btn.textContent = 'Zaregistrovat se';
        btn.disabled = false;
      }
    } catch {
      showMsg('Chyba připojení.');
      btn.textContent = 'Zaregistrovat se';
      btn.disabled = false;
    }
  }
</script>
</body>
</html>
