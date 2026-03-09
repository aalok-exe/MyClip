<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

boot_session();
require_admin();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_note') {
        $id = (int)($_POST['note_id'] ?? 0);
        if ($id) { delete_note($id); flash_set('success', 'Note deleted.'); }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $me = current_user();
        if ($id && $id !== (int)$me['id']) {
            db()->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
            flash_set('success', 'User deleted.');
        } else {
            flash_set('error', 'Cannot delete your own account.');
        }
    }

    if ($action === 'toggle_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $me = current_user();
        if ($id && $id !== (int)$me['id']) {
            db()->prepare('UPDATE users SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
            flash_set('success', 'User status updated.');
        }
    }

    if ($action === 'set_role') {
        $id   = (int)($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', ['user','admin']) ? $_POST['role'] : 'user';
        $me   = current_user();
        if ($id && $id !== (int)$me['id']) {
            db()->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
            flash_set('success', 'Role updated.');
        }
    }

    redirect(url('admin/') . '?tab=' . ($_GET['tab'] ?? 'overview'));
}

$tab = $_GET['tab'] ?? 'overview';

// Stats
$stats = db()->query('
    SELECT
      (SELECT COUNT(*) FROM users) as total_users,
      (SELECT COUNT(*) FROM users WHERE is_active=1) as active_users,
      (SELECT COUNT(*) FROM notes) as total_notes,
      (SELECT COUNT(*) FROM notes WHERE is_public=1) as public_notes,
      (SELECT SUM(views) FROM notes) as total_views,
      (SELECT COUNT(*) FROM revisions) as total_revisions
')->fetch();

// Users list
$users = db()->query('SELECT u.*, (SELECT COUNT(*) FROM notes WHERE user_id=u.id) as note_count FROM users u ORDER BY u.created_at DESC')->fetchAll();

// Notes list (recent 100)
$notes_list = db()->query('
    SELECT n.*, u.username FROM notes n
    JOIN users u ON u.id = n.user_id
    ORDER BY n.created_at DESC LIMIT 100
')->fetchAll();

$page_title = 'Admin Panel — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.admin-shell{max-width:1200px;margin:0 auto;padding:24px;}
.admin-title{font-size:1.1rem;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:10px;}
.admin-title span{font-size:.85rem;color:var(--muted);background:rgba(240,176,96,.1);border:1px solid rgba(240,176,96,.3);border-radius:4px;padding:2px 8px;font-family:var(--mono);}
.tabs{display:flex;gap:2px;border-bottom:1px solid var(--border);margin-bottom:24px;}
.tab-btn{padding:9px 18px;border:none;background:transparent;color:var(--muted);font-family:var(--sans);font-size:.875rem;cursor:pointer;border-bottom:2px solid transparent;transition:all .18s;}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.tab-btn:hover{color:var(--text);}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:28px;}
.stat-box{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px 18px;}
.stat-box .n{font-family:var(--sans);font-size:1.8rem;font-weight:800;line-height:1;}
.stat-box .l{font-size:.75rem;color:var(--muted);margin-top:4px;}
.section-title{font-size:1.1rem;font-weight:700;margin-bottom:14px;}
.truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;}
</style>

<main>
<div class="wrap-wide">
  <div class="admin-title">⚙ Admin Panel <span>admin</span></div>

  <div class="tabs">
    <button class="tab-btn <?= $tab==='overview'?'active':'' ?>" onclick="switchTab('overview')">Overview</button>
    <button class="tab-btn <?= $tab==='users'?'active':'' ?>"    onclick="switchTab('users')">Users (<?= count($users) ?>)</button>
    <button class="tab-btn <?= $tab==='notes'?'active':'' ?>"    onclick="switchTab('notes')">Notes (<?= number_format($stats['total_notes']) ?>)</button>
  </div>

  <!-- Overview Tab -->
  <div id="tab-overview" class="tab-panel" style="display:<?= $tab==='overview'?'':'none' ?>">
    <div class="stats-grid">
      <div class="stat-box"><div class="n"><?= number_format($stats['total_users']) ?></div><div class="l">Total users</div></div>
      <div class="stat-box"><div class="n"><?= number_format($stats['active_users']) ?></div><div class="l">Active users</div></div>
      <div class="stat-box"><div class="n"><?= number_format($stats['total_notes']) ?></div><div class="l">Total notes</div></div>
      <div class="stat-box"><div class="n"><?= number_format($stats['public_notes']) ?></div><div class="l">Public notes</div></div>
      <div class="stat-box"><div class="n"><?= number_format($stats['total_views']) ?></div><div class="l">Total views</div></div>
      <div class="stat-box"><div class="n"><?= number_format($stats['total_revisions']) ?></div><div class="l">Revisions stored</div></div>
    </div>

    <div class="section-title">Recent Activity</div>
    <div class="card">
      <table class="data-table">
        <thead><tr><th>Note</th><th>User</th><th>Visibility</th><th>Views</th><th>Created</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($notes_list, 0, 10) as $n): ?>
          <tr>
            <td><a href="<?= h(note_url($n)) ?>" class="truncate" style="display:block;color:var(--text);"><?= h($n['title'] ?: 'Untitled') ?></a></td>
            <td><span style="font-family:var(--mono);font-size:.8rem;color:var(--muted);">@<?= h($n['username']) ?></span></td>
            <td><?= $n['is_public'] ? '<span class="badge badge-green">public</span>' : '<span class="badge badge-red">private</span>' ?></td>
            <td style="font-family:var(--mono);font-size:.85rem;"><?= number_format($n['views']) ?></td>
            <td style="font-family:var(--mono);font-size:.78rem;color:var(--muted);"><?= time_ago($n['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Users Tab -->
  <div id="tab-users" class="tab-panel" style="display:<?= $tab==='users'?'':'none' ?>">
    <div class="section-title">All Users</div>
    <div class="card" style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Notes</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): $isMe = $u['id'] == current_user()['id']; ?>
          <tr>
            <td>
              <strong style="font-family:var(--mono);font-size:.85rem;">@<?= h($u['username']) ?></strong>
              <?php if ($isMe): ?> <span class="badge badge-blue">you</span><?php endif; ?>
            </td>
            <td style="font-size:.82rem;color:var(--muted);"><?= h($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['role']==='admin'?'badge-yellow':'badge-blue' ?>"><?= h($u['role']) ?></span>
            </td>
            <td>
              <span class="badge <?= $u['is_active']?'badge-green':'badge-red' ?>"><?= $u['is_active']?'active':'suspended' ?></span>
            </td>
            <td style="font-family:var(--mono);font-size:.85rem;"><?= number_format($u['note_count']) ?></td>
            <td style="font-family:var(--mono);font-size:.78rem;color:var(--muted);"><?= time_ago($u['created_at']) ?></td>
            <td>
              <?php if (!$isMe): ?>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <!-- Toggle active -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-xs" onclick="return confirm('Toggle user status?')">
                    <?= $u['is_active'] ? '🚫 Suspend' : '✅ Activate' ?>
                  </button>
                </form>
                <!-- Set role -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="set_role">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <select name="role" onchange="this.form.submit()" style="background:var(--bg);border:1px solid var(--border);border-radius:4px;color:var(--text);font-size:.75rem;padding:3px 6px;">
                    <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>user</option>
                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
                  </select>
                </form>
                <!-- Delete -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Delete user @<?= h($u['username']) ?> and ALL their notes?')">🗑</button>
                </form>
              </div>
              <?php else: ?><span style="font-size:.75rem;color:var(--muted);">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Notes Tab -->
  <div id="tab-notes" class="tab-panel" style="display:<?= $tab==='notes'?'':'none' ?>">
    <div class="section-title">All Notes (latest 100)</div>
    <div class="card" style="overflow-x:auto;">
      <table class="data-table">
        <thead><tr><th>Title</th><th>Author</th><th>Syntax</th><th>Visibility</th><th>Views</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($notes_list as $n): ?>
          <tr>
            <td>
              <a href="<?= h(note_url($n)) ?>" style="color:var(--text);font-size:.875rem;" class="truncate" target="_blank">
                <?= h($n['title'] ?: 'Untitled') ?>
              </a>
            </td>
            <td style="font-family:var(--mono);font-size:.78rem;color:var(--muted);">@<?= h($n['username']) ?></td>
            <td><span class="badge badge-blue"><?= h($n['syntax']) ?></span></td>
            <td><?= $n['is_public'] ? '<span class="badge badge-green">public</span>' : '<span class="badge badge-red">private</span>' ?></td>
            <td style="font-family:var(--mono);font-size:.82rem;"><?= number_format($n['views']) ?></td>
            <td style="font-family:var(--mono);font-size:.75rem;color:var(--muted);"><?= time_ago($n['created_at']) ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="delete_note">
                <input type="hidden" name="note_id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Delete this note?')">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</main>

<script>
function switchTab(name) {
  document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).style.display = '';
  event.target.classList.add('active');
  history.replaceState(null, '', '?tab=' + name);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
