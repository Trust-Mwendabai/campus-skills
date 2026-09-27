<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/exchange.php';
$me = require_student();
$id = $me['id'];

$view = ($_GET['view'] ?? '') === 'recommended' ? 'recommended' : 'browse';
$f = [
    'q'         => trim((string) ($_GET['q'] ?? '')),
    'category'  => trim((string) ($_GET['category'] ?? '')),
    'level'     => array_key_exists($_GET['level'] ?? '', LEVELS) ? $_GET['level'] : '',
    'programme' => trim((string) ($_GET['programme'] ?? '')),
    'year'      => ctype_digit((string) ($_GET['year'] ?? '')) ? (int) $_GET['year'] : 0,
    'day'       => (int) ($_GET['day'] ?? 0),
    'method'    => array_key_exists($_GET['method'] ?? '', METHODS) && ($_GET['method'] ?? '') !== 'any' ? $_GET['method'] : '',
    'rating'    => (int) ($_GET['rating'] ?? 0),
    'sort'      => in_array($_GET['sort'] ?? '', ['match', 'rating', 'newest'], true) ? $_GET['sort'] : 'match',
];
$hasFilters = (bool) array_filter([$f['q'], $f['category'], $f['level'], $f['programme'], $f['year'], $f['day'], $f['method'], $f['rating']]);
$like = fn(string $s) => '%' . addcslashes($s, '%_\\') . '%';

$results = [];
$recommended = [];
if ($view === 'recommended') {
    $recommended = find_matches($id, 24);
} else {
    [$disc, $params] = discoverable_clause($id);
    $where = [$disc];

    if ($f['q'] !== '') {
        $where[] = "(u.name LIKE ? OR u.programme LIKE ? OR EXISTS (SELECT 1 FROM user_skills x JOIN skills sx ON sx.id = x.skill_id
                     WHERE x.user_id = u.id AND x.type = 'offer' AND sx.name LIKE ?))";
        array_push($params, $like($f['q']), $like($f['q']), $like($f['q']));
    }
    if ($f['category'] !== '' || $f['level'] !== '') {
        $sub = "EXISTS (SELECT 1 FROM user_skills x JOIN skills sx ON sx.id = x.skill_id JOIN categories cx ON cx.id = sx.category_id
                 WHERE x.user_id = u.id AND x.type = 'offer' AND sx.is_active = 1";
        if ($f['category'] !== '') { $sub .= ' AND cx.slug = ?'; $params[] = $f['category']; }
        if ($f['level'] !== '')    { $sub .= ' AND x.level = ?'; $params[] = $f['level']; }
        $where[] = $sub . ')';
    }
    if ($f['programme'] !== '') { $where[] = 'u.programme LIKE ?'; $params[] = $like($f['programme']); }
    if ($f['year'])             { $where[] = 'u.year_of_study = ?'; $params[] = $f['year']; }
    if ($f['day'] >= 1 && $f['day'] <= 7) { $where[] = 'EXISTS (SELECT 1 FROM availability av WHERE av.user_id = u.id AND av.day_of_week = ?)'; $params[] = $f['day']; }
    if ($f['method'])           { $where[] = "u.learning_method IN ('any', ?)"; $params[] = $f['method']; }
    if ($f['rating'] >= 1)      { $where[] = '(SELECT AVG(score) FROM ratings r WHERE r.reviewee_id = u.id AND r.is_hidden = 0) >= ?'; $params[] = $f['rating']; }

    $rows = fetch_all('SELECT u.id FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 300', $params);
    if ($rows) {
        $bundles = load_bundles(array_merge([$id], array_column($rows, 'id')));
        $mine = $bundles[$id];
        unset($bundles[$id]);
        foreach ($bundles as $uid => $b) {
            $results[] = ['b' => $b, 'm' => score_pair($mine, $b), 'created' => $uid];
        }
        usort($results, match ($f['sort']) {
            'rating' => fn($x, $y) => [$y['b']['avg_rating'] ?? 0, $y['b']['rating_count']] <=> [$x['b']['avg_rating'] ?? 0, $x['b']['rating_count']],
            'newest' => fn($x, $y) => $y['created'] <=> $x['created'],
            default  => fn($x, $y) => $y['m']['score'] <=> $x['m']['score'],
        });
    }
}

[$page, $pages, $offset] = paginate(count($results), 12);
$pageResults = array_slice($results, $offset, 12);

// Relationship info for the cards on this page
$matchedIds = array_column(fetch_all("SELECT IF(user_a = ?, user_b, user_a) AS pid FROM matches WHERE status <> 'cancelled' AND (user_a = ? OR user_b = ?)", [$id, $id, $id]), 'pid');

$categories = all_categories();
$pageTitle = 'Find Skills';
$active = 'find';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Find Skills</h1><p>Discover students who can teach what you want to learn.</p></div>
</div>

<div class="tabs" role="tablist">
  <a class="tab<?= $view === 'browse' ? ' active' : '' ?>" href="find-skills.php">Browse all</a>
  <a class="tab<?= $view === 'recommended' ? ' active' : '' ?>" href="find-skills.php?view=recommended"><?= icon('zap', 14) ?> Recommended for you</a>
</div>

<?php if ($view === 'recommended'): ?>
  <?php if ($recommended): ?>
    <p class="muted">Ranked by two-way compatibility: what they can teach you, what you can teach them, availability and more.</p>
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
      <?php foreach ($recommended as $m) echo render_match_card($m); ?>
    </div>
  <?php else: ?>
    <div class="panel empty">
      <?= icon('zap', 44) ?>
      <strong>No recommendations yet</strong>
      Add the skills you can teach and want to learn — we'll match you with compatible students.
      <div><a class="btn btn-primary btn-sm" href="skills.php">Add skills</a></div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <form class="panel mb" method="get">
    <div class="filter-bar">
      <div style="flex:2 1 240px"><label for="q">Search</label><input id="q" name="q" type="search" value="<?= e($f['q']) ?>" placeholder="Skill, name or programme" data-suggest="skillList" autocomplete="off"><datalist id="skillList"></datalist></div>
      <div><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= e($c['slug']) ?>"<?= $f['category'] === $c['slug'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div><label for="level">Skill level</label><select id="level" name="level"><option value="">Any level</option>
        <?php foreach (LEVELS as $k => $v): ?><option value="<?= $k ?>"<?= $f['level'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div><label for="programme">Programme</label><input id="programme" name="programme" value="<?= e($f['programme']) ?>" placeholder="Any"></div>
      <div><label for="year">Year</label><select id="year" name="year"><option value="">Any</option>
        <?php for ($y = 1; $y <= 7; $y++): ?><option value="<?= $y ?>"<?= $f['year'] === $y ? ' selected' : '' ?>>Year <?= $y ?></option><?php endfor; ?></select></div>
      <div><label for="day">Available on</label><select id="day" name="day"><option value="">Any day</option>
        <?php foreach (DAYS as $n => $d): ?><option value="<?= $n ?>"<?= $f['day'] === $n ? ' selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
      <div><label for="method">Format</label><select id="method" name="method"><option value="">Any</option>
        <?php foreach (METHODS as $k => $v): if ($k === 'any') continue; ?><option value="<?= $k ?>"<?= $f['method'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div><label for="rating">Min. rating</label><select id="rating" name="rating"><option value="">Any</option>
        <?php foreach ([4, 3, 2] as $r): ?><option value="<?= $r ?>"<?= $f['rating'] === $r ? ' selected' : '' ?>><?= $r ?>+ stars</option><?php endforeach; ?></select></div>
      <div><label for="sort">Sort by</label><select id="sort" name="sort">
        <option value="match"<?= $f['sort'] === 'match' ? ' selected' : '' ?>>Best match</option>
        <option value="rating"<?= $f['sort'] === 'rating' ? ' selected' : '' ?>>Highest rated</option>
        <option value="newest"<?= $f['sort'] === 'newest' ? ' selected' : '' ?>>Newest</option></select></div>
      <div style="flex:0 0 auto;flex-direction:row;gap:8px"><button class="btn btn-primary" type="submit">Search</button><?php if ($hasFilters): ?><a class="btn btn-outline" href="find-skills.php">Clear</a><?php endif; ?></div>
    </div>
  </form>

  <p class="muted small"><?= count($results) ?> student<?= count($results) === 1 ? '' : 's' ?> found</p>

  <?php if ($pageResults): ?>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(320px,1fr))">
    <?php foreach ($pageResults as ['b' => $b, 'm' => $m]):
        $meta = trim(($b['programme'] ?? '') . ($b['year_of_study'] ? ' · Year ' . (int) $b['year_of_study'] : ''), ' ·');
        $isMatched = in_array($b['id'], $matchedIds); ?>
      <article class="card match">
        <div class="match-top">
          <?= avatar($b, 'lg') ?>
          <div class="grow">
            <a class="match-name" href="profile.php?id=<?= (int) $b['id'] ?>"><?= e($b['name']) ?></a>
            <div class="muted small"><?= e($meta ?: 'Student') ?></div>
            <?= stars($b['avg_rating'], $b['rating_count']) ?>
          </div>
          <?= score_ring($m['score']) ?>
        </div>
        <div class="match-skills">
          <div><small>Can teach</small><?= skill_chips(array_column($b['offers'], 'name'), 'chip-teach', 4) ?></div>
          <div><small>Wants to learn</small><?= skill_chips(array_column($b['wants'], 'name'), 'chip-learn', 3) ?></div>
        </div>
        <?php if ($m['teaches_me'] || $m['i_teach_them']): ?><p class="why"><b>Why:</b> <?= e($m['why']) ?></p><?php endif; ?>
        <div class="match-foot">
          <a class="btn btn-sm btn-outline" href="profile.php?id=<?= (int) $b['id'] ?>">View profile</a>
          <?php if ($isMatched): ?>
            <a class="btn btn-sm btn-soft push" href="messages.php?with=<?= (int) $b['id'] ?>">Message</a>
          <?php else: ?>
            <a class="btn btn-sm btn-primary push" href="profile.php?id=<?= (int) $b['id'] ?>&amp;request=1">Request exchange</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pager($page, $pages) ?>
  <?php else: ?>
    <div class="panel empty">
      <?= icon('search', 44) ?>
      <strong>No students match your search</strong>
      Try removing a filter or searching for a different skill.
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
