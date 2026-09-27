<?php
// Student sidebar. Expects $me, $active, $pendingRequests, $unreadMessages, $unreadNotifications.
$groups = [
    [
        ['dashboard', 'dashboard.php',   'Dashboard',         'dashboard', 0],
        ['find',      'find-skills.php', 'Find Skills',       'search',    0],
        ['matches',   'matches.php',     'My Matches',        'zap',       0],
        ['requests',  'requests.php',    'Requests',          'requests',  $pendingRequests],
        ['messages',  'messages.php',    'Messages',          'messages',  $unreadMessages],
        ['sessions',  'sessions.php',    'Learning Sessions', 'calendar',  0],
        ['reviews',   'reviews.php',     'Reviews',           'star',      0],
    ],
    [
        ['profile',       'profile.php',       'My Profile',    'user',     0],
        ['skills',        'skills.php',        'My Skills',     'skills',   0],
        ['notifications', 'notifications.php', 'Notifications',  'bell',     $unreadNotifications],
        ['settings',      'settings.php',      'Settings',      'settings', 0],
    ],
];
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <a href="<?= url('dashboard.php') ?>" class="brand">
    <span class="brand-mark"><?= icon('swap', 20) ?></span>
    <span class="brand-text"><b>CAMPUS SKILL<span class="brand-x">X</span></b><small>Skill Exchange</small></span>
  </a>

  <nav class="side-nav">
    <?php foreach ($groups as $i => $items): ?>
      <?php if ($i > 0): ?><hr class="side-sep"><?php endif; ?>
      <?php foreach ($items as [$key, $href, $text, $ico, $badge]): ?>
        <a href="<?= url($href) ?>" class="side-link<?= $active === $key ? ' active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
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
