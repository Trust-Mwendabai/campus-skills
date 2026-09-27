<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $rid = (int) ($_POST['id'] ?? 0);
    $report = fetch_one('SELECT * FROM reports WHERE id = ?', [$rid]);
    $status = $_POST['status'] ?? '';
    if (!$report || !in_array($status, ['open', 'investigating', 'resolved', 'dismissed'], true)) {
        flash('error', 'Invalid report update.');
        redirect('reports.php');
    }
    $note = post_str('admin_note', 500);
    q('UPDATE reports SET status = ?, admin_note = ?, handled_by = ? WHERE id = ?', [$status, $note ?: null, $me['id'], $rid]);
    admin_log($me['id'], 'report_' . $status, 'report', $rid, $note ?: null);

    if (($_POST['suspend'] ?? '') === '1') {
        q("UPDATE users SET status = 'suspended' WHERE id = ? AND role = 'student'", [$report['reported_user_id']]);
        admin_log($me['id'], 'suspend_account', 'user', (int) $report['reported_user_id'], 'From report #' . $rid);
        flash('success', 'Report updated and the reported student was suspended.');
    } else {
        flash('success', 'Report updated.');
    }
    redirect('reports.php?id=' . $rid);
}

$area = 'admin';
$active = 'reports';

// ---------- Detail ----------
if (!empty($_GET['id'])) {
    $r = fetch_one(
        'SELECT r.*, rp.name AS reporter, ru.name AS reported, ru.status AS reported_status, ru.email AS reported_email, h.name AS handler,
                (SELECT COUNT(*) FROM reports x WHERE x.reported_user_id = r.reported_user_id) AS total_against
           FROM reports r JOIN users rp ON rp.id = r.reporter_id JOIN users ru ON ru.id = r.reported_user_id LEFT JOIN users h ON h.id = r.handled_by
          WHERE r.id = ?', [(int) $_GET['id']]);
    if (!$r) {
        flash('error', 'Report not found.');
        redirect('reports.php');
    }
    $pageTitle = 'Report #' . $r['id'];
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-head"><div><a href="reports.php" class="muted small">← All reports</a><h1>Report #<?= (int) $r['id'] ?> <?= status_badge($r['status']) ?></h1><p><?= e(REPORT_CATEGORIES[$r['category']]) ?> · filed <?= e(time_ago($r['created_at'])) ?></p></div></div>
    <div class="grid grid-main">
      <section class="panel">
        <div class="panel-head"><h2>Details</h2></div>
        <div class="panel-body">
          <dl class="kv">
            <dt>Reported student</dt><dd><a href="users.php?id=<?= (int) $r['reported_user_id'] ?>" style="color:var(--primary);font-weight:600"><?= e($r['reported']) ?></a> <?= status_badge($r['reported_status']) ?> · <?= (int) $r['total_against'] ?> report<?= (int) $r['total_against'] === 1 ? '' : 's' ?> in total</dd>
            <dt>Reported by</dt><dd><a href="users.php?id=<?= (int) $r['reporter_id'] ?>" style="color:var(--primary)"><?= e($r['reporter']) ?></a></dd>
            <dt>Description</dt><dd style="white-space:pre-wrap"><?= e($r['description']) ?></dd>
            <?php if ($r['handler']): ?><dt>Last handled by</dt><dd><?= e($r['handler']) ?> · <?= e(time_ago($r['updated_at'])) ?></dd><?php endif; ?>
          </dl>
        </div>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Take action</h2></div>
        <form method="post" class="panel-body form">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <div class="field"><label for="status">Status</label><select id="status" name="status">
            <?php foreach (['open' => 'Open', 'investigating' => 'Investigating', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $k => $v): ?><option value="<?= $k ?>"<?= $r['status'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="note">Administrative note</label><textarea id="note" name="admin_note" maxlength="500" placeholder="Record what you found and what action was taken"><?= e($r['admin_note']) ?></textarea></div>
          <?php if ($r['reported_status'] !== 'suspended'): ?><label class="check"><input type="checkbox" name="suspend" value="1"> Also suspend the reported student</label><?php endif; ?>
          <div><button class="btn btn-primary">Save update</button></div>
        </form>
      </section>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ---------- List ----------
$status = in_array($_GET['status'] ?? '', ['open', 'investigating', 'resolved', 'dismissed'], true) ? $_GET['status'] : '';
$counts = array_column(fetch_all('SELECT status, COUNT(*) AS n FROM reports GROUP BY status'), 'n', 'status');
$where = $status ? 'WHERE r.status = ?' : '';
$params = $status ? [$status] : [];
$total = (int) fetch_value("SELECT COUNT(*) FROM reports r $where", $params);
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all(
    "SELECT r.*, rp.name AS reporter, ru.name AS reported FROM reports r JOIN users rp ON rp.id = r.reporter_id JOIN users ru ON ru.id = r.reported_user_id
      $where ORDER BY (r.status IN ('open','investigating')) DESC, r.id DESC LIMIT 20 OFFSET $offset", $params);

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Reports</h1><p>Investigate reports from students and record the outcome.</p></div></div>
<div class="tabs">
  <a class="tab<?= $status === '' ? ' active' : '' ?>" href="reports.php">All<span class="count"><?= array_sum($counts) ?></span></a>
  <?php foreach (['open', 'investigating', 'resolved', 'dismissed'] as $s): ?>
    <a class="tab<?= $status === $s ? ' active' : '' ?>" href="?status=<?= $s ?>"><?= ucfirst($s) ?><span class="count"><?= (int) ($counts[$s] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>
<section class="panel">
  <?= render_table([
      ['#', fn($r) => '<a href="reports.php?id=' . (int) $r['id'] . '"><b>' . (int) $r['id'] . '</b></a>'],
      ['Reported student', fn($r) => '<b>' . e($r['reported']) . '</b>'],
      ['Category', fn($r) => e(REPORT_CATEGORIES[$r['category']])],
      ['Reported by', fn($r) => e($r['reporter'])],
      ['Status', fn($r) => status_badge($r['status'])],
      ['Filed', fn($r) => '<span class="muted nowrap">' . e(time_ago($r['created_at'])) . '</span>'],
      ['', fn($r) => '<a class="btn btn-sm btn-soft" href="reports.php?id=' . (int) $r['id'] . '">Review</a>'],
  ], $rows, 'No reports found.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
