<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – BeSix Board</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Montserrat',sans-serif;background:#1e2710;color:#fff;min-height:100vh}
  /* ── NAV ── */
  nav{background:rgba(255,255,255,0.05);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,0.07);height:52px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:sticky;top:0;z-index:100}
  .nav-logo{display:flex;align-items:center}.besix-logo-nav{height:26px;width:auto;display:block}
  
  .nav-right{display:flex;align-items:center;gap:8px}
  .user-chip{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:50px;padding:4px 12px 4px 4px;font-size:13px;color:rgba(255,255,255,0.75)}
  .avatar{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0}
  .btn-logout{background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.6);padding:5px 12px;border-radius:7px;font-size:13px;cursor:pointer;transition:all 0.15s}
  .btn-logout:hover{background:rgba(255,59,48,0.15);border-color:rgba(255,59,48,0.3);color:#ff6b60}
  /* ── MAIN ── */
  main{max-width:1100px;margin:0 auto;padding:40px 24px}
  .page-title{font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800;margin-bottom:6px;letter-spacing:-0.5px}
  .page-sub{font-size:14px;color:rgba(210,175,80,0.5);margin-bottom:36px}
  /* ── ACTIONS BAR ── */
  .actions{display:flex;gap:10px;margin-bottom:36px;flex-wrap:wrap}
  .btn-action{display:flex;align-items:center;gap:7px;padding:10px 18px;border-radius:9px;font-size:14px;font-family:'Montserrat',sans-serif;font-weight:600;cursor:pointer;border:none;transition:all 0.15s}
  .btn-action.primary{background:linear-gradient(180deg,#c9922a 0%,#a87420 100%);color:#fff;box-shadow:0 2px 10px rgba(160,100,10,0.3)}
  .btn-action.secondary{background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.8);border:1px solid rgba(255,255,255,0.1)}
  .btn-action:hover{opacity:0.88;transform:translateY(-1px)}
  /* ── APP SECTION ── */
  .app-section{margin-bottom:40px}
  .app-header{display:flex;align-items:center;gap:10px;margin-bottom:16px}
  .app-badge{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.25);color:rgba(200,160,50,0.9);font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:0.06em}
  .app-name{font-family:'Montserrat',sans-serif;font-size:18px;font-weight:700;color:rgba(255,255,255,0.85)}
  /* ── GRID ── */
  .projects-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
  .project-card{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:20px;cursor:pointer;transition:all 0.2s;position:relative;text-decoration:none;display:block}
  .project-card:hover{background:rgba(255,255,255,0.08);transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,0.25)}
  .project-card-color{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;vertical-align:middle}
  .project-card-name{font-family:'Montserrat',sans-serif;font-size:15px;font-weight:700;color:rgba(255,255,255,0.92);margin-bottom:5px}
  .project-card-desc{font-size:13px;color:rgba(255,255,255,0.45);line-height:1.45;margin-bottom:12px;min-height:18px}
  .project-card-footer{display:flex;align-items:center;justify-content:space-between}
  .role-pill{font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;text-transform:capitalize}
  .role-owner{background:rgba(255,159,10,0.15);color:rgba(255,195,80,0.9);border:1px solid rgba(255,159,10,0.25)}
  .role-admin{background:rgba(10,132,255,0.12);color:rgba(80,170,255,0.9);border:1px solid rgba(10,132,255,0.25)}
  .role-member{background:rgba(107,128,60,0.12);color:rgba(200,160,50,0.9);border:1px solid rgba(107,128,60,0.25)}
  .role-viewer{background:rgba(255,255,255,0.07);color:rgba(255,255,255,0.5);border:1px solid rgba(255,255,255,0.1)}
  .member-count{font-size:12px;color:rgba(255,255,255,0.35)}
  /* ── NEW PROJECT CARD ── */
  .card-new{border:1.5px dashed rgba(255,255,255,0.15);background:rgba(255,255,255,0.02);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:120px;cursor:pointer;transition:all 0.2s}
  .card-new:hover{border-color:rgba(107,128,60,0.5);background:rgba(107,128,60,0.05)}
  .card-new-icon{font-size:28px;color:rgba(255,255,255,0.25)}
  .card-new-label{font-size:13px;color:rgba(210,175,80,0.5);font-weight:500}
  /* ── MODAL ── */
  .overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);z-index:200;align-items:center;justify-content:center}
  .overlay.open{display:flex}
  .modal{background:#1e2d10;border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:36px;width:100%;max-width:440px}
  .modal h2{font-family:'Montserrat',sans-serif;font-size:20px;font-weight:800;margin-bottom:20px;color:#fff}
  .mfield{margin-bottom:14px}
  .mfield label{display:block;font-size:12px;font-weight:600;color:rgba(210,175,80,0.6);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.04em}
  .mfield input,.mfield select,.mfield textarea{width:100%;padding:10px 13px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:14px;font-family:'Montserrat',sans-serif;outline:none;transition:border-color 0.15s}
  .mfield input:focus,.mfield select:focus,.mfield textarea:focus{border-color:rgba(107,128,60,0.6)}
  .mfield select option{background:#1e2d10}
  .mfield textarea{resize:vertical;min-height:70px}
  .color-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
  .color-swatch{width:28px;height:28px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:border-color 0.15s}
  .color-swatch.sel{border-color:#fff}
  .modal-btns{display:flex;gap:10px;margin-top:20px}
  .btn-cancel{flex:1;padding:11px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:rgba(255,255,255,0.7);font-size:14px;cursor:pointer}
  .btn-create{flex:2;padding:11px;background:linear-gradient(180deg,#c9922a 0%,#a87420 100%);border:none;border-radius:8px;color:#fff;font-size:14px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer}
  /* ── JOIN MODAL ── */
  .notice{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px}
  .notice.error{background:rgba(255,59,48,0.12);border:1px solid rgba(255,59,48,0.25);color:#ff6b60}
  .notice.success{background:rgba(107,128,60,0.15);border:1px solid rgba(107,128,60,0.3);color:rgba(210,185,70,0.9)}
  /* ── EMPTY ── */
  .empty{text-align:center;padding:48px 20px;color:rgba(210,175,80,0.35);font-size:14px}
  #loading{text-align:center;padding:60px;color:rgba(210,175,80,0.4);font-size:14px}
  /* ── SETTINGS BTN ── */
  .proj-settings-btn{position:absolute;top:10px;right:10px;width:28px;height:28px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:7px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;color:rgba(255,255,255,0.4);transition:all 0.15s;text-decoration:none;z-index:2}
  .proj-settings-btn:hover{background:rgba(255,255,255,0.14);color:rgba(255,255,255,0.8);border-color:rgba(255,255,255,0.2)}
  /* ── MEMBERS MODAL ── */
  .member-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.06)}
  .member-row:last-child{border-bottom:none}
  .member-info{flex:1;min-width:0}
  .member-info strong{display:block;font-size:13px;color:rgba(255,255,255,0.88);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .member-info span{font-size:11px;color:rgba(255,255,255,0.4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block}
  .btn-remove-member{background:none;border:none;color:rgba(255,100,80,0.5);font-size:16px;cursor:pointer;padding:2px 6px;border-radius:5px;line-height:1;transition:all 0.15s;flex-shrink:0}
  .btn-remove-member:hover{background:rgba(255,59,48,0.15);color:#ff6b60}
  .invite-row{display:flex;gap:8px;margin-top:14px}
  .invite-row input{flex:1;padding:9px 12px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:13px;font-family:'Montserrat',sans-serif;outline:none}
  .invite-row input:focus{border-color:rgba(107,128,60,0.6)}
  .invite-role-sel{padding:9px 10px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:13px;font-family:'Montserrat',sans-serif;outline:none}
  .invite-role-sel option{background:#1e2d10}
  .btn-invite{padding:9px 16px;background:linear-gradient(180deg,#c9922a 0%,#a87420 100%);border:none;border-radius:8px;color:#fff;font-size:13px;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;white-space:nowrap}
  .members-list{max-height:240px;overflow-y:auto;margin-bottom:4px}
  @media(max-width:640px){
    nav{padding:0 14px}
    main{padding:24px 14px}
    .page-title{font-size:22px}
    .actions{gap:8px}
    .btn-action{padding:8px 14px;font-size:13px}
    .projects-grid{grid-template-columns:1fr}
    .modal{padding:24px 20px;margin:0 12px}
  }
</style>
</head>
<body>
<nav>
  <img src="/assets/besix-logo.png" class="besix-logo-nav">
  <div class="nav-right">
    <div class="user-chip">
      <div class="avatar" id="navAvatar"></div>
      <span id="navName">…</span>
    </div>
    <button class="btn-logout" onclick="doLogout()">Odhlásit</button>
  </div>
</nav>

<main>
  <div class="page-title">Moje projekty</div>
  <div class="page-sub">Přehled všech projektů, ke kterým máš přístup</div>

  <div class="actions">
    <button class="btn-action primary" onclick="openNewModal()">+ Nový projekt</button>
    <button class="btn-action secondary" onclick="openJoinModal()">🔗 Připojit se kódem</button>
  </div>

  <div id="loading">Načítám projekty…</div>
  <div id="projectsContainer"></div>
</main>

<!-- New project modal -->
<div class="overlay" id="newModal">
  <div class="modal">
    <h2>Nový projekt</h2>
    <div id="newModalMsg"></div>
    <input type="hidden" id="newAppKey" value="stavbaboard">
    <div class="mfield">
      <label>Název projektu</label>
      <input type="text" id="newName" placeholder="Např. Rekonstrukce bytového domu">
    </div>
    <div class="mfield">
      <label>Popis (volitelný)</label>
      <textarea id="newDesc" placeholder="Krátký popis projektu…"></textarea>
    </div>
    <div class="mfield">
      <label>Barva projektu</label>
      <div class="color-row" id="colorRow"></div>
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="closeNewModal()">Zrušit</button>
      <button class="btn-create" onclick="createProject()">Vytvořit projekt</button>
    </div>
  </div>
</div>

<!-- Join by code modal -->
<div class="overlay" id="joinModal">
  <div class="modal">
    <h2>Připojit se kódem</h2>
    <div id="joinMsg"></div>
    <div class="mfield">
      <label>Kód projektu</label>
      <input type="text" id="joinCode" placeholder="Vlož zvací kód projektu">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="document.getElementById(\'joinModal\').classList.remove(\'open\')">Zrušit</button>
      <button class="btn-create" onclick="joinProject()">Připojit se</button>
    </div>
  </div>
</div>

<!-- Members modal -->
<div class="overlay" id="membersModal">
  <div class="modal" style="max-width:480px">
    <h2>Členové projektu</h2>
    <div id="membersMsg"></div>
    <div class="members-list" id="membersList"></div>
    <div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.08);padding-top:16px">
      <div class="mfield" style="margin-bottom:8px"><label>Pozvat nového člena e-mailem</label></div>
      <div class="invite-row">
        <input type="email" id="inviteEmail" placeholder="email@example.com">
        <select class="invite-role-sel" id="inviteRole">
          <option value="member">Člen</option>
          <option value="admin">Admin</option>
          <option value="viewer">Zobrazit</option>
        </select>
        <button class="btn-invite" onclick="sendInvite()">Pozvat</button>
      </div>
      <div id="inviteMsg" style="margin-top:8px;font-size:13px"></div>
    </div>
    <div class="modal-btns" style="margin-top:16px">
      <button class="btn-cancel" onclick="document.getElementById('membersModal').classList.remove('open')">Zavřít</button>
    </div>
  </div>
</div>

<script>
const BG_COLORS = ['#4a5240','#2e3a2a','#1e2d3a','#2a1e3a','#3a1e1e','#1e3a2e','#2a2e1e','#1a2232','#2d2010','#3a2e1a'];
let selColor = BG_COLORS[0];
let currentUser = null;

// ── Boot ─────────────────────────────────────────────────────────────────
(async () => {
  try {
    const res = await fetch('/api/auth.php?action=me', { credentials: 'include' });
    if (!res.ok) { location.href = '/login.php'; return; }
    const data = await res.json();
    currentUser = data.user;
    renderNav();
    await loadProjects();
    handleParams();
  } catch {
    location.href = '/login.php';
  }
})();

function renderNav() {
  document.getElementById('navName').textContent = currentUser.name;
  const av = document.getElementById('navAvatar');
  av.textContent = currentUser.name.charAt(0).toUpperCase();
  av.style.background = currentUser.avatar_color || '#4A5340';
}

function handleParams() {
  const p = new URLSearchParams(location.search);
  if (p.get('joined')) {
    // Highlight joined project - just scroll to it
    history.replaceState(null, '', '/dashboard.php');
  }
  if (p.get('error') === 'invite_wrong_account') {
    alert('Pozvánka je určena pro jiný email.');
    history.replaceState(null, '', '/dashboard.php');
  }
}

// ── Load projects ─────────────────────────────────────────────────────────
async function loadProjects() {
  const res = await fetch('/api/projects.php?action=list&app_key=stavbaboard', { credentials: 'include' });
  const data = await res.json();
  document.getElementById('loading').style.display = 'none';
  renderProjects(data.projects || []);
}

function renderProjects(projects) {
  const container = document.getElementById('projectsContainer');

  // Group by app
  const byApp = {};
  projects.forEach(p => {
    if (!byApp[p.app_key]) byApp[p.app_key] = { name: p.app_name, items: [] };
    byApp[p.app_key].items.push(p);
  });

  if (Object.keys(byApp).length === 0) {
    container.innerHTML = '<div class="empty">Nemáš žádné projekty. Vytvoř nový nebo se připoj kódem.</div>';
    return;
  }

  container.innerHTML = Object.entries(byApp).map(([key, app]) => `
    <div class="app-section">
      <div class="app-header">
        <div class="app-badge" style="${key==='stavbaboard'?'text-transform:none':''}">
          ${key === 'stavbaboard' ? 'BeSix Board' : escHtml(key)}
        </div>
        <div class="app-name">${key === 'stavbaboard' ? 'BeSix Board' : escHtml(app.name)}</div>
      </div>
      <div class="projects-grid">
        ${app.items.map(p => projectCard(p, key)).join('')}
        <div class="project-card card-new" onclick="openNewModalFor('${key}')">
          <div class="card-new-icon">+</div>
          <div class="card-new-label">Nový projekt</div>
        </div>
      </div>
    </div>
  `).join('');
}

function projectCard(p, appKey) {
  const roleClass = 'role-' + p.role;
  const appEntry  = appKey === 'stavbaboard' ? 'index.html' : 'plans/index.html';
  const href      = `/${appEntry}?project_id=${p.id}`;
  const canManage = (p.role === 'owner' || p.role === 'admin');
  return `
    <div style="position:relative">
      <a class="project-card" href="${href}" style="border-left:3px solid ${p.bg_color || '#4a5240'}">
        <div class="project-card-name">
          <span class="project-card-color" style="background:${p.bg_color || '#4a5240'}"></span>
          ${escHtml(p.name)}
        </div>
        <div class="project-card-desc">${escHtml(p.description || '')}</div>
        <div class="project-card-footer">
          <span class="role-pill ${roleClass}">${p.role}</span>
          <span class="member-count">${p.member_count} člen${p.member_count > 4 ? 'ů' : p.member_count > 1 ? 'i' : ''}</span>
        </div>
      </a>
      <button class="proj-settings-btn" onclick="openMembersModal(${p.id},'${p.role}')" title="Správa členů">👥</button>
    </div>`;
}

// ── New project modal ─────────────────────────────────────────────────────
function buildColorRow() {
  const row = document.getElementById('colorRow');
  row.innerHTML = BG_COLORS.map(c =>
    `<div class="color-swatch${c===selColor?' sel':''}" style="background:${c}" data-c="${c}" onclick="pickColor(this,'${c}')"></div>`
  ).join('');
}

function pickColor(el, c) {
  selColor = c;
  document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('sel'));
  el.classList.add('sel');
}

function openNewModal() {
  buildColorRow();
  document.getElementById('newModal').classList.add('open');
  document.getElementById('newModalMsg').innerHTML = '';
}

function openNewModalFor(appKey) {
  document.getElementById('newAppKey').value = appKey;
  openNewModal();
}

function closeNewModal() {
  document.getElementById('newModal').classList.remove('open');
}

async function createProject() {
  const appKey = document.getElementById('newAppKey').value;
  const name   = document.getElementById('newName').value.trim();
  const desc   = document.getElementById('newDesc').value.trim();

  if (!name) {
    document.getElementById('newModalMsg').innerHTML = '<div class="notice error">Název je povinný.</div>';
    return;
  }

  const res  = await fetch('/api/projects.php?action=create', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ app_key: appKey, name, description: desc, bg_color: selColor })
  });
  const data = await res.json();

  if (data.success) {
    closeNewModal();
    document.getElementById('newName').value = '';
    document.getElementById('newDesc').value = '';
    await loadProjects();
  } else {
    document.getElementById('newModalMsg').innerHTML = `<div class="notice error">${data.error || 'Chyba.'}</div>`;
  }
}

// ── Join by code ──────────────────────────────────────────────────────────
function openJoinModal() {
  document.getElementById('joinMsg').innerHTML = '';
  document.getElementById('joinCode').value = '';
  document.getElementById('joinModal').classList.add('open');
}

async function joinProject() {
  const code = document.getElementById('joinCode').value.trim();
  if (!code) return;

  const res  = await fetch('/api/invitations.php?action=join&invite_code=' + encodeURIComponent(code), {
    credentials: 'include'
  });
  const data = await res.json();

  if (data.success) {
    document.getElementById('joinMsg').innerHTML = `<div class="notice success">Připojen! Přesměruji…</div>`;
    setTimeout(async () => {
      document.getElementById('joinModal').classList.remove('open');
      await loadProjects();
    }, 1200);
  } else {
    document.getElementById('joinMsg').innerHTML = `<div class="notice error">${data.error || 'Chyba.'}</div>`;
  }
}

// ── Members modal ─────────────────────────────────────────────────────────
let activeMembersProjectId = null;
let activeMembersMyRole    = null;

async function openMembersModal(projectId, myRole) {
  activeMembersProjectId = projectId;
  activeMembersMyRole    = myRole;
  document.getElementById('membersMsg').innerHTML   = '';
  document.getElementById('inviteMsg').innerHTML    = '';
  document.getElementById('inviteEmail').value      = '';
  document.getElementById('membersList').innerHTML  = '<div style="color:rgba(255,255,255,0.35);font-size:13px;padding:12px 0">Načítám…</div>';
  document.getElementById('membersModal').classList.add('open');
  await loadMembers();
}

async function loadMembers() {
  const res  = await fetch(`/api/projects.php?action=members&project_id=${activeMembersProjectId}`, { credentials: 'include' });
  const data = await res.json();
  const list = document.getElementById('membersList');
  if (!data.success) { list.innerHTML = '<div style="color:#ff6b60;font-size:13px">Chyba načítání.</div>'; return; }

  const canManage = (activeMembersMyRole === 'owner' || activeMembersMyRole === 'admin');
  let html = '';

  if (data.members.length) {
    html += data.members.map(m => {
      const ini = m.name.split(' ').map(w=>w[0]||'').join('').substring(0,2).toUpperCase();
      const isOwner = m.role === 'owner';
      const canEdit = canManage && !isOwner && m.id !== currentUser.id;
      const roleEl = canEdit
        ? `<select class="invite-role-sel" style="padding:4px 8px;font-size:12px" onchange="changeMemberRole(${m.id}, this.value)">
            <option value="admin"  ${m.role==='admin'  ?'selected':''}>admin</option>
            <option value="member" ${m.role==='member' ?'selected':''}>member</option>
            <option value="viewer" ${m.role==='viewer' ?'selected':''}>viewer</option>
          </select>`
        : `<span class="role-pill role-${m.role}" style="flex-shrink:0">${m.role}</span>`;
      const delBtn = canEdit
        ? `<button class="btn-remove-member" onclick="removeMember(${m.id})" title="Odebrat člena">✕</button>`
        : '';
      return `<div class="member-row">
        <div class="avatar" style="background:${m.avatar_color||'#4A5340'};width:34px;height:34px;font-size:12px;flex-shrink:0">${ini}</div>
        <div class="member-info"><strong>${escHtml(m.name)}</strong><span>${escHtml(m.email)}</span></div>
        ${roleEl}
        ${delBtn}
      </div>`;
    }).join('');
  } else {
    html += '<div style="color:rgba(255,255,255,0.35);font-size:13px;padding:8px 0">Žádní členové.</div>';
  }

  // Invitation history (pending + accepted)
  if (canManage && data.invitations && data.invitations.length) {
    html += `<div style="margin-top:12px;padding-top:10px;border-top:1px solid rgba(255,255,255,0.07);font-size:11px;font-weight:700;color:rgba(200,165,60,0.5);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px">Historie pozvánek</div>`;
    html += data.invitations.map(inv => {
      const sentDate = new Date(inv.created_at).toLocaleDateString('cs-CZ');
      const isPending = inv.status === 'pending';
      const statusLabel = isPending ? '⏳ čeká na přijetí' : '✓ přijato';
      const statusColor = isPending ? 'rgba(255,255,255,0.45)' : 'rgba(90,180,100,0.9)';
      const cancelBtn = isPending
        ? `<button class="btn-remove-member" onclick="cancelInvite(${inv.id})" title="Zrušit pozvánku">✕</button>`
        : '';
      return `<div class="member-row" style="opacity:${isPending ? '0.75' : '0.9'}">
        <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.06);border:1.5px ${isPending ? 'dashed rgba(200,165,60,0.35)' : 'solid rgba(90,180,100,0.3)'};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">${isPending ? '✉' : '✓'}</div>
        <div class="member-info">
          <strong style="color:rgba(255,255,255,0.7)">${escHtml(inv.invited_email)}</strong>
          <span style="color:${statusColor}">${statusLabel} · ${sentDate}</span>
        </div>
        <span class="role-pill role-${inv.role}" style="flex-shrink:0;opacity:0.7">${inv.role}</span>
        ${cancelBtn}
      </div>`;
    }).join('');
  }

  list.innerHTML = html;
}

async function changeMemberRole(userId, newRole) {
  const res = await fetch('/api/projects.php?action=update_role', {
    method: 'PUT',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project_id: activeMembersProjectId, user_id: userId, role: newRole }),
  });
  const data = await res.json();
  if (!data.success) {
    document.getElementById('membersMsg').innerHTML = `<div class="notice error">${escHtml(data.error||'Chyba')}</div>`;
    await loadMembers(); // revert select to actual role
  }
}

async function removeMember(userId) {
  if (!confirm('Odebrat člena z projektu?')) return;
  const res = await fetch(
    `/api/projects.php?action=remove_member&project_id=${activeMembersProjectId}&user_id=${userId}`,
    { method: 'DELETE', credentials: 'include' }
  );
  const data = await res.json();
  if (data.success) {
    await loadMembers();
    await loadProjects();
  } else {
    document.getElementById('membersMsg').innerHTML = `<div class="notice error">${escHtml(data.error||'Chyba')}</div>`;
  }
}

async function cancelInvite(inviteId) {
  if (!confirm('Zrušit pozvánku?')) return;
  const res = await fetch(
    `/api/invitations.php?action=cancel&invite_id=${inviteId}&project_id=${activeMembersProjectId}`,
    { method: 'DELETE', credentials: 'include' }
  );
  const data = await res.json();
  if (data.success) {
    await loadMembers();
  } else {
    document.getElementById('membersMsg').innerHTML = `<div class="notice error">${escHtml(data.error||'Chyba')}</div>`;
  }
}

async function sendInvite() {
  const email = document.getElementById('inviteEmail').value.trim();
  const role  = document.getElementById('inviteRole').value;
  const msgEl = document.getElementById('inviteMsg');
  if (!email) { msgEl.innerHTML = '<span style="color:#ff6b60">Zadej e-mail.</span>'; return; }
  msgEl.innerHTML = '';
  const res = await fetch('/api/invitations.php?action=send', {
    method: 'POST', credentials: 'include',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ project_id: activeMembersProjectId, email, role })
  });
  const data = await res.json();
  if (data.success) {
    msgEl.innerHTML = `<span style="color:rgba(210,185,70,0.9)">✓ ${escHtml(data.message)}</span>`;
    document.getElementById('inviteEmail').value = '';
    await loadMembers();
    await loadProjects();
  } else {
    msgEl.innerHTML = `<span style="color:#ff6b60">${escHtml(data.error||'Chyba')}</span>`;
  }
}

// ── Logout ────────────────────────────────────────────────────────────────
async function doLogout() {
  await fetch('/api/auth.php?action=logout', { method: 'POST', credentials: 'include' });
  location.href = '/login.php';
}

// ── Util ──────────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close modals on overlay click
document.querySelectorAll('.overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>
