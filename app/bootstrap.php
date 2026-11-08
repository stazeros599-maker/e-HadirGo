<?php

declare(strict_types=1);

/**
 * Application bootstrap: constants, autoload of the app files, session.
 */

define('APP_ROOT', dirname(__DIR__));

// Load local settings before the database layer reads environment values.
$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    $envLines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($envLines === false) {
        throw new RuntimeException('Unable to read the local .env file.');
    }

    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/i', $line, $matches)) {
            continue;
        }

        $key = $matches[1];
        if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
            continue;
        }

        $value = trim($matches[2]);
        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Kuching');

require_once APP_ROOT . '/db/db.php';
require_once APP_ROOT . '/app/helpers.php';
require_once APP_ROOT . '/app/i18n.php';
require_once APP_ROOT . '/app/auth.php';
require_once APP_ROOT . '/app/geo.php';
require_once APP_ROOT . '/app/notify.php';
require_once APP_ROOT . '/app/repo.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('ehadirgo');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);
    session_start();
}
