<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
if (isset($_POST['confirm'])){
    mysqli_query($conn,"DELETE FROM ENROLLMENT WHERE EnrollmentID=$id");
    set_flash('success','Enrollment removed.');header('Location:enrollments.php');exit;
}
$e=mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.Name AS MemberName, c.ClassName FROM ENROLLMENT e
     JOIN MEMBER m ON e.MemberID=m.MemberID JOIN CLASS c ON e.ClassID=c.ClassID
     WHERE e.EnrollmentID=$id"));
if (!$e){set_flash('danger','Enrollment not found.');header('Location:enrollments.php');exit;}
$pageTitle='Remove Enrollment';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Remove enrollment?</h2>
    <p>This will unenroll <strong><?= htmlspecialchars($e['MemberName']) ?></strong> from <strong><?= htmlspecialchars($e['ClassName']) ?></strong>.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, remove</button>
        <a href="enrollments.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
