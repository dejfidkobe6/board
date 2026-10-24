// BeSix Board — landing overlay shown when the user opens the site in a
// regular mobile browser tab while the PWA is (probably) installed.
// Plans-style: brand icon + "Otevřít aplikaci" + "Pokračovat v prohlížeči".
//
// Dismissal is persistent (localStorage, 30 days). Once the user picks
// "Pokračovat v prohlížeči" we stay out of their way — no re-show, no
// further nudges in this device.
//
// Renders nothing if:
//   - The page is already running as a PWA (display-mode: standalone)
//   - The viewport doesn't match a phone
//   - The user dismissed within the last 30 days
(function () {
  var DISMISS_KEY  = 'pwaLandingDismissedAt';
  var DISMISS_DAYS = 30;

  // Register the service worker on every entry page — Chrome refuses to
  // offer "Install" (shows "This app cannot be installed — Create shortcut"
  // instead) unless the page has an active SW with a fetch handler. The
  // SPA (index.html) registers its own copy; this one covers the auth
  // pages (login/register/forgot/reset/dashboard/invite) where the user
  // first lands before being logged in.
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function (err) {
      console.warn('[pwa] sw register failed:', err);
    });
  }

  var isStandalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;

  var dismissedAt = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
  var dismissed   = dismissedAt > Date.now() - DISMISS_DAYS * 86400 * 1000;

  var isMobile = window.matchMedia(
    '(max-width: 640px), (orientation: landscape) and (max-height: 540px)'
  ).matches;

  if (!isMobile || isStandalone || dismissed) return;

  // Hide the underlying page immediately so users don't see a flash of login
  // form before the overlay paints. Removed once they tap "Pokračovat".
  var earlyStyle = document.createElement('style');
  earlyStyle.id = 'pwa-landing-early';
  earlyStyle.textContent =
    'body > *:not(#pwaLanding){visibility:hidden!important}';
  function attachEarly() {
    if (document.head) document.head.appendChild(earlyStyle);
    else setTimeout(attachEarly, 0);
  }
  attachEarly();

  // Capture install prompt — if the PWA hasn't been installed yet, the
  // primary button can trigger the native install dialog instead of trying
  // to deep-link to a non-existent app.
  var deferredInstall = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredInstall = e;
  });

  function isAndroid() {
    return /android/i.test(navigator.userAgent);
  }

  function launchInstalledPwa() {
    // Best-effort: on Android Chrome a WebAPK registers itself as a URL
    // handler. Navigating via intent:// with action=VIEW asks Android to
    // route the URL through the WebAPK if one is installed; otherwise the
    // browser falls back to its own handling.
    if (isAndroid()) {
      var host = location.host;
      var path = location.pathname || '/';
      window.location.href =
        'intent://' + host + path +
        '#Intent;scheme=https;action=android.intent.action.VIEW;' +
        'S.browser_fallback_url=' + encodeURIComponent(location.href) + ';end';
      return true;
    }
    return false;
  }

  function build() {
    var style = document.createElement('style');
    style.textContent = [
      '#pwaLanding{position:fixed;inset:0;background:#4A5340;display:flex;',
      'flex-direction:column;align-items:center;justify-content:center;',
      'padding:40px 24px;z-index:99999;font-family:"Montserrat",sans-serif;',
      'gap:18px;overflow-y:auto;visibility:visible!important}',
      '#pwaLanding .pwa-icon{width:128px;height:128px;border-radius:28px;',
      'box-shadow:0 8px 32px rgba(0,0,0,0.5);object-fit:cover;display:block}',
      '#pwaLanding h1{color:#fff;font-size:26px;font-weight:800;',
      'letter-spacing:-0.4px;margin-top:6px;text-align:center}',
      '#pwaLanding p{color:rgba(255,255,255,0.7);font-size:15px;',
      'line-height:1.5;text-align:center;margin-bottom:14px;max-width:320px}',
      '#pwaLanding .pwa-btn{width:100%;max-width:360px;padding:16px 24px;',
      'border-radius:14px;font-size:16px;font-weight:700;font-family:inherit;',
      'cursor:pointer;transition:opacity 0.15s,transform 0.1s;',
      '-webkit-tap-highlight-color:transparent}',
      '#pwaLanding .pwa-btn-primary{background:linear-gradient(180deg,',
      '#C9A84C 0%,#A88A42 100%);color:#4A5340;border:none;',
      'box-shadow:0 4px 16px rgba(168,138,66,0.4)}',
      '#pwaLanding .pwa-btn-primary:active{transform:translateY(1px);',
      'opacity:0.92}',
      '#pwaLanding .pwa-btn-secondary{background:transparent;',
      'color:rgba(255,255,255,0.85);border:1.5px solid rgba(255,255,255,0.22)}',
      '#pwaLanding .pwa-btn-secondary:active{background:rgba(255,255,255,0.06)}'
    ].join('');
    document.head.appendChild(style);

    var div = document.createElement('div');
    div.id = 'pwaLanding';
    div.innerHTML =
      '<img class="pwa-icon" src="/assets/icons/icon-512.png" alt="BeSix Board">' +
      '<h1>BeSix Board</h1>' +
      '<p>Aplikace je nainstalovaná na tvém telefonu.<br>' +
      'Otevři ji z plochy pro nejlepší zážitek.</p>' +
      '<button class="pwa-btn pwa-btn-primary" id="pwaLandingOpen">Otevřít aplikaci</button>' +
      '<button class="pwa-btn pwa-btn-secondary" id="pwaLandingContinue">Pokračovat v prohlížeči</button>';
    document.body.appendChild(div);

    document.getElementById('pwaLandingOpen').addEventListener('click', function () {
      // 1) Not installed yet → trigger native install dialog
      if (deferredInstall) {
        deferredInstall.prompt();
        deferredInstall.userChoice.finally(function () { deferredInstall = null; });
        return;
      }
      // 2) Installed → try Android intent to hand off to the WebAPK
      if (launchInstalledPwa()) return;
      // 3) Fallback (iOS, desktop) — instructions
      this.textContent = '✓ Najdi ji na ploše telefonu';
      this.style.background = 'rgba(255,255,255,0.15)';
      this.style.color = '#fff';
      this.style.boxShadow = 'none';
    });

    document.getElementById('pwaLandingContinue').addEventListener('click', function () {
      // Persistent dismissal — user explicitly chose the browser, don't
      // pester them again for 30 days on this device.
      localStorage.setItem(DISMISS_KEY, String(Date.now()));
      div.remove();
      var early = document.getElementById('pwa-landing-early');
      if (early) early.remove();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }
})();
