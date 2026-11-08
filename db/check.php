<?php

declare(strict_types=1);

/**
 * Shows which database settings PHP actually sees, and whether they connect.
 * Passwords are never printed.
 *
 *   php db/check.php
 *   php db/check.php "mysql://user:pass@host:3306/db"
 *
 * Expected: driver=mysql … and "connection OK".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Optional connection string, so no environment variable is needed.
if (isset($argv[1])) {
    putenv('DATABASE_URL=' . $argv[1]);
}

require __DIR__ . '/db.php';

$cfg = db_config();

printf("driver  : %s\n", $cfg['driver']);
printf("host    : %s:%d\n", $cfg['host'], $cfg['port']);
printf("database: %s\n", $cfg['name']);
printf("user    : %s\n", $cfg['user']);
printf("password: %s\n", $cfg['pass'] === '' ? '(empty)' : '********');
printf("dsn     : %s\n", db_dsn($cfg));
printf("drivers : %s\n", implode(', ', PDO::getAvailableDrivers()) ?: '(none)');

$health = db_health();

if ($health['ok']) {
    printf("\nconnection OK (%d ms)\n", (int) $health['latency_ms']);
    exit(0);
}

printf("\nconnection FAILED: %s\n", (string) $health['error']);

exit(1);
