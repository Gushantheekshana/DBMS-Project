<?php
require_once 'config.php';
$pageTitle='Edit Class';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$class=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM CLASS WHERE ClassID=$id"));
if (!$class){set_flash('danger','Class not found.');header('Location:classes.php');exit;}
$trainers=mysqli_query($conn,"SELECT TrainerID,Name FROM TRAINER ORDER BY Name");
$errors=[];$vals=$class;

if ($_SERVER['REQUEST_METHOD']==='POST'){
    $f=['ClassName','DateOfClass','StartTime','EndTime','Description','Capacity','TrainerID','Specialization','RatePerClass'];
    foreach ($f as $k) $vals[$k]=sanitize($conn,$_POST[$k]??'');
    if (!$vals['ClassName']||!$vals['DateOfClass']||!$vals['StartTime']||!$vals['EndTime']||!$vals['Capacity'])
        $errors[]='Please fill all required fields.';
    if (empty($errors)){
        $tid=$vals['TrainerID']?$vals['TrainerID']:'NULL';
        $rate=$vals['RatePerClass']?$vals['RatePerClass']:'NULL';
        mysqli_query($conn,"UPDATE CLASS SET ClassName='{$vals['ClassName']}',DateOfClass='{$vals['DateOfClass']}',
            StartTime='{$vals['StartTime']}',EndTime='{$vals['EndTime']}',Description='{$vals['Description']}',
            Capacity={$vals['Capacity']},TrainerID=$tid,Specialization='{$vals['Specialization']}',RatePerClass=$rate
            WHERE ClassID=$id");
        set_flash('success','Class updated.');header('Location:classes.php');exit;
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
                <option value="">No trainer</option>
                <?php while ($tr=mysqli_fetch_assoc($trainers)): ?>
                <option value="<?= $tr['TrainerID'] ?>" <?= $vals['TrainerID']==$tr['TrainerID']?'selected':'' ?>><?= htmlspecialchars($tr['Name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Specialization</label>
            <input type="text" name="Specialization" value="<?= htmlspecialchars($vals['Specialization']) ?>">
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
        <button type="submit" class="btn btn-primary">Update Class</button>
        <a href="classes.php" class="btn btn-ghost">Cancel</a>
    </div>
</form>
</div>
<?php include 'includes/footer.php'; ?>
