<?php

declare(strict_types=1);

/**
 * i18n — Bahasa Melayu (default) and English.
 *
 * The active language is chosen by, in order: ?lang= query override,
 * the current session, the signed-in user's saved preference, then 'ms'.
 */

function lang_available(): array
{
    return ['ms', 'en'];
}

function current_lang(): string
{
    if (isset($_GET['lang']) && in_array($_GET['lang'], lang_available(), true)) {
        $_SESSION['lang'] = $_GET['lang'];

        return $_GET['lang'];
    }

    $session = $_SESSION['lang'] ?? 'ms';
    if (in_array($session, lang_available(), true)) {
        return $session;
    }

    $user = current_user();
    if ($user !== null && in_array($user['language'], lang_available(), true)) {
        return $user['language'];
    }

    return 'ms';
}

function lang_array(string $lang): array
{
    static $cache = [];

    if (!isset($cache[$lang])) {
        $file = APP_ROOT . '/lang/' . $lang . '.php';
        $cache[$lang] = is_file($file) ? require $file : [];
    }

    return $cache[$lang];
}

/**
 * Translate a key, with {placeholder} substitution.
 */
function t(string $key, array $params = []): string
{
    $lang = current_lang();
    $strings = lang_array($lang);

    $text = $strings[$key] ?? null;
    if ($text === null) {
        $fallback = lang_array('ms');
        $text = $fallback[$key] ?? $key;
    }

    if ($params !== []) {
        $replace = [];
        foreach ($params as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }
        $text = strtr($text, $replace);
    }

    return $text;
}

/**
 * Translate for a specific recipient language (notifications).
 */
function tmpl(string $lang, string $key, array $params = []): string
{
    $strings = lang_array($lang);
    $text = $strings[$key] ?? t($key, $params);

    foreach ($params as $name => $value) {
        $text = strtr($text, ['{' . $name . '}' => (string) $value]);
    }

    return $text;
}

function lang_switch_url(string $lang): string
{
    $params = $_GET;
    $params['lang'] = $lang;

    return url($params['r'] ?? 'dashboard', array_diff_key($params, ['r' => '']));
}
