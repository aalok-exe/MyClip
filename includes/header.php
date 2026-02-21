<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link id="hljs-theme" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark-dimmed.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/9.1.6/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
/* ── Theme: Warm Grey Minimalist ── */
:root {
  --bg:        #1c1c1c;
  --bg2:       #242424;
  --surface:   #2c2c2c;
  --surface2:  #333333;
  --border:    #3d3d3d;
  --border2:   #4a4a4a;
  --text:      #e8e8e8;
  --text2:     #c0c0c0;
  --muted:     #888888;
  --muted2:    #555555;
  --accent:    #e8e8e8;
  --accent-d:  rgba(232,232,232,0.06);
  --danger:    #e06060;
  --success:   #60c080;
  --warning:   #d4904a;
  --r:         5px;
  --sans:      'IBM Plex Sans', system-ui, sans-serif;
  --mono:      'IBM Plex Mono', monospace;
  --nav-h:     48px;
}
[data-theme="light"] {
  --bg:       #f7f6f3;
  --bg2:      #efede9;
  --surface:  #ffffff;
  --surface2: #f0eeea;
  --border:   #dddbd7;
  --border2:  #ccc9c4;
  --text:     #1a1a1a;
  --text2:    #3a3a3a;
  --muted:    #888880;
  --muted2:   #d8d6d2;
  --accent:   #1a1a1a;
  --accent-d: rgba(0,0,0,0.05);
}

/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 14px; scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  font-size: 14px;
  line-height: 1.6;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
a:hover { color: var(--text2); }

/* ── Navbar — FULL WIDTH ── */
header {
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  height: var(--nav-h);
  position: sticky;
  top: 0;
  z-index: 100;
  width: 100%;
}
.nav-inner {
  width: 100%;
  padding: 0 24px;
  height: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
}
.logo {
  font-size: .9rem;
  font-weight: 600;
  color: var(--text);
  letter-spacing: -.1px;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  margin-right: 12px;
}
.logo-mark {
  width: 20px; height: 20px;
  background: var(--text);
  color: var(--bg);
  border-radius: 3px;
  font-size: 10px;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--mono);
  flex-shrink: 0;
}
.nav-sep { width: 1px; height: 18px; background: var(--border); margin: 0 4px; }
header nav { display: flex; align-items: center; gap: 2px; margin-left: auto; }

/* ── Buttons ── */
.btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 11px;
  border-radius: var(--r);
  font-family: var(--sans);
  font-size: .82rem;
  font-weight: 400;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all .12s;
  white-space: nowrap;
  line-height: 1.4;
  text-decoration: none !important;
}
.btn-primary  { background: var(--text); color: var(--bg); border-color: var(--text); font-weight: 500; }
.btn-primary:hover { opacity: .85; color: var(--bg); }
.btn-outline  { background: transparent; border-color: var(--border2); color: var(--text2); }
.btn-outline:hover { border-color: var(--text); color: var(--text); background: var(--accent-d); }
.btn-ghost    { background: transparent; border-color: transparent; color: var(--muted); }
.btn-ghost:hover { background: var(--surface2); color: var(--text); }
.btn-danger   { background: transparent; border-color: var(--danger); color: var(--danger); }
.btn-danger:hover { background: var(--danger); color: #fff; }
.btn-sm  { padding: 4px 9px; font-size: .78rem; }
.btn-xs  { padding: 3px 7px; font-size: .74rem; }
.btn-icon{ padding: 5px 7px; }

/* ── Layout — responsive fluid ── */
main { flex: 1; }
/* 
  Fluid layout: 92vw on small screens, grows to a comfortable max on wide monitors.
  clamp(min, preferred, max) — shrinks on mobile, fills well on 1080p/1440p/4K.
*/
.wrap       { width: min(92vw, 1100px); margin: 0 auto; padding: 28px clamp(16px,2.5vw,40px); }
.wrap-wide  { width: min(96vw, 1400px); margin: 0 auto; padding: 28px clamp(16px,2vw,40px); }
.container  { width: min(92vw, 1100px); margin: 0 auto; padding: 28px clamp(16px,2.5vw,40px); }

/* ── Cards ── */
.card        { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
.card-header { background: var(--surface2); border-bottom: 1px solid var(--border); padding: 9px 14px; display: flex; align-items: center; gap: 10px; font-size: .75rem; color: var(--muted); }
.card-body   { padding: 14px; }

/* ── Forms ── */
.form-group  { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.form-group label { font-size: .74rem; color: var(--muted); font-weight: 500; text-transform: uppercase; letter-spacing: .5px; }
.form-control {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: var(--r);
  color: var(--text);
  font-family: var(--sans);
  font-size: .875rem;
  padding: 7px 10px;
  width: 100%;
  outline: none;
  transition: border-color .12s;
}
.form-control:focus { border-color: var(--text2); }
input[type="checkbox"] { accent-color: var(--text); cursor: pointer; }

/* ── Flash ── */
.flash-wrap { width: min(92vw, 1100px); margin: 10px auto 0; padding: 0 clamp(16px,2.5vw,40px); }
.flash { padding: 9px 14px; border-radius: var(--r); font-size: .82rem; border-left: 2px solid; }
.flash-error   { background: rgba(224,96,96,.08);  border-color: var(--danger);  color: var(--danger); }
.flash-success { background: rgba(96,192,128,.08); border-color: var(--success); color: var(--success); }
.flash-info    { background: rgba(200,200,200,.06);border-color: var(--muted);   color: var(--muted); }

/* ── Tags & Badges ── */
.tag { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: .7rem; background: var(--surface2); border: 1px solid var(--border); color: var(--muted); font-family: var(--mono); }
.badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: .68rem; font-weight: 500; font-family: var(--mono); }
.badge-green  { background: rgba(96,192,128,.1);  color: var(--success); border: 1px solid rgba(96,192,128,.2); }
.badge-yellow { background: rgba(212,144,74,.1);  color: var(--warning); border: 1px solid rgba(212,144,74,.2); }
.badge-red    { background: rgba(224,96,96,.1);   color: var(--danger);  border: 1px solid rgba(224,96,96,.2); }
.badge-blue   { background: var(--surface2); color: var(--muted); border: 1px solid var(--border); }

/* ── Tables ── */
.data-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.data-table th { background: var(--surface2); color: var(--muted); font-weight: 500; font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; padding: 9px 12px; text-align: left; border-bottom: 1px solid var(--border); }
.data-table td { padding: 9px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--surface2); }

/* ── Modals ── */
.modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 500; align-items: center; justify-content: center; padding: 20px; }
.modal-backdrop.open { display: flex; }
.modal { background: var(--surface); border: 1px solid var(--border2); border-radius: 8px; padding: 22px; width: 100%; max-width: 380px; animation: mIn .14s ease; }
@keyframes mIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
.modal h3 { font-size: .95rem; font-weight: 600; margin-bottom: 10px; }

/* ── Footer ── */
footer { border-top: 1px solid var(--border); padding: 12px 24px; font-size: .74rem; color: var(--muted); display: flex; align-items: center; }
footer .ml { margin-left: auto; }

/* ── Scrollbar ── */
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 99px; }

/* ── Responsive ── */
@media (max-width: 700px) {
  .hide-sm { display: none !important; }
  .wrap, .container { padding: 16px 14px; }
  .nav-inner { padding: 0 12px; }
}
</style>
</head>
<body>
<header>
  <div class="nav-inner">
    <a href="<?= url() ?>" class="logo">
      <span class="logo-mark">M</span>
      MyClip
    </a>

    <?php $me = current_user(); ?>
    <?php if ($me && is_admin()): ?>
      <div class="nav-sep"></div>
      <a href="<?= url('admin/') ?>" class="btn btn-ghost btn-sm hide-sm">Admin</a>
    <?php endif; ?>

    <nav>
      <?php if ($me): ?>
        <span class="btn btn-ghost btn-sm hide-sm" style="cursor:default;color:var(--text2);"><?= h($me['username']) ?></span>
        <button class="btn btn-primary btn-sm" id="nav-my-notes" data-pad-url="<?= h(profile_url($me['username'])) ?>" onclick="togglePanel()">My Notes</button>
        <a href="<?= url('auth/logout.php') ?>" class="btn btn-ghost btn-sm">Out</a>
      <?php else: ?>
        <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost btn-sm">Sign in</a>
        <a href="<?= url('auth/register.php') ?>" class="btn btn-primary btn-sm">Register</a>
      <?php endif; ?>
      <button class="btn btn-ghost btn-icon" onclick="toggleTheme()" title="Toggle theme">
        <svg id="ico-moon" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3a7 7 0 109.79 9.79z"/></svg>
        <svg id="ico-sun" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
      </button>
    </nav>
  </div>
</header>

<?php $flash = flash_get(); if ($flash): ?>
  <div class="flash-wrap"><div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div></div>
<?php endif; ?>

<script>
function togglePanel() {
  const p = document.getElementById('saved-panel');
  if (p) { p.classList.toggle('open'); return; }
  // Not on pad page — navigate there
  const btn = document.getElementById('nav-my-notes');
  if (btn && btn.dataset.padUrl) window.location = btn.dataset.padUrl;
}
function toggleTheme() {
  const html = document.documentElement, light = html.dataset.theme === 'light';
  html.dataset.theme = light ? 'dark' : 'light';
  document.getElementById('ico-moon').style.display = light ? '' : 'none';
  document.getElementById('ico-sun').style.display  = light ? 'none' : '';
  document.getElementById('hljs-theme').href = light
    ? 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark-dimmed.min.css'
    : 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css';
  localStorage.setItem('pn_theme', html.dataset.theme);
}
(function() {
  const t = localStorage.getItem('pn_theme') || 'dark';
  document.documentElement.dataset.theme = t;
  if (t === 'light') {
    document.getElementById('ico-moon').style.display = 'none';
    document.getElementById('ico-sun').style.display  = '';
    document.getElementById('hljs-theme').href = 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css';
  }
})();
</script>
