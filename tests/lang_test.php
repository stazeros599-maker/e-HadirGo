<?php

declare(strict_types=1);

/**
 * Verifies that every literal translation key used in code exists in both
 * language files, and that ms.php and en.php have the same keys.
 *
 *   php tests/lang_test.php
 */

$root = dirname(__DIR__);
$ms = require $root . '/lang/ms.php';
$en = require $root . '/lang/en.php';

$missingMs = array_diff(array_keys($en), array_keys($ms));
$missingEn = array_diff(array_keys($ms), array_keys($en));

echo 'ms keys: ' . count($ms) . ' | en keys: ' . count($en) . "\n";

if ($missingMs !== []) {
    echo "Only in en: " . implode(', ', $missingMs) . "\n";
}
if ($missingEn !== []) {
    echo "Only in ms: " . implode(', ', $missingEn) . "\n";
}

$used = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if (str_contains($path, '/lang/') || str_contains($path, '/tests/')) {
        continue;
    }

    $source = (string) file_get_contents($path);
    preg_match_all("/\\bt\\('([^']+)'/", $source, $m);
    foreach ($m[1] as $key) {
        $used[$key] = true;
    }
    preg_match_all("/tmpl\\([^,]+,\\s*'([^']+)'/", $source, $m2);
    foreach ($m2[1] as $key) {
        $used[$key] = true;
    }
}

$missing = [];
foreach (array_keys($used) as $key) {
    if (str_ends_with($key, '.') || isset($ms[$key])) {
        continue;
    }

    // A key that is a prefix of real keys is a dynamic fragment
    // (e.g. "checkin.decision_" . $decision), not a missing string.
    $isPrefix = false;
    foreach (array_keys($ms) as $existing) {
        if (str_starts_with($existing, $key)) {
            $isPrefix = true;
            break;
        }
    }

    if (!$isPrefix) {
        $missing[] = $key;
    }
}

echo 'literal keys used: ' . count($used) . "\n";
echo $missing === [] ? "all used keys exist\n" : 'MISSING: ' . implode(', ', $missing) . "\n";

exit(($missing === [] && $missingMs === [] && $missingEn === []) ? 0 : 1);
