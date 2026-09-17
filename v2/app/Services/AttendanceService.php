<?php
declare(strict_types=1);

namespace GymPro\V2\Services;

use InvalidArgumentException;
use mysqli;
use RuntimeException;

final class AttendanceService
{
    private const ATTENDANCE_STATUSES = ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED'];

    public static function markClass(int $actorId, int $classId, array $statuses): void
    {
        \transaction(static function (mysqli $db) use ($actorId, $classId, $statuses): void {
            $trainer = self::actor($db, $actorId, 'TRAINER', true);
            if ($classId < 1) {
                throw new InvalidArgumentException('Invalid class.');
            }

            $classStatement = $db->prepare('SELECT GymClassID,TrainerID,StartsAt,Status FROM GYM_CLASS WHERE GymClassID=? FOR UPDATE');
            $classStatement->bind_param('i', $classId);
            $classStatement->execute();
            $class = $classStatement->get_result()->fetch_assoc();
            if ($class === null) {
                throw new RuntimeException('Class not found.');
            }
            if ((int) $class['TrainerID'] !== (int) $trainer['TrainerID']) {
                throw new RuntimeException('You may only mark attendance for your own classes.');
            }
            if (!in_array($class['Status'], ['SCHEDULED', 'COMPLETED'], true)) {
                throw new RuntimeException('Attendance cannot be marked for this class.');
            }
            if (new \DateTimeImmutable((string) $class['StartsAt']) > new \DateTimeImmutable()) {
                throw new RuntimeException('Attendance cannot be marked before the class starts.');
            }

            $rosterStatement = $db->prepare("SELECT ClassEnrollmentID FROM CLASS_ENROLLMENT WHERE GymClassID=? AND Status='ENROLLED' ORDER BY ClassEnrollmentID FOR UPDATE");
            $rosterStatement->bind_param('i', $classId);
            $rosterStatement->execute();
            $roster = array_map('intval', array_column($rosterStatement->get_result()->fetch_all(MYSQLI_ASSOC), 'ClassEnrollmentID'));

            $submitted = [];
            foreach ($statuses as $enrollmentId => $status) {
                if (!is_scalar($enrollmentId) || filter_var($enrollmentId, FILTER_VALIDATE_INT) === false || (int) $enrollmentId < 1 || !is_string($status)) {
                    throw new InvalidArgumentException('Invalid attendance roster.');
                }
                $submitted[(int) $enrollmentId] = strtoupper(trim($status));
            }
            ksort($submitted);
            $expected = $roster;
            sort($expected);
            if (array_keys($submitted) !== $expected) {
                throw new RuntimeException('The submitted attendance must exactly match the enrolled roster.');
            }
            foreach ($submitted as $status) {
                if (!in_array($status, self::ATTENDANCE_STATUSES, true)) {
                    throw new InvalidArgumentException('Invalid attendance status.');
                }
            }

            $statement = $db->prepare(
                "INSERT INTO CLASS_ATTENDANCE (ClassEnrollmentID,GymClassID,Status,CheckedInAt,MarkedByUserAccountID)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE Status=VALUES(Status),CheckedInAt=CASE WHEN Status=VALUES(Status) THEN CheckedInAt ELSE VALUES(CheckedInAt) END,MarkedByUserAccountID=VALUES(MarkedByUserAccountID)"
            );
            foreach ($submitted as $enrollmentId => $status) {
                $checkedInAt = in_array($status, ['PRESENT', 'LATE'], true) ? date('Y-m-d H:i:s') : null;
                $statement->bind_param('iissi', $enrollmentId, $classId, $status, $checkedInAt, $actorId);
                $statement->execute();
            }
        });
    }

    public static function checkIn(int $actorId, int $memberId): int
    {
        return \transaction(static function (mysqli $db) use ($actorId, $memberId): int {
            self::actor($db, $actorId, 'RECEPTIONIST');
            if ($memberId < 1) {
                throw new InvalidArgumentException('Invalid member.');
            }
            $memberStatement = $db->prepare('SELECT m.MemberID,m.Status,u.Status AS AccountStatus FROM MEMBER m JOIN USER_ACCOUNT u ON u.UserAccountID=m.UserAccountID WHERE m.MemberID=? FOR UPDATE');
            $memberStatement->bind_param('i', $memberId);
            $memberStatement->execute();
            $member = $memberStatement->get_result()->fetch_assoc();
            if ($member === null || $member['Status'] !== 'ACTIVE' || $member['AccountStatus'] !== 'ACTIVE') {
                throw new RuntimeException('The member is not active.');
            }

            $today = date('Y-m-d');
            $membershipStatement = $db->prepare("SELECT MembershipID FROM MEMBERSHIP WHERE MemberID=? AND Status='ACTIVE' AND StartsOn<=? AND EndsOn>=? LIMIT 1 FOR UPDATE");
            $membershipStatement->bind_param('iss', $memberId, $today, $today);
            $membershipStatement->execute();
            if ($membershipStatement->get_result()->fetch_assoc() === null) {
                throw new RuntimeException('The member does not have an active membership.');
            }

            $visitStatement = $db->prepare('SELECT GymVisitID FROM GYM_VISIT WHERE MemberID=? AND CheckedOutAt IS NULL FOR UPDATE');
            $visitStatement->bind_param('i', $memberId);
            $visitStatement->execute();
            if ($visitStatement->get_result()->fetch_assoc() !== null) {
                throw new RuntimeException('The member already has an open visit.');
            }

            $insert = $db->prepare('INSERT INTO GYM_VISIT (MemberID,CheckedInByUserAccountID) VALUES (?,?)');
            $insert->bind_param('ii', $memberId, $actorId);
            $insert->execute();
            return (int) $db->insert_id;
        });
    }

    public static function checkOut(int $actorId, int $visitId): void
    {
        \transaction(static function (mysqli $db) use ($actorId, $visitId): void {
            self::actor($db, $actorId, 'RECEPTIONIST');
            if ($visitId < 1) {
                throw new InvalidArgumentException('Invalid visit.');
            }
            $statement = $db->prepare('SELECT GymVisitID,CheckedOutAt FROM GYM_VISIT WHERE GymVisitID=? FOR UPDATE');
            $statement->bind_param('i', $visitId);
            $statement->execute();
            $visit = $statement->get_result()->fetch_assoc();
            if ($visit === null) {
                throw new RuntimeException('Visit not found.');
            }
            if ($visit['CheckedOutAt'] !== null) {
                throw new RuntimeException('This visit is already closed.');
            }
            $update = $db->prepare('UPDATE GYM_VISIT SET CheckedOutAt=NOW(),CheckedOutByUserAccountID=? WHERE GymVisitID=? AND CheckedOutAt IS NULL');
            $update->bind_param('ii', $actorId, $visitId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                throw new RuntimeException('The visit could not be checked out.');
            }
        });
    }

    private static function actor(mysqli $db, int $actorId, string $role, bool $trainerProfile = false): array
    {
        if ($actorId < 1) {
            throw new RuntimeException('Invalid actor.');
        }
        $join = $trainerProfile ? ' LEFT JOIN TRAINER t ON t.UserAccountID=u.UserAccountID' : '';
        $fields = $trainerProfile ? ',t.TrainerID,t.Status AS TrainerStatus' : '';
        $statement = $db->prepare("SELECT u.UserAccountID,u.Role,u.Status{$fields} FROM USER_ACCOUNT u{$join} WHERE u.UserAccountID=? FOR UPDATE");
        $statement->bind_param('i', $actorId);
        $statement->execute();
        $actor = $statement->get_result()->fetch_assoc();
        if ($actor === null || $actor['Role'] !== $role || $actor['Status'] !== 'ACTIVE') {
            throw new RuntimeException('The actor is not authorized for this action.');
        }
        if ($trainerProfile && ($actor['TrainerID'] === null || $actor['TrainerStatus'] !== 'ACTIVE')) {
            throw new RuntimeException('The trainer profile is not active.');
        }
        return $actor;
    }
}
