<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $reported = (int) ($_POST['reported_user_id'] ?? 0);
    $category = $_POST['category'] ?? '';
    $desc = post_str('description', 1000);

    if (!fetch_value("SELECT 1 FROM users WHERE id = ? AND role = 'student'", [$reported]) || $reported === $id) {
        flash('error', 'Choose a valid student to report.');
    } elseif (!array_key_exists($category, REPORT_CATEGORIES)) {
        flash('error', 'Choose a report category.');
    } elseif (mb_strlen($desc) < 10) {
        flash('error', 'Please describe what happened (at least 10 characters).');
    } elseif ((int) fetch_value("SELECT COUNT(*) FROM reports WHERE reporter_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)", [$id]) >= 5) {
        flash('error', 'You have reached the daily limit for reports. Please try again tomorrow.');
    } else {
        q('INSERT INTO reports (reporter_id, reported_user_id, category, description) VALUES (?,?,?,?)', [$id, $reported, $category, $desc]);
        flash('success', 'Report submitted. An administrator will review it.');
        redirect('reports.php');
    }
    redirect('reports.php?user=' . $reported);
}

$target = null;
if (!empty($_GET['user'])) {
    $target = fetch_one("SELECT id, name, programme FROM users WHERE id = ? AND role = 'student' AND id <> ?", [(int) $_GET['user'], $id]);
}
$mine = fetch_all('SELECT r.*, u.name AS reported_name FROM reports r JOIN users u ON u.id = r.reported_user_id WHERE r.reporter_id = ? ORDER BY r.id DESC LIMIT 30', [$id]);

$pageTitle = 'Reports';
$active = 'settings';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Report a problem</h1><p>Help keep Campus SkillX safe. Reports are reviewed by administrators.</p></div></div>

<div class="grid grid-main">
  <section class="panel">
    <div class="panel-head"><h2>New report</h2></div>
    <form method="post" class="panel-body form">
      <?= csrf_field() ?>
      <?php if ($target): ?>
        <input type="hidden" name="reported_user_id" value="<?= (int) $target['id'] ?>">
        <div class="field"><span class="label">Reported user</span><div class="cell-user"><?= avatar($target) ?><div><b><?= e($target['name']) ?></b><small><?= e($target['programme'] ?? '') ?></small></div></div></div>
      <?php else: ?>
        <div class="alert alert-info" style="margin:0">To report someone, open their profile and choose <b>Report</b>.</div>
      <?php endif; ?>
      <div class="field"><label for="category">Category</label>
        <select id="category" name="category" required<?= $target ? '' : ' disabled' ?>><option value="">Choose…</option>
          <?php foreach (REPORT_CATEGORIES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="description">What happened?</label><textarea id="description" name="description" maxlength="1000" required<?= $target ? '' : ' disabled' ?>></textarea></div>
      <div><button class="btn btn-primary"<?= $target ? '' : ' disabled' ?>>Submit report</button></div>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>My reports</h2></div>
    <?php if ($mine): ?>
    <ul class="list">
      <?php foreach ($mine as $r): ?>
        <li><div class="grow"><div class="title"><?= e($r['reported_name']) ?></div><div class="sub"><?= e(REPORT_CATEGORIES[$r['category']]) ?> · <?= e(time_ago($r['created_at'])) ?></div></div><?= status_badge($r['status']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><div class="empty">You haven't submitted any reports.</div><?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
