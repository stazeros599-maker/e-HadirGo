#!/usr/bin/env bash
#
# Waits for MySQL, applies the schema once, loads the demo roster,
# then hands over to Apache.

set -euo pipefail

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-ehadirgo}"
DB_USER="${DB_USER:-ehadirgo}"
DB_PASS="${DB_PASS:-ehadirgo}"

mysql_cmd() {
    MYSQL_PWD="${DB_PASS}" mysql --protocol=TCP -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" "$@"
}

echo "[entrypoint] waiting for ${DB_HOST}:${DB_PORT} …"
db_ready=0
for _ in $(seq 1 60); do
    if MYSQL_PWD="${DB_PASS}" mysqladmin --protocol=TCP -h "${DB_HOST}" -P "${DB_PORT}" \
         -u "${DB_USER}" ping --silent >/dev/null 2>&1; then
        echo "[entrypoint] database is up"
        db_ready=1
        break
    fi
    sleep 2
done
if [ "${db_ready}" != "1" ]; then
    echo "[entrypoint] database did not become ready in time" >&2
    exit 1
fi

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    if [ "$(mysql_cmd -N -B -e "SHOW TABLES LIKE 'users'" "${DB_NAME}")" = "users" ]; then
        echo "[entrypoint] schema already present"
    else
        echo "[entrypoint] applying schema.mysql.sql"
        mysql_cmd "${DB_NAME}" < db/schema.mysql.sql
    fi

    echo "[entrypoint] applying assignment participant-group migration"
    php db/migrations/20261108_assignment_participant_group.php
fi

if [ "${RUN_SEED:-1}" = "1" ]; then
    echo "[entrypoint] loading demo roster"
    php db/seed.php
fi

echo "[entrypoint] ready → http://localhost:8080  (demo login: admin.hadirgo@sabah.gov.my / HadirGo#2026)"

exec "$@"
