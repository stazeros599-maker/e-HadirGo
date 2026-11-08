<?php

declare(strict_types=1);

/**
 * seed.php — load the demo roster described in Section 13 of the specification.
 *
 * CLI only. Idempotent: existing emails are skipped, so it is safe to re-run.
 *
 *   php db/seed.php
 *
 * Every account is flagged is_dummy = 1 and is excluded from production
 * reports. Password for all demo accounts: HadirGo#2026
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Optional connection string, so no environment variable is needed:
//   php db/seed.php "mysql://user:pass@host:3306/db"
if (isset($argv[1])) {
    putenv('DATABASE_URL=' . $argv[1]);
}

require __DIR__ . '/db.php';

const DEMO_PASSWORD = 'HadirGo#2026';

/** @var array<string,int> "Daerah|Bahagian" => unit id */
$units = [];
foreach (db_all('SELECT id, daerah, bahagian FROM units') as $row) {
    $units[$row['daerah'] . '|' . $row['bahagian']] = (int) $row['id'];
}

if ($units === []) {
    fwrite(STDERR, "No units found. Run php db/migrate.php (schema.mysql.sql) first.\n");
    exit(1);
}

$people = [
    ['Administrator Sistem',        'admin.hadirgo@sabah.gov.my',   'Kota Kinabalu', 'Pengurusan',            ['staf', 'super_admin']],
    ['Mohd Faizal bin Osman',       'faizal.osman@sabah.gov.my',    'Kota Kinabalu', 'Perikanan Tangkapan',   ['staf', 'penyelia']],
    ['Nurul Aisyah binti Rahman',   'nurul.aisyah@sabah.gov.my',    'Kota Kinabalu', 'Perikanan Tangkapan',   ['staf']],
    ['Jacqueline anak Jantan',      'jackie.jantan@sabah.gov.my',   'Sandakan',      'Akuakultur',            ['staf']],
    ['Ahmad Zulkifli bin Hamzah',   'zulkifli.hamzah@sabah.gov.my', 'Sandakan',      'Pengurusan',            ['staf', 'penyelia']],
    ['Siti Khadijah binti Yusof',   'khadijah.yusof@sabah.gov.my',  'Tawau',         'Perikanan Tangkapan',   ['staf']],
    ['Lim Wei Sheng',               'weisheng.lim@sabah.gov.my',    'Tawau',         'Akuakultur',            ['staf']],
    ['Dayang Norhayati binti Awang','norhayati.awang@sabah.gov.my', 'Lahad Datu',    'Penguatkuasaan',        ['staf', 'penyelia']],
    ['Ronaldo anak Belayan',        'ronaldo.belayan@sabah.gov.my', 'Kudat',         'Perikanan Tangkapan',   ['staf']],
    ['Pengguna Dummy',              'dummy@dummy.test',             'Kota Kinabalu', 'Pengurusan',            ['staf']],
];

$created = 0;
$skipped = 0;
$adminId = null;

printf("%-30s %-38s %s\n", 'NAMA', 'EMEL', 'PERANAN');
echo str_repeat('-', 92), "\n";

foreach ($people as [$name, $email, $daerah, $bahagian, $roles]) {
    $key = $daerah . '|' . $bahagian;

    if (!isset($units[$key])) {
        fwrite(STDERR, "Unit not found: {$key} — skipped {$email}\n");
        continue;
    }

    $existing = db_val('SELECT id FROM users WHERE email = ?', [$email]);

    if ($existing !== null) {
        $userId = (int) $existing;
        $skipped++;
    } else {
        $userId = db_insert('users', [
            'email'          => $email,
            'name'           => $name,
            'password_hash'  => password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT),
            'unit_id'        => $units[$key],
            'email_verified' => true,
            'is_dummy'       => true,
            'language'       => 'ms',
            'is_active'      => true,
        ]);
        $created++;
    }

    // Roles and channel are ensured even for an account created by an
    // earlier partial run, so re-running the seed always repairs them.
    foreach ($roles as $role) {
        $has = db_val(
            'SELECT 1 AS ok FROM user_roles WHERE user_id = ? AND role_code = ?',
            [$userId, $role]
        );

        if ($has === null) {
            db_insert('user_roles', [
                'user_id'    => $userId,
                'role_code'  => $role,
                'granted_by' => null,
            ]);
        }
    }

    $channel = db_val(
        'SELECT id FROM notification_channels WHERE user_id = ? AND channel_type = ?',
        [$userId, 'email']
    );

    if ($channel === null) {
        db_insert('notification_channels', [
            'user_id'      => $userId,
            'channel_type' => 'email',
            'identifier'   => $email,
            'verified'     => true,
            'is_primary'   => true,
            'linked_at'    => gmdate('Y-m-d H:i:s'),
        ]);
    }

    if ($email === 'admin.hadirgo@sabah.gov.my') {
        $adminId = $userId;
    }

    printf("%-30s %-38s %s%s\n", $name, $email, implode(',', $roles), $existing !== null ? ' (sedia ada)' : '');
}

// Attach the seeded locations to the admin account so they look maintained.
if ($adminId !== null) {
    db_update('saved_locations', ['created_by' => $adminId], 'created_by IS NULL');
}

echo str_repeat('-', 92), "\n";
printf("Dicipta: %d   Dilangkau: %d   Jumlah: %d\n", $created, $skipped, count($people));
printf("Kata laluan demo: %s\n", DEMO_PASSWORD);
