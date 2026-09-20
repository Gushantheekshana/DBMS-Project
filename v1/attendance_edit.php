<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$r=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM ATTENDANCE WHERE AttendanceID=$id"));
if (!$r){set_flash('danger','Record not found.');header('Location:attendance.php');exit;}
$pageTitle='Edit Attendance';
if ($_SERVER['REQUEST_METHOD']==='POST'){
    $st=in_array($_POST['Status']??'',['Present','Absent'])?$_POST['Status']:'Absent';
    $dt=sanitize($conn,$_POST['Date']??'');
    mysqli_query($conn,"UPDATE ATTENDANCE SET Status='$st',Date='$dt' WHERE AttendanceID=$id");
    set_flash('success','Attendance updated.');header('Location:attendance.php');exit;
}
$info=mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.Name AS MemberName, c.ClassName FROM ATTENDANCE a
     JOIN MEMBER m ON a.MemberID=m.MemberID JOIN CLASS c ON a.ClassID=c.ClassID WHERE a.AttendanceID=$id"));
include 'includes/header.php';
?>
<div class="form-card">
    <p style="margin-bottom:16px;font-size:0.88rem;color:var(--text-muted);">
        Editing attendance for <strong style="color:var(--text)"><?= htmlspecialchars($info['MemberName']) ?></strong>
        in <strong style="color:var(--text)"><?= htmlspecialchars($info['ClassName']) ?></strong>
    </p>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="Date" value="<?= $r['Date'] ?>" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="Status">
                    <option value="Present" <?= $r['Status']==='Present'?'selected':'' ?>>Present</option>
                    <option value="Absent"  <?= $r['Status']==='Absent' ?'selected':'' ?>>Absent</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="attendance.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
