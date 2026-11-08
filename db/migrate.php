<?php

declare(strict_types=1);

/**
 * Applies schema.mysql.sql over PDO — useful when the mysql client is not
 * installed (typical on Windows).
 *
 *   php db/migrate.php
 *   php db/migrate.php "mysql://user:pass@host:3306/db"
 *
 * Reads credentials from DATABASE_URL / MYSQL_ADDON_* / db.local.php,
 * exactly like the application.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/db.php';

$uri = $argv[1] ?? null;
$cfg = $uri !== null ? db_config_from_uri($uri) : db_config();

printf("target : mysql %s:%d/%s\n", $cfg['host'], $cfg['port'], $cfg['name']);

try {
    $pdo = new PDO(db_dsn($cfg), $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$file = __DIR__ . '/schema.mysql.sql';
$sql = file_get_contents($file);

if ($sql === false) {
    fwrite(STDERR, "cannot read {$file}\n");
    exit(1);
}

// MySQL runs one statement per exec(), so the script is split on semicolons
// outside quotes and comments.
$statements = db_split_sql($sql);
$done = 0;

foreach ($statements as $index => $statement) {
    try {
        $pdo->exec($statement);
        $done++;
    } catch (PDOException $e) {
        fwrite(STDERR, sprintf(
            "statement %d/%d failed: %s\n---\n%s\n---\n",
            $index + 1,
            count($statements),
            $e->getMessage(),
            substr($statement, 0, 400)
        ));
        exit(1);
    }
}

printf("schema applied (%d statements)\n", $done);
echo "next: php db/seed.php\n";
