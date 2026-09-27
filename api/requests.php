<?php
require __DIR__ . '/../includes/api.php';
$me = api_user();
api_post();

$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

switch ($action) {
    case 'accept':
    case 'reject':
    case 'cancel':
        api_from_result(respond_request($id, $me['id'], $action));
    case 'create':
        $r = create_request($me['id'], $_POST);
        json_out(['ok' => $r['ok'], 'message' => $r['message']] + ($r['ok'] ? ['id' => $r['id']] : ['error' => $r['message']]), $r['ok'] ? 200 : 422);
    default:
        json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}
