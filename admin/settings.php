<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();
$isSuper = $me['role'] === 'super_admin';

if (is_post()) {
    csrf_check();
    $do = post_str('do', 12);

    if ($do === 'system') {
        if (!$isSuper) {
            http_response_code(403);
            exit('Only the super administrator can change system settings.');
        }
        save_setting('require_verification', empty($_POST['require_verification']) ? '0' : '1');
        save_setting('allow_registration', empty($_POST['allow_registration']) ? '0' : '1');
        save_setting('site_notice', post_str('site_notice', 200));
        admin_log($me['id'], 'update_settings', 'settings');
        flash('success', 'System settings saved.');
    } elseif ($do === 'password') {
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) {
            flash('error', 'Your current password is incorrect.');
        } elseif ($err = password_error($new)) {
            flash('error', $err);
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('error', 'The new passwords do not match.');
        } else {
            q('UPDATE users SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            $_SESSION['epoch'] = (int) fetch_value('SELECT session_epoch FROM users WHERE id = ?', [$me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed.');
        }
    }
    redirect('settings.php');
}

$area = 'admin';
$active = 'settings';
$pageTitle = 'Settings';
require __DIR__ . '/../includes/header.php';
$dis = $isSuper ? '' : ' disabled';
?>
<div class="page-head"><div><h1>Settings</h1><p>System configuration and your administrator account.</p></div></div>

<div class="grid grid-2" style="align-items:start">
  <section class="panel">
    <div class="panel-head"><h2>System settings</h2><?php if (!$isSuper): ?><span class="badge badge-neutral">Super admin only</span><?php endif; ?></div>
    <form method="post" class="panel-body form">
      <?= csrf_field() ?><input type="hidden" name="do" value="system">
      <label class="check"><input type="checkbox" name="allow_registration" value="1"<?= setting('allow_registration', '1') === '1' ? ' checked' : '' ?><?= $dis ?>> Allow new student registrations</label>
      <div><label class="check"><input type="checkbox" name="require_verification" value="1"<?= setting('require_verification', '0') === '1' ? ' checked' : '' ?><?= $dis ?>> Require account verification</label>
        <span class="hint">New accounts stay <i>pending</i> until an administrator activates them in Students.</span></div>
      <div class="field"><label for="site_notice">Site notice <span class="opt">(optional)</span></label><input id="site_notice" name="site_notice" maxlength="200" value="<?= e(setting('site_notice')) ?>"<?= $dis ?> placeholder="Shown as a banner to every signed-in student">
        <span class="hint">Leave empty to hide the banner.</span></div>
      <?php if ($isSuper): ?><div><button class="btn btn-primary">Save settings</button></div><?php endif; ?>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Change password</h2></div>
    <form method="post" class="panel-body form">
      <?= csrf_field() ?><input type="hidden" name="do" value="password">
      <div class="field"><label for="cp">Current password</label><input id="cp" type="password" name="current_password" autocomplete="current-password" required></div>
      <div class="form-row">
        <div class="field"><label for="np">New password</label><input id="np" type="password" name="new_password" autocomplete="new-password" required></div>
        <div class="field"><label for="np2">Confirm</label><input id="np2" type="password" name="confirm_password" autocomplete="new-password" required></div>
      </div>
      <div><button class="btn btn-primary">Change password</button></div>
    </form>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
