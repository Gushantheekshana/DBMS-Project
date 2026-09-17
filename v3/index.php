<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap/app.php';

$user = current_user();
if ($user !== null) {
    redirect(account_home($user));
}

$pageTitle = 'Run your gym with clarity';
include V3_ROOT . '/includes/header.php';
?>
<nav class="public-nav" aria-label="Public navigation">
  <a class="brand" href="<?= e(base_url()) ?>" aria-label="GymPro home">
    <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span><span>GymPro</span>
  </a>
  <div class="public-actions">
    <a class="btn btn-secondary" href="<?= e(base_url('auth/register-trainer.php')) ?>">Join as trainer</a>
    <a class="btn btn-primary" href="<?= e(base_url('auth/login.php')) ?>">Sign in</a>
  </div>
</nav>
<section class="landing-hero" aria-labelledby="hero-title">
  <div class="hero-copy">
    <h1 id="hero-title">Every workout. One connected gym.</h1>
    <p>Manage memberships, classes, attendance, and daily operations without losing sight of the people on the floor.</p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="<?= e(base_url('auth/register-member.php')) ?>">Become a member</a>
      <a class="btn btn-secondary" href="<?= e(base_url('auth/login.php')) ?>">Open your workspace</a>
    </div>
    <ul class="hero-proof" aria-label="GymPro features">
      <li>Fast check-in</li><li>Live class schedules</li><li>Clear membership status</li>
    </ul>
  </div>
  <div class="hero-visual" aria-hidden="true">
    <div class="track-lines"></div>
    <article class="hero-pass">
      <div class="pass-head"><span class="brand"><span class="brand-mark"><i></i><i></i><i></i></span>GymPro</span><span class="pass-status">Active member</span></div>
      <h2>Ready when you are.</h2>
      <p>Your membership, classes, and visits stay in sync.</p>
      <div class="pass-code"><?php for ($i = 0; $i < 16; $i++): ?><i></i><?php endfor; ?></div>
    </article>
  </div>
</section>
<?php include V3_ROOT . '/includes/footer.php'; ?>
