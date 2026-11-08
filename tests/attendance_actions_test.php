<?php

declare(strict_types=1);

$testDatabaseCalls = [];
$testFlashes = [];
$testAttendance = [
    'id' => 12,
    'check_out_at' => null,
];

function require_login(): void {}
function require_supervisor(): void {}
function csrf_verify(): void {}
function current_user(): ?array { return ['id' => 2]; }
function t(string $key): string { return $key; }
function url(string $route = '', array $params = []): string { return $route; }
function flash(string $type, string $message): void { $GLOBALS['testFlashes'][] = [$type, $message]; }
function redirect(string $target): void { throw new RuntimeException('redirect:' . $target); }
function assignment_by_id(int $id): ?array
{
    return [
        'id' => $id,
        'staff_id' => 2,
        'supervisor_id' => 2,
        'lat' => 5.98,
        'lng' => 116.07,
        'radius_m' => 250,
        'assignment_date' => '2026-11-08',
        'location_name' => 'Test location',
    ];
}
function attendance_by_assignment(int $id): ?array { return $GLOBALS['testAttendance']; }
function now_local(): DateTimeImmutable { return new DateTimeImmutable('2026-11-08 10:00:00'); }
function to_utc(DateTimeImmutable $date): string { return $date->format('Y-m-d H:i:s'); }
function haversine_m(float $lat1, float $lng1, float $lat2, float $lng2): int { return 12; }
function db_exec(string $sql, array $params = []): int
{
    $GLOBALS['testDatabaseCalls'][] = [$sql, $params];
    return 1;
}
function audit_write(?int $actorId, string $action, string $entity, ?int $entityId, $before = null, $after = null): void {}
function notify_event(int $userId, string $event, array $data = [], ?int $assignmentId = null): void {}

require dirname(__DIR__) . '/app/controllers/staff.php';
require dirname(__DIR__) . '/app/controllers/supervisor.php';

$_POST = ['id' => '7', 'lat' => '5.981', 'lng' => '116.071'];
try {
    staff_checkout();
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'redirect:assignment') {
        throw $e;
    }
}

$attendanceUpdate = $testDatabaseCalls[0] ?? null;
$assignmentUpdate = $testDatabaseCalls[1] ?? null;
if ($attendanceUpdate === null || strpos($attendanceUpdate[0], 'UPDATE attendance SET check_out_at') === false
    || $attendanceUpdate[1][1] !== 5.981 || $attendanceUpdate[1][2] !== 116.071
    || $assignmentUpdate === null || $assignmentUpdate[1][0] !== 'selesai') {
    fwrite(STDERR, "FAIL: check-out was not recorded with its coordinates and completed status\n");
    exit(1);
}

$testDatabaseCalls = [];
$_POST = ['assignment_id' => '7', 'decision' => 'terima', 'note' => ''];
try {
    sup_decide();
} catch (RuntimeException $e) {
    if ($e->getMessage() !== 'redirect:sup.review') {
        throw $e;
    }
}

$decisionUpdate = $testDatabaseCalls[0] ?? null;
if ($decisionUpdate === null || strpos($decisionUpdate[0], 'UPDATE attendance SET supervisor_decision') === false
    || $decisionUpdate[1][1] !== null) {
    fwrite(STDERR, "FAIL: supervisor decision did not accept an empty optional note\n");
    exit(1);
}

echo "ok: check-out records coordinates and completes the assignment\n";
echo "ok: supervisor decision accepts an empty optional note\n";
