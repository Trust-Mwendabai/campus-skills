<?php
// General helpers: output, URLs, icons, flash, CSRF, settings, notifications, pagination.

const CATEGORY_ICONS = [
    'programming' => 'code', 'cybersecurity' => 'shield', 'design' => 'palette', 'data' => 'chart',
    'languages' => 'globe', 'music' => 'music', 'academics' => 'book', 'business' => 'briefcase', 'lifestyle' => 'coffee',
];
const LEVELS  = ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'];
const LEVEL_RANK = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
const METHODS = ['any' => 'Any format', 'online' => 'Online', 'physical' => 'In person', 'group' => 'Group', 'one_to_one' => 'One-to-one'];
const DAYS    = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
const REPORT_CATEGORIES = [
    'fake_profile' => 'Fake profile', 'spam' => 'Spam', 'inappropriate_behaviour' => 'Inappropriate behaviour',
    'harassment' => 'Harassment', 'misleading_information' => 'Misleading information',
    'inappropriate_message' => 'Inappropriate message', 'other' => 'Other',
];

// ---------- Output ----------

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** URL path of the project root (handles folders with spaces and sub-directories like /admin). */
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $root = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
        $rel  = (strlen($doc) && strncasecmp($root, $doc, strlen($doc)) === 0) ? substr($root, strlen($doc)) : '';
        $base = rtrim(implode('/', array_map('rawurlencode', explode('/', $rel))), '/');
    }
    return $base;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function initials(string $name): string
{
    $out = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

function avatar(array|string $user, string $size = ''): string
{
    $id   = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
    $name = is_array($user) ? (string) ($user['name'] ?? '') : $user;
    return '<span class="avatar av' . ($id % 6) . ($size ? ' avatar-' . $size : '') . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function time_ago(string $ts): string
{
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($ts));
}

function greeting(): string
{
    $h = (int) date('G');
    return $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?? $name;
}

function level_badge(string $level): string
{
    return '<span class="level level-' . e($level) . '">' . e(LEVELS[$level] ?? $level) . '</span>';
}

function status_badge(string $status): string
{
    $tone = match ($status) {
        'accepted', 'completed', 'active', 'resolved', 'scheduled' => 'good',
        'pending', 'investigating', 'open' => 'warn',
        'rejected', 'cancelled', 'missed', 'suspended', 'dismissed' => 'bad',
        default => 'neutral',
    };
    return '<span class="badge badge-' . $tone . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

function icon(string $name, int $size = 18): string
{
    static $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'skills'    => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/>',
        'requests'  => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'zap'       => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'messages'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'star'      => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        'menu'      => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'trash'     => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
        'send'      => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'swap'      => '<path d="M4 8h13l-3-3M20 16H7l3 3"/>',
        'arrow'     => '<path d="M5 12h14M13 5l7 7-7 7"/>',
        'bell'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'flag'      => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'award'     => '<circle cx="12" cy="8" r="6"/><path d="m15.5 13.5 1.5 8.5-5-3-5 3 1.5-8.5"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'tag'       => '<path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><path d="M7 7h.01"/>',
        'link'      => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'pin'       => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'ban'       => '<circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/>',
        'code'      => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'palette'   => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.65-.75 1.65-1.69 0-.44-.18-.84-.44-1.13-.29-.29-.44-.65-.44-1.13a1.64 1.64 0 0 1 1.67-1.67h2c3.05 0 5.55-2.5 5.55-5.55C21.97 6.01 17.46 2 12 2z"/>',
        'globe'     => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'music'     => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'book'      => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'coffee'    => '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4z"/><path d="M6 2v2M10 2v2M14 2v2"/>',
        'sparkles'  => '<path d="m12 3 1.9 5.8L20 10.7l-6.1 1.9L12 18.4l-1.9-5.8L4 10.7l6.1-1.9z"/>',
    ];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function stars(?float $avg, int $count = 0): string
{
    if ($avg === null || $count === 0) {
        return '<span class="muted small">No ratings yet</span>';
    }
    return '<span class="rating">' . icon('star', 14) . ' <b>' . number_format($avg, 1) . '</b> <span class="muted">(' . $count . ')</span></span>';
}

function star_row(int $score): string
{
    return '<span class="star-row" aria-label="' . $score . ' out of 5">' . str_repeat(icon('star', 14), $score) . '<i>' . str_repeat(icon('star', 14), 5 - $score) . '</i></span>';
}

// ---------- Flash + CSRF ----------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(403);   // 419 is not a standard status, Apache turns it into a 500
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            header('Content-Type: application/json');
            exit(json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']));
        }
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function post_str(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}

// ---------- Settings, notifications, audit log ----------

function setting(string $name, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = array_column(fetch_all('SELECT name, value FROM settings'), 'value', 'name');
    }
    return $cache[$name] ?? $default;
}

function save_setting(string $name, string $value): void
{
    q('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): void
{
    $prefColumn = match ($type) {
        'match' => 'notify_matches',
        'request', 'request_accepted', 'request_rejected' => 'notify_requests',
        'message' => 'notify_messages',
        'session', 'session_completed' => 'notify_sessions',
        default => null,
    };
    if ($prefColumn !== null && !fetch_value("SELECT $prefColumn FROM users WHERE id = ?", [$userId])) {
        return;
    }
    // Store links relative to the app root so they survive a move to another URL path.
    if ($link !== null && str_starts_with($link, base_url() . '/')) {
        $link = substr($link, strlen(base_url()) + 1);
    }
    q('INSERT INTO notifications (user_id, type, title, body, link) VALUES (?, ?, ?, ?, ?)',
      [$userId, $type, mb_substr($title, 0, 150), $body !== null ? mb_substr($body, 0, 300) : null, $link]);
}

function admin_log(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void
{
    q('INSERT INTO admin_actions (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)',
      [$adminId, $action, $targetType, $targetId, $details !== null ? mb_substr($details, 0, 300) : null]);
}

// ---------- Pagination ----------

/** @return array{0:int,1:int,2:int} [page, pages, offset] */
function paginate(int $total, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    return [$page, $pages, ($page - 1) * $perPage];
}

function pager(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $link = function (int $p, string $label, bool $disabled = false, bool $current = false): string {
        if ($disabled) {
            return '<span class="pg disabled">' . $label . '</span>';
        }
        $qs = http_build_query(array_merge($_GET, ['page' => $p]));
        return '<a class="pg' . ($current ? ' current' : '') . '" href="?' . e($qs) . '">' . $label . '</a>';
    };
    $out = '<nav class="pager" aria-label="Pagination">' . $link($page - 1, '‹ Prev', $page <= 1);
    for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++) {
        $out .= $link($p, (string) $p, false, $p === $page);
    }
    return $out . $link($page + 1, 'Next ›', $page >= $pages) . '</nav>';
}

function skill_chips(array $names, string $class = 'chip-teach', int $max = 4): string
{
    $out = '';
    foreach (array_slice($names, 0, $max) as $n) {
        $out .= '<span class="chip ' . $class . '">' . e($n) . '</span>';
    }
    if (count($names) > $max) {
        $out .= '<span class="chip chip-more">+' . (count($names) - $max) . '</span>';
    }
    return $out ?: '<span class="muted small">None listed</span>';
}

function all_categories(bool $activeOnly = true): array
{
    return fetch_all('SELECT id, name, slug FROM categories' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name');
}

function all_skills(): array
{
    return fetch_all('SELECT s.id, s.name, c.name AS category, c.id AS category_id FROM skills s JOIN categories c ON c.id = s.category_id WHERE s.is_active = 1 AND c.is_active = 1 ORDER BY c.name, s.name');
}
