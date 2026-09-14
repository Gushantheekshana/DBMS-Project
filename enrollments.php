<?php
require_once 'config.php';
$pageTitle    = 'Enrollments';
$pageSubtitle = 'Member-to-class assignments';

$search = isset($_GET['search']) ? sanitize($conn,$_GET['search']) : '';
$where  = $search ? "WHERE m.Name LIKE '%$search%' OR c.ClassName LIKE '%$search%'" : '';

$rows = mysqli_query($conn,
    "SELECT e.EnrollmentID, m.Name AS MemberName, m.MemberID,
            c.ClassName, c.DateOfClass, c.StartTime, c.ClassID
     FROM ENROLLMENT e
     JOIN MEMBER m ON e.MemberID=m.MemberID
     JOIN CLASS  c ON e.ClassID=c.ClassID
     $where
     ORDER BY c.DateOfClass DESC, m.Name ASC");

include 'includes/header.php';
?>

<div class="section-header">
    <span class="section-title">Enrollments</span>
    <a href="enrollment_add.php" class="btn btn-primary">+ Enroll Member</a>
</div>

<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search member or class…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-ghost">Filter</button>
    <a href="enrollments.php" class="btn btn-ghost">Clear</a>
</form>

<div class="table-wrap">
<table class="data-table">
<thead>
    <tr>
        <th>#</th>
        <th>Member</th>
        <th>Class</th>
        <th>Date</th>
        <th>Time</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if (mysqli_num_rows($rows)===0): ?>
<tr><td colspan="6"><div class="empty-state"><p>No enrollments yet. <a href="enrollment_add.php" style="color:var(--blue)">Enroll a member.</a></p></div></td></tr>
<?php endif;
while ($r = mysqli_fetch_assoc($rows)): ?>
<tr>
    <td><?= $r['EnrollmentID'] ?></td>
    <td><a href="member_view.php?id=<?= $r['MemberID'] ?>" style="color:var(--blue);font-weight:600;"><?= htmlspecialchars($r['MemberName']) ?></a></td>
    <td><?= htmlspecialchars($r['ClassName']) ?></td>
    <td><?= $r['DateOfClass'] ?></td>
    <td><?= substr($r['StartTime'],0,5) ?></td>
    <td>
        <a href="enrollment_delete.php?id=<?= $r['EnrollmentID'] ?>" class="btn btn-danger btn-sm">Remove</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
