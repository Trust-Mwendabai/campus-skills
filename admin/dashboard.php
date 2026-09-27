<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

$n = fn(string $sql, array $p = []) => (int) fetch_value($sql, $p);
$stats = [
    'students'   => $n("SELECT COUNT(*) FROM users WHERE role = 'student'"),
    'active'     => $n("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'"),
    'pending'    => $n("SELECT COUNT(*) FROM users WHERE status = 'pending'"),
    'skills'     => $n('SELECT COUNT(*) FROM skills WHERE is_active = 1'),
    'matches'    => $n('SELECT COUNT(*) FROM matches'),
    'completed'  => $n("SELECT COUNT(*) FROM matches WHERE status = 'completed'"),
    'requests'   => $n("SELECT COUNT(*) FROM requests WHERE status = 'pending'"),
    'reports'    => $n("SELECT COUNT(*) FROM reports WHERE status IN ('open','investigating')"),
];
$avg = fetch_value('SELECT AVG(score) FROM ratings WHERE is_hidden = 0');

$newUsers = fetch_all("SELECT id, name, email, programme, status, created_at FROM users WHERE role = 'student' ORDER BY id DESC LIMIT 6");
$openReports = fetch_all(
    "SELECT r.id, r.category, r.created_at, ru.name AS reported, rp.name AS reporter
       FROM reports r JOIN users ru ON ru.id = r.reported_user_id JOIN users rp ON rp.id = r.reporter_id
      WHERE r.status IN ('open','investigating') ORDER BY r.id DESC LIMIT 5");
$actions = fetch_all('SELECT a.*, u.name AS admin_name FROM admin_actions a LEFT JOIN users u ON u.id = a.admin_id ORDER BY a.id DESC LIMIT 8');

$area = 'admin';
$active = 'dashboard';
$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div><h1><?= e(greeting()) ?>, <?= e(first_name($me['name'])) ?></h1><p>Here's what's happening on <?= e(APP_NAME) ?> today.</p></div>
  <a class="btn btn-primary" href="analytics.php"><?= icon('chart', 16) ?>View analytics</a>
</div>

<?php if ($stats['pending'] || $stats['reports']): ?>
  <div class="alert alert-warn">
    <?php if ($stats['pending']): ?><b><?= $stats['pending'] ?></b> account<?= $stats['pending'] === 1 ? '' : 's' ?> awaiting verification — <a href="users.php?status=pending" style="text-decoration:underline">review now</a>. <?php endif; ?>
    <?php if ($stats['reports']): ?><b><?= $stats['reports'] ?></b> open report<?= $stats['reports'] === 1 ? '' : 's' ?> — <a href="reports.php?status=open" style="text-decoration:underline">investigate</a>.<?php endif; ?>
  </div>
<?php endif; ?>

<section class="grid grid-4 stats mb">
  <?= stat_card('Total students', $stats['students'], 'users') ?>
  <?= stat_card('Active students', $stats['active'], 'check', 'green') ?>
  <?= stat_card('Skills listed', $stats['skills'], 'skills') ?>
  <?= stat_card('Average rating', $avg !== null ? number_format((float) $avg, 1) : '—', 'star', 'amber') ?>
  <?= stat_card('Total matches', $stats['matches'], 'zap') ?>
  <?= stat_card('Completed exchanges', $stats['completed'], 'award', 'green') ?>
  <?= stat_card('Pending requests', $stats['requests'], 'requests', 'amber') ?>
  <?= stat_card('Open reports', $stats['reports'], 'flag', 'red') ?>
</section>

<div class="grid grid-main">
  <section class="panel">
    <div class="panel-head"><h2>Newest students</h2><a href="users.php">All students</a></div>
    <?= render_table([
        ['Student', fn($r) => user_cell($r['name'], $r['email'], (int) $r['id'])],
        ['Programme', fn($r) => e($r['programme'] ?: '—')],
        ['Status', fn($r) => status_badge($r['status'])],
        ['Joined', fn($r) => '<span class="muted nowrap">' . e(time_ago($r['created_at'])) . '</span>'],
    ], $newUsers, 'No students have registered yet.') ?>
  </section>

  <div class="grid" style="gap:24px">
    <section class="panel">
      <div class="panel-head"><h2>Open reports</h2><a href="reports.php">All</a></div>
      <?php if ($openReports): ?><ul class="list">
        <?php foreach ($openReports as $r): ?>
          <li><div class="grow"><a class="title" href="reports.php?id=<?= (int) $r['id'] ?>"><?= e($r['reported']) ?></a><div class="sub"><?= e(REPORT_CATEGORIES[$r['category']]) ?> · by <?= e($r['reporter']) ?> · <?= e(time_ago($r['created_at'])) ?></div></div></li>
        <?php endforeach; ?></ul>
      <?php else: ?><div class="empty"><?= icon('check', 16) ?> No open reports</div><?php endif; ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Recent admin activity</h2></div>
      <?php if ($actions): ?><ul class="list">
        <?php foreach ($actions as $a): ?>
          <li><div class="grow"><div class="title"><?= e(str_replace('_', ' ', ucfirst($a['action']))) ?></div><div class="sub"><?= e($a['admin_name'] ?? 'System') ?><?= $a['details'] ? ' · ' . e($a['details']) : '' ?></div></div><small class="muted nowrap"><?= e(time_ago($a['created_at'])) ?></small></li>
        <?php endforeach; ?></ul>
      <?php else: ?><div class="empty">No actions recorded yet.</div><?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
