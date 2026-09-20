<?php
require_once 'config.php';
$pageTitle = 'Record Payment';

$members  = mysqli_query($conn,"SELECT MemberID,Name FROM MEMBER ORDER BY Name");
$trainers = mysqli_query($conn,"SELECT TrainerID,Name FROM TRAINER ORDER BY Name");

// Auto-fill trainer salary from classes
$trainer_classes = [];
$tcRes = mysqli_query($conn,"SELECT t.TrainerID, t.Name, COALESCE(SUM(c.RatePerClass),0) AS calc_salary
    FROM TRAINER t LEFT JOIN CLASS c ON c.TrainerID=t.TrainerID
    WHERE c.RatePerClass IS NOT NULL GROUP BY t.TrainerID");
while ($tc=mysqli_fetch_assoc($tcRes)) $trainer_classes[$tc['TrainerID']] = $tc['calc_salary'];

$errors=[];
$vals=['PaymentDate'=>date('Y-m-d'),'PaymentMethod'=>'Cash','Amount'=>'','PaymentType'=>'Membership','MemberID'=>'','TrainerID'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST'){
    foreach ($vals as $k=>$_) $vals[$k]=sanitize($conn,$_POST[$k]??'');
    if (!$vals['Amount']||!is_numeric($vals['Amount'])) $errors[]='Amount is required.';
    if (!$vals['PaymentDate']) $errors[]='Payment date is required.';
    if ($vals['PaymentType']==='Membership' && !$vals['MemberID']) $errors[]='Select a member for membership payment.';
    if ($vals['PaymentType']==='Salary'     && !$vals['TrainerID']) $errors[]='Select a trainer for salary payment.';

    if (empty($errors)){
        $mid = $vals['PaymentType']==='Membership' && $vals['MemberID'] ? $vals['MemberID'] : 'NULL';
        $tid = $vals['PaymentType']==='Salary'     && $vals['TrainerID'] ? $vals['TrainerID'] : 'NULL';
        mysqli_query($conn,"INSERT INTO PAYMENT (PaymentDate,PaymentMethod,Amount,PaymentType,MemberID,TrainerID)
            VALUES ('{$vals['PaymentDate']}','{$vals['PaymentMethod']}',{$vals['Amount']},
                    '{$vals['PaymentType']}',$mid,$tid)");
        set_flash('success','Payment recorded.'); header('Location:payments.php'); exit;
    }
}
include 'includes/header.php';
?>
<?php if ($errors): ?>
<div class="alert alert-danger"><?= implode('<br>',array_map('htmlspecialchars',$errors)) ?><button class="alert-close" onclick="this.parentElement.remove()">&#x2715;</button></div>
<?php endif; ?>

<div class="form-card">
<form method="POST" id="payForm">
    <div class="form-grid">
        <div class="form-group">
            <label>Payment Type *</label>
            <select name="PaymentType" id="payType" onchange="toggleType(this.value)">
                <option value="Membership" <?= $vals['PaymentType']==='Membership'?'selected':'' ?>>Membership Fee</option>
                <option value="Salary"     <?= $vals['PaymentType']==='Salary'    ?'selected':'' ?>>Trainer Salary</option>
            </select>
        </div>
        <div class="form-group">
            <label>Payment Date *</label>
            <input type="date" name="PaymentDate" value="<?= $vals['PaymentDate'] ?>" required>
        </div>

        <!-- Member selector -->
        <div class="form-group" id="memberRow">
            <label>Member *</label>
            <select name="MemberID">
                <option value="">Select member</option>
                <?php while ($m=mysqli_fetch_assoc($members)): ?>
                <option value="<?= $m['MemberID'] ?>" <?= $vals['MemberID']==$m['MemberID']?'selected':'' ?>><?= htmlspecialchars($m['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Trainer selector -->
        <div class="form-group" id="trainerRow" style="display:none;">
            <label>Trainer *</label>
            <select name="TrainerID" id="trainerSel" onchange="fillSalary(this)">
                <option value="">Select trainer</option>
                <?php foreach ($trainer_classes as $tid=>$sal):
                    $tn=mysqli_fetch_assoc(mysqli_query($conn,"SELECT Name FROM TRAINER WHERE TrainerID=$tid")); ?>
                <option value="<?= $tid ?>" data-salary="<?= $sal ?>" <?= $vals['TrainerID']==$tid?'selected':'' ?>><?= htmlspecialchars($tn['Name']) ?> (Rs.<?= number_format($sal,2) ?> calc.)</option>
                <?php endforeach;
                // trainers with no classes
                $noclass=mysqli_query($conn,"SELECT TrainerID,Name FROM TRAINER WHERE TrainerID NOT IN (".implode(',',array_merge(array_keys($trainer_classes),[0])).")");
                while ($tt=mysqli_fetch_assoc($noclass)): ?>
                <option value="<?= $tt['TrainerID'] ?>" data-salary="0" <?= $vals['TrainerID']==$tt['TrainerID']?'selected':'' ?>><?= htmlspecialchars($tt['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Amount (Rs.) *</label>
            <input type="number" name="Amount" id="amountField" min="0" step="0.01" value="<?= htmlspecialchars($vals['Amount']) ?>" required>
        </div>
        <div class="form-group">
            <label>Payment Method</label>
            <select name="PaymentMethod">
                <?php foreach (['Cash','Card','Online','Bank Transfer','Cheque'] as $m): ?>
                <option value="<?= $m ?>" <?= $vals['PaymentMethod']===$m?'selected':'' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Record Payment</button>
        <a href="payments.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>

<script>
function toggleType(v) {
    document.getElementById('memberRow').style.display  = v==='Membership' ? '' : 'none';
    document.getElementById('trainerRow').style.display = v==='Salary'     ? '' : 'none';
    if (v==='Salary') fillSalary(document.getElementById('trainerSel'));
}
function fillSalary(sel) {
    var opt = sel.selectedOptions[0];
    if (opt && opt.dataset.salary) {
        document.getElementById('amountField').value = parseFloat(opt.dataset.salary).toFixed(2);
    }
}
toggleType(document.getElementById('payType').value);
</script>

<?php include 'includes/footer.php'; ?>
