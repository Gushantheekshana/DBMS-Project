<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$r=mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.Name AS MemberName, t.Name AS TrainerName FROM TRAINER_REQUEST tr
     JOIN MEMBER m ON tr.MemberID=m.MemberID JOIN TRAINER t ON tr.TrainerID=t.TrainerID
     WHERE tr.RequestID=$id"));
if (!$r){set_flash('danger','Request not found.');header('Location:trainer_requests.php');exit;}
if (isset($_POST['confirm'])){
    mysqli_query($conn,"DELETE FROM TRAINER_REQUEST WHERE RequestID=$id");
    set_flash('success','Request deleted.');header('Location:trainer_requests.php');exit;
}
$pageTitle='Delete Request';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete request?</h2>
    <p><?= htmlspecialchars($r['MemberName']) ?>'s request for trainer <?= htmlspecialchars($r['TrainerName']) ?>.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="trainer_requests.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
