<?php
require_once 'config.php';
$pageTitle = 'Mark Attendance';

// Get classes that have enrolled members
$classes = mysqli_query($conn,
    "SELECT c.ClassID, c.ClassName, c.DateOfClass, c.StartTime FROM CLASS c
     WHERE EXISTS (SELECT 1 FROM ENROLLMENT e WHERE e.ClassID=c.ClassID)
     ORDER BY c.DateOfClass DESC");

$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$date_val = isset($_GET['date']) ? sanitize($conn,$_GET['date']) : date('Y-m-d');

$enrolled = [];
if ($selected_class && $date_val) {
    $enrolled = [];
    $res = mysqli_query($conn,
        "SELECT m.MemberID, m.Name,
                (SELECT Status FROM ATTENDANCE WHERE MemberID=m.MemberID AND ClassID=$selected_class AND Date='$date_val') AS status
         FROM ENROLLMENT e JOIN MEMBER m ON e.MemberID=m.MemberID
         WHERE e.ClassID=$selected_class ORDER BY m.Name");
    while ($r=mysqli_fetch_assoc($res)) $enrolled[]=$r;
}

$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $cid  = (int)($_POST['ClassID']??0);
    $date = sanitize($conn,$_POST['date']??'');
    if (!$cid||!$date) { $errors[]='Class and date are required.'; }
    else {
        $statuses = $_POST['status'] ?? [];
        foreach ($statuses as $mid=>$st) {
            $mid=(int)$mid; $st=in_array($st,['Present','Absent'])?$st:'Absent';
            // Upsert
            $exists=mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT AttendanceID FROM ATTENDANCE WHERE MemberID=$mid AND ClassID=$cid AND Date='$date'"));
            if ($exists) {
                mysqli_query($conn,"UPDATE ATTENDANCE SET Status='$st' WHERE AttendanceID={$exists['AttendanceID']}");
            } else {
                mysqli_query($conn,"INSERT INTO ATTENDANCE (Status,Date,MemberID,ClassID) VALUES ('$st','$date',$mid,$cid)");
            }
        }
        set_flash('success','Attendance saved for '.count($statuses).' member(s).');
        header("Location:attendance.php"); exit;
    }
}

include 'includes/header.php';
?>
<?php if ($errors): ?>
<div class="alert alert-danger"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?><button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button></div>
<?php endif; ?>

<!-- Step 1: Select class + date -->
<div class="form-card" style="margin-bottom:20px;">
    <form method="GET">
        <div class="form-grid">
            <div class="form-group">
                <label>Select Class</label>
                <select name="class_id" required>
                    <option value="">Choose a class</option>
                    <?php while ($c=mysqli_fetch_assoc($classes)): ?>
                    <option value="<?= $c['ClassID'] ?>" <?= $selected_class==$c['ClassID']?'selected':'' ?>>
                        <?= htmlspecialchars($c['ClassName']) ?> — <?= $c['DateOfClass'] ?> <?= substr($c['StartTime'],0,5) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Attendance Date</label>
                <input type="date" name="date" value="<?= $date_val ?>" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-ghost">Load Members</button>
        </div>
    </form>
</div>

<!-- Step 2: Mark attendance -->
<?php if ($selected_class && !empty($enrolled)): ?>
<div class="form-card">
    <div style="font-size:0.9rem;font-weight:700;margin-bottom:16px;">
        Marking attendance for <?= date('D, d M Y', strtotime($date_val)) ?>
    </div>
    <form method="POST">
        <input type="hidden" name="ClassID" value="<?= $selected_class ?>">
        <input type="hidden" name="date" value="<?= $date_val ?>">

        <div class="table-wrap" style="margin-bottom:16px;">
        <table class="data-table">
        <thead><tr><th>Member</th><th>Present</th><th>Absent</th></tr></thead>
        <tbody>
        <?php foreach ($enrolled as $m): ?>
        <tr>
            <td style="font-weight:500;"><?= htmlspecialchars($m['Name']) ?></td>
            <td>
                <input type="radio" name="status[<?= $m['MemberID'] ?>]" value="Present"
                    <?= $m['status']==='Present'||$m['status']===null?'checked':'' ?>>
            </td>
            <td>
                <input type="radio" name="status[<?= $m['MemberID'] ?>]" value="Absent"
                    <?= $m['status']==='Absent'?'checked':'' ?>>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </div>

        <div style="margin-bottom:12px;">
            <button type="button" onclick="markAll('Present')" class="btn btn-success btn-sm">Mark all present</button>
            <button type="button" onclick="markAll('Absent')"  class="btn btn-danger btn-sm">Mark all absent</button>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Attendance</button>
            <a href="attendance.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
<script>
function markAll(status) {
    document.querySelectorAll('input[type="radio"][value="'+status+'"]').forEach(r=>r.checked=true);
}
</script>
<?php elseif ($selected_class && empty($enrolled)): ?>
<div class="form-card"><p style="color:var(--text-muted);">No members enrolled in this class.</p></div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
