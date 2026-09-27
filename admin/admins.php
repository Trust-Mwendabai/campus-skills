<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_super_admin();

$form = ['name' => '', 'email' => ''];
if (is_post()) {
    csrf_check();
    $do = post_str('do', 10);
    $uid = (int) ($_POST['id'] ?? 0);

    if ($do === 'create') {
        $form['name'] = post_str('name', 100);
        $form['email'] = mb_strtolower(post_str('email', 150));
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($form['name']) < 2 || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email address.');
        } elseif ($err = password_error($password)) {
            flash('error', $err);
        } elseif (fetch_value('SELECT 1 FROM users WHERE email = ?', [$form['email']])) {
            flash('error', 'An account with this email already exists.');
        } else {
            q("INSERT INTO users (student_id, name, email, password_hash, role, status) VALUES (?,?,?,?, 'admin', 'active')",
              ['ADM-' . strtoupper(bin2hex(random_bytes(3))), $form['name'], $form['email'], password_hash($password, PASSWORD_DEFAULT)]);
            admin_log($me['id'], 'create_admin', 'user', insert_id(), $form['email']);
            flash('success', 'Administrator account created.');
            redirect('admins.php');
        }
    } else {
        // Only regular administrators can be changed here — never the super administrator.
        $target = fetch_one("SELECT * FROM users WHERE id = ? AND role = 'admin'", [$uid]);
        if (!$target) {
            flash('error', 'Administrator not found.');
        } elseif ($do === 'suspend') {
            q("UPDATE users SET status = 'suspended' WHERE id = ?", [$uid]);
            admin_log($me['id'], 'suspend_admin', 'user', $uid, $target['email']);
            flash('success', 'Administrator suspended.');
        } elseif ($do === 'activate') {
            q("UPDATE users SET status = 'active' WHERE id = ?", [$uid]);
            admin_log($me['id'], 'activate_admin', 'user', $uid, $target['email']);
            flash('success', 'Administrator activated.');
        } elseif ($do === 'demote') {
            q("UPDATE users SET role = 'student' WHERE id = ?", [$uid]);
            admin_log($me['id'], 'demote_admin', 'user', $uid, $target['email']);
            flash('success', $target['name'] . ' is now a regular student account.');
        } elseif ($do === 'delete') {
            q('DELETE FROM users WHERE id = ?', [$uid]);
            admin_log($me['id'], 'delete_admin', 'user', null, $target['email']);
            flash('success', 'Administrator deleted.');
        }
        redirect('admins.php');
    }
}

$rows = fetch_all("SELECT id, name, email, role, status, last_login, created_at FROM users WHERE role IN ('admin','super_admin') ORDER BY role DESC, name");

$area = 'admin';
$active = 'admins';
$pageTitle = 'Admin accounts';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Admin accounts</h1><p>Only the super administrator can create or manage administrators.</p></div></div>

<div class="grid grid-main">
  <section class="panel">
    <?= render_table([
        ['Administrator', fn($r) => '<div class="cell-user">' . avatar(['id' => $r['id'], 'name' => $r['name']]) . '<div><b>' . e($r['name']) . '</b><small>' . e($r['email']) . '</small></div></div>'],
        ['Role', fn($r) => $r['role'] === 'super_admin' ? '<span class="badge badge-good">Super admin</span>' : '<span class="badge badge-neutral">Admin</span>'],
        ['Status', fn($r) => status_badge($r['status'])],
        ['Last login', fn($r) => '<span class="muted nowrap">' . ($r['last_login'] ? e(time_ago($r['last_login'])) : 'Never') . '</span>'],
        ['', fn($r) => $r['role'] === 'super_admin' ? '' : '<div class="row-actions">'
            . ($r['status'] === 'active' ? action_form('suspend', (int) $r['id'], 'Suspend', 'btn-outline', 'Suspend this administrator?') : action_form('activate', (int) $r['id'], 'Activate', 'btn-success'))
            . action_form('demote', (int) $r['id'], 'Make student', 'btn-outline', 'Remove admin rights from this account?')
            . action_form('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Permanently delete this administrator?') . '</div>'],
    ], $rows) ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Add administrator</h2></div>
    <form method="post" class="panel-body form">
      <?= csrf_field() ?><input type="hidden" name="do" value="create">
      <div class="field"><label for="name">Full name</label><input id="name" name="name" value="<?= e($form['name']) ?>" required></div>
      <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e($form['email']) ?>" required></div>
      <div class="field"><label for="password">Temporary password</label><input id="password" type="password" name="password" autocomplete="new-password" required><span class="hint">At least 8 characters with a letter and a number. Ask them to change it in Settings.</span></div>
      <div><button class="btn btn-primary">Create administrator</button></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
