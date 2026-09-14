<?php
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT Name FROM MEMBER WHERE MemberID=$id"));
if (!$member) { set_flash('danger', 'Member not found.'); header('Location: members.php'); exit; }

if (isset($_POST['confirm'])) {
    mysqli_query($conn, "DELETE FROM ENROLLMENT WHERE MemberID=$id");
    mysqli_query($conn, "DELETE FROM ATTENDANCE WHERE MemberID=$id");
    mysqli_query($conn, "DELETE FROM PAYMENT WHERE MemberID=$id");
    mysqli_query($conn, "DELETE FROM TRAINER_REQUEST WHERE MemberID=$id");
    mysqli_query($conn, "DELETE FROM MEMBER WHERE MemberID=$id");
    set_flash('success', 'Member "' . $member['Name'] . '" deleted.');
    header('Location: members.php'); exit;
}

$pageTitle = 'Delete Member';
include 'includes/header.php';
?>
<div class="confirm-box">
    <h2>Delete member?</h2>
    <p>This will permanently remove <strong><?= htmlspecialchars($member['Name']) ?></strong> and all their enrollment, attendance, payment, and request records. This cannot be undone.</p>
    <form method="POST" class="confirm-actions">
        <button type="submit" name="confirm" class="btn btn-danger">Yes, delete</button>
        <a href="members.php" class="btn btn-ghost">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>
