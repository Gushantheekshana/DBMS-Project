<?php
require_once 'config.php';
$pageTitle    = 'Trainer Requests';
$pageSubtitle = 'Members requesting personal trainers';

$status_f = isset($_GET['status']) ? sanitize($conn,$_GET['status']) : '';
$where    = $status_f ? "WHERE tr.Status='$status_f'" : '';

$requests = mysqli_query($conn,
    "SELECT tr.*, m.Name AS MemberName, t.Name AS TrainerName FROM TRAINER_REQUEST tr
     JOIN MEMBER  m ON tr.MemberID=m.MemberID
     JOIN TRAINER t ON tr.TrainerID=t.TrainerID
     $where ORDER BY tr.RequestDate DESC, tr.RequestID DESC");

// Count by status
$counts = [];
$cr = mysqli_query($conn,"SELECT Status, COUNT(*) AS n FROM TRAINER_REQUEST GROUP BY Status");
while ($r=mysqli_fetch_assoc($cr)) $counts[$r['Status']] = $r['n'];

include 'includes/header.php';
?>

<div class="section-header">
    <span class="section-title">Trainer Requests</span>
    <a href="request_add.php" class="btn btn-primary">+ New Request</a>
</div>

<!-- Mini-stats -->
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="stat-card stat-amber" style="padding:14px 20px;flex:0 0 auto;">
        <div class="stat-label">Pending</div>
        <div class="stat-value" style="font-size:1.3rem;"><?= $counts['Pending'] ?? 0 ?></div>
    </div>
    <div class="stat-card stat-green" style="padding:14px 20px;flex:0 0 auto;">
        <div class="stat-label">Accepted</div>
        <div class="stat-value" style="font-size:1.3rem;"><?= $counts['Accepted'] ?? 0 ?></div>
    </div>
    <div class="stat-card stat-red" style="padding:14px 20px;flex:0 0 auto;">
        <div class="stat-label">Rejected</div>
        <div class="stat-value" style="font-size:1.3rem;"><?= $counts['Rejected'] ?? 0 ?></div>
    </div>
</div>

<form method="GET" class="filter-bar">
    <select name="status">
        <option value="">All statuses</option>
        <option value="Pending"  <?= $status_f==='Pending' ?'selected':'' ?>>Pending</option>
        <option value="Accepted" <?= $status_f==='Accepted'?'selected':'' ?>>Accepted</option>
        <option value="Rejected" <?= $status_f==='Rejected'?'selected':'' ?>>Rejected</option>
    </select>
    <button type="submit" class="btn btn-ghost">Filter</button>
    <a href="trainer_requests.php" class="btn btn-ghost">Clear</a>
</form>

<div class="table-wrap">
<table class="data-table">
<thead>
    <tr>
        <th>#</th>
        <th>Member</th>
        <th>Trainer</th>
        <th>Request Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if (mysqli_num_rows($requests)===0): ?>
<tr><td colspan="6"><div class="empty-state"><p>No requests. <a href="request_add.php" style="color:var(--blue)">Create one.</a></p></div></td></tr>
<?php endif;
while ($r = mysqli_fetch_assoc($requests)):
    $badge = match($r['Status']) {
        'Pending'  => 'badge-amber',
        'Accepted' => 'badge-green',
        'Rejected' => 'badge-red',
        default    => 'badge-grey',
    };
?>
<tr>
    <td><?= $r['RequestID'] ?></td>
    <td style="font-weight:500;"><?= htmlspecialchars($r['MemberName']) ?></td>
    <td><?= htmlspecialchars($r['TrainerName']) ?></td>
    <td><?= $r['RequestDate'] ?></td>
    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
    <td class="action-cell">
        <?php if ($r['Status']==='Pending'): ?>
        <a href="request_update.php?id=<?= $r['RequestID'] ?>&status=Accepted" class="btn btn-success btn-sm">Accept</a>
        <a href="request_update.php?id=<?= $r['RequestID'] ?>&status=Rejected" class="btn btn-danger btn-sm">Reject</a>
        <?php endif; ?>
        <a href="request_delete.php?id=<?= $r['RequestID'] ?>" class="btn btn-ghost btn-sm">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include 'includes/footer.php'; ?>
