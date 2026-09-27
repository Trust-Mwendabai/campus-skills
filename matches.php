<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 10);
    if (in_array($do, ['complete', 'end'], true)) {
        $r = change_match((int) ($_POST['id'] ?? 0), $id, $do);
        flash($r['ok'] ? 'success' : 'error', $r['message']);
    }
    redirect('matches.php?status=' . urlencode(post_str('status', 12) ?: 'active'));
}

$status = in_array($_GET['status'] ?? '', ['active', 'completed', 'cancelled'], true) ? $_GET['status'] : 'active';
$counts = array_column(fetch_all('SELECT status, COUNT(*) AS n FROM matches WHERE user_a = ? OR user_b = ? GROUP BY status', [$id, $id]), 'n', 'status');
$matches = user_matches($id, $status);

$pageTitle = 'My Matches';
$active = 'matches';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>My Matches</h1><p>Your skill exchange partners and their progress.</p></div>
  <a class="btn btn-primary" href="find-skills.php?view=recommended"><?= icon('zap', 16) ?>Find more partners</a>
</div>

<div class="tabs">
  <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $label): ?>
    <a class="tab<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $label ?><span class="count"><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<?php if ($matches): ?>
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(340px,1fr))">
  <?php foreach ($matches as $m): ?>
    <article class="card match">
      <div class="match-top">
        <?= avatar(['id' => $m['partner_id'], 'name' => $m['partner_name']], 'lg') ?>
        <div class="grow">
          <a class="match-name" href="profile.php?id=<?= (int) $m['partner_id'] ?>"><?= e($m['partner_name']) ?></a>
          <div class="muted small"><?= e($m['partner_programme'] ?: 'Student') ?><?= $m['partner_year'] ? ' · Year ' . (int) $m['partner_year'] : '' ?></div>
          <?= status_badge($m['status']) ?>
        </div>
        <?= score_ring((int) $m['score']) ?>
      </div>
      <div class="match-skills">
        <div><small>You learn</small><span class="chip chip-learn"><?= e($m['i_learn'] ?? '—') ?></span></div>
        <?php if ($m['i_teach']): ?><div><small>You teach</small><span class="chip chip-teach"><?= e($m['i_teach']) ?></span></div><?php endif; ?>
      </div>
      <div class="muted small">Matched <?= e(date('M j, Y', strtotime($m['created_at']))) ?></div>
      <div class="match-foot">
        <a class="btn btn-sm btn-outline" href="profile.php?id=<?= (int) $m['partner_id'] ?>">View profile</a>
        <a class="btn btn-sm btn-soft" href="messages.php?with=<?= (int) $m['partner_id'] ?>">Message</a>
        <?php if ($m['status'] === 'active'): ?>
          <a class="btn btn-sm btn-soft" href="sessions.php?match=<?= (int) $m['id'] ?>#schedule">Schedule session</a>
        <?php elseif ($m['status'] === 'completed' && !$m['i_reviewed']): ?>
          <a class="btn btn-sm btn-primary" href="reviews.php">Leave review</a>
        <?php endif; ?>
      </div>
      <?php if ($m['status'] === 'active'): ?>
      <div class="match-foot" style="border-top:1px solid var(--border);padding-top:12px">
        <form method="post" class="inline-form" data-confirm="Mark this exchange as completed?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="do" value="complete"><input type="hidden" name="status" value="active"><button class="btn btn-sm btn-success"><?= icon('check', 14) ?>Mark completed</button></form>
        <form method="post" class="inline-form" data-confirm="End this match? Scheduled sessions will be cancelled."><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="do" value="end"><input type="hidden" name="status" value="active"><button class="btn btn-sm btn-danger">End match</button></form>
        <a class="btn btn-sm btn-outline push" href="reports.php?user=<?= (int) $m['partner_id'] ?>"><?= icon('flag', 14) ?>Report</a>
      </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php else: ?>
  <div class="panel empty"><?= icon('zap', 44) ?><strong>No <?= e($status) ?> matches</strong>
    <?= $status === 'active' ? 'Send a request to a recommended partner and your matches will appear here once accepted.' : 'Nothing here yet.' ?>
    <?php if ($status === 'active'): ?><div><a class="btn btn-primary btn-sm" href="find-skills.php?view=recommended">See recommendations</a></div><?php endif; ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
