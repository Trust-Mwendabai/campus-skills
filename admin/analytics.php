<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

$n = fn(string $sql) => (int) fetch_value($sql);

// Last 12 months, oldest first, with zero-filled gaps.
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i month"))] = date('M y', strtotime("first day of -$i month"));
}
$series = function (string $sql) use ($months): array {
    $found = array_column(fetch_all($sql), 'n', 'm');
    return array_map(fn($k) => (int) ($found[$k] ?? 0), array_keys($months));
};
$since = date('Y-m-01', strtotime('first day of -11 month'));

$registrations = $series("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM users WHERE role = 'student' AND created_at >= '$since' GROUP BY m");
$completed     = $series("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM matches WHERE status = 'completed' AND created_at >= '$since' GROUP BY m");
$reqPerMonth   = $series("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM requests WHERE created_at >= '$since' GROUP BY m");
$msgPerMonth   = $series("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM messages WHERE created_at >= '$since' GROUP BY m");
$sesPerMonth   = $series("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS n FROM sessions WHERE created_at >= '$since' GROUP BY m");

$popular = fetch_all("SELECT s.name, COUNT(*) AS n FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.type = 'offer' GROUP BY s.id ORDER BY n DESC, s.name LIMIT 8");
$requested = fetch_all("SELECT s.name, COUNT(*) AS n FROM requests r JOIN skills s ON s.id = r.want_skill_id GROUP BY s.id ORDER BY n DESC, s.name LIMIT 8");
$byCategory = fetch_all("SELECT c.name, COUNT(us.id) AS n FROM categories c LEFT JOIN skills s ON s.category_id = c.id LEFT JOIN user_skills us ON us.skill_id = s.id GROUP BY c.id HAVING n > 0 ORDER BY n DESC");

$avg = fetch_value('SELECT AVG(score) FROM ratings WHERE is_hidden = 0');
$totals = [
    ['Total students', $n("SELECT COUNT(*) FROM users WHERE role = 'student'"), 'users', ''],
    ['Active students', $n("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active' AND last_login > (NOW() - INTERVAL 30 DAY)"), 'check', 'green'],
    ['Total skills', $n('SELECT COUNT(*) FROM skills WHERE is_active = 1'), 'skills', ''],
    ['Total matches', $n('SELECT COUNT(*) FROM matches'), 'zap', ''],
    ['Completed exchanges', $n("SELECT COUNT(*) FROM matches WHERE status = 'completed'"), 'award', 'green'],
    ['Pending requests', $n("SELECT COUNT(*) FROM requests WHERE status = 'pending'"), 'requests', 'amber'],
    ['Average rating', $avg !== null ? number_format((float) $avg, 1) : '—', 'star', 'amber'],
    ['Open reports', $n("SELECT COUNT(*) FROM reports WHERE status IN ('open','investigating')"), 'flag', 'red'],
];

$chartData = [
    'labels'        => array_values($months),
    'registrations' => $registrations,
    'completed'     => $completed,
    'requests'      => $reqPerMonth,
    'messages'      => $msgPerMonth,
    'sessions'      => $sesPerMonth,
    'popular'       => ['labels' => array_column($popular, 'name'), 'data' => array_map('intval', array_column($popular, 'n'))],
    'requested'     => ['labels' => array_column($requested, 'name'), 'data' => array_map('intval', array_column($requested, 'n'))],
    'categories'    => ['labels' => array_column($byCategory, 'name'), 'data' => array_map('intval', array_column($byCategory, 'n'))],
];

$area = 'admin';
$active = 'analytics';
$pageTitle = 'Analytics';
$pageScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js', url('assets/js/analytics.js')];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Analytics</h1><p>How students are using <?= e(APP_NAME) ?>. "Active" means logged in within the last 30 days.</p></div></div>

<section class="grid grid-4 stats mb">
  <?php foreach ($totals as [$label, $value, $ico, $tone]) echo stat_card($label, $value, $ico, $tone); ?>
</section>

<div class="grid grid-2 mb">
  <section class="panel"><div class="panel-head"><h2>Students registered per month</h2></div><div class="chart-box"><canvas id="chRegistrations" aria-label="Students registered per month"></canvas></div></section>
  <section class="panel"><div class="panel-head"><h2>Completed exchanges</h2></div><div class="chart-box"><canvas id="chCompleted" aria-label="Completed exchanges per month"></canvas></div></section>
  <section class="panel"><div class="panel-head"><h2>Most popular skills (taught)</h2></div><div class="chart-box"><canvas id="chPopular" aria-label="Most popular skills"></canvas></div></section>
  <section class="panel"><div class="panel-head"><h2>Most requested skills</h2></div><div class="chart-box"><canvas id="chRequested" aria-label="Most requested skills"></canvas></div></section>
  <section class="panel"><div class="panel-head"><h2>Skill categories</h2></div><div class="chart-box"><canvas id="chCategories" aria-label="Skills by category"></canvas></div></section>
  <section class="panel"><div class="panel-head"><h2>Platform activity</h2></div><div class="chart-box"><canvas id="chActivity" aria-label="Requests, messages and sessions per month"></canvas></div></section>
</div>

<script type="application/json" id="chartData"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
