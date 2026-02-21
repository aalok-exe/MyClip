<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
boot_session();
require_login();

$me     = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    if (empty(trim($content)))         $errors[] = 'Content cannot be empty.';
    if (!validate_note_size($content)) $errors[] = 'Note too large (max 1 MB).';

    if (empty($errors)) {
        $note = create_note($me['id'], [
            'content'   => $content,
            'title'     => $_POST['title']    ?? '',
            'syntax'    => $_POST['syntax']   ?? 'plain',
            'tags'      => $_POST['tags']     ?? '',
            'is_public' => isset($_POST['is_public']),
            'password'  => $_POST['password'] ?? '',
        ]);
        flash_set('success', 'Note created.');
        redirect(note_url($note));
    }
}

$page_title = 'New Note — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.editor-wrap { width: min(92vw, 1100px); margin: 0 auto; padding: 24px clamp(16px,2.5vw,40px); }
.note-title-input {
  display: block; width: 100%;
  background: transparent; border: none; border-bottom: 1px solid var(--border);
  color: var(--text); font-family: var(--sans); font-size: 1.1rem; font-weight: 600;
  padding: 4px 0 8px; outline: none; margin-bottom: 16px;
  transition: border-color .12s;
}
.note-title-input:focus { border-color: var(--text2); }
.note-title-input::placeholder { color: var(--muted); font-weight: 400; }
.url-hint { font-family: var(--mono); font-size: .72rem; color: var(--muted); margin-bottom: 14px; }
.url-hint span { color: var(--text2); }
.editor-card { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; }
.editor-toolbar {
  background: var(--surface2); border-bottom: 1px solid var(--border);
  padding: 7px 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
}
.editor-toolbar select {
  background: var(--bg); border: 1px solid var(--border); border-radius: 4px;
  color: var(--text); font-family: var(--mono); font-size: .75rem;
  padding: 3px 7px; outline: none; cursor: pointer;
}
.editor-toolbar select:focus { border-color: var(--text2); }
#note-ta {
  display: block; width: 100%; min-height: clamp(320px, 55vh, 720px);
  background: transparent; border: none; color: var(--text);
  font-family: var(--mono); font-size: .85rem; line-height: 1.7;
  padding: 16px; resize: vertical; outline: none; tab-size: 4;
}
.editor-footer {
  background: var(--surface2); border-top: 1px solid var(--border);
  padding: 6px 12px; display: flex; gap: 14px; align-items: center;
  font-size: .72rem; color: var(--muted); font-family: var(--mono);
}
#preview-pane { display:none; padding:16px; min-height:200px; font-size:.875rem; line-height:1.75; border-top:1px solid var(--border); }
.options-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
.toggle-card {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 5px; padding: 10px 12px; cursor: pointer;
  transition: border-color .12s;
}
.toggle-card:has(input:checked) { border-color: var(--text2); }
.toggle-card input { margin-top: 2px; flex-shrink: 0; }
.toggle-card strong { display: block; font-size: .82rem; font-weight: 500; }
.toggle-card small { font-size: .74rem; color: var(--muted); }
.err-list { list-style:none; padding:9px 12px; background:rgba(224,96,96,.08); border-left:2px solid var(--danger); border-radius:4px; margin-bottom:14px; color:var(--danger); font-size:.82rem; }
.submit-row { display:flex; align-items:center; gap:8px; margin-top:14px; }
.autosave { font-size:.72rem; color:var(--muted); margin-left:auto; }
@media(max-width:600px){ .options-row{grid-template-columns:1fr;} .editor-wrap{padding:16px;} }
</style>

<main>
<div class="editor-wrap">
  <?php if ($errors): ?>
    <ul class="err-list"><?php foreach($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <form method="POST" id="nf">
    <input type="text" name="title" id="note-title" class="note-title-input"
           placeholder="Note title…" maxlength="255"
           value="<?= h($_POST['title'] ?? '') ?>" oninput="updateUrlHint()" autocomplete="off">

    <div class="url-hint">Link: <?= h(url($me['username'] . '/')) ?><span id="slug-hint">your-note-title</span></div>

    <div class="editor-card">
      <div class="editor-toolbar">
        <select name="syntax" id="syn" onchange="onSyn()">
          <?php foreach (SYNTAX_MODES as $v => $l): ?>
            <option value="<?= h($v) ?>" <?= (($_POST['syntax'] ?? 'plain') === $v ? 'selected':'') ?>><?= h($l) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="prev-btn" class="btn btn-ghost btn-xs" style="display:none" onclick="togglePrev()">Preview</button>
        <span style="margin-left:auto;font-size:.72rem;color:var(--muted);font-family:var(--mono);" id="cc">0</span>
      </div>

      <textarea name="content" id="note-ta"
                placeholder="Start typing…"
                oninput="updateStats()" onkeydown="handleTab(event)"
                autofocus><?= h($_POST['content'] ?? '') ?></textarea>
      <div id="preview-pane"></div>

      <div class="editor-footer">
        <span id="s-chars">0 chars</span>
        <span id="s-words">0 words</span>
        <span id="s-lines">1 line</span>
      </div>
    </div>

    <div class="options-row">
      <div class="form-group" style="margin:0;">
        <label>Password (optional)</label>
        <input class="form-control" type="password" name="password" placeholder="Leave blank for none" autocomplete="new-password">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Tags</label>
        <input class="form-control" type="text" name="tags" placeholder="php, snippet, todo" value="<?= h($_POST['tags'] ?? '') ?>">
      </div>
    </div>

    <div style="margin-top:10px;">
      <label class="toggle-card">
        <input type="checkbox" name="is_public" checked>
        <div>
          <strong>Public</strong>
          <small>Anyone with the link can view this note</small>
        </div>
      </label>
    </div>

    <div class="submit-row">
      <button type="submit" class="btn btn-primary">Save Note</button>
      <a href="<?= h(profile_url($me['username'])) ?>" class="btn btn-ghost">Cancel</a>
      <span class="autosave" id="autosave-lbl"></span>
    </div>
  </form>
</div>
</main>

<script>
const ta = document.getElementById('note-ta');
const DRAFT = 'pn_draft';

function slugify(t) {
  return t.toLowerCase().replace(/[^a-z0-9\s\-]/g,'').replace(/[\s\-]+/g,'-').replace(/^-|-$/g,'') || 'untitled';
}
function updateUrlHint() {
  document.getElementById('slug-hint').textContent = slugify(document.getElementById('note-title').value) || 'your-note-title';
}
function updateStats() {
  const v=ta.value, c=v.length, w=v.trim()?v.trim().split(/\s+/).length:0, l=v.split('\n').length;
  document.getElementById('cc').textContent = c.toLocaleString();
  document.getElementById('s-chars').textContent = c.toLocaleString()+' chars';
  document.getElementById('s-words').textContent = w.toLocaleString()+' words';
  document.getElementById('s-lines').textContent = l+' line'+(l!==1?'s':'');
}
function handleTab(e) {
  if (e.key!=='Tab') return; e.preventDefault();
  const s=ta.selectionStart, en=ta.selectionEnd;
  ta.value = ta.value.slice(0,s)+'    '+ta.value.slice(en);
  ta.selectionStart = ta.selectionEnd = s+4;
  updateStats();
}
function onSyn() {
  document.getElementById('prev-btn').style.display = document.getElementById('syn').value==='markdown' ? '' : 'none';
  if (document.getElementById('syn').value !== 'markdown') showEditor();
}
let isPrev = false;
function togglePrev() { isPrev ? showEditor() : showPrev(); }
function showPrev() {
  document.getElementById('preview-pane').innerHTML = marked.parse(ta.value || '*Nothing to preview*');
  document.getElementById('preview-pane').style.display = '';
  ta.style.display = 'none';
  document.getElementById('prev-btn').textContent = 'Edit';
  isPrev = true;
}
function showEditor() {
  document.getElementById('preview-pane').style.display = 'none';
  ta.style.display = ''; document.getElementById('prev-btn').textContent = 'Preview'; isPrev = false;
}

let saveTimer;
ta.addEventListener('input', () => {
  clearTimeout(saveTimer);
  saveTimer = setTimeout(() => {
    localStorage.setItem(DRAFT, JSON.stringify({content: ta.value, ts: Date.now()}));
    const lbl = document.getElementById('autosave-lbl');
    lbl.textContent = 'Draft saved';
    setTimeout(() => lbl.textContent = '', 2000);
  }, 900);
});

window.addEventListener('DOMContentLoaded', () => {
  updateStats(); updateUrlHint();
  if (!ta.value) {
    try {
      const d = JSON.parse(localStorage.getItem(DRAFT)||'');
      if (d?.content && d.ts > Date.now()-86400000) {
        if (confirm('Restore unsaved draft from '+new Date(d.ts).toLocaleTimeString()+'?')) {
          ta.value = d.content; updateStats();
        }
      }
    } catch(e){}
  }
});
document.getElementById('nf').addEventListener('submit', () => localStorage.removeItem(DRAFT));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
