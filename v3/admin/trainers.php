<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\TrainerApplicationService;

require_role('ADMIN');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $trainerId = filter_var($_POST['trainer_id'] ?? null, FILTER_VALIDATE_INT);
        if ($trainerId === false) {
            throw new InvalidArgumentException('Invalid trainer application.');
        }
        $decision = strtolower(trim((string) ($_POST['decision'] ?? '')));
        TrainerApplicationService::review(
            (int) current_user()['UserAccountID'],
            $trainerId,
            $decision,
            (string) ($_POST['reason'] ?? '')
        );
        flash('success', $decision === 'approve' ? 'Trainer application approved.' : 'Trainer application rejected.');
    } catch (Throwable $exception) {
        flash('danger', $exception->getMessage());
    }
    redirect('admin/trainers.php');
}

$status = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['PENDING', 'ACTIVE', 'INACTIVE', 'ARCHIVED', 'REJECTED'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$sql = "SELECT t.TrainerID,t.TrainerNumber,t.FirstName,t.LastName,t.Phone,t.Specialization,t.Bio,
               t.HireDate,t.Status,t.CreatedAt,t.ReviewedAt,t.RejectionReason,
               u.Email,u.Status AS AccountStatus,
               (SELECT COUNT(*) FROM GYM_CLASS gc WHERE gc.TrainerID=t.TrainerID) AS ClassCount
          FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID";
$types = '';
$params = [];
if ($status !== '') {
    $sql .= ' WHERE t.Status=?';
    $types = 's';
    $params[] = $status;
}
$sql .= " ORDER BY CASE WHEN t.Status='PENDING' AND u.Status='PENDING' THEN 0 ELSE 1 END,
                  CASE WHEN t.Status='PENDING' THEN t.CreatedAt END ASC,
                  t.Status,t.LastName,t.FirstName";
$rows = db_all($sql, $types, $params);
$pendingCount = (int) (db_one(
    "SELECT COUNT(*) AS Total FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID WHERE t.Status='PENDING' AND u.Status='PENDING'"
)['Total'] ?? 0);

$pageTitle = 'Trainer requests';
$pageSubtitle = $pendingCount === 1 ? '1 application awaiting review' : "{$pendingCount} applications awaiting review";
include V3_ROOT . '/includes/header.php';
?>
<section class="panel request-summary" aria-labelledby="request-summary-title">
  <div>
    <p class="eyebrow">Application queue</p>
    <h2 id="request-summary-title"><?= $pendingCount ?> pending trainer request<?= $pendingCount === 1 ? '' : 's' ?></h2>
    <p>Approve an applicant to activate their trainer login and allow them to create classes.</p>
  </div>
  <?php if ($pendingCount > 0): ?><a class="btn btn-secondary" href="<?= e(base_url('admin/trainers.php?status=PENDING')) ?>">Review pending</a><?php endif; ?>
</section>

<form class="filter-bar" method="get">
  <label for="status-filter">Application status
    <select id="status-filter" name="status">
      <option value="">All trainers</option>
      <?php foreach ($allowedStatuses as $item): ?>
        <option value="<?= e($item) ?>"<?= $status === $item ? ' selected' : '' ?>><?= e(ucwords(strtolower($item))) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-secondary" type="submit">Apply filter</button>
</form>

<section aria-labelledby="trainer-directory-title">
<h2 class="sr-only" id="trainer-directory-title">Trainer application directory</h2>
<div class="table-wrap"><table>
  <caption class="sr-only">Trainer application directory</caption>
  <thead><tr><th>Applicant</th><th>Contact</th><th>Practice</th><th>Submitted</th><th>Classes</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row):
      $isPending = $row['Status'] === 'PENDING' && $row['AccountStatus'] === 'PENDING';
      $name = trim($row['FirstName'] . ' ' . $row['LastName']);
  ?>
    <tr<?= $isPending ? ' class="pending-row"' : '' ?>>
      <td><strong><?= e($name) ?></strong><br><small><?= e($row['TrainerNumber']) ?></small><?php if ($row['Bio']): ?><p class="table-note"><?= e($row['Bio']) ?></p><?php endif; ?></td>
      <td><?= e($row['Email']) ?><br><small><?= e($row['Phone'] ?: 'No phone provided') ?></small></td>
      <td><?= e($row['Specialization'] ?: 'Not specified') ?></td>
      <td><?= e(date('d M Y', strtotime((string) $row['CreatedAt']))) ?></td>
      <td><?= (int) $row['ClassCount'] ?></td>
      <td>
        <span class="status-badge status-<?= e(strtolower($row['Status'])) ?>"><?= e(ucwords(strtolower($row['Status']))) ?></span>
        <?php if ($row['RejectionReason']): ?><small class="status-detail">Reason: <?= e($row['RejectionReason']) ?></small><?php endif; ?>
      </td>
      <td>
        <?php if ($isPending): ?>
          <div class="row-actions">
            <button class="btn btn-success" type="button" data-confirm-open="trainer-review-dialog" data-record-id="<?= (int) $row['TrainerID'] ?>" data-decision="approve" data-confirm-title="Approve trainer application?" data-confirm-description="<?= e($name) ?> will be able to sign in and start creating classes." data-confirm-label="Approve trainer" data-confirm-tone="success">Approve</button>
            <button class="btn btn-danger" type="button" data-confirm-open="trainer-review-dialog" data-record-id="<?= (int) $row['TrainerID'] ?>" data-decision="reject" data-confirm-title="Reject trainer application?" data-confirm-description="<?= e($name) ?> will remain unable to sign in. They can submit a revised application later." data-confirm-label="Reject application" data-confirm-tone="danger" data-detail-visible="true" data-detail-required="true" data-detail-label="Reason for rejection" data-detail-hint="The applicant can revise their details and submit again.">Reject</button>
          </div>
        <?php else: ?>
          <span class="muted">Reviewed</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">No trainers found for this filter.</td></tr><?php endif; ?>
  </tbody>
</table></div>
</section>

<dialog class="review-dialog" id="trainer-review-dialog" aria-labelledby="trainer-review-title" aria-describedby="trainer-review-description" data-confirm-dialog>
  <form method="post" data-confirm-form>
    <?= csrf_field() ?>
    <input type="hidden" name="trainer_id" value="" data-confirm-record>
    <input type="hidden" name="decision" value="" data-confirm-decision>
    <div class="dialog-icon" aria-hidden="true">?</div>
    <h2 id="trainer-review-title" data-confirm-title>Review trainer application</h2>
    <p id="trainer-review-description" data-confirm-description></p>
    <label class="dialog-reason" for="trainer-review-reason" data-confirm-detail-group hidden>
      <span data-confirm-detail-label>Reason for rejection</span>
      <textarea id="trainer-review-reason" name="reason" maxlength="500" placeholder="Explain why this application was not approved" data-confirm-detail></textarea>
      <span class="field-hint" data-confirm-detail-hint>The applicant can revise their details and submit again.</span>
    </label>
    <div class="dialog-actions">
      <button class="btn btn-secondary" type="button" data-confirm-cancel>Cancel</button>
      <button class="btn" type="submit" data-confirm-submit>Confirm</button>
    </div>
  </form>
</dialog>
<?php include V3_ROOT . '/includes/footer.php'; ?>
