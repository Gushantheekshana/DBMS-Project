<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
if (isset($_POST['confirm'])){
    mysqli_query($conn,"DELETE FROM ATTENDANCE WHERE AttendanceID=$id");
    set_flash('success','Record deleted.');header('Location:attendance.php');exit;
}
$r=mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.Name AS MemberName, c.ClassName, a.Date FROM ATTENDANCE a
     JOIN MEMBER m ON a.MemberID=m.MemberID JOIN CLASS c ON a.ClassID=c.ClassID WHERE a.AttendanceID=$id"));
if (!$r){set_flash('danger','Record not found.');header('Location:attendance.php');exit;}
$pageTitle='Delete Attendance Record';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete attendance record?</h2>
    <p><?= htmlspecialchars($r['MemberName']) ?>'s record for <?= htmlspecialchars($r['ClassName']) ?> on <?= $r['Date'] ?>.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="attendance.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
