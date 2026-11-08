<?php

declare(strict_types=1);

/**
 * Reminder job. Run every 5 minutes from cron:
 *
 *   every-5-minutes  php /path/to/e-hadirgo/cron/reminders.php
 *
 * Sends:
 *   h1  — the day before an assignment
 *   h30 — 30 minutes before the start time
 *
 * Deduped through assignment_reminders, so a missed or repeated run is safe.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$now = now_local();
$sent = 0;
$skipped = 0;

// ---------------------------------------------------------------- H-1 ------
$tomorrow = $now->modify('+1 day')->format('Y-m-d');

$rows = db_all(
    "SELECT a.*, u.name AS staff_name
       FROM assignments a
       JOIN users u ON u.id = a.staff_id
      WHERE a.assignment_date = ?
        AND a.status IN ('dijadualkan', 'dalam_tindakan')",
    [$tomorrow]
);

foreach ($rows as $a) {
    $already = db_val(
        'SELECT id FROM assignment_reminders WHERE assignment_id = ? AND kind = ?',
        [(int) $a['id'], 'h1']
    );

    if ($already !== null) {
        $skipped++;
        continue;
    }

    notify_event((int) $a['staff_id'], 'reminder_h1', [
        'purpose'  => (string) $a['purpose'],
        'location' => (string) $a['location_name'],
        'date'     => (string) $a['assignment_date'],
        'time'     => substr((string) $a['start_time'], 0, 5) . ' – ' . substr((string) $a['end_time'], 0, 5),
        'radius'   => (int) $a['radius_m'],
    ], (int) $a['id']);

    db_insert('assignment_reminders', ['assignment_id' => (int) $a['id'], 'kind' => 'h1']);
    $sent++;
}

// --------------------------------------------------------------- H-30 ------
$today = $now->format('Y-m-d');
$from = $now->modify('+25 minutes')->format('H:i:s');
$to = $now->modify('+35 minutes')->format('H:i:s');

$rows = db_all(
    "SELECT a.*, u.name AS staff_name
       FROM assignments a
       JOIN users u ON u.id = a.staff_id
      WHERE a.assignment_date = ?
        AND a.start_time BETWEEN ? AND ?
        AND a.status = 'dijadualkan'",
    [$today, $from, $to]
);

foreach ($rows as $a) {
    $already = db_val(
        'SELECT id FROM assignment_reminders WHERE assignment_id = ? AND kind = ?',
        [(int) $a['id'], 'h30']
    );

    if ($already !== null) {
        $skipped++;
        continue;
    }

    notify_event((int) $a['staff_id'], 'reminder_h30', [
        'purpose'  => (string) $a['purpose'],
        'location' => (string) $a['location_name'],
        'date'     => (string) $a['assignment_date'],
        'time'     => substr((string) $a['start_time'], 0, 5),
        'radius'   => (int) $a['radius_m'],
    ], (int) $a['id']);

    db_insert('assignment_reminders', ['assignment_id' => (int) $a['id'], 'kind' => 'h30']);
    $sent++;
}

// ------------------------------------------------------------ no-shows -----
$yesterday = $now->modify('-1 day')->format('Y-m-d');

$missing = db_all(
    "SELECT a.id, a.staff_id, a.supervisor_id, a.location_name, a.assignment_date, u.name AS staff_name
       FROM assignments a
       JOIN users u ON u.id = a.staff_id
       LEFT JOIN attendance att ON att.assignment_id = a.id
      WHERE a.assignment_date IN (?, ?)
        AND a.status = 'dijadualkan'
        AND att.id IS NULL",
    [$yesterday, $today]
);

foreach ($missing as $a) {
    // Only close it after the end time has passed.
    $end = local_dt((string) $a['assignment_date'], '23:59:59');
    if ($now < $end) {
        continue;
    }

    db_exec("UPDATE assignments SET status = 'tidak_hadir', updated_at = ? WHERE id = ?", [
        gmdate('Y-m-d H:i:s'),
        (int) $a['id'],
    ]);

    notify_event((int) $a['supervisor_id'], 'late_alert', [
        'staff'    => (string) $a['staff_name'],
        'date'     => (string) $a['assignment_date'],
        'location' => (string) $a['location_name'],
        'status'   => t('status.absent'),
        'reason'   => '—',
    ], (int) $a['id']);

    $sent++;
}

printf("[%s] reminders sent=%d skipped=%d\n", $now->format('Y-m-d H:i:s'), $sent, $skipped);
