# e-HadirGo — database layer

| File | Purpose |
|---|---|
| `schema.mysql.sql` | Complete schema for MySQL 8.0.16+ / MariaDB 10.6+ |
| `db.php` | PDO connection + query helpers |
| `seed.php` | Loads the dummy roster from the specification |
| `check.php` | Prints the resolved config and tests the connection |
| `migrate.php` | Applies `schema.mysql.sql` over PDO (no `mysql` client needed) |
| `db.local.example.php` | Copy to `db.local.php` on hosts without env vars |

12 tables, one reporting view, all foreign keys, checks and indexes.

## Clever Cloud setup

1. Create a **MySQL** add-on and link it to the application. Clever Cloud injects
   `MYSQL_ADDON_HOST`, `MYSQL_ADDON_PORT`, `MYSQL_ADDON_DB`, `MYSQL_ADDON_USER`,
   `MYSQL_ADDON_PASSWORD`. Nothing is hardcoded.
2. Import the schema (locally, with the add-on's credentials):

   ```bash
   DATABASE_URL="mysql://user:pass@host:3306/db" php db/migrate.php
   ```

3. Load the demo roster:

   ```bash
   php db/seed.php
   ```

   All demo accounts use the password `HadirGo#2026` and carry `is_dummy = 1`.

## Local development

Create a database named `ehadirgo`, import `schema.mysql.sql`, then either set

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=ehadirgo
DB_USER=root
DB_PASS=
```

or copy `db.local.example.php` to `db.local.php` and fill it in.

## Using db.php

```php
require __DIR__ . '/db/db.php';

$user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
$rows = db_all('SELECT * FROM v_attendance_report WHERE tarikh = ?', [$date]);
$id   = db_insert('assignments', ['staff_id' => 4, 'supervisor_id' => 2 /* ... */]);
$n    = db_update('users', ['phone' => $phone], 'id = :id', [':id' => $userId]);

db_tx(function (PDO $pdo) use ($assignmentId) {
    db_exec('UPDATE assignments SET status = ? WHERE id = ?', ['dalam_tindakan', $assignmentId]);
    db_insert('attendance', ['assignment_id' => $assignmentId /* ... */]);
});
```

`db_health()` returns a small array suitable for a `/health` endpoint.

## Conventions

- **Time**: every `DATETIME` holds UTC. Sessions are pinned to `+00:00`; convert to
  `Asia/Kuching` only when rendering.
- **Booleans**: passed as PHP `true`/`false`; `db_prepare()` binds them with
  `PDO::PARAM_BOOL`, so `false` never becomes an empty string under strict mode.
- **Identifiers**: `db_insert()` / `db_update()` reject anything that is not a
  plain `[A-Za-z][A-Za-z0-9_]*` name.
- **No credentials in code**: `db.php` reads the environment first and only
  falls back to local defaults.
- **Roles** live in `user_roles`, so each grant has an author and a timestamp.
- **Errors** are logged, never echoed; the browser receives a generic 503.
