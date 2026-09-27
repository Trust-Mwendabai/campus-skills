<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 10);
    if ($do === 'update') {
        $r = update_review($id, (int) ($_POST['id'] ?? 0), (int) ($_POST['score'] ?? 0), (string) ($_POST['comment'] ?? ''));
    } elseif ($do === 'delete') {
        $r = delete_review($id, (int) ($_POST['id'] ?? 0));
    } else {
        $r = submit_review($id, (int) ($_POST['match_id'] ?? 0), (int) ($_POST['score'] ?? 0), (string) ($_POST['comment'] ?? ''));
    }
    flash($r['ok'] ? 'success' : 'error', $r['message']);
    redirect('reviews.php' . ($do === 'update' || $do === 'delete' ? '?tab=given' : ''));
}

$tab = in_array($_GET['tab'] ?? '', ['received', 'given'], true) ? $_GET['tab'] : 'todo';

$todo = array_filter(user_matches($id, 'completed'), fn($m) => !$m['i_reviewed']);
$received = fetch_all('SELECT r.*, u.name AS other_name, u.id AS other_id FROM ratings r JOIN users u ON u.id = r.reviewer_id WHERE r.reviewee_id = ? AND r.is_hidden = 0 ORDER BY r.id DESC LIMIT 50', [$id]);
$given = fetch_all('SELECT r.*, u.name AS other_name, u.id AS other_id FROM ratings r JOIN users u ON u.id = r.reviewee_id WHERE r.reviewer_id = ? ORDER BY r.id DESC LIMIT 50', [$id]);
$stats = user_stats($id);

$pageTitle = 'Reviews';
$active = 'reviews';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Reviews</h1><p>Honest feedback keeps the community trustworthy.</p></div>
  <div><?= stars($stats['avg_rating'], $stats['reviews']) ?></div>
</div>

<div class="tabs">
  <a class="tab<?= $tab === 'todo' ? ' active' : '' ?>" href="?tab=todo">To review<span class="count"><?= count($todo) ?></span></a>
  <a class="tab<?= $tab === 'received' ? ' active' : '' ?>" href="?tab=received">Received<span class="count"><?= count($received) ?></span></a>
  <a class="tab<?= $tab === 'given' ? ' active' : '' ?>" href="?tab=given">Given<span class="count"><?= count($given) ?></span></a>
</div>

<?php if ($tab === 'todo'): ?>
  <?php if ($todo): ?>
    <div class="grid grid-2">
    <?php foreach ($todo as $m): ?>
      <section class="panel">
        <div class="panel-head"><div class="cell-user"><?= avatar(['id' => $m['partner_id'], 'name' => $m['partner_name']]) ?><div><b><?= e($m['partner_name']) ?></b><small class="muted"><?= e($m['i_learn'] ?? '') ?><?= $m['i_teach'] ? ' ' . icon('swap', 12) . ' ' . e($m['i_teach']) : '' ?></small></div></div></div>
        <form method="post" class="panel-body form">
          <?= csrf_field() ?><input type="hidden" name="match_id" value="<?= (int) $m['id'] ?>">
          <div class="field"><span class="label">How was your learning experience?</span>
            <div class="star-input" role="radiogroup" aria-label="Rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="s<?= (int) $m['id'] ?>-<?= $i ?>" name="score" value="<?= $i ?>" required><label for="s<?= (int) $m['id'] ?>-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><?= icon('star', 28) ?></label>
              <?php endfor; ?>
            </div></div>
          <div class="field"><label for="c<?= (int) $m['id'] ?>">Review</label><textarea id="c<?= (int) $m['id'] ?>" name="comment" maxlength="600" placeholder="What went well? What would you tell other students?"></textarea></div>
          <div><button class="btn btn-primary">Submit review</button></div>
        </form>
      </section>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="panel empty"><?= icon('star', 44) ?><strong>Nothing to review</strong>When you complete an exchange, you can rate your partner here.</div>
  <?php endif; ?>
<?php else:
    $rows = $tab === 'received' ? $received : $given; ?>
  <?php if ($rows): ?>
  <section class="panel"><ul class="list">
    <?php foreach ($rows as $r): ?>
      <li class="wrap" style="align-items:flex-start">
        <?= avatar(['id' => $r['other_id'], 'name' => $r['other_name']]) ?>
        <div class="grow"><div class="title"><?= $tab === 'received' ? 'From ' : 'To ' ?><?= e($r['other_name']) ?> <?= star_row((int) $r['score']) ?></div>
          <div class="sub"><?= $r['comment'] ? e($r['comment']) : '<i>No comment</i>' ?> · <?= e(time_ago($r['created_at'])) ?></div></div>
        <?php if ($tab === 'given'): ?>
          <div class="row-actions">
            <?php if ($r['is_hidden']): ?><span class="badge badge-neutral">Removed by admin</span>
            <?php else: ?><button class="btn btn-sm btn-outline" type="button" data-toggle="#edit-<?= (int) $r['id'] ?>"><?= icon('edit', 14) ?>Edit</button><?php endif; ?>
            <form method="post" class="inline-form" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger" aria-label="Delete review"><?= icon('trash', 14) ?>Delete</button></form>
          </div>
          <?php if (!$r['is_hidden']): ?>
          <form method="post" class="form hidden" id="edit-<?= (int) $r['id'] ?>" style="margin-top:12px">
            <?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <div class="star-input" role="radiogroup" aria-label="Rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="e<?= (int) $r['id'] ?>-<?= $i ?>" name="score" value="<?= $i ?>" required<?= (int) $r['score'] === $i ? ' checked' : '' ?>><label for="e<?= (int) $r['id'] ?>-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><?= icon('star', 28) ?></label>
              <?php endfor; ?>
            </div>
            <div class="field"><label for="ec<?= (int) $r['id'] ?>">Review</label><textarea id="ec<?= (int) $r['id'] ?>" name="comment" maxlength="600"><?= e($r['comment'] ?? '') ?></textarea></div>
            <div><button class="btn btn-primary btn-sm">Save changes</button></div>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul></section>
  <?php else: ?>
    <div class="panel empty"><?= icon('star', 44) ?><strong>No reviews <?= e($tab) ?> yet</strong></div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
