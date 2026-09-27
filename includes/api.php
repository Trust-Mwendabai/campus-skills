<?php
// Helpers for JSON endpoints in /api.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/exchange.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_out(['ok' => false, 'error' => 'Please log in.'], 401);
    }
    return $user;
}

/** Require a POST with a valid CSRF token (sent as form field or X-CSRF-Token header). */
function api_post(): void
{
    if (!is_post()) {
        json_out(['ok' => false, 'error' => 'POST required.'], 405);
    }
    csrf_check();
}

function api_from_result(array $r): never
{
    json_out(['ok' => $r['ok'], 'message' => $r['message']] + ($r['ok'] ? [] : ['error' => $r['message']]), $r['ok'] ? 200 : 422);
}
