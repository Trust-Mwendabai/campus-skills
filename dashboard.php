<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
mark_missed_sessions();

$id = $me['id'];
$offers  = (int) fetch_value("SELECT COUNT(*) FROM user_skills WHERE user_id = ? AND type = 'offer'", [$id]);
$wants   = (int) fetch_value("SELECT COUNT(*) FROM user_skills WHERE user_id = ? AND type = 'want'", [$id]);
$activeMatches = (int) fetch_value("SELECT COUNT(*) FROM matches WHERE (user_a = ? OR user_b = ?) AND status = 'active'", [$id, $id]);
$pending = (int) fetch_value("SELECT COUNT(*) FROM requests WHERE (sender_id = ? OR receiver_id = ?) AND status = 'pending'", [$id, $id]);

$recommended = find_matches($id, 3);

$incoming = fetch_all(
    "SELECT r.id, r.message, r.created_at, u.id AS uid, u.name, s.name AS skill
       FROM requests r JOIN users u ON u.id = r.sender_id JOIN skills s ON s.id = r.want_skill_id
      WHERE r.receiver_id = ? AND r.status = 'pending' ORDER BY r.id DESC LIMIT 4", [$id]);
$outgoing = fetch_all(
    "SELECT r.id, r.created_at, u.id AS uid, u.name, s.name AS skill
       FROM requests r JOIN users u ON u.id = r.receiver_id JOIN skills s ON s.id = r.want_skill_id
      WHERE r.sender_id = ? AND r.status = 'pending' ORDER BY r.id DESC LIMIT 3", [$id]);

$sessions = fetch_all(
    "SELECT s.*, p.name AS partner_name FROM sessions s
       JOIN matches m ON m.id = s.match_id
       JOIN users p ON p.id = IF(m.user_a = ?, m.user_b, m.user_a)
      WHERE (m.user_a = ? OR m.user_b = ?) AND s.status = 'scheduled' AND s.session_date >= CURDATE()
      ORDER BY s.session_date, s.start_time LIMIT 4", [$id, $id, $id]);

$chats = conversations($id, 4);
$activity = fetch_all('SELECT title, body, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6', [$id]);

// Profile completeness
$hasAvail = (bool) fetch_value('SELECT 1 FROM availability WHERE user_id = ? LIMIT 1', [$id]);
$steps = [
    'Add your bio'                => (bool) $me['bio'],
    'Add a phone number'          => (bool) $me['phone'],
    'List a skill you can teach'  => $offers > 0,
    'List a skill you want'       => $wants > 0,
    'Set your availability'       => $hasAvail,
];
$done = count(array_filter($steps));
$pct = (int) round(($done / count($steps)) * 100);

$pageTitle = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<section class="hero-card">
  <h1><?= e(greeting()) ?>, <?= e(first_name($me['name'])) ?></h1>
  <p>Find someone who can teach you something new today.</p>
  <form class="hero-search" action="find-skills.php" method="get" role="search">
    <label for="hq" class="sr-only">Search for a skill</label>
    <input id="hq" name="q" type="search" placeholder="Search for a skill…" data-suggest="skillList" autocomplete="off">
    <datalist id="skillList"></datalist>
    <button class="btn btn-primary" type="submit">Search</button>
  </form>
</section>

<section class="grid grid-4 stats mb" aria-label="Your statistics">
  <a class="panel stat" href="skills.php" style="text-decoration:none"><span class="stat-icon green"><?= icon('award', 22) ?></span><div><div class="stat-num"><?= $offers ?></div><div class="stat-label">Skills I offer</div></div></a>
  <a class="panel stat" href="skills.php#want" style="text-decoration:none"><span class="stat-icon amber"><?= icon('skills', 22) ?></span><div><div class="stat-num"><?= $wants ?></div><div class="stat-label">Skills I want</div></div></a>
  <a class="panel stat" href="matches.php" style="text-decoration:none"><span class="stat-icon"><?= icon('zap', 22) ?></span><div><div class="stat-num"><?= $activeMatches ?></div><div class="stat-label">Active matches</div></div></a>
  <a class="panel stat" href="requests.php" style="text-decoration:none"><span class="stat-icon red"><?= icon('requests', 22) ?></span><div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Pending requests</div></div></a>
</section>

<?php if ($pct < 100): ?>
<section class="panel mb">
  <div class="panel-body" style="display:flex;gap:24px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:240px">
      <strong>Complete your profile — <?= $pct ?>%</strong>
      <p class="muted small" style="margin:2px 0 10px">A complete profile gets better matches and more requests.</p>
      <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $pct ?>%"></i></div>
    </div>
    <ul class="check-list" style="margin:0">
      <?php foreach ($steps as $label => $ok): ?>
        <li class="<?= $ok ? 'done' : '' ?>"><?= icon($ok ? 'check' : 'plus', 14) ?> <?= e($label) ?></li>
      <?php endforeach; ?>
    </ul>
    <a class="btn btn-primary" href="<?= $offers && $wants ? 'edit-profile.php' : 'skills.php' ?>">Continue</a>
  </div>
</section>
<?php endif; ?>

<div class="grid grid-main">
  <div class="grid" style="gap:24px">
    <section>
      <div class="page-head" style="margin-bottom:12px"><h2>Recommended partners</h2><a href="find-skills.php?view=recommended" class="small" style="color:var(--primary);font-weight:600;text-decoration:none">See all →</a></div>
      <?php if ($recommended): ?>
        <div class="grid grid-3" style="grid-template-columns:repeat(auto-fill,minmax(250px,1fr))">
          <?php foreach ($recommended as $m) echo render_match_card($m); ?>
        </div>
      <?php else: ?>
        <div class="panel empty">
          <?= icon('zap', 40) ?>
          <strong>No recommendations yet</strong>
          Add skills you can teach and want to learn, and we'll find compatible students.
          <div><a class="btn btn-primary btn-sm" href="skills.php">Add skills</a></div>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Pending requests</h2><a href="requests.php">Manage</a></div>
      <?php if ($incoming || $outgoing): ?>
      <ul class="list">
        <?php foreach ($incoming as $r): ?>
          <li>
            <?= avatar(['id' => $r['uid'], 'name' => $r['name']]) ?>
            <div class="grow"><a class="title" href="profile.php?id=<?= (int) $r['uid'] ?>"><?= e($r['name']) ?></a>
              <div class="sub">wants to learn <b><?= e($r['skill']) ?></b> from you · <?= e(time_ago($r['created_at'])) ?></div></div>
            <div class="row-actions">
              <form method="post" action="requests.php" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="do" value="accept"><input type="hidden" name="back" value="dashboard.php"><button class="btn btn-sm btn-success">Accept</button></form>
              <form method="post" action="requests.php" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="do" value="reject"><input type="hidden" name="back" value="dashboard.php"><button class="btn btn-sm btn-outline">Decline</button></form>
            </div>
          </li>
        <?php endforeach; ?>
        <?php foreach ($outgoing as $r): ?>
          <li>
            <?= avatar(['id' => $r['uid'], 'name' => $r['name']]) ?>
            <div class="grow"><a class="title" href="profile.php?id=<?= (int) $r['uid'] ?>"><?= e($r['name']) ?></a>
              <div class="sub">You asked to learn <b><?= e($r['skill']) ?></b> · <?= e(time_ago($r['created_at'])) ?></div></div>
            <span class="badge badge-warn">Awaiting reply</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <div class="empty">No pending requests. <a href="find-skills.php" style="color:var(--primary);font-weight:600">Find a partner</a></div>
      <?php endif; ?>
    </section>
  </div>

  <div class="grid" style="gap:24px">
    <section class="panel">
      <div class="panel-head"><h2>Upcoming sessions</h2><a href="sessions.php">All</a></div>
      <?php if ($sessions): ?>
      <ul class="list">
        <?php foreach ($sessions as $s): $t = strtotime($s['session_date']); ?>
          <li>
            <div class="session-date"><b><?= date('j', $t) ?></b><span><?= date('M', $t) ?></span></div>
            <div class="grow"><div class="title"><?= e($s['title']) ?></div>
              <div class="sub">with <?= e($s['partner_name']) ?> · <?= e(substr($s['start_time'], 0, 5)) ?>–<?= e(substr($s['end_time'], 0, 5)) ?></div></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <div class="empty">Nothing scheduled yet.<div><a class="btn btn-soft btn-sm" href="sessions.php">Schedule a session</a></div></div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Recent messages</h2><a href="messages.php">Open</a></div>
      <?php if ($chats): ?>
      <ul class="list">
        <?php foreach ($chats as $c): ?>
          <li>
            <?= avatar(['id' => $c['partner_id'], 'name' => $c['partner_name']]) ?>
            <div class="grow"><a class="title" href="messages.php?with=<?= (int) $c['partner_id'] ?>"><?= e($c['partner_name']) ?></a>
              <div class="sub" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= (int) $c['sender_id'] === $id ? 'You: ' : '' ?><?= e(mb_substr($c['body'], 0, 50)) ?></div></div>
            <?php if ($c['unread']): ?><span class="badge badge-good"><?= (int) $c['unread'] ?> new</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <div class="empty">No conversations yet.</div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Recent activity</h2></div>
      <?php if ($activity): ?>
      <ul class="list">
        <?php foreach ($activity as $a): ?>
          <li><div class="grow"><div class="title"><?= e($a['title']) ?></div><div class="sub"><?= e($a['body'] ?? '') ?></div></div><small class="muted nowrap"><?= e(time_ago($a['created_at'])) ?></small></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <div class="empty">Your activity will appear here.</div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
