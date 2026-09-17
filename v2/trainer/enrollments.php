<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\ClassService;

require_role('TRAINER');
$actorId = (int) current_user()['UserAccountID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $decision = (string) ($_POST['decision'] ?? '');
    try {
        ClassService::decideEnrollment(
            $actorId,
            (int) ($_POST['enrollment_id'] ?? 0),
            $decision,
            trim((string) ($_POST['reason'] ?? ''))
        );
        flash('success', $decision === 'ENROLLED' ? 'Enrollment request accepted.' : 'Enrollment request declined.');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
    redirect('trainer/enrollments.php');
}

$rows = db_all(
    "SELECT ce.ClassEnrollmentID,ce.Status,ce.EnrolledAt,gc.Name ClassName,gc.StartsAt,gc.EndsAt,
            m.MemberNumber,CONCAT(m.FirstName,' ',m.LastName) MemberName
       FROM CLASS_ENROLLMENT ce
       JOIN GYM_CLASS gc ON gc.GymClassID=ce.GymClassID
       JOIN TRAINER t ON t.TrainerID=gc.TrainerID
       JOIN MEMBER m ON m.MemberID=ce.MemberID
      WHERE t.UserAccountID=?
      ORDER BY FIELD(ce.Status,'WAITLISTED','ENROLLED','CANCELLED'),gc.StartsAt",
    'i',
    [$actorId]
);
$pageTitle = 'Class enrollments';
$pageSubtitle = 'Review requests for your classes';
include V2_ROOT . '/includes/header.php';
?>
<div class="table-wrap"><table>
  <thead><tr><th>Member</th><th>Class</th><th>Status</th><th>Decision</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row):
      $memberName = (string) $row['MemberName'];
      $memberNumber = (string) $row['MemberNumber'];
      $className = (string) $row['ClassName'];
      $schedule = date('d M Y, H:i', strtotime((string) $row['StartsAt'])) . '–' . date('H:i', strtotime((string) $row['EndsAt']));
  ?>
    <tr>
      <td><strong><?= e($memberName) ?></strong><br><small><?= e($memberNumber) ?></small></td>
      <td><?= e($className) ?><br><small><?= e($row['StartsAt']) ?></small></td>
      <td><span class="status-badge status-<?= e(strtolower((string) $row['Status'])) ?>"><?= e(ucwords(strtolower((string) $row['Status']))) ?></span></td>
      <td>
        <?php if ($row['Status'] === 'WAITLISTED'): ?>
          <div class="row-actions">
            <button
              class="btn btn-success"
              type="button"
              data-confirm-open="enrollment-review-dialog"
              data-record-id="<?= (int) $row['ClassEnrollmentID'] ?>"
              data-decision="ENROLLED"
              data-confirm-title="Accept enrollment request?"
              data-confirm-description="<?= e("Accept {$memberName} ({$memberNumber}) into {$className} on {$schedule}. This assigns one available class place.") ?>"
              data-confirm-label="Accept request"
              data-confirm-tone="success"
              data-detail-visible="true"
              data-detail-label="Decision note"
              data-detail-hint="A note is optional."
            >Accept</button>
            <button
              class="btn btn-danger"
              type="button"
              data-confirm-open="enrollment-review-dialog"
              data-record-id="<?= (int) $row['ClassEnrollmentID'] ?>"
              data-decision="CANCELLED"
              data-confirm-title="Decline enrollment request?"
              data-confirm-description="<?= e("Decline {$memberName} ({$memberNumber}) for {$className} on {$schedule}. The request will be cancelled.") ?>"
              data-confirm-label="Decline request"
              data-confirm-tone="danger"
              data-detail-visible="true"
              data-detail-label="Reason for declining"
              data-detail-hint="A reason is optional."
            >Decline</button>
          </div>
        <?php else: ?>
          <span class="muted">Decided</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4">No enrollments.</td></tr><?php endif; ?>
  </tbody>
</table></div>

<dialog class="review-dialog" id="enrollment-review-dialog" aria-labelledby="enrollment-review-title" aria-describedby="enrollment-review-description" data-confirm-dialog>
  <form method="post" data-confirm-form>
    <?= csrf_field() ?>
    <input type="hidden" name="enrollment_id" value="" data-confirm-record>
    <input type="hidden" name="decision" value="" data-confirm-decision>
    <div class="dialog-icon" aria-hidden="true">?</div>
    <h2 id="enrollment-review-title" data-confirm-title>Review enrollment request</h2>
    <p id="enrollment-review-description" data-confirm-description></p>
    <label class="dialog-reason" for="enrollment-review-reason" data-confirm-detail-group hidden>
      <span data-confirm-detail-label>Decision note</span>
      <textarea id="enrollment-review-reason" name="reason" maxlength="500" placeholder="Optional note about this decision" data-confirm-detail></textarea>
      <span class="field-hint" data-confirm-detail-hint>A note is optional.</span>
    </label>
    <div class="dialog-actions">
      <button class="btn btn-secondary" type="button" data-confirm-cancel>Cancel</button>
      <button class="btn" type="submit" data-confirm-submit>Confirm</button>
    </div>
  </form>
</dialog>
<?php include V2_ROOT . '/includes/footer.php'; ?>
