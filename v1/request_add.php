<?php
require_once 'config.php';
$pageTitle = 'New Trainer Request';
$members  = mysqli_query($conn,"SELECT MemberID,Name FROM MEMBER ORDER BY Name");
$trainers = mysqli_query($conn,"SELECT TrainerID,Name FROM TRAINER ORDER BY Name");
$errors=[]; $vals=['MemberID'=>'','TrainerID'=>'','RequestDate'=>date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD']==='POST'){
    $vals['MemberID']    = (int)($_POST['MemberID']??0);
    $vals['TrainerID']   = (int)($_POST['TrainerID']??0);
    $vals['RequestDate'] = sanitize($conn,$_POST['RequestDate']??'');
    if (!$vals['MemberID'])    $errors[]='Select a member.';
    if (!$vals['TrainerID'])   $errors[]='Select a trainer.';
    if (!$vals['RequestDate']) $errors[]='Date is required.';
    if (empty($errors)){
        mysqli_query($conn,"INSERT INTO TRAINER_REQUEST (RequestDate,Status,MemberID,TrainerID)
            VALUES ('{$vals['RequestDate']}','Pending',{$vals['MemberID']},{$vals['TrainerID']})");
        set_flash('success','Request submitted.'); header('Location:trainer_requests.php'); exit;
    }
}
include 'includes/header.php';
?>
<?php if ($errors): ?>
<div class="alert alert-danger"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?><button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button></div>
<?php endif; ?>
<div class="form-card">
<form method="POST">
    <div class="form-grid">
        <div class="form-group">
            <label>Member *</label>
            <select name="MemberID" required>
                <option value="">Select member</option>
                <?php while ($m=mysqli_fetch_assoc($members)): ?>
                <option value="<?= $m['MemberID'] ?>" <?= $vals['MemberID']==$m['MemberID']?'selected':'' ?>><?= htmlspecialchars($m['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Preferred Trainer *</label>
            <select name="TrainerID" required>
                <option value="">Select trainer</option>
                <?php while ($t=mysqli_fetch_assoc($trainers)): ?>
                <option value="<?= $t['TrainerID'] ?>" <?= $vals['TrainerID']==$t['TrainerID']?'selected':'' ?>><?= htmlspecialchars($t['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Request Date</label>
            <input type="date" name="RequestDate" value="<?= $vals['RequestDate'] ?>" required>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Submit Request</button>
        <a href="trainer_requests.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
