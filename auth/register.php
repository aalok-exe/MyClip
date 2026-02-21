<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

boot_session();
if (current_user()) { $_u = current_user(); redirect(profile_url($_u['username'])); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $pass     = $_POST['password']      ?? '';
    $pass2    = $_POST['password2']     ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,40}$/', $username))
        $errors[] = 'Username must be 3–40 characters (letters, numbers, underscore).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';
    if (strlen($pass) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($pass !== $pass2)
        $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $s = db()->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $s->execute([$username, $email]);
        if ($s->fetch()) {
            $errors[] = 'Username or email is already taken.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')->execute([$username, $email, $hash]);
            flash_set('success', 'Account created! Please sign in.');
            redirect(url('auth/login.php'));
        }
    }
}

$page_title = 'Register — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
.auth-wrap{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 120px);padding:24px;}
.auth-box{background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:28px 24px;width:100%;max-width:420px;box-shadow:0 16px 60px rgba(0,0,0,.4);}
.auth-title{font-size:1rem;font-weight:600;margin-bottom:4px;}
.auth-sub{color:var(--muted);font-size:.875rem;margin-bottom:28px;}
.auth-footer{text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted);}
.err-list{list-style:none;padding:10px 14px;background:rgba(240,96,96,.1);border-left:3px solid var(--danger);border-radius:var(--r);margin-bottom:16px;color:var(--danger);font-size:.85rem;display:flex;flex-direction:column;gap:4px;}
</style>
<main>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-title">Create account</div>
    <div class="auth-sub">Join <?= APP_NAME ?> — free, no nonsense.</div>

    <?php if ($errors): ?>
      <ul class="err-list">
        <?php foreach ($errors as $e): ?><li>⚠ <?= h($e) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username</label>
        <input class="form-control" type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus autocomplete="username" maxlength="40" pattern="[a-zA-Z0-9_]{3,40}">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input class="form-control" type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>
      <div class="form-group">
        <label>Password <span style="color:var(--muted);font-size:.75rem;">(min 8 chars)</span></label>
        <input class="form-control" type="password" name="password" required autocomplete="new-password" minlength="8">
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input class="form-control" type="password" name="password2" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:6px;">Create Account</button>
    </form>

    <div class="auth-footer">Already have an account? <a href="<?= url('auth/login.php') ?>">Sign in</a></div>
  </div>
</div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
