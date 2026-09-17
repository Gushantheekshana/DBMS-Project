<?php
declare(strict_types=1);

namespace GymPro\V2\Services;

use DateTimeImmutable;
use InvalidArgumentException;
use mysqli;
use RuntimeException;

final class ClassService
{
    public static function create(int $actorId, array $input): int
    {
        return \transaction(static function (mysqli $db) use ($actorId, $input): int {
            $trainer = self::actor($db, $actorId, 'TRAINER', true);
            $name = trim((string) ($input['name'] ?? ''));
            $description = trim((string) ($input['description'] ?? ''));
            $location = trim((string) ($input['location'] ?? ''));
            $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
            $startsAt = self::dateTime((string) ($input['starts_at'] ?? ''), 'start time');
            $endsAt = self::dateTime((string) ($input['ends_at'] ?? ''), 'end time');

            if ($name === '' || mb_strlen($name) > 120) {
                throw new InvalidArgumentException('Class name is required and must not exceed 120 characters.');
            }
            if ($description !== '' && mb_strlen($description) > 65535) {
                throw new InvalidArgumentException('Description is too long.');
            }
            if ($location !== '' && mb_strlen($location) > 120) {
                throw new InvalidArgumentException('Location must not exceed 120 characters.');
            }
            if ($capacity === false || $capacity < 1 || $capacity > 65535) {
                throw new InvalidArgumentException('Capacity must be between 1 and 65535.');
            }
            if ($endsAt <= $startsAt) {
                throw new InvalidArgumentException('Class end time must be after its start time.');
            }
            if ($startsAt <= new DateTimeImmutable()) {
                throw new InvalidArgumentException('Class start time must be in the future.');
            }

            self::assertNoTrainerOverlap($db, (int) $trainer['TrainerID'], $startsAt, $endsAt);
            $statement = $db->prepare(
                "INSERT INTO GYM_CLASS (TrainerID,Name,Description,StartsAt,EndsAt,Capacity,Location,Status)
                 VALUES (?,?,?,?,?,?,?,'DRAFT')"
            );
            $trainerId = (int) $trainer['TrainerID'];
            $start = $startsAt->format('Y-m-d H:i:s');
            $end = $endsAt->format('Y-m-d H:i:s');
            $descriptionValue = $description === '' ? null : $description;
            $locationValue = $location === '' ? null : $location;
            $statement->bind_param('issssis', $trainerId, $name, $descriptionValue, $start, $end, $capacity, $locationValue);
            $statement->execute();

            return (int) $db->insert_id;
        });
    }

    public static function review(int $actorId, int $classId, string $decision, string $notes = ''): void
    {
        \transaction(static function (mysqli $db) use ($actorId, $classId, $decision, $notes): void {
            self::actor($db, $actorId, 'ADMIN');
            if ($classId < 1 || !in_array($decision, ['SCHEDULED', 'CANCELLED'], true)) {
                throw new InvalidArgumentException('Invalid class review decision.');
            }
            if (mb_strlen($notes) > 500) {
                throw new InvalidArgumentException('Review notes must not exceed 500 characters.');
            }

            $class = self::lockedClass($db, $classId);
            if ($class['Status'] !== 'DRAFT') {
                throw new RuntimeException('Only draft classes can be reviewed.');
            }
            if ($decision === 'SCHEDULED') {
                if (new DateTimeImmutable((string) $class['StartsAt']) <= new DateTimeImmutable()) {
                    throw new RuntimeException('A class can only be approved before it starts.');
                }
                if ($class['TrainerID'] === null) {
                    throw new RuntimeException('A trainer must be assigned before approval.');
                }
                $trainer = self::lockedTrainer($db, (int) $class['TrainerID']);
                if ($trainer['Status'] !== 'ACTIVE' || $trainer['AccountStatus'] !== 'ACTIVE') {
                    throw new RuntimeException('The assigned trainer is not active.');
                }
                self::assertNoTrainerOverlap(
                    $db,
                    (int) $class['TrainerID'],
                    new DateTimeImmutable($class['StartsAt']),
                    new DateTimeImmutable($class['EndsAt']),
                    $classId
                );
            }

            $statement = $db->prepare('UPDATE GYM_CLASS SET Status=? WHERE GymClassID=? AND Status=\'DRAFT\'');
            $statement->bind_param('si', $decision, $classId);
            $statement->execute();
            if ($statement->affected_rows !== 1) {
                throw new RuntimeException('The class review could not be applied.');
            }
        });
    }

    public static function requestEnrollment(int $actorId, int $classId): int
    {
        return \transaction(static function (mysqli $db) use ($actorId, $classId): int {
            $member = self::actor($db, $actorId, 'MEMBER', true);
            if ($classId < 1) {
                throw new InvalidArgumentException('Invalid class.');
            }
            $class = self::lockedClass($db, $classId);
            if ($class['Status'] !== 'SCHEDULED' || new DateTimeImmutable($class['StartsAt']) <= new DateTimeImmutable()) {
                throw new RuntimeException('This class is not open for enrollment.');
            }

            $membership = self::activeMembershipForClass($db, (int) $member['MemberID'], $class['StartsAt']);
            if ($membership === null) {
                throw new RuntimeException('An active membership covering the class date is required.');
            }
            $memberId = (int) $member['MemberID'];
            self::assertClassLimit($db, $memberId, $membership);

            $existingStatement = $db->prepare('SELECT ClassEnrollmentID,Status FROM CLASS_ENROLLMENT WHERE GymClassID=? AND MemberID=? FOR UPDATE');
            $existingStatement->bind_param('ii', $classId, $memberId);
            $existingStatement->execute();
            $existing = $existingStatement->get_result()->fetch_assoc();
            if ($existing !== null && $existing['Status'] !== 'CANCELLED') {
                throw new RuntimeException('You already have an enrollment request for this class.');
            }

            if ($existing !== null) {
                $statement = $db->prepare("UPDATE CLASS_ENROLLMENT SET Status='WAITLISTED',EnrolledAt=NOW(),CancelledAt=NULL WHERE ClassEnrollmentID=?");
                $enrollmentId = (int) $existing['ClassEnrollmentID'];
                $statement->bind_param('i', $enrollmentId);
                $statement->execute();
                return $enrollmentId;
            }

            $statement = $db->prepare("INSERT INTO CLASS_ENROLLMENT (GymClassID,MemberID,Status) VALUES (?,?,'WAITLISTED')");
            $statement->bind_param('ii', $classId, $memberId);
            $statement->execute();
            return (int) $db->insert_id;
        });
    }

    public static function decideEnrollment(int $actorId, int $enrollmentId, string $decision, string $reason = ''): void
    {
        \transaction(static function (mysqli $db) use ($actorId, $enrollmentId, $decision, $reason): void {
            $trainer = self::actor($db, $actorId, 'TRAINER', true);
            if ($enrollmentId < 1 || !in_array($decision, ['ENROLLED', 'CANCELLED'], true)) {
                throw new InvalidArgumentException('Invalid enrollment decision.');
            }
            if (mb_strlen($reason) > 500) {
                throw new InvalidArgumentException('Reason must not exceed 500 characters.');
            }

            $statement = $db->prepare(
                'SELECT ce.ClassEnrollmentID,ce.MemberID,ce.Status,gc.GymClassID,gc.TrainerID,gc.StartsAt,gc.Capacity,gc.Status AS ClassStatus
                   FROM CLASS_ENROLLMENT ce JOIN GYM_CLASS gc ON gc.GymClassID=ce.GymClassID
                  WHERE ce.ClassEnrollmentID=? FOR UPDATE'
            );
            $statement->bind_param('i', $enrollmentId);
            $statement->execute();
            $enrollment = $statement->get_result()->fetch_assoc();
            if ($enrollment === null) {
                throw new RuntimeException('Enrollment not found.');
            }
            if ((int) $enrollment['TrainerID'] !== (int) $trainer['TrainerID']) {
                throw new RuntimeException('You may only decide enrollments for your own classes.');
            }
            if ($enrollment['Status'] !== 'WAITLISTED') {
                throw new RuntimeException('Only pending enrollment requests can be decided.');
            }
            if ($enrollment['ClassStatus'] !== 'SCHEDULED' || new DateTimeImmutable($enrollment['StartsAt']) <= new DateTimeImmutable()) {
                throw new RuntimeException('Enrollment decisions are closed for this class.');
            }

            if ($decision === 'ENROLLED') {
                $membership = self::activeMembershipForClass($db, (int) $enrollment['MemberID'], $enrollment['StartsAt']);
                if ($membership === null) {
                    throw new RuntimeException('The member no longer has class entitlement.');
                }
                self::assertClassLimit($db, (int) $enrollment['MemberID'], $membership, $enrollmentId);
                $countStatement = $db->prepare("SELECT COUNT(*) AS Enrolled FROM CLASS_ENROLLMENT WHERE GymClassID=? AND Status='ENROLLED'");
                $classId = (int) $enrollment['GymClassID'];
                $countStatement->bind_param('i', $classId);
                $countStatement->execute();
                if ((int) $countStatement->get_result()->fetch_assoc()['Enrolled'] >= (int) $enrollment['Capacity']) {
                    throw new RuntimeException('The class has reached capacity.');
                }
            }

            $cancelledAt = $decision === 'CANCELLED' ? date('Y-m-d H:i:s') : null;
            $update = $db->prepare('UPDATE CLASS_ENROLLMENT SET Status=?,CancelledAt=? WHERE ClassEnrollmentID=? AND Status=\'WAITLISTED\'');
            $update->bind_param('ssi', $decision, $cancelledAt, $enrollmentId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new RuntimeException('The enrollment decision could not be applied.');
            }
        });
    }

    private static function actor(mysqli $db, int $actorId, string $role, bool $profile = false): array
    {
        if ($actorId < 1) {
            throw new RuntimeException('Invalid actor.');
        }
        $join = $role === 'TRAINER' ? ' LEFT JOIN TRAINER p ON p.UserAccountID=u.UserAccountID' : ($role === 'MEMBER' ? ' LEFT JOIN MEMBER p ON p.UserAccountID=u.UserAccountID' : '');
        $fields = $profile ? ',p.' . ($role === 'TRAINER' ? 'TrainerID' : 'MemberID') . ' AS ProfileID,p.Status AS ProfileStatus' : '';
        $statement = $db->prepare("SELECT u.UserAccountID,u.Role,u.Status{$fields} FROM USER_ACCOUNT u{$join} WHERE u.UserAccountID=? FOR UPDATE");
        $statement->bind_param('i', $actorId);
        $statement->execute();
        $actor = $statement->get_result()->fetch_assoc();
        if ($actor === null || $actor['Role'] !== $role || $actor['Status'] !== 'ACTIVE') {
            throw new RuntimeException('The actor is not authorized for this action.');
        }
        if ($profile && ($actor['ProfileID'] === null || $actor['ProfileStatus'] !== 'ACTIVE')) {
            throw new RuntimeException('The actor profile is not active.');
        }
        if ($profile) {
            $actor[$role === 'TRAINER' ? 'TrainerID' : 'MemberID'] = $actor['ProfileID'];
        }
        return $actor;
    }

    private static function lockedClass(mysqli $db, int $classId): array
    {
        $statement = $db->prepare('SELECT GymClassID,TrainerID,StartsAt,EndsAt,Capacity,Status FROM GYM_CLASS WHERE GymClassID=? FOR UPDATE');
        $statement->bind_param('i', $classId);
        $statement->execute();
        $class = $statement->get_result()->fetch_assoc();
        if ($class === null) {
            throw new RuntimeException('Class not found.');
        }
        return $class;
    }

    private static function lockedTrainer(mysqli $db, int $trainerId): array
    {
        $statement = $db->prepare('SELECT t.Status,u.Status AS AccountStatus FROM TRAINER t JOIN USER_ACCOUNT u ON u.UserAccountID=t.UserAccountID WHERE t.TrainerID=? FOR UPDATE');
        $statement->bind_param('i', $trainerId);
        $statement->execute();
        $trainer = $statement->get_result()->fetch_assoc();
        if ($trainer === null) {
            throw new RuntimeException('Trainer not found.');
        }
        return $trainer;
    }

    private static function assertNoTrainerOverlap(mysqli $db, int $trainerId, DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, ?int $excludeId = null): void
    {
        $sql = "SELECT GymClassID FROM GYM_CLASS WHERE TrainerID=? AND Status IN ('DRAFT','SCHEDULED') AND StartsAt<? AND EndsAt>?";
        if ($excludeId !== null) {
            $sql .= ' AND GymClassID<>?';
        }
        $sql .= ' LIMIT 1 FOR UPDATE';
        $statement = $db->prepare($sql);
        $start = $startsAt->format('Y-m-d H:i:s');
        $end = $endsAt->format('Y-m-d H:i:s');
        if ($excludeId === null) {
            $statement->bind_param('iss', $trainerId, $end, $start);
        } else {
            $statement->bind_param('issi', $trainerId, $end, $start, $excludeId);
        }
        $statement->execute();
        if ($statement->get_result()->fetch_assoc() !== null) {
            throw new RuntimeException('The trainer already has an overlapping class.');
        }
    }

    private static function assertClassLimit(mysqli $db, int $memberId, array $membership, ?int $excludeEnrollmentId = null): void
    {
        if ($membership['ClassLimit'] === null) {
            return;
        }
        $sql = "SELECT COUNT(*) AS Used
                  FROM CLASS_ENROLLMENT ce
                  JOIN GYM_CLASS gc ON gc.GymClassID=ce.GymClassID
                 WHERE ce.MemberID=? AND ce.Status='ENROLLED'
                   AND gc.StartsAt BETWEEN ? AND ?";
        if ($excludeEnrollmentId !== null) {
            $sql .= ' AND ce.ClassEnrollmentID<>?';
        }
        $statement = $db->prepare($sql);
        $startsOn = $membership['StartsOn'] . ' 00:00:00';
        $endsOn = $membership['EndsOn'] . ' 23:59:59';
        if ($excludeEnrollmentId === null) {
            $statement->bind_param('iss', $memberId, $startsOn, $endsOn);
        } else {
            $statement->bind_param('issi', $memberId, $startsOn, $endsOn, $excludeEnrollmentId);
        }
        $statement->execute();
        if ((int) $statement->get_result()->fetch_assoc()['Used'] >= (int) $membership['ClassLimit']) {
            throw new RuntimeException('The membership class limit has been reached.');
        }
    }

    private static function activeMembershipForClass(mysqli $db, int $memberId, string $startsAt): ?array
    {
        $classDate = (new DateTimeImmutable($startsAt))->format('Y-m-d');
        $statement = $db->prepare(
            "SELECT ms.MembershipID,ms.StartsOn,ms.EndsOn,mp.ClassLimit
               FROM MEMBERSHIP ms JOIN MEMBERSHIP_PLAN mp ON mp.MembershipPlanID=ms.MembershipPlanID
              WHERE ms.MemberID=? AND ms.Status='ACTIVE' AND ms.StartsOn<=? AND ms.EndsOn>=?
              ORDER BY ms.EndsOn DESC,ms.MembershipID DESC LIMIT 1 FOR UPDATE"
        );
        $statement->bind_param('iss', $memberId, $classDate, $classDate);
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: null;
    }

    private static function dateTime(string $value, string $label): DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Invalid class ' . $label . '.');
        }
        return $date;
    }
}
