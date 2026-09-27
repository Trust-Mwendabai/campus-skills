<?php
// Split-screen layout for login / register. Set $pageTitle before including.
$pageTitle = $pageTitle ?? 'Welcome';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body class="auth-body">
<div class="auth">
  <aside class="auth-side">
    <a href="<?= url('index.php') ?>" class="brand brand-light">
      <span class="brand-mark"><?= icon('swap', 20) ?></span>
      <span class="brand-text"><b>CAMPUS SKILL<span class="brand-x">X</span></b><small>Skill Exchange</small></span>
    </a>
    <div>
      <h2>Trade what you know for what you want to learn.</h2>
      <ul class="auth-points">
        <li><?= icon('zap', 18) ?> Two-way matching finds your ideal skill partner</li>
        <li><?= icon('messages', 18) ?> Chat and schedule sessions in one place</li>
        <li><?= icon('award', 18) ?> Reviews build trust across campus</li>
      </ul>
    </div>
    <small>Free for every student.</small>
  </aside>

  <section class="auth-main">
    <div class="auth-card">
