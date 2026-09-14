<?php
require_once 'config.php';
$pageTitle='Add Class';
$trainers=mysqli_query($conn,"SELECT TrainerID,Name FROM TRAINER ORDER BY Name");
$errors=[];
$vals=['ClassName'=>'','DateOfClass'=>'','StartTime'=>'','EndTime'=>'','Description'=>'','Capacity'=>'','TrainerID'=>'','Specialization'=>'','RatePerClass'=>''];

if ($_SERVER['REQUEST_METHOD']==='POST'){
    foreach ($vals as $k=>$_) $vals[$k]=sanitize($conn,$_POST[$k]??'');
    if (!$vals['ClassName'])   $errors[]='Class name is required.';
    if (!$vals['DateOfClass']) $errors[]='Date is required.';
    if (!$vals['StartTime'])   $errors[]='Start time is required.';
    if (!$vals['EndTime'])     $errors[]='End time is required.';
    if (!$vals['Capacity'] || !is_numeric($vals['Capacity'])) $errors[]='Capacity must be a number.';
    if (empty($errors)){
        $tid   = $vals['TrainerID'] ? $vals['TrainerID'] : 'NULL';
        $rate  = $vals['RatePerClass'] ? $vals['RatePerClass'] : 'NULL';
        mysqli_query($conn,"INSERT INTO CLASS (ClassName,DateOfClass,StartTime,EndTime,Description,Capacity,TrainerID,Specialization,RatePerClass)
            VALUES ('{$vals['ClassName']}','{$vals['DateOfClass']}','{$vals['StartTime']}','{$vals['EndTime']}',
                    '{$vals['Description']}',{$vals['Capacity']},$tid,'{$vals['Specialization']}',$rate)");
        set_flash('success','Class scheduled.'); header('Location:classes.php'); exit;
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
        <div class="form-group form-full">
            <label>Class Name *</label>
            <input type="text" name="ClassName" value="<?= htmlspecialchars($vals['ClassName']) ?>" required>
        </div>
        <div class="form-group">
            <label>Date *</label>
            <input type="date" name="DateOfClass" value="<?= $vals['DateOfClass'] ?>" required>
        </div>
        <div class="form-group">
            <label>Capacity *</label>
            <input type="number" name="Capacity" min="1" value="<?= htmlspecialchars($vals['Capacity']) ?>" required>
        </div>
        <div class="form-group">
            <label>Start Time *</label>
            <input type="time" name="StartTime" value="<?= $vals['StartTime'] ?>" required>
        </div>
        <div class="form-group">
            <label>End Time *</label>
            <input type="time" name="EndTime" value="<?= $vals['EndTime'] ?>" required>
        </div>
        <div class="form-group">
            <label>Trainer</label>
            <select name="TrainerID">
                <option value="">No trainer assigned</option>
                <?php while ($tr=mysqli_fetch_assoc($trainers)): ?>
                <option value="<?= $tr['TrainerID'] ?>" <?= $vals['TrainerID']==$tr['TrainerID']?'selected':'' ?>><?= htmlspecialchars($tr['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Specialization</label>
            <input type="text" name="Specialization" value="<?= htmlspecialchars($vals['Specialization']) ?>" placeholder="e.g. Yoga, Cardio, Weights">
        </div>
        <div class="form-group">
            <label>Rate Per Class (Rs.)</label>
            <input type="number" name="RatePerClass" min="0" step="0.01" value="<?= htmlspecialchars($vals['RatePerClass']) ?>">
        </div>
        <div class="form-group form-full">
            <label>Description</label>
            <textarea name="Description"><?= htmlspecialchars($vals['Description']) ?></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Class</button>
        <a href="classes.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
