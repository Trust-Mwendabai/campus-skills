<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'read_all') {
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$id]);
    } elseif (($_POST['do'] ?? '') === 'clear') {
        q('DELETE FROM notifications WHERE user_id = ? AND is_read = 1', [$id]);
        flash('success', 'Read notifications cleared.');
    } elseif (($_POST['do'] ?? '') === 'delete') {
        q('DELETE FROM notifications WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $id]);
        flash('success', 'Notification deleted.');
    }
    redirect('notifications.php');
}

$total = (int) fetch_value('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$id]);
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 20 OFFSET ' . $offset, [$id]);

$typeIcon = [
    'match' => 'zap', 'request' => 'requests', 'request_accepted' => 'check', 'request_rejected' => 'x', 'message' => 'messages',
    'session' => 'calendar', 'session_completed' => 'check', 'review' => 'star', 'announcement' => 'megaphone',
];

$pageTitle = 'Notifications';
$active = 'notifications';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Notifications</h1><p>Matches, requests, messages, sessions and announcements.</p></div>
  <div class="page-actions">
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="do" value="read_all"><button class="btn btn-outline btn-sm">Mark all as read</button></form>
    <form method="post" class="inline-form" data-confirm="Delete all read notifications?"><?= csrf_field() ?><input type="hidden" name="do" value="clear"><button class="btn btn-outline btn-sm">Clear read</button></form>
  </div>
</div>

<?php if ($rows): ?>
<section class="panel"><ul class="list">
  <?php foreach ($rows as $n): ?>
    <li<?= $n['is_read'] ? '' : ' style="background:color-mix(in srgb, var(--primary) 6%, transparent)"' ?>>
      <span class="stat-icon <?= $n['type'] === 'request_rejected' ? 'red' : ($n['type'] === 'announcement' ? 'amber' : '') ?>" style="width:38px;height:38px"><?= icon($typeIcon[$n['type']] ?? 'bell', 18) ?></span>
      <div class="grow">
        <?php if ($n['link']): ?><a class="title" href="<?= e(url($n['link'])) ?>"><?= e($n['title']) ?></a><?php else: ?><div class="title"><?= e($n['title']) ?></div><?php endif; ?>
        <div class="sub"><?= e($n['body'] ?? '') ?></div>
      </div>
      <small class="muted nowrap"><?= e(time_ago($n['created_at'])) ?></small>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="icon-btn" aria-label="Delete notification" title="Delete"><?= icon('trash', 16) ?></button></form>
    </li>
  <?php endforeach; ?>
</ul></section>
<?= pager($page, $pages) ?>
<?php else: ?>
  <div class="panel empty"><?= icon('bell', 44) ?><strong>You're all caught up</strong>New notifications will appear here.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
