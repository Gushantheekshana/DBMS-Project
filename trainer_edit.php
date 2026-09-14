<?php
require_once 'config.php';
$pageTitle='Edit Trainer';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$t=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM TRAINER WHERE TrainerID=$id"));
if (!$t){set_flash('danger','Trainer not found.');header('Location:trainers.php');exit;}
$errors=[];$vals=$t;

if ($_SERVER['REQUEST_METHOD']==='POST'){
    foreach(['Name','PhoneNo','Email','Experience'] as $k) $vals[$k]=sanitize($conn,$_POST[$k]??'');
    if (!$vals['Name']) $errors[]='Name is required.';
    if (empty($errors)){
        $exp=$vals['Experience']?(int)$vals['Experience']:'NULL';
        mysqli_query($conn,"UPDATE TRAINER SET Name='{$vals['Name']}',PhoneNo='{$vals['PhoneNo']}',Email='{$vals['Email']}',Experience=$exp WHERE TrainerID=$id");
        set_flash('success','Trainer updated.');header('Location:trainers.php');exit;
    }
}
include 'includes/header.php';
?>
<?php if ($errors): ?>
<div class="alert alert-danger"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?><button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button></div>
<?php endif; ?>
<div class="form-card">
<form method="POST">
    <div class="form-grid">
        <div class="form-group form-full">
            <label>Full Name *</label>
            <input type="text" name="Name" value="<?= htmlspecialchars($vals['Name']) ?>" required>
        </div>
        <div class="form-group">
            <label>Phone No.</label>
            <input type="text" name="PhoneNo" value="<?= htmlspecialchars($vals['PhoneNo']) ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="Email" value="<?= htmlspecialchars($vals['Email']) ?>">
        </div>
        <div class="form-group">
            <label>Years of Experience</label>
            <input type="number" name="Experience" min="0" value="<?= htmlspecialchars($vals['Experience']) ?>">
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Update Trainer</button>
        <a href="trainers.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
