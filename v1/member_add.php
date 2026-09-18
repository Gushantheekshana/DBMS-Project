<?php
require_once 'config.php';
$pageTitle = 'Add Member';

$plans = mysqli_query($conn, "SELECT PlanID, PlanName, Duration, Price FROM MEMBERSHIP_PLAN ORDER BY PlanName");

$errors = [];
$vals   = ['Name'=>'','NIC'=>'','DOB'=>'','Age'=>'','Gender'=>'','Email'=>'','Address'=>'','PhoneNo'=>'','RegDate'=>date('Y-m-d'),'PlanID'=>'','PlanStartDate'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($vals as $k => $_) {
        $vals[$k] = sanitize($conn, $_POST[$k] ?? '');
    }

    if (!$vals['Name'])    $errors[] = 'Name is required.';
    if (!$vals['NIC'])     $errors[] = 'NIC is required.';
    if (!$vals['RegDate']) $errors[] = 'Registration date is required.';

    // Auto-calculate age and plan end date
    $age = '';
    if ($vals['DOB']) {
        $age = (int) date_diff(date_create($vals['DOB']), date_create('today'))->y;
    }

    $planEndDate = 'NULL';
    if ($vals['PlanID'] && $vals['PlanStartDate']) {
        $dur = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT Duration FROM MEMBERSHIP_PLAN WHERE PlanID={$vals['PlanID']}"))['Duration'];
        $planEndDate = "'" . date('Y-m-d', strtotime("+{$dur} months", strtotime($vals['PlanStartDate']))) . "'";
    }

    // NIC uniqueness
    if ($vals['NIC']) {
        $dup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MemberID FROM MEMBER WHERE NIC='{$vals['NIC']}'"));
        if ($dup) $errors[] = 'A member with this NIC already exists.';
    }

    if (empty($errors)) {
        $age_val  = $age !== '' ? $age : 'NULL';
        $dob_val  = $vals['DOB']          ? "'{$vals['DOB']}'"          : 'NULL';
        $plan_val = $vals['PlanID']        ? $vals['PlanID']             : 'NULL';
        $psd_val  = $vals['PlanStartDate'] ? "'{$vals['PlanStartDate']}'" : 'NULL';

        $sql = "INSERT INTO MEMBER
            (Name,NIC,DOB,Age,Gender,Email,Address,PhoneNo,RegDate,PlanID,PlanStartDate,PlanEndDate)
            VALUES
            ('{$vals['Name']}','{$vals['NIC']}',$dob_val,$age_val,'{$vals['Gender']}',
             '{$vals['Email']}','{$vals['Address']}','{$vals['PhoneNo']}',
             '{$vals['RegDate']}',$plan_val,$psd_val,$planEndDate)";

        if (mysqli_query($conn, $sql)) {
            set_flash('success', 'Member "' . $vals['Name'] . '" added successfully.');
            header('Location: members.php'); exit;
        } else {
            $errors[] = 'Database error: ' . mysqli_error($conn);
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
                <label>Registration Date *</label>
                <input type="date" name="RegDate" value="<?= $vals['RegDate'] ?>" required>
            </div>
            <div class="form-group">
                <label>Membership Plan</label>
                <select name="PlanID" id="planSelect" onchange="updatePlanInfo(this)">
                    <option value="">No plan</option>
                    <?php while ($p = mysqli_fetch_assoc($plans)): ?>
                    <option value="<?= $p['PlanID'] ?>"
                        data-duration="<?= $p['Duration'] ?>"
                        data-price="<?= $p['Price'] ?>"
                        <?= $vals['PlanID']==$p['PlanID']?'selected':'' ?>>
                        <?= htmlspecialchars($p['PlanName']) ?> (<?= $p['Duration'] ?>mo · Rs.<?= number_format($p['Price'],2) ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plan Start Date</label>
                <input type="date" name="PlanStartDate" id="planStart" value="<?= $vals['PlanStartDate'] ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Member</button>
            <a href="members.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>

<script>
function updatePlanInfo(sel) {
    var opt = sel.selectedOptions[0];
    var dur = opt ? opt.dataset.duration : null;
    var startEl = document.getElementById('planStart');
    if (dur && startEl.value) {
        // visual hint only
    }
}
</script>

<?php include 'includes/footer.php'; ?>
