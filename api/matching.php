<?php
require __DIR__ . '/../includes/api.php';
$me = api_user();
if (is_admin_role($me)) {
    json_out(['ok' => false, 'error' => 'Matching is for students.'], 403);
}

$limit = min(30, max(1, (int) ($_GET['limit'] ?? 10)));
$matches = array_map(fn($m) => [
    'id' => $m['id'], 'name' => $m['name'], 'programme' => $m['programme'], 'score' => $m['score'],
    'mutual' => (bool) $m['mutual'], 'teaches_me' => $m['teaches_me'], 'i_teach_them' => $m['i_teach_them'],
    'why' => $m['why'], 'breakdown' => $m['parts'], 'relation' => $m['relation'] ?? null,
], find_matches($me['id'], $limit));

json_out(['ok' => true, 'matches' => $matches]);
