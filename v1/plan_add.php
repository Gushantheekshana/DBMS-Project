<?php
require_once 'config.php';
$pageTitle = 'Add Plan';
$errors = [];
$vals = ['PlanName'=>'','PlanDetails'=>'','Duration'=>'','Price'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach ($vals as $k=>$_) $vals[$k] = sanitize($conn, $_POST[$k]??'');
    if (!$vals['PlanName'])  $errors[] = 'Plan name is required.';
    if (!$vals['Duration'] || !is_numeric($vals['Duration']) || $vals['Duration']<1) $errors[] = 'Duration must be a positive number.';
    if (!$vals['Price']    || !is_numeric($vals['Price'])    || $vals['Price']<0)    $errors[] = 'Price must be a valid amount.';

    if (empty($errors)) {
        mysqli_query($conn,"INSERT INTO MEMBERSHIP_PLAN (PlanName,PlanDetails,Duration,Price)
            VALUES ('{$vals['PlanName']}','{$vals['PlanDetails']}',{$vals['Duration']},{$vals['Price']})");
        set_flash('success','Plan created.'); header('Location:plans.php'); exit;
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
            <label>Plan Name *</label>
            <input type="text" name="PlanName" value="<?= htmlspecialchars($vals['PlanName']) ?>" required>
        </div>
        <div class="form-group">
            <label>Duration (months) *</label>
            <input type="number" name="Duration" min="1" value="<?= htmlspecialchars($vals['Duration']) ?>" required>
        </div>
        <div class="form-group">
            <label>Price (Rs.) *</label>
            <input type="number" name="Price" min="0" step="0.01" value="<?= htmlspecialchars($vals['Price']) ?>" required>
        </div>
        <div class="form-group form-full">
            <label>Details / Description</label>
            <textarea name="PlanDetails"><?= htmlspecialchars($vals['PlanDetails']) ?></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Plan</button>
        <a href="plans.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
