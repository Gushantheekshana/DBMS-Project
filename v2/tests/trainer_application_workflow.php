<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap/app.php';

use GymPro\V2\Services\AuthService;
use GymPro\V2\Services\TrainerApplicationService;

$prefix = 'workflow.' . bin2hex(random_bytes(6));
$approvedEmail = $prefix . '.approved@example.test';
$rejectedEmail = $prefix . '.rejected@example.test';
$password = 'Workflow@12345';
$newPassword = 'Revised@12345';
$createdAccountIds = [];
$failures = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

try {
    $adminHash = password_hash($password, PASSWORD_DEFAULT);
    db_execute("INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?, 'ADMIN','ACTIVE',NOW())", 'ss', [$prefix . '.admin@example.test', $adminHash]);
    $adminId = (int) db()->insert_id;
    $createdAccountIds[] = $adminId;

    $approvedId = TrainerApplicationService::submit([
        'email' => $approvedEmail,
        'password' => $password,
        'first_name' => 'Pending',
        'last_name' => 'Approval',
        'phone' => '+10000000001',
        'specialization' => 'Strength',
        'bio' => 'Workflow fixture',
    ]);
    $createdAccountIds[] = $approvedId;
    $approvedProfile = db_one('SELECT TrainerID,Status FROM TRAINER WHERE UserAccountID=?', 'i', [$approvedId]);
    $check(($approvedProfile['Status'] ?? '') === 'PENDING', 'New trainer profile must be pending.');
    $check((db_one('SELECT Status FROM USER_ACCOUNT WHERE UserAccountID=?', 'i', [$approvedId])['Status'] ?? '') === 'PENDING', 'New trainer account must be pending.');

    try {
        AuthService::login($approvedEmail, $password, 'TRAINER');
        $failures[] = 'Pending trainer login unexpectedly succeeded.';
    } catch (RuntimeException $error) {
        $check($error->getMessage() === 'Your trainer application is pending admin approval.', 'Pending trainer login message is incorrect.');
    }
    $check(!isset($_SESSION['user_id']), 'Pending trainer login must not create a session.');

    TrainerApplicationService::review($adminId, (int) $approvedProfile['TrainerID'], 'approve');
    $approved = db_one('SELECT t.Status,u.Status AccountStatus,t.ReviewedByUserAccountID,t.HireDate FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID WHERE t.TrainerID=?', 'i', [(int) $approvedProfile['TrainerID']]);
    $check(($approved['Status'] ?? '') === 'ACTIVE' && ($approved['AccountStatus'] ?? '') === 'ACTIVE', 'Approval must activate both records.');
    $check((int) ($approved['ReviewedByUserAccountID'] ?? 0) === $adminId && !empty($approved['HireDate']), 'Approval review metadata is incomplete.');
    AuthService::login($approvedEmail, $password, 'TRAINER');
    $check((int) ($_SESSION['user_id'] ?? 0) === $approvedId, 'Approved trainer login must create a session.');
    unset($_SESSION['user_id']);

    $rejectedId = TrainerApplicationService::submit([
        'email' => $rejectedEmail,
        'password' => $password,
        'first_name' => 'Pending',
        'last_name' => 'Rejection',
        'specialization' => 'Mobility',
    ]);
    $createdAccountIds[] = $rejectedId;
    $rejectedProfile = db_one('SELECT TrainerID FROM TRAINER WHERE UserAccountID=?', 'i', [$rejectedId]);
    $trainerId = (int) $rejectedProfile['TrainerID'];
    TrainerApplicationService::review($adminId, $trainerId, 'reject', 'Please provide revised professional details.');
    $rejected = db_one('SELECT t.Status,u.Status AccountStatus,t.RejectionReason FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID WHERE t.TrainerID=?', 'i', [$trainerId]);
    $check(($rejected['Status'] ?? '') === 'REJECTED' && ($rejected['AccountStatus'] ?? '') === 'DISABLED', 'Rejection must block both records.');
    $check(($rejected['RejectionReason'] ?? '') !== '', 'Rejection reason must be stored.');

    $resubmittedId = TrainerApplicationService::submit([
        'email' => $rejectedEmail,
        'password' => $newPassword,
        'first_name' => 'Revised',
        'last_name' => 'Applicant',
        'specialization' => 'Mobility and recovery',
    ]);
    $check($resubmittedId === $rejectedId, 'Resubmission must reuse the account identifier.');
    $resubmitted = db_one('SELECT t.TrainerID,t.FirstName,t.Status,t.ReviewedAt,t.RejectionReason,u.Status AccountStatus,u.PasswordHash FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID WHERE t.UserAccountID=?', 'i', [$rejectedId]);
    $check((int) ($resubmitted['TrainerID'] ?? 0) === $trainerId, 'Resubmission must reuse the trainer identifier.');
    $check(($resubmitted['Status'] ?? '') === 'PENDING' && ($resubmitted['AccountStatus'] ?? '') === 'PENDING', 'Resubmission must return both records to pending.');
    $check(($resubmitted['FirstName'] ?? '') === 'Revised' && $resubmitted['ReviewedAt'] === null && $resubmitted['RejectionReason'] === null, 'Resubmission must refresh details and clear review state.');
    $check(password_verify($newPassword, (string) ($resubmitted['PasswordHash'] ?? '')), 'Resubmission must replace the password.');

    try {
        TrainerApplicationService::submit([
            'email' => $approvedEmail,
            'password' => $newPassword,
            'first_name' => 'Unsafe',
            'last_name' => 'Overwrite',
        ]);
        $failures[] = 'An active trainer email was incorrectly reusable.';
    } catch (RuntimeException) {
        // Expected.
    }

    try {
        TrainerApplicationService::review($adminId, (int) $approvedProfile['TrainerID'], 'reject', 'Stale decision');
        $failures[] = 'An already reviewed application accepted another decision.';
    } catch (RuntimeException) {
        // Expected.
    }
} catch (Throwable $error) {
    $failures[] = 'Workflow execution failed: ' . $error->getMessage();
} finally {
    unset($_SESSION['user_id']);
    foreach (array_reverse($createdAccountIds) as $accountId) {
        try {
            db_execute('DELETE FROM TRAINER WHERE UserAccountID=?', 'i', [$accountId]);
            db_execute('DELETE FROM USER_ACCOUNT WHERE UserAccountID=?', 'i', [$accountId]);
        } catch (Throwable $cleanupError) {
            $failures[] = 'Fixture cleanup failed: ' . $cleanupError->getMessage();
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Trainer application workflow failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Trainer application workflow passed.\n";
