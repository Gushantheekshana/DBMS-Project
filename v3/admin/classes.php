<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\ClassService;

require_role('ADMIN');
$actorId = (int) current_user()['UserAccountID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $decision = (string) ($_POST['decision'] ?? '');
    try {
        ClassService::review(
            $actorId,
            (int) ($_POST['class_id'] ?? 0),
            $decision,
            trim((string) ($_POST['notes'] ?? ''))
        );
        flash('success', $decision === 'SCHEDULED' ? 'Class approved and opened for enrollment.' : 'Class request rejected.');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
    redirect('admin/classes.php');
}

$rows = db_all(
    "SELECT gc.GymClassID,gc.Name,gc.StartsAt,gc.EndsAt,gc.Capacity,gc.Location,gc.Status,
            CONCAT(t.FirstName,' ',t.LastName) TrainerName,
            (SELECT COUNT(*) FROM CLASS_ENROLLMENT ce WHERE ce.GymClassID=gc.GymClassID AND ce.Status='ENROLLED') EnrolledCount
       FROM GYM_CLASS gc
       LEFT JOIN TRAINER t ON t.TrainerID=gc.TrainerID
      ORDER BY FIELD(gc.Status,'DRAFT','SCHEDULED','COMPLETED','CANCELLED'),gc.StartsAt DESC"
);
$pageTitle = 'Classes';
$pageSubtitle = 'Review and monitor the class schedule';
include V3_ROOT . '/includes/header.php';
?>
<section aria-labelledby="class-schedule-title">
<h2 class="sr-only" id="class-schedule-title">Class schedule and review queue</h2>
<div class="table-wrap"><table>
  <caption class="sr-only">Class schedule and review queue</caption>
  <thead><tr><th>Class</th><th>Trainer</th><th>Schedule</th><th>Enrollment</th><th>Status / Review</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row):
      $className = (string) $row['Name'];
      $trainerName = (string) ($row['TrainerName'] ?? 'Unassigned');
      $schedule = date('d M Y, H:i', strtotime((string) $row['StartsAt'])) . '–' . date('H:i', strtotime((string) $row['EndsAt']));
  ?>
    <tr>
      <td><strong><?= e($className) ?></strong><br><small><?= e($row['Location'] ?? '—') ?></small></td>
      <td><?= e($trainerName) ?></td>
      <td><?= e($row['StartsAt']) ?> – <?= e($row['EndsAt']) ?></td>
      <td><?= (int) $row['EnrolledCount'] ?>/<?= (int) $row['Capacity'] ?></td>
      <td>
        <span class="status-badge status-<?= e(strtolower((string) $row['Status'])) ?>"><?= e(ucwords(strtolower((string) $row['Status']))) ?></span>
        <?php if ($row['Status'] === 'DRAFT'): ?>
          <div class="row-actions">
            <button
              class="btn btn-success"
              type="button"
              data-confirm-open="class-review-dialog"
              data-record-id="<?= (int) $row['GymClassID'] ?>"
              data-decision="SCHEDULED"
              data-confirm-title="Approve class request?"
              data-confirm-description="<?= e("{$className} by {$trainerName}, scheduled {$schedule}, will become available for member enrollment.") ?>"
              data-confirm-label="Approve class"
              data-confirm-tone="success"
              data-detail-visible="true"
              data-detail-label="Review notes"
              data-detail-hint="Notes are optional."
            >Approve</button>
            <button
              class="btn btn-danger"
              type="button"
              data-confirm-open="class-review-dialog"
              data-record-id="<?= (int) $row['GymClassID'] ?>"
              data-decision="CANCELLED"
              data-confirm-title="Reject class request?"
              data-confirm-description="<?= e("{$className} by {$trainerName}, scheduled {$schedule}, will be cancelled and will not open for enrollment.") ?>"
              data-confirm-label="Reject class"
              data-confirm-tone="danger"
              data-detail-visible="true"
              data-detail-label="Review notes"
              data-detail-hint="Add an optional explanation for the review record."
            >Reject</button>
          </div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5">No classes.</td></tr><?php endif; ?>
  </tbody>
</table></div>
</section>

<dialog class="review-dialog" id="class-review-dialog" aria-labelledby="class-review-title" aria-describedby="class-review-description" data-confirm-dialog>
  <form method="post" data-confirm-form>
    <?= csrf_field() ?>
    <input type="hidden" name="class_id" value="" data-confirm-record>
    <input type="hidden" name="decision" value="" data-confirm-decision>
    <div class="dialog-icon" aria-hidden="true">?</div>
    <h2 id="class-review-title" data-confirm-title>Review class request</h2>
    <p id="class-review-description" data-confirm-description></p>
    <label class="dialog-reason" for="class-review-notes" data-confirm-detail-group hidden>
      <span data-confirm-detail-label>Review notes</span>
      <textarea id="class-review-notes" name="notes" maxlength="500" placeholder="Optional notes about this decision" data-confirm-detail></textarea>
      <span class="field-hint" data-confirm-detail-hint>Notes are optional.</span>
    </label>
    <div class="dialog-actions">
      <button class="btn btn-secondary" type="button" data-confirm-cancel>Cancel</button>
      <button class="btn" type="submit" data-confirm-submit>Confirm</button>
    </div>
  </form>
</dialog>
<?php include V3_ROOT . '/includes/footer.php'; ?>
