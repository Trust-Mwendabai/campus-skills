<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
$id = $me['id'];

$with = (int) ($_GET['with'] ?? 0);

// No-JS fallback for sending
if (is_post()) {
    csrf_check();
    $to = (int) ($_POST['to'] ?? 0);
    $r = send_message($id, $to, (string) ($_POST['body'] ?? ''));
    if (!$r['ok']) {
        flash('error', $r['message']);
    }
    redirect('messages.php?with=' . $to);
}

$partner = null;
$thread = [];
if ($with) {
    $partner = fetch_one("SELECT id, name, programme FROM users WHERE id = ? AND status = 'active' AND role = 'student'", [$with]);
    if ($partner && $partner['id'] !== $id) {
        $thread = fetch_all(
            "SELECT * FROM (SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY id DESC LIMIT 200) t ORDER BY id",
            [$id, $with, $with, $id]);
        q('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0', [$with, $id]);
    } else {
        $partner = null;
    }
}

$chats = conversations($id);
// Offer matched partners with no messages yet so a chat can be started.
$known = array_column($chats, 'partner_id');
$starters = array_filter(user_matches($id, 'active'), fn($m) => !in_array($m['partner_id'], $known));
$canSend = $partner && can_message($id, $partner['id']);
$lastId = $thread ? (int) end($thread)['id'] : 0;

$pageTitle = 'Messages';
$active = 'messages';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Messages</h1><p>Chat with your skill exchange partners.</p></div></div>

<section class="panel chat<?= $partner ? ' has-thread' : '' ?>">
  <div class="chat-list">
    <?php foreach ($chats as $c): ?>
      <a href="?with=<?= (int) $c['partner_id'] ?>" class="<?= $with === (int) $c['partner_id'] ? 'active' : '' ?>">
        <?= avatar(['id' => $c['partner_id'], 'name' => $c['partner_name']]) ?>
        <div class="grow"><b><?= e($c['partner_name']) ?></b><div class="preview"><?= (int) $c['sender_id'] === $id ? 'You: ' : '' ?><?= e(mb_substr($c['body'], 0, 40)) ?></div></div>
        <div style="text-align:right"><small class="muted"><?= e(time_ago($c['created_at'])) ?></small><?php if ($c['unread'] && $with !== (int) $c['partner_id']): ?><br><span class="count"><?= (int) $c['unread'] ?></span><?php endif; ?></div>
      </a>
    <?php endforeach; ?>
    <?php foreach ($starters as $m): ?>
      <a href="?with=<?= (int) $m['partner_id'] ?>" class="<?= $with === (int) $m['partner_id'] ? 'active' : '' ?>">
        <?= avatar(['id' => $m['partner_id'], 'name' => $m['partner_name']]) ?>
        <div class="grow"><b><?= e($m['partner_name']) ?></b><div class="preview">Say hello</div></div>
      </a>
    <?php endforeach; ?>
    <?php if (!$chats && !$starters): ?>
      <div class="empty"><?= icon('messages', 36) ?><strong>No conversations yet</strong>Once a request is accepted, you can chat with your partner here.</div>
    <?php endif; ?>
  </div>

  <div class="chat-pane">
    <?php if ($partner): ?>
      <div class="chat-head">
        <a class="icon-btn back-link" href="messages.php" aria-label="Back to conversations"><?= icon('arrow', 18) ?></a>
        <?= avatar($partner) ?>
        <div class="grow"><b><a href="profile.php?id=<?= (int) $partner['id'] ?>" style="text-decoration:none"><?= e($partner['name']) ?></a></b><div class="muted small"><?= e($partner['programme'] ?: 'Student') ?></div></div>
        <a class="btn btn-sm btn-soft" href="sessions.php?with=<?= (int) $partner['id'] ?>#schedule"><?= icon('calendar', 14) ?>Schedule</a>
      </div>
      <div class="chat-body" id="chatBody" data-partner="<?= (int) $partner['id'] ?>" data-last="<?= $lastId ?>">
        <?php if (!$thread): ?><div class="chat-empty">No messages yet — say hello!</div><?php endif; ?>
        <?php foreach ($thread as $m): $ts = strtotime($m['created_at']); ?>
          <div class="bubble <?= (int) $m['sender_id'] === $id ? 'mine' : 'theirs' ?>"><?= e($m['body']) ?><small><?= date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('M j, H:i', $ts) ?></small></div>
        <?php endforeach; ?>
      </div>
      <?php if ($canSend): ?>
      <form class="chat-form" id="chatForm" method="post">
        <?= csrf_field() ?><input type="hidden" name="to" value="<?= (int) $partner['id'] ?>">
        <textarea name="body" rows="1" maxlength="2000" placeholder="Type a message…" aria-label="Message" data-autosize required></textarea>
        <button class="btn btn-primary" type="submit"><?= icon('send', 16) ?>Send</button>
      </form>
      <?php else: ?>
        <div class="chat-form muted small">You can message this student once your exchange request has been accepted.</div>
      <?php endif; ?>
    <?php else: ?>
      <div class="chat-empty" style="margin:auto"><?= icon('messages', 48) ?><p>Select a conversation to start chatting.</p></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
