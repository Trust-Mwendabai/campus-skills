<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

$status = in_array($_GET['status'] ?? '', ['active', 'completed', 'cancelled'], true) ? $_GET['status'] : '';
$counts = array_column(fetch_all('SELECT status, COUNT(*) AS n FROM matches GROUP BY status'), 'n', 'status');
$where = $status ? 'WHERE m.status = ?' : '';
$params = $status ? [$status] : [];
$total = (int) fetch_value("SELECT COUNT(*) FROM matches m $where", $params);
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all(
    "SELECT m.*, a.id AS aid, a.name AS aname, b.id AS bid, b.name AS bname, sa.name AS skill_a, sb.name AS skill_b,
            (SELECT COUNT(*) FROM sessions WHERE match_id = m.id) AS sessions
       FROM matches m JOIN users a ON a.id = m.user_a JOIN users b ON b.id = m.user_b
       JOIN skills sa ON sa.id = m.skill_a_id LEFT JOIN skills sb ON sb.id = m.skill_b_id
       $where ORDER BY m.id DESC LIMIT 20 OFFSET $offset", $params);

$area = 'admin';
$active = 'matches';
$pageTitle = 'Matches';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Matches</h1><p>Every skill exchange partnership on the platform.</p></div></div>
<div class="tabs">
  <a class="tab<?= $status === '' ? ' active' : '' ?>" href="matches.php">All<span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach (['active', 'completed', 'cancelled'] as $s): ?>
    <a class="tab<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?><span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>
<section class="panel">
  <?= render_table([
      ['Student A', fn($r) => user_cell($r['aname'], 'learns ' . $r['skill_a'], (int) $r['aid'])],
      ['Student B', fn($r) => user_cell($r['bname'], $r['skill_b'] ? 'learns ' . $r['skill_b'] : 'teaches only', (int) $r['bid'])],
      ['Compatibility', fn($r) => '<b>' . (int) $r['score'] . '%</b>'],
      ['Sessions', fn($r) => (int) $r['sessions']],
      ['Status', fn($r) => status_badge($r['status'])],
      ['Matched', fn($r) => '<span class="muted nowrap">' . e(date('M j, Y', strtotime($r['created_at']))) . '</span>'],
  ], $rows, 'No matches yet.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
