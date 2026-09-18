<?php
require_once 'config.php';
$pageTitle = 'Edit Plan';
$id = isset($_GET['id'])?(int)$_GET['id']:0;
$plan = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM MEMBERSHIP_PLAN WHERE PlanID=$id"));
if (!$plan) { set_flash('danger','Plan not found.'); header('Location:plans.php'); exit; }
$errors = []; $vals = $plan;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $fields = ['PlanName','PlanDetails','Duration','Price'];
    foreach ($fields as $k) $vals[$k] = sanitize($conn,$_POST[$k]??'');
    if (!$vals['PlanName'])  $errors[] = 'Plan name is required.';
    if (!$vals['Duration'])  $errors[] = 'Duration is required.';
    if (!$vals['Price'])     $errors[] = 'Price is required.';
    if (empty($errors)) {
        mysqli_query($conn,"UPDATE MEMBERSHIP_PLAN SET PlanName='{$vals['PlanName']}',
            PlanDetails='{$vals['PlanDetails']}',Duration={$vals['Duration']},Price={$vals['Price']}
            WHERE PlanID=$id");
        set_flash('success','Plan updated.'); header('Location:plans.php'); exit;
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
        <button type="submit" class="btn btn-primary">Update Plan</button>
        <a href="plans.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
