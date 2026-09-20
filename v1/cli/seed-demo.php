<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../bootstrap/app.php';

if (env('APP_ENV', 'production') !== 'local' && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "Demo data may only be seeded in APP_ENV=local. Pass --force to override.\n");
    exit(1);
}

const DEMO_PASSWORD = 'Demo@12345';
const DEMO_ACCOUNTS = [
    'SUPER_ADMIN' => 'admin.demo@gmail.com',
    'RECEPTIONIST' => 'reception.demo@gmail.com',
    'TRAINER' => 'trainer.demo@gmail.com',
    'MEMBER' => 'member.demo@gmail.com',
];

$connection = db();
$locked = (int)($connection->query("SELECT GET_LOCK('gympro-demo-seed', 5) AS Acquired")->fetch_assoc()['Acquired'] ?? 0);
if ($locked !== 1) {
    fwrite(STDERR, "Another demo seed is already running.\n");
    exit(1);
}

$findId = static function (string $sql, string $column, string $types = '', array $params = []): ?int {
    $row = db_one($sql, $types, $params);
    return $row ? (int)$row[$column] : null;
};

try {
    $summary = transaction(function () use ($findId): array {
        $passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Unable to hash the demo password.');
        }

        $planName = 'GymPro Demo All Access';
        $planId = $findId('SELECT PlanID FROM MEMBERSHIP_PLAN WHERE PlanName=?', 'PlanID', 's', [$planName]);
        if ($planId === null) {
            db_execute(
                'INSERT INTO MEMBERSHIP_PLAN (PlanName,PlanDetails,Duration,Price,IsActive,AllowsGymAccess,AllowsClasses,ClassLimitPerCycle) VALUES (?,?,?,?,1,1,1,?)',
                'ssidi',
                [$planName, 'Demo plan with gym access and up to four classes per cycle', 3, 7500.00, 4]
            );
            $planId = db()->insert_id;
        } else {
            db_execute(
                'UPDATE MEMBERSHIP_PLAN SET PlanDetails=?,Duration=3,Price=7500.00,IsActive=1,AllowsGymAccess=1,AllowsClasses=1,ClassLimitPerCycle=4 WHERE PlanID=?',
                'si',
                ['Demo plan with gym access and up to four classes per cycle', $planId]
            );
        }

        $memberEmail = DEMO_ACCOUNTS['MEMBER'];
        $memberId = $findId('SELECT MemberID FROM MEMBER WHERE Email=?', 'MemberID', 's', [$memberEmail]);
        if ($memberId === null) {
            db_execute(
                'INSERT INTO MEMBER (Name,NIC,DOB,Gender,Email,Address,PhoneNo,RegDate,PlanID,PlanStartDate,PlanEndDate) VALUES (?,?,?,?,?,?,?,CURDATE(),?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 3 MONTH))',
                'sssssssi',
                ['Demo Member', 'DEMO-NIC-0001', '2001-05-14', 'Other', $memberEmail, '42 University Road, Colombo', '0770001001', $planId]
            );
            $memberId = db()->insert_id;
            db_execute("UPDATE MEMBER SET MemberNumber=CONCAT('MEM-',LPAD(MemberID,6,'0')) WHERE MemberID=?", 'i', [$memberId]);
        } else {
            db_execute(
                'UPDATE MEMBER SET Name=?,NIC=?,DOB=?,Gender=?,Address=?,PhoneNo=?,PlanID=?,PlanStartDate=CURDATE(),PlanEndDate=DATE_ADD(CURDATE(),INTERVAL 3 MONTH),ArchivedAt=NULL WHERE MemberID=?',
                'ssssssii',
                ['Demo Member', 'DEMO-NIC-0001', '2001-05-14', 'Other', '42 University Road, Colombo', '0770001001', $planId, $memberId]
            );
        }

        $trainerEmail = DEMO_ACCOUNTS['TRAINER'];
        $trainerId = $findId('SELECT TrainerID FROM TRAINER WHERE Email=?', 'TrainerID', 's', [$trainerEmail]);
        if ($trainerId === null) {
            db_execute(
                "INSERT INTO TRAINER (Name,PhoneNo,Email,Experience,Bio,Specialization,ApplicationStatus) VALUES (?,?,?,?,?,?,'APPROVED')",
                'sssiss',
                ['Demo Trainer', '0770002001', $trainerEmail, 6, 'Certified group fitness trainer used for the GymPro demonstration.', 'Strength & Conditioning']
            );
            $trainerId = db()->insert_id;
        } else {
            db_execute(
                "UPDATE TRAINER SET Name=?,PhoneNo=?,Experience=6,Bio=?,Specialization=?,ApplicationStatus='APPROVED',RejectionReason=NULL,ArchivedAt=NULL WHERE TrainerID=?",
                'ssssi',
                ['Demo Trainer', '0770002001', 'Certified group fitness trainer used for the GymPro demonstration.', 'Strength & Conditioning', $trainerId]
            );
        }

        $ensureAccount = static function (string $email, string $role, string $hash, ?int $memberId = null, ?int $trainerId = null): int {
            $existing = db_one('SELECT UserID,Role,MemberID,TrainerID FROM USER_ACCOUNT WHERE Email=? FOR UPDATE', 's', [$email]);
            if ($existing) {
                if ($existing['Role'] !== $role) {
                    throw new RuntimeException("Reserved demo email {$email} already belongs to another role.");
                }
                if ($memberId !== null && $existing['MemberID'] !== null && (int)$existing['MemberID'] !== $memberId) {
                    throw new RuntimeException("Reserved demo member account {$email} is linked to another profile.");
                }
                if ($trainerId !== null && $existing['TrainerID'] !== null && (int)$existing['TrainerID'] !== $trainerId) {
                    throw new RuntimeException("Reserved demo trainer account {$email} is linked to another profile.");
                }
                db_execute(
                    "UPDATE USER_ACCOUNT SET PasswordHash=?,Status='ACTIVE',MemberID=?,TrainerID=?,EmailVerifiedAt=COALESCE(EmailVerifiedAt,NOW()),PaymentDueAt=NULL,FailedLoginCount=0,LockedUntil=NULL,RejectedAt=NULL,RejectionReason=NULL,ExpiredAt=NULL,AnonymizedAt=NULL WHERE UserID=?",
                    'siii',
                    [$hash, $memberId, $trainerId, $existing['UserID']]
                );
                return (int)$existing['UserID'];
            }
            db_execute(
                "INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,MemberID,TrainerID,EmailVerifiedAt) VALUES (?,?,?,'ACTIVE',?,?,NOW())",
                'sssii',
                [$email, $hash, $role, $memberId, $trainerId]
            );
            return db()->insert_id;
        };

        $adminId = $ensureAccount(DEMO_ACCOUNTS['SUPER_ADMIN'], 'SUPER_ADMIN', $passwordHash);
        $receptionId = $ensureAccount(DEMO_ACCOUNTS['RECEPTIONIST'], 'RECEPTIONIST', $passwordHash);
        $trainerUserId = $ensureAccount($trainerEmail, 'TRAINER', $passwordHash, null, $trainerId);
        $memberUserId = $ensureAccount($memberEmail, 'MEMBER', $passwordHash, $memberId);

        $subscriptionId = $findId("SELECT SubscriptionID FROM SUBSCRIPTION WHERE MemberID=? AND Source='DEMO'", 'SubscriptionID', 'i', [$memberId]);
        if ($subscriptionId === null) {
            db_execute(
                "INSERT INTO SUBSCRIPTION (MemberID,PlanID,PlanNameSnapshot,PriceSnapshot,DurationMonthsSnapshot,Status,StartsAt,EndsAt,Source) VALUES (?,?,?,?,3,'ACTIVE',NOW(),DATE_ADD(NOW(),INTERVAL 3 MONTH),'DEMO')",
                'iisd',
                [$memberId, $planId, $planName, 7500.00]
            );
            $subscriptionId = db()->insert_id;
        } else {
            db_execute(
                "UPDATE SUBSCRIPTION SET PlanID=?,PlanNameSnapshot=?,PriceSnapshot=7500.00,DurationMonthsSnapshot=3,Status='ACTIVE',PaymentDueAt=NULL,StartsAt=NOW(),EndsAt=DATE_ADD(NOW(),INTERVAL 3 MONTH),CancelAtPeriodEnd=0 WHERE SubscriptionID=?",
                'isi',
                [$planId, $planName, $subscriptionId]
            );
        }

        $paymentId = $findId("SELECT MembershipPaymentID FROM MEMBERSHIP_PAYMENT WHERE IdempotencyKey='GYMPRO-DEMO-MEMBER-PAYMENT'", 'MembershipPaymentID');
        if ($paymentId === null) {
            db_execute(
                "INSERT INTO MEMBERSHIP_PAYMENT (SubscriptionID,MemberID,Amount,Currency,Status,Provider,TransactionReference,IdempotencyKey,CardBrand,CardLast4,Source,PaidAt) VALUES (?,? ,7500.00,'LKR','SUCCEEDED','SIMULATED_CARD','GYMPRO-DEMO-TXN-001','GYMPRO-DEMO-MEMBER-PAYMENT','VISA','4242','SIMULATION',NOW())",
                'ii',
                [$subscriptionId, $memberId]
            );
            $paymentId = db()->insert_id;
        }

        $upsertClass = static function (string $name, string $date, string $start, string $end, string $status, int $trainerId, int $adminId, string $description, float $rate): int {
            $row = db_one('SELECT ClassID FROM CLASS WHERE ClassName=? AND TrainerID=? ORDER BY ClassID LIMIT 1 FOR UPDATE', 'si', [$name, $trainerId]);
            if ($row) {
                $classId = (int)$row['ClassID'];
                db_execute(
                    'UPDATE CLASS SET DateOfClass=?,StartTime=?,EndTime=?,Description=?,Capacity=10,Specialization=?,RatePerClass=?,Status=?,ReviewNotes=?,ReviewedBy=?,ReviewedAt=NOW() WHERE ClassID=?',
                    'sssssdssii',
                    [$date, $start, $end, $description, 'Strength & Conditioning', $rate, $status, 'Approved demo class', $adminId, $classId]
                );
                return $classId;
            }
            db_execute(
                'INSERT INTO CLASS (ClassName,DateOfClass,StartTime,EndTime,Description,Capacity,TrainerID,Specialization,RatePerClass,Status,ReviewNotes,ReviewedBy,ReviewedAt) VALUES (?,?,?,?,?,10,?,?,?, ?,?,?,NOW())',
                'sssssisdssi',
                [$name, $date, $start, $end, $description, $trainerId, 'Strength & Conditioning', $rate, $status, 'Approved demo class', $adminId]
            );
            return db()->insert_id;
        };

        $completedDate = (new DateTimeImmutable('today'))->modify('-7 days')->format('Y-m-d');
        $futureDate = (new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');
        $requestDate = (new DateTimeImmutable('today'))->modify('+14 days')->format('Y-m-d');
        $completedClassId = $upsertClass('Demo Strength Fundamentals', $completedDate, '09:00:00', '10:00:00', 'COMPLETED', $trainerId, $adminId, 'A completed class with attendance and paid commission.', 2500.00);
        $futureClassId = $upsertClass('Demo Functional Fitness', $futureDate, '17:00:00', '18:00:00', 'SCHEDULED', $trainerId, $adminId, 'A future class ready for the live demonstration.', 2800.00);
        $requestClassId = $upsertClass('Demo Mobility Workshop', $requestDate, '11:00:00', '12:00:00', 'SCHEDULED', $trainerId, $adminId, 'A future class with an enrollment request awaiting the trainer.', 2200.00);

        $upsertSession = static function (int $classId, string $startsAt, string $endsAt, string $status): int {
            $sessions = db_all('SELECT SessionID FROM CLASS_SESSION WHERE ClassID=? ORDER BY SessionID', 'i', [$classId]);
            if ($sessions) {
                $sessionId = (int)$sessions[0]['SessionID'];
                db_execute(
                    'UPDATE CLASS_SESSION SET StartsAt=?,EndsAt=?,Status=?,CompletedAt=CASE WHEN ?="COMPLETED" THEN ? ELSE NULL END WHERE SessionID=?',
                    'sssssi',
                    [$startsAt, $endsAt, $status, $status, $endsAt, $sessionId]
                );
                return $sessionId;
            }
            db_execute(
                'INSERT INTO CLASS_SESSION (ClassID,StartsAt,EndsAt,Status,CompletedAt) VALUES (?,?,?,?,CASE WHEN ?="COMPLETED" THEN ? ELSE NULL END)',
                'isssss',
                [$classId, $startsAt, $endsAt, $status, $status, $endsAt]
            );
            return db()->insert_id;
        };

        $completedSessionId = $upsertSession($completedClassId, $completedDate . ' 09:00:00', $completedDate . ' 10:00:00', 'COMPLETED');
        $futureSessionId = $upsertSession($futureClassId, $futureDate . ' 17:00:00', $futureDate . ' 18:00:00', 'SCHEDULED');
        $requestSessionId = $upsertSession($requestClassId, $requestDate . ' 11:00:00', $requestDate . ' 12:00:00', 'SCHEDULED');

        $upsertEnrollment = static function (int $memberId, int $classId, int $trainerUserId, string $status): int {
            $row = db_one('SELECT EnrollmentID FROM ENROLLMENT WHERE MemberID=? AND ClassID=? FOR UPDATE', 'ii', [$memberId, $classId]);
            if ($row) {
                if ($status === 'PENDING') {
                    db_execute("UPDATE ENROLLMENT SET Status='PENDING',RequestedAt=DATE_SUB(NOW(),INTERVAL 1 DAY),ReviewedAt=NULL,ReviewedBy=NULL,CancellationEffectiveAt=NULL,DecisionReason=NULL WHERE EnrollmentID=?", 'i', [$row['EnrollmentID']]);
                } else {
                    db_execute("UPDATE ENROLLMENT SET Status='ENROLLED',ReviewedAt=NOW(),ReviewedBy=?,CancellationEffectiveAt=NULL,DecisionReason=NULL WHERE EnrollmentID=?", 'ii', [$trainerUserId, $row['EnrollmentID']]);
                }
                return (int)$row['EnrollmentID'];
            }
            if ($status === 'PENDING') {
                db_execute("INSERT INTO ENROLLMENT (MemberID,ClassID,Status,RequestedAt) VALUES (?,?,'PENDING',DATE_SUB(NOW(),INTERVAL 1 DAY))", 'ii', [$memberId, $classId]);
            } else {
                db_execute("INSERT INTO ENROLLMENT (MemberID,ClassID,Status,RequestedAt,ReviewedAt,ReviewedBy) VALUES (?,?,'ENROLLED',DATE_SUB(NOW(),INTERVAL 14 DAY),NOW(),?)", 'iii', [$memberId, $classId, $trainerUserId]);
            }
            return db()->insert_id;
        };

        $completedEnrollmentId = $upsertEnrollment($memberId, $completedClassId, $trainerUserId, 'ENROLLED');
        $futureEnrollmentId = $upsertEnrollment($memberId, $futureClassId, $trainerUserId, 'ENROLLED');
        $pendingEnrollmentId = $upsertEnrollment($memberId, $requestClassId, $trainerUserId, 'PENDING');

        db_execute(
            "INSERT INTO CLASS_ATTENDANCE (SessionID,EnrollmentID,Status,MarkedBy,MarkedAt) VALUES (?,?,'PRESENT',?,?) ON DUPLICATE KEY UPDATE Status='PRESENT',MarkedBy=VALUES(MarkedBy),MarkedAt=VALUES(MarkedAt),CorrectionReason=NULL",
            'iiis',
            [$completedSessionId, $completedEnrollmentId, $trainerUserId, $completedDate . ' 10:00:00']
        );

        $gymVisitId = $findId("SELECT GymAttendanceID FROM GYM_ATTENDANCE WHERE MemberID=? AND CorrectionReason='GYMPRO_DEMO_VISIT'", 'GymAttendanceID', 'i', [$memberId]);
        $checkInAt = (new DateTimeImmutable('today 07:30:00'))->modify('-2 days')->format('Y-m-d H:i:s');
        $checkOutAt = (new DateTimeImmutable('today 09:00:00'))->modify('-2 days')->format('Y-m-d H:i:s');
        if ($gymVisitId === null) {
            db_execute(
                "INSERT INTO GYM_ATTENDANCE (MemberID,CheckInAt,CheckOutAt,CheckedInBy,CheckedOutBy,Status,CorrectionReason) VALUES (?,?,?,?,?,'CHECKED_OUT','GYMPRO_DEMO_VISIT')",
                'issii',
                [$memberId, $checkInAt, $checkOutAt, $receptionId, $receptionId]
            );
            $gymVisitId = db()->insert_id;
        } else {
            db_execute("UPDATE GYM_ATTENDANCE SET CheckInAt=?,CheckOutAt=?,CheckedInBy=?,CheckedOutBy=?,Status='CHECKED_OUT' WHERE GymAttendanceID=?", 'ssiii', [$checkInAt, $checkOutAt, $receptionId, $receptionId, $gymVisitId]);
        }

        $rule = db_one("SELECT RuleID,CalculationType,Rate FROM COMMISSION_RULE WHERE IsActive=1 AND EffectiveFrom<=CURDATE() AND (EffectiveTo IS NULL OR EffectiveTo>=CURDATE()) ORDER BY EffectiveFrom DESC,RuleID DESC LIMIT 1");
        if (!$rule) {
            throw new RuntimeException('No active commission rule is available for demo data.');
        }
        $basis = 2500.00;
        $commission = $rule['CalculationType'] === 'PERCENTAGE' ? round($basis * (float)$rule['Rate'] / 100, 2) : (float)$rule['Rate'];
        $commissionId = $findId('SELECT CommissionEntryID FROM COMMISSION_ENTRY WHERE SessionID=?', 'CommissionEntryID', 'i', [$completedSessionId]);
        if ($commissionId === null) {
            db_execute(
                "INSERT INTO COMMISSION_ENTRY (TrainerID,SessionID,RuleID,CalculationType,RateSnapshot,BasisAmount,Amount,Status,EarnedAt) VALUES (?,?,?,?,?,?,?,'PAID',?)",
                'iiisddds',
                [$trainerId, $completedSessionId, $rule['RuleID'], $rule['CalculationType'], $rule['Rate'], $basis, $commission, $completedDate . ' 10:00:00']
            );
            $commissionId = db()->insert_id;
        }

        $payoutId = $findId("SELECT PayoutID FROM PAYOUT WHERE Reference='GYMPRO-DEMO-PAYOUT-001'", 'PayoutID');
        if ($payoutId === null) {
            db_execute(
                "INSERT INTO PAYOUT (TrainerID,Amount,Status,Reference,PaidAt,CreatedBy) VALUES (?,?,'PAID','GYMPRO-DEMO-PAYOUT-001',NOW(),?)",
                'idi',
                [$trainerId, $commission, $adminId]
            );
            $payoutId = db()->insert_id;
        }
        if (!db_one('SELECT PayoutID FROM PAYOUT_ITEM WHERE CommissionEntryID=?', 'i', [$commissionId])) {
            db_execute('INSERT INTO PAYOUT_ITEM (PayoutID,CommissionEntryID,Amount) VALUES (?,?,?)', 'iid', [$payoutId, $commissionId, $commission]);
        }
        db_execute("UPDATE COMMISSION_ENTRY SET Status='PAID' WHERE CommissionEntryID=?", 'i', [$commissionId]);

        $ensureNotification = static function (int $senderId, int $recipientId, string $title, string $body, string $type, string $entityType, int $entityId, bool $read): int {
            $row = db_one(
                'SELECT n.NotificationID FROM NOTIFICATION n JOIN NOTIFICATION_RECIPIENT r ON r.NotificationID=n.NotificationID WHERE n.Title=? AND n.EntityType=? AND n.EntityID=? AND r.UserID=? ORDER BY n.NotificationID LIMIT 1',
                'ssii',
                [$title, $entityType, $entityId, $recipientId]
            );
            if ($row) {
                $notificationId = (int)$row['NotificationID'];
                db_execute('UPDATE NOTIFICATION SET SenderUserID=?,Body=?,Type=? WHERE NotificationID=?', 'issi', [$senderId, $body, $type, $notificationId]);
                db_execute('UPDATE NOTIFICATION_RECIPIENT SET ReadAt=CASE WHEN ?=1 THEN COALESCE(ReadAt,NOW()) ELSE NULL END WHERE NotificationID=? AND UserID=?', 'iii', [$read ? 1 : 0, $notificationId, $recipientId]);
                return $notificationId;
            }
            db_execute(
                'INSERT INTO NOTIFICATION (SenderUserID,Title,Body,Type,EntityType,EntityID) VALUES (?,?,?,?,?,?)',
                'issssi',
                [$senderId, $title, $body, $type, $entityType, $entityId]
            );
            $notificationId = db()->insert_id;
            db_execute(
                'INSERT INTO NOTIFICATION_RECIPIENT (NotificationID,UserID,ReadAt) VALUES (?,?,CASE WHEN ?=1 THEN NOW() ELSE NULL END)',
                'iii',
                [$notificationId, $recipientId, $read ? 1 : 0]
            );
            return $notificationId;
        };

        $ensureNotification($adminId, $memberUserId, 'Welcome to the GymPro demo', 'Your all-access membership is active and ready to use.', 'SUCCESS', 'SUBSCRIPTION', $subscriptionId, true);
        $ensureNotification($trainerUserId, $memberUserId, 'Upcoming class reminder', 'Demo Functional Fitness starts in seven days at 5:00 PM.', 'INFO', 'CLASS', $futureClassId, false);
        $ensureNotification($adminId, $trainerUserId, 'Commission payout recorded', 'Your completed demo class commission has been paid.', 'SUCCESS', 'PAYOUT', $payoutId, false);
        $ensureNotification($adminId, $receptionId, 'Reception demo ready', 'The demo member has active gym access and can be checked in.', 'INFO', 'MEMBER', $memberId, false);
        $ensureNotification($memberUserId, $trainerUserId, 'New class request', 'Demo Member requested Demo Mobility Workshop.', 'INFO', 'ENROLLMENT', $pendingEnrollmentId, false);

        $upsertConversation = static function (string $contextKey, string $channel, int $memberId, ?int $classId, ?int $trainerId, string $subject, array $participants, array $messages): int {
            $row = db_one('SELECT ConversationID FROM CONVERSATION WHERE ContextKey=? FOR UPDATE', 's', [$contextKey]);
            if ($row) {
                $conversationId = (int)$row['ConversationID'];
                db_execute("UPDATE CONVERSATION SET Channel=?,MemberID=?,ClassID=?,TrainerID=?,Subject=?,Status='OPEN' WHERE ConversationID=?", 'siiisi', [$channel, $memberId, $classId, $trainerId, $subject, $conversationId]);
            } else {
                db_execute("INSERT INTO CONVERSATION (Channel,MemberID,ClassID,TrainerID,Subject,Status,ContextKey) VALUES (?,?,?,?,?,'OPEN',?)", 'siiiss', [$channel, $memberId, $classId, $trainerId, $subject, $contextKey]);
                $conversationId = db()->insert_id;
            }
            foreach ($participants as $userId) {
                db_execute('INSERT IGNORE INTO CONVERSATION_PARTICIPANT (ConversationID,UserID) VALUES (?,?)', 'ii', [$conversationId, $userId]);
            }
            foreach ($messages as $message) {
                $existing = db_one('SELECT MessageID FROM CONVERSATION_MESSAGE WHERE ConversationID=? AND SenderUserID=? AND Body=?', 'iis', [$conversationId, $message['sender'], $message['body']]);
                if (!$existing) {
                    db_execute('INSERT INTO CONVERSATION_MESSAGE (ConversationID,SenderUserID,Body,CreatedAt) VALUES (?,?,?,?)', 'iiss', [$conversationId, $message['sender'], $message['body'], $message['created']]);
                }
            }
            db_execute('UPDATE CONVERSATION SET LastMessageAt=(SELECT MAX(CreatedAt) FROM CONVERSATION_MESSAGE WHERE ConversationID=?) WHERE ConversationID=?', 'ii', [$conversationId, $conversationId]);
            return $conversationId;
        };

        $trainerConversationId = $upsertConversation(
            'CLASS:' . $requestClassId . ':MEMBER:' . $memberId . ':TRAINER:' . $trainerId,
            'CLASS_TRAINER',
            $memberId,
            $requestClassId,
            $trainerId,
            'Mobility workshop requirements',
            [$memberUserId, $trainerUserId],
            [['sender' => $memberUserId, 'body' => 'Hello, do I need to bring any equipment for the mobility workshop?', 'created' => date('Y-m-d H:i:s', strtotime('-2 hours'))]]
        );
        $supportConversationId = $upsertConversation(
            'SUPPORT:MEMBER:' . $memberId,
            'ADMIN_SUPPORT',
            $memberId,
            null,
            null,
            'Membership plan question',
            [$memberUserId, $adminId],
            [['sender' => $memberUserId, 'body' => 'Hello GymPro Support, can you confirm how many classes my plan includes?', 'created' => date('Y-m-d H:i:s', strtotime('-1 hour'))]]
        );
        $ensureNotification($memberUserId, $trainerUserId, 'New class message', 'Open Messages to read and reply.', 'MESSAGE', 'CONVERSATION', $trainerConversationId, false);
        $ensureNotification($memberUserId, $adminId, 'New support message', 'Open Messages to read and reply.', 'MESSAGE', 'CONVERSATION', $supportConversationId, false);

        return [
            'accounts' => count(DEMO_ACCOUNTS),
            'plan_id' => $planId,
            'member_id' => $memberId,
            'trainer_id' => $trainerId,
            'subscription_id' => $subscriptionId,
            'payment_id' => $paymentId,
            'classes' => 3,
            'sessions' => [$completedSessionId, $futureSessionId, $requestSessionId],
            'enrollments' => [$completedEnrollmentId, $futureEnrollmentId, $pendingEnrollmentId],
            'conversations' => [$trainerConversationId, $supportConversationId],
            'gym_visit_id' => $gymVisitId,
            'commission_id' => $commissionId,
            'payout_id' => $payoutId,
        ];
    });

    echo "GymPro demo data is ready. Existing non-demo records were preserved.\n\n";
    echo "Login accounts (shared password: " . DEMO_PASSWORD . "):\n";
    foreach (DEMO_ACCOUNTS as $role => $email) {
        echo sprintf("  %-14s %s\n", $role, $email);
    }
    echo "\nCreated or refreshed: {$summary['accounts']} role accounts, 1 plan, 1 subscription, 1 simulated payment, 3 classes, 3 enrollments (including 1 pending request), attendance, 2 conversations, notifications, commission, and payout data.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Demo seed failed: {$e->getMessage()}\n");
    exit(1);
} finally {
    $connection->query("SELECT RELEASE_LOCK('gympro-demo-seed')");
}
