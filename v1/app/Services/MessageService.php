<?php
declare(strict_types=1);

namespace GymPro\Services;

use RuntimeException;

final class MessageService
{
    public static function eligibleMemberClasses(int $memberId): array
    {
        return \db_all(
            "SELECT c.ClassID,c.ClassName,c.DateOfClass,t.Name TrainerName,e.Status EnrollmentStatus
             FROM ENROLLMENT e
             JOIN CLASS c ON c.ClassID=e.ClassID
             JOIN TRAINER t ON t.TrainerID=c.TrainerID AND t.ArchivedAt IS NULL
             JOIN USER_ACCOUNT tu ON tu.TrainerID=t.TrainerID AND tu.Role='TRAINER' AND tu.Status='ACTIVE' AND tu.AnonymizedAt IS NULL
             WHERE e.MemberID=? AND e.Status IN ('PENDING','ENROLLED')
             ORDER BY c.DateOfClass DESC,c.ClassName",
            'i',
            [$memberId]
        );
    }

    public static function openConversation(array $user, string $channel, ?int $classId, string $subject, string $body): int
    {
        self::assertActiveRole($user, ['MEMBER']);
        $subject = self::validateText($subject, 'Subject', 150);
        $body = self::validateText($body, 'Message', 5000);
        if (!in_array($channel, ['ADMIN_SUPPORT', 'CLASS_TRAINER'], true)) {
            throw new RuntimeException('Choose a valid message destination.');
        }

        $result = \transaction(function () use ($user, $channel, $classId, $subject, $body): array {
            $memberId = (int)$user['MemberID'];
            $trainerId = null;
            $recipientIds = [];

            if ($channel === 'ADMIN_SUPPORT') {
                $classId = null;
                $recipientIds = array_map('intval', array_column(\db_all("SELECT UserID FROM USER_ACCOUNT WHERE Role='SUPER_ADMIN' AND Status='ACTIVE' AND AnonymizedAt IS NULL"), 'UserID'));
                if (!$recipientIds) {
                    throw new RuntimeException('GymPro support is unavailable right now.');
                }
                $contextKey = 'SUPPORT:MEMBER:' . $memberId;
            } else {
                if (!$classId) {
                    throw new RuntimeException('Choose a class trainer.');
                }
                $context = \db_one(
                    "SELECT c.ClassID,c.TrainerID,tu.UserID TrainerUserID
                     FROM ENROLLMENT e
                     JOIN CLASS c ON c.ClassID=e.ClassID
                     JOIN TRAINER t ON t.TrainerID=c.TrainerID AND t.ArchivedAt IS NULL
                     JOIN USER_ACCOUNT tu ON tu.TrainerID=t.TrainerID AND tu.Role='TRAINER' AND tu.Status='ACTIVE' AND tu.AnonymizedAt IS NULL
                     WHERE e.MemberID=? AND e.ClassID=? AND e.Status IN ('PENDING','ENROLLED') FOR UPDATE",
                    'ii',
                    [$memberId, $classId]
                );
                if (!$context) {
                    throw new RuntimeException('This trainer conversation is unavailable.');
                }
                $trainerId = (int)$context['TrainerID'];
                $recipientIds = [(int)$context['TrainerUserID']];
                $contextKey = 'CLASS:' . $classId . ':MEMBER:' . $memberId . ':TRAINER:' . $trainerId;
            }

            $conversation = \db_one('SELECT ConversationID FROM CONVERSATION WHERE ContextKey=? FOR UPDATE', 's', [$contextKey]);
            if ($conversation) {
                $conversationId = (int)$conversation['ConversationID'];
                \db_execute("UPDATE CONVERSATION SET Subject=?,Status='OPEN' WHERE ConversationID=?", 'si', [$subject, $conversationId]);
            } else {
                \db_execute(
                    "INSERT INTO CONVERSATION (Channel,MemberID,ClassID,TrainerID,Subject,Status,ContextKey,LastMessageAt) VALUES (?,?,?,?,?,'OPEN',?,NOW())",
                    'siiiss',
                    [$channel, $memberId, $classId, $trainerId, $subject, $contextKey]
                );
                $conversationId = \db()->insert_id;
            }

            \db_execute('INSERT IGNORE INTO CONVERSATION_PARTICIPANT (ConversationID,UserID) VALUES (?,?)', 'ii', [$conversationId, $user['UserID']]);
            foreach ($recipientIds as $recipientId) {
                \db_execute('INSERT IGNORE INTO CONVERSATION_PARTICIPANT (ConversationID,UserID) VALUES (?,?)', 'ii', [$conversationId, $recipientId]);
            }

            $messageId = self::insertMessage($conversationId, (int)$user['UserID'], $body);
            self::markReadInternal($conversationId, (int)$user['UserID'], $messageId);
            return compact('conversationId', 'messageId', 'recipientIds', 'channel');
        });

        self::alertRecipients($result['recipientIds'], $result['channel'], $result['conversationId']);
        \audit('conversation.opened', 'CONVERSATION', $result['conversationId'], ['channel' => $channel, 'class_id' => $classId]);
        return $result['conversationId'];
    }

    public static function listInbox(array $user): array
    {
        self::assertActiveRole($user, ['MEMBER', 'TRAINER', 'SUPER_ADMIN']);
        [$where, $types, $params] = self::scope($user, 'c');
        return \db_all(
            "SELECT c.*,m.Name MemberName,t.Name TrainerName,cl.ClassName,
                    (SELECT cm.Body FROM CONVERSATION_MESSAGE cm WHERE cm.ConversationID=c.ConversationID ORDER BY cm.MessageID DESC LIMIT 1) LastMessage,
                    (SELECT COUNT(*) FROM CONVERSATION_MESSAGE um WHERE um.ConversationID=c.ConversationID AND um.MessageID>COALESCE(cp.LastReadMessageID,0) AND um.SenderUserID<>?) UnreadCount
             FROM CONVERSATION c
             JOIN MEMBER m ON m.MemberID=c.MemberID
             LEFT JOIN TRAINER t ON t.TrainerID=c.TrainerID
             LEFT JOIN CLASS cl ON cl.ClassID=c.ClassID
             LEFT JOIN CONVERSATION_PARTICIPANT cp ON cp.ConversationID=c.ConversationID AND cp.UserID=?
             WHERE {$where}
             ORDER BY c.LastMessageAt DESC,c.ConversationID DESC",
            'ii' . $types,
            array_merge([(int)$user['UserID'], (int)$user['UserID']], $params)
        );
    }

    public static function getConversation(int $conversationId, array $user): array
    {
        self::assertActiveRole($user, ['MEMBER', 'TRAINER', 'SUPER_ADMIN']);
        [$where, $types, $params] = self::scope($user, 'c');
        $conversation = \db_one(
            "SELECT c.*,m.Name MemberName,t.Name TrainerName,cl.ClassName,cl.DateOfClass
             FROM CONVERSATION c
             JOIN MEMBER m ON m.MemberID=c.MemberID
             LEFT JOIN TRAINER t ON t.TrainerID=c.TrainerID
             LEFT JOIN CLASS cl ON cl.ClassID=c.ClassID
             WHERE c.ConversationID=? AND {$where}",
            'i' . $types,
            array_merge([$conversationId], $params)
        );
        if (!$conversation) {
            throw new RuntimeException('Conversation unavailable.');
        }
        $conversation['Messages'] = \db_all(
            'SELECT cm.*,u.Email SenderEmail,u.Role SenderRole FROM CONVERSATION_MESSAGE cm JOIN USER_ACCOUNT u ON u.UserID=cm.SenderUserID WHERE cm.ConversationID=? ORDER BY cm.MessageID',
            'i',
            [$conversationId]
        );
        return $conversation;
    }

    public static function sendMessage(int $conversationId, array $user, string $body): int
    {
        self::assertActiveRole($user, ['MEMBER', 'TRAINER', 'SUPER_ADMIN']);
        $body = self::validateText($body, 'Message', 5000);
        $result = \transaction(function () use ($conversationId, $user, $body): array {
            $conversation = self::authorizedConversationForUpdate($conversationId, $user);
            if ($conversation['Status'] !== 'OPEN') {
                throw new RuntimeException('This conversation is closed.');
            }
            $recipientIds = self::recipients($conversation, $user);
            if (!$recipientIds) {
                throw new RuntimeException('The message recipient is unavailable.');
            }
            $messageId = self::insertMessage($conversationId, (int)$user['UserID'], $body);
            \db_execute('INSERT IGNORE INTO CONVERSATION_PARTICIPANT (ConversationID,UserID) VALUES (?,?)', 'ii', [$conversationId, $user['UserID']]);
            self::markReadInternal($conversationId, (int)$user['UserID'], $messageId);
            return ['messageId' => $messageId, 'recipientIds' => $recipientIds, 'channel' => $conversation['Channel']];
        });
        self::alertRecipients($result['recipientIds'], $result['channel'], $conversationId);
        \audit('conversation.message_sent', 'CONVERSATION', $conversationId, ['message_id' => $result['messageId']]);
        return $result['messageId'];
    }

    public static function markRead(int $conversationId, array $user): void
    {
        self::assertActiveRole($user, ['MEMBER', 'TRAINER', 'SUPER_ADMIN']);
        $conversation = self::authorizedConversation($conversationId, $user);
        $latest = (int)(\db_one('SELECT COALESCE(MAX(MessageID),0) Latest FROM CONVERSATION_MESSAGE WHERE ConversationID=?', 'i', [$conversationId])['Latest'] ?? 0);
        \db_execute('INSERT IGNORE INTO CONVERSATION_PARTICIPANT (ConversationID,UserID) VALUES (?,?)', 'ii', [$conversationId, $user['UserID']]);
        self::markReadInternal($conversationId, (int)$user['UserID'], $latest ?: null);
    }

    public static function unreadCount(array $user): int
    {
        if (!in_array($user['Role'] ?? '', ['MEMBER', 'TRAINER', 'SUPER_ADMIN'], true) || ($user['Status'] ?? '') !== 'ACTIVE') {
            return 0;
        }
        [$where, $types, $params] = self::scope($user, 'c');
        return (int)(\db_one(
            "SELECT COUNT(*) n FROM CONVERSATION_MESSAGE cm JOIN CONVERSATION c ON c.ConversationID=cm.ConversationID LEFT JOIN CONVERSATION_PARTICIPANT cp ON cp.ConversationID=c.ConversationID AND cp.UserID=? WHERE {$where} AND cm.MessageID>COALESCE(cp.LastReadMessageID,0) AND cm.SenderUserID<>?",
            'i' . $types . 'i',
            array_merge([(int)$user['UserID']], $params, [(int)$user['UserID']])
        )['n'] ?? 0);
    }

    private static function authorizedConversation(int $conversationId, array $user): array
    {
        [$where, $types, $params] = self::scope($user, 'c');
        $conversation = \db_one("SELECT c.* FROM CONVERSATION c WHERE c.ConversationID=? AND {$where}", 'i' . $types, array_merge([$conversationId], $params));
        if (!$conversation) {
            throw new RuntimeException('Conversation unavailable.');
        }
        return $conversation;
    }

    private static function authorizedConversationForUpdate(int $conversationId, array $user): array
    {
        [$where, $types, $params] = self::scope($user, 'c');
        $conversation = \db_one("SELECT c.* FROM CONVERSATION c WHERE c.ConversationID=? AND {$where} FOR UPDATE", 'i' . $types, array_merge([$conversationId], $params));
        if (!$conversation) {
            throw new RuntimeException('Conversation unavailable.');
        }
        return $conversation;
    }

    private static function scope(array $user, string $alias): array
    {
        return match ($user['Role']) {
            'MEMBER' => ["{$alias}.MemberID=?", 'i', [(int)$user['MemberID']]],
            'TRAINER' => ["{$alias}.Channel='CLASS_TRAINER' AND {$alias}.TrainerID=? AND EXISTS (SELECT 1 FROM CLASS owned WHERE owned.ClassID={$alias}.ClassID AND owned.TrainerID=?)", 'ii', [(int)$user['TrainerID'], (int)$user['TrainerID']]],
            'SUPER_ADMIN' => ["{$alias}.Channel='ADMIN_SUPPORT'", '', []],
            default => ['1=0', '', []],
        };
    }

    private static function recipients(array $conversation, array $sender): array
    {
        if ($conversation['Channel'] === 'ADMIN_SUPPORT') {
            if ($sender['Role'] === 'MEMBER') {
                return array_map('intval', array_column(\db_all("SELECT UserID FROM USER_ACCOUNT WHERE Role='SUPER_ADMIN' AND Status='ACTIVE' AND AnonymizedAt IS NULL AND UserID<>?", 'i', [$sender['UserID']]), 'UserID'));
            }
            $member = \db_one("SELECT UserID FROM USER_ACCOUNT WHERE MemberID=? AND Role='MEMBER' AND Status='ACTIVE' AND AnonymizedAt IS NULL", 'i', [$conversation['MemberID']]);
            return $member ? [(int)$member['UserID']] : [];
        }
        if ($sender['Role'] === 'MEMBER') {
            $trainer = \db_one("SELECT UserID FROM USER_ACCOUNT WHERE TrainerID=? AND Role='TRAINER' AND Status='ACTIVE' AND AnonymizedAt IS NULL", 'i', [$conversation['TrainerID']]);
            return $trainer ? [(int)$trainer['UserID']] : [];
        }
        $member = \db_one("SELECT UserID FROM USER_ACCOUNT WHERE MemberID=? AND Role='MEMBER' AND Status='ACTIVE' AND AnonymizedAt IS NULL", 'i', [$conversation['MemberID']]);
        return $member ? [(int)$member['UserID']] : [];
    }

    private static function alertRecipients(array $recipientIds, string $channel, int $conversationId): void
    {
        if (!$recipientIds) {
            throw new RuntimeException('The message recipient is unavailable.');
        }
        \notify_users(
            $recipientIds,
            $channel === 'ADMIN_SUPPORT' ? 'New support message' : 'New class message',
            'Open Messages to read and reply.',
            'MESSAGE',
            'CONVERSATION',
            $conversationId
        );
    }

    private static function insertMessage(int $conversationId, int $senderUserId, string $body): int
    {
        \db_execute('INSERT INTO CONVERSATION_MESSAGE (ConversationID,SenderUserID,Body) VALUES (?,?,?)', 'iis', [$conversationId, $senderUserId, $body]);
        $messageId = \db()->insert_id;
        \db_execute('UPDATE CONVERSATION SET LastMessageAt=NOW() WHERE ConversationID=?', 'i', [$conversationId]);
        return $messageId;
    }

    private static function markReadInternal(int $conversationId, int $userId, ?int $messageId): void
    {
        \db_execute('UPDATE CONVERSATION_PARTICIPANT SET LastReadMessageID=?,ReadAt=NOW() WHERE ConversationID=? AND UserID=?', 'iii', [$messageId, $conversationId, $userId]);
    }

    private static function validateText(string $value, string $label, int $maximum): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new RuntimeException($label . ' is required.');
        }
        if (mb_strlen($value) > $maximum) {
            throw new RuntimeException($label . " must be {$maximum} characters or fewer.");
        }
        return $value;
    }

    private static function assertActiveRole(array $user, array $roles): void
    {
        if (($user['Status'] ?? '') !== 'ACTIVE' || ($user['AnonymizedAt'] ?? null) !== null || !in_array($user['Role'] ?? '', $roles, true)) {
            throw new RuntimeException('Messaging is unavailable for this account.');
        }
    }
}
