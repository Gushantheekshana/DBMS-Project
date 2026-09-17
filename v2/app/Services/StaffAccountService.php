<?php
declare(strict_types=1);

namespace GymPro\V2\Services;

use mysqli;
use mysqli_sql_exception;
use RuntimeException;

final class StaffAccountService
{
    private const ROLES = ['ADMIN', 'RECEPTIONIST'];

    public static function create(int $actorAccountId, array $data): int
    {
        $email = self::email($data['email'] ?? null);
        $password = self::password($data['password'] ?? null);
        $role = self::role($data['role'] ?? null);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to secure the password.');
        }

        try {
            return \transaction(static function (mysqli $db) use ($actorAccountId, $email, $passwordHash, $role): int {
                $actorStatement = $db->prepare(
                    "SELECT UserAccountID FROM USER_ACCOUNT WHERE UserAccountID=? AND Role='ADMIN' AND Status='ACTIVE' FOR UPDATE"
                );
                $actorStatement->bind_param('i', $actorAccountId);
                $actorStatement->execute();
                if ($actorStatement->get_result()->fetch_assoc() === null) {
                    throw new RuntimeException('You are not authorized to create staff accounts.');
                }

                $insert = $db->prepare(
                    "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,EmailVerifiedAt) VALUES (?,?,?,'ACTIVE',NOW())"
                );
                $insert->bind_param('sss', $email, $passwordHash, $role);
                $insert->execute();

                return (int) $db->insert_id;
            });
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062) {
                throw new RuntimeException('An account already uses this email.', 0, $exception);
            }
            throw $exception;
        }
    }

    public static function remove(int $actorAccountId, int $targetAccountId): string
    {
        if ($targetAccountId <= 0) {
            throw new RuntimeException('Invalid staff account identifier.');
        }

        if ($actorAccountId === $targetAccountId) {
            throw new RuntimeException('You cannot remove your own staff account.');
        }

        return \transaction(static function (mysqli $db) use ($actorAccountId, $targetAccountId): string {
            $actorStatement = $db->prepare(
                "SELECT UserAccountID FROM USER_ACCOUNT WHERE UserAccountID=? AND Role='ADMIN' AND Status='ACTIVE' FOR UPDATE"
            );
            $actorStatement->bind_param('i', $actorAccountId);
            $actorStatement->execute();
            if ($actorStatement->get_result()->fetch_assoc() === null) {
                throw new RuntimeException('You are not authorized to manage staff accounts.');
            }

            $targetStatement = $db->prepare(
                "SELECT UserAccountID, Email, Role, Status FROM USER_ACCOUNT WHERE UserAccountID=? FOR UPDATE"
            );
            $targetStatement->bind_param('i', $targetAccountId);
            $targetStatement->execute();
            $target = $targetStatement->get_result()->fetch_assoc();
            if ($target === null) {
                throw new RuntimeException('Staff account not found.');
            }

            if (!in_array($target['Role'], self::ROLES, true)) {
                throw new RuntimeException('Only administrative and receptionist accounts can be managed here.');
            }

            if (strtolower((string) $target['Email']) === 'admin.demo@example.test') {
                throw new RuntimeException('The default demo administrator account cannot be removed.');
            }

            if ($target['Role'] === 'ADMIN' && $target['Status'] === 'ACTIVE') {
                $adminCountResult = $db->query(
                    "SELECT COUNT(*) AS AdminCount FROM USER_ACCOUNT WHERE Role='ADMIN' AND Status='ACTIVE'"
                );
                $adminCount = (int) ($adminCountResult->fetch_assoc()['AdminCount'] ?? 0);
                if ($adminCount <= 1) {
                    throw new RuntimeException('The last active administrator account cannot be removed.');
                }
            }

            $depStatement = $db->prepare(
                "SELECT (
                    (SELECT COUNT(*) FROM GYM_VISIT WHERE CheckedInByUserAccountID=? OR CheckedOutByUserAccountID=?) +
                    (SELECT COUNT(*) FROM CLASS_ATTENDANCE WHERE MarkedByUserAccountID=?) +
                    (SELECT COUNT(*) FROM MEMBERSHIP_PAYMENT WHERE RecordedByUserAccountID=?) +
                    (SELECT COUNT(*) FROM TRAINER WHERE ReviewedByUserAccountID=? OR UserAccountID=?) +
                    (SELECT COUNT(*) FROM MEMBER WHERE UserAccountID=?)
                ) AS DependencyCount"
            );
            $depStatement->bind_param(
                'iiiiiii',
                $targetAccountId,
                $targetAccountId,
                $targetAccountId,
                $targetAccountId,
                $targetAccountId,
                $targetAccountId,
                $targetAccountId
            );
            $depStatement->execute();
            $dependencies = (int) ($depStatement->get_result()->fetch_assoc()['DependencyCount'] ?? 0);

            if ($dependencies > 0) {
                if ($target['Status'] === 'DISABLED') {
                    throw new RuntimeException('This staff account is already disabled.');
                }

                $disableStatement = $db->prepare(
                    "UPDATE USER_ACCOUNT SET Status='DISABLED' WHERE UserAccountID=?"
                );
                $disableStatement->bind_param('i', $targetAccountId);
                $disableStatement->execute();

                return 'disabled';
            }

            $deleteStatement = $db->prepare(
                "DELETE FROM USER_ACCOUNT WHERE UserAccountID=?"
            );
            $deleteStatement->bind_param('i', $targetAccountId);
            $deleteStatement->execute();

            return 'deleted';
        });
    }

    private static function email(mixed $value): string
    {
        $email = strtolower(self::scalar($value, 'Email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            throw new RuntimeException('Enter a valid email address.');
        }
        return $email;
    }

    private static function password(mixed $value): string
    {
        if (!is_string($value)) {
            throw new RuntimeException('Password is invalid.');
        }
        if (strlen($value) < 10 || strlen($value) > 4096) {
            throw new RuntimeException('Password must contain between 10 and 4096 characters.');
        }
        return $value;
    }

    private static function role(mixed $value): string
    {
        $role = strtoupper(self::scalar($value, 'Staff role'));
        if (!in_array($role, self::ROLES, true)) {
            throw new RuntimeException('Select Administrative or Receptionist as the staff role.');
        }
        return $role;
    }

    private static function scalar(mixed $value, string $label): string
    {
        if (!is_scalar($value)) {
            throw new RuntimeException($label . ' is invalid.');
        }
        return trim((string) $value);
    }
}
