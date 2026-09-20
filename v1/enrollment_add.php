<?php
require_once 'config.php';
$pageTitle='Enroll Member in Class';
$members = mysqli_query($conn,"SELECT MemberID,Name FROM MEMBER ORDER BY Name");
$classes = mysqli_query($conn,
    "SELECT c.ClassID, c.ClassName, c.DateOfClass, c.StartTime, c.Capacity,
            COUNT(e.EnrollmentID) AS enrolled
     FROM CLASS c LEFT JOIN ENROLLMENT e ON e.ClassID=c.ClassID
     GROUP BY c.ClassID HAVING enrolled < c.Capacity
     ORDER BY c.DateOfClass DESC");
$errors=[]; $vals=['MemberID'=>'','ClassID'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST'){
    $vals['MemberID'] = (int)($_POST['MemberID']??0);
    $vals['ClassID']  = (int)($_POST['ClassID']??0);
    if (!$vals['MemberID']) $errors[]='Select a member.';
    if (!$vals['ClassID'])  $errors[]='Select a class.';
    if (empty($errors)){
        // Check duplicate
        $dup=mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT EnrollmentID FROM ENROLLMENT WHERE MemberID={$vals['MemberID']} AND ClassID={$vals['ClassID']}"));
        if ($dup) $errors[]='This member is already enrolled in that class.';
    }
    if (empty($errors)){
        mysqli_query($conn,"INSERT INTO ENROLLMENT (MemberID,ClassID) VALUES ({$vals['MemberID']},{$vals['ClassID']})");
        set_flash('success','Member enrolled.'); header('Location:enrollments.php'); exit;
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
        <div class="form-group">
            <label>Member *</label>
            <select name="MemberID" required>
                <option value="">Select member</option>
                <?php while ($m=mysqli_fetch_assoc($members)): ?>
                <option value="<?= $m['MemberID'] ?>" <?= $vals['MemberID']==$m['MemberID']?'selected':'' ?>><?= htmlspecialchars($m['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Class (with available spots) *</label>
            <select name="ClassID" required>
                <option value="">Select class</option>
                <?php while ($c=mysqli_fetch_assoc($classes)): ?>
                <option value="<?= $c['ClassID'] ?>" <?= $vals['ClassID']==$c['ClassID']?'selected':'' ?>>
                    <?= htmlspecialchars($c['ClassName']) ?> — <?= $c['DateOfClass'] ?> <?= substr($c['StartTime'],0,5) ?>
                    (<?= $c['Capacity']-$c['enrolled'] ?> spots left)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Enroll</button>
        <a href="enrollments.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
