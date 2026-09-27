<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_student();
$id = $me['id'];

if (is_post()) {
    csrf_check();
    $do = post_str('do', 10);

    if ($do === 'add') {
        $name  = preg_replace('/\s+/', ' ', post_str('skill', 80));
        $type  = ($_POST['type'] ?? '') === 'want' ? 'want' : 'offer';
        $level = array_key_exists($_POST['level'] ?? '', LEVELS) ? $_POST['level'] : 'intermediate';
        $catId = (int) ($_POST['category_id'] ?? 0);

        if (!preg_match('/^[\p{L}\p{N} &+#.\/()\-]{2,80}$/u', $name)) {
            flash('error', 'Enter a skill name of 2–80 characters (letters, numbers and simple punctuation).');
        } else {
            $skill = fetch_one('SELECT id, is_active FROM skills WHERE name = ?', [$name]);
            if ($skill && !$skill['is_active']) {
                flash('error', 'That skill is not available.');
            } else {
                if (!$skill) {
                    if (!fetch_value('SELECT 1 FROM categories WHERE id = ? AND is_active = 1', [$catId])) {
                        flash('error', 'That skill is new — choose a category for it.');
                        redirect('skills.php');
                    }
                    q('INSERT INTO skills (category_id, name) VALUES (?, ?)', [$catId, $name]);
                    $skillId = insert_id();
                } else {
                    $skillId = (int) $skill['id'];
                }
                $opposite = $type === 'offer' ? 'want' : 'offer';
                if (fetch_value('SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ? AND type = ?', [$id, $skillId, $opposite])) {
                    flash('error', $type === 'offer' ? 'You already listed this skill as one you want to learn.' : 'You already offer this skill, so you cannot also want to learn it.');
                } elseif (fetch_value('SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ? AND type = ?', [$id, $skillId, $type])) {
                    flash('error', 'That skill is already on your list.');
                } else {
                    q('INSERT INTO user_skills (user_id, skill_id, type, level, description, experience, availability) VALUES (?,?,?,?,?,?,?)',
                      [$id, $skillId, $type, $level, post_str('description', 300) ?: null, post_str('experience', 150) ?: null, post_str('availability', 150) ?: null]);
                    flash('success', '"' . $name . '" added to ' . ($type === 'offer' ? 'skills you can teach' : 'skills you want to learn') . '.');
                }
            }
        }
    } elseif ($do === 'update') {
        $level = array_key_exists($_POST['level'] ?? '', LEVELS) ? $_POST['level'] : 'intermediate';
        q('UPDATE user_skills SET level = ?, description = ?, experience = ?, availability = ? WHERE id = ? AND user_id = ?',
          [$level, post_str('description', 300) ?: null, post_str('experience', 150) ?: null, post_str('availability', 150) ?: null, (int) ($_POST['id'] ?? 0), $id]);
        flash('success', 'Skill updated.');
    } elseif ($do === 'delete') {
        q('DELETE FROM user_skills WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $id]);
        flash('success', 'Skill removed.');
    }
    redirect('skills.php' . (($_POST['type'] ?? '') === 'want' ? '#want' : ''));
}

$mine = fetch_all(
    'SELECT us.*, s.name, c.name AS category FROM user_skills us JOIN skills s ON s.id = us.skill_id JOIN categories c ON c.id = s.category_id
      WHERE us.user_id = ? ORDER BY s.name', [$id]);
$offers = array_filter($mine, fn($r) => $r['type'] === 'offer');
$wants  = array_filter($mine, fn($r) => $r['type'] === 'want');
$categories = all_categories();
$allSkills = all_skills();

function skill_list(array $rows, string $type): void
{
    if (!$rows) {
        echo '<div class="empty">' . icon($type === 'offer' ? 'award' : 'skills', 36)
           . '<strong>Nothing here yet</strong>' . ($type === 'offer' ? 'Add a skill you could teach a classmate.' : 'Add something you would love to learn.') . '</div>';
        return;
    }
    foreach ($rows as $r) { ?>
      <details class="skill-card" style="display:block">
        <summary style="display:flex;gap:12px;align-items:center;cursor:pointer;list-style:none">
          <div class="grow">
            <div class="title" style="font-weight:600"><?= e($r['name']) ?> <?= level_badge($r['level']) ?></div>
            <div class="sub muted small"><?= e($r['category']) ?><?= $r['experience'] ? ' · ' . e($r['experience']) : '' ?></div>
          </div>
          <span class="link-btn"><?= icon('edit', 14) ?> Edit</span>
        </summary>
        <form method="post" class="form" style="margin-top:14px">
          <?= csrf_field() ?><input type="hidden" name="do" value="update"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="type" value="<?= e($type) ?>">
          <div class="form-row">
            <div class="field"><label>Level</label>
              <select name="level"><?php foreach (LEVELS as $k => $v): ?><option value="<?= $k ?>"<?= $r['level'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Experience</label><input name="experience" maxlength="150" value="<?= e($r['experience']) ?>" placeholder="e.g. 2 years, class projects"></div>
          </div>
          <div class="field"><label>Description</label><input name="description" maxlength="300" value="<?= e($r['description']) ?>"></div>
          <div class="field"><label>Availability for this skill</label><input name="availability" maxlength="150" value="<?= e($r['availability']) ?>" placeholder="e.g. Weekday evenings"></div>
          <div class="row-actions">
            <button class="btn btn-primary btn-sm">Save changes</button>
          </div>
        </form>
        <form method="post" data-confirm="Remove <?= e($r['name']) ?> from your list?" style="margin-top:8px">
          <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="type" value="<?= e($type) ?>">
          <button class="btn btn-danger btn-sm"><?= icon('trash', 14) ?>Remove</button>
        </form>
      </details>
    <?php }
}

$pageTitle = 'My Skills';
$active = 'skills';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>My Skills</h1><p>The skills you list here power your matches.</p></div>
  <button class="btn btn-primary" type="button" data-toggle="#addSkill"><?= icon('plus', 16) ?>Add skill</button>
</div>

<section class="panel mb <?= (!$mine) ? '' : 'hidden' ?>" id="addSkill">
  <div class="panel-head"><h2>Add a skill</h2></div>
  <form method="post" class="panel-body form">
    <?= csrf_field() ?><input type="hidden" name="do" value="add">
    <div class="form-row">
      <div class="field">
        <label for="skill">Skill</label>
        <input id="skill" name="skill" list="skillOptions" data-suggest="skillOptions" maxlength="80" required autocomplete="off" placeholder="Start typing, e.g. Python">
        <datalist id="skillOptions">
          <?php foreach ($allSkills as $s): ?><option value="<?= e($s['name']) ?>" label="<?= e($s['category']) ?>"></option><?php endforeach; ?>
        </datalist>
        <span class="hint">Pick a suggestion, or type a new skill and choose its category.</span>
      </div>
      <div class="field">
        <label for="category_id">Category <span class="opt">(for new skills)</span></label>
        <select id="category_id" name="category_id">
          <option value="">Choose a category…</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="field"><label for="type">Skill type</label>
        <select id="type" name="type"><option value="offer">I can teach</option><option value="want">I want to learn</option></select></div>
      <div class="field"><label for="level">Skill level</label>
        <select id="level" name="level"><?php foreach (LEVELS as $k => $v): ?><option value="<?= $k ?>"<?= $k === 'intermediate' ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <span class="hint">For skills you want, choose the level you're at now.</span></div>
    </div>
    <div class="field"><label for="description">Description <span class="opt">(optional)</span></label><input id="description" name="description" maxlength="300" placeholder="What can you help with, or what do you want to get out of it?"></div>
    <div class="form-row">
      <div class="field"><label for="experience">Experience <span class="opt">(optional)</span></label><input id="experience" name="experience" maxlength="150" placeholder="e.g. 2 years, final-year project"></div>
      <div class="field"><label for="availability">Availability <span class="opt">(optional)</span></label><input id="availability" name="availability" maxlength="150" placeholder="e.g. Weekday evenings"></div>
    </div>
    <div><button class="btn btn-primary" type="submit">Add skill</button></div>
  </form>
</section>

<div class="grid grid-2" style="align-items:start">
  <section class="panel" id="offer">
    <div class="panel-head"><h2>Skills I can teach <span class="badge badge-good"><?= count($offers) ?></span></h2></div>
    <?php skill_list($offers, 'offer'); ?>
  </section>
  <section class="panel" id="want">
    <div class="panel-head"><h2>Skills I want to learn <span class="badge badge-warn"><?= count($wants) ?></span></h2></div>
    <?php skill_list($wants, 'want'); ?>
  </section>
</div>

<?php if ($offers && $wants): ?>
  <div class="alert alert-info mt">You're all set. <a href="find-skills.php?view=recommended" style="font-weight:600;text-decoration:underline">See your recommended partners →</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
