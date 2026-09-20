<?php
/**
 * Shared controller/template for fixed-role sign-in routes.
 * The including route must define $loginRole, $loginTitle and $loginDescription.
 */
if (!isset($loginRole, $loginTitle, $loginDescription)) {
    http_response_code(500);
    exit('Sign-in portal is not configured.');
}

require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\Services\AuthService;

require_guest();
$allowedRoles = ['MEMBER', 'TRAINER', 'RECEPTIONIST', 'SUPER_ADMIN'];
if (!in_array($loginRole, $allowedRoles, true)) {
    http_response_code(500);
    exit('Sign-in portal is not configured.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $user = AuthService::login(
            (string)($_POST['email'] ?? ''),
            (string)($_POST['password'] ?? ''),
            $loginRole
        );
        clear_old_input();
        redirect(account_home($user));
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$demoAccounts = [
    'MEMBER' => 'member.demo@gmail.com',
    'TRAINER' => 'trainer.demo@gmail.com',
    'RECEPTIONIST' => 'reception.demo@gmail.com',
    'SUPER_ADMIN' => 'admin.demo@gmail.com',
];
$pageTitle = $loginTitle;
$authLayout = true;
include ROOT_PATH . '/includes/header.php';
?>
<div class="auth-card role-login-card">
 <div class="auth-brand"><span class="brand-icon">&#9651;</span><strong>GymPro</strong><span class="badge badge-blue"><?= e(str_replace('_', ' ', $loginRole)) ?></span></div>
 <h1><?= e($loginTitle) ?></h1><p class="page-subtitle"><?= e($loginDescription) ?></p>
 <?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
 <form method="post" novalidate><?= csrf_field() ?>
  <div class="form-group"><label for="email">Gmail address</label><input id="email" type="email" name="email" autocomplete="username" required value="<?= e($_POST['email'] ?? '') ?>"></div>
  <div class="form-group"><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
  <button class="btn btn-primary btn-block" type="submit">Sign in as <?= e(strtolower(str_replace('_', ' ', $loginRole))) ?></button>
 </form>
 <div class="auth-links"><a href="login.php">Choose another role</a><?php if ($loginRole === 'MEMBER'): ?><a href="register-member.php">Create member account</a><?php elseif ($loginRole === 'TRAINER'): ?><a href="register-trainer.php">Apply as trainer</a><?php endif; ?></div>
 <?php if (env('APP_ENV', 'production') === 'local'): ?><div class="demo-note"><strong>Local <?= e(strtolower(str_replace('_', ' ', $loginRole))) ?> demo</strong><br><code><?= e($demoAccounts[$loginRole]) ?></code><br>Password: <code>Demo@12345</code></div><?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
