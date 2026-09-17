<?php
declare(strict_types=1);

require_once __DIR__ . '/icons.php';

$user = current_user();
$authLayout = $authLayout ?? false;
$pageTitle = $pageTitle ?? 'GymPro';
$pageSubtitle = $pageSubtitle ?? null;

$navigation = match ($user['Role'] ?? '') {
    'ADMIN' => [
        ['Dashboard', 'admin/index.php', 'grid'],
        ['Members', 'admin/members.php', 'users'],
        ['Plans', 'admin/plans.php', 'card'],
        ['Trainer requests', 'admin/trainers.php', 'trainer'],
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
        ['Membership', 'member/membership.php', 'card'],
        ['Classes', 'member/classes.php', 'calendar'],
        ['Attendance', 'member/attendance.php', 'history'],
    ],
    default => [],
};

$profileName = $user ? ucfirst(strtolower((string) $user['Role'])) : '';
if ($user && in_array($user['Role'], ['MEMBER', 'TRAINER'], true)) {
    $table = $user['Role'] === 'MEMBER' ? 'MEMBER' : 'TRAINER';
    $profile = db_one("SELECT FirstName,LastName FROM {$table} WHERE UserAccountID=?", 'i', [(int) $user['UserAccountID']]);
    if ($profile) {
        $profileName = trim($profile['FirstName'] . ' ' . $profile['LastName']);
    }
}
$parts = preg_split('/\s+/', $profileName) ?: [];
$initials = $profileName !== '' ? implode('', array_map(static fn ($part) => strtoupper(substr($part, 0, 1)), array_slice($parts, 0, 2))) : 'GP';
$flashMessages = get_flash();
$cssFile = V3_ROOT . '/assets/css/app.css';
?><!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8fafc">
  <title><?= e($pageTitle) ?> · GymPro</title>
  <script>(function(){var k='gympro-v3-theme',p='system';try{p=localStorage.getItem(k)||'system'}catch(e){}if(!['light','dark','system'].includes(p))p='system';var d=p==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):p;var r=document.documentElement;r.dataset.theme=d;r.dataset.themePreference=p;r.classList.toggle('dark',d==='dark');r.style.colorScheme=d;})();</script>
  <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css?v=' . filemtime($cssFile))) ?>">
</head>
<body class="<?= $authLayout ? 'auth-layout' : ($user ? 'app-layout' : 'public-layout') ?>">
<?= v3_icon_sprite() ?>
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php if (!$authLayout && $user): ?>
<div class="app-shell">
  <aside class="sidebar" id="primary-navigation" aria-label="Primary navigation">
    <div class="sidebar-inner">
      <a class="brand" href="<?= e(base_url(account_home($user))) ?>" aria-label="GymPro dashboard">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span><span>Gym<span>Pro</span></span>
      </a>
      <div class="workspace-label"><span>Workspace</span><strong><?= e(ucfirst(strtolower((string) $user['Role']))) ?></strong></div>
      <nav class="sidebar-nav"><ul>
      <?php foreach ($navigation as [$label, $path, $icon]): $active = nav_is_active($path); ?>
        <li><a href="<?= e(base_url($path)) ?>"<?= $active ? ' class="active" aria-current="page"' : '' ?>><?= v3_icon($icon) ?><span><?= e($label) ?></span></a></li>
      <?php endforeach; ?>
      </ul></nav>
      <div class="sidebar-foot">
        <div class="account-chip"><span class="avatar" aria-hidden="true"><?= e($initials) ?></span><span><strong><?= e($profileName) ?></strong><small><?= e($user['Email']) ?></small></span></div>
        <form action="<?= e(base_url('auth/logout.php')) ?>" method="post"><?= csrf_field() ?><button class="signout-button" type="submit" aria-label="Sign out"><?= v3_icon('log-out') ?></button></form>
      </div>
    </div>
  </aside>
  <button class="nav-backdrop" type="button" data-nav-close aria-label="Close navigation"></button>
  <div class="app-main">
    <header class="topbar">
      <button class="icon-button menu-button" type="button" data-nav-toggle aria-controls="primary-navigation" aria-expanded="false"><?= v3_icon('menu') ?><span class="visually-hidden">Open navigation</span></button>
      <div class="topbar-title"><h1><?= e($pageTitle) ?></h1><?php if ($pageSubtitle): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?></div>
      <div class="topbar-actions">
        <span class="date-chip"><?= e(date('D, j M')) ?></span>
        <div class="theme-menu" data-theme-menu>
          <button class="icon-button" id="appearance-menu-button" type="button" data-dropdown-toggle="appearance-menu" data-dropdown-placement="bottom-end" aria-label="Choose appearance"><?= v3_icon('sun') ?></button>
          <div class="theme-options hidden" id="appearance-menu" role="menu" aria-labelledby="appearance-menu-button">
            <?php foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $value => $label): ?><button type="button" role="menuitemradio" data-theme-value="<?= $value ?>"><?= e($label) ?></button><?php endforeach; ?>
          </div>
        </div>
        <span class="header-avatar" aria-label="Signed in as <?= e($profileName) ?>"><?= e($initials) ?></span>
      </div>
    </header>
    <main id="main-content" class="page-content" tabindex="-1">
<?php else: ?>
<main id="main-content" class="<?= $authLayout ? 'auth-main' : 'public-main' ?>" tabindex="-1">
  <div class="public-theme"><button class="theme-cycle" type="button" data-theme-cycle aria-label="Change appearance"><?= v3_icon('sun') ?><span data-theme-label>System</span></button></div>
<?php endif; ?>
<?php if ($flashMessages): ?><div class="toast-region" aria-label="Notifications"><?php endif; ?>
<?php foreach ($flashMessages as $message): $messageType = (string) ($message['type'] ?? 'info'); ?>
  <div class="alert alert-<?= e($messageType) ?> toast" role="<?= $messageType === 'danger' ? 'alert' : 'status' ?>" aria-atomic="true" data-alert><span><?= e($message['message'] ?? '') ?></span><button type="button" data-dismiss aria-label="Dismiss message">×</button></div>
<?php endforeach; ?>
<?php if ($flashMessages): ?></div><?php endif; ?>
