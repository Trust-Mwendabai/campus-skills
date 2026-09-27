<?php
// Shared bootstrap: session, config, database, helpers and auth for every page.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Friendly page instead of a stack trace when something unexpected fails.
set_exception_handler(function (Throwable $ex): void {
    error_log($ex);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Server error']);
    } else {
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Something went wrong</title>'
           . '<body style="font-family:system-ui;max-width:32rem;margin:15vh auto;padding:0 1rem">'
           . '<h1>Something went wrong</h1><p>The service is temporarily unavailable. '
           . 'Please try again in a moment.</p></body>';
    }
});
