<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../bootstrap/app.php';

const DEMO_PASSWORD = 'Demo@12345';
const DEMO_ACCOUNTS = [
    'ADMIN' => 'admin.demo@example.test',
    'RECEPTIONIST' => 'reception.demo@example.test',
    'TRAINER' => 'trainer.demo@example.test',
    'MEMBER' => 'member.demo@example.test',
];

if ((string) env('APP_ENV', 'production') !== 'local') {
    fwrite(STDERR, "Refusing to seed demo data outside APP_ENV=local.\n");
    exit(1);
}

/** @return int Identifier selected by $keyColumn. */
function demo_upsert(string $table, string $idColumn, string $keyColumn, string $keyValue, array $values): int
{
    $existing = db_one("SELECT {$idColumn} FROM {$table} WHERE {$keyColumn}=? FOR UPDATE", 's', [$keyValue]);
    $columns = array_keys($values);
    if ($existing !== null) {
        $assignments = implode(',', array_map(static fn(string $column): string => "{$column}=?", $columns));
        db_execute("UPDATE {$table} SET {$assignments} WHERE {$idColumn}=?", str_repeat('s', count($values)) . 'i', [...array_values($values), (int) $existing[$idColumn]]);
        return (int) $existing[$idColumn];
    }

    $insertColumns = array_merge([$keyColumn], $columns);
    $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
    db_execute(
        "INSERT INTO {$table} (" . implode(',', $insertColumns) . ") VALUES ({$placeholders})",
        str_repeat('s', count($insertColumns)),
        [$keyValue, ...array_values($values)]
    );
    return (int) db()->insert_id;
}

/** Update a reserved demo plan in place so existing membership relationships remain intact. */
function demo_upsert_plan(string $legacyName, string $name, array $values, int $demoMemberId): int
{
    $legacy = db_one('SELECT MembershipPlanID FROM MEMBERSHIP_PLAN WHERE Name=? FOR UPDATE', 's', [$legacyName]);
    $current = db_one('SELECT MembershipPlanID FROM MEMBERSHIP_PLAN WHERE Name=? FOR UPDATE', 's', [$name]);

    if ($legacy !== null && $current !== null && (int) $legacy['MembershipPlanID'] !== (int) $current['MembershipPlanID']) {
        throw new RuntimeException("Cannot replace reserved demo plan {$legacyName}: {$name} already belongs to another plan.");
    }

    $plan = $current ?? $legacy;
    if ($legacy !== null && $current === null) {
        $owned = db_one(
            'SELECT MembershipID FROM MEMBERSHIP WHERE MembershipPlanID=? AND MemberID=? LIMIT 1',
            'ii',
            [(int) $legacy['MembershipPlanID'], $demoMemberId]
        );
        if ($owned === null) {
            throw new RuntimeException("Refusing to rename {$legacyName}: it is not linked to the expected demo member.");
        }
    }

    if ($plan === null) {
        return demo_upsert('MEMBERSHIP_PLAN', 'MembershipPlanID', 'Name', $name, $values);
    }

    $columns = array_keys($values);
    $assignments = implode(',', array_map(static fn(string $column): string => "{$column}=?", $columns));
    db_execute(
        "UPDATE MEMBERSHIP_PLAN SET Name=?,{$assignments} WHERE MembershipPlanID=?",
        's' . str_repeat('s', count($values)) . 'i',
        [$name, ...array_values($values), (int) $plan['MembershipPlanID']]
    );

    return (int) $plan['MembershipPlanID'];
}

try {
    $ids = transaction(static function (): array {
        $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash the demo password.');
        }

        $users = [];
        foreach (DEMO_ACCOUNTS as $role => $email) {
            $existing = db_one('SELECT UserAccountID,Role FROM USER_ACCOUNT WHERE Email=? FOR UPDATE', 's', [$email]);
            if ($existing !== null && $existing['Role'] !== $role) {
                throw new RuntimeException("Reserved demo address {$email} belongs to another role.");
            }
            $users[$role] = demo_upsert('USER_ACCOUNT', 'UserAccountID', 'Email', $email, [
                'PasswordHash' => $hash,
                'Role' => $role,
                'Status' => 'ACTIVE',
                'EmailVerifiedAt' => date('Y-m-d H:i:s'),
                'FailedLoginCount' => '0',
                'LockedUntil' => null,
            ]);
        }

        $member = db_one('SELECT MemberID FROM MEMBER WHERE UserAccountID=? FOR UPDATE', 'i', [$users['MEMBER']]);
        if ($member === null) {
            db_execute("INSERT INTO MEMBER (UserAccountID,MemberNumber,FirstName,LastName,DateOfBirth,Phone,EmergencyContactName,EmergencyContactPhone,JoinedOn,Status) VALUES (?,'DEMO-MEM-001','Demo','Member','2000-01-01','+94000000001','Demo Contact','+94000000003',CURDATE(),'ACTIVE')", 'i', [$users['MEMBER']]);
            $memberId = (int) db()->insert_id;
        } else {
            $memberId = (int) $member['MemberID'];
            db_execute("UPDATE MEMBER SET MemberNumber='DEMO-MEM-001',FirstName='Demo',LastName='Member',DateOfBirth='2000-01-01',Phone='+94000000001',EmergencyContactName='Demo Contact',EmergencyContactPhone='+94000000003',JoinedOn=CURDATE(),Status='ACTIVE' WHERE MemberID=?", 'i', [$memberId]);
        }

        $trainer = db_one('SELECT TrainerID FROM TRAINER WHERE UserAccountID=? FOR UPDATE', 'i', [$users['TRAINER']]);
        if ($trainer === null) {
            db_execute("INSERT INTO TRAINER (UserAccountID,TrainerNumber,FirstName,LastName,Phone,Specialization,Bio,HireDate,Status) VALUES (?,'DEMO-TRN-001','Demo','Trainer','+94000000002','Strength and mobility','Fictional trainer for local demonstrations.',CURDATE(),'ACTIVE')", 'i', [$users['TRAINER']]);
            $trainerId = (int) db()->insert_id;
        } else {
            $trainerId = (int) $trainer['TrainerID'];
            db_execute("UPDATE TRAINER SET TrainerNumber='DEMO-TRN-001',FirstName='Demo',LastName='Trainer',Phone='+94000000002',Specialization='Strength and mobility',Bio='Fictional trainer for local demonstrations.',HireDate=CURDATE(),Status='ACTIVE',ReviewedAt=NULL,ReviewedByUserAccountID=NULL,RejectionReason=NULL WHERE TrainerID=?", 'i', [$trainerId]);
        }

        $planId = demo_upsert_plan('Demo All Access', 'Basic Fitness', [
            'Description' => 'Access to gym facilities and standard workout equipment.',
            'DurationMonths' => '1',
            'Price' => '4000.00',
            'Currency' => 'LKR',
            'ClassLimit' => '4',
            'IsActive' => '1',
        ], $memberId);

        $membership = db_one('SELECT MembershipID FROM MEMBERSHIP WHERE MemberID=? FOR UPDATE', 'i', [$memberId]);
        $startsOn = date('Y-m-d');
        $endsOn = (new DateTimeImmutable($startsOn))->modify('+1 month')->modify('-1 day')->format('Y-m-d');
        if ($membership === null) {
            db_execute("INSERT INTO MEMBERSHIP (MemberID,MembershipPlanID,PlanNameSnapshot,PriceSnapshot,CurrencySnapshot,StartsOn,EndsOn,Status) VALUES (?,?,'Basic Fitness',4000.00,'LKR',?,?,'ACTIVE')", 'iiss', [$memberId, $planId, $startsOn, $endsOn]);
            $membershipId = (int) db()->insert_id;
        } else {
            $membershipId = (int) $membership['MembershipID'];
            db_execute("UPDATE MEMBERSHIP SET MembershipPlanID=?,PlanNameSnapshot='Basic Fitness',PriceSnapshot=4000.00,CurrencySnapshot='LKR',StartsOn=?,EndsOn=?,Status='ACTIVE',CancelledAt=NULL WHERE MembershipID=?", 'issi', [$planId, $startsOn, $endsOn, $membershipId]);
        }

        $demoPayment = db_one('SELECT MembershipPaymentID FROM MEMBERSHIP_PAYMENT WHERE IdempotencyKey=? FOR UPDATE', 's', ['V2-DEMO-PAYMENT-001']);
        if ($demoPayment === null) {
            db_execute("INSERT INTO MEMBERSHIP_PAYMENT (MembershipID,Amount,Currency,Status,Method,TransactionReference,IdempotencyKey,PaidAt,RecordedByUserAccountID,Notes) VALUES (?,4000.00,'LKR','PAID','CARD','V2-DEMO-TXN-001','V2-DEMO-PAYMENT-001',NOW(),?,'Fictional demo payment')", 'ii', [$membershipId, $users['MEMBER']]);
        } else {
            db_execute("UPDATE MEMBERSHIP_PAYMENT SET MembershipID=?,Amount=4000.00,Currency='LKR',Status='PAID',Method='CARD',TransactionReference='V2-DEMO-TXN-001',PaidAt=NOW(),RecordedByUserAccountID=?,Notes='Fictional demo payment' WHERE MembershipPaymentID=?", 'iii', [$membershipId, $users['MEMBER'], (int) $demoPayment['MembershipPaymentID']]);
        }

        $class = db_one("SELECT GymClassID FROM GYM_CLASS WHERE Name='Demo Functional Fitness' AND TrainerID=? FOR UPDATE", 'i', [$trainerId]);
        $start = (new DateTimeImmutable('today 17:00'))->modify('+7 days')->format('Y-m-d H:i:s');
        $end = (new DateTimeImmutable('today 18:00'))->modify('+7 days')->format('Y-m-d H:i:s');
        if ($class === null) {
            db_execute("INSERT INTO GYM_CLASS (TrainerID,Name,Description,StartsAt,EndsAt,Capacity,Location,Status) VALUES (?,'Demo Functional Fitness','Fictional class for local demonstrations.',?,?,10,'Demo Studio','SCHEDULED')", 'iss', [$trainerId, $start, $end]);
            $classId = (int) db()->insert_id;
        } else {
            $classId = (int) $class['GymClassID'];
            db_execute("UPDATE GYM_CLASS SET Description='Fictional class for local demonstrations.',StartsAt=?,EndsAt=?,Capacity=10,Location='Demo Studio',Status='SCHEDULED' WHERE GymClassID=?", 'ssi', [$start, $end, $classId]);
        }

        $enrollment = db_one('SELECT ClassEnrollmentID FROM CLASS_ENROLLMENT WHERE GymClassID=? AND MemberID=? FOR UPDATE', 'ii', [$classId, $memberId]);
        if ($enrollment === null) {
            db_execute("INSERT INTO CLASS_ENROLLMENT (GymClassID,MemberID,Status) VALUES (?,?,'ENROLLED')", 'ii', [$classId, $memberId]);
            $enrollmentId = (int) db()->insert_id;
        } else {
            $enrollmentId = (int) $enrollment['ClassEnrollmentID'];
            db_execute("UPDATE CLASS_ENROLLMENT SET Status='ENROLLED',EnrolledAt=NOW(),CancelledAt=NULL WHERE ClassEnrollmentID=?", 'i', [$enrollmentId]);
        }

        $attendance = db_one('SELECT ClassAttendanceID FROM CLASS_ATTENDANCE WHERE ClassEnrollmentID=?', 'i', [$enrollmentId]);
        if ($attendance === null) {
            db_execute("INSERT INTO CLASS_ATTENDANCE (ClassEnrollmentID,GymClassID,Status,CheckedInAt,MarkedByUserAccountID,Notes) VALUES (?,?,'PRESENT',NOW(),?,'Fictional demo attendance')", 'iii', [$enrollmentId, $classId, $users['TRAINER']]);
        } else {
            db_execute("UPDATE CLASS_ATTENDANCE SET GymClassID=?,Status='PRESENT',CheckedInAt=NOW(),MarkedByUserAccountID=?,Notes='Fictional demo attendance' WHERE ClassAttendanceID=?", 'iii', [$classId, $users['TRAINER'], (int) $attendance['ClassAttendanceID']]);
        }

        $visit = db_one("SELECT GymVisitID FROM GYM_VISIT WHERE MemberID=? AND Notes='Fictional demo visit'", 'i', [$memberId]);
        if ($visit === null) {
            db_execute("INSERT INTO GYM_VISIT (MemberID,CheckedInAt,CheckedOutAt,CheckedInByUserAccountID,CheckedOutByUserAccountID,Notes) VALUES (?,DATE_SUB(NOW(),INTERVAL 2 HOUR),DATE_SUB(NOW(),INTERVAL 1 HOUR),?,?,'Fictional demo visit')", 'iii', [$memberId, $users['RECEPTIONIST'], $users['RECEPTIONIST']]);
        }

        $scenarios = [
            [
                'number' => '101',
                'member' => ['Avery', 'Sample', '1998-02-14', '+94000000101'],
                'trainer' => ['Riley', 'Example', '+94000000201', 'Functional fitness'],
                'plan' => ['Demo Starter Monthly', 'Standard Plus', 'Full gym access plus selected group fitness classes.', '3', '10500.00', '8'],
                'class' => 'Demo Mobility Foundations',
                'attendance' => 'PRESENT',
                'payment_method' => 'CARD',
            ],
            [
                'number' => '102',
                'member' => ['Jordan', 'Placeholder', '1996-05-23', '+94000000102'],
                'trainer' => ['Morgan', 'Sample', '+94000000202', 'Cardio conditioning'],
                'plan' => ['Demo Fitness Quarterly', 'Premium', 'Full gym and group-class access plus 1 personal training session per month.', '6', '18000.00', null],
                'class' => 'Demo Cardio Circuit',
                'attendance' => 'LATE',
                'payment_method' => 'CASH',
            ],
            [
                'number' => '103',
                'member' => ['Casey', 'Example', '2001-08-09', '+94000000103'],
                'trainer' => ['Taylor', 'Placeholder', '+94000000203', 'Strength training'],
                'plan' => ['Demo Strength Quarterly', 'VIP Elite', 'Full gym access, unlimited classes, 2 personal training sessions per month, and nutrition consultation.', '12', '32000.00', null],
                'class' => 'Demo Strength Basics',
                'attendance' => 'ABSENT',
                'payment_method' => 'BANK_TRANSFER',
            ],
            [
                'number' => '104',
                'member' => ['Quinn', 'Fiction', '1999-11-30', '+94000000104'],
                'trainer' => ['Cameron', 'Fiction', '+94000000204', 'Yoga and flexibility'],
                'plan' => ['Demo Unlimited Half-Year', 'Family Plan', 'Membership package for 2–4 family members with full gym access.', '12', '50000.00', null],
                'class' => 'Demo Yoga Flow',
                'attendance' => 'EXCUSED',
                'payment_method' => 'OTHER',
            ],
            [
                'number' => '105',
                'member' => ['Skyler', 'Mock', '1997-04-17', '+94000000105'],
                'trainer' => ['Dakota', 'Mock', '+94000000205', 'Endurance coaching'],
                'plan' => ['Demo Annual Plus', 'Student Plan', 'Discounted gym membership for students with a valid student ID.', '6', '8500.00', '6'],
                'class' => 'Demo Endurance Workshop',
                'attendance' => 'PRESENT',
                'payment_method' => 'CARD',
            ],
        ];

        $scenarioIds = [];
        foreach ($scenarios as $index => $scenario) {
            $number = $scenario['number'];
            $memberEmail = "member.demo.{$number}@example.test";
            $trainerEmail = "trainer.demo.{$number}@example.test";
            $scenarioUsers = [];

            foreach (['MEMBER' => $memberEmail, 'TRAINER' => $trainerEmail] as $role => $email) {
                $existing = db_one('SELECT UserAccountID,Role FROM USER_ACCOUNT WHERE Email=? FOR UPDATE', 's', [$email]);
                if ($existing !== null && $existing['Role'] !== $role) {
                    throw new RuntimeException("Reserved demo address {$email} belongs to another role.");
                }
                $scenarioUsers[$role] = demo_upsert('USER_ACCOUNT', 'UserAccountID', 'Email', $email, [
                    'PasswordHash' => $hash,
                    'Role' => $role,
                    'Status' => 'ACTIVE',
                    'EmailVerifiedAt' => date('Y-m-d H:i:s'),
                    'FailedLoginCount' => '0',
                    'LockedUntil' => null,
                ]);
            }

            [$memberFirstName, $memberLastName, $dateOfBirth, $memberPhone] = $scenario['member'];
            $memberNumber = "DEMO-MEM-{$number}";
            $scenarioMember = db_one('SELECT MemberID,UserAccountID FROM MEMBER WHERE MemberNumber=? FOR UPDATE', 's', [$memberNumber]);
            if ($scenarioMember !== null && (int) $scenarioMember['UserAccountID'] !== $scenarioUsers['MEMBER']) {
                throw new RuntimeException("Reserved demo member number {$memberNumber} belongs to another account.");
            }
            if ($scenarioMember === null) {
                db_execute(
                    "INSERT INTO MEMBER (UserAccountID,MemberNumber,FirstName,LastName,DateOfBirth,Phone,EmergencyContactName,EmergencyContactPhone,JoinedOn,Status) VALUES (?,?,?,?,?,?,?, ?,CURDATE(),'ACTIVE')",
                    'isssssss',
                    [$scenarioUsers['MEMBER'], $memberNumber, $memberFirstName, $memberLastName, $dateOfBirth, $memberPhone, 'Fictional Emergency Contact', '+94000000999']
                );
                $scenarioMemberId = (int) db()->insert_id;
            } else {
                $scenarioMemberId = (int) $scenarioMember['MemberID'];
                db_execute(
                    "UPDATE MEMBER SET FirstName=?,LastName=?,DateOfBirth=?,Phone=?,EmergencyContactName='Fictional Emergency Contact',EmergencyContactPhone='+94000000999',JoinedOn=CURDATE(),Status='ACTIVE' WHERE MemberID=?",
                    'ssssi',
                    [$memberFirstName, $memberLastName, $dateOfBirth, $memberPhone, $scenarioMemberId]
                );
            }

            [$trainerFirstName, $trainerLastName, $trainerPhone, $specialization] = $scenario['trainer'];
            $trainerNumber = "DEMO-TRN-{$number}";
            $scenarioTrainer = db_one('SELECT TrainerID,UserAccountID FROM TRAINER WHERE TrainerNumber=? FOR UPDATE', 's', [$trainerNumber]);
            if ($scenarioTrainer !== null && (int) $scenarioTrainer['UserAccountID'] !== $scenarioUsers['TRAINER']) {
                throw new RuntimeException("Reserved demo trainer number {$trainerNumber} belongs to another account.");
            }
            if ($scenarioTrainer === null) {
                db_execute(
                    "INSERT INTO TRAINER (UserAccountID,TrainerNumber,FirstName,LastName,Phone,Specialization,Bio,HireDate,Status) VALUES (?,?,?,?,?,?,'Clearly fictional trainer for local demonstrations.',CURDATE(),'ACTIVE')",
                    'isssss',
                    [$scenarioUsers['TRAINER'], $trainerNumber, $trainerFirstName, $trainerLastName, $trainerPhone, $specialization]
                );
                $scenarioTrainerId = (int) db()->insert_id;
            } else {
                $scenarioTrainerId = (int) $scenarioTrainer['TrainerID'];
                db_execute(
                    "UPDATE TRAINER SET FirstName=?,LastName=?,Phone=?,Specialization=?,Bio='Clearly fictional trainer for local demonstrations.',HireDate=CURDATE(),Status='ACTIVE',ReviewedAt=NULL,ReviewedByUserAccountID=NULL,RejectionReason=NULL WHERE TrainerID=?",
                    'ssssi',
                    [$trainerFirstName, $trainerLastName, $trainerPhone, $specialization, $scenarioTrainerId]
                );
            }

            [$legacyPlanName, $planName, $planDescription, $durationMonths, $price, $classLimit] = $scenario['plan'];
            $scenarioPlanId = demo_upsert_plan($legacyPlanName, $planName, [
                'Description' => $planDescription,
                'DurationMonths' => $durationMonths,
                'Price' => $price,
                'Currency' => 'LKR',
                'ClassLimit' => $classLimit,
                'IsActive' => '1',
            ], $scenarioMemberId);

            $membershipStartsOn = (new DateTimeImmutable('today'))->modify('-15 days')->format('Y-m-d');
            $membershipEndsOn = (new DateTimeImmutable($membershipStartsOn))
                ->modify("+{$durationMonths} months")
                ->modify('-1 day')
                ->format('Y-m-d');
            $scenarioMembership = db_one(
                'SELECT MembershipID FROM MEMBERSHIP WHERE MemberID=? FOR UPDATE',
                'i',
                [$scenarioMemberId]
            );
            if ($scenarioMembership === null) {
                db_execute(
                    "INSERT INTO MEMBERSHIP (MemberID,MembershipPlanID,PlanNameSnapshot,PriceSnapshot,CurrencySnapshot,StartsOn,EndsOn,Status) VALUES (?,?,?,?, 'LKR',?,?,'ACTIVE')",
                    'iisdss',
                    [$scenarioMemberId, $scenarioPlanId, $planName, (float) $price, $membershipStartsOn, $membershipEndsOn]
                );
                $scenarioMembershipId = (int) db()->insert_id;
            } else {
                $scenarioMembershipId = (int) $scenarioMembership['MembershipID'];
                db_execute(
                    "UPDATE MEMBERSHIP SET MembershipPlanID=?,PlanNameSnapshot=?,PriceSnapshot=?,CurrencySnapshot='LKR',StartsOn=?,EndsOn=?,Status='ACTIVE',CancelledAt=NULL WHERE MembershipID=?",
                    'isdssi',
                    [$scenarioPlanId, $planName, (float) $price, $membershipStartsOn, $membershipEndsOn, $scenarioMembershipId]
                );
            }

            $paymentKey = "V2-DEMO-PAYMENT-{$number}";
            $paymentReference = "V2-DEMO-TXN-{$number}";
            $scenarioPayment = db_one('SELECT MembershipPaymentID,MembershipID FROM MEMBERSHIP_PAYMENT WHERE IdempotencyKey=? FOR UPDATE', 's', [$paymentKey]);
            if ($scenarioPayment !== null && (int) $scenarioPayment['MembershipID'] !== $scenarioMembershipId) {
                throw new RuntimeException("Reserved demo payment key {$paymentKey} belongs to another membership.");
            }
            if ($scenarioPayment === null) {
                db_execute(
                    "INSERT INTO MEMBERSHIP_PAYMENT (MembershipID,Amount,Currency,Status,Method,TransactionReference,IdempotencyKey,PaidAt,RecordedByUserAccountID,Notes) VALUES (?,?,'LKR','PAID',?,?,?,NOW(),?,'Clearly fictional demo payment')",
                    'idsssi',
                    [$scenarioMembershipId, (float) $price, $scenario['payment_method'], $paymentReference, $paymentKey, $users['RECEPTIONIST']]
                );
                $scenarioPaymentId = (int) db()->insert_id;
            } else {
                $scenarioPaymentId = (int) $scenarioPayment['MembershipPaymentID'];
                db_execute(
                    "UPDATE MEMBERSHIP_PAYMENT SET Amount=?,Currency='LKR',Status='PAID',Method=?,TransactionReference=?,PaidAt=NOW(),RecordedByUserAccountID=?,Notes='Clearly fictional demo payment' WHERE MembershipPaymentID=?",
                    'dssii',
                    [(float) $price, $scenario['payment_method'], $paymentReference, $users['RECEPTIONIST'], $scenarioPaymentId]
                );
            }

            $classStart = (new DateTimeImmutable('today 09:00'))
                ->modify('-' . (10 - $index) . ' days')
                ->format('Y-m-d H:i:s');
            $classEnd = (new DateTimeImmutable($classStart))->modify('+1 hour')->format('Y-m-d H:i:s');
            $scenarioClass = db_one('SELECT GymClassID,TrainerID FROM GYM_CLASS WHERE Name=? FOR UPDATE', 's', [$scenario['class']]);
            if ($scenarioClass !== null && (int) $scenarioClass['TrainerID'] !== $scenarioTrainerId) {
                throw new RuntimeException("Reserved demo class {$scenario['class']} belongs to another trainer.");
            }
            if ($scenarioClass === null) {
                db_execute(
                    "INSERT INTO GYM_CLASS (TrainerID,Name,Description,StartsAt,EndsAt,Capacity,Location,Status) VALUES (?,?,'Clearly fictional completed class for local demonstrations.',?,?,12,'Demo Studio','COMPLETED')",
                    'isss',
                    [$scenarioTrainerId, $scenario['class'], $classStart, $classEnd]
                );
                $scenarioClassId = (int) db()->insert_id;
            } else {
                $scenarioClassId = (int) $scenarioClass['GymClassID'];
                db_execute(
                    "UPDATE GYM_CLASS SET Description='Clearly fictional completed class for local demonstrations.',StartsAt=?,EndsAt=?,Capacity=12,Location='Demo Studio',Status='COMPLETED' WHERE GymClassID=?",
                    'ssi',
                    [$classStart, $classEnd, $scenarioClassId]
                );
            }

            $scenarioEnrollment = db_one('SELECT ClassEnrollmentID FROM CLASS_ENROLLMENT WHERE GymClassID=? AND MemberID=? FOR UPDATE', 'ii', [$scenarioClassId, $scenarioMemberId]);
            if ($scenarioEnrollment === null) {
                db_execute("INSERT INTO CLASS_ENROLLMENT (GymClassID,MemberID,Status,EnrolledAt) VALUES (?,?,'ENROLLED',DATE_SUB(NOW(),INTERVAL 20 DAY))", 'ii', [$scenarioClassId, $scenarioMemberId]);
                $scenarioEnrollmentId = (int) db()->insert_id;
            } else {
                $scenarioEnrollmentId = (int) $scenarioEnrollment['ClassEnrollmentID'];
                db_execute("UPDATE CLASS_ENROLLMENT SET Status='ENROLLED',EnrolledAt=DATE_SUB(NOW(),INTERVAL 20 DAY),CancelledAt=NULL WHERE ClassEnrollmentID=?", 'i', [$scenarioEnrollmentId]);
            }

            $checkedInAt = in_array($scenario['attendance'], ['PRESENT', 'LATE'], true) ? $classStart : null;
            $scenarioAttendance = db_one('SELECT ClassAttendanceID FROM CLASS_ATTENDANCE WHERE ClassEnrollmentID=? FOR UPDATE', 'i', [$scenarioEnrollmentId]);
            if ($scenarioAttendance === null) {
                db_execute(
                    "INSERT INTO CLASS_ATTENDANCE (ClassEnrollmentID,GymClassID,Status,CheckedInAt,MarkedByUserAccountID,Notes) VALUES (?,?,?,?,?,'Clearly fictional demo attendance')",
                    'iissi',
                    [$scenarioEnrollmentId, $scenarioClassId, $scenario['attendance'], $checkedInAt, $scenarioUsers['TRAINER']]
                );
                $scenarioAttendanceId = (int) db()->insert_id;
            } else {
                $scenarioAttendanceId = (int) $scenarioAttendance['ClassAttendanceID'];
                db_execute(
                    "UPDATE CLASS_ATTENDANCE SET GymClassID=?,Status=?,CheckedInAt=?,MarkedByUserAccountID=?,Notes='Clearly fictional demo attendance' WHERE ClassAttendanceID=?",
                    'issii',
                    [$scenarioClassId, $scenario['attendance'], $checkedInAt, $scenarioUsers['TRAINER'], $scenarioAttendanceId]
                );
            }

            $visitNote = "Fictional demo visit {$number}";
            $visitStart = (new DateTimeImmutable('today 14:00'))->modify('-' . (5 + $index) . ' days');
            $visitEnd = $visitStart->modify('+75 minutes');
            $scenarioVisit = db_one('SELECT GymVisitID FROM GYM_VISIT WHERE MemberID=? AND Notes=? FOR UPDATE', 'is', [$scenarioMemberId, $visitNote]);
            if ($scenarioVisit === null) {
                db_execute(
                    'INSERT INTO GYM_VISIT (MemberID,CheckedInAt,CheckedOutAt,CheckedInByUserAccountID,CheckedOutByUserAccountID,Notes) VALUES (?,?,?,?,?,?)',
                    'issiis',
                    [$scenarioMemberId, $visitStart->format('Y-m-d H:i:s'), $visitEnd->format('Y-m-d H:i:s'), $users['RECEPTIONIST'], $users['RECEPTIONIST'], $visitNote]
                );
                $scenarioVisitId = (int) db()->insert_id;
            } else {
                $scenarioVisitId = (int) $scenarioVisit['GymVisitID'];
                db_execute(
                    'UPDATE GYM_VISIT SET CheckedInAt=?,CheckedOutAt=?,CheckedInByUserAccountID=?,CheckedOutByUserAccountID=? WHERE GymVisitID=?',
                    'ssiii',
                    [$visitStart->format('Y-m-d H:i:s'), $visitEnd->format('Y-m-d H:i:s'), $users['RECEPTIONIST'], $users['RECEPTIONIST'], $scenarioVisitId]
                );
            }

            $scenarioIds[$number] = [
                'member' => $scenarioMemberId,
                'trainer' => $scenarioTrainerId,
                'plan' => $scenarioPlanId,
                'membership' => $scenarioMembershipId,
                'payment' => $scenarioPaymentId,
                'class' => $scenarioClassId,
                'enrollment' => $scenarioEnrollmentId,
                'attendance' => $scenarioAttendanceId,
                'visit' => $scenarioVisitId,
            ];
        }

        return [
            'original' => compact('memberId', 'trainerId', 'planId', 'membershipId', 'classId', 'enrollmentId'),
            'added_scenarios' => $scenarioIds,
        ];
    });

    echo "Fictional local demo data is ready. Shared password: " . DEMO_PASSWORD . "\n";
    foreach (DEMO_ACCOUNTS as $role => $email) {
        echo sprintf("  %-14s %s\n", $role, $email);
    }
    echo "  MEMBER/TRAINER member.demo.101@example.test through member.demo.105@example.test\n";
    echo "                 trainer.demo.101@example.test through trainer.demo.105@example.test\n";
    echo 'Record IDs: ' . json_encode($ids, JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Demo seed failed: ' . $error->getMessage() . "\n");
    exit(1);
}
