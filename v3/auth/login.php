<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\AuthService;

require_guest();
$error = '';
$selectedRole = (string) ($_POST['role'] ?? $_GET['role'] ?? 'MEMBER');
$roles = ['MEMBER' => 'Member', 'TRAINER' => 'Trainer', 'RECEPTIONIST' => 'Reception', 'ADMIN' => 'Admin'];
if (!isset($roles[$selectedRole])) $selectedRole = 'MEMBER';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $user = AuthService::login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), $selectedRole);
        clear_old_input();
        flash('success', 'Welcome back.');
        redirect(account_home($user));
    } catch (Throwable $exception) {
        flash_input($_POST);
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Sign in';
$authLayout = true;
include V3_ROOT . '/includes/header.php';
?>
<div class="auth-wrap compact">
  <a class="brand auth-home" href="<?= e(base_url()) ?>"><span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span><span>GymPro</span></a>
  <section class="auth-card" aria-labelledby="login-title">
    <div class="auth-card-inner">
      <h1 id="login-title">Welcome back</h1>
      <p class="auth-intro">Choose your workspace and enter your account details.</p>
      <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
      <form class="auth-form" method="post" novalidate>
        <?= csrf_field() ?>
        <fieldset><legend class="sr-only">Account role</legend>
          <div class="role-picker">
          <?php foreach ($roles as $value => $label): ?>
            <label><input type="radio" name="role" value="<?= e($value) ?>"<?= $selectedRole === $value ? ' checked' : '' ?>><span class="role-option"><?= e($label) ?></span></label>
          <?php endforeach; ?>
          </div>
        </fieldset>
        <label for="email">Email address
          <input id="email" name="email" type="email" value="<?= old('email') ?>" autocomplete="email" inputmode="email" required autofocus placeholder="you@example.com">
        </label>
        <label for="password">Password
          <span class="password-row"><input id="password" name="password" type="password" autocomplete="current-password" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">Show</button></span>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
      </form>
      <div class="auth-links"><span>New to GymPro?</span><span><a href="<?= e(base_url('auth/register-member.php')) ?>">Join as member</a> or <a href="<?= e(base_url('auth/register-trainer.php')) ?>">trainer</a></span></div>
    </div>
  </section>
</div>
<?php include V3_ROOT . '/includes/footer.php'; ?>
