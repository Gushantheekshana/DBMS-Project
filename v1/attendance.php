<?php
require_once 'config.php';
$pageTitle    = 'Attendance';
$pageSubtitle = 'Class attendance records';

$date_f  = isset($_GET['date'])    ? sanitize($conn,$_GET['date'])   : '';
$class_f = isset($_GET['class_id'])? (int)$_GET['class_id']          : 0;
$stat_f  = isset($_GET['status'])  ? sanitize($conn,$_GET['status']) : '';

// Build WHERE using column names without alias (for plain COUNT queries)
$where_plain = "WHERE 1=1";
if ($date_f)  $where_plain .= " AND Date='$date_f'";
if ($class_f) $where_plain .= " AND ClassID=$class_f";
if ($stat_f)  $where_plain .= " AND Status='$stat_f'";

// Build WHERE using alias (for the JOIN query)
$where_join = "WHERE 1=1";
if ($date_f)  $where_join .= " AND a.Date='$date_f'";
if ($class_f) $where_join .= " AND a.ClassID=$class_f";
if ($stat_f)  $where_join .= " AND a.Status='$stat_f'";

$records = mysqli_query($conn,
    "SELECT a.*, m.Name AS MemberName, c.ClassName FROM ATTENDANCE a
     JOIN MEMBER m ON a.MemberID=m.MemberID
     JOIN CLASS  c ON a.ClassID=c.ClassID
     $where_join ORDER BY a.Date DESC, m.Name ASC");

$classes_list = mysqli_query($conn,"SELECT ClassID,ClassName,DateOfClass FROM CLASS ORDER BY DateOfClass DESC");

// Summary counts — use plain column names (no alias), no JOIN needed
$total   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS n FROM ATTENDANCE $where_plain"))['n'];
$present_where = $where_plain . ($stat_f ? "" : " AND Status='Present'");
$present = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS n FROM ATTENDANCE $present_where"))['n'];

include 'includes/header.php';
?>

<div class="section-header">
    <span class="section-title">Attendance</span>
    <a href="attendance_mark.php" class="btn btn-primary">+ Mark Attendance</a>
</div>

<form method="GET" class="filter-bar">
    <input type="date" name="date" value="<?= $date_f ?>">
    <select name="class_id">
        <option value="">All classes</option>
        <?php while ($cl=mysqli_fetch_assoc($classes_list)): ?>
        <option value="<?= $cl['ClassID'] ?>" <?= $class_f==$cl['ClassID']?'selected':'' ?>><?= htmlspecialchars($cl['ClassName']) ?> (<?= $cl['DateOfClass'] ?>)</option>
        <?php endwhile; ?>
    </select>
    <select name="status">
        <option value="">Any status</option>
        <option value="Present" <?= $stat_f==='Present'?'selected':'' ?>>Present</option>
        <option value="Absent"  <?= $stat_f==='Absent' ?'selected':'' ?>>Absent</option>
    </select>
    <button type="submit" class="btn btn-ghost">Filter</button>
    <a href="attendance.php" class="btn btn-ghost">Clear</a>
</form>

<?php if ($date_f || $class_f || $stat_f || true): ?>
<div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:12px;">
    <?= $present ?> present of <?= $total ?> records
    <?= $total>0 ? '('.round($present/$total*100).'% attendance rate)' : '' ?>
</div>
<?php endif; ?>

<div class="table-wrap">
<table class="data-table">
<thead>
    <tr>
        <th>#</th>
        <th>Member</th>
        <th>Class</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if (mysqli_num_rows($records)===0): ?>
<tr><td colspan="6"><div class="empty-state"><p>No attendance records. <a href="attendance_mark.php" style="color:var(--blue)">Mark today's session.</a></p></div></td></tr>
<?php endif;
while ($r = mysqli_fetch_assoc($records)): ?>
<tr>
    <td><?= $r['AttendanceID'] ?></td>
    <td><?= htmlspecialchars($r['MemberName']) ?></td>
    <td><?= htmlspecialchars($r['ClassName']) ?></td>
    <td><?= $r['Date'] ?></td>
    <td><?= $r['Status']==='Present'
        ? '<span class="badge badge-green">Present</span>'
        : '<span class="badge badge-red">Absent</span>' ?></td>
    <td class="action-cell">
        <a href="attendance_edit.php?id=<?= $r['AttendanceID'] ?>" class="btn btn-primary btn-sm">Edit</a>
        <a href="attendance_delete.php?id=<?= $r['AttendanceID'] ?>" class="btn btn-danger btn-sm">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
