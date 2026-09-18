<?php
require_once 'config.php';
$pageTitle = 'Edit Member';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM MEMBER WHERE MemberID=$id"));
if (!$member) { set_flash('danger', 'Member not found.'); header('Location: members.php'); exit; }

$plans  = mysqli_query($conn, "SELECT PlanID, PlanName, Duration, Price FROM MEMBERSHIP_PLAN ORDER BY PlanName");
$errors = [];
$vals   = $member;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['Name','NIC','DOB','Gender','Email','Address','PhoneNo','RegDate','PlanID','PlanStartDate'];
    foreach ($fields as $k) $vals[$k] = sanitize($conn, $_POST[$k] ?? '');

    if (!$vals['Name'])    $errors[] = 'Name is required.';
    if (!$vals['NIC'])     $errors[] = 'NIC is required.';

    // Age
    $age = '';
    if ($vals['DOB']) $age = (int) date_diff(date_create($vals['DOB']), date_create('today'))->y;

    // Plan end
    $planEndDate = 'NULL';
    if ($vals['PlanID'] && $vals['PlanStartDate']) {
        $dur = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT Duration FROM MEMBERSHIP_PLAN WHERE PlanID={$vals['PlanID']}"))['Duration'];
        $planEndDate = "'" . date('Y-m-d', strtotime("+{$dur} months", strtotime($vals['PlanStartDate']))) . "'";
    }

    // NIC uniqueness (exclude self)
    if ($vals['NIC']) {
        $dup = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT MemberID FROM MEMBER WHERE NIC='{$vals['NIC']}' AND MemberID<>$id"));
        if ($dup) $errors[] = 'Another member already has this NIC.';
    }

    if (empty($errors)) {
        $age_v = ($age !== '') ? $age : 'NULL';
        $dob_v = $vals['DOB']          ? "'{$vals['DOB']}'"           : 'NULL';
        $p_v   = $vals['PlanID']        ? $vals['PlanID']              : 'NULL';
        $ps_v  = $vals['PlanStartDate'] ? "'{$vals['PlanStartDate']}'" : 'NULL';

        $sql = "UPDATE MEMBER SET
            Name='{$vals['Name']}', NIC='{$vals['NIC']}', DOB=$dob_v, Age=$age_v,
            Gender='{$vals['Gender']}', Email='{$vals['Email']}', Address='{$vals['Address']}',
            PhoneNo='{$vals['PhoneNo']}', RegDate='{$vals['RegDate']}',
            PlanID=$p_v, PlanStartDate=$ps_v, PlanEndDate=$planEndDate
            WHERE MemberID=$id";

        if (mysqli_query($conn, $sql)) {
            set_flash('success', 'Member updated.');
            header('Location: members.php'); exit;
        } else {
            $errors[] = mysqli_error($conn);
        }
    }
}

include 'includes/header.php';
?>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    <button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button>
</div>
<?php endif; ?>

<div class="form-card">
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="Name" value="<?= htmlspecialchars($vals['Name']) ?>" required>
            </div>
            <div class="form-group">
                <label>NIC *</label>
                <input type="text" name="NIC" value="<?= htmlspecialchars($vals['NIC']) ?>" required>
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="DOB" value="<?= $vals['DOB'] ?>">
            </div>
            <div class="form-group">
                <label>Gender</label>
                <select name="Gender">
                    <option value="">Select</option>
                    <option value="Male"   <?= $vals['Gender']==='Male'  ?'selected':'' ?>>Male</option>
                    <option value="Female" <?= $vals['Gender']==='Female'?'selected':'' ?>>Female</option>
                    <option value="Other"  <?= $vals['Gender']==='Other' ?'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="Email" value="<?= htmlspecialchars($vals['Email']) ?>">
            </div>
            <div class="form-group">
                <label>Phone No.</label>
                <input type="text" name="PhoneNo" value="<?= htmlspecialchars($vals['PhoneNo']) ?>">
            </div>
            <div class="form-group form-full">
                <label>Address</label>
                <input type="text" name="Address" value="<?= htmlspecialchars($vals['Address']) ?>">
            </div>
            <div class="form-group">
                <label>Registration Date</label>
                <input type="date" name="RegDate" value="<?= $vals['RegDate'] ?>" required>
            </div>
            <div class="form-group">
                <label>Membership Plan</label>
                <select name="PlanID">
                    <option value="">No plan</option>
                    <?php while ($p = mysqli_fetch_assoc($plans)): ?>
                    <option value="<?= $p['PlanID'] ?>"
                        data-duration="<?= $p['Duration'] ?>"
                        <?= $vals['PlanID']==$p['PlanID']?'selected':'' ?>>
                        <?= htmlspecialchars($p['PlanName']) ?> (<?= $p['Duration'] ?>mo · Rs.<?= number_format($p['Price'],2) ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plan Start Date</label>
                <input type="date" name="PlanStartDate" value="<?= $vals['PlanStartDate'] ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Member</button>
            <a href="members.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
