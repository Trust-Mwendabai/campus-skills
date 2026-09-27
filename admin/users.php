<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/exchange.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $do = post_str('do', 12);
    $uid = (int) ($_POST['id'] ?? 0);
    $target = fetch_one("SELECT * FROM users WHERE id = ? AND role = 'student'", [$uid]);   // students only; admins live in Admin Accounts
    if (!$target) {
        flash('error', 'Student not found.');
        redirect('users.php');
    }
    switch ($do) {
        case 'activate':
            q("UPDATE users SET status = 'active' WHERE id = ?", [$uid]);
            admin_log($me['id'], 'activate_account', 'user', $uid, $target['name']);
            notify($uid, 'announcement', 'Account activated', 'Your account has been verified. Welcome to ' . APP_NAME . '!', url('dashboard.php'));
            flash('success', $target['name'] . ' is now active.');
            break;
        case 'suspend':
            q("UPDATE users SET status = 'suspended' WHERE id = ?", [$uid]);
            admin_log($me['id'], 'suspend_account', 'user', $uid, $target['name']);
            flash('success', $target['name'] . ' has been suspended.');
            break;
        case 'delete':
            q('DELETE FROM users WHERE id = ?', [$uid]);
            admin_log($me['id'], 'delete_account', 'user', null, $target['name'] . ' (' . $target['student_id'] . ')');
            flash('success', 'Account deleted.');
            redirect('users.php');
        case 'edit':
            $name = post_str('name', 100);
            $year = (int) ($_POST['year_of_study'] ?? 0);
            if (mb_strlen($name) < 2) {
                flash('error', 'Name must be at least 2 characters.');
                break;
            }
            q('UPDATE users SET name = ?, phone = ?, programme = ?, faculty = ?, year_of_study = ?, bio = ? WHERE id = ?',
              [$name, post_str('phone', 30) ?: null, post_str('programme', 120) ?: null, post_str('faculty', 120) ?: null, ($year >= 1 && $year <= 7) ? $year : null, post_str('bio', 1000) ?: null, $uid]);
            admin_log($me['id'], 'edit_profile', 'user', $uid, $name);
            flash('success', 'Profile updated.');
            break;
    }
    redirect('users.php?id=' . $uid);
}

$area = 'admin';
$active = 'users';

// ---------------- Detail view ----------------
if (!empty($_GET['id'])) {
    $u = fetch_one("SELECT * FROM users WHERE id = ? AND role = 'student'", [(int) $_GET['id']]);
    if (!$u) {
        flash('error', 'Student not found.');
        redirect('users.php');
    }
    $stats = user_stats((int) $u['id']);
    $skills = fetch_all('SELECT us.type, us.level, s.name FROM user_skills us JOIN skills s ON s.id = us.skill_id WHERE us.user_id = ? ORDER BY us.type, s.name', [$u['id']]);
    $reportsAgainst = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE reported_user_id = ?', [$u['id']]);
    $reportsBy = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE reporter_id = ?', [$u['id']]);

    $pageTitle = $u['name'];
    require __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-head">
      <div><a href="users.php" class="muted small">← All students</a><h1><?= e($u['name']) ?> <?= status_badge($u['status']) ?></h1><p><?= e($u['email']) ?> · <?= e($u['student_id']) ?></p></div>
      <div class="page-actions">
        <?php if ($u['status'] !== 'active') echo action_form('activate', (int) $u['id'], 'Activate account', 'btn-success'); ?>
        <?php if ($u['status'] !== 'suspended') echo action_form('suspend', (int) $u['id'], 'Suspend', 'btn-outline', 'Suspend ' . $u['name'] . '? They will be signed out and unable to log in.'); ?>
        <?= action_form('delete', (int) $u['id'], 'Delete', 'btn-danger', 'Permanently delete ' . $u['name'] . ' and all their data? This cannot be undone.') ?>
      </div>
    </div>

    <div class="grid grid-main">
      <section class="panel">
        <div class="panel-head"><h2>Edit profile</h2></div>
        <form method="post" class="panel-body form">
          <?= csrf_field() ?><input type="hidden" name="do" value="edit"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <div class="form-row">
            <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($u['name']) ?>" required></div>
            <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($u['phone']) ?>"></div>
          </div>
          <div class="form-row">
            <div class="field"><label for="programme">Programme</label><input id="programme" name="programme" value="<?= e($u['programme']) ?>"></div>
            <div class="field"><label for="faculty">Faculty</label><input id="faculty" name="faculty" value="<?= e($u['faculty']) ?>"></div>
          </div>
          <div class="field" style="max-width:200px"><label for="yr">Year of study</label><input id="yr" type="number" min="1" max="7" name="year_of_study" value="<?= e((string) $u['year_of_study']) ?>"></div>
          <div class="field"><label for="bio">Bio</label><textarea id="bio" name="bio" maxlength="1000"><?= e($u['bio']) ?></textarea></div>
          <div><button class="btn btn-primary">Save changes</button></div>
        </form>
      </section>
      <div class="grid" style="gap:24px">
        <section class="panel"><div class="panel-head"><h2>Activity</h2></div><div class="panel-body"><dl class="kv">
          <dt>Joined</dt><dd><?= e(date('M j, Y', strtotime($u['created_at']))) ?></dd>
          <dt>Last login</dt><dd><?= $u['last_login'] ? e(time_ago($u['last_login'])) : 'Never' ?></dd>
          <dt>Exchanges</dt><dd><?= $stats['exchanges'] ?> (<?= $stats['completed'] ?> completed)</dd>
          <dt>Rating</dt><dd><?= stars($stats['avg_rating'], $stats['reviews']) ?></dd>
          <dt>Reports against</dt><dd><?= $reportsAgainst ?></dd>
          <dt>Reports filed</dt><dd><?= $reportsBy ?></dd>
        </dl></div></section>
        <section class="panel"><div class="panel-head"><h2>Skills</h2></div><div class="panel-body">
          <?php foreach (['offer' => 'chip-teach', 'want' => 'chip-learn'] as $type => $cls): ?>
            <div style="margin-bottom:10px"><small class="muted"><?= $type === 'offer' ? 'Teaches' : 'Wants to learn' ?></small><div>
              <?php foreach ($skills as $s) if ($s['type'] === $type) echo '<span class="chip ' . $cls . '">' . e($s['name']) . '</span>'; ?></div></div>
          <?php endforeach; if (!$skills) echo '<span class="muted">No skills listed.</span>'; ?>
        </div></section>
      </div>
    </div>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// ---------------- List view ----------------
$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['active', 'pending', 'suspended'], true) ? $_GET['status'] : '';
$year = (int) ($_GET['year'] ?? 0);
$where = ["role = 'student'"];
$params = [];
if ($search !== '') {
    $l = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(name LIKE ? OR email LIKE ? OR student_id LIKE ? OR programme LIKE ?)';
    array_push($params, $l, $l, $l, $l);
}
if ($status) { $where[] = 'status = ?'; $params[] = $status; }
if ($year >= 1 && $year <= 7) { $where[] = 'year_of_study = ?'; $params[] = $year; }
$w = implode(' AND ', $where);

$total = (int) fetch_value("SELECT COUNT(*) FROM users WHERE $w", $params);
[$page, $pages, $offset] = paginate($total, 15);
$rows = fetch_all("SELECT id, student_id, name, email, programme, year_of_study, status, created_at FROM users WHERE $w ORDER BY id DESC LIMIT 15 OFFSET $offset", $params);

$pageTitle = 'Students';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Students</h1><p><?= $total ?> student<?= $total === 1 ? '' : 's' ?> registered</p></div></div>

<form class="panel mb" method="get"><div class="filter-bar">
  <div style="flex:2 1 220px"><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, student ID or programme"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
    <?php foreach (['active', 'pending', 'suspended'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
  <div><label for="year">Year</label><select id="year" name="year"><option value="">Any</option>
    <?php for ($y = 1; $y <= 7; $y++): ?><option value="<?= $y ?>"<?= $year === $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?></select></div>
  <div style="flex:0 0 auto;flex-direction:row;gap:8px"><button class="btn btn-primary">Filter</button><a class="btn btn-outline" href="users.php">Reset</a></div>
</div></form>

<section class="panel">
  <?= render_table([
      ['Student', fn($r) => user_cell($r['name'], $r['email'], (int) $r['id'])],
      ['Student ID', fn($r) => e($r['student_id'])],
      ['Programme', fn($r) => e($r['programme'] ?: '—') . ($r['year_of_study'] ? '<small class="muted"> · Y' . (int) $r['year_of_study'] . '</small>' : '')],
      ['Status', fn($r) => status_badge($r['status'])],
      ['Joined', fn($r) => '<span class="muted nowrap">' . e(date('M j, Y', strtotime($r['created_at']))) . '</span>'],
      ['', fn($r) => '<div class="row-actions">'
          . ($r['status'] !== 'active' ? action_form('activate', (int) $r['id'], 'Activate', 'btn-success') : action_form('suspend', (int) $r['id'], 'Suspend', 'btn-outline', 'Suspend ' . $r['name'] . '?'))
          . '<a class="btn btn-sm btn-soft" href="users.php?id=' . (int) $r['id'] . '">View</a></div>'],
  ], $rows, 'No students match these filters.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
