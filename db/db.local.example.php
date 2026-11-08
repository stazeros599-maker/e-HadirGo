<?php

/**
 * Local database override — copy this file to db.local.php and fill it in.
 *
 * Use it only when the host cannot provide environment variables
 * (classic shared hosting). Everywhere else, leave it out: db.php reads
 * DATABASE_URL, MYSQL_ADDON_* or DB_* from the environment.
 *
 * db.local.php is listed in .gitignore. Never commit real credentials.
 */

return [
    'host'   => '127.0.0.1',
    'port'   => 3306,
    'name'   => 'ehadirgo',
    'user'   => 'root',
    'pass'   => '',
];
