<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Přihlášení – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .wrap{width:100%;max-width:420px;display:flex;flex-direction:column;align-items:center;gap:24px}
  .logo-wrap{display:flex;flex-direction:column;align-items:center;gap:10px}
  .besix-logo{width:160px;height:auto;display:block}
  .app-title{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px}
  .app-title span{color:rgba(200,160,50,0.9)}
  .card{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:36px}
  h1{font-size:20px;font-weight:800;color:#fff;margin-bottom:4px;letter-spacing:-0.4px}
  .sub{font-size:13px;color:rgba(210,175,80,0.5);margin-bottom:24px}
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px}
  .notice.success{background:rgba(160,120,20,0.15);border:1px solid rgba(160,120,20,0.3);color:rgba(210,185,70,0.9)}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .field{margin-bottom:16px}
  .field label{display:block;font-size:12px;font-weight:600;color:rgba(210,175,80,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .field input{width:100%;padding:11px 14px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;transition:border-color 0.15s,background 0.15s;outline:none}
  .field input::placeholder{color:rgba(255,255,255,0.25)}
  .field input:focus{border-color:rgba(160,120,20,0.6);background:rgba(255,255,255,0.09)}
  .btn-main{width:100%;padding:13px;background:linear-gradient(180deg,#c9922a 0%,#a87420 100%);border:none;border-radius:9px;color:#fff;font-size:15px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;margin-top:8px;transition:opacity 0.15s,transform 0.1s;box-shadow:0 2px 12px rgba(160,100,10,0.35)}
  .btn-main:hover{opacity:0.92;transform:translateY(-1px)}
  .btn-main:active{transform:translateY(0)}
  .divider{display:flex;align-items:center;gap:12px;margin:18px 0 0}
  .divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,0.08)}
  .divider span{font-size:11px;color:rgba(200,165,60,0.4);text-transform:uppercase;letter-spacing:0.1em}
  .btn-google{width:100%;padding:12px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.14);border-radius:9px;color:rgba(255,255,255,0.8);font-size:14px;font-family:'Montserrat',sans-serif;font-weight:600;cursor:pointer;margin-top:10px;display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;transition:all 0.15s}
  .btn-google:hover{background:rgba(255,255,255,0.11);border-color:rgba(255,255,255,0.24)}
  .links{margin-top:20px;text-align:center;font-size:13px;color:rgba(210,175,80,0.45)}
  .links a{color:rgba(200,155,40,0.95);text-decoration:none;font-weight:600}
  .links a:hover{color:#d4a830}
  #spinner{display:none;width:18px;height:18px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.7s linear infinite;margin:0 auto}
  @keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo-wrap">
    <img src="/assets/besix-logo.png" class="besix-logo" alt="BeSix">
    <div class="app-title">BeSix <span>Board</span></div>
  </div>
  <div class="card">
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
    <div class="field" style="flex-direction:row;align-items:center;gap:10px;margin-bottom:8px;display:flex">
      <input type="checkbox" id="remember" style="width:auto;height:16px;width:16px;accent-color:#c9922a;cursor:pointer;flex-shrink:0">
      <label for="remember" style="font-size:13px;color:rgba(210,175,80,0.7);cursor:pointer;font-weight:500;text-transform:none;letter-spacing:0;margin:0">Zapamatovat si mě (30 dní)</label>
    </div>
    <button class="btn-main" id="btnLogin" onclick="doLogin()">Přihlásit se</button>
    <div class="divider"><span>nebo</span></div>
    <a href="/api/auth.php?action=google_redirect" class="btn-google">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
      Přihlásit se přes Google
    </a>

    <div class="links">
      <a href="forgot.php">Zapomenuté heslo?</a><br><br>
      Nemáš účet? <a href="register.php">Zaregistruj se</a>
    </div>
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
  if (params.get('error') === 'google_not_configured') {
    showMsg('Google přihlášení není nakonfigurováno. Kontaktuj správce aplikace.', 'error');
  }
  if (params.get('error') === 'google_oauth_failed') {
    showMsg('Google přihlášení selhalo. Zkus to znovu.', 'error');
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
        body: JSON.stringify({ email, password: pass, remember_me: document.getElementById('remember').checked })
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
