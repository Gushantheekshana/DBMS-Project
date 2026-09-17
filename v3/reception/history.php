<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
require_role('RECEPTIONIST');

$q = trim((string) ($_GET['q'] ?? ''));
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');
$error = '';
foreach (['from' => $from, 'to' => $to] as $label => $value) {
    if ($value !== '' && (DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') !== $value)) {
        $error = ucfirst($label) . ' date is invalid.';
    }
}
if ($error === '' && $from !== '' && $to !== '' && $from > $to) {
    $error = 'From date cannot be after to date.';
}
$sql = "SELECT gv.CheckedInAt,gv.CheckedOutAt,gv.Notes,m.MemberNumber,CONCAT(m.FirstName,' ',m.LastName) Name FROM GYM_VISIT gv JOIN MEMBER m ON m.MemberID=gv.MemberID WHERE 1=1";
$types = '';
$params = [];
if ($q !== '') {
    $sql .= " AND (m.MemberNumber LIKE CONCAT('%',?,'%') OR m.FirstName LIKE CONCAT('%',?,'%') OR m.LastName LIKE CONCAT('%',?,'%'))";
    $types .= 'sss';
    array_push($params, $q, $q, $q);
}
if ($error === '' && $from !== '') {
    $sql .= ' AND gv.CheckedInAt>=?';
    $types .= 's';
    $params[] = $from . ' 00:00:00';
}
if ($error === '' && $to !== '') {
    $sql .= ' AND gv.CheckedInAt<?';
    $types .= 's';
    $params[] = (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d 00:00:00');
}
$sql .= ' ORDER BY gv.CheckedInAt DESC LIMIT 500';
$visits = $error === '' ? db_all($sql, $types, $params) : [];
$pageTitle = 'Visit history';
$pageSubtitle = 'Search gym check-ins and check-outs';
include V3_ROOT . '/includes/header.php';
?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="get" class="filter-bar" role="search">
  <label for="visit-member">Member<input id="visit-member" name="q" value="<?= e($q) ?>"></label>
  <label for="visit-from">From<input id="visit-from" type="date" name="from" value="<?= e($from) ?>"></label>
  <label for="visit-to">To<input id="visit-to" type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn btn-secondary" type="submit">Apply filters</button>
</form>
<section aria-labelledby="visit-history-title">
  <h2 class="sr-only" id="visit-history-title">Gym visit history</h2>
  <div class="table-wrap"><table>
    <caption class="sr-only">Gym visit history</caption>
    <thead><tr><th>Member</th><th>Checked in</th><th>Checked out</th><th>Notes</th></tr></thead>
    <tbody><?php foreach ($visits as $visit): ?><tr><td><?= e($visit['Name']) ?><br><small><?= e($visit['MemberNumber']) ?></small></td><td><?= e($visit['CheckedInAt']) ?></td><td><?= e($visit['CheckedOutAt'] ?? 'Still inside') ?></td><td><?= e($visit['Notes'] ?: '—') ?></td></tr><?php endforeach; ?><?php if (!$visits): ?><tr><td colspan="4"><?= $q !== '' || $from !== '' || $to !== '' ? 'No visits match these filters.' : 'No gym visits have been recorded yet.' ?></td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php include V3_ROOT . '/includes/footer.php'; ?>
