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
        AuthService::registerTrainer($_POST);
        clear_old_input();
        flash('success', 'Application submitted. You can sign in after an admin approves it.');
        redirect('auth/login.php?role=TRAINER');
    } catch (Throwable $exception) {
        flash_input($_POST);
        $error = $exception->getMessage();
    }
}
$pageTitle = 'Apply as a trainer';
$authLayout = true;
include V2_ROOT . '/includes/header.php';
?>
<div class="auth-wrap">
  <a class="brand auth-home" href="<?= e(base_url()) ?>"><span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span><span>GymPro</span></a>
  <section class="auth-card auth-grid" aria-labelledby="trainer-title">
    <aside class="auth-aside"><div><h2>Build the room people return to.</h2><p>Plan classes, manage your roster, and record attendance from one focused workspace.</p></div></aside>
    <div class="auth-card-inner">
      <h1 id="trainer-title">Trainer application</h1>
      <p class="auth-intro">Tell us about your practice. An admin must approve your application before you can sign in or create classes. If a previous application was rejected, you can submit revised details with the same email.</p>
      <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><span><?= e($error) ?></span></div><?php endif; ?>
      <form class="form-grid" method="post" novalidate>
        <?= csrf_field() ?>
        <label for="first_name">First name<input id="first_name" name="first_name" value="<?= old('first_name') ?>" autocomplete="given-name" maxlength="80" required autofocus></label>
        <label for="last_name">Last name<input id="last_name" name="last_name" value="<?= old('last_name') ?>" autocomplete="family-name" maxlength="80" required></label>
        <label for="email">Email address<input id="email" name="email" type="email" value="<?= old('email') ?>" autocomplete="email" maxlength="254" required></label>
        <label for="phone">Phone <span class="field-hint">Optional</span><input id="phone" name="phone" type="tel" value="<?= old('phone') ?>" autocomplete="tel" maxlength="32"></label>
        <label class="form-full" for="specialization">Specialization<input id="specialization" name="specialization" value="<?= old('specialization') ?>" maxlength="160" placeholder="For example: strength and conditioning"></label>
        <label class="form-full" for="bio">Professional bio <span class="field-hint">Optional</span><textarea id="bio" name="bio" maxlength="3000" placeholder="Share your coaching approach and experience."><?= old('bio') ?></textarea></label>
        <label for="password">Password<span class="password-row"><input id="password" name="password" type="password" minlength="10" maxlength="4096" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">Show</button></span><span class="field-hint">Use at least 10 characters.</span></label>
        <label for="password_confirmation">Confirm password<input id="password_confirmation" name="password_confirmation" type="password" minlength="10" maxlength="4096" autocomplete="new-password" required></label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Send application</button><a class="btn btn-secondary" href="<?= e(base_url('auth/login.php?role=TRAINER')) ?>">Back to sign in</a></div>
      </form>
    </div>
  </section>
</div>
<?php include V2_ROOT . '/includes/footer.php'; ?>
