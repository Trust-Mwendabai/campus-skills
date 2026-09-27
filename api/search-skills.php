<?php
require __DIR__ . '/../includes/api.php';
api_user();

$term = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($term) < 2) {
    json_out(['ok' => true, 'skills' => []]);
}
$like = '%' . addcslashes($term, '%_\\') . '%';
$skills = fetch_all(
    'SELECT s.id, s.name, c.name AS category FROM skills s JOIN categories c ON c.id = s.category_id
      WHERE s.is_active = 1 AND c.is_active = 1 AND s.name LIKE ? ORDER BY (s.name LIKE ?) DESC, s.name LIMIT 10',
    [$like, addcslashes($term, '%_\\') . '%']
);
json_out(['ok' => true, 'skills' => $skills]);
