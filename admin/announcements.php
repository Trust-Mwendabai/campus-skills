<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

$title = '';
$body = '';
if (is_post()) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'delete') {
        q('DELETE FROM announcements WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
        admin_log($me['id'], 'delete_announcement', 'announcement', (int) ($_POST['id'] ?? 0));
        flash('success', 'Announcement removed from the list. Notifications already sent stay in students\' inboxes.');
        redirect('announcements.php');
    }
    $title = post_str('title', 150);
    $body = post_str('body', 2000);
    if (($_POST['do'] ?? '') === 'update') {
        $aid = (int) ($_POST['id'] ?? 0);
        if (mb_strlen($title) < 3 || mb_strlen($body) < 5) {
            flash('error', 'Add a title (3+ characters) and a message (5+ characters).');
        } elseif (!fetch_value('SELECT 1 FROM announcements WHERE id = ?', [$aid])) {
            flash('error', 'Announcement not found.');
        } else {
            q('UPDATE announcements SET title = ?, body = ? WHERE id = ?', [$title, $body, $aid]);
            admin_log($me['id'], 'edit_announcement', 'announcement', $aid, $title);
            flash('success', 'Announcement updated. Notifications already sent are not changed.');
        }
        redirect('announcements.php');
    }
    if (mb_strlen($title) < 3 || mb_strlen($body) < 5) {
        flash('error', 'Add a title (3+ characters) and a message (5+ characters).');
    } else {
        q('INSERT INTO announcements (admin_id, title, body) VALUES (?,?,?)', [$me['id'], $title, $body]);
        $aid = insert_id();
        // One notification per active student.
        $sent = q("INSERT INTO notifications (user_id, type, title, body, link)
                   SELECT id, 'announcement', ?, ?, ? FROM users WHERE status = 'active' AND role = 'student'",
                  [$title, mb_substr($body, 0, 300), url('notifications.php')])->rowCount();
        admin_log($me['id'], 'publish_announcement', 'announcement', $aid, $title . ' → ' . $sent . ' students');
        flash('success', 'Announcement published to ' . $sent . ' student' . ($sent === 1 ? '' : 's') . '.');
        redirect('announcements.php');
    }
}

$rows = fetch_all('SELECT a.*, u.name AS admin_name FROM announcements a LEFT JOIN users u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT 30');

$area = 'admin';
$active = 'announcements';
$pageTitle = 'Announcements';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Announcements</h1><p>Publish a campus-wide message. Every active student receives it as a notification.</p></div></div>

<div class="grid grid-main">
  <section class="panel">
    <div class="panel-head"><h2>New announcement</h2></div>
    <form method="post" class="panel-body form">
      <?= csrf_field() ?>
      <div class="field"><label for="title">Title</label><input id="title" name="title" maxlength="150" value="<?= e($title) ?>" placeholder="Cybersecurity Skills Week" required></div>
      <div class="field"><label for="body">Message</label><textarea id="body" name="body" maxlength="2000" style="min-height:140px" placeholder="Students interested in cybersecurity can now join the new skill exchange category." required><?= e($body) ?></textarea></div>
      <div><button class="btn btn-primary"><?= icon('megaphone', 16) ?>Publish</button></div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Published</h2></div>
    <?php if ($rows): ?><ul class="list">
      <?php foreach ($rows as $a): ?>
        <li class="wrap" style="align-items:flex-start"><div class="grow"><div class="title"><?= e($a['title']) ?></div><div class="sub"><?= e(mb_substr($a['body'], 0, 120)) ?><?= mb_strlen($a['body']) > 120 ? '…' : '' ?></div><div class="sub"><?= e($a['admin_name'] ?? 'Admin') ?> · <?= e(time_ago($a['created_at'])) ?></div></div>
          <div class="row-actions"><button class="btn btn-sm btn-outline" type="button" data-toggle="#edit-<?= (int) $a['id'] ?>"><?= icon('edit', 14) ?>Edit</button>
          <?= action_form('delete', (int) $a['id'], 'Delete', 'btn-danger', 'Delete this announcement?') ?></div>
          <form method="post" class="form hidden" id="edit-<?= (int) $a['id'] ?>" style="margin-top:12px">
            <?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <div class="field"><label for="at<?= (int) $a['id'] ?>">Title</label><input id="at<?= (int) $a['id'] ?>" name="title" maxlength="150" value="<?= e($a['title']) ?>" required></div>
            <div class="field"><label for="ab<?= (int) $a['id'] ?>">Message</label><textarea id="ab<?= (int) $a['id'] ?>" name="body" maxlength="2000" required><?= e($a['body']) ?></textarea></div>
            <div><button class="btn btn-primary btn-sm">Save changes</button></div>
          </form></li>
      <?php endforeach; ?></ul>
    <?php else: ?><div class="empty">No announcements yet.</div><?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
