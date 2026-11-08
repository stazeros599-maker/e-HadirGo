<?php

declare(strict_types=1);

/**
 * Location and time rules.
 *
 * The server always recomputes distance and timing from the raw coordinates;
 * the browser's own calculation is only a preview shown to the user.
 */

function haversine_m(float $lat1, float $lng1, float $lat2, float $lng2): int
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return (int) round(2 * $earth * asin(min(1.0, sqrt($a))));
}

/**
 * Normalise one geocoder hit to the place-search shape.
 *
 * @return array{lat:string,lon:string,name:string,display_name:string}|null
 */
function place_point(float $lat, float $lng, string $name, string $displayName = ''): ?array
{
    if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return null;
    }

    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $displayName = trim($displayName);

    return [
        'lat'          => (string) $lat,
        'lon'          => (string) $lng,
        'name'         => mb_substr($name, 0, 180),
        'display_name' => mb_substr($displayName !== '' ? $displayName : $name, 0, 300),
    ];
}

/**
 * Convert a Nominatim JSONv2 response to the place-search shape.
 *
 * @param array<mixed> $response
 * @return list<array{lat:string,lon:string,name:string,display_name:string}>
 */
function nominatim_places_results(array $response): array
{
    $places = [];
    foreach ($response as $result) {
        if (!is_array($result) || !is_numeric($result['lat'] ?? null) || !is_numeric($result['lon'] ?? null)) {
            continue;
        }

        $displayName = (string) ($result['display_name'] ?? '');
        $name = trim((string) ($result['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) strtok($displayName, ','));
        }

        $point = place_point((float) $result['lat'], (float) $result['lon'], $name, $displayName);
        if ($point !== null) {
            $places[] = $point;
        }
    }

    return $places;
}

/**
 * Convert a Photon GeoJSON response to the place-search shape.
 *
 * Photon indexes the same OpenStreetMap data with a fuzzy search that finds
 * many Sabah places Nominatim's free-form ranking misses, so it is used as a
 * second provider rather than replacing Nominatim.
 *
 * @param array<mixed> $response
 * @return list<array{lat:string,lon:string,name:string,display_name:string}>
 */
function photon_places_results(array $response): array
{
    $features = $response['features'] ?? null;
    if (!is_array($features)) {
        return [];
    }

    $places = [];
    foreach ($features as $feature) {
        $properties = is_array($feature) ? ($feature['properties'] ?? null) : null;
        $coordinates = is_array($feature) ? ($feature['geometry']['coordinates'] ?? null) : null;
        if (!is_array($properties) || !is_array($coordinates) || count($coordinates) < 2
            || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
            continue;
        }

        $country = strtoupper((string) ($properties['countrycode'] ?? ''));
        if ($country !== '' && $country !== 'MY') {
            continue;
        }

        $name = trim((string) ($properties['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($properties['street'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string) ($properties['city'] ?? ''));
        }

        $parts = [];
        foreach (['name', 'street', 'city', 'state', 'postcode', 'country'] as $key) {
            $value = trim((string) ($properties[$key] ?? ''));
            if ($value !== '' && !in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }

        $point = place_point((float) $coordinates[1], (float) $coordinates[0], $name, implode(', ', $parts));
        if ($point !== null) {
            $places[] = $point;
        }
    }

    return $places;
}

/**
 * Merge provider hits: drop duplicates, measure from the map centre, nearest first.
 *
 * @param list<array<string,string>> $places
 * @return list<array{lat:string,lon:string,name:string,display_name:string,distance_m:int}>
 */
function places_finalize(array $places, float $biasLat, float $biasLng, int $limit = 8): array
{
    $unique = [];
    foreach ($places as $place) {
        if (!is_array($place) || !is_numeric($place['lat'] ?? null) || !is_numeric($place['lon'] ?? null)) {
            continue;
        }

        $point = place_point(
            (float) $place['lat'],
            (float) $place['lon'],
            (string) ($place['name'] ?? ''),
            (string) ($place['display_name'] ?? '')
        );
        if ($point === null) {
            continue;
        }

        $lat = (float) $point['lat'];
        $lng = (float) $point['lon'];

        // The same place can come back from both providers; keep the first.
        $duplicate = false;
        foreach ($unique as $existing) {
            $gap = haversine_m($lat, $lng, (float) $existing['lat'], (float) $existing['lon']);
            if ($gap <= 80
                || (mb_strtolower($point['name']) === mb_strtolower($existing['name']) && $gap <= 2000)) {
                $duplicate = true;
                break;
            }
        }
        if ($duplicate) {
            continue;
        }

        $point['distance_m'] = haversine_m($biasLat, $biasLng, $lat, $lng);
        $unique[] = $point;
    }

    usort($unique, static fn (array $a, array $b): int => $a['distance_m'] <=> $b['distance_m']);

    // If something close matches, the query is local, so namesakes in other
    // states are noise (a Selangor "Taman Tun Fuad" beat the Sabah one before).
    if ($unique !== [] && $unique[0]['distance_m'] <= 50000) {
        $unique = array_values(array_filter(
            $unique,
            static fn (array $place): bool => $place['distance_m'] <= 400000
        ));
    }

    return array_slice($unique, 0, max(1, $limit));
}

/**
 * Apply the attendance rules from the specification.
 *
 * @return array{
 *   distance_m:int|null,
 *   outside_radius:bool,
 *   location_unavailable:bool,
 *   weak_accuracy:bool,
 *   is_early:bool,
 *   is_late:bool,
 *   past_end:bool,
 *   requires_note:bool,
 *   requires_reason:bool,
 *   status:string
 * }
 */
function evaluate_checkin(array $assignment, DateTimeImmutable $now, ?float $lat, ?float $lng, ?int $accuracy): array
{
    $start = local_dt((string) $assignment['assignment_date'], (string) $assignment['start_time']);
    $end = local_dt((string) $assignment['assignment_date'], (string) $assignment['end_time']);
    $graceEnd = $start->modify('+15 minutes');

    $distance = null;
    if ($lat !== null && $lng !== null) {
        $distance = haversine_m($lat, $lng, (float) $assignment['lat'], (float) $assignment['lng']);
    }

    $radius = (int) $assignment['radius_m'];
    $outside = $distance !== null && $distance > $radius;
    $unavailable = $lat === null || $lng === null;
    $weak = $accuracy === null || $accuracy > 100;

    $isEarly = $now < $start;
    $isLate = $now > $graceEnd;
    $pastEnd = $now > $end;

    if ($unavailable || $outside) {
        $status = 'luar_kawasan';
    } elseif ($isLate) {
        $status = 'lewat';
    } elseif ($isEarly) {
        $status = 'awal';
    } else {
        $status = 'tepat_masa';
    }

    return [
        'distance_m'           => $distance,
        'outside_radius'       => $outside,
        'location_unavailable' => $unavailable,
        'weak_accuracy'        => $weak,
        'is_early'             => $isEarly,
        'is_late'              => $isLate,
        'past_end'             => $pastEnd,
        'requires_note'        => $outside || $unavailable,
        'requires_reason'      => $isLate || $pastEnd,
        'status'               => $status,
    ];
}

/**
 * Human label for a computed status.
 */
function status_label(string $status): string
{
    $map = [
        'tepat_masa'   => 'status.on_time',
        'awal'         => 'status.early',
        'lewat'        => 'status.late',
        'luar_kawasan' => 'status.outside',
        'tidak_hadir'  => 'status.absent',
        'dibatalkan'   => 'status.cancelled',
        'selesai'      => 'status.done',
    ];

    return t($map[$status] ?? 'status.on_time');
}

function status_class(string $status): string
{
    return match ($status) {
        'tepat_masa', 'selesai' => 'ok',
        'awal'                  => 'info',
        'lewat'                 => 'warn',
        'luar_kawasan'          => 'bad',
        'tidak_hadir'           => 'bad',
        default                 => 'muted',
    };
}
