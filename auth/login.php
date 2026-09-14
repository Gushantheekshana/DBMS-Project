<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_guest();

$pageTitle = 'Choose sign-in portal';
$authLayout = true;
include ROOT_PATH . '/includes/header.php';

$portals = [
    [
        'role' => 'Member',
        'href' => 'member-login.php',
        'description' => 'Membership, classes, attendance, and messages.',
    ],
    [
        'role' => 'Trainer',
        'href' => 'trainer-login.php',
        'description' => 'Class requests, attendance, messages, and commission.',
    ],
    [
        'role' => 'Receptionist',
        'href' => 'receptionist-login.php',
        'description' => 'Member search and gym check-in or check-out.',
    ],
    [
        'role' => 'Super admin',
        'href' => 'admin-login.php',
        'description' => 'Approvals, operations, support, finance, and audits.',
    ],
];
?>
<div class="auth-card auth-card-wide portal-chooser">
 <div class="auth-brand"><span class="brand-icon">&#9651;</span><strong>GymPro</strong></div>
 <h1>Choose your sign-in portal</h1>
 <p class="page-subtitle">Select your account role to continue securely.</p>
 <div class="portal-grid" aria-label="Sign-in portals">
  <?php foreach ($portals as $portal): ?>
   <a class="portal-card" href="<?= e($portal['href']) ?>">
    <span class="badge badge-blue"><?= e($portal['role']) ?></span>
    <strong><?= e($portal['role']) ?> sign in</strong>
    <span><?= e($portal['description']) ?></span>
    <span class="portal-action">Continue <span aria-hidden="true">&rarr;</span></span>
   </a>
  <?php endforeach; ?>
 </div>
 <div class="auth-links"><a href="register-member.php">Create member account</a><a href="register-trainer.php">Apply as trainer</a></div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
