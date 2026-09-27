<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(url('dashboard.php'));
}

$errors = [];
$old = ['name' => '', 'student_id' => '', 'email' => '', 'phone' => '', 'programme' => '', 'faculty' => '', 'year' => ''];
$registrationOpen = setting('allow_registration', '1') === '1';

if (is_post()) {
    csrf_check();
    foreach ($old as $k => $_) {
        $old[$k] = post_str($k, 150);
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');
    $email    = mb_strtolower($old['email']);

    if (!$registrationOpen) {
        $errors['form'] = 'Registration is currently closed.';
    }
    if (mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Enter your full name.';
    }
    if (!preg_match('/^[A-Za-z0-9\-\/]{4,30}$/', $old['student_id'])) {
        $errors['student_id'] = 'Use 4–30 letters, numbers, dashes or slashes.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($old['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{7,20}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number.';
    }
    if ($old['programme'] === '') {
        $errors['programme'] = 'Enter your programme.';
    }
    if ($old['faculty'] === '') {
        $errors['faculty'] = 'Enter your school or faculty.';
    }
    if (!ctype_digit($old['year']) || (int) $old['year'] < 1 || (int) $old['year'] > 7) {
        $errors['year'] = 'Select your year of study.';
    }
    if ($err = password_error($password)) {
        $errors['password'] = $err;
    }
    if ($password !== $confirm) {
        $errors['confirm'] = 'Passwords do not match.';
    }
    if (!isset($errors['email']) && fetch_value('SELECT 1 FROM users WHERE email = ?', [$email])) {
        $errors['email'] = 'An account with this email already exists.';
    }
    if (!isset($errors['student_id']) && fetch_value('SELECT 1 FROM users WHERE student_id = ?', [$old['student_id']])) {
        $errors['student_id'] = 'This student ID is already registered.';
    }

    if (!$errors) {
        $status = setting('require_verification', '0') === '1' ? 'pending' : 'active';
        try {
            q('INSERT INTO users (student_id, name, email, phone, programme, faculty, year_of_study, password_hash, status) VALUES (?,?,?,?,?,?,?,?,?)',
              [$old['student_id'], $old['name'], $email, $old['phone'] ?: null, $old['programme'], $old['faculty'], (int) $old['year'],
               password_hash($password, PASSWORD_DEFAULT), $status]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {           // lost a race on a unique key
                $errors['form'] = 'That email or student ID is already registered.';
            } else {
                throw $ex;
            }
        }
        if (!$errors) {
            $id = insert_id();
            if ($status === 'pending') {
                flash('info', 'Account created. An administrator will verify it shortly — you can log in once it is approved.');
                redirect(url('login.php'));
            }
            login_user(['id' => $id]);
            flash('success', 'Welcome to ' . APP_NAME . '! Start by adding the skills you can teach and the ones you want to learn.');
            redirect(url('skills.php'));
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/auth_top.php';

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span class="field-error">' . e($errors[$key]) . '</span>' : '';
}
?>
<h1>Create your account</h1>
<p class="muted">Join your campus skill community. It takes about two minutes.</p>

<?php if (!$registrationOpen): ?>
  <div class="alert alert-warn">Registration is currently closed. Please check back later.</div>
<?php endif; ?>
<?php if (isset($errors['form'])): ?><div class="alert alert-error"><?= e($errors['form']) ?></div><?php endif; ?>

<form method="post" class="form" novalidate>
  <?= csrf_field() ?>
  <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
    <label for="name">Full name</label>
    <input id="name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required>
    <?= field_error($errors, 'name') ?>
  </div>

  <div class="form-row">
    <div class="field<?= isset($errors['student_id']) ? ' has-error' : '' ?>">
      <label for="student_id">Student ID</label>
      <input id="student_id" name="student_id" value="<?= e($old['student_id']) ?>" required>
      <?= field_error($errors, 'student_id') ?>
    </div>
    <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>">
      <label for="phone">Phone number <span class="opt">(optional)</span></label>
      <input id="phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" autocomplete="tel">
      <?= field_error($errors, 'phone') ?>
    </div>
  </div>

  <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
    <?= field_error($errors, 'email') ?>
  </div>

  <div class="form-row">
    <div class="field<?= isset($errors['programme']) ? ' has-error' : '' ?>">
      <label for="programme">Programme</label>
      <input id="programme" name="programme" value="<?= e($old['programme']) ?>" placeholder="e.g. Information Security" required>
      <?= field_error($errors, 'programme') ?>
    </div>
    <div class="field<?= isset($errors['faculty']) ? ' has-error' : '' ?>">
      <label for="faculty">School / Faculty</label>
      <input id="faculty" name="faculty" value="<?= e($old['faculty']) ?>" placeholder="e.g. School of Computing" required>
      <?= field_error($errors, 'faculty') ?>
    </div>
  </div>

  <div class="field<?= isset($errors['year']) ? ' has-error' : '' ?>">
    <label for="year">Year of study</label>
    <select id="year" name="year" required>
      <option value="">Select…</option>
      <?php for ($y = 1; $y <= 7; $y++): ?>
        <option value="<?= $y ?>"<?= $old['year'] === (string) $y ? ' selected' : '' ?>>Year <?= $y ?></option>
      <?php endfor; ?>
    </select>
    <?= field_error($errors, 'year') ?>
  </div>

  <div class="form-row">
    <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required>
      <?= field_error($errors, 'password') ?>
      <span class="hint">At least 8 characters, with a letter and a number.</span>
    </div>
    <div class="field<?= isset($errors['confirm']) ? ' has-error' : '' ?>">
      <label for="confirm">Confirm password</label>
      <input id="confirm" name="confirm" type="password" autocomplete="new-password" required>
      <?= field_error($errors, 'confirm') ?>
    </div>
  </div>

  <button class="btn btn-primary btn-block btn-lg" type="submit"<?= $registrationOpen ? '' : ' disabled' ?>>Create account</button>
</form>

<p class="auth-alt">Already have an account? <a href="<?= url('login.php') ?>">Log in</a></p>
<?php require __DIR__ . '/includes/auth_bottom.php'; ?>
