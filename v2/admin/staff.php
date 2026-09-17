<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\StaffAccountService;

require_role('ADMIN');
$actorId = (int) current_user()['UserAccountID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? 'create';

    if ($action === 'remove') {
        try {
            $targetId = filter_var($_POST['user_account_id'] ?? null, FILTER_VALIDATE_INT);
            if (!is_int($targetId) || $targetId <= 0) {
                throw new RuntimeException('Invalid staff account selected.');
            }

            $outcome = StaffAccountService::remove($actorId, $targetId);
            if ($outcome === 'disabled') {
                flash('info', 'Staff account has recorded operational activity and was disabled instead of deleted to preserve history.');
            } else {
                flash('success', 'Staff account removed successfully.');
            }
        } catch (RuntimeException $exception) {
            flash('danger', $exception->getMessage());
        } catch (Throwable) {
            flash('danger', 'The staff account could not be removed. Please try again.');
        }

        redirect('admin/staff.php');
    }

    try {
        $password = $_POST['password'] ?? null;
        $confirmation = $_POST['password_confirmation'] ?? null;
        if (!is_string($password) || !is_string($confirmation) || !hash_equals($password, $confirmation)) {
            throw new RuntimeException('Passwords do not match.');
        }

        StaffAccountService::create($actorId, $_POST);
        clear_old_input();
        flash('success', 'Staff account created successfully.');
    } catch (RuntimeException $exception) {
        flash_input([
            'email' => is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
            'role' => is_string($_POST['role'] ?? null) ? $_POST['role'] : '',
        ]);
        flash('danger', $exception->getMessage());
    } catch (Throwable) {
        flash_input([
            'email' => is_string($_POST['email'] ?? null) ? $_POST['email'] : '',
            'role' => is_string($_POST['role'] ?? null) ? $_POST['role'] : '',
        ]);
        flash('danger', 'The staff account could not be created. Please try again.');
    }

    redirect('admin/staff.php');
}

$rows = db_all(
    "SELECT UserAccountID,Email,Role,Status,EmailVerifiedAt,LastLoginAt,CreatedAt
       FROM USER_ACCOUNT
      WHERE Role IN ('ADMIN','RECEPTIONIST')
      ORDER BY Role,Email"
);
$oldRole = $_SESSION['_old']['role'] ?? 'RECEPTIONIST';
$oldRole = is_string($oldRole) && in_array($oldRole, ['ADMIN', 'RECEPTIONIST'], true)
    ? $oldRole
    : 'RECEPTIONIST';
$pageTitle = 'Staff';
$pageSubtitle = 'Administrative and reception accounts';
include V2_ROOT . '/includes/header.php';
?>
<section class="panel" aria-labelledby="create-staff-title">
  <div class="section-heading">
    <div>
      <h2 id="create-staff-title">Create a staff account</h2>
      <p>Give an administrator or receptionist immediate access to their workspace.</p>
    </div>
  </div>
  <form class="form-grid staff-create-form" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label for="email">Email address
      <input id="email" name="email" type="email" value="<?= old('email') ?>" autocomplete="email" maxlength="254" required autofocus>
    </label>
    <label for="role">Account role
      <select id="role" name="role" required>
        <option value="ADMIN"<?= $oldRole === 'ADMIN' ? ' selected' : '' ?>>Administrative</option>
        <option value="RECEPTIONIST"<?= $oldRole === 'RECEPTIONIST' ? ' selected' : '' ?>>Receptionist</option>
      </select>
      <span class="field-hint">Administrative accounts have full access to this workspace.</span>
    </label>
    <label for="password">Password
      <span class="password-row">
        <input id="password" name="password" type="password" minlength="10" maxlength="4096" autocomplete="new-password" aria-describedby="staff-password-hint" required>
        <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password">Show</button>
      </span>
      <span class="field-hint" id="staff-password-hint">Use at least 10 characters.</span>
    </label>
    <label for="password_confirmation">Confirm password
      <input id="password_confirmation" name="password_confirmation" type="password" minlength="10" maxlength="4096" autocomplete="new-password" required>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Create staff account</button>
    </div>
  </form>
</section>

<div class="table-wrap"><table>
  <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Verified</th><th>Last login</th><th>Created</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row):
      $targetAccountId = (int) $row['UserAccountID'];
      $isProtectedDemo = strtolower((string) $row['Email']) === 'admin.demo@example.test';
      $isCurrentUser = $targetAccountId === $actorId;
      $isDisabled = $row['Status'] === 'DISABLED';
      $roleLabel = $row['Role'] === 'ADMIN' ? 'Administrative' : 'Receptionist';
  ?>
    <tr>
      <td><?= e($row['Email']) ?></td>
      <td><?= e($roleLabel) ?></td>
      <td><span class="status-badge status-<?= e(strtolower((string) $row['Status'])) ?>"><?= e(ucwords(strtolower((string) $row['Status']))) ?></span></td>
      <td><?= e($row['EmailVerifiedAt'] ?? '—') ?></td>
      <td><?= e($row['LastLoginAt'] ?? 'Never') ?></td>
      <td><?= e($row['CreatedAt']) ?></td>
      <td>
        <?php if ($isProtectedDemo): ?>
          <span class="muted">Protected</span>
        <?php elseif ($isCurrentUser): ?>
          <span class="muted">Current account</span>
        <?php elseif ($isDisabled): ?>
          <span class="muted">Disabled</span>
        <?php else: ?>
          <div class="row-actions">
            <button
              class="btn btn-danger"
              type="button"
              data-confirm-open="staff-remove-dialog"
              data-record-id="<?= $targetAccountId ?>"
              data-confirm-title="Remove staff account?"
              data-confirm-description="<?= e("Are you sure you want to remove {$row['Email']} ({$roleLabel})? If this account has recorded visits, payments, or attendance, it will be disabled to preserve audit history; otherwise it will be permanently deleted.") ?>"
              data-confirm-label="Remove account"
              data-confirm-tone="danger"
            >Remove</button>
          </div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7">No staff accounts.</td></tr><?php endif; ?>
  </tbody>
</table></div>

<dialog class="review-dialog" id="staff-remove-dialog" aria-labelledby="staff-remove-title" aria-describedby="staff-remove-description" data-confirm-dialog>
  <form method="post" data-confirm-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="remove">
    <input type="hidden" name="user_account_id" value="" data-confirm-record>
    <div class="dialog-icon" aria-hidden="true">!</div>
    <h2 id="staff-remove-title" data-confirm-title>Remove staff account</h2>
    <p id="staff-remove-description" data-confirm-description>Please confirm that you want to remove this staff account.</p>
    <div class="dialog-actions">
      <button class="btn btn-secondary" type="button" data-confirm-cancel>Cancel</button>
      <button class="btn btn-danger" type="submit" data-confirm-submit>Remove account</button>
    </div>
  </form>
</dialog>
<?php include V2_ROOT . '/includes/footer.php'; ?>
