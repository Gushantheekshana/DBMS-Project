<?php
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\Services\ClassService;

require_role('MEMBER');
$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if ($user['Status'] !== 'ACTIVE') {
            throw new RuntimeException('Activate your membership before requesting a class.');
        }
        ClassService::requestEnrollment((int)$user['MemberID'], (int)($_POST['class_id'] ?? 0));
        set_flash('success', 'Your enrollment request was sent to the class trainer.');
        redirect('member/classes.php');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$rows = db_all(
    "SELECT c.*,t.Name TrainerName,tu.UserID TrainerUserID,
            (SELECT COUNT(*) FROM ENROLLMENT x WHERE x.ClassID=c.ClassID AND x.Status='ENROLLED') Enrolled,
            e.Status EnrollmentStatus
     FROM CLASS c
     JOIN TRAINER t ON t.TrainerID=c.TrainerID AND t.ArchivedAt IS NULL
     JOIN USER_ACCOUNT tu ON tu.TrainerID=t.TrainerID AND tu.Role='TRAINER' AND tu.Status='ACTIVE' AND tu.AnonymizedAt IS NULL
     LEFT JOIN ENROLLMENT e ON e.ClassID=c.ClassID AND e.MemberID=?
     WHERE c.Status='SCHEDULED' AND TIMESTAMP(c.DateOfClass,c.StartTime)>NOW()
     ORDER BY c.DateOfClass,c.StartTime",
    'i',
    [$user['MemberID']]
);
$pageTitle = 'Browse classes';
$pageSubtitle = 'Requests go directly to the active class trainer';
include ROOT_PATH . '/includes/header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<div class="card-grid">
 <?php foreach ($rows as $row): ?>
 <article class="class-card">
  <div class="class-card-top"><span class="badge badge-blue"><?= e($row['Specialization']) ?></span><span class="meta"><?= (int)$row['Enrolled'] ?> / <?= min(10, (int)$row['Capacity']) ?> seats</span></div>
  <h2><?= e($row['ClassName']) ?></h2><p><?= e($row['Description']) ?></p>
  <dl><div><dt>Trainer</dt><dd><?= e($row['TrainerName']) ?></dd></div><div><dt>Schedule</dt><dd><?= e(date('d M Y', strtotime($row['DateOfClass']))) ?>, <?= e(substr($row['StartTime'], 0, 5)) ?></dd></div></dl>
  <?php if ($row['EnrollmentStatus']): ?>
   <?php $badge = $row['EnrollmentStatus'] === 'ENROLLED' ? 'badge-green' : ($row['EnrollmentStatus'] === 'REJECTED' ? 'badge-red' : 'badge-amber'); ?>
   <div class="form-actions"><span class="badge <?= $badge ?>"><?= e($row['EnrollmentStatus']) ?></span>
   <?php if (in_array($row['EnrollmentStatus'], ['PENDING', 'ENROLLED'], true)): ?><a class="btn btn-ghost btn-sm" href="<?= e(base_url('messages.php?class_id=' . (int)$row['ClassID'])) ?>">Message trainer or support</a><?php endif; ?></div>
  <?php else: ?>
   <form method="post"><?= csrf_field() ?><input type="hidden" name="class_id" value="<?= (int)$row['ClassID'] ?>"><button class="btn btn-primary" type="submit" <?= (int)$row['Enrolled'] >= min(10, (int)$row['Capacity']) ? 'disabled' : '' ?>>Request enrollment</button></form>
  <?php endif; ?>
 </article>
 <?php endforeach; ?>
 <?php if (!$rows): ?><div class="empty-state">No classes with an available trainer are scheduled right now.</div><?php endif; ?>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
