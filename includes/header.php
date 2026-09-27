<?php
// App shell start (head, sidebar, top bar).
// Before including set: $pageTitle, $active, and (admin pages only) $area = 'admin'.
$area = $area ?? 'student';
$me = $area === 'admin' ? require_admin() : require_student();
$pageTitle = $pageTitle ?? 'Dashboard';
$active = $active ?? '';
$unreadNotifications = (int) fetch_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$me['id']]);
$unreadMessages = (int) fetch_value('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0', [$me['id']]);
$pendingRequests = (int) fetch_value("SELECT COUNT(*) FROM requests WHERE receiver_id = ? AND status = 'pending'", [$me['id']]);
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
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <meta name="base" content="<?= e(url()) ?>">
</head>
<body class="app<?= $area === 'admin' ? ' is-admin' : '' ?>">
<div class="shell">
  <?php require __DIR__ . ($area === 'admin' ? '/admin-sidebar.php' : '/sidebar.php'); ?>
  <div class="scrim" id="scrim"></div>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu', 22) ?></button>
      <?php if ($area === 'student'): ?>
        <form class="top-search" action="<?= url('find-skills.php') ?>" method="get" role="search">
          <?= icon('search', 16) ?>
          <input type="search" name="q" placeholder="Search for a skill, person or programme…" aria-label="Search">
        </form>
      <?php else: ?>
        <div class="top-search top-search-static"><?= icon('shield', 16) ?> <span>Administration</span></div>
      <?php endif; ?>

      <div class="notif" id="notif">
        <button class="icon-btn" id="notifBtn" aria-label="Notifications" aria-expanded="false" aria-controls="notifPanel">
          <?= icon('bell', 20) ?>
          <span class="badge-count" id="notifCount"<?= $unreadNotifications ? '' : ' hidden' ?>><?= $unreadNotifications > 99 ? '99+' : $unreadNotifications ?></span>
        </button>
        <div class="notif-panel" id="notifPanel" hidden>
          <div class="notif-head"><strong>Notifications</strong><button class="link-btn" id="notifReadAll" type="button">Mark all read</button></div>
          <div class="notif-list" id="notifList"><p class="muted small pad">Loading…</p></div>
          <a class="notif-foot" href="<?= url($area === 'admin' ? 'admin/dashboard.php' : 'notifications.php') ?>">View all</a>
        </div>
      </div>

      <a href="<?= url($area === 'admin' ? 'admin/dashboard.php' : 'profile.php') ?>" class="top-user" aria-label="Your account">
        <?= avatar($me) ?>
        <span class="top-user-text"><strong><?= e(first_name($me['name'])) ?></strong><small><?= e(ucwords(str_replace('_', ' ', $me['role']))) ?></small></span>
      </a>
    </header>

    <main class="content">
      <?php if ($area === 'student' && ($siteNotice = setting('site_notice')) !== ''): ?>
        <div class="alert alert-info"><?= icon('megaphone', 16) ?> <?= e($siteNotice) ?></div>
      <?php endif; ?>
      <?php foreach (take_flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
      <?php endforeach; ?>
