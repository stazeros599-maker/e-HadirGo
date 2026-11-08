<?php

declare(strict_types=1);

/**
 * Pure logic tests — no database, no HTTP.
 *
 *   php tests/logic_test.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

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

function sample(array $overrides = []): array
{
    return array_merge([
        'assignment_date' => '2026-11-06',
        'start_time'      => '08:30:00',
        'end_time'        => '12:00:00',
        'lat'             => 5.980400,
        'lng'             => 116.073500,
        'radius_m'        => 250,
    ], $overrides);
}

echo "haversine_m\n";
check('local timezone is Asia/Kuching', date_default_timezone_get() === 'Asia/Kuching' && local_tz()->getName() === 'Asia/Kuching');
check('same point is zero', haversine_m(5.9804, 116.0735, 5.9804, 116.0735) === 0);
check('one degree of latitude is ~111 km', abs(haversine_m(5.0, 116.0, 6.0, 116.0) - 111195) < 500);
check('small offset is metres', haversine_m(5.9804, 116.0735, 5.9804, 116.0745) > 90);

echo "\nevaluate_checkin — timing\n";
$base = new DateTimeImmutable('2026-11-06 08:00:00', local_tz());
$r = evaluate_checkin(sample(), $base, 5.9804, 116.0735, 10);
check('before start is early', $r['is_early'] === true && $r['is_late'] === false);
check('before start status is awal', $r['status'] === 'awal');
check('before start needs no reason', $r['requires_reason'] === false);

$at = new DateTimeImmutable('2026-11-06 08:40:00', local_tz());
$r = evaluate_checkin(sample(), $at, 5.9804, 116.0735, 10);
check('within grace is on time', $r['is_late'] === false);
check('within grace status is tepat_masa', $r['status'] === 'tepat_masa');

$late = new DateTimeImmutable('2026-11-06 08:46:00', local_tz());
$r = evaluate_checkin(sample(), $late, 5.9804, 116.0735, 10);
check('sixteen minutes late is late', $r['is_late'] === true);
check('late requires a reason', $r['requires_reason'] === true);
check('late status is lewat', $r['status'] === 'lewat');

$afterEnd = new DateTimeImmutable('2026-11-06 12:30:00', local_tz());
$r = evaluate_checkin(sample(), $afterEnd, 5.9804, 116.0735, 10);
check('after end flags past_end', $r['past_end'] === true);
check('after end still requires a reason', $r['requires_reason'] === true);

echo "\nevaluate_checkin — location\n";
$inside = new DateTimeImmutable('2026-11-06 08:30:00', local_tz());
$r = evaluate_checkin(sample(), $inside, 5.9809, 116.0735, 8);
check('60 m away is inside a 250 m radius', $r['outside_radius'] === false && $r['distance_m'] < 100);

$r = evaluate_checkin(sample(), $inside, 6.0000, 116.0735, 8);
check('2 km away is outside', $r['outside_radius'] === true);
check('outside requires a note', $r['requires_note'] === true);
check('outside status is luar_kawasan', $r['status'] === 'luar_kawasan');

$r = evaluate_checkin(sample(), $inside, null, null, null);
check('missing coordinates are unavailable', $r['location_unavailable'] === true);
check('unavailable requires a note', $r['requires_note'] === true);

$r = evaluate_checkin(sample(), $inside, 5.9804, 116.0735, 240);
check('weak accuracy is reported', $r['weak_accuracy'] === true);

echo "\nstatus helpers\n";
check('status_class maps on time', status_class('tepat_masa') === 'ok');
check('status_class maps outside', status_class('luar_kawasan') === 'bad');

echo "\nutc_timestamp — stored datetime shapes\n";
$naive = utc_timestamp('2026-11-06 01:00:00');          // MySQL DATETIME
$tz = utc_timestamp('2026-11-06 01:00:00+00');          // explicit offset
check('both shapes parse', $naive !== null && $tz !== null);
check('both shapes agree', $naive === $tz);
check('matches gmmktime', $naive === gmmktime(1, 0, 0, 11, 6, 2026));
check('null stays null', utc_timestamp(null) === null);
check('garbage is null', utc_timestamp('not-a-date') === null);

echo "\n" . str_repeat('-', 48) . "\n";
echo "passed: {$passed}  failed: {$failed}\n";

exit($failed === 0 ? 0 : 1);
