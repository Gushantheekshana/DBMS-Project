<?php
require_once 'config.php';
$pageTitle = 'Member Profile';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$m  = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT m.*, p.PlanName, p.Duration, p.Price FROM MEMBER m
     LEFT JOIN MEMBERSHIP_PLAN p ON m.PlanID=p.PlanID WHERE m.MemberID=$id"));
if (!$m) { set_flash('danger','Member not found.'); header('Location:members.php'); exit; }

$classes   = mysqli_query($conn,
    "SELECT c.ClassName, c.DateOfClass, c.StartTime FROM ENROLLMENT e
     JOIN CLASS c ON e.ClassID=c.ClassID WHERE e.MemberID=$id ORDER BY c.DateOfClass DESC");
$attend    = mysqli_query($conn,
    "SELECT a.Date, a.Status, c.ClassName FROM ATTENDANCE a
     JOIN CLASS c ON a.ClassID=c.ClassID WHERE a.MemberID=$id ORDER BY a.Date DESC LIMIT 10");
$payments  = mysqli_query($conn,
    "SELECT PaymentDate, Amount, PaymentMethod, PaymentType FROM PAYMENT WHERE MemberID=$id ORDER BY PaymentDate DESC LIMIT 10");
$requests  = mysqli_query($conn,
    "SELECT tr.RequestDate, tr.Status, t.Name AS Trainer FROM TRAINER_REQUEST tr
     JOIN TRAINER t ON tr.TrainerID=t.TrainerID WHERE tr.MemberID=$id ORDER BY tr.RequestDate DESC");

$active = ($m['PlanEndDate'] && $m['PlanEndDate'] >= date('Y-m-d'));
include 'includes/header.php';
?>

<div style="display:flex;gap:20px;flex-wrap:wrap;">
    <!-- Profile card -->
    <div style="flex:0 0 280px;">
        <div class="dash-card" style="padding:24px;">
            <div style="font-size:2.5rem;font-weight:800;letter-spacing:-0.05em;color:var(--blue);margin-bottom:8px;">
                <?= strtoupper(substr($m['Name'],0,1)) ?>
            </div>
            <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($m['Name']) ?></div>
            <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:16px;">Member #<?= $m['MemberID'] ?></div>
            <table style="font-size:0.8rem;width:100%;border-collapse:collapse;">
                <?php $rows = [
                    ['NIC',        $m['NIC']],
                    ['DOB',        $m['DOB']    ?? '—'],
                    ['Age',        $m['Age']    ?? '—'],
                    ['Gender',     $m['Gender'] ?? '—'],
                    ['Phone',      $m['PhoneNo'] ?? '—'],
                    ['Email',      $m['Email']   ?? '—'],
                    ['Address',    $m['Address'] ?? '—'],
                    ['Registered', $m['RegDate']],
                ];
                foreach ($rows as [$label, $val]): ?>
                <tr>
                    <td style="color:var(--text-muted);padding:4px 0;width:80px;"><?= $label ?></td>
                    <td style="padding:4px 0;"><?= htmlspecialchars($val) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="dash-card" style="padding:20px;margin-top:16px;">
            <div style="font-size:0.8rem;font-weight:700;margin-bottom:12px;">Membership</div>
            <?php if ($m['PlanName']): ?>
            <div style="font-size:1rem;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($m['PlanName']) ?></div>
            <div style="font-size:0.78rem;color:var(--text-muted);">Rs.<?= number_format($m['Price'],2) ?> / <?= $m['Duration'] ?> mo</div>
            <div style="font-size:0.78rem;margin-top:8px;">
                <?= $m['PlanStartDate'] ?> → <?= $m['PlanEndDate'] ?>
            </div>
            <div style="margin-top:10px;">
                <?= $active ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-red">Expired</span>' ?>
            </div>
            <?php else: ?>
            <span class="badge badge-grey">No plan assigned</span>
            <?php endif; ?>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
            <a href="member_edit.php?id=<?= $id ?>" class="btn btn-primary btn-sm">Edit</a>
            <a href="member_delete.php?id=<?= $id ?>" class="btn btn-danger btn-sm">Delete</a>
        </div>
    </div>

    <!-- Right panel -->
    <div style="flex:1;min-width:320px;display:flex;flex-direction:column;gap:16px;">
        <!-- Enrolled classes -->
        <div class="dash-card">
            <div class="dash-card-title">Enrolled Classes</div>
            <table class="data-table">
                <thead><tr><th>Class</th><th>Date</th><th>Time</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($classes)===0): ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:16px;">No enrollments yet</td></tr>
                <?php endif; ?>
                <?php while ($r = mysqli_fetch_assoc($classes)): ?>
                <tr>
                    <td><?= htmlspecialchars($r['ClassName']) ?></td>
                    <td><?= $r['DateOfClass'] ?></td>
                    <td><?= substr($r['StartTime'],0,5) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Attendance -->
        <div class="dash-card">
            <div class="dash-card-title">Attendance (last 10)</div>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($attend)===0): ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:16px;">No records</td></tr>
                <?php endif; ?>
                <?php while ($r = mysqli_fetch_assoc($attend)): ?>
                <tr>
                    <td><?= $r['Date'] ?></td>
                    <td><?= htmlspecialchars($r['ClassName']) ?></td>
                    <td><?= $r['Status']==='Present'
                        ? '<span class="badge badge-green">Present</span>'
                        : '<span class="badge badge-red">Absent</span>' ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Payments -->
        <div class="dash-card">
            <div class="dash-card-title">Payments (last 10)</div>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Type</th><th>Method</th><th>Amount</th></tr></thead>
                <tbody>
                <?php if (mysqli_num_rows($payments)===0): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:16px;">No payments</td></tr>
                <?php endif; ?>
                <?php while ($r = mysqli_fetch_assoc($payments)): ?>
                <tr>
                    <td><?= $r['PaymentDate'] ?></td>
                    <td><?= htmlspecialchars($r['PaymentType']) ?></td>
                    <td><?= htmlspecialchars($r['PaymentMethod'] ?? '—') ?></td>
                    <td style="font-weight:600;">Rs.<?= number_format($r['Amount'],2) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
