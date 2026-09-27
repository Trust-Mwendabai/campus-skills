<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();

$viewId = (int) ($_GET['id'] ?? $me['id']);
$isSelf = $viewId === $me['id'];
$user = $isSelf ? $me : fetch_one("SELECT * FROM users WHERE id = ? AND status = 'active' AND role = 'student'", [$viewId]);
if (!$user) {
    http_response_code(404);
    $pageTitle = 'Not found';
    $active = 'find';
    require __DIR__ . '/includes/header.php';
    echo '<div class="panel empty"><strong>Student not found</strong>This profile does not exist or is unavailable.<div><a class="btn btn-primary btn-sm" href="find-skills.php">Find skills</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$matched = !$isSelf && is_matched($me['id'], $viewId);
$canSee = $isSelf || $matched || $user['profile_visibility'] === 'everyone';
$canSeeSkills = $isSelf || $matched || ($canSee && $user['skill_visibility'] === 'everyone');

if (is_post() && !$isSelf) {
    csrf_check();
    if (($_POST['do'] ?? '') === 'request') {
        $r = create_request($me['id'], $_POST + ['receiver_id' => $viewId]);
        flash($r['ok'] ? 'success' : 'error', $r['message']);
        redirect($r['ok'] ? 'requests.php?tab=sent' : 'profile.php?id=' . $viewId . '&request=1');
    }
}

$stats = user_stats($viewId);
$offers = $wants = $avail = $reviews = [];
if ($canSee) {
    if ($canSeeSkills) {
        $rows = fetch_all('SELECT us.*, s.name, s.id AS sid, c.name AS category FROM user_skills us JOIN skills s ON s.id = us.skill_id JOIN categories c ON c.id = s.category_id WHERE us.user_id = ? AND s.is_active = 1 ORDER BY s.name', [$viewId]);
        $offers = array_values(array_filter($rows, fn($r) => $r['type'] === 'offer'));
        $wants  = array_values(array_filter($rows, fn($r) => $r['type'] === 'want'));
    }
    $avail = fetch_all('SELECT * FROM availability WHERE user_id = ? ORDER BY day_of_week, start_time', [$viewId]);
    $reviews = fetch_all(
        'SELECT r.*, u.name AS reviewer_name, u.id AS reviewer_id FROM ratings r JOIN users u ON u.id = r.reviewer_id
          WHERE r.reviewee_id = ? AND r.is_hidden = 0 ORDER BY r.id DESC LIMIT 10', [$viewId]);
}

$compat = null;
$myOffers = [];
if (!$isSelf && $canSee) {
    $b = load_bundles([$me['id'], $viewId]);
    $compat = score_pair($b[$me['id']], $b[$viewId]);
    $myOffers = fetch_all("SELECT s.id, s.name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ? AND us.type = 'offer' AND s.is_active = 1 ORDER BY s.name", [$me['id']]);
}
$pendingReq = !$isSelf ? fetch_all("SELECT want_skill_id FROM requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'", [$me['id'], $viewId]) : [];
$pendingSkillIds = array_column($pendingReq, 'want_skill_id');
$showRequest = !$isSelf && $canSee && !empty($_GET['request']);

$pageTitle = $isSelf ? 'My Profile' : $user['name'];
$active = $isSelf ? 'profile' : 'find';
require __DIR__ . '/includes/header.php';
?>
<section class="panel mb">
  <div class="profile-head">
    <?= avatar($user, 'xl') ?>
    <div class="grow">
      <h1><?= e($user['name']) ?></h1>
      <div class="profile-meta">
        <?= e($user['programme'] ?: 'Student') ?><?= $user['faculty'] ? ' · ' . e($user['faculty']) : '' ?><?= $user['year_of_study'] ? ' · Year ' . (int) $user['year_of_study'] : '' ?>
      </div>
      <div style="margin:6px 0 12px"><?= stars($stats['avg_rating'], $stats['reviews']) ?></div>
      <div class="row-actions">
        <?php if ($isSelf): ?>
          <a class="btn btn-primary btn-sm" href="edit-profile.php"><?= icon('edit', 14) ?>Edit profile</a>
          <a class="btn btn-outline btn-sm" href="skills.php">Manage skills</a>
        <?php elseif ($canSee): ?>
          <?php if ($matched || can_message($me['id'], $viewId)): ?><a class="btn btn-soft btn-sm" href="messages.php?with=<?= $viewId ?>"><?= icon('messages', 14) ?>Message</a><?php endif; ?>
          <a class="btn btn-primary btn-sm" href="profile.php?id=<?= $viewId ?>&amp;request=1#request"><?= icon('swap', 14) ?>Request exchange</a>
          <a class="btn btn-outline btn-sm" href="reports.php?user=<?= $viewId ?>"><?= icon('flag', 14) ?>Report</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="mini-stats">
      <div><b><?= $stats['exchanges'] ?></b><span>Exchanges</span></div>
      <div><b><?= $stats['completed'] ?></b><span>Completed</span></div>
      <div><b><?= $stats['avg_rating'] !== null ? number_format($stats['avg_rating'], 1) : '—' ?></b><span>Avg. rating</span></div>
    </div>
  </div>
</section>

<?php if (!$canSee): ?>
  <div class="panel empty"><?= icon('shield', 44) ?><strong>This profile is private</strong>This student only shares their profile with people they've matched with.</div>
<?php else: ?>

<?php if ($showRequest): ?>
<section class="panel mb" id="request">
  <div class="panel-head"><h2>Request an exchange with <?= e(first_name($user['name'])) ?></h2></div>
  <?php if (!$offers): ?>
    <div class="empty"><?= $canSeeSkills ? 'This student has not listed any skills to teach yet.' : 'This student keeps their skills private until you match.' ?></div>
  <?php else: ?>
  <form method="post" class="panel-body form">
    <?= csrf_field() ?><input type="hidden" name="do" value="request">
    <div class="form-row">
      <div class="field"><label for="want_skill_id">Skill I want to learn</label>
        <select id="want_skill_id" name="want_skill_id" required>
          <?php foreach ($offers as $o): ?><option value="<?= (int) $o['sid'] ?>"<?= in_array($o['sid'], $pendingSkillIds) ? ' disabled' : '' ?>><?= e($o['name']) ?> (<?= e(LEVELS[$o['level']]) ?>)<?= in_array($o['sid'], $pendingSkillIds) ? ' — request pending' : '' ?></option><?php endforeach; ?>
        </select></div>
      <div class="field"><label for="offer_skill_id">Skill I can offer in return</label>
        <select id="offer_skill_id" name="offer_skill_id">
          <option value="">Nothing in particular</option>
          <?php foreach ($myOffers as $o): ?><option value="<?= (int) $o['id'] ?>"<?= in_array($o['name'], $compat['i_teach_them'] ?? []) ? ' selected' : '' ?>><?= e($o['name']) ?></option><?php endforeach; ?>
        </select>
        <?php if (!$myOffers): ?><span class="hint">You haven't listed any skills you can teach. <a href="skills.php" style="color:var(--primary)">Add some</a> to offer an exchange.</span><?php endif; ?></div>
    </div>
    <div class="field"><label for="message">Message</label>
      <textarea id="message" name="message" maxlength="600" placeholder="Hi <?= e(first_name($user['name'])) ?>, I'd like to learn … and I can help you with …"></textarea></div>
    <div class="form-row">
      <div class="field"><label for="method">Preferred learning method</label>
        <select id="method" name="method"><?php foreach (METHODS as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="form-row" style="gap:12px">
        <div class="field"><label for="preferred_date">Preferred date</label><input id="preferred_date" type="date" name="preferred_date" min="<?= date('Y-m-d') ?>"></div>
        <div class="field"><label for="preferred_time">Preferred time</label><input id="preferred_time" type="time" name="preferred_time"></div>
      </div>
    </div>
    <div class="row-actions"><button class="btn btn-primary">Send request</button><a class="btn btn-outline" href="profile.php?id=<?= $viewId ?>">Cancel</a></div>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid grid-main">
  <div class="grid" style="gap:24px">
    <section class="panel">
      <div class="panel-head"><h2>About</h2></div>
      <div class="panel-body"><?= $user['bio'] ? nl2br(e($user['bio'])) : '<span class="muted">' . ($isSelf ? 'You haven\'t written a bio yet. <a href="edit-profile.php" style="color:var(--primary)">Add one</a>.' : 'No bio yet.') . '</span>' ?></div>
    </section>

    <?php if ($canSeeSkills): ?>
    <div class="grid grid-2" style="align-items:start">
      <section class="panel">
        <div class="panel-head"><h2>Skills offered</h2></div>
        <?php foreach ($offers as $s): ?>
          <div class="skill-card"><div class="grow"><b><?= e($s['name']) ?></b> <?= level_badge($s['level']) ?>
            <div class="muted small"><?= e($s['category']) ?><?= $s['experience'] ? ' · ' . e($s['experience']) : '' ?></div>
            <?php if ($s['description']): ?><div class="small" style="margin-top:4px"><?= e($s['description']) ?></div><?php endif; ?></div></div>
        <?php endforeach; if (!$offers): ?><div class="empty">No skills offered yet.</div><?php endif; ?>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Skills wanted</h2></div>
        <?php foreach ($wants as $s): ?>
          <div class="skill-card"><div class="grow"><b><?= e($s['name']) ?></b> <?= level_badge($s['level']) ?>
            <div class="muted small"><?= e($s['category']) ?></div>
            <?php if ($s['description']): ?><div class="small" style="margin-top:4px"><?= e($s['description']) ?></div><?php endif; ?></div></div>
        <?php endforeach; if (!$wants): ?><div class="empty">No skills wanted yet.</div><?php endif; ?>
      </section>
    </div>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head"><h2>Reviews</h2><?= stars($stats['avg_rating'], $stats['reviews']) ?></div>
      <?php if ($reviews): ?>
      <ul class="list">
        <?php foreach ($reviews as $r): ?>
          <li style="align-items:flex-start">
            <?= avatar(['id' => $r['reviewer_id'], 'name' => $r['reviewer_name']]) ?>
            <div class="grow"><div class="title"><?= e($r['reviewer_name']) ?> <?= star_row((int) $r['score']) ?></div>
              <div class="sub"><?= $r['comment'] ? e($r['comment']) : '<i>No comment</i>' ?> · <?= e(time_ago($r['created_at'])) ?></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?><div class="empty">No reviews yet.</div><?php endif; ?>
    </section>
  </div>

  <div class="grid" style="gap:24px">
    <?php if ($compat): ?>
    <section class="panel">
      <div class="panel-head"><h2>Compatibility</h2></div>
      <div class="panel-body">
        <div style="display:flex;gap:16px;align-items:center;margin-bottom:14px"><?= score_ring($compat['score'], 'lg') ?><div><b><?= $compat['mutual'] ? 'Two-way match' : 'Partial match' ?></b><div class="muted small">Based on skills, availability and more</div></div></div>
        <p class="why" style="margin-bottom:14px"><b>Why:</b> <?= e($compat['why']) ?></p>
        <?php foreach ($compat['parts'] as $label => $pct): ?>
          <div class="bar-line" style="grid-template-columns:1fr 40px"><span style="grid-column:1/-1;color:var(--muted)"><?= e($label) ?></span><div class="bar"><i style="width:<?= $pct ?>%"></i></div><b><?= $pct ?>%</b></div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="panel">
      <div class="panel-head"><h2>Availability</h2><?php if ($isSelf): ?><a href="settings.php#availability">Edit</a><?php endif; ?></div>
      <div class="panel-body">
        <?php foreach ($avail as $a): ?>
          <div class="avail-row"><span><?= e(DAYS[$a['day_of_week']]) ?></span><b><?= e(substr($a['start_time'], 0, 5)) ?> – <?= e(substr($a['end_time'], 0, 5)) ?></b></div>
        <?php endforeach; if (!$avail): ?><span class="muted">No availability shared yet.</span><?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Learning preferences</h2></div>
      <div class="panel-body">
        <dl class="kv">
          <dt>Format</dt><dd><?= e(METHODS[$user['learning_method']]) ?></dd>
          <dt>Session length</dt><dd><?= (int) $user['session_duration'] ?> minutes</dd>
          <dt>Member since</dt><dd><?= e(date('M Y', strtotime($user['created_at']))) ?></dd>
        </dl>
      </div>
    </section>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
