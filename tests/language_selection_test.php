<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$testUserLanguage = 'ms';
function current_user(): ?array
{
    return ['language' => $GLOBALS['testUserLanguage']];
}

require APP_ROOT . '/app/i18n.php';

$_SESSION = [];
$_GET = ['lang' => 'en'];
if (current_lang() !== 'en') {
    fwrite(STDERR, "FAIL: query language override was ignored\n");
    exit(1);
}

$_GET = [];
if (current_lang() !== 'en') {
    fwrite(STDERR, "FAIL: session language selection was not retained\n");
    exit(1);
}

unset($_SESSION['lang']);
if (current_lang() !== 'ms') {
    fwrite(STDERR, "FAIL: saved account language was not used as fallback\n");
    exit(1);
}

echo "ok: query and session language overrides persist ahead of account fallback\n";
