<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\AuthService;

require_guest();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ((string) ($_POST['password'] ?? '') !== (string) ($_POST['password_confirmation'] ?? '')) {
            throw new RuntimeException('Passwords do not match.');
        }
        AuthService::registerMember($_POST);
        clear_old_input();
        flash('success', 'Your member account is ready. Sign in to choose a plan.');
        redirect('auth/login.php?role=MEMBER');
    } catch (Throwable $exception) {
        flash_input($_POST);
        $error = $exception->getMessage();
    }
}
$pageTitle = 'Create a member account';
$authLayout = true;
include V3_ROOT . '/includes/header.php';
?>
<div class="auth-wrap">
  <a class="brand auth-home" href="<?= e(base_url()) ?>"><span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span><span>GymPro</span></a>
  <section class="auth-card auth-grid" aria-labelledby="register-title">
    <aside class="auth-aside"><div><h2>Your training belongs in one place.</h2><p>Book classes, follow your membership, and keep a clear record of every visit.</p></div></aside>
    <div class="auth-card-inner">
      <h1 id="register-title">Join the gym</h1>
      <p class="auth-intro">Create your member profile. You can choose a membership after signing in.</p>
      <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
      <form class="form-grid" method="post" novalidate>
        <?= csrf_field() ?>
        <label for="first_name">First name<input id="first_name" name="first_name" value="<?= old('first_name') ?>" autocomplete="given-name" maxlength="80" required autofocus></label>
        <label for="last_name">Last name<input id="last_name" name="last_name" value="<?= old('last_name') ?>" autocomplete="family-name" maxlength="80" required></label>
        <label for="email">Email address<input id="email" name="email" type="email" value="<?= old('email') ?>" autocomplete="email" maxlength="254" required></label>
        <label for="phone">Phone <span class="field-hint">Optional</span><input id="phone" name="phone" type="tel" value="<?= old('phone') ?>" autocomplete="tel" maxlength="32"></label>
        <label for="date_of_birth">Date of birth <span class="field-hint">Optional</span><input id="date_of_birth" name="date_of_birth" type="date" value="<?= old('date_of_birth') ?>" max="<?= e(date('Y-m-d')) ?>" autocomplete="bday"></label>
        <label for="emergency_contact_name">Emergency contact <span class="field-hint">Optional</span><input id="emergency_contact_name" name="emergency_contact_name" value="<?= old('emergency_contact_name') ?>" maxlength="160"></label>
        <label for="emergency_contact_phone">Emergency phone <span class="field-hint">Optional</span><input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="<?= old('emergency_contact_phone') ?>" maxlength="32"></label>
        <label for="password">Password<span class="password-row"><input id="password" name="password" type="password" minlength="10" maxlength="4096" autocomplete="new-password" aria-describedby="password-hint" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">Show</button></span><span class="field-hint" id="password-hint">Use at least 10 characters.</span></label>
        <label for="password_confirmation">Confirm password<input id="password_confirmation" name="password_confirmation" type="password" minlength="10" maxlength="4096" autocomplete="new-password" required></label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Create member account</button><a class="btn btn-secondary" href="<?= e(base_url('auth/login.php?role=MEMBER')) ?>">I already have an account</a></div>
      </form>
    </div>
  </section>
</div>
<?php include V3_ROOT . '/includes/footer.php'; ?>
