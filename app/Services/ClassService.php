<?php
declare(strict_types=1);

namespace GymPro\Services;

use RuntimeException;

final class ClassService
{
    public static function create(int $trainerId, array $data): int
    {
        foreach (['name', 'date', 'start', 'end', 'specialization'] as $field) {
            if (trim((string)($data[$field] ?? '')) === '') {
                throw new RuntimeException(ucfirst($field) . ' is required.');
            }
        }

        $capacity = (int)($data['capacity'] ?? 0);
        if ($capacity < 1 || $capacity > 10) {
            throw new RuntimeException('Class capacity must be between 1 and 10.');
        }
        if ($data['date'] < date('Y-m-d') || $data['end'] <= $data['start']) {
            throw new RuntimeException('Enter a future class with an end time after its start time.');
        }

        $conflict = \db_one(
            "SELECT ClassID FROM CLASS WHERE TrainerID=? AND DateOfClass=? AND Status NOT IN ('CANCELLED','REJECTED') AND StartTime<? AND EndTime>?",
            'isss',
            [$trainerId, $data['date'], $data['end'], $data['start']]
        );
        if ($conflict) {
            throw new RuntimeException('This schedule overlaps another of your classes.');
        }

        \db_execute(
            "INSERT INTO CLASS (ClassName,DateOfClass,StartTime,EndTime,Description,Capacity,TrainerID,Specialization,RatePerClass,Status) VALUES (?,?,?,?,?,?,?,?,?,'PENDING_APPROVAL')",
            'sssssiisd',
            [trim($data['name']), $data['date'], $data['start'], $data['end'], trim($data['description'] ?? ''), $capacity, $trainerId, trim($data['specialization']), max(0, (float)($data['rate'] ?? 0))]
        );
        $id = \db()->insert_id;
        \audit('class.submitted', 'CLASS', $id);
        return $id;
    }

    public static function review(int $classId, string $decision, string $notes, int $reviewer): void
    {
        if (!in_array($decision, ['SCHEDULED', 'REJECTED'], true)) {
            throw new RuntimeException('Invalid class decision.');
        }

        \transaction(function () use ($classId, $decision, $notes, $reviewer): void {
            $class = \db_one(
                "SELECT c.*,u.UserID TrainerUserID FROM CLASS c LEFT JOIN USER_ACCOUNT u ON u.TrainerID=c.TrainerID WHERE c.ClassID=? FOR UPDATE",
                'i',
                [$classId]
            );
            if (!$class || $class['Status'] !== 'PENDING_APPROVAL') {
                throw new RuntimeException('Class is not awaiting approval.');
            }

            \db_execute('UPDATE CLASS SET Status=?,ReviewNotes=?,ReviewedBy=?,ReviewedAt=NOW() WHERE ClassID=?', 'ssii', [$decision, $notes, $reviewer, $classId]);
            if ($decision === 'SCHEDULED') {
                \db_execute(
                    "INSERT IGNORE INTO CLASS_SESSION (ClassID,StartsAt,EndsAt,Status) VALUES (?,CONCAT(?,' ',?),CONCAT(?,' ',?),'SCHEDULED')",
                    'issss',
                    [$classId, $class['DateOfClass'], $class['StartTime'], $class['DateOfClass'], $class['EndTime']]
                );
            }
            if ($class['TrainerUserID']) {
                \notify_users(
                    [(int)$class['TrainerUserID']],
                    $decision === 'SCHEDULED' ? 'Class approved' : 'Class rejected',
                    $notes ?: $class['ClassName'],
                    $decision === 'SCHEDULED' ? 'SUCCESS' : 'WARNING',
                    'CLASS',
                    $classId
                );
            }
            \audit('class.' . strtolower($decision), 'CLASS', $classId, ['notes' => $notes]);
        });
    }

    public static function requestEnrollment(int $memberId, int $classId): int
    {
        return \transaction(function () use ($memberId, $classId): int {
            $class = \db_one(
                "SELECT c.*,s.SubscriptionID,s.StartsAt SubscriptionStartsAt,s.EndsAt SubscriptionEndsAt,p.ClassLimitPerCycle,tu.UserID TrainerUserID
                 FROM CLASS c
                 JOIN SUBSCRIPTION s ON s.MemberID=? AND s.Status='ACTIVE' AND s.StartsAt<=NOW() AND s.EndsAt>NOW()
                 JOIN MEMBERSHIP_PLAN p ON p.PlanID=s.PlanID AND p.IsActive=1 AND p.AllowsClasses=1
                 JOIN TRAINER t ON t.TrainerID=c.TrainerID AND t.ArchivedAt IS NULL
                 JOIN USER_ACCOUNT tu ON tu.TrainerID=t.TrainerID AND tu.Role='TRAINER' AND tu.Status='ACTIVE' AND tu.AnonymizedAt IS NULL
                 WHERE c.ClassID=? AND c.Status='SCHEDULED' AND TIMESTAMP(c.DateOfClass,c.StartTime)>NOW()
                 ORDER BY s.EndsAt DESC
                 LIMIT 1 FOR UPDATE",
                'ii',
                [$memberId, $classId]
            );
            if (!$class) {
                throw new RuntimeException('This class is unavailable or your active plan does not include class access.');
            }

            $existing = \db_one('SELECT EnrollmentID FROM ENROLLMENT WHERE MemberID=? AND ClassID=?', 'ii', [$memberId, $classId]);
            if ($existing) {
                throw new RuntimeException('You already requested this class.');
            }

            if ($class['ClassLimitPerCycle'] !== null) {
                $used = (int)(\db_one(
                    "SELECT COUNT(*) n FROM ENROLLMENT e JOIN CLASS c ON c.ClassID=e.ClassID WHERE e.MemberID=? AND e.Status IN ('PENDING','ENROLLED') AND TIMESTAMP(c.DateOfClass,c.StartTime) BETWEEN ? AND ?",
                    'iss',
                    [$memberId, $class['SubscriptionStartsAt'], $class['SubscriptionEndsAt']]
                )['n'] ?? 0);
                if ($used >= (int)$class['ClassLimitPerCycle']) {
                    throw new RuntimeException('You have reached your plan class limit for this membership cycle.');
                }
            }

            \db_execute("INSERT INTO ENROLLMENT (MemberID,ClassID,Status,RequestedAt) VALUES (?,?,'PENDING',NOW())", 'ii', [$memberId, $classId]);
            $enrollmentId = \db()->insert_id;
            \notify_users(
                [(int)$class['TrainerUserID']],
                'New class request',
                'A member requested ' . $class['ClassName'] . '.',
                'INFO',
                'ENROLLMENT',
                $enrollmentId
            );
            \audit('enrollment.requested', 'ENROLLMENT', $enrollmentId, ['class_id' => $classId]);
            return $enrollmentId;
        });
    }

    public static function decideEnrollment(int $enrollmentId, int $trainerId, int $actorUserId, string $decision, string $reason = ''): void
    {
        if (!in_array($decision, ['ENROLLED', 'REJECTED'], true)) {
            throw new RuntimeException('Invalid decision.');
        }

        \transaction(function () use ($enrollmentId, $trainerId, $actorUserId, $decision, $reason): void {
            $enrollment = \db_one(
                "SELECT e.*,c.Capacity,c.ClassName,c.TrainerID,c.Status ClassStatus,TIMESTAMP(c.DateOfClass,c.StartTime) ClassStartsAt,u.UserID MemberUserID
                 FROM ENROLLMENT e
                 JOIN CLASS c ON c.ClassID=e.ClassID
                 JOIN USER_ACCOUNT u ON u.MemberID=e.MemberID AND u.Role='MEMBER' AND u.AnonymizedAt IS NULL
                 WHERE e.EnrollmentID=? FOR UPDATE",
                'i',
                [$enrollmentId]
            );
            if (!$enrollment || (int)$enrollment['TrainerID'] !== $trainerId || $enrollment['Status'] !== 'PENDING') {
                throw new RuntimeException('Enrollment request is unavailable.');
            }
            if ($enrollment['ClassStatus'] !== 'SCHEDULED' || strtotime($enrollment['ClassStartsAt']) <= time()) {
                throw new RuntimeException('This class can no longer accept enrollment decisions.');
            }

            if ($decision === 'ENROLLED') {
                $count = (int)(\db_one("SELECT COUNT(*) n FROM ENROLLMENT WHERE ClassID=? AND Status='ENROLLED' FOR UPDATE", 'i', [$enrollment['ClassID']])['n'] ?? 0);
                if ($count >= min(10, (int)$enrollment['Capacity'])) {
                    throw new RuntimeException('This class has reached its 10-student capacity.');
                }
            }

            \db_execute(
                'UPDATE ENROLLMENT SET Status=?,ReviewedAt=NOW(),ReviewedBy=?,DecisionReason=? WHERE EnrollmentID=?',
                'sisi',
                [$decision, $actorUserId, $reason, $enrollmentId]
            );
            \notify_users(
                [(int)$enrollment['MemberUserID']],
                $decision === 'ENROLLED' ? 'Class request accepted' : 'Class request rejected',
                $reason ?: $enrollment['ClassName'],
                $decision === 'ENROLLED' ? 'SUCCESS' : 'WARNING',
                'ENROLLMENT',
                $enrollmentId
            );
            \audit('enrollment.' . strtolower($decision), 'ENROLLMENT', $enrollmentId, ['reason' => $reason]);
        });
    }
}
