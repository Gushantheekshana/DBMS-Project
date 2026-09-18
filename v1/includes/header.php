<?php
use GymPro\Services\MessageService;

$user = current_user();
$authLayout = $authLayout ?? false;
$script = basename($_SERVER['PHP_SELF']);
$unread = $user ? (int)(db_one('SELECT COUNT(*) n FROM NOTIFICATION_RECIPIENT WHERE UserID=? AND ReadAt IS NULL','i',[$user['UserID']])['n'] ?? 0) : 0;
$messageUnread = $user && in_array($user['Role'], ['MEMBER','TRAINER','SUPER_ADMIN'], true) && db_one("SELECT 1 found FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='CONVERSATION' LIMIT 1") ? MessageService::unreadCount($user) : 0;
$nav = [];
if ($user) {
    $home = account_home($user);
    $nav[]=['Dashboard',$home,'index.php'];
    $nav[]=['Notifications','notifications.php','notifications.php'];
    if(is_role('MEMBER','TRAINER','SUPER_ADMIN')) $nav[]=['Messages','messages.php','messages.php'];
    if(is_role('MEMBER')) {$nav[]=['Membership','member/membership.php','membership.php'];$nav[]=['Classes','member/classes.php','classes.php'];$nav[]=['Attendance','member/attendance.php','attendance.php'];}
    if(is_role('TRAINER')) {$nav[]=['My Classes','trainer/classes.php','classes.php'];$nav[]=['Enrollment Requests','trainer/enrollments.php','enrollments.php'];$nav[]=['Class Attendance','trainer/attendance.php','attendance.php'];$nav[]=['Commissions','trainer/commissions.php','commissions.php'];}
    if(is_role('RECEPTIONIST')) {$nav[]=['Reception Check-in','reception/index.php','index.php'];$nav[]=['Visit History','reception/history.php','history.php'];}
    if(is_role('SUPER_ADMIN')) {$nav=array_merge($nav,[['Members','members.php','members.php'],['Plans','plans.php','plans.php'],['Trainer Applications','admin/trainers.php','trainers.php'],['Classes','admin/classes.php','classes.php'],['Payments','payments.php','payments.php'],['Payouts','admin/payouts.php','payouts.php'],['Staff','admin/staff.php','staff.php'],['Audit Log','admin/audit.php','audit.php']]);}
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=e($pageTitle??'GymPro')?> — GymPro</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="<?=e(base_url('css/style.css'))?>"></head>
<body class="<?=$authLayout?'auth-page':''?>"><a class="skip-link" href="#main-content">Skip to content</a>
<?php if(!$authLayout && $user):?><button class="mobile-menu" type="button" data-menu aria-label="Open navigation" aria-expanded="false">☰</button><nav class="sidebar" aria-label="Primary"><div class="sidebar-brand"><span class="brand-icon">&#9651;</span><span class="brand-name">GymPro</span></div><ul class="nav-links">
<?php foreach($nav as [$label,$href,$active]):?><li><a href="<?=e(base_url($href))?>" <?=$script===$active?'class="active" aria-current="page"':''?>><span class="nav-dot" aria-hidden="true"></span><?=e($label)?><?=$label==='Notifications'&&$unread?'<span class="nav-count">'.$unread.'</span>':''?><?=$label==='Messages'&&$messageUnread?'<span class="nav-count">'.$messageUnread.'</span>':''?></a></li><?php endforeach;?>
</ul><div class="sidebar-user"><span><?=e($user['Email'])?></span><small><?=e(str_replace('_',' ',$user['Role']))?></small><form method="post" action="<?=e(base_url('auth/logout.php'))?>"><?=csrf_field()?><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form></div></nav><?php endif;?>
<main id="main-content" class="<?=$authLayout?'auth-main':'main-content'?>">
<?php if(!$authLayout && $user):?><div class="topbar"><div class="page-heading"><h1><?=e($pageTitle??'Dashboard')?></h1><?php if(isset($pageSubtitle)):?><p class="page-subtitle"><?=e($pageSubtitle)?></p><?php endif;?></div><div class="topbar-right"><span class="today-date"><?=date('D, d M Y')?></span></div></div><?php endif;?>
<?php $flash=get_flash();if($flash):?><div class="alert alert-<?=e($flash['type'])?>" role="status"><span><?=e($flash['msg'])?></span><button class="alert-close" type="button" data-dismiss aria-label="Dismiss message">&#x2715;</button></div><?php endif;?>
<div class="content-body">
