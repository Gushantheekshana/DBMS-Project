<?php
require_once __DIR__.'/../bootstrap/app.php';
use GymPro\Services\AuthService;
require_guest();$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();try{AuthService::registerTrainer($_POST,$_FILES['document']??[]);set_flash('success','Application created. Verify your Gmail address; an admin will then review it.');redirect('auth/trainer-login.php');}catch(Throwable $e){$errors[]=$e->getMessage();}
}
$pageTitle='Trainer application';$authLayout=true;include ROOT_PATH.'/includes/header.php';
?>
<div class="auth-card auth-card-wide"><div class="auth-brand"><span class="brand-icon">&#9651;</span><strong>GymPro</strong></div><h1>Apply as a trainer</h1><p class="page-subtitle">Your qualification document is privately reviewed by the super admin.</p>
<?php if($errors):?><div class="alert alert-danger" role="alert"><?=e(implode(' ',$errors))?></div><?php endif;?>
<form method="post" enctype="multipart/form-data" novalidate><?=csrf_field()?><div class="form-grid">
<div class="form-group"><label for="name">Full name *</label><input id="name" name="name" required value="<?=e($_POST['name']??'')?>"></div>
<div class="form-group"><label for="email">Gmail address *</label><input id="email" type="email" name="email" required value="<?=e($_POST['email']??'')?>"></div>
<div class="form-group"><label for="phone">Phone *</label><input id="phone" name="phone" required value="<?=e($_POST['phone']??'')?>"></div>
<div class="form-group"><label for="experience">Experience (years)</label><input id="experience" type="number" name="experience" min="0" max="60" value="<?=e($_POST['experience']??0)?>"></div>
<div class="form-group form-full"><label for="specialization">Specialization *</label><input id="specialization" name="specialization" required value="<?=e($_POST['specialization']??'')?>"></div>
<div class="form-group form-full"><label for="bio">Professional bio</label><textarea id="bio" name="bio"><?=e($_POST['bio']??'')?></textarea></div>
<div class="form-group"><label for="document">Qualification document *</label><input id="document" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required><small>PDF, JPG, or PNG; maximum 5 MB.</small></div>
<div class="form-group"><label for="password">Password *</label><input id="password" type="password" name="password" minlength="10" required autocomplete="new-password"></div>
</div><div class="form-actions"><button class="btn btn-primary" type="submit">Submit application</button><a class="btn btn-ghost" href="trainer-login.php">Back to trainer sign in</a></div></form></div>
<?php include ROOT_PATH.'/includes/footer.php';?>
