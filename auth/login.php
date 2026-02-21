<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

boot_session();
if (current_user()) { $u = current_user(); redirect(profile_url($u['username'])); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $s = db()->prepare('SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
    $s->execute([$login, $login]);
    $user = $s->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
        ];
        db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
        session_regenerate_id(false);
        $next = !empty($_GET['next']) ? $_GET['next'] : profile_url($user['username']);
        redirect($next);
    } else {
        $error = 'Invalid username / email or password.';
    }
}

$page_title = 'Sign In — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.auth-wrap{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 120px);padding:24px;}
.auth-box{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:28px 24px;width:100%;max-width:400px;box-shadow:0 16px 60px rgba(0,0,0,.4);}
.auth-title{font-size:1rem;font-weight:600;margin-bottom:4px;}
.auth-sub{color:var(--muted);font-size:.875rem;margin-bottom:28px;}
.auth-footer{text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted);}
</style>
<main>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-title">Welcome back</div>
    <div class="auth-sub">Sign in to your <?= APP_NAME ?> account</div>

    <?php if ($error): ?>
      <div class="flash flash-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username or Email</label>
        <input class="form-control" type="text" name="login" value="<?= h($_POST['login'] ?? '') ?>" required autofocus autocomplete="username">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input class="form-control" type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:6px;">Sign In</button>
    </form>

    <div class="auth-footer">Don't have an account? <a href="<?= url('auth/register.php') ?>">Register</a></div>
  </div>
</div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
