<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/db.php';

$pdo = db();
$column = $pdo->query("SHOW COLUMNS FROM assignments LIKE 'participant_group_id'")->fetch();

if ($column === false) {
    $pdo->exec('ALTER TABLE assignments ADD COLUMN participant_group_id CHAR(32) NULL AFTER staff_id');
    echo "Added assignments.participant_group_id\n";
}

$index = $pdo->query("SHOW INDEX FROM assignments WHERE Key_name = 'idx_as_participant_group'")->fetch();

if ($index === false) {
    $pdo->exec('ALTER TABLE assignments ADD KEY idx_as_participant_group (participant_group_id)');
    echo "Added idx_as_participant_group\n";
}

echo "Assignment participant-group migration complete.\n";
