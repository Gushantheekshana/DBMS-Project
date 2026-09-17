<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

use GymPro\V2\Services\MembershipService;

require_role('MEMBER');
$user = current_user();
$actorId = (int) $user['UserAccountID'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $membershipId = MembershipService::choosePlan($actorId, (int) ($_POST['plan_id'] ?? 0));
        redirect('member/checkout.php?membership_id=' . $membershipId);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$current = MembershipService::getCurrent($actorId);
$plans = db_all('SELECT MembershipPlanID,Name,Description,DurationMonths,Price,Currency,ClassLimit FROM MEMBERSHIP_PLAN WHERE IsActive=1 ORDER BY Price,Name');
$pageTitle = 'Membership';
$pageSubtitle = 'Choose and manage your plan';
include V2_ROOT . '/includes/header.php';
?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($current): ?><section class="panel"><h2>Current membership</h2><p><strong><?= e($current['PlanNameSnapshot']) ?></strong> · <?= e($current['Status']) ?> · <?= e($current['StartsOn']) ?> to <?= e($current['EndsOn']) ?></p></section><?php endif; ?>
<section class="card-grid">
<?php foreach ($plans as $plan): ?><article class="card"><h2><?= e($plan['Name']) ?></h2><p><?= e($plan['Description'] ?? '') ?></p><p><strong><?= e($plan['Currency']) ?> <?= e(number_format((float) $plan['Price'], 2)) ?></strong> / <?= (int) $plan['DurationMonths'] ?> month(s)</p><p><?= $plan['ClassLimit'] === null ? 'Unlimited classes' : (int) $plan['ClassLimit'].' classes' ?></p><form method="post"><?= csrf_field() ?><input type="hidden" name="plan_id" value="<?= (int) $plan['MembershipPlanID'] ?>"><button type="submit" class="btn btn-primary">Choose plan</button></form></article><?php endforeach; ?>
<?php if (!$plans): ?><p>No plans are currently available.</p><?php endif; ?>
</section>
<?php include V2_ROOT . '/includes/footer.php'; ?>
