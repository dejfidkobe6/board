<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrace – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:stretch}
  .brand{flex:1;background:linear-gradient(155deg,#3a4d1a 0%,#2a3912 50%,#1a2508 100%);display:flex;flex-direction:column;justify-content:space-between;padding:52px 56px;position:relative;overflow:hidden}
  .brand::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 20% 20%,rgba(107,128,60,0.12) 0%,transparent 60%),radial-gradient(ellipse 60% 80% at 80% 80%,rgba(60,85,30,0.18) 0%,transparent 55%);pointer-events:none}
  .brand-name{position:relative;z-index:1}.besix-logo{height:48px;width:auto;mix-blend-mode:screen;display:block}.besix-logo-sm{height:34px;width:auto;mix-blend-mode:screen;display:block;margin-bottom:32px}.besix-logo-nav{height:26px;width:auto;mix-blend-mode:screen;display:block}
  .brand-name span{color:rgba(130,165,75,0.9)}
  .brand-tagline{font-family:'Montserrat',sans-serif;font-size:36px;font-weight:800;color:rgba(255,255,255,0.92);line-height:1.1;letter-spacing:-1.2px;margin-bottom:16px;position:relative;z-index:1}
  .brand-tagline em{color:rgba(130,165,75,0.85);font-style:normal}
  .brand-desc{font-size:14px;color:rgba(162,188,130,0.5);line-height:1.65;max-width:320px;position:relative;z-index:1}
  .brand-dots{display:flex;gap:6px;margin-top:32px;position:relative;z-index:1}
  .brand-dots span{width:6px;height:6px;border-radius:50%;background:rgba(120,158,70,0.3)}
  .brand-dots span:first-child{background:rgba(120,158,70,0.75);width:22px;border-radius:3px}
  @media(max-width:768px){.brand{display:none}}
  .side{width:480px;flex-shrink:0;background:#16200a;display:flex;flex-direction:column;justify-content:center;padding:48px 48px;position:relative;overflow-y:auto}
  @media(max-width:768px){.side{width:100%;padding:32px 24px}}
  .side-logo{font-family:'Montserrat',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:32px;letter-spacing:-0.5px}
  .side-logo span{color:rgba(130,165,75,0.9)}
  h1{font-family:'Montserrat',sans-serif;font-size:26px;font-weight:800;color:#fff;margin-bottom:6px;letter-spacing:-0.5px}
  .sub{font-size:14px;color:rgba(162,188,130,0.5);margin-bottom:24px}
  .invite-banner{background:rgba(107,128,60,0.12);border:1px solid rgba(107,128,60,0.3);border-radius:9px;padding:12px 14px;margin-bottom:20px;font-size:13px;color:rgba(145,180,105,0.9)}
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
  .notice.success{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.3);color:rgba(145,180,105,0.9)}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .field{margin-bottom:14px}
  .field label{display:block;font-size:12px;font-weight:600;color:rgba(162,188,130,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .field input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;transition:border-color 0.15s;outline:none}
  .field input::placeholder{color:rgba(255,255,255,0.25)}
  .field input:focus{border-color:rgba(107,128,60,0.6);background:rgba(255,255,255,0.09)}
  .btn-main{width:100%;padding:13px;background:linear-gradient(180deg,#6b8040 0%,#506030 100%);border:none;border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;margin-top:8px;transition:opacity 0.15s,transform 0.1s;box-shadow:0 2px 12px rgba(60,85,30,0.35)}
  .btn-main:hover{opacity:0.92;transform:translateY(-1px)}
  .links{margin-top:20px;text-align:center;font-size:13px;color:rgba(162,188,130,0.45)}
  .links a{color:rgba(107,128,60,0.9);text-decoration:none;font-weight:600}
  .links a:hover{color:#aac850}
</style>
</head>
<body>
<div class="brand">
  <img src="/assets/besix-logo.png" class="besix-logo">
  <div>
    <div class="brand-tagline">Začni<br>organizovat<br><em>ještě dnes.</em></div>
    <div class="brand-desc">Vytvoř si účet zdarma a připoj se ke svému týmu na BeSix Board.</div>
    <div class="brand-dots"><span></span><span></span><span></span></div>
  </div>
</div>
<div class="side">
  <img src="/assets/besix-logo.png" class="besix-logo-sm">
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

<script>
  const params     = new URLSearchParams(location.search);
  const inviteToken = params.get('invite') || '';

  if (inviteToken) {
    // Load invite info
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
