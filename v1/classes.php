<?php
require_once 'config.php';
$pageTitle    = 'Classes';
$pageSubtitle = 'All scheduled gym classes';

$search = isset($_GET['search']) ? sanitize($conn,$_GET['search']) : '';
$where  = $search ? "WHERE c.ClassName LIKE '%$search%' OR t.Name LIKE '%$search%'" : '';

$classes = mysqli_query($conn,
    "SELECT c.*, t.Name AS TrainerName,
            COUNT(DISTINCT e.EnrollmentID) AS enrolled
     FROM CLASS c
     LEFT JOIN TRAINER t ON c.TrainerID=t.TrainerID
     LEFT JOIN ENROLLMENT e ON e.ClassID=c.ClassID
     $where
     GROUP BY c.ClassID ORDER BY c.DateOfClass DESC, c.StartTime ASC");

include 'includes/header.php';
?>

<div class="section-header">
    <span class="section-title">Classes</span>
    <a href="class_add.php" class="btn btn-primary">+ Add Class</a>
</div>

<form method="GET" class="filter-bar">
    <input type="text" name="search" placeholder="Search class name or trainer…" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-ghost">Filter</button>
    <a href="classes.php" class="btn btn-ghost">Clear</a>
</form>

<div class="table-wrap">
<table class="data-table">
<thead>
    <tr>
        <th>#</th>
        <th>Class</th>
        <th>Date</th>
        <th>Time</th>
        <th>Trainer</th>
        <th>Specialization</th>
        <th>Capacity</th>
        <th>Enrolled</th>
        <th>Rate/Class</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if (mysqli_num_rows($classes)===0): ?>
<tr><td colspan="10"><div class="empty-state"><p>No classes found. <a href="class_add.php" style="color:var(--blue)">Schedule one.</a></p></div></td></tr>
<?php endif;
while ($c = mysqli_fetch_assoc($classes)):
    $full = $c['enrolled'] >= $c['Capacity'];
?>
<tr>
    <td><?= $c['ClassID'] ?></td>
    <td style="font-weight:600;"><?= htmlspecialchars($c['ClassName']) ?></td>
    <td><?= $c['DateOfClass'] ?></td>
    <td><?= substr($c['StartTime'],0,5) ?> – <?= substr($c['EndTime'],0,5) ?></td>
    <td><?= htmlspecialchars($c['TrainerName'] ?? '—') ?></td>
    <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($c['Specialization'] ?? '—') ?></td>
    <td><?= $c['Capacity'] ?></td>
    <td><?= $full
        ? '<span class="badge badge-red">'.$c['enrolled'].'</span>'
        : '<span class="badge badge-green">'.$c['enrolled'].'</span>' ?></td>
    <td><?= $c['RatePerClass'] ? 'Rs.'.number_format($c['RatePerClass'],2) : '—' ?></td>
    <td class="action-cell">
        <a href="class_edit.php?id=<?= $c['ClassID'] ?>" class="btn btn-primary btn-sm">Edit</a>
        <a href="class_delete.php?id=<?= $c['ClassID'] ?>" class="btn btn-danger btn-sm">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
