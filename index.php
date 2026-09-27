<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$home = $user ? (is_admin_role($user) ? 'admin/dashboard.php' : 'dashboard.php') : null;

// Live platform statistics and categories. The page still renders if the database is unavailable.
$stats = null;
$categories = [];
try {
    $stats = [
        ['Students',            (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")],
        ['Skills',              (int) fetch_value('SELECT COUNT(*) FROM skills WHERE is_active = 1')],
        ['Matches',             (int) fetch_value('SELECT COUNT(*) FROM matches')],
        ['Completed exchanges', (int) fetch_value("SELECT COUNT(*) FROM matches WHERE status = 'completed'")],
    ];
    $categories = fetch_all(
        "SELECT c.name, c.slug, COUNT(s.id) AS skills FROM categories c LEFT JOIN skills s ON s.category_id = c.id AND s.is_active = 1
          WHERE c.is_active = 1 GROUP BY c.id ORDER BY skills DESC, c.name LIMIT 6");
} catch (Throwable $ex) {
    error_log($ex->getMessage());
}
$fmt = fn(int $n) => number_format($n);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> — Exchange skills. Learn from your campus.</title>
  <meta name="description" content="Connect with students who have the skills you want to learn, and share what you know in return.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>

<header class="site-header" id="top">
  <div class="container nav">
    <a href="#top" class="brand" aria-label="<?= e(APP_NAME) ?> home">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h13l-3-3M20 16H7l3 3"/></svg>
      </span>
      <span>Campus Skill<span class="brand-sub">X</span></span>
    </a>

    <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav-menu"><span></span><span></span><span></span></button>

    <nav id="nav-menu" class="nav-menu">
      <a href="#how">How it works</a>
      <a href="#skills">Skills</a>
      <a href="#matching">Smart matching</a>
      <a href="#faq">FAQ</a>
      <?php if ($user): ?>
        <a href="<?= url($home) ?>" class="btn btn-ghost">Hi, <?= e(first_name($user['name'])) ?></a>
        <form method="post" action="<?= url('logout.php') ?>" class="nav-form"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Log out</button></form>
      <?php else: ?>
        <a href="<?= url('login.php') ?>" class="btn btn-ghost">Log in</a>
        <a href="<?= url('register.php') ?>" class="btn btn-primary">Create account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main>
  <!-- Hero -->
  <section class="hero">
    <div class="container hero-grid">
      <div class="hero-copy">
        <p class="eyebrow">Peer-to-peer learning for students</p>
        <h1>Exchange Skills.<br><span class="accent">Learn From Your Campus.</span></h1>
        <p class="lead">Connect with students who have the skills you want and share what you know.</p>
        <div class="hero-cta">
          <a href="<?= url('find-skills.php') ?>" class="btn btn-primary btn-lg">Find a Skill Partner</a>
          <?php if ($user): ?>
            <a href="<?= url($home) ?>" class="btn btn-ghost btn-lg">Go to dashboard</a>
          <?php else: ?>
            <a href="<?= url('register.php') ?>" class="btn btn-ghost btn-lg">Create Account</a>
          <?php endif; ?>
        </div>
        <ul class="hero-tags" aria-label="Popular skills">
          <li>Python</li><li>Graphic design</li><li>Guitar</li><li>Public speaking</li><li>Excel</li>
        </ul>
      </div>

      <!-- Illustrative match card -->
      <div class="hero-visual" aria-hidden="true">
        <div class="card match-card">
          <div class="match-head"><span class="pill pill-good">92% match</span><span class="muted">Sample</span></div>
          <div class="match-people">
            <div class="person"><span class="avatar a1">A</span><div><strong>Amara</strong><small>Year 2 · Computer Science</small></div></div>
            <span class="swap-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h13l-3-3M20 16H7l3 3"/></svg></span>
            <div class="person"><span class="avatar a2">J</span><div><strong>Jonas</strong><small>Year 3 · Design</small></div></div>
          </div>
          <div class="match-skills">
            <div><small>Amara teaches</small><span class="chip chip-teach">Python</span><span class="chip chip-teach">SQL</span></div>
            <div><small>Jonas teaches</small><span class="chip chip-learn">Graphic design</span><span class="chip chip-learn">Video editing</span></div>
          </div>
          <span class="btn btn-primary btn-block">Request exchange</span>
        </div>
        <div class="float-chip fc1"><?= icon('palette', 16) ?> Graphic design</div>
        <div class="float-chip fc2"><?= icon('shield', 16) ?> Cybersecurity</div>
      </div>
    </div>
  </section>

  <!-- Live statistics -->
  <?php if ($stats): ?>
  <section class="strip" aria-label="Platform statistics">
    <div class="container strip-grid">
      <?php foreach ($stats as [$label, $value]): ?>
        <div><strong><?= $fmt($value) ?></strong><span><?= e($label) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- How it works -->
  <section class="section" id="how">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">How it works</p>
        <h2>From “I wish I could…” to your first exchange</h2>
      </div>
      <ol class="steps steps-6">
        <?php foreach ([
            ['Create your profile', 'Tell us about your programme, year and how you like to learn.'],
            ['Add skills you offer', 'List what you can teach a classmate, and at what level.'],
            ['Add skills you want', 'Choose what you’d love to learn next.'],
            ['Discover compatible students', 'Two-way matching ranks partners and explains why they fit.'],
            ['Send an exchange request', 'Propose a swap, then chat once it’s accepted.'],
            ['Learn and exchange skills', 'Schedule sessions, mark them complete and leave a review.'],
        ] as $i => [$title, $text]): ?>
          <li class="card step"><span class="step-num"><?= sprintf('%02d', $i + 1) ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- Skill categories -->
  <section class="section section-alt" id="skills">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">Browse by category</p>
        <h2>Whatever you want to learn, someone here can teach it</h2>
      </div>
      <div class="cat-grid">
        <?php foreach ($categories as $c): ?>
          <a class="card cat" href="<?= url('find-skills.php?category=' . urlencode($c['slug'])) ?>">
            <span class="cat-icon"><?= icon(CATEGORY_ICONS[$c['slug']] ?? 'sparkles', 28) ?></span>
            <h3><?= e($c['name']) ?></h3>
            <p><?= (int) $c['skills'] ?> skill<?= (int) $c['skills'] === 1 ? '' : 's' ?></p>
          </a>
        <?php endforeach; ?>
        <?php if (!$categories): ?><p class="muted">Categories will appear here once the platform is set up.</p><?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Matching -->
  <section class="section" id="matching">
    <div class="container split">
      <div>
        <p class="eyebrow">Smart matching</p>
        <h2>We find the partner whose skills fit yours</h2>
        <p class="lead-sm">Our matching looks at what you offer and what you want to learn, then ranks students where both sides benefit — and tells you exactly why.</p>
        <ul class="checks">
          <li>Skills offered and wanted, in both directions</li>
          <li>Skill levels, categories and weekly availability</li>
          <li>Learning preferences and previous exchange activity</li>
          <li>A plain-language explanation for every match</li>
        </ul>
        <a href="<?= url($user ? 'find-skills.php?view=recommended' : 'register.php') ?>" class="btn btn-primary">See my matches</a>
      </div>

      <div class="card score-card" aria-label="Example of how a match score is built">
        <h3>How a match score builds</h3>
        <div class="bar-row"><span>They teach what you want</span><div class="bar"><i style="--w:85%"></i></div><b>85%</b></div>
        <div class="bar-row"><span>You teach what they want</span><div class="bar"><i style="--w:70%"></i></div><b>70%</b></div>
        <div class="bar-row"><span>Availability overlap</span><div class="bar"><i style="--w:90%"></i></div><b>90%</b></div>
        <div class="bar-row"><span>Category affinity</span><div class="bar"><i style="--w:55%"></i></div><b>55%</b></div>
        <p class="muted small">Illustrative example. Real scores are calculated from your profile.</p>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section class="section section-alt">
    <div class="container">
      <div class="section-head">
        <p class="eyebrow">Everything in one place</p>
        <h2>Built for students who’d rather learn together</h2>
      </div>
      <div class="feat-grid">
        <article><h3>Skill profiles</h3><p>Showcase what you teach and what you’re curious about, with levels for each skill.</p></article>
        <article><h3>Exchange requests</h3><p>Send, accept and track requests with preferred times and learning formats.</p></article>
        <article><h3>Messaging &amp; sessions</h3><p>Chat with your match and schedule sessions with a place or meeting link.</p></article>
        <article><h3>Reviews &amp; history</h3><p>Rate every completed exchange and build a trusted record of what you’ve shared.</p></article>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="section" id="faq">
    <div class="container narrow">
      <div class="section-head"><p class="eyebrow">FAQ</p><h2>Common questions</h2></div>
      <details class="faq" open><summary>Do I need to be an expert to teach?</summary><p>No. If you can help someone get started, that’s enough. You choose your level for each skill.</p></details>
      <details class="faq"><summary>Does it cost anything?</summary><p>No. Skill exchanges are free — you trade knowledge, not money.</p></details>
      <details class="faq"><summary>What if I don’t have a skill someone wants?</summary><p>Every student knows something worth sharing. Add anything you can help with — the matching engine also recommends one-way partners.</p></details>
      <details class="faq"><summary>Is it safe to meet other students?</summary><p>Meet in public campus spaces or online, check ratings first, and report anything that doesn’t feel right — administrators review every report.</p></details>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta">
    <div class="container cta-inner">
      <h2>Ready to swap your first skill?</h2>
      <p>Create a free profile in under two minutes.</p>
      <a href="<?= url($home ?? 'register.php') ?>" class="btn btn-light"><?= $user ? 'Go to my dashboard' : 'Create my profile' ?></a>
    </div>
  </section>
</main>

<footer class="site-footer">
  <div class="container foot">
    <span>© <?= date('Y') ?> <?= e(APP_NAME) ?></span>
    <nav aria-label="Footer">
      <a href="#how">How it works</a>
      <a href="#faq">FAQ</a>
      <?php if ($user): ?><a href="<?= url($home) ?>">Dashboard</a><?php else: ?><a href="<?= url('login.php') ?>">Log in</a><a href="<?= url('register.php') ?>">Sign up</a><?php endif; ?>
    </nav>
  </div>
</footer>

<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
