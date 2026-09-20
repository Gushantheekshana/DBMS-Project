<?php
require_once 'config.php';
$pageTitle    = 'Trainers';
$pageSubtitle = 'Gym instructor roster';

$trainers = mysqli_query($conn,
    "SELECT t.*, COUNT(DISTINCT c.ClassID) AS class_count,
            COALESCE(SUM(p.Amount),0) AS salary_paid
     FROM TRAINER t
     LEFT JOIN CLASS c ON c.TrainerID=t.TrainerID
     LEFT JOIN PAYMENT p ON p.TrainerID=t.TrainerID AND p.PaymentType='Salary'
     GROUP BY t.TrainerID ORDER BY t.TrainerID DESC");

include 'includes/header.php';
?>

<div class="section-header">
    <span class="section-title">Trainers</span>
    <a href="trainer_add.php" class="btn btn-primary">+ Add Trainer</a>
</div>

<div class="table-wrap">
<table class="data-table">
<thead>
    <tr>
        <th>#</th>
        <th>Name</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Experience</th>
        <th>Classes</th>
        <th>Salary Paid</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if (mysqli_num_rows($trainers)===0): ?>
<tr><td colspan="8"><div class="empty-state"><p>No trainers yet. <a href="trainer_add.php" style="color:var(--blue)">Add the first trainer.</a></p></div></td></tr>
<?php endif;
while ($t = mysqli_fetch_assoc($trainers)): ?>
<tr>
    <td><?= $t['TrainerID'] ?></td>
    <td style="font-weight:600;"><?= htmlspecialchars($t['Name']) ?></td>
    <td><?= htmlspecialchars($t['PhoneNo'] ?? '—') ?></td>
    <td style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($t['Email'] ?? '—') ?></td>
    <td><?= $t['Experience'] ? $t['Experience'].' yr'.($t['Experience']>1?'s':'') : '—' ?></td>
    <td><span class="badge badge-blue"><?= $t['class_count'] ?></span></td>
    <td style="color:var(--green);font-weight:600;">Rs.<?= number_format($t['salary_paid'],2) ?></td>
    <td class="action-cell">
        <a href="trainer_edit.php?id=<?= $t['TrainerID'] ?>" class="btn btn-primary btn-sm">Edit</a>
        <a href="trainer_delete.php?id=<?= $t['TrainerID'] ?>" class="btn btn-danger btn-sm">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
