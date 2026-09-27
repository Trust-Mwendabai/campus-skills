<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

$status = in_array($_GET['status'] ?? '', ['pending', 'accepted', 'rejected', 'cancelled', 'completed'], true) ? $_GET['status'] : '';
$counts = array_column(fetch_all('SELECT status, COUNT(*) AS n FROM requests GROUP BY status'), 'n', 'status');
$where = $status ? 'WHERE r.status = ?' : '';
$params = $status ? [$status] : [];
$total = (int) fetch_value("SELECT COUNT(*) FROM requests r $where", $params);
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all(
    "SELECT r.*, a.id AS aid, a.name AS sender, b.id AS bid, b.name AS receiver, sw.name AS want_skill, so.name AS offer_skill
       FROM requests r JOIN users a ON a.id = r.sender_id JOIN users b ON b.id = r.receiver_id
       JOIN skills sw ON sw.id = r.want_skill_id LEFT JOIN skills so ON so.id = r.offer_skill_id
       $where ORDER BY r.id DESC LIMIT 20 OFFSET $offset", $params);

$area = 'admin';
$active = 'requests';
$pageTitle = 'Requests';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Exchange requests</h1><p>Monitor pending, accepted, rejected and completed exchanges.</p></div></div>
<div class="tabs">
  <a class="tab<?= $status === '' ? ' active' : '' ?>" href="requests.php">All<span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach (['pending', 'accepted', 'rejected', 'cancelled', 'completed'] as $s): ?>
    <a class="tab<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?><span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>
<section class="panel">
  <?= render_table([
      ['From', fn($r) => user_cell($r['sender'], null, (int) $r['aid'])],
      ['To', fn($r) => user_cell($r['receiver'], null, (int) $r['bid'])],
      ['Wants to learn', fn($r) => '<span class="chip chip-learn">' . e($r['want_skill']) . '</span>'],
      ['Offers', fn($r) => $r['offer_skill'] ? '<span class="chip chip-teach">' . e($r['offer_skill']) . '</span>' : '<span class="muted">—</span>'],
      ['Format', fn($r) => e(METHODS[$r['method']])],
      ['Status', fn($r) => status_badge($r['status'])],
      ['Sent', fn($r) => '<span class="muted nowrap">' . e(time_ago($r['created_at'])) . '</span>'],
  ], $rows, 'No requests found.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
