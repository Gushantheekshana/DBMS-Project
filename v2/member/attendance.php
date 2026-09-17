<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
require_role('MEMBER');
$actorId = (int) current_user()['UserAccountID'];
$classAttendance = db_all(
    "SELECT gc.Name,gc.StartsAt,ca.Status,ca.CheckedInAt,ca.Notes
       FROM MEMBER m JOIN CLASS_ENROLLMENT ce ON ce.MemberID=m.MemberID
       JOIN GYM_CLASS gc ON gc.GymClassID=ce.GymClassID
       LEFT JOIN CLASS_ATTENDANCE ca ON ca.ClassEnrollmentID=ce.ClassEnrollmentID
      WHERE m.UserAccountID=? ORDER BY gc.StartsAt DESC",
    'i', [$actorId]
);
$visits = db_all(
    'SELECT gv.CheckedInAt,gv.CheckedOutAt,gv.Notes FROM GYM_VISIT gv JOIN MEMBER m ON m.MemberID=gv.MemberID WHERE m.UserAccountID=? ORDER BY gv.CheckedInAt DESC LIMIT 100',
    'i', [$actorId]
);
$pageTitle = 'Attendance'; $pageSubtitle = 'Your class and gym visit history';
include V2_ROOT . '/includes/header.php';
?>
<section class="panel"><h2>Class attendance</h2><div class="table-wrap"><table><thead><tr><th>Class</th><th>Starts</th><th>Status</th><th>Checked in</th></tr></thead><tbody><?php foreach ($classAttendance as $row): ?><tr><td><?= e($row['Name']) ?></td><td><?= e($row['StartsAt']) ?></td><td><?= e($row['Status'] ?? 'NOT MARKED') ?></td><td><?= e($row['CheckedInAt'] ?? '—') ?></td></tr><?php endforeach; ?><?php if (!$classAttendance): ?><tr><td colspan="4">No class attendance records.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><h2>Gym visits</h2><div class="table-wrap"><table><thead><tr><th>Checked in</th><th>Checked out</th><th>Notes</th></tr></thead><tbody><?php foreach ($visits as $row): ?><tr><td><?= e($row['CheckedInAt']) ?></td><td><?= e($row['CheckedOutAt'] ?? 'Still inside') ?></td><td><?= e($row['Notes'] ?? '') ?></td></tr><?php endforeach; ?><?php if (!$visits): ?><tr><td colspan="3">No gym visits.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include V2_ROOT . '/includes/footer.php'; ?>
