<?php
declare(strict_types=1);

$user = current_user();
$authLayout = $authLayout ?? false;
$pageTitle = $pageTitle ?? 'GymPro';
$pageSubtitle = $pageSubtitle ?? null;

$navigation = match ($user['Role'] ?? '') {
    'ADMIN' => [
        ['Dashboard', 'admin/index.php', 'grid'],
        ['Members', 'admin/members.php', 'users'],
        ['Plans', 'admin/plans.php', 'ticket'],
        ['Trainer requests', 'admin/trainers.php', 'coach'],
        ['Classes', 'admin/classes.php', 'calendar'],
        ['Payments', 'admin/payments.php', 'wallet'],
        ['Staff', 'admin/staff.php', 'staff'],
    ],
    'RECEPTIONIST' => [
        ['Check in', 'reception/index.php', 'scan'],
        ['Visit history', 'reception/history.php', 'history'],
    ],
    'TRAINER' => [
        ['Dashboard', 'trainer/index.php', 'grid'],
        ['My classes', 'trainer/classes.php', 'calendar'],
        ['Enrollments', 'trainer/enrollments.php', 'users'],
        ['Attendance', 'trainer/attendance.php', 'check'],
    ],
    'MEMBER' => [
        ['Dashboard', 'member/index.php', 'grid'],
        ['Membership', 'member/membership.php', 'ticket'],
        ['Classes', 'member/classes.php', 'calendar'],
        ['Attendance', 'member/attendance.php', 'history'],
    ],
    default => [],
};

$icons = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'ticket' => '<path d="M2 9a3 3 0 0 0 0 6v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a3 3 0 0 0 0-6V5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><path d="M13 5v2M13 17v2M13 11v2"/>',
    'coach' => '<circle cx="12" cy="7" r="4"/><path d="M5.5 21a6.5 6.5 0 0 1 13 0M4 12h3M17 12h3M2 10v4M22 10v4"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
    'wallet' => '<path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v12H5a3 3 0 0 1-3-3V6"/><path d="M16 13h4"/>',
    'staff' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0M18 3l1 1 2-2"/>',
    'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
    'check' => '<path d="M20 6 9 17l-5-5"/>',
];

function ui_icon(string $name, array $icons): string
{
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$name] ?? $icons['grid']) . '</svg>';
}

$profileName = $user ? ucfirst(strtolower((string) $user['Role'])) : '';
if ($user && in_array($user['Role'], ['MEMBER', 'TRAINER'], true)) {
    $table = $user['Role'] === 'MEMBER' ? 'MEMBER' : 'TRAINER';
    $profile = db_one("SELECT FirstName,LastName FROM {$table} WHERE UserAccountID=?", 'i', [(int) $user['UserAccountID']]);
    if ($profile) $profileName = trim($profile['FirstName'] . ' ' . $profile['LastName']);
}
$initials = $profileName !== '' ? implode('', array_map(static fn ($part) => strtoupper(substr($part, 0, 1)), array_slice(preg_split('/\s+/', $profileName) ?: [], 0, 2))) : 'GP';
$flashMessages = get_flash();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#112b26">
  <title><?= e($pageTitle) ?> · GymPro</title>
  <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css?v=' . filemtime(V2_ROOT . '/assets/css/app.css'))) ?>">
</head>
<body class="<?= $authLayout ? 'auth-layout' : ($user ? 'app-layout' : 'public-layout') ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php if (!$authLayout && $user): ?>
<div class="app-shell">
  <aside class="sidebar" id="primary-navigation" aria-label="Primary navigation">
    <a class="brand" href="<?= e(base_url(account_home($user))) ?>" aria-label="GymPro dashboard">
      <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
      <span>GymPro</span>
    </a>
    <nav class="sidebar-nav">
      <p class="nav-label">Workspace</p>
      <ul>
      <?php foreach ($navigation as [$label, $path, $icon]): $active = nav_is_active($path); ?>
        <li><a href="<?= e(base_url($path)) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?>><?= ui_icon($icon, $icons) ?><span><?= e($label) ?></span></a></li>
      <?php endforeach; ?>
      </ul>
    </nav>
    <div class="sidebar-foot">
      <div class="account-chip">
        <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
        <span class="account-copy"><strong><?= e($profileName) ?></strong><small><?= e(ucfirst(strtolower((string) $user['Role']))) ?></small></span>
      </div>
      <form action="<?= e(base_url('auth/logout.php')) ?>" method="post">
        <?= csrf_field() ?>
        <button class="signout-button" type="submit"><?= ui_icon('history', $icons) ?><span>Sign out</span></button>
      </form>
    </div>
  </aside>
  <button class="nav-backdrop" type="button" data-nav-close aria-label="Close navigation"></button>
  <div class="app-main">
    <header class="topbar">
      <button class="menu-button" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false"><span></span><span></span><span></span><span class="sr-only">Open navigation</span></button>
      <div class="topbar-title"><h1><?= e($pageTitle) ?></h1><?php if ($pageSubtitle): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?></div>
      <div class="topbar-meta"><span class="live-dot" aria-hidden="true"></span><span><?= e(date('D, j M')) ?></span></div>
    </header>
    <main id="main-content" class="page-content" tabindex="-1">
<?php else: ?>
<main id="main-content" class="<?= $authLayout ? 'auth-main' : 'public-main' ?>" tabindex="-1">
<?php endif; ?>
<?php if ($flashMessages): ?><div class="toast-region" aria-label="Notifications"><?php endif; ?>
<?php foreach ($flashMessages as $message): $messageType = (string) ($message['type'] ?? 'info'); ?>
  <div class="alert alert-<?= e($messageType) ?> toast" role="<?= $messageType === 'danger' ? 'alert' : 'status' ?>" aria-atomic="true" data-alert><span><?= e($message['message'] ?? '') ?></span><button type="button" data-dismiss aria-label="Dismiss message">×</button></div>
<?php endforeach; ?>
<?php if ($flashMessages): ?></div><?php endif; ?>
