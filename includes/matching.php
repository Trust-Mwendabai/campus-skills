<?php
// Intelligent matching: ranks students by how well their skills complement yours.
//
// Score (0-100):
//   35  they teach what I want to learn              (skill coverage)
//   25  I teach what they want to learn              (skill coverage)
//   15  overlapping weekly availability
//   10  category affinity (related fields even without an exact skill match)
//    5  skill level fit (teacher's level >= learner's level)
//    5  compatible learning format
//    5  track record (average rating + completed exchanges)

/** SQL fragment: students this viewer may discover (active, public, or already matched). */
function discoverable_clause(int $viewerId, string $a = 'u'): array
{
    return [
        "($a.status = 'active' AND $a.role = 'student' AND $a.id <> ?
          AND (($a.profile_visibility = 'everyone' AND $a.skill_visibility = 'everyone')
               OR EXISTS (SELECT 1 FROM matches mm WHERE mm.status <> 'cancelled'
                          AND ((mm.user_a = ? AND mm.user_b = $a.id) OR (mm.user_b = ? AND mm.user_a = $a.id)))))",
        [$viewerId, $viewerId, $viewerId],
    ];
}

/** Skills, availability and reputation for a set of users, keyed by user id. */
function load_bundles(array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));

    $bundles = [];
    foreach (fetch_all("SELECT id, name, programme, year_of_study, learning_method FROM users WHERE id IN ($in)", $ids) as $u) {
        $bundles[$u['id']] = $u + ['offers' => [], 'wants' => [], 'avail' => [], 'avg_rating' => null, 'rating_count' => 0, 'completed' => 0];
    }

    foreach (fetch_all(
        "SELECT us.user_id, us.type, us.level, s.id AS skill_id, s.name, c.id AS category_id, c.name AS category
           FROM user_skills us JOIN skills s ON s.id = us.skill_id JOIN categories c ON c.id = s.category_id
          WHERE us.user_id IN ($in) AND s.is_active = 1", $ids) as $r) {
        $bundles[$r['user_id']][$r['type'] === 'offer' ? 'offers' : 'wants'][$r['skill_id']] = $r;
    }

    foreach (fetch_all("SELECT user_id, day_of_week, start_time, end_time FROM availability WHERE user_id IN ($in)", $ids) as $r) {
        $bundles[$r['user_id']]['avail'][] = [(int) $r['day_of_week'], time_to_minutes($r['start_time']), time_to_minutes($r['end_time'])];
    }

    foreach (fetch_all("SELECT reviewee_id, AVG(score) AS a, COUNT(*) AS n FROM ratings WHERE is_hidden = 0 AND reviewee_id IN ($in) GROUP BY reviewee_id", $ids) as $r) {
        $bundles[$r['reviewee_id']]['avg_rating'] = (float) $r['a'];
        $bundles[$r['reviewee_id']]['rating_count'] = (int) $r['n'];
    }

    foreach (fetch_all("SELECT u.id AS uid, COUNT(*) AS n FROM users u JOIN matches m ON (m.user_a = u.id OR m.user_b = u.id) AND m.status = 'completed' WHERE u.id IN ($in) GROUP BY u.id", $ids) as $r) {
        $bundles[$r['uid']]['completed'] = (int) $r['n'];
    }

    return $bundles;
}

function time_to_minutes(string $t): int
{
    [$h, $m] = array_map('intval', explode(':', $t));
    return $h * 60 + $m;
}

/** Fraction (0-1) of the smaller availability window that overlaps; 0.5 when either side has no data. */
function availability_overlap(array $a, array $b): array
{
    if (!$a['avail'] || !$b['avail']) {
        return [0.5, null];
    }
    $overlap = 0;
    foreach ($a['avail'] as [$d1, $s1, $e1]) {
        foreach ($b['avail'] as [$d2, $s2, $e2]) {
            if ($d1 === $d2) {
                $overlap += max(0, min($e1, $e2) - max($s1, $s2));
            }
        }
    }
    $total = fn(array $slots) => array_sum(array_map(fn($s) => max(0, $s[2] - $s[1]), $slots));
    $smaller = max(1, min($total($a['avail']), $total($b['avail'])));
    return [min(1, $overlap / $smaller), round($overlap / 60, 1)];
}

/** Compatibility between two users' bundles, with a plain-language explanation. */
function score_pair(array $me, array $them): array
{
    $theyTeach = array_intersect_key($them['offers'], $me['wants']);   // skills I can learn from them
    $iTeach    = array_intersect_key($me['offers'], $them['wants']);   // skills they can learn from me

    $wantCov  = min(1, count($theyTeach) / max(1, count($me['wants'])));
    $offerCov = min(1, count($iTeach) / max(1, count($them['wants'])));

    [$avail, $overlapHours] = availability_overlap($me, $them);

    $cats = fn(array $skills) => array_unique(array_column($skills, 'category_id'));
    $affA = count(array_intersect($cats($me['wants']), $cats($them['offers']))) / max(1, count($cats($me['wants'])));
    $affB = count(array_intersect($cats($them['wants']), $cats($me['offers']))) / max(1, count($cats($them['wants'])));
    $category = ($affA + $affB) / 2;

    $fits = [];
    foreach ($theyTeach as $id => $s) {
        $fits[] = LEVEL_RANK[$s['level']] >= LEVEL_RANK[$me['wants'][$id]['level']] ? 1 : 0.5;
    }
    foreach ($iTeach as $id => $s) {
        $fits[] = LEVEL_RANK[$s['level']] >= LEVEL_RANK[$them['wants'][$id]['level']] ? 1 : 0.5;
    }
    $levelFit = $fits ? array_sum($fits) / count($fits) : 0.5;

    $method = ($me['learning_method'] === 'any' || $them['learning_method'] === 'any' || $me['learning_method'] === $them['learning_method']) ? 1 : 0;

    $rating  = $them['rating_count'] > 0 ? $them['avg_rating'] / 5 : 0.5;
    $history = 0.7 * $rating + 0.3 * min(1, $them['completed'] / 5);

    $score = (int) round(100 * (0.35 * $wantCov + 0.25 * $offerCov + 0.15 * $avail + 0.10 * $category + 0.05 * $levelFit + 0.05 * $method + 0.05 * $history));

    $teachNames = array_column($theyTeach, 'name');
    $learnNames = array_column($iTeach, 'name');
    $first = first_name($them['name']);
    if ($teachNames && $learnNames) {
        $why = 'You want to learn ' . implode(', ', $teachNames) . ', which ' . $first . ' offers, while they want to learn '
             . implode(', ', $learnNames) . ', which you offer.';
    } elseif ($teachNames) {
        $why = $first . ' offers ' . implode(', ', $teachNames) . ', which you want to learn.';
    } elseif ($learnNames) {
        $why = 'You offer ' . implode(', ', $learnNames) . ', which ' . $first . ' wants to learn.';
    } else {
        $why = 'You share interests in related skill categories.';
    }

    return [
        'score'         => min(100, $score),
        'teaches_me'    => array_values($teachNames),
        'i_teach_them'  => array_values($learnNames),
        'mutual'        => $teachNames && $learnNames,
        'overlap_hours' => $overlapHours,
        'why'           => $why,
        'parts'         => [
            'They teach what you want' => (int) round($wantCov * 100),
            'You teach what they want' => (int) round($offerCov * 100),
            'Availability overlap'     => (int) round($avail * 100),
            'Category affinity'        => (int) round($category * 100),
        ],
    ];
}

/** Ranked recommendations for a student. */
function find_matches(int $userId, int $limit = 12): array
{
    [$disc, $params] = discoverable_clause($userId);
    $candidates = fetch_all(
        "SELECT u.id FROM users u
          WHERE $disc AND (
                EXISTS (SELECT 1 FROM user_skills a JOIN user_skills b ON b.skill_id = a.skill_id
                         WHERE a.user_id = u.id AND a.type = 'offer' AND b.user_id = ? AND b.type = 'want')
             OR EXISTS (SELECT 1 FROM user_skills a JOIN user_skills b ON b.skill_id = a.skill_id
                         WHERE a.user_id = u.id AND a.type = 'want' AND b.user_id = ? AND b.type = 'offer')
             OR EXISTS (SELECT 1 FROM user_skills a JOIN skills sa ON sa.id = a.skill_id
                         JOIN skills sb ON sb.category_id = sa.category_id
                         JOIN user_skills b ON b.skill_id = sb.id
                         WHERE a.user_id = u.id AND a.type = 'offer' AND b.user_id = ? AND b.type = 'want'))
          LIMIT 300",
        array_merge($params, [$userId, $userId, $userId])
    );
    if (!$candidates) {
        return [];
    }

    $bundles = load_bundles(array_merge([$userId], array_column($candidates, 'id')));
    $me = $bundles[$userId];
    unset($bundles[$userId]);

    $out = [];
    foreach ($bundles as $id => $them) {
        $r = score_pair($me, $them);
        $out[] = $r + ['id' => $id, 'name' => $them['name'], 'programme' => $them['programme'],
                       'year_of_study' => $them['year_of_study'], 'avg_rating' => $them['avg_rating'],
                       'rating_count' => $them['rating_count']];
    }
    usort($out, fn($a, $b) => [$b['score'], (int) $b['mutual']] <=> [$a['score'], (int) $a['mutual']]);
    $out = array_slice($out, 0, $limit);

    // Relationship with each candidate, so the card can show the right action.
    if ($out) {
        $ids = array_column($out, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $matched = array_column(fetch_all(
            "SELECT IF(user_a = ?, user_b, user_a) AS pid FROM matches WHERE status <> 'cancelled' AND (user_a = ? OR user_b = ?) AND IF(user_a = ?, user_b, user_a) IN ($in)",
            array_merge([$userId, $userId, $userId, $userId], $ids)), 'pid');
        $pending = array_column(fetch_all(
            "SELECT receiver_id AS pid FROM requests WHERE sender_id = ? AND status = 'pending' AND receiver_id IN ($in)",
            array_merge([$userId], $ids)), 'pid');
        foreach ($out as &$r) {
            $r['relation'] = in_array($r['id'], $matched) ? 'matched' : (in_array($r['id'], $pending) ? 'pending' : null);
        }
        unset($r);
    }
    return $out;
}

/** Circular progress ring. */
function score_ring(int $score, string $size = ''): string
{
    $tone = $score >= 75 ? 'good' : ($score >= 45 ? 'ok' : 'low');
    return '<div class="ring ring-' . $tone . ($size ? ' ring-' . $size : '') . '" style="--p:' . $score . '" role="img" aria-label="' . $score . '% match"><span>' . $score . '%</span></div>';
}

/** Match / recommendation card. */
function render_match_card(array $m): string
{
    $meta = trim(($m['programme'] ?? '') . ($m['year_of_study'] ? ' · Year ' . (int) $m['year_of_study'] : ''), ' ·');
    $cta = match ($m['relation'] ?? null) {
        'matched' => '<a class="btn btn-sm btn-soft" href="' . url('messages.php?with=' . (int) $m['id']) . '">Message</a>',
        'pending' => '<span class="badge badge-warn">Request sent</span>',
        default   => '<a class="btn btn-sm btn-primary" href="' . url('profile.php?id=' . (int) $m['id'] . '&request=1') . '">Request exchange</a>',
    };
    return '<article class="card match">'
        . '<div class="match-top">' . avatar($m, 'lg')
        . '<div class="grow"><a class="match-name" href="' . url('profile.php?id=' . (int) $m['id']) . '">' . e($m['name']) . '</a>'
        . '<div class="muted small">' . e($meta ?: 'Student') . '</div>'
        . stars($m['avg_rating'] !== null ? (float) $m['avg_rating'] : null, (int) $m['rating_count']) . '</div>'
        . score_ring((int) $m['score']) . '</div>'
        . '<div class="match-skills">'
        . ($m['teaches_me'] ? '<div><small>Can teach you</small>' . skill_chips($m['teaches_me'], 'chip-teach', 3) . '</div>' : '')
        . ($m['i_teach_them'] ? '<div><small>Wants to learn from you</small>' . skill_chips($m['i_teach_them'], 'chip-learn', 3) . '</div>' : '')
        . '</div>'
        . '<p class="why"><b>Why this match:</b> ' . e($m['why']) . '</p>'
        . '<div class="match-foot">' . ($m['mutual'] ? '<span class="pill pill-good">' . icon('swap', 13) . ' Two-way match</span>' : '<span class="pill">One-way</span>')
        . '<span class="push">' . $cta . '</span></div></article>';
}
