<?php
declare(strict_types=1);

namespace GymPro\V2\Services;

use RuntimeException;

final class AuthService
{
    private const ROLES = ['ADMIN', 'RECEPTIONIST', 'TRAINER', 'MEMBER'];
    private const MAX_FAILED_LOGINS = 5;
    private const LOCK_MINUTES = 15;

    public static function registerMember(array $data): int
    {
        $email = self::email($data);
        $password = self::password($data);
        $firstName = self::required($data, 'first_name', 'First name');
        $lastName = self::required($data, 'last_name', 'Last name');

        return \transaction(function () use ($data, $email, $password, $firstName, $lastName): int {
            self::assertEmailAvailable($email);
            \db_execute("INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status) VALUES (?,?,'MEMBER','ACTIVE')", 'ss', [$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) \db()->insert_id;
            \db_execute(
                "INSERT INTO MEMBER (UserAccountID,MemberNumber,FirstName,LastName,DateOfBirth,Phone,EmergencyContactName,EmergencyContactPhone,JoinedOn,Status) VALUES (?,CONCAT('MEM-',LPAD(?,8,'0')),?,?,?,?,?,?,CURDATE(),'ACTIVE')",
                'iissssss', [$userId, $userId, $firstName, $lastName, self::dateOrNull($data['date_of_birth'] ?? $data['dob'] ?? null), self::nullable($data['phone'] ?? null, 'Phone', 32), self::nullable($data['emergency_contact_name'] ?? null, 'Emergency contact name', 160), self::nullable($data['emergency_contact_phone'] ?? null, 'Emergency contact phone', 32)]
            );
            return $userId;
        });
    }

    public static function registerTrainer(array $data): int
    {
        return TrainerApplicationService::submit($data);
    }

    public static function login(string $email, string $password, ?string $expectedRole = null): array
    {
        if ($expectedRole !== null && !in_array($expectedRole, self::ROLES, true)) {
            throw new RuntimeException('Invalid sign-in portal.');
        }
        $email = strtolower(trim($email));
        $user = $expectedRole === null
            ? \db_one('SELECT * FROM USER_ACCOUNT WHERE Email=?', 's', [$email])
            : \db_one('SELECT * FROM USER_ACCOUNT WHERE Email=? AND Role=?', 'ss', [$email, $expectedRole]);
        if (!$user) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            throw new RuntimeException('Invalid email or password.');
        }
        if ($user['LockedUntil'] !== null && strtotime((string) $user['LockedUntil']) > time()) {
            throw new RuntimeException('Invalid email or password.');
        }
        if (!password_verify($password, (string) $user['PasswordHash'])) {
            \db_execute(
                'UPDATE USER_ACCOUNT
                    SET FailedLoginCount=CASE
                            WHEN LockedUntil IS NOT NULL AND LockedUntil<=NOW() THEN 1
                            ELSE LEAST(FailedLoginCount+1,1000)
                        END,
                        LockedUntil=CASE
                            WHEN LockedUntil IS NOT NULL AND LockedUntil<=NOW() THEN NULL
                            WHEN FailedLoginCount+1>=? THEN DATE_ADD(NOW(),INTERVAL ? MINUTE)
                            ELSE LockedUntil
                        END
                  WHERE UserAccountID=?',
                'iii', [self::MAX_FAILED_LOGINS, self::LOCK_MINUTES, (int) $user['UserAccountID']]
            );
            throw new RuntimeException('Invalid email or password.');
        }
        if (($user['Status'] ?? '') !== 'ACTIVE') {
            if (($user['Role'] ?? '') === 'TRAINER' && ($user['Status'] ?? '') === 'PENDING') {
                throw new RuntimeException('Your trainer application is pending admin approval.');
            }
            if (($user['Role'] ?? '') === 'TRAINER' && ($user['Status'] ?? '') === 'DISABLED') {
                $trainer = \db_one('SELECT Status FROM TRAINER WHERE UserAccountID=?', 'i', [(int) $user['UserAccountID']]);
                if (($trainer['Status'] ?? '') === 'REJECTED') {
                    throw new RuntimeException('Your trainer application was not approved. You may submit a revised application.');
                }
            }
            throw new RuntimeException('This account is not active.');
        }
        \db_execute('UPDATE USER_ACCOUNT SET FailedLoginCount=0,LockedUntil=NULL,LastLoginAt=NOW() WHERE UserAccountID=?', 'i', [(int) $user['UserAccountID']]);
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int) $user['UserAccountID'];
        $user['FailedLoginCount'] = 0;
        $user['LockedUntil'] = null;
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        }
    }

    private static function email(array $data): string
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) throw new RuntimeException('Enter a valid email address.');
        return $email;
    }

    private static function password(array $data): string
    {
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 10 || strlen($password) > 4096) throw new RuntimeException('Password must contain between 10 and 4096 characters.');
        return $password;
    }

    private static function assertEmailAvailable(string $email): void
    {
        if (\db_one('SELECT UserAccountID FROM USER_ACCOUNT WHERE Email=? FOR UPDATE', 's', [$email])) throw new RuntimeException('An account already uses this email.');
    }

    private static function required(array $data, string $key, string $label): string
    {
        $value = self::scalar($data[$key] ?? null, $label);
        if ($value === '') throw new RuntimeException($label . ' is required.');
        $maximum = in_array($key, ['first_name', 'last_name'], true) ? 80 : 255;
        if (mb_strlen($value) > $maximum) throw new RuntimeException($label . ' is too long.');
        return $value;
    }

    private static function nullable(mixed $value, string $label = 'Value', int $maximum = 255): ?string
    {
        $value = self::scalar($value, $label);
        if ($value === '') return null;
        if (mb_strlen($value) > $maximum) throw new RuntimeException($label . ' is too long.');
        return $value;
    }

    private static function scalar(mixed $value, string $label): string
    {
        if ($value !== null && !is_scalar($value)) throw new RuntimeException($label . ' is invalid.');
        return trim((string) ($value ?? ''));
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = self::nullable($value, 'Date of birth', 10);
        if ($value !== null && (\DateTimeImmutable::createFromFormat('!Y-m-d', $value)?->format('Y-m-d') !== $value)) throw new RuntimeException('Enter a valid date of birth.');
        if ($value !== null && $value > date('Y-m-d')) throw new RuntimeException('Date of birth cannot be in the future.');
        return $value;
    }
}
