<?php
declare(strict_types=1);

namespace GymPro\V2\Services;

use InvalidArgumentException;
use mysqli;
use RuntimeException;

final class TrainerApplicationService
{
    public static function submit(array $data): int
    {
        $email = self::email($data);
        $password = self::password($data);
        $firstName = self::required($data, 'first_name', 'First name', 80);
        $lastName = self::required($data, 'last_name', 'Last name', 80);
        $phone = self::nullable($data['phone'] ?? null, 'Phone', 32);
        $specialization = self::nullable($data['specialization'] ?? null, 'Specialization', 160);
        $bio = self::nullable($data['bio'] ?? null, 'Professional bio', 65535);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to secure the password.');
        }

        return \transaction(static function (mysqli $db) use ($email, $passwordHash, $firstName, $lastName, $phone, $specialization, $bio): int {
            $accountStatement = $db->prepare('SELECT UserAccountID,Role,Status FROM USER_ACCOUNT WHERE Email=? FOR UPDATE');
            $accountStatement->bind_param('s', $email);
            $accountStatement->execute();
            $account = $accountStatement->get_result()->fetch_assoc();

            if ($account === null) {
                $insertAccount = $db->prepare("INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status) VALUES (?,?,'TRAINER','PENDING')");
                $insertAccount->bind_param('ss', $email, $passwordHash);
                $insertAccount->execute();
                $userId = (int) $db->insert_id;

                $insertTrainer = $db->prepare(
                    "INSERT INTO TRAINER (UserAccountID,TrainerNumber,FirstName,LastName,Phone,Specialization,Bio,HireDate,Status)
                     VALUES (?,CONCAT('TRN-',LPAD(?,8,'0')),?,?,?,?,?,NULL,'PENDING')"
                );
                $insertTrainer->bind_param('iisssss', $userId, $userId, $firstName, $lastName, $phone, $specialization, $bio);
                $insertTrainer->execute();

                return $userId;
            }

            $userId = (int) $account['UserAccountID'];
            $trainerStatement = $db->prepare('SELECT TrainerID,Status FROM TRAINER WHERE UserAccountID=? FOR UPDATE');
            $trainerStatement->bind_param('i', $userId);
            $trainerStatement->execute();
            $trainer = $trainerStatement->get_result()->fetch_assoc();

            if (
                $account['Role'] !== 'TRAINER'
                || $account['Status'] !== 'DISABLED'
                || $trainer === null
                || $trainer['Status'] !== 'REJECTED'
            ) {
                throw new RuntimeException('An account already uses this email.');
            }

            $updateAccount = $db->prepare(
                "UPDATE USER_ACCOUNT
                    SET PasswordHash=?,Status='PENDING',FailedLoginCount=0,LockedUntil=NULL,LastLoginAt=NULL
                  WHERE UserAccountID=? AND Role='TRAINER' AND Status='DISABLED'"
            );
            $updateAccount->bind_param('si', $passwordHash, $userId);
            $updateAccount->execute();
            if ($updateAccount->affected_rows !== 1) {
                throw new RuntimeException('The trainer application could not be resubmitted.');
            }

            $trainerId = (int) $trainer['TrainerID'];
            $updateTrainer = $db->prepare(
                "UPDATE TRAINER
                    SET FirstName=?,LastName=?,Phone=?,Specialization=?,Bio=?,HireDate=NULL,Status='PENDING',
                        ReviewedAt=NULL,ReviewedByUserAccountID=NULL,RejectionReason=NULL
                  WHERE TrainerID=? AND Status='REJECTED'"
            );
            $updateTrainer->bind_param('sssssi', $firstName, $lastName, $phone, $specialization, $bio, $trainerId);
            $updateTrainer->execute();
            if ($updateTrainer->affected_rows !== 1) {
                throw new RuntimeException('The trainer application could not be resubmitted.');
            }

            return $userId;
        });
    }

    public static function review(int $adminAccountId, int $trainerId, string $decision, string $reason = ''): void
    {
        $decision = strtolower(trim($decision));
        $reason = trim($reason);
        if ($trainerId < 1 || !in_array($decision, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Invalid trainer application decision.');
        }
        if (mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Rejection reason must not exceed 500 characters.');
        }
        if ($decision === 'reject' && $reason === '') {
            throw new InvalidArgumentException('Enter a reason before rejecting this application.');
        }

        \transaction(static function (mysqli $db) use ($adminAccountId, $trainerId, $decision, $reason): void {
            $actorStatement = $db->prepare("SELECT UserAccountID FROM USER_ACCOUNT WHERE UserAccountID=? AND Role='ADMIN' AND Status='ACTIVE' FOR UPDATE");
            $actorStatement->bind_param('i', $adminAccountId);
            $actorStatement->execute();
            if ($actorStatement->get_result()->fetch_assoc() === null) {
                throw new RuntimeException('You are not authorized to review trainer applications.');
            }

            $applicationStatement = $db->prepare(
                'SELECT t.TrainerID,t.UserAccountID,t.Status,u.Status AS AccountStatus
                   FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID
                  WHERE t.TrainerID=? FOR UPDATE'
            );
            $applicationStatement->bind_param('i', $trainerId);
            $applicationStatement->execute();
            $application = $applicationStatement->get_result()->fetch_assoc();
            if ($application === null) {
                throw new RuntimeException('Trainer application not found.');
            }
            if ($application['Status'] !== 'PENDING' || $application['AccountStatus'] !== 'PENDING') {
                throw new RuntimeException('This trainer application is no longer pending.');
            }

            $userId = (int) $application['UserAccountID'];
            if ($decision === 'approve') {
                $accountStatus = 'ACTIVE';
                $trainerStatus = 'ACTIVE';
                $storedReason = null;
            } else {
                $accountStatus = 'DISABLED';
                $trainerStatus = 'REJECTED';
                $storedReason = $reason;
            }

            $updateAccount = $db->prepare('UPDATE USER_ACCOUNT SET Status=?,FailedLoginCount=0,LockedUntil=NULL WHERE UserAccountID=? AND Status=\'PENDING\'');
            $updateAccount->bind_param('si', $accountStatus, $userId);
            $updateAccount->execute();
            if ($updateAccount->affected_rows !== 1) {
                throw new RuntimeException('The trainer application decision could not be applied.');
            }

            $hireDateSql = $decision === 'approve' ? 'COALESCE(HireDate,CURDATE())' : 'NULL';
            $updateTrainer = $db->prepare(
                "UPDATE TRAINER
                    SET Status=?,HireDate={$hireDateSql},ReviewedAt=NOW(),ReviewedByUserAccountID=?,RejectionReason=?
                  WHERE TrainerID=? AND Status='PENDING'"
            );
            $updateTrainer->bind_param('sisi', $trainerStatus, $adminAccountId, $storedReason, $trainerId);
            $updateTrainer->execute();
            if ($updateTrainer->affected_rows !== 1) {
                throw new RuntimeException('The trainer application decision could not be applied.');
            }
        });
    }

    private static function email(array $data): string
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            throw new RuntimeException('Enter a valid email address.');
        }
        return $email;
    }

    private static function password(array $data): string
    {
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 10 || strlen($password) > 4096) {
            throw new RuntimeException('Password must contain between 10 and 4096 characters.');
        }
        return $password;
    }

    private static function required(array $data, string $key, string $label, int $maximum): string
    {
        $value = self::scalar($data[$key] ?? null, $label);
        if ($value === '') {
            throw new RuntimeException($label . ' is required.');
        }
        if (mb_strlen($value) > $maximum) {
            throw new RuntimeException($label . ' is too long.');
        }
        return $value;
    }

    private static function nullable(mixed $value, string $label, int $maximum): ?string
    {
        $value = self::scalar($value, $label);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new RuntimeException($label . ' is too long.');
        }
        return $value;
    }

    private static function scalar(mixed $value, string $label): string
    {
        if ($value !== null && !is_scalar($value)) {
            throw new RuntimeException($label . ' is invalid.');
        }
        return trim((string) ($value ?? ''));
    }
}
