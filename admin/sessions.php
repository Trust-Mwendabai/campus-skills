<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/exchange.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();
mark_missed_sessions();

$status = in_array($_GET['status'] ?? '', ['scheduled', 'completed', 'cancelled', 'missed'], true) ? $_GET['status'] : '';
$counts = array_column(fetch_all('SELECT status, COUNT(*) AS n FROM sessions GROUP BY status'), 'n', 'status');
$where = $status ? 'WHERE s.status = ?' : '';
$params = $status ? [$status] : [];
$total = (int) fetch_value("SELECT COUNT(*) FROM sessions s $where", $params);
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all(
    "SELECT s.*, a.id AS aid, a.name AS aname, b.id AS bid, b.name AS bname, sk.name AS skill
       FROM sessions s JOIN matches m ON m.id = s.match_id JOIN users a ON a.id = m.user_a JOIN users b ON b.id = m.user_b
       LEFT JOIN skills sk ON sk.id = s.skill_id
       $where ORDER BY s.session_date DESC, s.start_time DESC LIMIT 20 OFFSET $offset", $params);

$area = 'admin';
$active = 'sessions';
$pageTitle = 'Sessions';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Learning sessions</h1><p>Scheduled, completed, cancelled and missed sessions.</p></div></div>
<div class="tabs">
  <a class="tab<?= $status === '' ? ' active' : '' ?>" href="sessions.php">All<span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach (['scheduled', 'completed', 'cancelled', 'missed'] as $s): ?>
    <a class="tab<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?><span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>
<section class="panel">
  <?= render_table([
      ['Session', fn($r) => '<b>' . e($r['title']) . '</b>' . ($r['skill'] ? '<small class="muted" style="display:block">' . e($r['skill']) . '</small>' : '')],
      ['Participants', fn($r) => '<a href="users.php?id=' . (int) $r['aid'] . '">' . e($r['aname']) . '</a> &amp; <a href="users.php?id=' . (int) $r['bid'] . '">' . e($r['bname']) . '</a>'],
      ['When', fn($r) => '<span class="nowrap">' . e(date('M j, Y', strtotime($r['session_date']))) . '<small class="muted" style="display:block">' . e(substr($r['start_time'], 0, 5)) . '–' . e(substr($r['end_time'], 0, 5)) . '</small></span>'],
      ['Where', fn($r) => e($r['location'] ?: ($r['meeting_link'] ? 'Online' : '—'))],
      ['Status', fn($r) => status_badge($r['status'])],
  ], $rows, 'No sessions yet.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
