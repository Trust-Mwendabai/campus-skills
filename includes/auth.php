<?php
// Authentication helpers: session user, guards, login throttling.

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin_role(?array $user): bool
{
    return $user !== null && in_array($user['role'], ['admin', 'super_admin'], true);
}

/** Fresh user row for the signed-in user (cached per request). */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (is_logged_in()) {
            $user = fetch_one('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
            if ($user !== null) {
                unset($user['password_hash']);
            }
            if ($user !== null && (int) ($_SESSION['epoch'] ?? -1) !== (int) $user['session_epoch']) {
                $user = null;   // signed out from another device / after a password change
            }
            if ($user === null || $user['status'] !== 'active') {
                $user = null;
                logout_user();
            }
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['epoch'] = (int) fetch_value('SELECT session_epoch FROM users WHERE id = ?', [$user['id']]);
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $next = (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && $script && !str_contains($_SERVER['SCRIPT_NAME'], '/admin/') && !str_contains($_SERVER['SCRIPT_NAME'], '/api/'))
            ? '?next=' . urlencode($script) : '';
        redirect(url('login.php') . $next);
    }
    return $user;
}

/** Student pages: admins are sent to their own interface. */
function require_student(): array
{
    $user = require_login();
    if (is_admin_role($user)) {
        redirect(url('admin/dashboard.php'));
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (!is_admin_role($user)) {
        http_response_code(403);
        exit('You do not have permission to view this page.');
    }
    return $user;
}

function require_super_admin(): array
{
    $user = require_admin();
    if ($user['role'] !== 'super_admin') {
        http_response_code(403);
        exit('Only the super administrator can view this page.');
    }
    return $user;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Too many recent failures for this identifier or IP? */
function login_blocked(string $identifier): bool
{
    q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    $byId = (int) fetch_value('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [$identifier]);
    $byIp = (int) fetch_value('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)', [client_ip()]);
    return $byId >= 5 || $byIp >= 20;
}

function record_failed_login(string $identifier): void
{
    q('INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)', [$identifier, client_ip()]);
}

function clear_failed_logins(string $identifier): void
{
    q('DELETE FROM login_attempts WHERE identifier = ?', [$identifier]);
}

/** Validate a new password; returns an error message or null. */
function password_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must include at least one letter and one number.';
    }
    return null;
}
