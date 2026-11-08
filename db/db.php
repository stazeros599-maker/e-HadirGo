<?php

declare(strict_types=1);

/**
 * db.php — e-HadirGo database connection layer (PDO, MySQL).
 *
 * Works on a Clever Cloud MySQL add-on, a plain XAMPP/phpMyAdmin install,
 * or any MySQL 8 / MariaDB 10.6+ server. Settings are resolved in this order:
 *
 *   1. db.local.php            (optional, git-ignored)
 *   2. DATABASE_URL            (mysql://user:pass@host:3306/db)
 *   3. MYSQL_ADDON_URI         (injected by Clever Cloud)
 *   4. MYSQL_ADDON_* variables (injected by Clever Cloud)
 *   5. DB_HOST / DB_NAME / DB_USER / DB_PASS
 *   6. Local fallback: root@127.0.0.1/ehadirgo
 *
 * All access is through prepared statements; no string concatenation of
 * user input reaches SQL. Every timestamp is handled in UTC.
 *
 * @package e-HadirGo
 */

if (!defined('EHADIRGO_DB')) {
    define('EHADIRGO_DB', true);
}

// Guard: never render this file if it is requested directly over HTTP.
if (PHP_SAPI !== 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(404);
    exit;
}

/**
 * Read a configuration value from the environment with a fallback.
 *
 * Clever Cloud injects variables into the process environment; shared hosts
 * often expose them through $_ENV or $_SERVER instead.
 */
function env_str(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    if ($value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

/**
 * Parse a connection URI into a config array.
 */
function db_config_from_uri(string $uri): array
{
    $parts = parse_url($uri);
    if ($parts === false || empty($parts['scheme'])) {
        throw new RuntimeException('Invalid database URI.');
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['mysql', 'mysqls'], true)) {
        throw new RuntimeException('Only MySQL connection strings are supported.');
    }

    return [
        'driver' => 'mysql',
        'host'   => $parts['host'] ?? '127.0.0.1',
        'port'   => (int) ($parts['port'] ?? 3306),
        'name'   => isset($parts['path']) ? ltrim($parts['path'], '/') : '',
        'user'   => isset($parts['user']) ? rawurldecode($parts['user']) : '',
        'pass'   => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
    ];
}

/**
 * Resolve the active connection settings.
 *
 * @return array{driver:string,host:string,port:int,name:string,user:string,pass:string}
 */
function db_config(): array
{
    // 0) Explicit local override, for hosts that cannot set environment
    //    variables (shared hosting). Copy db.local.example.php and fill it in.
    //    This file must never be committed — it is in .gitignore.
    $local = __DIR__ . '/db.local.php';
    if (is_file($local)) {
        $override = require $local;

        if (is_array($override) && isset($override['host'], $override['name'], $override['user'])) {
            return $override + ['driver' => 'mysql', 'port' => 3306, 'pass' => ''];
        }
    }

    $uri = env_str('DATABASE_URL') ?? env_str('MYSQL_ADDON_URI');

    if ($uri !== null) {
        return db_config_from_uri($uri);
    }

    return [
        //Do not change these value anymore
        'driver' => 'mysql',
        'host'   => (string) env_str('MYSQL_ADDON_HOST', env_str('DB_HOST', 'bvy032mx0rsw22pdomzs-mysql.services.clever-cloud.com')),
        'port'   => (int) env_str('MYSQL_ADDON_PORT', env_str('DB_PORT', '3306')),
        'name'   => (string) env_str('MYSQL_ADDON_DB', env_str('DB_NAME', 'bvy032mx0rsw22pdomzs')),
        'user'   => (string) env_str('MYSQL_ADDON_USER', env_str('DB_USER', 'uo1bi7kmof2wyavh')),
        'pass'   => (string) env_str('MYSQL_ADDON_PASSWORD', env_str('DB_PASS', 'QN7N9MZnuTdnixATwZ7X')),
    ];
}

/**
 * Build the PDO DSN.
 */
function db_dsn(array $cfg): string
{
    return sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['name']
    );
}

/**
 * Abort with a generic message. Details go to the error log only, never
 * to the browser.
 */
function db_fail(string $reason): void
{
    error_log('[e-HadirGo] Database unavailable: ' . $reason);

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
    }

    exit("Perkhidmatan tidak tersedia. Sila cuba sebentar lagi.\n");
}

/**
 * Shared PDO handle.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = db_config();

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        db_fail('The pdo_mysql extension is not installed.');
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];

    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '+00:00'";
    }

    // Managed MySQL often presents a self-signed certificate; encrypt without
    // demanding a trusted CA. Enable with MYSQL_SSL=1.
    if (env_str('MYSQL_SSL', '0') === '1' && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        $pdo = new PDO(db_dsn($cfg), $cfg['user'], $cfg['pass'], $options);
    } catch (PDOException $e) {
        db_fail($e->getMessage());
    }

    return $pdo;
}

// ---------------------------------------------------------------------------
// Query helpers
// ---------------------------------------------------------------------------

/**
 * Prepare, bind with the correct PDO type, execute, and return the statement.
 *
 * Binding matters: execute(array) sends every value as a string, which breaks
 * TINYINT/BOOLEAN columns under strict mode and sends '' for false.
 */
function db_prepare(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $param = is_int($key) ? $key + 1 : $key;

        if ($value === null) {
            $type = PDO::PARAM_NULL;
        } elseif (is_bool($value)) {
            $type = PDO::PARAM_BOOL;
        } elseif (is_int($value)) {
            $type = PDO::PARAM_INT;
        } else {
            $type = PDO::PARAM_STR;
        }

        $stmt->bindValue($param, $value, $type);
    }

    $stmt->execute();

    return $stmt;
}

/**
 * Run a statement and return the PDOStatement.
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    return db_prepare($sql, $params);
}

/**
 * Fetch a single row, or null.
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * Fetch all rows.
 */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/**
 * Fetch the first column of the first row.
 *
 * @return mixed
 */
function db_val(string $sql, array $params = [])
{
    $value = db_run($sql, $params)->fetchColumn();

    return $value === false ? null : $value;
}

/**
 * Execute a statement and return the affected row count.
 */
function db_exec(string $sql, array $params = []): int
{
    return db_run($sql, $params)->rowCount();
}

/**
 * Reject anything that is not a plain identifier, so table and column names
 * can never carry SQL.
 */
function db_ident(string $name): string
{
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name) !== 1) {
        throw new InvalidArgumentException('Invalid SQL identifier: ' . $name);
    }

    return $name;
}

/**
 * Split a multi-statement SQL script into individual statements.
 *
 * MySQL's PDO driver refuses multiple statements in one exec(), so the
 * schema is applied one statement at a time. Comment lines stay attached
 * to the statement below them; splitting only happens on semicolons
 * outside quotes, backticks and comments.
 *
 * @return list<string>
 */
function db_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = $inDouble = $inBacktick = $inLineComment = $inBlockComment = false;

    $push = static function (string $chunk) use (&$statements): void {
        $chunk = trim($chunk);
        if ($chunk === '') {
            return;
        }

        // Drop leading banner comments and blank lines so each statement
        // starts with real SQL, not with the comment block above it.
        $lines = preg_split('/\R/', $chunk) ?: [];
        while ($lines !== [] && (trim($lines[0]) === '' || preg_match('/^\s*(--|#)/', $lines[0]) === 1)) {
            array_shift($lines);
        }
        $chunk = trim(implode("\n", $lines));

        if ($chunk === '') {
            return;
        }

        // Skip fragments that hold nothing but comments.
        $clean = preg_replace('/^\s*--[^\n]*$/m', '', $chunk);
        $clean = preg_replace('/^\s*#[^\n]*$/m', '', (string) $clean);
        $clean = preg_replace('!/\*.*?\*/!s', '', (string) $clean);

        if (trim((string) $clean) !== '') {
            $statements[] = $chunk;
        }
    };

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $inBlockComment = false;
            }
            continue;
        }

        if ($inSingle || $inDouble) {
            $buffer .= $char;
            $quote = $inSingle ? "'" : '"';

            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote && $next === $quote) {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) {
                $inSingle = $inDouble = false;
            }
            continue;
        }

        if ($inBacktick) {
            $buffer .= $char;
            if ($char === '`') {
                $inBacktick = false;
            }
            continue;
        }

        if ($char === '-' && $next === '-') {
            $inLineComment = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '#') {
            $inLineComment = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $buffer .= $char;
            continue;
        }
        if ($char === "'") {
            $inSingle = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '"') {
            $inDouble = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '`') {
            $inBacktick = true;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $push($buffer);
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $push($buffer);

    return $statements;
}

/**
 * Insert an associative array and return the new AUTO_INCREMENT id
 * (0 for tables without one, such as user_roles).
 */
function db_insert(string $table, array $data): int
{
    if ($data === []) {
        throw new InvalidArgumentException('db_insert() needs at least one column.');
    }

    $table = db_ident($table);
    $columns = [];
    $marks = [];
    $params = [];

    foreach ($data as $column => $value) {
        $column = db_ident((string) $column);
        $columns[] = $column;
        $marks[] = ':' . $column;
        $params[':' . $column] = $value;
    }

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $columns),
        implode(', ', $marks)
    );

    db_prepare($sql, $params);

    return (int) db()->lastInsertId();
}

/**
 * Update rows matched by a WHERE clause written by the caller, e.g.
 *   db_update('users', ['phone' => $n], 'id = :id', [':id' => 7]);
 */
function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    if ($data === []) {
        return 0;
    }

    $table = db_ident($table);
    $sets = [];

    foreach ($data as $column => $value) {
        $column = db_ident((string) $column);
        $sets[] = $column . ' = :set_' . $column;
        $whereParams[':set_' . $column] = $value;
    }

    $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);

    return db_exec($sql, $whereParams);
}

/**
 * Run a closure inside a transaction and roll back on any exception.
 *
 * @return mixed Whatever the closure returns.
 */
function db_tx(callable $work)
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result = $work($pdo);
        $pdo->commit();

        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Lightweight connectivity probe for a /health endpoint.
 *
 * @return array{ok:bool,driver:string,latency_ms:int|null,error:string|null}
 */
function db_health(): array
{
    $cfg = db_config();
    $start = microtime(true);

    try {
        // Deliberately not db(): a failed connection must return a status,
        // not terminate the request.
        $pdo = new PDO(db_dsn($cfg), $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->query('SELECT 1');
        $latency = (int) round((microtime(true) - $start) * 1000);

        return ['ok' => true, 'driver' => $cfg['driver'], 'latency_ms' => $latency, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'driver' => $cfg['driver'], 'latency_ms' => null, 'error' => $e->getMessage()];
    }
}
