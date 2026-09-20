<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$t=mysqli_fetch_assoc(mysqli_query($conn,"SELECT Name FROM TRAINER WHERE TrainerID=$id"));
if (!$t){set_flash('danger','Trainer not found.');header('Location:trainers.php');exit;}
if (isset($_POST['confirm'])){
    mysqli_query($conn,"DELETE FROM TRAINER_REQUEST WHERE TrainerID=$id");
    mysqli_query($conn,"UPDATE CLASS SET TrainerID=NULL WHERE TrainerID=$id");
    mysqli_query($conn,"UPDATE PAYMENT SET TrainerID=NULL WHERE TrainerID=$id");
    mysqli_query($conn,"DELETE FROM TRAINER WHERE TrainerID=$id");
    set_flash('success','Trainer deleted.');header('Location:trainers.php');exit;
}
$pageTitle='Delete Trainer';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete trainer?</h2>
    <p>Removing <strong><?= htmlspecialchars($t['Name']) ?></strong> will unlink them from their classes and requests.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="trainers.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
