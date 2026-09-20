<?php
require_once 'config.php';
$id = isset($_GET['id'])?(int)$_GET['id']:0;
$plan = mysqli_fetch_assoc(mysqli_query($conn,"SELECT PlanName FROM MEMBERSHIP_PLAN WHERE PlanID=$id"));
if (!$plan) { set_flash('danger','Plan not found.'); header('Location:plans.php'); exit; }
if (isset($_POST['confirm'])) {
    // Unlink members first
    mysqli_query($conn,"UPDATE MEMBER SET PlanID=NULL,PlanStartDate=NULL,PlanEndDate=NULL WHERE PlanID=$id");
    mysqli_query($conn,"DELETE FROM MEMBERSHIP_PLAN WHERE PlanID=$id");
    set_flash('success','Plan deleted.'); header('Location:plans.php'); exit;
}
$pageTitle = 'Delete Plan';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete plan?</h2>
    <p>Deleting <strong><?= htmlspecialchars($plan['PlanName']) ?></strong> will unassign it from all current members. This cannot be undone.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="plans.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
