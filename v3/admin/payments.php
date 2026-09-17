<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
require_role('ADMIN');

$status = (string) ($_GET['status'] ?? '');
$sql = "SELECT mp.MembershipPaymentID,mp.Amount,mp.Currency,mp.Status,mp.Method,mp.TransactionReference,mp.PaidAt,mp.CreatedAt,m.MemberNumber,CONCAT(m.FirstName,' ',m.LastName) MemberName,ms.PlanNameSnapshot FROM MEMBERSHIP_PAYMENT mp JOIN MEMBERSHIP ms ON ms.MembershipID=mp.MembershipID JOIN MEMBER m ON m.MemberID=ms.MemberID";
$types = '';
$params = [];
if (in_array($status, ['PENDING', 'PAID', 'FAILED', 'REFUNDED', 'VOID'], true)) {
    $sql .= ' WHERE mp.Status=?';
    $types = 's';
    $params = [$status];
}
$sql .= ' ORDER BY mp.CreatedAt DESC LIMIT 500';
$rows = db_all($sql, $types, $params);
$totals = db_all('SELECT Currency,Status,COUNT(*) PaymentCount,COALESCE(SUM(Amount),0) Total FROM MEMBERSHIP_PAYMENT GROUP BY Currency,Status ORDER BY Currency,Status');
$pageTitle = 'Payments';
$pageSubtitle = 'Membership payment ledger';
include V3_ROOT . '/includes/header.php';
?>
<?php if ($totals): ?><section class="stats-grid" aria-label="Payment totals"><?php foreach ($totals as $total): ?><article class="stat-card"><span><?= e(ucwords(strtolower($total['Status']))) ?> (<?= (int) $total['PaymentCount'] ?>)</span><strong><?= e($total['Currency']) ?> <?= e(number_format((float) $total['Total'], 2)) ?></strong></article><?php endforeach; ?></section><?php endif; ?>
<form method="get" class="filter-bar">
  <label for="payment-status">Status
    <select id="payment-status" name="status"><option value="">All</option><?php foreach (['PENDING', 'PAID', 'FAILED', 'REFUNDED', 'VOID'] as $item): ?><option value="<?= e($item) ?>"<?= $status === $item ? ' selected' : '' ?>><?= e(ucwords(strtolower($item))) ?></option><?php endforeach; ?></select>
  </label>
  <button class="btn btn-secondary" type="submit">Apply filter</button>
</form>
<section aria-labelledby="payment-ledger-title">
  <h2 class="sr-only" id="payment-ledger-title">Payment ledger</h2>
  <div class="table-wrap"><table>
    <caption class="sr-only">Membership payment ledger</caption>
    <thead><tr><th>Payment</th><th>Member</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Paid</th></tr></thead>
    <tbody><?php foreach ($rows as $row): ?><tr><td>#<?= (int) $row['MembershipPaymentID'] ?><br><small><?= e($row['TransactionReference'] ?: 'No reference') ?></small></td><td><?= e($row['MemberName']) ?><br><small><?= e($row['MemberNumber']) ?></small></td><td><?= e($row['PlanNameSnapshot']) ?></td><td><?= e($row['Currency']) ?> <?= e(number_format((float) $row['Amount'], 2)) ?></td><td><?= e(ucwords(strtolower(str_replace('_', ' ', $row['Method'])))) ?></td><td><span class="status-badge status-<?= e(strtolower($row['Status'])) ?>"><?= e(ucwords(strtolower($row['Status']))) ?></span></td><td><?= e($row['PaidAt'] ?? '—') ?></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7"><?= $status !== '' ? 'No payments match this status. Choose All to clear the filter.' : 'No payment activity yet.' ?></td></tr><?php endif; ?></tbody>
  </table></div>
</section>
<?php include V3_ROOT . '/includes/footer.php'; ?>
