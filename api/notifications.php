<?php
require __DIR__ . '/../includes/api.php';
$me = api_user();

if (is_post()) {
    api_post();
    $action = $_POST['action'] ?? '';
    if ($action === 'read') {
        q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [(int) ($_POST['id'] ?? 0), $me['id']]);
    } elseif ($action === 'read_all') {
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$me['id']]);
    } else {
        json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
    json_out(['ok' => true]);
}

$unread = (int) fetch_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$me['id']]);
if (isset($_GET['count'])) {
    json_out(['ok' => true, 'unread' => $unread]);
}

$items = array_map(fn($n) => [
    'id' => (int) $n['id'], 'type' => $n['type'], 'title' => $n['title'], 'body' => $n['body'],
    'link' => $n['link'] ? url($n['link']) : null, 'is_read' => (bool) $n['is_read'], 'time' => time_ago($n['created_at']),
], fetch_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$me['id']]));

json_out(['ok' => true, 'unread' => $unread, 'items' => $items]);
