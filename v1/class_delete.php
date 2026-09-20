<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$c=mysqli_fetch_assoc(mysqli_query($conn,"SELECT ClassName FROM CLASS WHERE ClassID=$id"));
if (!$c){set_flash('danger','Class not found.');header('Location:classes.php');exit;}
if (isset($_POST['confirm'])){
    mysqli_query($conn,"DELETE FROM ENROLLMENT WHERE ClassID=$id");
    mysqli_query($conn,"DELETE FROM ATTENDANCE WHERE ClassID=$id");
    mysqli_query($conn,"DELETE FROM CLASS WHERE ClassID=$id");
    set_flash('success','Class deleted.');header('Location:classes.php');exit;
}
$pageTitle='Delete Class';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete class?</h2>
    <p>This removes <strong><?= htmlspecialchars($c['ClassName']) ?></strong> along with all its enrollment and attendance records.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="classes.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
