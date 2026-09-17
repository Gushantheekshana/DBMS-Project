<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\AttendanceService;

require_role('TRAINER');
$actorId = (int) current_user()['UserAccountID'];
$classId = (int) ($_POST['class_id'] ?? $_GET['class_id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $statuses = is_array($_POST['statuses'] ?? null) ? $_POST['statuses'] : [];
        AttendanceService::markClass($actorId, $classId, $statuses);
        flash('success', count($statuses) === 1 ? 'Attendance saved for 1 member.' : 'Attendance saved for ' . count($statuses) . ' members.');
        redirect('trainer/attendance.php?class_id=' . $classId);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$classes = db_all(
    "SELECT gc.GymClassID,gc.Name,gc.StartsAt FROM GYM_CLASS gc JOIN TRAINER t ON t.TrainerID=gc.TrainerID WHERE t.UserAccountID=? ORDER BY gc.StartsAt DESC",
    'i',
    [$actorId]
);
$roster = $classId ? db_all(
    "SELECT ce.ClassEnrollmentID,m.MemberNumber,CONCAT(m.FirstName,' ',m.LastName) MemberName,ca.Status FROM CLASS_ENROLLMENT ce JOIN GYM_CLASS gc ON gc.GymClassID=ce.GymClassID JOIN TRAINER t ON t.TrainerID=gc.TrainerID JOIN MEMBER m ON m.MemberID=ce.MemberID LEFT JOIN CLASS_ATTENDANCE ca ON ca.ClassEnrollmentID=ce.ClassEnrollmentID WHERE ce.GymClassID=? AND t.UserAccountID=? AND ce.Status='ENROLLED' ORDER BY m.LastName,m.FirstName",
    'ii',
    [$classId, $actorId]
) : [];
$pageTitle = 'Class attendance';
$pageSubtitle = 'Mark attendance for your roster';
include V3_ROOT . '/includes/header.php';
?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="filter-bar">
  <label for="class-filter">Class
    <select id="class-filter" name="class_id">
      <option value="">Select a class</option>
      <?php foreach ($classes as $class): ?><option value="<?= (int) $class['GymClassID'] ?>"<?= $classId === (int) $class['GymClassID'] ? ' selected' : '' ?>><?= e($class['Name'] . ' · ' . $class['StartsAt']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="btn btn-secondary" type="submit">Load class</button>
</form>
<?php if ($classId): ?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="class_id" value="<?= $classId ?>">
  <div class="table-wrap"><table>
    <caption class="sr-only">Class attendance roster</caption>
    <thead><tr><th>Member</th><th>Attendance status</th></tr></thead>
    <tbody>
    <?php foreach ($roster as $row): $labelId = 'member-' . (int) $row['ClassEnrollmentID']; ?>
      <tr>
        <td><strong id="<?= e($labelId) ?>"><?= e($row['MemberName']) ?></strong><br><small><?= e($row['MemberNumber']) ?></small></td>
        <td><select name="statuses[<?= (int) $row['ClassEnrollmentID'] ?>]" aria-labelledby="<?= e($labelId) ?>">
          <?php foreach (['PRESENT', 'ABSENT', 'LATE', 'EXCUSED'] as $status): ?><option<?= $row['Status'] === $status ? ' selected' : '' ?>><?= e(ucwords(strtolower($status))) ?></option><?php endforeach; ?>
        </select></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$roster): ?><tr><td colspan="2">This class has no enrolled members.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if ($roster): ?><button class="btn btn-primary" type="submit">Save attendance</button><?php endif; ?>
</form>
<?php endif; ?>
<?php include V3_ROOT . '/includes/footer.php'; ?>
