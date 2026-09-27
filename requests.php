<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 10);
    if (in_array($do, ['accept', 'reject', 'cancel'], true)) {
        $r = respond_request((int) ($_POST['id'] ?? 0), $id, $do);
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    }
    $back = ($_POST['back'] ?? '') === 'dashboard.php' ? 'dashboard.php' : 'requests.php?tab=' . (($_POST['tab'] ?? '') === 'sent' ? 'sent' : 'received');
    redirect($back);
}

$tab = ($_GET['tab'] ?? 'received') === 'sent' ? 'sent' : 'received';
$counts = [
    'received' => (int) fetch_value("SELECT COUNT(*) FROM requests WHERE receiver_id = ? AND status = 'pending'", [$id]),
    'sent'     => (int) fetch_value("SELECT COUNT(*) FROM requests WHERE sender_id = ? AND status = 'pending'", [$id]),
];
$status = in_array($_GET['status'] ?? '', ['pending', 'accepted', 'rejected', 'cancelled', 'completed'], true) ? $_GET['status'] : '';

$other = $tab === 'received' ? 'sender_id' : 'receiver_id';
$mine  = $tab === 'received' ? 'receiver_id' : 'sender_id';
$params = [$id];
$sql = "SELECT r.*, u.id AS uid, u.name AS uname, u.programme AS uprog, sw.name AS want_skill, so.name AS offer_skill,
               (SELECT m.id FROM matches m WHERE m.request_id = r.id) AS match_id
          FROM requests r JOIN users u ON u.id = r.$other JOIN skills sw ON sw.id = r.want_skill_id LEFT JOIN skills so ON so.id = r.offer_skill_id
         WHERE r.$mine = ?";
if ($status) { $sql .= ' AND r.status = ?'; $params[] = $status; }
$total = (int) fetch_value("SELECT COUNT(*) FROM requests r WHERE r.$mine = ?" . ($status ? ' AND r.status = ?' : ''), $params);
[$page, $pages, $offset] = paginate($total, 10);
$rows = fetch_all($sql . ' ORDER BY (r.status = \'pending\') DESC, r.id DESC LIMIT 10 OFFSET ' . $offset, $params);

$pageTitle = 'Requests';
$active = 'requests';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Skill exchange requests</h1><p>Review requests from others and track the ones you've sent.</p></div>
  <a class="btn btn-primary" href="find-skills.php"><?= icon('search', 16) ?>Find a partner</a>
</div>

<div class="tabs">
  <a class="tab<?= $tab === 'received' ? ' active' : '' ?>" href="?tab=received">Received<?php if ($counts['received']): ?><span class="count"><?= $counts['received'] ?></span><?php endif; ?></a>
  <a class="tab<?= $tab === 'sent' ? ' active' : '' ?>" href="?tab=sent">Sent<?php if ($counts['sent']): ?><span class="count"><?= $counts['sent'] ?></span><?php endif; ?></a>
</div>

<form method="get" class="filter-bar" style="padding:0 0 16px">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div style="flex:0 0 200px"><label for="status">Status</label>
    <select id="status" name="status" onchange="this.form.submit()"><option value="">All</option>
      <?php foreach (['pending', 'accepted', 'rejected', 'cancelled', 'completed'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
</form>

<?php if ($rows): ?>
<section class="panel"><ul class="list">
  <?php foreach ($rows as $r): ?>
    <li style="align-items:flex-start;flex-wrap:wrap">
      <?= avatar(['id' => $r['uid'], 'name' => $r['uname']], 'lg') ?>
      <div class="grow">
        <div><a class="title" href="profile.php?id=<?= (int) $r['uid'] ?>"><?= e($r['uname']) ?></a> <?= status_badge($r['status']) ?></div>
        <div class="sub">
          <?= $tab === 'received' ? 'Wants to learn' : 'You want to learn' ?> <b><?= e($r['want_skill']) ?></b>
          <?php if ($r['offer_skill']): ?> · <?= $tab === 'received' ? 'offers' : 'you offer' ?> <b><?= e($r['offer_skill']) ?></b><?php endif; ?>
        </div>
        <?php if ($r['message']): ?><p class="why" style="margin:8px 0 0"><?= nl2br(e($r['message'])) ?></p><?php endif; ?>
        <div class="sub" style="margin-top:6px">
          <?= e(METHODS[$r['method']]) ?>
          <?php if ($r['preferred_date']): ?> · Prefers <?= e(date('D, M j', strtotime($r['preferred_date']))) ?><?= $r['preferred_time'] ? ' at ' . e(substr($r['preferred_time'], 0, 5)) : '' ?><?php endif; ?>
          · <?= e(time_ago($r['created_at'])) ?>
        </div>
      </div>
      <div class="row-actions">
        <?php if ($r['status'] === 'pending'): ?>
          <?php if ($tab === 'received'): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="do" value="accept"><input type="hidden" name="tab" value="received"><button class="btn btn-sm btn-success"><?= icon('check', 14) ?>Accept</button></form>
            <form method="post" class="inline-form" data-confirm="Decline this request?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="do" value="reject"><input type="hidden" name="tab" value="received"><button class="btn btn-sm btn-danger"><?= icon('x', 14) ?>Decline</button></form>
          <?php else: ?>
            <form method="post" class="inline-form" data-confirm="Cancel this request?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="do" value="cancel"><input type="hidden" name="tab" value="sent"><button class="btn btn-sm btn-outline">Cancel request</button></form>
          <?php endif; ?>
        <?php elseif ($r['match_id']): ?>
          <a class="btn btn-sm btn-soft" href="messages.php?with=<?= (int) $r['uid'] ?>">Message</a>
          <a class="btn btn-sm btn-outline" href="matches.php">View match</a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul></section>
<?= pager($page, $pages) ?>
<?php else: ?>
  <div class="panel empty"><?= icon('requests', 44) ?><strong>No <?= $status ? e($status) . ' ' : '' ?><?= e($tab) ?> requests</strong>
    <?= $tab === 'received' ? 'When someone asks to learn from you, it will show up here.' : 'Find a student to learn from and send your first request.' ?>
    <?php if ($tab === 'sent'): ?><div><a class="btn btn-primary btn-sm" href="find-skills.php">Find skills</a></div><?php endif; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
