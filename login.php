<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(url(is_admin_role($u) ? 'admin/dashboard.php' : 'dashboard.php'));
}

$error = null;
$identifier = '';
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
// Only allow redirects to known local pages.
$next = (preg_match('/^[a-z][a-z-]*\.php$/', $next) && is_file(__DIR__ . '/' . $next)) ? $next : '';

if (is_post()) {
    csrf_check();
    $identifier = post_str('identifier', 150);
    $password = (string) ($_POST['password'] ?? '');
    $key = mb_strtolower($identifier);

    if ($identifier === '' || $password === '') {
        $error = 'Enter your email or student ID and your password.';
    } elseif (login_blocked($key)) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = fetch_one('SELECT * FROM users WHERE email = ? OR student_id = ? LIMIT 1', [$key, $identifier]);
        // Verify against a dummy hash when the user is unknown so timing does not reveal which accounts exist.
        $hash = $user['password_hash'] ?? '$2y$10$tmILr11l.mQyJhdGg2Yr.OvQ1GSvFg6zrthHXfDEL1WhQBTXLMJBy';
        $valid = password_verify($password, $hash) && $user;

        if (!$valid) {
            record_failed_login($key);
            $error = 'Incorrect email/student ID or password.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Your account is awaiting verification by an administrator.';
        } elseif ($user['status'] === 'suspended') {
            $error = 'This account has been suspended. Please contact the administrators.';
        } else {
            clear_failed_logins($key);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_user($user);
            if (is_admin_role($user)) {
                redirect(url('admin/dashboard.php'));
            }
            redirect(url($next ?: 'dashboard.php'));
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/auth_top.php';
?>
<h1>Welcome back</h1>
<p class="muted">Log in to continue exchanging skills.</p>

<?php foreach (take_flashes() as [$type, $msg]): ?>
  <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach; ?>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="form" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="field">
    <label for="identifier">Email or student ID</label>
    <input id="identifier" name="identifier" value="<?= e($identifier) ?>" autocomplete="username" autofocus required>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
</form>

<p class="auth-alt">New to <?= e(APP_NAME) ?>? <a href="<?= url('register.php') ?>">Create an account</a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
