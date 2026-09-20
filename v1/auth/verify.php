<?php
require_once __DIR__.'/../bootstrap/app.php';
use GymPro\Services\AuthService;
$destination = 'auth/login.php';
try {
    $row = AuthService::verifyEmail((string)($_GET['token'] ?? ''));
    $destination = $row['Role'] === 'MEMBER' ? 'auth/member-login.php' : 'auth/trainer-login.php';
    set_flash('success', $row['Role'] === 'MEMBER'
        ? 'Email verified. Sign in to select your membership plan.'
        : 'Email verified. Your application is awaiting admin review.');
} catch (Throwable $e) {
    set_flash('danger', $e->getMessage());
}
redirect($destination);
