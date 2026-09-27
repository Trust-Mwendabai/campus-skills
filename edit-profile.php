<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_student();

$errors = [];
$v = [
    'name' => $me['name'], 'phone' => (string) $me['phone'], 'programme' => (string) $me['programme'],
    'faculty' => (string) $me['faculty'], 'year' => (string) $me['year_of_study'], 'bio' => (string) $me['bio'],
];

if (is_post()) {
    csrf_check();
    foreach (array_keys($v) as $k) {
        $v[$k] = $k === 'bio' ? post_str($k, 1000) : post_str($k, 150);
    }
    if (mb_strlen($v['name']) < 2) $errors['name'] = 'Enter your full name.';
    if ($v['phone'] !== '' && !preg_match('/^\+?[0-9 ()\-]{7,20}$/', $v['phone'])) $errors['phone'] = 'Enter a valid phone number.';
    if ($v['programme'] === '') $errors['programme'] = 'Enter your programme.';
    if ($v['faculty'] === '') $errors['faculty'] = 'Enter your school or faculty.';
    if (!ctype_digit($v['year']) || (int) $v['year'] < 1 || (int) $v['year'] > 7) $errors['year'] = 'Select your year of study.';

    if (!$errors) {
        q('UPDATE users SET name = ?, phone = ?, programme = ?, faculty = ?, year_of_study = ?, bio = ? WHERE id = ?',
          [$v['name'], $v['phone'] ?: null, $v['programme'], $v['faculty'], (int) $v['year'], $v['bio'] ?: null, $me['id']]);
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }
}

$err = fn(string $k) => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$pageTitle = 'Edit profile';
$active = 'profile';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Edit profile</h1><p>This is what other students see on your profile.</p></div></div>

<form method="post" class="grid grid-main" novalidate>
  <?= csrf_field() ?>
  <section class="panel">
    <div class="panel-head"><h2>Personal details</h2></div>
    <div class="panel-body form">
      <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>"><label for="name">Full name</label><input id="name" name="name" value="<?= e($v['name']) ?>" required><?= $err('name') ?></div>
      <div class="form-row">
        <div class="field"><label>Student ID</label><input value="<?= e($me['student_id']) ?>" disabled><span class="hint">Contact an administrator to change your ID or email.</span></div>
        <div class="field"><label>Email</label><input value="<?= e($me['email']) ?>" disabled></div>
      </div>
      <div class="form-row">
        <div class="field<?= isset($errors['programme']) ? ' has-error' : '' ?>"><label for="programme">Programme</label><input id="programme" name="programme" value="<?= e($v['programme']) ?>" required><?= $err('programme') ?></div>
        <div class="field<?= isset($errors['faculty']) ? ' has-error' : '' ?>"><label for="faculty">School / Faculty</label><input id="faculty" name="faculty" value="<?= e($v['faculty']) ?>" required><?= $err('faculty') ?></div>
      </div>
      <div class="form-row">
        <div class="field<?= isset($errors['year']) ? ' has-error' : '' ?>"><label for="year">Year of study</label>
          <select id="year" name="year"><option value="">Select…</option><?php for ($y = 1; $y <= 7; $y++): ?><option value="<?= $y ?>"<?= $v['year'] === (string) $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?></select><?= $err('year') ?></div>
        <div class="field<?= isset($errors['phone']) ? ' has-error' : '' ?>"><label for="phone">Phone <span class="opt">(optional)</span></label><input id="phone" name="phone" type="tel" value="<?= e($v['phone']) ?>"><?= $err('phone') ?></div>
      </div>
      <div class="field"><label for="bio">About you</label><textarea id="bio" name="bio" maxlength="1000" placeholder="What are you studying, what are you passionate about, how do you like to learn?"><?= e($v['bio']) ?></textarea></div>
      <div class="row-actions"><button class="btn btn-primary">Save profile</button><a class="btn btn-outline" href="profile.php">Cancel</a></div>
    </div>
  </section>
  <aside class="panel"><div class="panel-body">
    <h3>Tips for a great profile</h3>
    <ul class="check-list">
      <li><?= icon('check', 14) ?> Mention what you enjoy teaching</li>
      <li><?= icon('check', 14) ?> Say how you like to learn</li>
      <li><?= icon('check', 14) ?> Add your weekly availability in <a href="settings.php#availability" style="color:var(--primary)">Settings</a></li>
    </ul>
  </div></aside>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
