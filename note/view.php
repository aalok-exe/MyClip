<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

boot_session();

$username  = $_GET['u'] ?? $_GET['username'] ?? '';
$note_slug = $_GET['n'] ?? $_GET['note_slug'] ?? '';

$note = get_note_by_user_slug($username, $note_slug);

if (!$note) {
    http_response_code(404);
    $page_title = '404 — ' . APP_NAME;
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="wrap" style="text-align:center;padding-top:72px;">
      <div style="font-size:2.5rem;margin-bottom:16px;">🪹</div>
      <h2 style="font-size:1rem;font-weight:600;margin-bottom:8px;">Note not found</h2>
      <p style="color:var(--muted);font-size:.875rem;">This note may have been deleted, or the URL is wrong.</p>
      <a href="' . url() . '" class="btn btn-outline btn-sm" style="margin-top:20px;display:inline-flex;">Go home</a>
    </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$me    = current_user();
$owner = user_owns_note($note);

// Private note gate
if (!$note['is_public'] && !$owner) {
    http_response_code(403);
    $page_title = 'Private — ' . APP_NAME;
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="wrap" style="text-align:center;padding-top:72px;">
      <div style="font-size:2rem;margin-bottom:12px;">🔒</div>
      <h2 style="font-size:1rem;font-weight:600;margin-bottom:6px;">Private note</h2>
      <p style="color:var(--muted);font-size:.875rem;">You need permission to view this.</p>
    </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Password gate
$unlocked = !$note['password_hash'];
$pw_error = false;

if ($note['password_hash'] && !$owner) {
    $skey = 'note_unlocked_' . $note['id'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_password'])) {
        if (password_verify($_POST['note_password'], $note['password_hash'])) {
            $_SESSION[$skey] = true;
            $unlocked = true;
        } else {
            $pw_error = true;
        }
    } elseif (!empty($_SESSION[$skey])) {
        $unlocked = true;
    }
} elseif ($owner) {
    $unlocked = true;
}

if ($unlocked) increment_views($note['id']);

$tags      = tags_array($note['tags']);
$is_md     = $note['syntax'] === 'markdown';
$is_code   = !in_array($note['syntax'], ['plain', 'markdown']);
$revisions = ($owner && $unlocked) ? get_revisions($note['id']) : [];

// Is this note safe for live sync? (public, no password, not private)
$live_sync = $note['is_public'] && !$note['password_hash'] && $unlocked;

$page_title = ($note['title'] ?: 'Untitled') . ' — ' . $note['username'] . ' — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
/* ── View page: use full fluid width ── */
.view-outer {
  width: min(96vw, 1400px);
  margin: 0 auto;
  padding: 20px clamp(12px, 2vw, 40px) 40px;
  display: grid;
  grid-template-columns: 1fr 260px;
  gap: 24px;
  align-items: start;
}
.view-main { min-width: 0; }
.view-sidebar { position: sticky; top: 64px; }

/* Note header */
.note-title-h { font-size: clamp(1rem, 2.5vw, 1.5rem); font-weight: 600; line-height: 1.25; margin-bottom: 8px; }
.note-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: .75rem; color: var(--muted); font-family: var(--mono); margin-bottom: 14px; }
.note-meta a { color: var(--text2); }

/* Action bar */
.action-bar { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; align-items: center; }

/* Content card */
.note-card { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
.note-card-head { background: var(--surface2); border-bottom: 1px solid var(--border); padding: 7px 14px; display: flex; align-items: center; gap: 8px; font-size: .74rem; color: var(--muted); font-family: var(--mono); }
.note-body { padding: 20px; font-family: var(--mono); font-size: .875rem; line-height: 1.75; white-space: pre-wrap; word-break: break-word; overflow-x: auto; }
.note-body.is-md { font-family: var(--sans); font-size: .9rem; white-space: normal; }
.is-md h1,.is-md h2,.is-md h3 { margin: .9em 0 .4em; font-weight: 600; }
.is-md pre { background: var(--surface2); border-radius: 4px; padding: 12px 14px; overflow-x: auto; margin: 10px 0; }
.is-md code { font-family: var(--mono); font-size: .82em; background: var(--surface2); padding: 1px 4px; border-radius: 3px; }
.is-md pre code { background: none; padding: 0; }
.is-md blockquote { border-left: 2px solid var(--border2); margin: 0; padding-left: 14px; color: var(--muted); }
.is-md table { border-collapse: collapse; width: 100%; margin: 10px 0; }
.is-md td,.is-md th { border: 1px solid var(--border); padding: 6px 10px; font-size: .85rem; }
.is-md a { color: var(--text); text-decoration: underline; }
.is-md img { max-width: 100%; border-radius: 4px; }

/* Sidebar */
.sidebar-section { margin-bottom: 20px; }
.sidebar-label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); margin-bottom: 8px; }
.sidebar-link-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 5px;
  padding: 10px;
  font-family: var(--mono);
  font-size: .72rem;
  color: var(--text2);
  word-break: break-all;
  line-height: 1.5;
}
.sidebar-link-box .copy-hint { font-size: .68rem; color: var(--muted); margin-top: 6px; cursor: pointer; }
.sidebar-link-box .copy-hint:hover { color: var(--text); }

/* Live sync badge */
.live-dot { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; font-family: var(--mono); color: var(--muted); }
.live-dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--muted2); display: inline-block; }
.live-dot.active::before { background: var(--success); animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* Revisions */
.rev-list { display: flex; flex-direction: column; gap: 0; }
.rev-item { display: flex; align-items: center; gap: 6px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: .74rem; color: var(--muted); font-family: var(--mono); }
.rev-item:last-child { border-bottom: none; }

/* Password wall */
.pw-wall { text-align: center; padding: 80px 24px; }
.pw-wall h2 { font-size: 1rem; font-weight: 600; margin-bottom: 8px; }
.pw-form { display: flex; gap: 8px; max-width: 300px; margin: 16px auto 0; }
.pw-form input { flex: 1; background: var(--bg); border: 1px solid var(--border); border-radius: var(--r); color: var(--text); padding: 7px 10px; font-family: var(--mono); font-size: .85rem; outline: none; }
.pw-form input:focus { border-color: var(--text2); }

/* Breadcrumb */
.breadcrumb { font-family: var(--mono); font-size: .74rem; color: var(--muted); margin-bottom: 14px; }
.breadcrumb a:hover { color: var(--text); }

/* Collapsed on mobile: sidebar moves below content */
@media (max-width: 860px) {
  .view-outer { grid-template-columns: 1fr; }
  .view-sidebar { position: static; }
}
</style>

<main>
<?php if (!$unlocked): ?>
<div class="wrap">
  <div class="pw-wall">
    <div style="font-size:2rem;margin-bottom:12px;">🔐</div>
    <h2>Password protected</h2>
    <p style="color:var(--muted);font-size:.85rem;">Enter the password to view this note.</p>
    <form method="POST" class="pw-form">
      <input type="password" name="note_password" placeholder="Password" autofocus required>
      <button type="submit" class="btn btn-primary btn-sm">Unlock</button>
    </form>
    <?php if ($pw_error): ?>
      <p style="color:var(--danger);margin-top:10px;font-size:.82rem;">Incorrect password.</p>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<div class="view-outer">

  <!-- ── Main content ── -->
  <div class="view-main">
    <div class="breadcrumb">
      <a href="<?= h(profile_url($note['username'])) ?>">@<?= h($note['username']) ?></a>
      &nbsp;/&nbsp;<span><?= h($note['note_slug'] ?: $note['slug']) ?></span>
    </div>

    <h1 class="note-title-h"><?= h($note['title'] ?: 'Untitled') ?></h1>

    <div class="note-meta">
      <span><?= time_ago($note['updated_at']) ?></span>
      <span>·</span>
      <span id="view-count"><?= number_format($note['views']) ?> views</span>
      <span>·</span>
      <span><?= h(SYNTAX_MODES[$note['syntax']] ?? $note['syntax']) ?></span>
      <?php if (!$note['is_public']): ?><span class="badge badge-red" style="font-size:.68rem;">private</span><?php endif; ?>
      <?php if ($note['password_hash']): ?><span class="badge badge-yellow" style="font-size:.68rem;">password</span><?php endif; ?>
      <?php foreach ($tags as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
    </div>

    <div class="action-bar">
      <button class="btn btn-outline btn-sm" onclick="copyNote()">Copy</button>
      <a href="<?= h(raw_url($note['slug'])) ?>" class="btn btn-outline btn-sm" target="_blank">Raw</a>
      <button class="btn btn-outline btn-sm" onclick="openQR()">QR</button>
      <button class="btn btn-outline btn-sm" onclick="dlNote()">Download</button>
      <?php if ($owner): ?>
        <a href="<?= h(url('note/edit.php?slug=' . urlencode($note['slug']))) ?>" class="btn btn-outline btn-sm">Edit</a>
        <a href="<?= h(url('note/delete.php?slug=' . urlencode($note['slug']))) ?>"
           class="btn btn-danger btn-sm" style="margin-left:auto;"
           onclick="return confirm('Delete this note permanently?')">Delete</a>
      <?php endif; ?>
    </div>

    <div class="note-card" id="note-card">
      <div class="note-card-head">
        <span id="syntax-label"><?= h(SYNTAX_MODES[$note['syntax']] ?? $note['syntax']) ?></span>
        <span style="margin-left:auto;" id="char-count"><?= number_format(strlen($note['content'])) ?> chars</span>
        <?php if ($live_sync): ?>
          <span class="live-dot" id="live-dot" title="Live sync — updates automatically when note changes">Watching</span>
        <?php endif; ?>
      </div>

      <?php if ($is_md): ?>
        <div class="note-body is-md" id="note-body-md"></div>
        <script>document.getElementById('note-body-md').innerHTML = marked.parse(<?= json_encode($note['content']) ?>);</script>
      <?php elseif ($is_code): ?>
        <pre class="note-body" style="padding:0;margin:0;"><code id="code-blk" class="language-<?= h($note['syntax']) ?>"><?= h($note['content']) ?></code></pre>
        <script>document.addEventListener('DOMContentLoaded',()=>hljs.highlightElement(document.getElementById('code-blk')));</script>
      <?php else: ?>
        <div class="note-body" id="note-body-plain"><?= h($note['content']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($owner && count($revisions)): ?>
    <details style="margin-top:16px;">
      <summary class="btn btn-ghost btn-sm" style="cursor:pointer;list-style:none;display:inline-flex;">
        ↺ <?= count($revisions) ?> revision<?= count($revisions)!==1?'s':'' ?>
      </summary>
      <div class="card" style="margin-top:10px;">
        <div class="card-header">Edit history</div>
        <div class="card-body" style="padding:0 14px;">
          <div class="rev-list">
            <?php foreach ($revisions as $r): ?>
              <div class="rev-item">
                <span style="flex:1;"><?= date('M j, Y g:i a', strtotime($r['created_at'])) ?></span>
                <form method="POST" action="<?= h(url('note/restore.php')) ?>" style="display:inline;">
                  <input type="hidden" name="slug" value="<?= h($note['slug']) ?>">
                  <input type="hidden" name="rev_id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-xs"
                          onclick="return confirm('Restore this revision?')">Restore</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </details>
    <?php endif; ?>
  </div>

  <!-- ── Sidebar ── -->
  <div class="view-sidebar">

    <?php if ($note['is_public']): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Permanent link</div>
      <div class="sidebar-link-box">
        <div id="share-url"><?= h(note_url($note)) ?></div>
        <div class="copy-hint" onclick="copyLink()">Click to copy link</div>
      </div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:6px;line-height:1.5;">
        This link works for anyone, forever — no login needed.
      </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
      <div class="sidebar-label">Details</div>
      <div style="font-size:.78rem;color:var(--muted);line-height:1.9;font-family:var(--mono);">
        <div>Author &nbsp;<span style="color:var(--text2);">@<?= h($note['username']) ?></span></div>
        <div>Syntax &nbsp;<span style="color:var(--text2);" id="sd-syntax"><?= h(SYNTAX_MODES[$note['syntax']] ?? $note['syntax']) ?></span></div>
        <div>Created &nbsp;<span style="color:var(--text2);"><?= date('M j, Y', strtotime($note['created_at'])) ?></span></div>
        <div>Updated &nbsp;<span style="color:var(--text2);" id="sd-updated"><?= date('M j, Y g:i a', strtotime($note['updated_at'])) ?></span></div>
        <div>Views &nbsp;<span style="color:var(--text2);" id="sd-views"><?= number_format($note['views']) ?></span></div>
        <div>Size &nbsp;<span style="color:var(--text2);" id="sd-size"><?= number_format(strlen($note['content'])) ?> chars</span></div>
      </div>
    </div>

    <?php if ($tags): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Tags</div>
      <div style="display:flex;flex-wrap:wrap;gap:4px;">
        <?php foreach ($tags as $t): ?><span class="tag"><?= h($t) ?></span><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($owner): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Owner actions</div>
      <div style="display:flex;flex-direction:column;gap:6px;">
        <a href="<?= h(url('note/edit.php?slug=' . urlencode($note['slug']))) ?>" class="btn btn-outline btn-sm" style="justify-content:center;">Edit note</a>
        <a href="<?= h(url('note/delete.php?slug=' . urlencode($note['slug']))) ?>"
           class="btn btn-danger btn-sm" style="justify-content:center;"
           onclick="return confirm('Delete this note permanently?')">Delete</a>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /sidebar -->
</div><!-- /view-outer -->
<?php endif; ?>
</main>

<!-- QR Modal -->
<div class="modal-backdrop" id="qr-modal">
  <div class="modal" style="text-align:center;">
    <h3 style="margin-bottom:14px;">Share via QR</h3>
    <div id="qr-wrap" style="display:inline-block;padding:8px;background:#fff;border-radius:6px;"></div>
    <p style="color:var(--muted);font-size:.72rem;margin-top:8px;font-family:var(--mono);word-break:break-all;"><?= h(note_url($note)) ?></p>
    <button class="btn btn-ghost btn-sm" style="margin-top:12px;" onclick="document.getElementById('qr-modal').classList.remove('open')">Close</button>
  </div>
</div>

<script>
// ── Static data ───────────────────────────────────────────────
const NOTE_SLUG    = <?= json_encode($note['slug']) ?>;
const NOTE_CONTENT_INIT = <?= json_encode($note['content']) ?>;
const NOTE_SYNTAX  = <?= json_encode($note['syntax']) ?>;
const NOTE_TITLE   = <?= json_encode($note['title'] ?: ($note['note_slug'] ?: $note['slug'])) ?>;
const NOTE_URL     = <?= json_encode(note_url($note)) ?>;
const POLL_URL     = <?= json_encode(url('note/poll.php')) ?>;
const LIVE_SYNC    = <?= json_encode($live_sync) ?>;
const POLL_INTERVAL = 8000; // ms between checks

let currentContent = NOTE_CONTENT_INIT;
let lastUpdatedTs  = <?= strtotime($note['updated_at']) ?>;

// ── Copy / Download / QR ─────────────────────────────────────
function copyNote() {
  navigator.clipboard.writeText(currentContent).then(() => {
    const b = event.target; b.textContent = 'Copied!';
    setTimeout(() => b.textContent = 'Copy', 1800);
  });
}
function copyLink() {
  navigator.clipboard.writeText(NOTE_URL).then(() => {
    const el = document.querySelector('.copy-hint');
    el.textContent = 'Copied!';
    setTimeout(() => el.textContent = 'Click to copy link', 2000);
  });
}
function dlNote() {
  const ext = {plain:'.txt',markdown:'.md',javascript:'.js',python:'.py',php:'.php',
    html:'.html',css:'.css',sql:'.sql',json:'.json',yaml:'.yaml',bash:'.sh',
    cpp:'.cpp',java:'.java',ruby:'.rb',go:'.go',rust:'.rs',xml:'.xml'}[NOTE_SYNTAX]||'.txt';
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([currentContent], {type:'text/plain'}));
  a.download = NOTE_TITLE + ext; a.click();
}
let qrDone = false;
function openQR() {
  document.getElementById('qr-modal').classList.add('open');
  if (!qrDone) {
    new QRCode(document.getElementById('qr-wrap'), {text:NOTE_URL, width:180, height:180, colorDark:'#000', colorLight:'#fff'});
    qrDone = true;
  }
}
document.querySelectorAll('.modal-backdrop').forEach(el =>
  el.addEventListener('click', e => { if (e.target===el) el.classList.remove('open'); })
);

// ── Live sync ─────────────────────────────────────────────────
<?php if ($live_sync): ?>
function applyUpdate(data) {
  currentContent = data.content;
  lastUpdatedTs  = data.updated_ts;

  const syntax = data.syntax || NOTE_SYNTAX;

  // Update plain text view
  const plain = document.getElementById('note-body-plain');
  if (plain) plain.textContent = data.content;

  // Update markdown view
  const md = document.getElementById('note-body-md');
  if (md) md.innerHTML = marked.parse(data.content);

  // Update code block (re-highlight)
  const code = document.getElementById('code-blk');
  if (code) {
    code.textContent = data.content;
    code.className = 'language-' + syntax;
    hljs.highlightElement(code);
  }

  // Update sidebar details
  const sdViews = document.getElementById('sd-views');
  if (sdViews) sdViews.textContent = data.views.toLocaleString();
  const sdUpdated = document.getElementById('sd-updated');
  if (sdUpdated) {
    const d = new Date(data.updated_at.replace(' ','T'));
    sdUpdated.textContent = d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})
      + ' ' + d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
  }
  const vcEl = document.getElementById('view-count');
  if (vcEl) vcEl.textContent = data.views.toLocaleString() + ' views';
  const sdSize = document.getElementById('sd-size');
  if (sdSize) sdSize.textContent = data.content.length.toLocaleString() + ' chars';
  const ccEl = document.getElementById('char-count');
  if (ccEl) ccEl.textContent = data.content.length.toLocaleString() + ' chars';
}

function startLiveSync() {
  const dot = document.getElementById('live-dot');
  if (dot) dot.classList.add('active');

  async function poll() {
    try {
      const res = await fetch(POLL_URL + '?slug=' + encodeURIComponent(NOTE_SLUG) + '&since=' + lastUpdatedTs);
      if (!res.ok) throw new Error('poll failed');
      const data = await res.json();
      if (data.changed) applyUpdate(data);
    } catch(e) {
      // silent fail — just try again next interval
    }
    setTimeout(poll, POLL_INTERVAL);
  }

  setTimeout(poll, POLL_INTERVAL); // first check after initial delay
}

document.addEventListener('DOMContentLoaded', startLiveSync);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
