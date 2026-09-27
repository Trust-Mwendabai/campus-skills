<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $rid = (int) ($_POST['id'] ?? 0);
    $do = post_str('do', 10);
    if ($do === 'hide' || $do === 'show') {
        q('UPDATE ratings SET is_hidden = ? WHERE id = ?', [$do === 'hide' ? 1 : 0, $rid]);
        admin_log($me['id'], $do === 'hide' ? 'hide_review' : 'restore_review', 'review', $rid);
        flash('success', $do === 'hide' ? 'Review removed from public view.' : 'Review restored.');
    } elseif ($do === 'delete') {
        q('DELETE FROM ratings WHERE id = ?', [$rid]);
        admin_log($me['id'], 'delete_review', 'review', $rid);
        flash('success', 'Review deleted.');
    }
    redirect('reviews.php' . (isset($_POST['filter']) && $_POST['filter'] === 'low' ? '?filter=low' : ''));
}

$filter = in_array($_GET['filter'] ?? '', ['low', 'hidden'], true) ? $_GET['filter'] : '';
$where = match ($filter) { 'low' => 'WHERE r.score <= 2', 'hidden' => 'WHERE r.is_hidden = 1', default => '' };
$total = (int) fetch_value("SELECT COUNT(*) FROM ratings r $where");
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all(
    "SELECT r.*, a.id AS aid, a.name AS reviewer, b.id AS bid, b.name AS reviewee
       FROM ratings r JOIN users a ON a.id = r.reviewer_id JOIN users b ON b.id = r.reviewee_id
       $where ORDER BY r.id DESC LIMIT 20 OFFSET $offset");

$area = 'admin';
$active = 'reviews';
$pageTitle = 'Reviews';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Reviews</h1><p>Remove reviews that are abusive or misleading.</p></div></div>
<div class="tabs">
  <a class="tab<?= $filter === '' ? ' active' : '' ?>" href="reviews.php">All</a>
  <a class="tab<?= $filter === 'low' ? ' active' : '' ?>" href="?filter=low">Low ratings (1–2 <?= icon('star', 14) ?>)</a>
  <a class="tab<?= $filter === 'hidden' ? ' active' : '' ?>" href="?filter=hidden">Hidden</a>
</div>
<section class="panel">
  <?= render_table([
      ['Reviewer', fn($r) => user_cell($r['reviewer'], null, (int) $r['aid'])],
      ['Reviewed', fn($r) => user_cell($r['reviewee'], null, (int) $r['bid'])],
      ['Rating', fn($r) => star_row((int) $r['score'])],
      ['Comment', fn($r) => $r['comment'] ? e(mb_substr($r['comment'], 0, 200)) : '<span class="muted">—</span>'],
      ['Date', fn($r) => '<span class="muted nowrap">' . e(time_ago($r['created_at'])) . '</span>'],
      ['', function ($r) use ($filter) { return '<div class="row-actions">' . ($r['is_hidden'] ? action_form('show', (int) $r['id'], 'Restore', 'btn-soft', null, ['filter' => $filter]) : action_form('hide', (int) $r['id'], 'Hide', 'btn-outline', 'Hide this review from the public profile?', ['filter' => $filter]))
          . action_form('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Permanently delete this review?', ['filter' => $filter]) . '</div>'; }],
  ], $rows, 'No reviews found.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
