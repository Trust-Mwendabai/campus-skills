<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

$validName = fn(string $n) => (bool) preg_match('/^[\p{L}\p{N} &+#.\/()\-]{2,80}$/u', $n);

if (is_post()) {
    csrf_check();
    $do = post_str('do', 12);
    $sid = (int) ($_POST['id'] ?? 0);

    if ($do === 'add') {
        $name = preg_replace('/\s+/', ' ', post_str('name', 80));
        $cat = (int) ($_POST['category_id'] ?? 0);
        if (!$validName($name) || !fetch_value('SELECT 1 FROM categories WHERE id = ?', [$cat])) {
            flash('error', 'Enter a valid skill name and choose a category.');
        } elseif (fetch_value('SELECT 1 FROM skills WHERE name = ?', [$name])) {
            flash('error', 'That skill already exists.');
        } else {
            q('INSERT INTO skills (category_id, name) VALUES (?, ?)', [$cat, $name]);
            admin_log($me['id'], 'add_skill', 'skill', insert_id(), $name);
            flash('success', 'Skill added.');
        }
    } elseif ($do === 'edit') {
        $name = preg_replace('/\s+/', ' ', post_str('name', 80));
        $cat = (int) ($_POST['category_id'] ?? 0);
        $dupe = fetch_value('SELECT 1 FROM skills WHERE name = ? AND id <> ?', [$name, $sid]);
        if (!$validName($name) || $dupe || !fetch_value('SELECT 1 FROM categories WHERE id = ?', [$cat])) {
            flash('error', $dupe ? 'Another skill already uses that name.' : 'Enter a valid skill name and category.');
        } else {
            q('UPDATE skills SET name = ?, category_id = ? WHERE id = ?', [$name, $cat, $sid]);
            admin_log($me['id'], 'edit_skill', 'skill', $sid, $name);
            flash('success', 'Skill updated.');
        }
    } elseif ($do === 'toggle') {
        q('UPDATE skills SET is_active = 1 - is_active WHERE id = ?', [$sid]);
        admin_log($me['id'], 'toggle_skill', 'skill', $sid);
        flash('success', 'Skill visibility changed.');
    } elseif ($do === 'delete') {
        $name = fetch_value('SELECT name FROM skills WHERE id = ?', [$sid]);
        q('DELETE FROM skills WHERE id = ?', [$sid]);
        admin_log($me['id'], 'delete_skill', 'skill', null, (string) $name);
        flash('success', 'Skill deleted.');
    }
    redirect('skills.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$catFilter = (int) ($_GET['category'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);
$where = ['1=1'];
$params = [];
if ($search !== '') { $where[] = 's.name LIKE ?'; $params[] = '%' . addcslashes($search, '%_\\') . '%'; }
if ($catFilter) { $where[] = 's.category_id = ?'; $params[] = $catFilter; }
$w = implode(' AND ', $where);

$total = (int) fetch_value("SELECT COUNT(*) FROM skills s WHERE $w", $params);
[$page, $pages, $offset] = paginate($total, 20);
$rows = fetch_all(
    "SELECT s.*, c.name AS category,
            (SELECT COUNT(*) FROM user_skills WHERE skill_id = s.id AND type = 'offer') AS offers,
            (SELECT COUNT(*) FROM user_skills WHERE skill_id = s.id AND type = 'want') AS wants
       FROM skills s JOIN categories c ON c.id = s.category_id WHERE $w ORDER BY s.name LIMIT 20 OFFSET $offset", $params);
$cats = all_categories(false);
$catOptions = fn(int $sel) => implode('', array_map(fn($c) => '<option value="' . (int) $c['id'] . '"' . ((int) $c['id'] === $sel ? ' selected' : '') . '>' . e($c['name']) . '</option>', $cats));

$area = 'admin';
$active = 'skills';
$pageTitle = 'Skills';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Skills</h1><p><?= $total ?> skill<?= $total === 1 ? '' : 's' ?>. Deactivate skills that are inappropriate — they disappear from search but keep their history.</p></div></div>

<section class="panel mb">
  <div class="panel-head"><h2>Add a skill</h2></div>
  <form method="post" class="panel-body form-row" style="grid-template-columns:2fr 2fr auto;align-items:end">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="field"><label for="name">Skill name</label><input id="name" name="name" maxlength="80" required></div>
    <div class="field"><label for="cat">Category</label><select id="cat" name="category_id" required><?= $catOptions(0) ?></select></div>
    <button class="btn btn-primary" style="height:42px">Add skill</button>
  </form>
</section>

<form class="panel mb" method="get"><div class="filter-bar">
  <div style="flex:2 1 220px"><label for="q">Search</label><input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Skill name"></div>
  <div><label for="cf">Category</label><select id="cf" name="category"><option value="">All</option><?= $catOptions($catFilter) ?></select></div>
  <div style="flex:0 0 auto;flex-direction:row;gap:8px"><button class="btn btn-primary">Filter</button><a class="btn btn-outline" href="skills.php">Reset</a></div>
</div></form>

<section class="panel">
  <?= render_table([
      ['Skill', function ($r) use ($editId, $catOptions) {
          if ((int) $r['id'] !== $editId) {
              return '<b>' . e($r['name']) . '</b>';
          }
          return '<form method="post" class="inline-form" style="display:flex;gap:8px">' . csrf_field() . '<input type="hidden" name="do" value="edit"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
               . '<input name="name" value="' . e($r['name']) . '" maxlength="80" required style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text)">'
               . '<select name="category_id" style="padding:.4rem;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text)">' . $catOptions((int) $r['category_id']) . '</select>'
               . '<button class="btn btn-sm btn-primary">Save</button><a class="btn btn-sm btn-outline" href="skills.php">Cancel</a></form>';
      }],
      ['Category', fn($r) => e($r['category'])],
      ['Teachers', fn($r) => (int) $r['offers']],
      ['Learners', fn($r) => (int) $r['wants']],
      ['Status', fn($r) => $r['is_active'] ? '<span class="badge badge-good">Active</span>' : '<span class="badge badge-bad">Deactivated</span>'],
      ['', fn($r) => '<div class="row-actions"><a class="btn btn-sm btn-soft" href="?edit=' . (int) $r['id'] . '">Edit</a>'
          . action_form('toggle', (int) $r['id'], $r['is_active'] ? 'Deactivate' : 'Activate')
          . action_form('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Delete "' . $r['name'] . '"? It will be removed from every profile.') . '</div>'],
  ], $rows, 'No skills found.') ?>
</section>
<?= pager($page, $pages) ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
