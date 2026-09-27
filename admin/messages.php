<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'delete') {
        $mid = (int) ($_POST['id'] ?? 0);
        q('DELETE FROM messages WHERE id = ?', [$mid]);
        admin_log($me['id'], 'delete_message', 'message', $mid);
        flash('success', 'Message removed.');
    }
    redirect('messages.php');
}

$total = (int) fetch_value('SELECT COUNT(*) FROM messages');
[$page, $pages, $offset] = paginate($total, 25);
// Message text is private. It is only shown when an unresolved report involves someone in the conversation.
$rows = fetch_all(
    "SELECT m.id, m.body, m.created_at, m.is_read, a.id AS aid, a.name AS sender, b.id AS bid, b.name AS receiver,
            EXISTS (SELECT 1 FROM reports r WHERE r.status IN ('open','investigating') AND r.reported_user_id IN (m.sender_id, m.receiver_id)) AS reported
       FROM messages m JOIN users a ON a.id = m.sender_id JOIN users b ON b.id = m.receiver_id
      ORDER BY m.id DESC LIMIT 25 OFFSET $offset");

$area = 'admin';
$active = 'messages';
$pageTitle = 'Messages';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Messages</h1><p><?= number_format($total) ?> messages sent. Conversations are private.</p></div></div>
<div class="alert alert-info"><?= icon('shield', 16) ?> To protect student privacy, message text is only visible for conversations involving a user with an open report.</div>
<section class="panel">
  <?= render_table([
      ['From', fn($r) => user_cell($r['sender'], null, (int) $r['aid'])],
      ['To', fn($r) => user_cell($r['receiver'], null, (int) $r['bid'])],
      ['Message', fn($r) => $r['reported']
          ? '<span style="white-space:pre-wrap">' . e(mb_substr($r['body'], 0, 300)) . '</span>'
          : '<span class="muted"><i>Hidden (' . mb_strlen($r['body']) . ' characters)</i></span>'],
      ['Sent', fn($r) => '<span class="muted nowrap">' . e(time_ago($r['created_at'])) . '</span>'],
      ['', fn($r) => $r['reported'] ? action_form('delete', (int) $r['id'], 'Remove', 'btn-danger', 'Remove this message?') : ''],
  ], $rows, 'No messages yet.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
