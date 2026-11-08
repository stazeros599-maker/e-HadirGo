<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/geo.php';

$checks = 0;
$failures = 0;

$check = static function (string $name, bool $passed) use (&$checks, &$failures): void {
    $checks++;
    echo ($passed ? '  ok    ' : '  FAIL  ') . $name . "\n";
    if (!$passed) {
        $failures++;
    }
};

$check('Nominatim hit maps to the place-search shape', nominatim_places_results([[
    'lat' => '5.9804',
    'lon' => '116.0735',
    'name' => 'Suria Sabah',
    'display_name' => 'Suria Sabah, Kota Kinabalu, Sabah, Malaysia',
]]) === [[
    'lat' => '5.9804',
    'lon' => '116.0735',
    'name' => 'Suria Sabah',
    'display_name' => 'Suria Sabah, Kota Kinabalu, Sabah, Malaysia',
]]);

$photon = photon_places_results(['features' => [[
    'properties' => [
        'name' => 'Suria Sabah',
        'city' => 'Kota Kinabalu',
        'state' => 'Sabah',
        'countrycode' => 'MY',
    ],
    'geometry' => ['coordinates' => [116.0771, 5.9875]],
]]]);
$check(
    'Photon feature maps to the place-search shape',
    count($photon) === 1
        && $photon[0]['lat'] === '5.9875'
        && $photon[0]['lon'] === '116.0771'
        && $photon[0]['name'] === 'Suria Sabah'
        && $photon[0]['display_name'] === 'Suria Sabah, Kota Kinabalu, Sabah'
);

$check('Photon hits outside Malaysia are discarded', photon_places_results(['features' => [[
    'properties' => ['name' => 'Foreign place', 'countrycode' => 'ID'],
    'geometry' => ['coordinates' => [106.8, -6.2]],
]]]) === []);

$merged = places_finalize([
    ['lat' => '5.9874876', 'lon' => '116.0771416', 'name' => 'Suria Sabah', 'display_name' => 'Suria Sabah, Kota Kinabalu'],
    ['lat' => '5.9874878', 'lon' => '116.0771419', 'name' => 'Suria Sabah', 'display_name' => 'Suria Sabah'],
], 5.9804, 116.0735, 8);
$check('places_finalize keeps one copy of a duplicated place', count($merged) === 1);

$sorted = places_finalize([
    ['lat' => '5.9559032', 'lon' => '116.1054397', 'name' => 'Further', 'display_name' => 'Further'],
    ['lat' => '5.9874876', 'lon' => '116.0771416', 'name' => 'Nearer', 'display_name' => 'Nearer'],
], 5.9804, 116.0735, 8);
$check(
    'places_finalize orders nearest first and measures from the map centre',
    count($sorted) === 2
        && $sorted[0]['name'] === 'Nearer'
        && $sorted[1]['name'] === 'Further'
        && $sorted[0]['distance_m'] > 0
        && $sorted[0]['distance_m'] < $sorted[1]['distance_m']
);

$local = places_finalize([
    ['lat' => '5.9874876', 'lon' => '116.0771416', 'name' => 'Local', 'display_name' => 'Local'],
    ['lat' => '3.1414160', 'lon' => '101.6291793', 'name' => 'Distant namesake', 'display_name' => 'Distant namesake'],
], 5.9804, 116.0735, 8);
$check(
    'places_finalize drops a distant namesake when a local match exists',
    count($local) === 1 && $local[0]['name'] === 'Local'
);

$check('places_finalize discards invalid coordinates', places_finalize([
    ['lat' => '95', 'lon' => '181', 'name' => 'Bad', 'display_name' => 'Bad'],
], 5.9804, 116.0735) === []);

echo "\npassed: " . ($checks - $failures) . "  failed: " . $failures . "\n";
exit($failures === 0 ? 0 : 1);
