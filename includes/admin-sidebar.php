<?php
// Administrator sidebar. Expects $me and $active.
$sideOpenReports = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE status IN ('open','investigating')");
$sidePendingAccounts = (int) fetch_value("SELECT COUNT(*) FROM users WHERE status = 'pending'");

$groups = [
    [
        ['dashboard',     'dashboard.php',     'Dashboard',     'dashboard', 0],
        ['users',         'users.php',         'Students',      'users',     $sidePendingAccounts],
        ['skills',        'skills.php',        'Skills',        'skills',    0],
        ['categories',    'categories.php',    'Categories',    'tag',       0],
        ['requests',      'requests.php',      'Requests',      'requests',  0],
        ['matches',       'matches.php',       'Matches',       'zap',       0],
        ['messages',      'messages.php',      'Messages',      'messages',  0],
        ['sessions',      'sessions.php',      'Sessions',      'calendar',  0],
        ['reviews',       'reviews.php',       'Reviews',       'star',      0],
        ['reports',       'reports.php',       'Reports',       'flag',      $sideOpenReports],
        ['announcements', 'announcements.php', 'Announcements', 'megaphone', 0],
        ['analytics',     'analytics.php',     'Analytics',     'chart',     0],
    ],
    array_filter([
        $me['role'] === 'super_admin' ? ['admins', 'admins.php', 'Admin Accounts', 'shield', 0] : null,
        ['settings', 'settings.php', 'Settings', 'settings', 0],
    ]),
];
?>
<aside class="sidebar" id="sidebar" aria-label="Administration navigation">
  <a href="<?= url('admin/dashboard.php') ?>" class="brand">
    <span class="brand-mark"><?= icon('shield', 20) ?></span>
    <span class="brand-text"><b>CAMPUS SKILL<span class="brand-x">X</span></b><small>Administration</small></span>
  </a>

  <nav class="side-nav">
    <?php foreach ($groups as $i => $items): ?>
      <?php if ($i > 0): ?><hr class="side-sep"><?php endif; ?>
      <?php foreach ($items as [$key, $href, $text, $ico, $badge]): ?>
        <a href="<?= url('admin/' . $href) ?>" class="side-link<?= $active === $key ? ' active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
          <?= icon($ico) ?><span><?= e($text) ?></span>
          <?php if ($badge > 0): ?><span class="count"><?= $badge > 99 ? '99+' : $badge ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <form method="post" action="<?= url('logout.php') ?>" class="side-foot">
    <?= csrf_field() ?>
    <button class="side-link side-logout" type="submit"><?= icon('logout') ?><span>Logout</span></button>
  </form>
</aside>
