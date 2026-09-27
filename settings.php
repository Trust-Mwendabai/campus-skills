<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 20);

    if ($do === 'privacy') {
        $opt = fn(string $k) => ($_POST[$k] ?? '') === 'matches' ? 'matches' : 'everyone';
        q('UPDATE users SET profile_visibility = ?, skill_visibility = ?, message_permission = ? WHERE id = ?',
          [$opt('profile_visibility'), $opt('skill_visibility'), $opt('message_permission'), $id]);
        flash('success', 'Privacy settings saved.');
    } elseif ($do === 'preferences') {
        $method = array_key_exists($_POST['learning_method'] ?? '', METHODS) ? $_POST['learning_method'] : 'any';
        $dur = (int) ($_POST['session_duration'] ?? 60);
        $dur = in_array($dur, [30, 45, 60, 90, 120], true) ? $dur : 60;
        q('UPDATE users SET learning_method = ?, session_duration = ? WHERE id = ?', [$method, $dur, $id]);
        flash('success', 'Learning preferences saved.');
    } elseif ($do === 'notifications') {
        $on = fn(string $k) => empty($_POST[$k]) ? 0 : 1;
        q('UPDATE users SET notify_matches = ?, notify_requests = ?, notify_messages = ?, notify_sessions = ? WHERE id = ?',
          [$on('notify_matches'), $on('notify_requests'), $on('notify_messages'), $on('notify_sessions'), $id]);
        flash('success', 'Notification settings saved.');
    } elseif ($do === 'add_slot') {
        $day = (int) ($_POST['day'] ?? 0);
        $start = (string) ($_POST['start'] ?? '');
        $end = (string) ($_POST['end'] ?? '');
        if ($day < 1 || $day > 7 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $end <= $start) {
            flash('error', 'Choose a day and a valid time range (the end must be after the start).');
        } elseif ((int) fetch_value('SELECT COUNT(*) FROM availability WHERE user_id = ?', [$id]) >= 21) {
            flash('error', 'You can add up to 21 availability slots.');
        } elseif (fetch_value('SELECT 1 FROM availability WHERE user_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ?', [$id, $day, $end, $start])) {
            flash('error', 'That overlaps a slot you already have on ' . DAYS[$day] . '.');
        } else {
            q('INSERT INTO availability (user_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)', [$id, $day, $start, $end]);
            flash('success', 'Availability added.');
        }
        redirect('settings.php#availability');
    } elseif ($do === 'del_slot') {
        q('DELETE FROM availability WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $id]);
        redirect('settings.php#availability');
    } elseif ($do === 'password') {
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$id]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($err = password_error($new)) {
            flash('error', $err);
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('error', 'The new passwords do not match.');
        } else {
            q('UPDATE users SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
            $_SESSION['epoch'] = (int) fetch_value('SELECT session_epoch FROM users WHERE id = ?', [$id]);   // keep this device signed in
            session_regenerate_id(true);
            flash('success', 'Password changed. Other devices have been signed out.');
        }
        redirect('settings.php#security');
    } elseif ($do === 'delete_account') {
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$id]);
        if (!password_verify((string) ($_POST['confirm_delete_password'] ?? ''), $row['password_hash'])) {
            flash('error', 'Your password is incorrect, so your account was not deleted.');
            redirect('settings.php#danger');
        }
        q('DELETE FROM users WHERE id = ? AND role = ?', [$id, 'student']);   // related rows are removed by ON DELETE CASCADE
        logout_user();
        session_start();
        flash('success', 'Your account and all its data have been deleted.');
        redirect(url('login.php'));
    } elseif ($do === 'logout_others') {
        q('UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?', [$id]);
        $_SESSION['epoch'] = (int) fetch_value('SELECT session_epoch FROM users WHERE id = ?', [$id]);
        flash('success', 'All other sessions have been signed out.');
        redirect('settings.php#security');
    }
    redirect('settings.php');
}

$slots = fetch_all('SELECT * FROM availability WHERE user_id = ? ORDER BY day_of_week, start_time', [$id]);

$pageTitle = 'Settings';
$active = 'settings';
require __DIR__ . '/includes/header.php';
$sel = fn($a, $b) => $a === $b ? ' selected' : '';
$chk = fn($v) => $v ? ' checked' : '';
?>
<div class="page-head"><div><h1>Settings</h1><p>Manage your account, privacy, preferences and security.</p></div></div>

<div class="grid grid-2" style="align-items:start">
  <div class="grid" style="gap:24px">
    <section class="panel" id="account">
      <div class="panel-head"><h2>Account information</h2><a href="edit-profile.php">Edit</a></div>
      <div class="panel-body"><dl class="kv">
        <dt>Full name</dt><dd><?= e($me['name']) ?></dd>
        <dt>Email</dt><dd><?= e($me['email']) ?></dd>
        <dt>Phone</dt><dd><?= e($me['phone'] ?: '—') ?></dd>
        <dt>Student ID</dt><dd><?= e($me['student_id']) ?></dd>
        <dt>Programme</dt><dd><?= e($me['programme'] ?: '—') ?></dd>
        <dt>Year</dt><dd><?= $me['year_of_study'] ? 'Year ' . (int) $me['year_of_study'] : '—' ?></dd>
      </dl></div>
    </section>

    <section class="panel" id="privacy">
      <div class="panel-head"><h2>Privacy</h2></div>
      <form method="post" class="panel-body form"><?= csrf_field() ?><input type="hidden" name="do" value="privacy">
        <div class="field"><label for="pv">Profile visibility</label><select id="pv" name="profile_visibility">
          <option value="everyone"<?= $sel($me['profile_visibility'], 'everyone') ?>>Everyone on campus</option><option value="matches"<?= $sel($me['profile_visibility'], 'matches') ?>>Only my matches</option></select></div>
        <div class="field"><label for="sv">Skill visibility</label><select id="sv" name="skill_visibility">
          <option value="everyone"<?= $sel($me['skill_visibility'], 'everyone') ?>>Everyone on campus</option><option value="matches"<?= $sel($me['skill_visibility'], 'matches') ?>>Only my matches</option></select>
          <span class="hint">If either setting is limited to matches, you won't appear in search or recommendations.</span></div>
        <div class="field"><label for="mp">Who can message me</label><select id="mp" name="message_permission">
          <option value="matches"<?= $sel($me['message_permission'], 'matches') ?>>Only my matches</option><option value="everyone"<?= $sel($me['message_permission'], 'everyone') ?>>Any student</option></select></div>
        <div><button class="btn btn-primary btn-sm">Save privacy settings</button></div>
      </form>
    </section>

    <section class="panel" id="notifications">
      <div class="panel-head"><h2>Notifications</h2></div>
      <form method="post" class="panel-body form"><?= csrf_field() ?><input type="hidden" name="do" value="notifications">
        <label class="check"><input type="checkbox" name="notify_matches" value="1"<?= $chk($me['notify_matches']) ?>> New skill matches</label>
        <label class="check"><input type="checkbox" name="notify_requests" value="1"<?= $chk($me['notify_requests']) ?>> Exchange requests and responses</label>
        <label class="check"><input type="checkbox" name="notify_messages" value="1"<?= $chk($me['notify_messages']) ?>> New messages</label>
        <label class="check"><input type="checkbox" name="notify_sessions" value="1"<?= $chk($me['notify_sessions']) ?>> Session reminders and updates</label>
        <div><button class="btn btn-primary btn-sm">Save notification settings</button></div>
      </form>
    </section>
  </div>

  <div class="grid" style="gap:24px">
    <section class="panel" id="preferences">
      <div class="panel-head"><h2>Learning preferences</h2></div>
      <form method="post" class="panel-body form"><?= csrf_field() ?><input type="hidden" name="do" value="preferences">
        <div class="field"><label for="lm">Preferred learning method</label><select id="lm" name="learning_method">
          <?php foreach (METHODS as $k => $v): ?><option value="<?= $k ?>"<?= $sel($me['learning_method'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="sd">Preferred session duration</label><select id="sd" name="session_duration">
          <?php foreach ([30, 45, 60, 90, 120] as $d): ?><option value="<?= $d ?>"<?= $sel((int) $me['session_duration'], $d) ?>><?= $d ?> minutes</option><?php endforeach; ?></select></div>
        <div><button class="btn btn-primary btn-sm">Save preferences</button></div>
      </form>
    </section>

    <section class="panel" id="availability">
      <div class="panel-head"><h2>Weekly availability</h2></div>
      <div class="panel-body">
        <?php foreach ($slots as $s): ?>
          <div class="avail-row"><span><?= e(DAYS[$s['day_of_week']]) ?></span>
            <span><b><?= e(substr($s['start_time'], 0, 5)) ?> – <?= e(substr($s['end_time'], 0, 5)) ?></b>
              <form method="post" class="inline-form" style="margin-left:10px"><?= csrf_field() ?><input type="hidden" name="do" value="del_slot"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="link-btn" style="color:var(--danger)" aria-label="Remove slot">Remove</button></form></span></div>
        <?php endforeach; if (!$slots): ?><p class="muted small">No availability set. Adding it improves your matches.</p><?php endif; ?>
        <form method="post" class="form-row" style="grid-template-columns:1.3fr 1fr 1fr auto;margin-top:16px;align-items:end"><?= csrf_field() ?><input type="hidden" name="do" value="add_slot">
          <div class="field"><label for="day">Day</label><select id="day" name="day"><?php foreach (DAYS as $n => $d): ?><option value="<?= $n ?>"><?= $d ?></option><?php endforeach; ?></select></div>
          <div class="field"><label for="st">From</label><input id="st" type="time" name="start" required></div>
          <div class="field"><label for="en">To</label><input id="en" type="time" name="end" required></div>
          <button class="btn btn-soft" style="height:42px">Add</button>
        </form>
      </div>
    </section>

    <section class="panel" id="security">
      <div class="panel-head"><h2>Security</h2></div>
      <form method="post" class="panel-body form"><?= csrf_field() ?><input type="hidden" name="do" value="password">
        <div class="field"><label for="cp">Current password</label><input id="cp" type="password" name="current_password" autocomplete="current-password" required></div>
        <div class="form-row">
          <div class="field"><label for="np">New password</label><input id="np" type="password" name="new_password" autocomplete="new-password" required></div>
          <div class="field"><label for="np2">Confirm new password</label><input id="np2" type="password" name="confirm_password" autocomplete="new-password" required></div>
        </div>
        <div><button class="btn btn-primary btn-sm">Change password</button></div>
      </form>
      <form method="post" class="panel-body" style="border-top:1px solid var(--border)" data-confirm="Sign out of every other device?"><?= csrf_field() ?><input type="hidden" name="do" value="logout_others">
        <p class="muted small" style="margin-top:0">Signed in somewhere you don't recognise? End all other sessions.</p>
        <button class="btn btn-outline btn-sm">Log out from other sessions</button>
      </form>
    </section>

    <section class="panel" id="danger">
      <div class="panel-head"><h2>Delete account</h2></div>
      <form method="post" class="panel-body form" data-confirm="Permanently delete your account? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="do" value="delete_account">
        <p class="muted small" style="margin-top:0">This permanently removes your profile, skills, requests, matches, messages, sessions and reviews.</p>
        <div class="field"><label for="dp">Confirm with your password</label><input id="dp" type="password" name="confirm_delete_password" autocomplete="current-password" required></div>
        <div><button class="btn btn-danger btn-sm"><?= icon('trash', 14) ?>Delete my account</button></div>
      </form>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
