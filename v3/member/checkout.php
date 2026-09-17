<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\MembershipService;

require_role('MEMBER');
$user = current_user();
$actorId = (int) $user['UserAccountID'];
$membershipId = (int) ($_POST['membership_id'] ?? $_GET['membership_id'] ?? 0);
$error = '';
$receipt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $receipt = MembershipService::pay($actorId, $membershipId, $_POST);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$membership = db_one(
    "SELECT ms.MembershipID,ms.PlanNameSnapshot,ms.PriceSnapshot,ms.CurrencySnapshot,ms.Status FROM MEMBERSHIP ms JOIN MEMBER m ON m.MemberID=ms.MemberID WHERE ms.MembershipID=? AND m.UserAccountID=?",
    'ii',
    [$membershipId, $actorId]
);
if (!$membership) {
    http_response_code(404);
    exit('Membership not found.');
}
$pageTitle = 'Checkout';
$pageSubtitle = 'Complete your membership payment';
include V3_ROOT . '/includes/header.php';
?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($receipt): ?>
  <div class="alert alert-success" role="status">Payment recorded. Reference: <?= e($receipt['TransactionReference'] ?? '') ?></div>
<?php else: ?>
<section class="panel">
  <h2><?= e($membership['PlanNameSnapshot']) ?></h2>
  <p>Total: <strong><?= e($membership['CurrencySnapshot']) ?> <?= e(number_format((float) $membership['PriceSnapshot'], 2)) ?></strong></p>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="membership_id" value="<?= $membershipId ?>">
    <label for="payment-method">Payment method<select id="payment-method" name="method" required><option value="CASH">Cash</option><option value="CARD">Card</option><option value="BANK_TRANSFER">Bank transfer</option><option value="OTHER">Other</option></select></label>
    <label for="transaction-reference">Transaction reference <span class="muted">(optional)</span><input id="transaction-reference" name="transaction_reference" maxlength="100"></label>
    <label for="payment-notes">Notes <span class="muted">(optional)</span><textarea id="payment-notes" name="notes" maxlength="500"></textarea></label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Pay now</button></div>
  </form>
</section>
<?php endif; ?>
<?php include V3_ROOT . '/includes/footer.php'; ?>
