<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
require_role('TRAINER');
$actorId = (int) current_user()['UserAccountID'];
$trainer = db_one('SELECT TrainerID,TrainerNumber,FirstName,LastName,Specialization,Status FROM TRAINER WHERE UserAccountID=?', 'i', [$actorId]);
$stats = $trainer ? db_one(
    "SELECT COUNT(*) AS Classes,
            COALESCE(SUM((SELECT COUNT(*) FROM CLASS_ENROLLMENT ce WHERE ce.GymClassID=gc.GymClassID AND ce.Status='ENROLLED')),0) AS Enrollments,
            COALESCE(SUM((SELECT COUNT(*) FROM CLASS_ENROLLMENT ce WHERE ce.GymClassID=gc.GymClassID AND ce.Status='WAITLISTED')),0) AS Waitlisted
       FROM GYM_CLASS gc WHERE gc.TrainerID=?",
    'i', [(int) $trainer['TrainerID']]
) : ['Classes'=>0,'Enrollments'=>0,'Waitlisted'=>0];
$upcoming = $trainer ? db_all("SELECT Name,StartsAt,Location,Status FROM GYM_CLASS WHERE TrainerID=? AND StartsAt>=NOW() ORDER BY StartsAt LIMIT 5", 'i', [(int) $trainer['TrainerID']]) : [];
$pageTitle='Trainer dashboard'; $pageSubtitle='Classes, enrollments, and attendance'; include V3_ROOT.'/includes/header.php';
?>
<section class="stats-grid"><article class="stat-card"><span>Classes</span><strong><?= (int) $stats['Classes'] ?></strong></article><article class="stat-card"><span>Enrolled</span><strong><?= (int) $stats['Enrollments'] ?></strong></article><article class="stat-card"><span>Waitlisted</span><strong><?= (int) $stats['Waitlisted'] ?></strong></article></section>
<section class="panel"><h2>Upcoming classes</h2><div class="table-wrap"><table><caption class="sr-only">Upcoming trainer classes</caption><thead><tr><th>Class</th><th>Starts</th><th>Location</th><th>Status</th></tr></thead><tbody><?php foreach($upcoming as $row): ?><tr><td><?= e($row['Name']) ?></td><td><?= e($row['StartsAt']) ?></td><td><?= e($row['Location']??'—') ?></td><td><span class="status-badge status-<?= e(strtolower($row['Status'])) ?>"><?= e(ucwords(strtolower($row['Status']))) ?></span></td></tr><?php endforeach; ?><?php if(!$upcoming): ?><tr><td colspan="4">No upcoming classes.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include V3_ROOT.'/includes/footer.php'; ?>
