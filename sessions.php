<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 10);
    if ($do === 'create') {
        $r = create_session($id, $_POST);
    } elseif ($do === 'update') {
        $r = update_session((int) ($_POST['id'] ?? 0), $id, $_POST);
    } elseif ($do === 'delete') {
        $r = delete_session((int) ($_POST['id'] ?? 0), $id);
    } elseif (in_array($do, ['complete', 'cancel'], true)) {
        $r = change_session((int) ($_POST['id'] ?? 0), $id, $do === 'complete' ? 'completed' : 'cancelled');
    } else {
        $r = result(false, 'Unknown action.');
    }
    flash($r['ok'] ? 'success' : 'error', $r['message']);
    redirect('sessions.php' . ($r['ok'] ? '' : '?match=' . (int) ($_POST['match_id'] ?? 0) . '#schedule'));
}

mark_missed_sessions();
$tab = ($_GET['tab'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$activeMatches = user_matches($id, 'active');

// Preselect a match from ?match= or ?with=<partner>
$preselect = (int) ($_GET['match'] ?? 0);
if (!$preselect && !empty($_GET['with'])) {
    foreach ($activeMatches as $m) {
        if ((int) $m['partner_id'] === (int) $_GET['with']) $preselect = (int) $m['id'];
    }
}

$cond = $tab === 'upcoming' ? "s.status = 'scheduled'" : "s.status <> 'scheduled'";
$rows = fetch_all(
    "SELECT s.*, sk.name AS skill_name, p.id AS partner_id, p.name AS partner_name
       FROM sessions s JOIN matches m ON m.id = s.match_id
       JOIN users p ON p.id = IF(m.user_a = ?, m.user_b, m.user_a)
       LEFT JOIN skills sk ON sk.id = s.skill_id
      WHERE (m.user_a = ? OR m.user_b = ?) AND $cond
      ORDER BY s.session_date " . ($tab === 'upcoming' ? 'ASC' : 'DESC') . ', s.start_time LIMIT 50',
    [$id, $id, $id]);

$pageTitle = 'Learning Sessions';
$active = 'sessions';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Learning Sessions</h1><p>Organise the time you'll spend teaching and learning together.</p></div>
  <button class="btn btn-primary" type="button" data-toggle="#schedule"><?= icon('plus', 16) ?>Schedule session</button>
</div>

<section class="panel mb <?= $preselect ? '' : 'hidden' ?>" id="schedule">
  <div class="panel-head"><h2>Schedule a session</h2></div>
  <?php if (!$activeMatches): ?>
    <div class="empty">You need an active match before you can schedule a session. <a href="find-skills.php?view=recommended" style="color:var(--primary);font-weight:600">Find a partner</a></div>
  <?php else: ?>
  <form method="post" class="panel-body form">
    <?= csrf_field() ?><input type="hidden" name="do" value="create">
    <div class="form-row">
      <div class="field"><label for="match_id">Learning partner</label>
        <select id="match_id" name="match_id" required>
          <?php foreach ($activeMatches as $m): ?><option value="<?= (int) $m['id'] ?>"<?= $preselect === (int) $m['id'] ? ' selected' : '' ?>><?= e($m['partner_name']) ?> — <?= e($m['i_learn'] ?? '') ?><?= $m['i_teach'] ? ' / ' . e($m['i_teach']) : '' ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="title">Session title</label><input id="title" name="title" maxlength="150" required placeholder="e.g. Python basics: loops and functions"></div>
    </div>
    <div class="form-row">
      <div class="field"><label for="session_date">Date</label><input id="session_date" type="date" name="session_date" min="<?= date('Y-m-d') ?>" required></div>
      <div class="form-row" style="gap:12px">
        <div class="field"><label for="start_time">Start time</label><input id="start_time" type="time" name="start_time" required></div>
        <div class="field"><label for="end_time">End time</label><input id="end_time" type="time" name="end_time" required></div>
      </div>
    </div>
    <div class="form-row">
      <div class="field"><label for="location">Location <span class="opt">(optional)</span></label><input id="location" name="location" maxlength="150" placeholder="e.g. Library, 2nd floor"></div>
      <div class="field"><label for="meeting_link">Meeting link <span class="opt">(optional)</span></label><input id="meeting_link" type="url" name="meeting_link" maxlength="255" placeholder="https://…"></div>
    </div>
    <div class="field"><label for="notes">Notes <span class="opt">(optional)</span></label><textarea id="notes" name="notes" maxlength="500" style="min-height:70px"></textarea></div>
    <div><button class="btn btn-primary">Schedule session</button></div>
  </form>
  <?php endif; ?>
</section>

<div class="tabs">
  <a class="tab<?= $tab === 'upcoming' ? ' active' : '' ?>" href="?tab=upcoming">Upcoming</a>
  <a class="tab<?= $tab === 'past' ? ' active' : '' ?>" href="?tab=past">Past</a>
</div>

<?php if ($rows): ?>
<section class="panel"><ul class="list">
  <?php foreach ($rows as $s): $t = strtotime($s['session_date']); ?>
    <li class="wrap" style="align-items:flex-start">
      <div class="session-date"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
      <div class="grow">
        <div class="title"><?= e($s['title']) ?> <?= status_badge($s['status']) ?></div>
        <div class="sub">with <a href="profile.php?id=<?= (int) $s['partner_id'] ?>" style="text-decoration:underline"><?= e($s['partner_name']) ?></a><?= $s['skill_name'] ? ' · ' . e($s['skill_name']) : '' ?></div>
        <div class="sub"><?= icon('clock', 13) ?> <?= e(date('D, M j', $t)) ?> · <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?>
          <?php if ($s['location']): ?> · <?= icon('pin', 13) ?> <?= e($s['location']) ?><?php endif; ?>
          <?php if ($s['meeting_link']): ?> · <?= icon('link', 13) ?> <a href="<?= e($s['meeting_link']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--primary)">Join link</a><?php endif; ?></div>
        <?php if ($s['notes']): ?><div class="sub"><?= e($s['notes']) ?></div><?php endif; ?>
      </div>
      <div class="row-actions">
      <?php if ($s['status'] === 'scheduled'): ?>
        <?php if ($s['session_date'] <= date('Y-m-d')): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="do" value="complete"><button class="btn btn-sm btn-success"><?= icon('check', 14) ?>Mark completed</button></form>
        <?php endif; ?>
        <button class="btn btn-sm btn-outline" type="button" data-toggle="#edit-<?= (int) $s['id'] ?>"><?= icon('edit', 14) ?>Edit</button>
        <form method="post" class="inline-form" data-confirm="Cancel this session?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="do" value="cancel"><button class="btn btn-sm btn-outline">Cancel</button></form>
      <?php endif; ?>
      <?php if ((int) $s['created_by'] === $id): ?>
        <form method="post" class="inline-form" data-confirm="Permanently delete this session?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="do" value="delete"><button class="btn btn-sm btn-danger" aria-label="Delete session"><?= icon('trash', 14) ?>Delete</button></form>
      <?php endif; ?>
      </div>
      <?php if ($s['status'] === 'scheduled'): ?>
      <form method="post" class="form hidden" id="edit-<?= (int) $s['id'] ?>" style="margin-top:12px">
        <?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
        <div class="form-row">
          <div class="field"><label for="et<?= (int) $s['id'] ?>">Title</label><input id="et<?= (int) $s['id'] ?>" name="title" maxlength="150" value="<?= e($s['title']) ?>" required></div>
          <div class="field"><label for="ed<?= (int) $s['id'] ?>">Date</label><input id="ed<?= (int) $s['id'] ?>" type="date" name="session_date" min="<?= date('Y-m-d') ?>" value="<?= e($s['session_date']) ?>" required></div>
        </div>
        <div class="form-row">
          <div class="field"><label for="es<?= (int) $s['id'] ?>">Start time</label><input id="es<?= (int) $s['id'] ?>" type="time" name="start_time" value="<?= e(substr($s['start_time'], 0, 5)) ?>" required></div>
          <div class="field"><label for="ee<?= (int) $s['id'] ?>">End time</label><input id="ee<?= (int) $s['id'] ?>" type="time" name="end_time" value="<?= e(substr($s['end_time'], 0, 5)) ?>" required></div>
        </div>
        <div class="form-row">
          <div class="field"><label for="el<?= (int) $s['id'] ?>">Location</label><input id="el<?= (int) $s['id'] ?>" name="location" maxlength="150" value="<?= e($s['location'] ?? '') ?>"></div>
          <div class="field"><label for="ek<?= (int) $s['id'] ?>">Meeting link</label><input id="ek<?= (int) $s['id'] ?>" type="url" name="meeting_link" maxlength="255" value="<?= e($s['meeting_link'] ?? '') ?>"></div>
        </div>
        <div class="field"><label for="en<?= (int) $s['id'] ?>">Notes</label><textarea id="en<?= (int) $s['id'] ?>" name="notes" maxlength="500" style="min-height:70px"><?= e($s['notes'] ?? '') ?></textarea></div>
        <input type="hidden" name="skill_id" value="<?= (int) $s['skill_id'] ?>">
        <div><button class="btn btn-primary btn-sm">Save changes</button></div>
      </form>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul></section>
<?php else: ?>
  <div class="panel empty"><?= icon('calendar', 44) ?><strong>No <?= e($tab) ?> sessions</strong><?= $tab === 'upcoming' ? 'Schedule a session with one of your matches.' : 'Completed and cancelled sessions will appear here.' ?></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
