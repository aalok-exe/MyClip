<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
boot_session();
require_login();

$slug = trim($_GET['slug'] ?? '');
$note = $slug ? get_note($slug) : null;

if (!$note || !user_owns_note($note)) {
    flash_set('error', 'Note not found or access denied.');
    redirect(profile_url(current_user()['username']));  
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    if (empty(trim($content)))          $errors[] = 'Content cannot be empty.';
    if (!validate_note_size($content))  $errors[] = 'Note too large (max 1 MB).';

    if (empty($errors)) {
        // Password handling:
        //   change_pw checkbox checked  AND password field non-empty → set new password
        //   change_pw checkbox checked  AND password field empty     → clear password
        //   change_pw checkbox NOT checked                           → leave unchanged (false)
        $pw = false;
        if (!empty($_POST['change_pw'])) {
            $pw = $_POST['password'] ?? '';   // '' = clear, 'xxx' = set new
        }

        update_note($note['id'], $content, $note['content'], [
            'title'     => $_POST['title']  ?? '',
            'syntax'    => $_POST['syntax'] ?? 'plain',
            'tags'      => $_POST['tags']   ?? '',
            'is_public' => isset($_POST['is_public']),
            'password'  => $pw,
        ]);

        // Re-fetch so the URL reflects any title/slug change
        $note = get_note($note['slug']);
        flash_set('success', 'Note updated.');
        redirect(note_url($note));
    }
}

$page_title = 'Edit — ' . ($note['title'] ?: 'Untitled') . ' — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.note-title-input {
  display: block; width: 100%;
  background: transparent; border: none; border-bottom: 1px solid var(--border);
  color: var(--text); font-family: var(--sans); font-size: 1.1rem; font-weight: 600;
  padding: 4px 0 8px; outline: none; margin-bottom: 16px;
  transition: border-color .12s;
}
.note-title-input:focus { border-color: var(--text2); }
.note-title-input::placeholder { color: var(--muted); font-weight: 400; }
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
#note-ta {
  display: block; width: 100%; min-height: clamp(300px, 55vh, 720px);
  background: transparent; border: none; color: var(--text);
  font-family: var(--mono); font-size: .85rem; line-height: 1.7;
  padding: 16px; resize: vertical; outline: none; tab-size: 4;
}
.editor-footer {
  background: var(--surface2); border-top: 1px solid var(--border);
  padding: 6px 12px; display: flex; gap: 14px; font-size: .72rem; color: var(--muted); font-family: var(--mono);
}
#preview-pane { display:none; padding:16px; min-height:200px; font-size:.875rem; line-height:1.75; border-top:1px solid var(--border); }
.options-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
.toggle-card {
  display: flex; align-items: flex-start; gap: 8px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 5px; padding: 10px 12px; cursor: pointer; transition: border-color .12s;
}
.toggle-card:has(input:checked) { border-color: var(--text2); }
.toggle-card input { margin-top: 2px; flex-shrink: 0; }
.toggle-card strong { display: block; font-size: .82rem; font-weight: 500; }
.toggle-card small { font-size: .74rem; color: var(--muted); }
.err-list { list-style:none; padding:9px 12px; background:rgba(224,96,96,.08); border-left:2px solid var(--danger); border-radius:4px; margin-bottom:14px; color:var(--danger); font-size:.82rem; }
@media(max-width:600px){ .options-row{grid-template-columns:1fr;} }
</style>
<main>
<div class="wrap">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <span style="font-size:.9rem;font-weight:600;">Edit note</span>
    <a href="<?= h(note_url($note)) ?>" class="btn btn-ghost btn-sm">← Back</a>
  </div>

  <?php if ($errors): ?>
    <ul class="err-list"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <form method="POST" id="ef">
    <input type="text" name="title" class="note-title-input"
           placeholder="Note title…" maxlength="255"
           value="<?= h($_POST['title'] ?? $note['title'] ?? '') ?>" autocomplete="off">

    <div class="editor-card">
      <div class="editor-toolbar">
        <?php $cur = $_POST['syntax'] ?? $note['syntax']; ?>
        <select name="syntax" id="syn" onchange="onSyn()">
          <?php foreach (SYNTAX_MODES as $v => $l): ?>
            <option value="<?= h($v) ?>" <?= ($cur === $v ? 'selected' : '') ?>><?= h($l) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="prev-btn" class="btn btn-ghost btn-xs"
                style="display:<?= ($cur === 'markdown' ? '' : 'none') ?>"
                onclick="togglePrev()">Preview</button>
        <span style="margin-left:auto;font-size:.72rem;color:var(--muted);font-family:var(--mono);" id="cc">0</span>
      </div>

      <textarea name="content" id="note-ta"
                oninput="updateStats()" onkeydown="handleTab(event)"><?= h($_POST['content'] ?? $note['content']) ?></textarea>
      <div id="preview-pane"></div>

      <div class="editor-footer">
        <span id="s-chars">0 chars</span>
        <span id="s-words">0 words</span>
        <span id="s-lines">1 line</span>
      </div>
    </div>

    <div class="options-row">
      <div class="form-group" style="margin:0;">
        <label>Tags</label>
        <input class="form-control" type="text" name="tags"
               placeholder="php, snippet" value="<?= h($_POST['tags'] ?? $note['tags'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Change password</label>
        <div style="display:flex;gap:6px;align-items:center;">
          <input class="form-control" type="password" name="password"
                 placeholder="New password (blank = remove)" autocomplete="new-password" style="flex:1;">
          <label style="display:flex;align-items:center;gap:4px;font-size:.78rem;cursor:pointer;white-space:nowrap;">
            <input type="checkbox" name="change_pw" value="1" id="chk-pw">
            Change
          </label>
        </div>
      </div>
    </div>

    <div style="margin-top:10px;">
      <label class="toggle-card">
        <input type="checkbox" name="is_public" value="1"
               <?= (!empty($_POST) ? (isset($_POST['is_public']) ? 'checked' : '') : ($note['is_public'] ? 'checked' : '')) ?>>
        <div>
          <strong>Public</strong>
          <small>Anyone with the link can view this note</small>
        </div>
      </label>
    </div>

    <div style="display:flex;gap:8px;margin-top:14px;">
      <button type="submit" class="btn btn-primary">Save</button>
      <a href="<?= h(note_url($note)) ?>" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>
</main>
<script>
const ta = document.getElementById('note-ta');
function updateStats() {
  const v=ta.value, c=v.length, w=v.trim()?v.trim().split(/\s+/).length:0, l=v.split('\n').length;
  document.getElementById('cc').textContent=c.toLocaleString();
  document.getElementById('s-chars').textContent=c.toLocaleString()+' chars';
  document.getElementById('s-words').textContent=w.toLocaleString()+' words';
  document.getElementById('s-lines').textContent=l+' line'+(l!==1?'s':'');
}
function handleTab(e) {
  if(e.key!=='Tab') return; e.preventDefault();
  const s=ta.selectionStart, en=ta.selectionEnd;
  ta.value=ta.value.slice(0,s)+'    '+ta.value.slice(en);
  ta.selectionStart=ta.selectionEnd=s+4; updateStats();
}
function onSyn() {
  document.getElementById('prev-btn').style.display=document.getElementById('syn').value==='markdown'?'':'none';
  if(document.getElementById('syn').value!=='markdown') showEditor();
}
let isPrev=false;
function togglePrev(){ isPrev?showEditor():showPrev(); }
function showPrev(){
  document.getElementById('preview-pane').innerHTML=marked.parse(ta.value||'*Nothing to preview*');
  document.getElementById('preview-pane').style.display=''; ta.style.display='none';
  document.getElementById('prev-btn').textContent='Edit'; isPrev=true;
}
function showEditor(){
  document.getElementById('preview-pane').style.display='none'; ta.style.display='';
  document.getElementById('prev-btn').textContent='Preview'; isPrev=false;
}
window.addEventListener('DOMContentLoaded', updateStats);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
