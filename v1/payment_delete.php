<?php
require_once 'config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0;
$p=mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM PAYMENT WHERE PaymentID=$id"));
if (!$p){set_flash('danger','Payment not found.');header('Location:payments.php');exit;}
if (isset($_POST['confirm'])){
    mysqli_query($conn,"DELETE FROM PAYMENT WHERE PaymentID=$id");
    set_flash('success','Payment deleted.');header('Location:payments.php');exit;
}
$pageTitle='Delete Payment';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete payment record?</h2>
    <p>Rs.<?= number_format($p['Amount'],2) ?> <?= htmlspecialchars($p['PaymentType']) ?> on <?= $p['PaymentDate'] ?>. This cannot be undone.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="payments.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
