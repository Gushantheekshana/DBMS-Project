<?php
declare(strict_types=1);

namespace GymPro\V2\Services;

use RuntimeException;

final class MembershipService
{
    public static function getCurrent(int $actorUserAccountId): ?array
    {
        self::memberActor($actorUserAccountId);
        return \db_one(
            "SELECT ms.* FROM MEMBERSHIP ms JOIN MEMBER m ON m.MemberID=ms.MemberID WHERE m.UserAccountID=? AND ms.Status IN ('PENDING','ACTIVE','PAUSED') ORDER BY FIELD(ms.Status,'ACTIVE','PAUSED','PENDING'),ms.MembershipID DESC LIMIT 1",
            'i', [$actorUserAccountId]
        );
    }

    public static function choosePlan(int $actorUserAccountId, int $planId): int
    {
        if ($planId < 1) throw new RuntimeException('Choose a valid membership plan.');
        return \transaction(function () use ($actorUserAccountId, $planId): int {
            $member = self::memberActor($actorUserAccountId, true);
            $plan = \db_one('SELECT * FROM MEMBERSHIP_PLAN WHERE MembershipPlanID=? AND IsActive=1 FOR UPDATE', 'i', [$planId]);
            if (!$plan) throw new RuntimeException('This membership plan is not available.');
            \db_execute("UPDATE MEMBERSHIP SET Status='EXPIRED' WHERE MemberID=? AND Status IN ('ACTIVE','PAUSED') AND EndsOn<CURDATE()", 'i', [(int) $member['MemberID']]);
            $existing = \db_one("SELECT MembershipID FROM MEMBERSHIP WHERE MemberID=? AND (Status='PENDING' OR (Status IN ('ACTIVE','PAUSED') AND EndsOn>=CURDATE())) FOR UPDATE", 'i', [(int) $member['MemberID']]);
            if ($existing) throw new RuntimeException('You already have a current or pending membership.');
            $startsOn = date('Y-m-d');
            $endsOn = (new \DateTimeImmutable($startsOn))->modify('+' . (int) $plan['DurationMonths'] . ' months')->modify('-1 day')->format('Y-m-d');
            \db_execute(
                "INSERT INTO MEMBERSHIP (MemberID,MembershipPlanID,PlanNameSnapshot,PriceSnapshot,CurrencySnapshot,StartsOn,EndsOn,Status) VALUES (?,?,?,?,?,?,?,'PENDING')",
                'iisssss', [(int) $member['MemberID'], $planId, (string) $plan['Name'], (string) $plan['Price'], (string) $plan['Currency'], $startsOn, $endsOn]
            );
            return (int) \db()->insert_id;
        });
    }

    public static function pay(int $actorUserAccountId, int $membershipId, array $data): array
    {
        if ($membershipId < 1) throw new RuntimeException('Membership not found.');
        $method = strtoupper(trim((string) ($data['method'] ?? 'CARD')));
        if (!in_array($method, ['CASH', 'CARD', 'BANK_TRANSFER', 'OTHER'], true)) throw new RuntimeException('Choose a valid payment method.');
        $reference = trim((string) ($data['transaction_reference'] ?? ''));
        $reference = $reference === '' ? 'SIM-' . strtoupper(bin2hex(random_bytes(8))) : $reference;
        if (strlen($reference) > 100) throw new RuntimeException('Transaction reference is too long.');
        $key = trim((string) ($data['idempotency_key'] ?? $reference));
        if ($key === '' || strlen($key) > 100) throw new RuntimeException('Invalid payment idempotency key.');
        $notes = trim((string) ($data['notes'] ?? ''));
        if (strlen($notes) > 500) throw new RuntimeException('Payment notes are too long.');

        return \transaction(function () use ($actorUserAccountId, $membershipId, $method, $reference, $key, $notes): array {
            self::memberActor($actorUserAccountId, true);
            $prior = \db_one('SELECT * FROM MEMBERSHIP_PAYMENT WHERE IdempotencyKey=? FOR UPDATE', 's', [$key]);
            if ($prior) {
                if ((int) $prior['MembershipID'] !== $membershipId) throw new RuntimeException('Payment key was already used for another membership.');
                return $prior;
            }
            $membership = \db_one(
                'SELECT ms.*,mp.DurationMonths FROM MEMBERSHIP ms JOIN MEMBER m ON m.MemberID=ms.MemberID JOIN MEMBERSHIP_PLAN mp ON mp.MembershipPlanID=ms.MembershipPlanID WHERE ms.MembershipID=? AND m.UserAccountID=? FOR UPDATE',
                'ii', [$membershipId, $actorUserAccountId]
            );
            if (!$membership) throw new RuntimeException('Membership not found.');
            $paid = \db_one("SELECT * FROM MEMBERSHIP_PAYMENT WHERE MembershipID=? AND Status='PAID' LIMIT 1 FOR UPDATE", 'i', [$membershipId]);
            if ($paid) return $paid;
            if ($membership['Status'] !== 'PENDING') throw new RuntimeException('This membership cannot be paid.');
            if ((float) $membership['PriceSnapshot'] <= 0) throw new RuntimeException('Invalid membership amount.');
            \db_execute(
                "INSERT INTO MEMBERSHIP_PAYMENT (MembershipID,Amount,Currency,Status,Method,TransactionReference,IdempotencyKey,PaidAt,RecordedByUserAccountID,Notes) VALUES (?,?,?,'PAID',?,?,?,NOW(),?,?)",
                'idssssis', [$membershipId, (float) $membership['PriceSnapshot'], (string) $membership['CurrencySnapshot'], $method, $reference, $key, $actorUserAccountId, $notes === '' ? null : $notes]
            );
            $paymentId = (int) \db()->insert_id;
            $startsOn = date('Y-m-d');
            $endsOn = (new \DateTimeImmutable($startsOn))
                ->modify('+' . (int) $membership['DurationMonths'] . ' months')
                ->modify('-1 day')
                ->format('Y-m-d');
            \db_execute("UPDATE MEMBERSHIP SET StartsOn=?,EndsOn=?,Status='ACTIVE' WHERE MembershipID=? AND Status='PENDING'", 'ssi', [$startsOn, $endsOn, $membershipId]);
            return \db_one('SELECT * FROM MEMBERSHIP_PAYMENT WHERE MembershipPaymentID=?', 'i', [$paymentId]) ?? throw new RuntimeException('Unable to read payment receipt.');
        });
    }

    private static function memberActor(int $actorUserAccountId, bool $lock = false): array
    {
        if ($actorUserAccountId < 1) throw new RuntimeException('Authentication is required.');
        $sql = "SELECT m.*,u.Role,u.Status AccountStatus FROM USER_ACCOUNT u JOIN MEMBER m ON m.UserAccountID=u.UserAccountID WHERE u.UserAccountID=? AND u.Role='MEMBER'" . ($lock ? ' FOR UPDATE' : '');
        $member = \db_one($sql, 'i', [$actorUserAccountId]);
        if (!$member || $member['AccountStatus'] !== 'ACTIVE' || $member['Status'] !== 'ACTIVE') throw new RuntimeException('An active member account is required.');
        return $member;
    }
}
