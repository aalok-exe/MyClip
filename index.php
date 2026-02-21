<?php
/**
 * index.php — Site root.
 *
 * Logged-in users  → redirect to their pad (website/username)
 * Visitors         → simple landing page with sign-in prompt
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
boot_session();

$me = current_user();
if ($me) {
    redirect(profile_url($me['username']));
}

$page_title = APP_NAME . ' — Live notepads';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.landing {
  width: min(92vw, 560px);
  margin: 0 auto;
  padding: 80px clamp(16px,2.5vw,32px) 56px;
}
.landing-eyebrow {
  font-family: var(--mono); font-size: .72rem; color: var(--muted);
  text-transform: uppercase; letter-spacing: .6px; margin-bottom: 18px;
}
.landing-title {
  font-size: clamp(1.6rem, 4vw, 2.4rem); font-weight: 600;
  letter-spacing: -.4px; line-height: 1.2; margin-bottom: 14px;
}
.landing-sub {
  font-size: .95rem; color: var(--muted); margin-bottom: 32px;
  line-height: 1.7; max-width: 460px;
}
.landing-cta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 48px; }
.how {
  display: flex; flex-direction: column; gap: 12px;
  border-top: 1px solid var(--border); padding-top: 32px;
}
.how-item { display: flex; gap: 14px; align-items: flex-start; }
.how-num {
  font-family: var(--mono); font-size: .72rem; color: var(--muted);
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 50%; width: 22px; height: 22px; display: flex;
  align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
}
.how-text strong { display: block; font-size: .85rem; font-weight: 500; margin-bottom: 2px; }
.how-text span { font-size: .78rem; color: var(--muted); line-height: 1.5; }
.url-demo {
  font-family: var(--mono); font-size: .82rem;
  color: var(--text2); background: var(--surface);
  border: 1px solid var(--border); border-radius: 4px;
  padding: 2px 8px; display: inline-block; margin-top: 4px;
}
</style>
<main>
  <div class="landing">
    <div class="landing-eyebrow">MYCLIP</div>
    <div class="landing-title">Your clipboard.<br>One URL. Always live.</div>
    <div class="landing-sub">
      Paste code, notes or text — it saves automatically.
      Share your pad URL and anyone can see what you paste, in real time.
    </div>
    <div class="landing-cta">
      <a href="<?= url('auth/register.php') ?>" class="btn btn-primary">Share your text free</a>
      <a href="<?= url('auth/login.php') ?>" class="btn btn-outline">Sign in</a>
    </div>

    <div class="how">
      <div class="how-item">
        <div class="how-num">1</div>
        <div class="how-text">
          <strong>Register</strong>
          <span>Pick a username. Your pad URL is reserved instantly.</span>
        </div>
      </div>
      <div class="how-item">
        <div class="how-num">2</div>
        <div class="how-text">
          <strong>Paste anything</strong>
          <span>Open your pad and type or paste — it auto-saves as you go. No buttons.</span>
          <div class="url-demo"><?= h(APP_URL) ?>/yourname</div>
        </div>
      </div>
      <div class="how-item">
        <div class="how-num">3</div>
        <div class="how-text">
          <strong>Share the URL</strong>
          <span>Anyone who visits your URL sees your latest content, live. Save named snapshots anytime.</span>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
