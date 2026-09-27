<?php
require __DIR__ . '/../includes/api.php';
$me = api_user();

function format_message(array $m, int $meId): array
{
    $ts = strtotime($m['created_at']);
    return [
        'id'   => (int) $m['id'],
        'mine' => (int) $m['sender_id'] === $meId,
        'body' => $m['body'],
        'time' => date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('M j, H:i', $ts),
    ];
}

if (is_post()) {
    api_post();
    $to = (int) ($_POST['to'] ?? 0);
    $r = send_message($me['id'], $to, (string) ($_POST['body'] ?? ''));
    if (!$r['ok']) {
        json_out(['ok' => false, 'error' => $r['message']], 422);
    }
    $row = fetch_one('SELECT * FROM messages WHERE id = ?', [$r['id']]);
    json_out(['ok' => true, 'message' => format_message($row, $me['id'])]);
}

$with = (int) ($_GET['with'] ?? 0);
$after = (int) ($_GET['after'] ?? 0);
$rows = fetch_all(
    'SELECT * FROM messages WHERE id > ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY id ASC LIMIT 100',
    [$after, $me['id'], $with, $with, $me['id']]
);
// Only messages the other person sent are new to us (ours were appended locally when sent).
$incoming = array_values(array_filter($rows, fn($m) => (int) $m['receiver_id'] === $me['id']));
if ($incoming) {
    q('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0', [$with, $me['id']]);
}
json_out(['ok' => true, 'messages' => array_map(fn($m) => format_message($m, $me['id']), $incoming)]);
