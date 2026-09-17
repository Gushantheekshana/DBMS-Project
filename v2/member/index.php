<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';

require_role('MEMBER');
$user = current_user();
$actorId = (int) $user['UserAccountID'];
$member = db_one(
    "SELECT m.MemberID,m.MemberNumber,m.FirstName,m.LastName,m.Status,
            ms.MembershipID,ms.PlanNameSnapshot,ms.Status AS MembershipStatus,ms.EndsOn
       FROM MEMBER m
       LEFT JOIN MEMBERSHIP ms ON ms.MembershipID=(SELECT x.MembershipID FROM MEMBERSHIP x WHERE x.MemberID=m.MemberID ORDER BY x.MembershipID DESC LIMIT 1)
      WHERE m.UserAccountID=?",
    'i', [$actorId]
);
$upcoming = $member ? db_all(
    "SELECT gc.Name,gc.StartsAt,gc.Location,ce.Status
       FROM CLASS_ENROLLMENT ce JOIN GYM_CLASS gc ON gc.GymClassID=ce.GymClassID
      WHERE ce.MemberID=? AND ce.Status IN ('ENROLLED','WAITLISTED') AND gc.StartsAt>=NOW()
      ORDER BY gc.StartsAt LIMIT 5",
    'i', [(int) $member['MemberID']]
) : [];
$pageTitle = 'Member dashboard';
$pageSubtitle = 'Your membership and upcoming classes';
include V2_ROOT . '/includes/header.php';
?>
<section class="stats-grid">
  <article class="stat-card"><span>Member</span><strong><?= e($member ? $member['FirstName'].' '.$member['LastName'] : 'Profile unavailable') ?></strong><small><?= e($member['MemberNumber'] ?? '') ?></small></article>
  <article class="stat-card"><span>Membership</span><strong><?= e($member['MembershipStatus'] ?? 'NONE') ?></strong><small><?= e($member['PlanNameSnapshot'] ?? 'Choose a plan') ?></small></article>
  <article class="stat-card"><span>Ends</span><strong><?= e($member['EndsOn'] ?? '—') ?></strong><small><?= count($upcoming) ?> upcoming</small></article>
</section>
<section class="panel"><h2>Upcoming classes</h2><div class="table-wrap"><table><thead><tr><th>Class</th><th>Starts</th><th>Location</th><th>Status</th></tr></thead><tbody>
<?php foreach ($upcoming as $class): ?><tr><td><?= e($class['Name']) ?></td><td><?= e($class['StartsAt']) ?></td><td><?= e($class['Location'] ?? '—') ?></td><td><?= e($class['Status']) ?></td></tr><?php endforeach; ?>
<?php if (!$upcoming): ?><tr><td colspan="4">No upcoming classes.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php include V2_ROOT . '/includes/footer.php'; ?>
