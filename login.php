<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Přihlášení – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:stretch}
  .brand{flex:1;background:linear-gradient(155deg,#3a4d1a 0%,#2a3912 50%,#1a2508 100%);display:flex;flex-direction:column;justify-content:space-between;padding:52px 56px;position:relative;overflow:hidden}
  .brand::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 20% 20%,rgba(107,128,60,0.12) 0%,transparent 60%),radial-gradient(ellipse 60% 80% at 80% 80%,rgba(60,85,30,0.18) 0%,transparent 55%);pointer-events:none}
  .brand-name{position:relative;z-index:1}.besix-logo{height:48px;width:auto;display:block}.besix-logo-sm{height:34px;width:auto;display:block;margin-bottom:32px}.besix-logo-nav{height:26px;width:auto;display:block}
  .brand-name span{color:rgba(130,165,75,0.9)}
  .brand-tagline{font-family:'Montserrat',sans-serif;font-size:36px;font-weight:800;color:rgba(255,255,255,0.92);line-height:1.1;letter-spacing:-1.2px;margin-bottom:16px;position:relative;z-index:1}
  .brand-tagline em{color:rgba(130,165,75,0.85);font-style:normal}
  .brand-desc{font-size:14px;color:rgba(162,188,130,0.5);line-height:1.65;max-width:320px;position:relative;z-index:1}
  .brand-dots{display:flex;gap:6px;margin-top:32px;position:relative;z-index:1}
  .brand-dots span{width:6px;height:6px;border-radius:50%;background:rgba(120,158,70,0.3)}
  .brand-dots span:first-child{background:rgba(120,158,70,0.75);width:22px;border-radius:3px}
  @media(max-width:768px){.brand{display:none}}
  .side{width:480px;flex-shrink:0;background:#16200a;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;position:relative}
  @media(max-width:768px){.side{width:100%;padding:32px 24px}}
  .side-logo{font-family:'Montserrat',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:40px;letter-spacing:-0.5px}
  .side-logo span{color:rgba(130,165,75,0.9)}
  h1{font-family:'Montserrat',sans-serif;font-size:26px;font-weight:800;color:#fff;margin-bottom:6px;letter-spacing:-0.5px}
  .sub{font-size:14px;color:rgba(162,188,130,0.5);margin-bottom:28px}
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
  .notice.success{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.3);color:rgba(145,180,105,0.9)}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;font-weight:600;color:rgba(162,188,130,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .field input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;transition:border-color 0.15s,background 0.15s;outline:none}
  .field input::placeholder{color:rgba(255,255,255,0.25)}
  .field input:focus{border-color:rgba(107,128,60,0.6);background:rgba(255,255,255,0.09)}
  .btn-main{width:100%;padding:13px;background:linear-gradient(180deg,#6b8040 0%,#506030 100%);border:none;border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;margin-top:8px;transition:opacity 0.15s,transform 0.1s;box-shadow:0 2px 12px rgba(60,85,30,0.35)}
  .btn-main:hover{opacity:0.92;transform:translateY(-1px)}
  .btn-main:active{transform:translateY(0)}
  .links{margin-top:22px;text-align:center;font-size:13px;color:rgba(162,188,130,0.45)}
  .links a{color:rgba(107,128,60,0.9);text-decoration:none;font-weight:600}
  .links a:hover{color:#aac850}
  #spinner{display:none;width:18px;height:18px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;margin:0 auto}
  @keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="brand">
  <img src="/assets/besix-logo.png" class="besix-logo">
  <div>
    <div class="brand-tagline">Organizuj<br>svou práci<br><em>efektivně.</em></div>
    <div class="brand-desc">Přehledný kanban board pro týmy i jednotlivce. Sleduj úkoly, projekty a pokrok.</div>
    <div class="brand-dots"><span></span><span></span><span></span></div>
  </div>
</div>
<div class="side">
  <img src="/assets/besix-logo.png" class="besix-logo-sm">
  <h1>Přihlásit se</h1>
  <div class="sub">Pokračuj do svého účtu</div>

  <div id="msg"></div>

  <div class="field">
    <label>E-mail</label>
    <input type="email" id="email" placeholder="jan@firma.cz" autocomplete="email">
  </div>
  <div class="field">
    <label>Heslo</label>
    <input type="password" id="pass" placeholder="••••••••" autocomplete="current-password">
  </div>
  <button class="btn-main" id="btnLogin" onclick="doLogin()">Přihlásit se</button>

  <div class="links">
    <a href="forgot.php">Zapomenuté heslo?</a><br><br>
    Nemáš účet? <a href="register.php">Zaregistruj se</a>
  </div>
</div>

<script>
  const params = new URLSearchParams(location.search);
  if (params.get('verified') === '1') {
    showMsg('Email ověřen, můžeš se přihlásit.', 'success');
  }
  if (params.get('error') === 'invalid_token') {
    showMsg('Neplatný nebo expirovaný odkaz.', 'error');
  }
  if (params.get('error') === 'invite_expired') {
    showMsg('Pozvánka vypršela.', 'error');
  }

  function showMsg(text, type = 'error') {
    const el = document.getElementById('msg');
    el.innerHTML = `<div class="notice ${type}">${text}</div>`;
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });

  async function doLogin() {
    const email = document.getElementById('email').value.trim();
    const pass  = document.getElementById('pass').value;
    if (!email || !pass) { showMsg('Vyplň email a heslo.'); return; }

    const btn = document.getElementById('btnLogin');
    btn.innerHTML = '<div id="spinner" style="display:block"></div>';
    btn.disabled = true;

    try {
      const res = await fetch('/api/auth.php?action=login', {
        method: 'POST', credentials: 'include',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ email, password: pass })
      });
      const data = await res.json();
      if (data.success) {
        const redirect = params.get('redirect') || '/dashboard.php';
        location.href = redirect;
      } else {
        showMsg(data.error || 'Chyba přihlášení.');
        btn.textContent = 'Přihlásit se';
        btn.disabled = false;
      }
    } catch {
      showMsg('Chyba připojení.');
      btn.textContent = 'Přihlásit se';
      btn.disabled = false;
    }
  }
</script>
</body>
</html>
