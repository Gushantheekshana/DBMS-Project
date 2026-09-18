<?php
require_once __DIR__.'/../bootstrap/app.php';
use GymPro\Services\AuthService;
require_guest(); $errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{AuthService::registerMember($_POST);set_flash('success','Account created. Check your Gmail inbox for the verification link.');redirect('auth/member-login.php');}
 catch(Throwable $e){$errors[]=$e->getMessage();}
}
$pageTitle='Member registration';$authLayout=true;include ROOT_PATH.'/includes/header.php';
?>
<div class="auth-card auth-card-wide"><div class="auth-brand"><span class="brand-icon">&#9651;</span><strong>GymPro</strong></div><h1>Create member account</h1><p class="page-subtitle">Verify your Gmail address, then choose and pay for a plan within 72 hours.</p>
<?php if($errors):?><div class="alert alert-danger" role="alert"><?=e(implode(' ',$errors))?></div><?php endif;?>
<form method="post" novalidate><?=csrf_field()?><div class="form-grid">
<div class="form-group"><label for="name">Full name *</label><input id="name" name="name" required value="<?=e($_POST['name']??'')?>"></div>
<div class="form-group"><label for="nic">NIC *</label><input id="nic" name="nic" required value="<?=e($_POST['nic']??'')?>"></div>
<div class="form-group"><label for="email">Gmail address *</label><input id="email" type="email" name="email" required autocomplete="email" value="<?=e($_POST['email']??'')?>"></div>
<div class="form-group"><label for="phone">Phone *</label><input id="phone" name="phone" required value="<?=e($_POST['phone']??'')?>"></div>
<div class="form-group"><label for="dob">Date of birth</label><input id="dob" type="date" name="dob" max="<?=date('Y-m-d')?>" value="<?=e($_POST['dob']??'')?>"></div>
<div class="form-group"><label for="gender">Gender</label><select id="gender" name="gender"><option value="">Prefer not to say</option><option>Male</option><option>Female</option><option>Other</option></select></div>
<div class="form-group form-full"><label for="address">Address</label><input id="address" name="address" value="<?=e($_POST['address']??'')?>"></div>
<div class="form-group form-full"><label for="password">Password *</label><input id="password" type="password" name="password" minlength="10" required autocomplete="new-password"><small>At least 10 characters.</small></div>
</div><div class="form-actions"><button class="btn btn-primary" type="submit">Create account</button><a class="btn btn-ghost" href="member-login.php">Back to member sign in</a></div></form></div>
<?php include ROOT_PATH.'/includes/footer.php';?>
