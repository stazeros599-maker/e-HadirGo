<?php

declare(strict_types=1);

$testUser = ['id' => 2];
$testGroupMembers = [1, 2];
$testPage = null;
$testResults = [];
$requestedRole = null;
$testFlashes = [];

function require_login(): void {}

function csrf_verify(): void {}

function current_user(): ?array
{
    return $GLOBALS['testUser'];
}

function has_role(string $role): bool
{
    return false;
}

function require_role(string $role): void
{
    $GLOBALS['requestedRole'] = $role;
    throw new RuntimeException('Authorization check intercepted by test.');
}

function assignment_by_id(int $id): ?array
{
    return [
        'id' => $id,
        'staff_id' => 1,
        'participant_group_id' => 'group123',
        'assignment_date' => '2026-11-08',
        'start_time' => '08:00:00',
        'status' => 'dijadualkan',
    ];
}

function assignment_group_contains_staff(string $groupId, int $staffId): bool
{
    return $groupId === 'group123' && in_array($staffId, $GLOBALS['testGroupMembers'], true);
}

function assignment_group_participants(string $groupId): array
{
    return [];
}

function attendance_by_assignment(int $assignmentId): ?array
{
    return null;
}

function evaluate_checkin(array $assignment, DateTimeImmutable $now, ?float $lat, ?float $lng, ?int $accuracy): array
{
    return [];
}

function now_local(): DateTimeImmutable
{
    return new DateTimeImmutable();
}

function local_dt(string $date, string $time): DateTimeImmutable
{
    return new DateTimeImmutable($date . ' ' . $time);
}

function t(string $key): string
{
    return $key;
}

function url(string $route = '', array $params = []): string
{
    return $route;
}

function flash(string $type, string $message): void
{
    $GLOBALS['testFlashes'][] = [$type, $message];
}

function redirect(string $target): void
{
    throw new RuntimeException('Redirected to ' . $target);
}

function page(string $name, array $data = []): void
{
    $GLOBALS['testPage'] = [$name, $data];
}

require dirname(__DIR__) . '/app/controllers/staff.php';

function check_assignment_access(int $userId, int $expectedCode, string $description): void
{
    $GLOBALS['testUser'] = ['id' => $userId];
    $GLOBALS['testPage'] = null;
    $_GET['id'] = '7';
    http_response_code(200);
    staff_assignment();

    $actualCode = http_response_code();
    if ($actualCode !== $expectedCode) {
        fwrite(STDERR, "FAIL: {$description}\n");
        exit(1);
    }

    $GLOBALS['testResults'][] = $description;
}

check_assignment_access(1, 200, 'assignment owner can view their attendance');
check_assignment_access(2, 200, 'participant in the same assignment group can view attendance');
$testGroupMembers = [1];
check_assignment_access(3, 403, 'staff outside the assignment group cannot view attendance');

$testUser = ['id' => 2];
$_POST['id'] = '7';
$redirectedToDashboard = false;
try {
    staff_checkin();
} catch (RuntimeException $e) {
    $redirectedToDashboard = $e->getMessage() === 'Redirected to dashboard';
}

if (!$redirectedToDashboard || ($testFlashes[0][0] ?? '') !== 'error') {
    fwrite(STDERR, "FAIL: a group participant cannot submit another staff member's attendance\n");
    exit(1);
}
$testResults[] = 'group participants cannot submit another staff member\'s attendance';

require dirname(__DIR__) . '/app/controllers/supervisor.php';
$authorizationCheckReached = false;
try {
    sup_form();
} catch (RuntimeException $e) {
    $authorizationCheckReached = $e->getMessage() === 'Authorization check intercepted by test.';
}

if (!$authorizationCheckReached || $requestedRole !== 'super_admin') {
    fwrite(STDERR, "FAIL: assignment creation and editing must require super_admin\n");
    exit(1);
}
$testResults[] = 'assignment creation and editing require super_admin';

foreach ($testResults as $result) {
    echo "ok: {$result}\n";
}
