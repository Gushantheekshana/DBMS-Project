<?php
require_once 'config.php';
$id     = isset($_GET['id'])     ? (int)$_GET['id']                            : 0;
$status = isset($_GET['status']) ? sanitize($conn,$_GET['status'])             : '';

if (!in_array($status,['Accepted','Rejected'])) {
    set_flash('danger','Invalid status.'); header('Location:trainer_requests.php'); exit;
}

$r=mysqli_fetch_assoc(mysqli_query($conn,"SELECT RequestID FROM TRAINER_REQUEST WHERE RequestID=$id"));
if (!$r){set_flash('danger','Request not found.');header('Location:trainer_requests.php');exit;}

mysqli_query($conn,"UPDATE TRAINER_REQUEST SET Status='$status' WHERE RequestID=$id");
set_flash('success',"Request $status.");
header('Location:trainer_requests.php'); exit;
