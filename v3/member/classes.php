<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\ClassService;

require_role('MEMBER');
$user = current_user();
$actorId = (int) $user['UserAccountID'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        ClassService::requestEnrollment($actorId, (int) ($_POST['class_id'] ?? 0));
        flash('success', 'Enrollment request sent to the trainer.');
        redirect('member/classes.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$classes = db_all(
    "SELECT gc.GymClassID,gc.Name,gc.Description,gc.StartsAt,gc.EndsAt,gc.Capacity,gc.Location,
            CONCAT(t.FirstName,' ',t.LastName) AS TrainerName,
            ce.Status AS EnrollmentStatus,
            (SELECT COUNT(*) FROM CLASS_ENROLLMENT x WHERE x.GymClassID=gc.GymClassID AND x.Status='ENROLLED') AS EnrolledCount
       FROM GYM_CLASS gc LEFT JOIN TRAINER t ON t.TrainerID=gc.TrainerID
       LEFT JOIN MEMBER m ON m.UserAccountID=?
       LEFT JOIN CLASS_ENROLLMENT ce ON ce.GymClassID=gc.GymClassID AND ce.MemberID=m.MemberID
      WHERE gc.Status='SCHEDULED' AND gc.StartsAt>NOW() ORDER BY gc.StartsAt",
    'i', [$actorId]
);
$pageTitle = 'Classes'; $pageSubtitle = 'Browse and request a place';
include V3_ROOT . '/includes/header.php';
?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section aria-labelledby="available-classes-title"><h2 class="sr-only" id="available-classes-title">Available classes</h2><div class="table-wrap"><table><caption class="sr-only">Classes available for enrollment</caption><thead><tr><th>Class</th><th>Trainer</th><th>Schedule</th><th>Places</th><th>Action</th></tr></thead><tbody><?php foreach ($classes as $class): ?><tr><td><strong><?= e($class['Name']) ?></strong><?php if ($class['Description']): ?><br><small><?= e($class['Description']) ?></small><?php endif; ?><br><small><?= e($class['Location'] ?? '—') ?></small></td><td><?= e($class['TrainerName'] ?? 'TBA') ?></td><td><?= e($class['StartsAt']) ?> – <?= e($class['EndsAt']) ?></td><td><?= (int) $class['EnrolledCount'] ?>/<?= (int) $class['Capacity'] ?></td><td><?php if ($class['EnrollmentStatus']): ?><span class="status-badge status-<?= e(strtolower($class['EnrollmentStatus'])) ?>"><?= e(ucwords(strtolower($class['EnrollmentStatus']))) ?></span><?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="class_id" value="<?= (int) $class['GymClassID'] ?>"><button class="btn btn-primary" type="submit">Request enrollment</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$classes): ?><tr><td colspan="5">No upcoming classes.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include V3_ROOT . '/includes/footer.php'; ?>
