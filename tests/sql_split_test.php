<?php

declare(strict_types=1);

/**
 * Checks db_split_sql() against the real MySQL schema.
 *
 *   php tests/sql_split_test.php
 */

require dirname(__DIR__) . '/db/db.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  ok    {$label}\n";
    } else {
        $failed++;
        echo "  FAIL  {$label}\n";
    }
}

$sql = (string) file_get_contents(dirname(__DIR__) . '/db/schema.mysql.sql');
$statements = db_split_sql($sql);

echo "schema.mysql.sql\n";
check('split produced statements', count($statements) >= 18);
check('12 CREATE TABLE', count(preg_grep('/CREATE TABLE/i', $statements)) === 12);
check('1 CREATE VIEW', count(preg_grep('/CREATE (OR REPLACE )?VIEW/i', $statements)) === 1);
check('2 INSERT seeds', count(preg_grep('/^INSERT/i', $statements)) === 2);
check('no statement is comment-only', count(preg_grep('/^\s*(--|#)/', $statements)) === 0);
check('every statement ends without trailing semicolon', count(preg_grep('/;\s*$/', $statements)) === 0);

echo "\nquoting rules\n";
$tricky = "INSERT INTO x VALUES ('a;b');\n-- comment with ; inside\nINSERT INTO y VALUES (\"c;d\");";
$parts = db_split_sql($tricky);
check('semicolon inside string does not split', str_contains($parts[0] ?? '', "'a;b'"));
check('only two statements', count($parts) === 2);
check('comment stays attached, not executed alone', str_contains($parts[1] ?? '', 'INSERT INTO y'));

$commentOnly = "-- just a note\n-- another\n";
check('comment-only script yields nothing', db_split_sql($commentOnly) === []);

echo "\n" . str_repeat('-', 48) . "\n";
echo "passed: {$passed}  failed: {$failed}\n";

exit($failed === 0 ? 0 : 1);
