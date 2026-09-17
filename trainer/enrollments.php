<?php
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\Services\ClassService;

require_role('TRAINER');
require_active_account();
$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        ClassService::decideEnrollment(
            (int)($_POST['enrollment_id'] ?? 0),
            (int)$user['TrainerID'],
            (int)$user['UserID'],
            (string)($_POST['decision'] ?? ''),
            trim((string)($_POST['reason'] ?? ''))
        );
        set_flash('success', 'Enrollment request updated.');
        redirect('trainer/enrollments.php#pending-requests');
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$rows = db_all(
    "SELECT e.*,m.Name MemberName,m.MemberNumber,c.ClassName,c.DateOfClass,c.StartTime,reviewer.Email ReviewerEmail
     FROM ENROLLMENT e
     JOIN MEMBER m ON m.MemberID=e.MemberID
     JOIN CLASS c ON c.ClassID=e.ClassID
     LEFT JOIN USER_ACCOUNT reviewer ON reviewer.UserID=e.ReviewedBy
     WHERE c.TrainerID=?
     ORDER BY FIELD(e.Status,'PENDING','ENROLLED','REJECTED'),e.RequestedAt DESC",
    'i',
    [$user['TrainerID']]
);
$pending = array_values(array_filter($rows, static fn(array $row): bool => $row['Status'] === 'PENDING'));
$history = array_values(array_filter($rows, static fn(array $row): bool => $row['Status'] !== 'PENDING'));
//$pageTitle = 'Enrollment requests';
$pageSubtitle = count($pending) . ' request(s) need a decision';
include ROOT_PATH . '/includes/header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<section id="pending-requests" class="dash-card">
 <div class="dash-card-title">Pending requests <span class="badge badge-amber"><?= count($pending) ?></span></div>
 <?php if ($pending): ?>
 <div class="table-wrap"><table class="data-table"><caption class="sr-only">Enrollment requests awaiting your decision</caption><thead><tr><th scope="col">Member</th><th scope="col">Class</th><th scope="col">Requested</th><th scope="col">Decision</th></tr></thead><tbody>
 <?php foreach ($pending as $row): ?><tr>
  <td><strong><?= e($row['MemberName']) ?></strong><br><small><?= e($row['MemberNumber']) ?></small></td>
  <td><?= e($row['ClassName']) ?><br><small><?= e(date('d M Y', strtotime($row['DateOfClass']))) ?> at <?= e(substr($row['StartTime'], 0, 5)) ?></small></td>
  <td><?= e(date('d M Y H:i', strtotime($row['RequestedAt']))) ?></td>
  <td><form method="post" class="inline-decision"><?= csrf_field() ?><input type="hidden" name="enrollment_id" value="<?= (int)$row['EnrollmentID'] ?>"><input name="reason" maxlength="500" aria-label="Optional decision reason for <?= e($row['MemberName']) ?>" placeholder="Reason (optional)"><button class="btn btn-success btn-sm" name="decision" value="ENROLLED">Accept</button><button class="btn btn-danger btn-sm" name="decision" value="REJECTED">Reject</button></form></td>
 </tr><?php endforeach; ?>
 </tbody></table></div>
 <?php else: ?><div class="empty-state">No pending enrollment requests for your classes.</div><?php endif; ?>
</section>

<section class="dash-card">
 <div class="dash-card-title">Request history</div>
 <?php if ($history): ?>
 <div class="table-wrap"><table class="data-table"><caption class="sr-only">Processed enrollment request history</caption><thead><tr><th scope="col">Member</th><th scope="col">Class</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col">Reviewed</th><th scope="col">Reason</th></tr></thead><tbody>
 <?php foreach ($history as $row): ?><tr>
  <td><strong><?= e($row['MemberName']) ?></strong><br><small><?= e($row['MemberNumber']) ?></small></td>
  <td><?= e($row['ClassName']) ?><br><small><?= e(date('d M Y', strtotime($row['DateOfClass']))) ?> at <?= e(substr($row['StartTime'], 0, 5)) ?></small></td>
  <td><?= e(date('d M Y H:i', strtotime($row['RequestedAt']))) ?></td>
  <td><span class="badge <?= $row['Status'] === 'ENROLLED' ? 'badge-green' : 'badge-red' ?>"><?= e($row['Status']) ?></span></td>
  <td><?= $row['ReviewedAt'] ? e(date('d M Y H:i', strtotime($row['ReviewedAt']))) : '—' ?><?php if ($row['ReviewerEmail']): ?><br><small><?= e($row['ReviewerEmail']) ?></small><?php endif; ?></td>
  <td><?= e($row['DecisionReason'] ?: '—') ?></td>
 </tr><?php endforeach; ?>
 </tbody></table></div>
 <?php else: ?><div class="empty-state">No processed enrollment requests yet.</div><?php endif; ?>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
