<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
require_role('ADMIN');

$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$sql = "SELECT m.MemberNumber,m.FirstName,m.LastName,m.Phone,m.JoinedOn,m.Status,u.Email,u.Status AccountStatus,ms.PlanNameSnapshot,ms.Status MembershipStatus,ms.EndsOn FROM MEMBER m JOIN USER_ACCOUNT u ON u.UserAccountID=m.UserAccountID LEFT JOIN MEMBERSHIP ms ON ms.MembershipID=(SELECT x.MembershipID FROM MEMBERSHIP x WHERE x.MemberID=m.MemberID ORDER BY x.MembershipID DESC LIMIT 1) WHERE 1=1";
$types = '';
$params = [];
if ($q !== '') {
    $sql .= " AND (m.MemberNumber LIKE CONCAT('%',?,'%') OR m.FirstName LIKE CONCAT('%',?,'%') OR m.LastName LIKE CONCAT('%',?,'%') OR u.Email LIKE CONCAT('%',?,'%'))";
    $types .= 'ssss';
    array_push($params, $q, $q, $q, $q);
}
if (in_array($status, ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'ARCHIVED'], true)) {
    $sql .= ' AND m.Status=?';
    $types .= 's';
    $params[] = $status;
}
$sql .= ' ORDER BY m.LastName,m.FirstName LIMIT 500';
$rows = db_all($sql, $types, $params);
$pageTitle = 'Members';
$pageSubtitle = 'Member directory and membership status';
include V3_ROOT . '/includes/header.php';
?>
<form method="get" class="filter-bar" role="search">
  <label for="member-query">Search<input id="member-query" name="q" value="<?= e($q) ?>"></label>
  <label for="member-status">Status<select id="member-status" name="status"><option value="">All</option><?php foreach (['ACTIVE', 'INACTIVE', 'SUSPENDED', 'ARCHIVED'] as $item): ?><option value="<?= e($item) ?>"<?= $status === $item ? ' selected' : '' ?>><?= e(ucwords(strtolower($item))) ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-secondary" type="submit">Apply filters</button>
</form>
<section aria-labelledby="member-directory-title">
  <h2 class="sr-only" id="member-directory-title">Member directory</h2>
  <div class="table-wrap"><table>
    <caption class="sr-only">Member directory</caption>
    <thead><tr><th>Member</th><th>Contact</th><th>Account</th><th>Membership</th><th>Joined</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr><td><?= e($row['FirstName'] . ' ' . $row['LastName']) ?><br><small><?= e($row['MemberNumber']) ?> · <?= e(ucwords(strtolower($row['Status']))) ?></small></td><td><?= e($row['Email']) ?><br><small><?= e($row['Phone'] ?: 'No phone provided') ?></small></td><td><span class="status-badge status-<?= e(strtolower($row['AccountStatus'])) ?>"><?= e(ucwords(strtolower($row['AccountStatus']))) ?></span></td><td><?= e($row['PlanNameSnapshot'] ?? 'No plan') ?><?php if ($row['MembershipStatus']): ?> · <span class="status-badge status-<?= e(strtolower($row['MembershipStatus'])) ?>"><?= e(ucwords(strtolower($row['MembershipStatus']))) ?></span><?php endif; ?><br><small><?= e($row['EndsOn'] ?: 'No end date') ?></small></td><td><?= e($row['JoinedOn']) ?></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5"><?= $q !== '' || $status !== '' ? 'No members match these filters.' : 'No members have registered yet.' ?></td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php include V3_ROOT . '/includes/footer.php'; ?>
