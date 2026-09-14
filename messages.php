<?php
require_once __DIR__ . '/bootstrap/app.php';

use GymPro\Services\MessageService;

require_role('MEMBER', 'TRAINER', 'SUPER_ADMIN');
require_active_account();
$user = current_user();
$errors = [];
$selectedId = (int)($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
$classHint = (int)($_GET['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'open') {
            $selectedId = MessageService::openConversation(
                $user,
                (string)($_POST['channel'] ?? ''),
                (int)($_POST['class_id'] ?? 0) ?: null,
                (string)($_POST['subject'] ?? ''),
                (string)($_POST['body'] ?? '')
            );
            set_flash('success', 'Message sent.');
        } elseif ($action === 'reply') {
            MessageService::sendMessage($selectedId, $user, (string)($_POST['body'] ?? ''));
            set_flash('success', 'Reply sent.');
        } elseif ($action === 'read') {
            MessageService::markRead($selectedId, $user);
        } else {
            throw new RuntimeException('Invalid messaging action.');
        }
        redirect('messages.php?conversation_id=' . $selectedId);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$inbox = MessageService::listInbox($user);
if (!$selectedId && $inbox) {
    $selectedId = (int)$inbox[0]['ConversationID'];
}
$conversation = null;
if ($selectedId) {
    try {
        $conversation = MessageService::getConversation($selectedId, $user);
        MessageService::markRead($selectedId, $user);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $selectedId = 0;
    }
}
$eligibleClasses = $user['Role'] === 'MEMBER' ? MessageService::eligibleMemberClasses((int)$user['MemberID']) : [];
$pageTitle = 'Messages';
$pageSubtitle = $user['Role'] === 'MEMBER' ? 'Contact GymPro Support or your class trainer' : ($user['Role'] === 'TRAINER' ? 'Class member conversations' : 'Shared member support inbox');
include ROOT_PATH . '/includes/header.php';
?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<?php if ($user['Role'] === 'MEMBER'): ?>
<details class="form-card message-compose" <?= !$inbox || $classHint ? 'open' : '' ?>>
 <summary class="section-title">Start a conversation</summary>
 <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="open">
  <div class="form-grid">
   <div class="form-group"><label for="channel">Destination</label><select id="channel" name="channel" required><option value="ADMIN_SUPPORT">GymPro Support (super admin)</option><option value="CLASS_TRAINER" <?= $classHint ? 'selected' : '' ?>>Class trainer</option></select></div>
   <div class="form-group"><label for="class_id">Class for trainer messages</label><select id="class_id" name="class_id"><option value="">Not applicable for support</option><?php foreach ($eligibleClasses as $class): ?><option value="<?= (int)$class['ClassID'] ?>" <?= $classHint === (int)$class['ClassID'] ? 'selected' : '' ?>><?= e($class['ClassName'] . ' — ' . $class['TrainerName'] . ' (' . $class['EnrollmentStatus'] . ')') ?></option><?php endforeach; ?></select></div>
   <div class="form-group form-full"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="150" required></div>
   <div class="form-group form-full"><label for="new-body">Message</label><textarea id="new-body" name="body" maxlength="5000" required></textarea></div>
  </div><div class="form-actions"><button class="btn btn-primary" type="submit">Send message</button></div>
 </form>
</details>
<?php endif; ?>

<div class="messages-layout">
 <aside class="conversation-list" aria-label="Conversations">
  <h2 class="section-title">Inbox</h2>
  <?php foreach ($inbox as $item): ?>
   <a class="conversation-card <?= $selectedId === (int)$item['ConversationID'] ? 'active' : '' ?> <?= (int)$item['UnreadCount'] ? 'unread' : '' ?>" href="<?= e(base_url('messages.php?conversation_id=' . (int)$item['ConversationID'])) ?>">
    <span class="conversation-title"><?= e($item['Subject']) ?></span>
    <small><?= e($item['Channel'] === 'ADMIN_SUPPORT' ? 'GymPro Support · ' . $item['MemberName'] : ($item['ClassName'] . ' · ' . ($user['Role'] === 'MEMBER' ? $item['TrainerName'] : $item['MemberName']))) ?></small>
    <span class="conversation-preview"><?= e(mb_strimwidth((string)$item['LastMessage'], 0, 85, '…')) ?></span>
    <small><?= e(date('d M, H:i', strtotime($item['LastMessageAt']))) ?><?php if ((int)$item['UnreadCount']): ?> · <strong><?= (int)$item['UnreadCount'] ?> unread</strong><?php endif; ?></small>
   </a>
  <?php endforeach; ?>
  <?php if (!$inbox): ?><div class="empty-state">No conversations yet.</div><?php endif; ?>
 </aside>

 <section class="message-thread" aria-label="Selected conversation">
  <?php if ($conversation): ?>
   <header class="message-thread-header"><div><span class="badge badge-blue"><?= e(str_replace('_', ' ', $conversation['Channel'])) ?></span><h2><?= e($conversation['Subject']) ?></h2><p><?= e($conversation['Channel'] === 'ADMIN_SUPPORT' ? 'Member: ' . $conversation['MemberName'] : $conversation['ClassName'] . ' · ' . $conversation['MemberName'] . ' and ' . $conversation['TrainerName']) ?></p></div></header>
   <div class="message-stream">
    <?php foreach ($conversation['Messages'] as $message): $own = (int)$message['SenderUserID'] === (int)$user['UserID']; ?>
     <article class="message-bubble <?= $own ? 'own' : '' ?>"><div class="message-sender"><?= $own ? 'You' : e(str_replace('_', ' ', $message['SenderRole'])) ?></div><p><?= nl2br(e($message['Body'])) ?></p><small><?= e(date('d M Y H:i', strtotime($message['CreatedAt']))) ?></small></article>
    <?php endforeach; ?>
   </div>
   <?php if ($conversation['Status'] === 'OPEN'): ?><form method="post" class="message-reply"><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="conversation_id" value="<?= (int)$conversation['ConversationID'] ?>"><label class="sr-only" for="reply-body">Reply</label><textarea id="reply-body" name="body" maxlength="5000" required placeholder="Write a reply..."></textarea><button class="btn btn-primary" type="submit">Reply</button></form><?php else: ?><div class="empty-state">This conversation is closed.</div><?php endif; ?>
  <?php else: ?><div class="empty-state">Select a conversation to read its messages.</div><?php endif; ?>
 </section>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
