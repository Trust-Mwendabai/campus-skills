<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_ui.php';
$me = require_admin();

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'category';
}

if (is_post()) {
    csrf_check();
    $do = post_str('do', 12);
    $cid = (int) ($_POST['id'] ?? 0);
    $name = preg_replace('/\s+/', ' ', post_str('name', 80));

    if ($do === 'add' || $do === 'rename') {
        if (!preg_match('/^[\p{L}\p{N} &+#.\/()\-]{2,80}$/u', $name)) {
            flash('error', 'Enter a category name of 2–80 characters.');
        } elseif (fetch_value('SELECT 1 FROM categories WHERE name = ? AND id <> ?', [$name, $cid])) {
            flash('error', 'A category with that name already exists.');
        } elseif ($do === 'add') {
            $slug = slugify($name);
            $base = $slug; $i = 2;
            while (fetch_value('SELECT 1 FROM categories WHERE slug = ?', [$slug])) { $slug = $base . '-' . $i++; }
            q('INSERT INTO categories (name, slug) VALUES (?, ?)', [$name, $slug]);
            admin_log($me['id'], 'add_category', 'category', insert_id(), $name);
            flash('success', 'Category added.');
        } else {
            q('UPDATE categories SET name = ? WHERE id = ?', [$name, $cid]);   // slug stays stable so links keep working
            admin_log($me['id'], 'rename_category', 'category', $cid, $name);
            flash('success', 'Category renamed.');
        }
    } elseif ($do === 'toggle') {
        q('UPDATE categories SET is_active = 1 - is_active WHERE id = ?', [$cid]);
        admin_log($me['id'], 'toggle_category', 'category', $cid);
        flash('success', 'Category visibility changed.');
    } elseif ($do === 'delete') {
        if ((int) fetch_value('SELECT COUNT(*) FROM skills WHERE category_id = ?', [$cid]) > 0) {
            flash('error', 'Move or delete this category\'s skills first, or deactivate the category instead.');
        } else {
            q('DELETE FROM categories WHERE id = ?', [$cid]);
            admin_log($me['id'], 'delete_category', 'category', null, $name);
            flash('success', 'Category deleted.');
        }
    }
    redirect('categories.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$rows = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM skills WHERE category_id = c.id) AS skills FROM categories c ORDER BY c.name');

$area = 'admin';
$active = 'categories';
$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Categories</h1><p>Group skills so students can browse and filter them.</p></div></div>

<section class="panel mb">
  <div class="panel-head"><h2>Add a category</h2></div>
  <form method="post" class="panel-body form-row" style="grid-template-columns:1fr auto;align-items:end">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="field"><label for="name">Category name</label><input id="name" name="name" maxlength="80" required></div>
    <button class="btn btn-primary" style="height:42px">Add category</button>
  </form>
</section>

<section class="panel">
  <?= render_table([
      ['Category', function ($r) use ($editId) {
          if ((int) $r['id'] !== $editId) return '<b>' . e($r['name']) . '</b>';
          return '<form method="post" class="inline-form" style="display:flex;gap:8px">' . csrf_field() . '<input type="hidden" name="do" value="rename"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
               . '<input name="name" value="' . e($r['name']) . '" maxlength="80" required style="padding:.4rem .6rem;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text)">'
               . '<button class="btn btn-sm btn-primary">Save</button><a class="btn btn-sm btn-outline" href="categories.php">Cancel</a></form>';
      }],
      ['Slug', fn($r) => '<code>' . e($r['slug']) . '</code>'],
      ['Skills', fn($r) => (int) $r['skills']],
      ['Status', fn($r) => $r['is_active'] ? '<span class="badge badge-good">Active</span>' : '<span class="badge badge-bad">Hidden</span>'],
      ['', fn($r) => '<div class="row-actions"><a class="btn btn-sm btn-soft" href="?edit=' . (int) $r['id'] . '">Rename</a>'
          . action_form('toggle', (int) $r['id'], $r['is_active'] ? 'Hide' : 'Show')
          . ((int) $r['skills'] === 0 ? action_form('delete', (int) $r['id'], 'Delete', 'btn-danger', 'Delete category "' . $r['name'] . '"?', ['name' => $r['name']]) : '') . '</div>'],
  ], $rows) ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
