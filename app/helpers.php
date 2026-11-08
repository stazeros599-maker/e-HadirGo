<?php

declare(strict_types=1);

/**
 * Shared helpers: URLs, escaping, CSRF, sessions, formatting.
 */

const TZ_LOCAL = 'Asia/Kuching';

function config(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? null;
    }
    if ($value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

/** Directory that contains index.php, e.g. "/ehadirgo/public" or "". */
function base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

    return $dir === '/' ? '' : rtrim($dir, '/');
}

function url(string $route = '', array $params = []): string
{
    $query = [];
    if ($route !== '') {
        $query['r'] = $route;
    }
    $query += $params;

    $qs = http_build_query($query);

    return base_path() . '/index.php' . ($qs !== '' ? '?' . $qs : '');
}

function asset(string $path): string
{
    return base_path() . '/assets/' . ltrim($path, '/');
}

function redirect(string $target): void
{
    header('Location: ' . $target);
    exit;
}

function back(): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref !== '' ? $ref : url('dashboard'));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function truthy($value): bool
{
    return in_array($value, [true, 1, '1', 't', 'true', 'y', 'yes'], true);
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi tamat. Muat semula halaman. / Session expired, reload the page.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $items;
}

// ---------------------------------------------------------------------------
// Auth context
// ---------------------------------------------------------------------------

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = $_SESSION['uid'] ?? null;
    if ($id === null) {
        return $user = null;
    }

    $row = db_one('SELECT * FROM users WHERE id = ? AND is_active', [(int) $id]);
    if ($row === null) {
        unset($_SESSION['uid']);

        return $user = null;
    }

    $row['roles'] = array_column(
        db_all('SELECT role_code FROM user_roles WHERE user_id = ?', [(int) $id]),
        'role_code'
    );
    $user = $row;

    return $user;
}

function has_role(string $role): bool
{
    $user = current_user();

    return $user !== null && in_array($role, $user['roles'], true);
}

function require_login(): void
{
    if (current_user() === null) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        redirect(url('login'));
    }
}

function require_role(string $role): void
{
    require_login();
    if (!has_role($role)) {
        http_response_code(403);
        page('error', ['title' => '403', 'code' => 403, 'message' => t('err.forbidden')]);
        exit;
    }
}

function require_supervisor(): void
{
    require_login();
    if (!has_role('penyelia') && !has_role('super_admin')) {
        http_response_code(403);
        page('error', ['title' => '403', 'code' => 403, 'message' => t('err.forbidden')]);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

function page(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);

    ob_start();
    require APP_ROOT . '/app/views/' . $name . '.php';
    $content = ob_get_clean();

    require APP_ROOT . '/app/partials/layout.php';
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// Time
// ---------------------------------------------------------------------------

function local_tz(): DateTimeZone
{
    return new DateTimeZone(TZ_LOCAL);
}

function now_local(): DateTimeImmutable
{
    return new DateTimeImmutable('now', local_tz());
}

function local_dt(string $date, string $time): DateTimeImmutable
{
    return new DateTimeImmutable(trim($date) . ' ' . trim($time), local_tz());
}

function to_utc(DateTimeInterface $dt): string
{
    return (new DateTimeImmutable('@' . $dt->getTimestamp()))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function fmt_dt(?string $utc, string $format = 'd/m/Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

        return $dt->setTimezone(local_tz())->format($format);
    } catch (Exception $e) {
        return (string) $utc;
    }
}

/**
 * Unix timestamp for a stored UTC value.
 *
 * MySQL hands back "2026-11-06 01:00:00". An explicit offset such as
 * "2026-11-06 01:00:00+00" is also accepted, so a restored dump with
 * offsets does not silently read as "expired".
 */
function utc_timestamp(?string $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
    } catch (Exception $e) {
        $fallback = strtotime($value);

        return $fallback === false ? null : $fallback;
    }
}

// ---------------------------------------------------------------------------
// CSV
// ---------------------------------------------------------------------------

function csv_download(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
    fputcsv($out, $header);

    foreach ($rows as $row) {
        fputcsv($out, array_map(static fn($v) => is_bool($v) ? ($v ? '1' : '0') : $v, $row));
    }

    fclose($out);
    exit;
}
