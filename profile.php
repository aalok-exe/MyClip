<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
boot_session();

$username = $_GET['username'] ?? '';
$user     = get_user_by_username($username);

if (!$user || !$user['is_active']) {
    http_response_code(404);
    $page_title = 'Not Found — ' . APP_NAME;
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="wrap" style="text-align:center;padding-top:80px;">
        <div style="font-size:2rem;margin-bottom:12px;">404</div>
        <p style="color:var(--muted);">No user found at this address.</p>
    </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$me      = current_user();
$is_own  = $me && (int)$me['id'] === (int)$user['id'];

// Load the pad note (auto-created on first visit)
$pad = get_or_create_pad($user['id'], $user['username']);

// Load other saved notes (exclude the pad)
$saved_notes = [];
$st = db()->prepare(
    'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id
     WHERE n.user_id = ? AND n.note_slug != ? AND n.is_public = 1
     ORDER BY n.updated_at DESC LIMIT 50'
);
$st->execute([$user['id'], '__pad__']);
$saved_notes = $st->fetchAll();

if ($is_own) {
    // Also fetch private saved notes
    $st2 = db()->prepare(
        'SELECT n.*, u.username FROM notes n JOIN users u ON u.id = n.user_id
         WHERE n.user_id = ? AND n.note_slug != ? AND n.is_public = 0
         ORDER BY n.updated_at DESC LIMIT 50'
    );
    $st2->execute([$user['id'], '__pad__']);
    $private_notes = $st2->fetchAll();
    // Merge and re-sort
    $saved_notes = array_merge($saved_notes, $private_notes);
    usort($saved_notes, fn($a,$b) => strtotime($b['updated_at']) - strtotime($a['updated_at']));
}

$is_md   = $pad['syntax'] === 'markdown';
$is_code = !in_array($pad['syntax'], ['plain','markdown']);

$page_title = $user['username'] . '\'s clip';
require_once __DIR__ . '/includes/header.php';
?>
<style>
/* ══ Full-height pad layout ══ */
.pad-shell {
  display: flex;
  flex-direction: column;
  height: calc(100vh - var(--nav-h));
}

/* ── Toolbar ── */
.pad-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 16px;
  height: 40px;
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
  flex-wrap: nowrap;
  overflow-x: auto;
}
.pad-user {
  font-family: var(--mono);
  font-size: .78rem;
  color: var(--muted);
  white-space: nowrap;
}
.pad-user strong { color: var(--text2); }
.pad-sep { width: 1px; height: 16px; background: var(--border); flex-shrink: 0; }
.pad-status {
  font-family: var(--mono);
  font-size: .72rem;
  color: var(--muted);
  white-space: nowrap;
  min-width: 60px;
}
.pad-status.saving { color: var(--warning); }
.pad-status.saved  { color: var(--success); }
.pad-status.error  { color: var(--danger); }
.syn-select {
  background: transparent;
  border: none;
  color: var(--muted);
  font-family: var(--mono);
  font-size: .72rem;
  outline: none;
  cursor: pointer;
  padding: 2px 4px;
}
.syn-select:hover { color: var(--text); }
.ml { margin-left: auto; }

/* ── Main area: editor | preview ── */
.pad-body {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr;
  overflow: hidden;
  position: relative;
}

/* ── The textarea ── */
#pad-ta {
  width: 100%;
  height: 100%;
  background: var(--bg);
  border: none;
  color: var(--text);
  font-family: var(--mono);
  font-size: .9rem;
  line-height: 1.75;
  padding: 24px clamp(20px, 4vw, 80px);
  resize: none;
  outline: none;
  tab-size: 4;
  overflow-y: auto;
  caret-color: var(--text);
}
#pad-ta::placeholder { color: var(--muted2); }
#pad-ta:read-only {
  cursor: default;
  background: var(--bg);
}

/* ── Read-only viewer ── */
#pad-viewer {
  display: none;
  width: 100%;
  height: 100%;
  overflow-y: auto;
  padding: 24px clamp(20px, 4vw, 80px);
  font-family: var(--mono);
  font-size: .9rem;
  line-height: 1.75;
  white-space: pre-wrap;
  word-break: break-word;
  background: var(--bg);
}
#pad-viewer.is-md { font-family: var(--sans); white-space: normal; }
.is-md h1,.is-md h2,.is-md h3 { margin:.9em 0 .4em; font-weight:600; }
.is-md pre { background:var(--surface); border-radius:4px; padding:12px 14px; overflow-x:auto; margin:10px 0; }
.is-md code { font-family:var(--mono); font-size:.84em; background:var(--surface); padding:1px 5px; border-radius:3px; }
.is-md pre code { background:none; padding:0; }
.is-md blockquote { border-left:2px solid var(--border2); margin:0; padding-left:14px; color:var(--muted); }
.is-md table { border-collapse:collapse; width:100%; margin:10px 0; }
.is-md td,.is-md th { border:1px solid var(--border); padding:6px 10px; }
.is-md a { color:var(--text); text-decoration:underline; }
.is-md img { max-width:100%; border-radius:4px; }

/* ── Code highlight wrap ── */
#pad-viewer pre.code-view {
  margin: 0;
  padding: 24px clamp(20px, 4vw, 80px);
  background: var(--bg);
  white-space: pre;
  overflow-x: auto;
  font-size: .9rem;
  line-height: 1.75;
  height: 100%;
}
#pad-viewer pre.code-view code { background: none; padding: 0; font-size: inherit; }

/* ── Saved notes drawer ── */
.saved-panel {
  position: fixed;
  top: calc(var(--nav-h) + 40px);
  right: 0;
  bottom: 0;
  width: 280px;
  background: var(--bg2);
  border-left: 1px solid var(--border);
  transform: translateX(100%);
  transition: transform .2s ease;
  z-index: 200;
  display: flex;
  flex-direction: column;
}
.saved-panel.open { transform: translateX(0); }
.saved-panel-head {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  font-size: .75rem;
  font-weight: 600;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .5px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.saved-panel-list {
  flex: 1;
  overflow-y: auto;
}
.saved-item {
  display: block;
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
  text-decoration: none;
  color: var(--text);
  font-size: .8rem;
  transition: background .1s;
}
.saved-item:hover { background: var(--surface); }
.saved-item-title {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  margin-bottom: 2px;
}
.saved-item-meta {
  font-size: .68rem;
  color: var(--muted);
  font-family: var(--mono);
  display: flex;
  align-items: center;
  gap: 6px;
}

/* ── Snapshot modal ── */
.snap-modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 400;
  align-items: center;
  justify-content: center;
}
.snap-modal.open { display: flex; }
.snap-box {
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: 8px;
  padding: 22px;
  width: min(92vw, 360px);
}
.snap-box h3 { font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
.snap-box input {
  display: block; width: 100%;
  background: var(--bg); border: 1px solid var(--border);
  border-radius: var(--r); color: var(--text);
  font-size: .875rem; padding: 7px 10px;
  outline: none; margin-bottom: 12px;
  transition: border-color .12s;
}
.snap-box input:focus { border-color: var(--text2); }
.snap-row { display: flex; gap: 8px; }

/* ── Copy toast ── */
.toast {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(12px);
  background: var(--surface2); border: 1px solid var(--border2);
  color: var(--text); font-size: .78rem; padding: 7px 16px;
  border-radius: 20px; opacity: 0; transition: all .2s ease;
  pointer-events: none; z-index: 999; white-space: nowrap;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

@media (max-width: 600px) {
  #pad-ta, #pad-viewer { padding: 16px; font-size: .82rem; }
  .saved-panel { width: 100%; }
}
</style>

<div class="pad-shell">

  <!-- ── Toolbar ── -->
  <div class="pad-toolbar">
    <span class="pad-user"><strong><?= h($user['username']) ?></strong>/clip</span>
    <div class="pad-sep"></div>

    <?php if ($is_own): ?>
      <!-- Syntax selector -->
      <select class="syn-select" id="syn-sel" title="Syntax">
        <?php foreach (SYNTAX_MODES as $v => $l): ?>
          <option value="<?= h($v) ?>" <?= $pad['syntax'] === $v ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="pad-sep"></div>
      <span class="pad-status" id="pad-status">ready</span>
      <div class="pad-sep"></div>
      <!-- Actions -->
      <button class="btn btn-ghost btn-xs" onclick="copyPad()" title="Copy all">Copy</button>
      <button class="btn btn-ghost btn-xs" onclick="clearPad()" title="Clear pad">Clear</button>
    <?php else: ?>
      <span class="pad-status" style="color:var(--muted);"><?= h(SYNTAX_MODES[$pad['syntax']] ?? $pad['syntax']) ?></span>
      <button class="btn btn-ghost btn-xs ml" onclick="copyPad()">Copy</button>
    <?php endif; ?>

    <?php if (!$is_own): ?>
      <div class="pad-sep"></div>
    <?php endif; ?>


    <!-- Raw link -->
    <a href="<?= h(raw_url($pad['slug'])) ?>" class="btn btn-ghost btn-xs" target="_blank" title="Raw content">Raw</a>

    <span style="font-family:var(--mono);font-size:.68rem;color:var(--muted2);margin-left:4px;" id="pad-chars">
      <?= number_format(strlen($pad['content'])) ?> chars
    </span>
  </div>

  <!-- ── Pad body ── -->
  <div class="pad-body">
    <?php if ($is_own): ?>
      <textarea id="pad-ta"
                placeholder="Paste or type anything… it auto-saves."
                spellcheck="false"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
      ><?= h($pad['content']) ?></textarea>
    <?php else: ?>
      <!-- Read-only: show rendered content -->
      <div id="pad-viewer" class="<?= $is_md ? 'is-md' : '' ?>">
        <?php if ($is_md): ?>
          <!-- rendered by JS -->
        <?php elseif ($is_code): ?>
          <pre class="code-view"><code class="language-<?= h($pad['syntax']) ?>" id="code-el"><?= h($pad['content']) ?></code></pre>
        <?php else: ?>
          <?= h($pad['content']) ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /clip-shell -->

<!-- ── Saved notes panel ── -->
<div class="saved-panel" id="saved-panel">
  <div class="saved-panel-head">
    Saved Notes
    <button class="btn btn-ghost btn-xs" style="margin-left:auto;" onclick="togglePanel()">✕</button>
  </div>
  <div class="saved-panel-list">
    <?php if (empty($saved_notes)): ?>
      <div style="padding:20px 14px;font-size:.78rem;color:var(--muted);line-height:1.6;">
        No saved notes yet.<br>
        <?php if ($is_own): ?>Use "Save as note…" to snapshot the current pad into a permanent note.<?php endif; ?>
      </div>
    <?php else: ?>
      <?php foreach ($saved_notes as $n): ?>
        <a href="<?= h(note_url($n)) ?>" class="saved-item">
          <div class="saved-item-title"><?= h($n['title'] ?: 'Untitled') ?></div>
          <div class="saved-item-meta">
            <span><?= time_ago($n['updated_at']) ?></span>
            <span>·</span>
            <span><?= h($n['syntax']) ?></span>
            <?php if (!$n['is_public']): ?><span class="badge badge-red" style="font-size:.6rem;">priv</span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($is_own): ?>
<!-- ── Snapshot modal ── -->
<div class="snap-modal" id="snap-modal">
  <div class="snap-box">
    <h3>Save as permanent note</h3>
    <p style="font-size:.78rem;color:var(--muted);margin-bottom:12px;line-height:1.5;">
      Creates a permanent, shareable note from your current pad content. Your pad stays as-is.
    </p>
    <input type="text" id="snap-title" placeholder="Title (optional — defaults to date/time)" maxlength="255">
    <div class="snap-row">
      <button class="btn btn-primary btn-sm" onclick="doSnapshot()">Save note</button>
      <button class="btn btn-ghost btn-sm" onclick="closeSnap()">Cancel</button>
      <span id="snap-status" style="font-size:.75rem;color:var(--muted);margin-left:auto;"></span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
const IS_OWN    = <?= json_encode($is_own) ?>;
const PAD_SLUG  = <?= json_encode($pad['slug']) ?>;
const PAD_SYNTAX_INIT = <?= json_encode($pad['syntax']) ?>;
const SAVE_URL  = <?= json_encode(url('pad/save.php')) ?>;
const SNAP_URL  = <?= json_encode(url('pad/snapshot.php')) ?>;
const POLL_URL  = <?= json_encode(url('note/poll.php')) ?>;
const IS_MD     = <?= json_encode($is_md) ?>;
const IS_CODE   = <?= json_encode($is_code) ?>;
const PAD_CONTENT_INIT = <?= json_encode($pad['content']) ?>;

// ── Toast ────────────────────────────────────────────────────
function toast(msg, dur = 2200) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), dur);
}

// ── Saved panel ───────────────────────────────────────────────
// togglePanel defined globally in header.php


<?php if ($is_own): ?>
// ── Auto-save ─────────────────────────────────────────────────
const ta       = document.getElementById('pad-ta');
const statusEl = document.getElementById('pad-status');
const charsEl  = document.getElementById('pad-chars');
const synSel   = document.getElementById('syn-sel');

let saveTimer   = null;
let lastSaved   = '';
let currentSyn  = PAD_SYNTAX_INIT;

function setStatus(s, cls) {
  statusEl.textContent = s;
  statusEl.className   = 'pad-status ' + (cls||'');
}

function updateChars() {
  charsEl.textContent = ta.value.length.toLocaleString() + ' chars';
}

async function doSave() {
  if (ta.value === lastSaved && synSel.value === currentSyn) return; // no change
  setStatus('saving…', 'saving');
  try {
    const fd = new FormData();
    fd.append('content', ta.value);
    fd.append('syntax', synSel.value);
    const res = await fetch(SAVE_URL, { method: 'POST', body: fd });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (data.ok) {
      lastSaved  = ta.value;
      currentSyn = synSel.value;
      setStatus('saved ' + data.saved_at, 'saved');
    } else {
      setStatus('error: ' + (data.error||'?'), 'error');
    }
  } catch(e) {
    setStatus('error: ' + e.message, 'error');
  }
}

function scheduleSave() {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(doSave, 900); // 900ms debounce
}

ta.addEventListener('input', () => { updateChars(); scheduleSave(); });
ta.addEventListener('paste', () => { updateChars(); scheduleSave(); });
synSel.addEventListener('change', scheduleSave);

// Tab key → indent
ta.addEventListener('keydown', e => {
  if (e.key !== 'Tab') return;
  e.preventDefault();
  const s = ta.selectionStart, en = ta.selectionEnd;
  ta.value = ta.value.slice(0, s) + '    ' + ta.value.slice(en);
  ta.selectionStart = ta.selectionEnd = s + 4;
  scheduleSave();
});

// Ctrl+S / Cmd+S → force save immediately
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    clearTimeout(saveTimer);
    doSave();
  }
});

// Init
lastSaved = ta.value;
updateChars();
setStatus('ready', '');

// ── Clear ─────────────────────────────────────────────────────
function clearPad() {
  if (ta.value && !confirm('Clear the pad? This will auto-save an empty pad.')) return;
  ta.value = '';
  updateChars();
  scheduleSave();
  ta.focus();
}

// ── Copy ──────────────────────────────────────────────────────
function copyPad() {
  navigator.clipboard.writeText(ta.value).then(() => toast('Copied to clipboard'));
}

// ── Snapshot modal ────────────────────────────────────────────
function openSnap() {
  document.getElementById('snap-modal').classList.add('open');
  document.getElementById('snap-title').focus();
  document.getElementById('snap-status').textContent = '';
}
function closeSnap() {
  document.getElementById('snap-modal').classList.remove('open');
}
document.getElementById('snap-modal').addEventListener('click', e => {
  if (e.target === document.getElementById('snap-modal')) closeSnap();
});
document.getElementById('snap-title').addEventListener('keydown', e => {
  if (e.key === 'Enter') doSnapshot();
  if (e.key === 'Escape') closeSnap();
});

async function doSnapshot() {
  const title  = document.getElementById('snap-title').value.trim();
  const status = document.getElementById('snap-status');
  status.textContent = 'Saving…';
  try {
    // Make sure we save the current pad first
    await doSave();
    const fd2 = new FormData();
    fd2.append('title', title);
    const res  = await fetch(SNAP_URL, { method: 'POST', body: fd2 });
    const data = await res.json();
    if (data.ok) {
      closeSnap();
      toast('Note saved! Opening…');
      setTimeout(() => window.open(data.url, '_blank'), 600);
    } else {
      status.textContent = data.error || 'Error';
    }
  } catch(e) {
    status.textContent = 'Network error';
  }
}

<?php else: ?>
// ── Read-only: render content ─────────────────────────────────
const viewer = document.getElementById('pad-viewer');
viewer.style.display = 'block';

if (IS_MD) {
  viewer.innerHTML = marked.parse(PAD_CONTENT_INIT || '');
} else if (IS_CODE) {
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('code-el');
    if (el) hljs.highlightElement(el);
  });
}

function copyPad() {
  navigator.clipboard.writeText(PAD_CONTENT_INIT).then(() => toast('Copied'));
}

// ── Live poll for visitor: refresh when pad changes ───────────
let lastTs = <?= strtotime($pad['updated_at'] ?: 'now') ?>;
async function pollPad() {
  try {
    const r = await fetch(POLL_URL + '?slug=' + encodeURIComponent(PAD_SLUG) + '&since=' + lastTs);
    if (r.ok) {
      const d = await r.json();
      if (d.changed) location.reload(); // simplest & most reliable
    }
  } catch(e) {}
  setTimeout(pollPad, 10000);
}
setTimeout(pollPad, 10000);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
