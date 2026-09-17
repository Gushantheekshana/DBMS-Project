<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap/app.php';

use GymPro\V2\Services\AuthService;
use GymPro\V2\Services\StaffAccountService;

$prefix = 'staff.workflow.' . bin2hex(random_bytes(6));
$password = 'Workflow@12345';
$createdAccountIds = [];
$createdTrainerIds = [];
$failures = [];

$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expectFailure = static function (callable $callback, string $expectedMessage, string $failureMessage) use (&$failures, $check): void {
    try {
        $callback();
        $failures[] = $failureMessage;
    } catch (RuntimeException $exception) {
        $check($exception->getMessage() === $expectedMessage, $failureMessage . ' Wrong message: ' . $exception->getMessage());
    }
};

try {
    $actorHash = password_hash($password, PASSWORD_DEFAULT);
    if ($actorHash === false) {
        throw new RuntimeException('Unable to hash workflow fixture password.');
    }

    db_execute(
        "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?,'ADMIN','ACTIVE',NOW())",
        'ss',
        [$prefix . '.admin@example.test', $actorHash]
    );
    $adminId = (int) db()->insert_id;
    $createdAccountIds[] = $adminId;

    db_execute(
        "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?,'RECEPTIONIST','ACTIVE',NOW())",
        'ss',
        [$prefix . '.receptionist@example.test', $actorHash]
    );
    $receptionistActorId = (int) db()->insert_id;
    $createdAccountIds[] = $receptionistActorId;

    db_execute(
        "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?,'ADMIN','DISABLED',NOW())",
        'ss',
        [$prefix . '.disabled-admin@example.test', $actorHash]
    );
    $disabledAdminId = (int) db()->insert_id;
    $createdAccountIds[] = $disabledAdminId;

    $administrativeEmail = $prefix . '.administrative@example.test';
    $administrativeId = StaffAccountService::create($adminId, [
        'email' => '  ' . strtoupper($administrativeEmail) . '  ',
        'password' => $password,
        'role' => 'admin',
    ]);
    $createdAccountIds[] = $administrativeId;

    $administrative = db_one(
        'SELECT Email,PasswordHash,Role,Status,EmailVerifiedAt,FailedLoginCount,LockedUntil FROM USER_ACCOUNT WHERE UserAccountID=?',
        'i',
        [$administrativeId]
    );
    $check(($administrative['Email'] ?? '') === $administrativeEmail, 'Administrative email must be trimmed and lowercased.');
    $check(($administrative['Role'] ?? '') === 'ADMIN', 'Administrative account must use the ADMIN role.');
    $check(($administrative['Status'] ?? '') === 'ACTIVE', 'Administrative account must be active.');
    $check(!empty($administrative['EmailVerifiedAt']), 'Administrative account must be verified when provisioned.');
    $check(($administrative['PasswordHash'] ?? '') !== $password, 'Administrative password must not be stored as plaintext.');
    $check(password_verify($password, (string) ($administrative['PasswordHash'] ?? '')), 'Administrative password hash must verify.');
    $check((int) ($administrative['FailedLoginCount'] ?? -1) === 0 && ($administrative['LockedUntil'] ?? null) === null, 'Administrative account login state must start clean.');

    $receptionistEmail = $prefix . '.created-receptionist@example.test';
    $receptionistId = StaffAccountService::create($adminId, [
        'email' => $receptionistEmail,
        'password' => $password,
        'role' => ' RECEPTIONIST ',
    ]);
    $createdAccountIds[] = $receptionistId;

    $receptionist = db_one(
        'SELECT Email,PasswordHash,Role,Status,EmailVerifiedAt FROM USER_ACCOUNT WHERE UserAccountID=?',
        'i',
        [$receptionistId]
    );
    $check(($receptionist['Role'] ?? '') === 'RECEPTIONIST', 'Receptionist account must use the RECEPTIONIST role.');
    $check(($receptionist['Status'] ?? '') === 'ACTIVE' && !empty($receptionist['EmailVerifiedAt']), 'Receptionist account must be active and verified.');
    $check(password_verify($password, (string) ($receptionist['PasswordHash'] ?? '')), 'Receptionist password hash must verify.');

    foreach ([$administrativeId, $receptionistId] as $staffId) {
        $memberProfile = db_one('SELECT MemberID FROM MEMBER WHERE UserAccountID=?', 'i', [$staffId]);
        $trainerProfile = db_one('SELECT TrainerID FROM TRAINER WHERE UserAccountID=?', 'i', [$staffId]);
        $check($memberProfile === null && $trainerProfile === null, 'Staff accounts must not create member or trainer profiles.');
    }

    AuthService::login($administrativeEmail, $password, 'ADMIN');
    $check((int) ($_SESSION['user_id'] ?? 0) === $administrativeId, 'Administrative account must sign in through the ADMIN portal.');
    unset($_SESSION['user_id']);
    AuthService::login($receptionistEmail, $password, 'RECEPTIONIST');
    $check((int) ($_SESSION['user_id'] ?? 0) === $receptionistId, 'Receptionist account must sign in through the RECEPTIONIST portal.');
    unset($_SESSION['user_id']);

    $expectFailure(
        static fn () => AuthService::login($administrativeEmail, $password, 'RECEPTIONIST'),
        'Invalid email or password.',
        'Administrative account must not sign in through the receptionist portal.'
    );
    $expectFailure(
        static fn () => AuthService::login($receptionistEmail, $password, 'ADMIN'),
        'Invalid email or password.',
        'Receptionist account must not sign in through the administrative portal.'
    );

    $expectFailure(
        static fn () => StaffAccountService::create($adminId, [
            'email' => strtoupper($administrativeEmail),
            'password' => $password,
            'role' => 'ADMIN',
        ]),
        'An account already uses this email.',
        'Duplicate normalized email must be rejected.'
    );
    $duplicateCount = db_one('SELECT COUNT(*) AccountCount FROM USER_ACCOUNT WHERE Email=?', 's', [$administrativeEmail]);
    $check((int) ($duplicateCount['AccountCount'] ?? 0) === 1, 'Duplicate rejection must not create another account.');

    foreach (['MEMBER', 'TRAINER', ''] as $invalidRole) {
        $expectFailure(
            static fn () => StaffAccountService::create($adminId, [
                'email' => $prefix . '.' . strtolower($invalidRole ?: 'empty') . '@example.test',
                'password' => $password,
                'role' => $invalidRole,
            ]),
            'Select Administrative or Receptionist as the staff role.',
            'Unsupported staff role must be rejected.'
        );
    }
    $expectFailure(
        static fn () => StaffAccountService::create($adminId, [
            'email' => $prefix . '.array-role@example.test',
            'password' => $password,
            'role' => ['ADMIN'],
        ]),
        'Staff role is invalid.',
        'Malformed staff role must be rejected.'
    );

    foreach ([
        [$receptionistActorId, 'Receptionist actor'],
        [$disabledAdminId, 'Disabled administrator'],
        [PHP_INT_MAX, 'Missing actor'],
    ] as [$actorId, $label]) {
        $expectFailure(
            static fn () => StaffAccountService::create((int) $actorId, [
                'email' => $prefix . '.' . strtolower(str_replace(' ', '-', (string) $label)) . '@example.test',
                'password' => $password,
                'role' => 'RECEPTIONIST',
            ]),
            'You are not authorized to create staff accounts.',
            $label . ' must not provision staff.'
        );
    }

    foreach ([
        ['not-an-email', $password, 'Enter a valid email address.'],
        [$prefix . '.short-password@example.test', 'short', 'Password must contain between 10 and 4096 characters.'],
        [$prefix . '.long-password@example.test', str_repeat('a', 4097), 'Password must contain between 10 and 4096 characters.'],
    ] as [$email, $candidatePassword, $message]) {
        $expectFailure(
            static fn () => StaffAccountService::create($adminId, [
                'email' => $email,
                'password' => $candidatePassword,
                'role' => 'RECEPTIONIST',
            ]),
            $message,
            'Invalid staff credentials must be rejected.'
        );
    }
    $expectFailure(
        static fn () => StaffAccountService::create($adminId, [
            'email' => ['invalid@example.test'],
            'password' => $password,
            'role' => 'ADMIN',
        ]),
        'Email is invalid.',
        'Malformed email must be rejected.'
    );
    $expectFailure(
        static fn () => StaffAccountService::create($adminId, [
            'email' => $prefix . '.array-password@example.test',
            'password' => [$password],
            'role' => 'ADMIN',
        ]),
        'Password is invalid.',
        'Malformed password must be rejected.'
    );

    // --- Staff Removal and Protection Tests ---

    // 1. Protection for admin.demo@example.test
    $demoAdmin = db_one("SELECT UserAccountID FROM USER_ACCOUNT WHERE Email='admin.demo@example.test'");
    if ($demoAdmin !== null) {
        $expectFailure(
            static fn () => StaffAccountService::remove($adminId, (int) $demoAdmin['UserAccountID']),
            'The default demo administrator account cannot be removed.',
            'Protected demo administrator must reject removal.'
        );
    } else {
        db_execute(
            "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES ('admin.demo@example.test',?,'ADMIN','ACTIVE',NOW())",
            's',
            [$actorHash]
        );
        $tempDemoId = (int) db()->insert_id;
        $createdAccountIds[] = $tempDemoId;
        $expectFailure(
            static fn () => StaffAccountService::remove($adminId, $tempDemoId),
            'The default demo administrator account cannot be removed.',
            'Protected demo administrator must reject removal.'
        );
    }

    // 2. Prevent self-deletion
    $expectFailure(
        static fn () => StaffAccountService::remove($adminId, $adminId),
        'You cannot remove your own staff account.',
        'Administrator must not be able to remove themselves.'
    );

    // 3. Unauthorized actor
    $expectFailure(
        static fn () => StaffAccountService::remove($receptionistActorId, $administrativeId),
        'You are not authorized to manage staff accounts.',
        'Receptionist actor must not remove staff accounts.'
    );
    $expectFailure(
        static fn () => StaffAccountService::remove($disabledAdminId, $administrativeId),
        'You are not authorized to manage staff accounts.',
        'Disabled administrator actor must not remove staff accounts.'
    );

    // 4. Invalid target identifiers
    $expectFailure(
        static fn () => StaffAccountService::remove($adminId, 0),
        'Invalid staff account identifier.',
        'Zero target ID must be rejected.'
    );
    $expectFailure(
        static fn () => StaffAccountService::remove($adminId, -5),
        'Invalid staff account identifier.',
        'Negative target ID must be rejected.'
    );
    $expectFailure(
        static fn () => StaffAccountService::remove($adminId, 999999999),
        'Staff account not found.',
        'Nonexistent target ID must be rejected.'
    );

    // 5. Hard deletion of unreferenced staff account
    $deletableEmail = $prefix . '.deletable@example.test';
    $deletableId = StaffAccountService::create($adminId, [
        'email' => $deletableEmail,
        'password' => $password,
        'role' => 'RECEPTIONIST',
    ]);
    $createdAccountIds[] = $deletableId;

    $deleteOutcome = StaffAccountService::remove($adminId, $deletableId);
    $check($deleteOutcome === 'deleted', 'Unreferenced staff account must be hard deleted.');
    $deletedRow = db_one('SELECT UserAccountID FROM USER_ACCOUNT WHERE UserAccountID=?', 'i', [$deletableId]);
    $check($deletedRow === null, 'Hard-deleted staff account must no longer exist in USER_ACCOUNT.');

    // 6. Disablement of staff account with operational history
    $staffWithHistoryEmail = $prefix . '.with-history@example.test';
    $staffWithHistoryId = StaffAccountService::create($adminId, [
        'email' => $staffWithHistoryEmail,
        'password' => $password,
        'role' => 'ADMIN',
    ]);
    $createdAccountIds[] = $staffWithHistoryId;

    db_execute(
        "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?,'TRAINER','ACTIVE',NOW())",
        'ss',
        [$prefix . '.history-trainer@example.test', $actorHash]
    );
    $trainerUserId = (int) db()->insert_id;
    $createdAccountIds[] = $trainerUserId;

    db_execute(
        "INSERT INTO TRAINER (UserAccountID,TrainerNumber,FirstName,LastName,Phone,Status,ReviewedByUserAccountID) VALUES (?,?,?,?,?,'ACTIVE',?)",
        'issssi',
        [$trainerUserId, 'TRN-' . bin2hex(random_bytes(4)), 'Hist', 'Trainer', '0771234567', $staffWithHistoryId]
    );
    $createdTrainerIds[] = (int) db()->insert_id;

    $disableOutcome = StaffAccountService::remove($adminId, $staffWithHistoryId);
    $check($disableOutcome === 'disabled', 'Staff account with history must be disabled instead of hard deleted.');
    $disabledRow = db_one('SELECT UserAccountID, Status FROM USER_ACCOUNT WHERE UserAccountID=?', 'i', [$staffWithHistoryId]);
    $check(($disabledRow['Status'] ?? '') === 'DISABLED', 'Staff account with history must have Status=DISABLED.');

    // Attempting to remove already-disabled staff account
    $expectFailure(
        static fn () => StaffAccountService::remove($adminId, $staffWithHistoryId),
        'This staff account is already disabled.',
        'Removing an already-disabled staff account must be rejected.'
    );

    // 7. Non-staff target accounts cannot be managed here
    $expectFailure(
        static fn () => StaffAccountService::remove($adminId, $trainerUserId),
        'Only administrative and receptionist accounts can be managed here.',
        'Trainer account must not be managed via staff removal.'
    );
} catch (Throwable $error) {
    $failures[] = 'Workflow execution failed: ' . $error->getMessage();
} finally {
    unset($_SESSION['user_id']);
    foreach (array_reverse($createdTrainerIds) as $trainerId) {
        try {
            db_execute('DELETE FROM TRAINER WHERE TrainerID=?', 'i', [$trainerId]);
        } catch (Throwable $cleanupError) {
            $failures[] = 'Trainer fixture cleanup failed: ' . $cleanupError->getMessage();
        }
    }
    foreach (array_reverse($createdAccountIds) as $accountId) {
        try {
            db_execute('DELETE FROM USER_ACCOUNT WHERE UserAccountID=?', 'i', [$accountId]);
        } catch (Throwable $cleanupError) {
            $failures[] = 'Fixture cleanup failed: ' . $cleanupError->getMessage();
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Staff account workflow failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Staff account workflow passed.\n";
