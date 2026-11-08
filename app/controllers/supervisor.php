<?php

declare(strict_types=1);

/**
 * Supervisor routes: assignment list, create, live map, review, report.
 */

function sup_assignments(): void
{
    require_supervisor();

    $filters = [
        'date'   => (string) ($_GET['date'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
    ];

    page('sup/list', [
        'title'   => t('sup.title'),
        'rows'    => supervisor_assignments((int) current_user()['id'], $filters),
        'filters' => $filters,
        'counts'  => dashboard_counts((int) current_user()['id']),
    ]);
}

function sup_form(): void
{
    require_role('super_admin');

    $editId = (int) ($_GET['id'] ?? 0);
    $assignment = $editId > 0 ? assignment_by_id($editId) : null;

    if ($editId > 0 && $assignment === null) {
        http_response_code(404);
        page('error', ['title' => '404', 'code' => 404, 'message' => t('err.notfound')]);

        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();

        $postedStaffIds = is_array($_POST['staff_ids'] ?? null) ? $_POST['staff_ids'] : [];
        $staffIds = $editId > 0
            ? [(int) ($_POST['staff_id'] ?? 0)]
            : array_values(array_unique(array_filter(
                array_map('intval', array_filter($postedStaffIds, 'is_scalar')),
                static fn (int $id): bool => $id > 0
            )));
        $data = [
            'assignment_date' => (string) ($_POST['assignment_date'] ?? ''),
            'start_time'      => (string) ($_POST['start_time'] ?? ''),
            'end_time'        => (string) ($_POST['end_time'] ?? ''),
            'location_name'   => trim((string) ($_POST['location_name'] ?? '')),
            'lat'             => (float) ($_POST['lat'] ?? 0),
            'lng'             => (float) ($_POST['lng'] ?? 0),
            'radius_m'        => (int) ($_POST['radius_m'] ?? 250),
            'purpose'         => trim((string) ($_POST['purpose'] ?? '')),
        ];
        if ($editId > 0) {
            $data['staff_id'] = $staffIds[0] ?? 0;
        }

        $problem = null;
        $validStaffIds = array_map('intval', array_column(staff_options(), 'id'));
        $invalidStaffSelection = $editId > 0
            ? $staffIds === [] || user_by_id($staffIds[0]) === null
            : $staffIds === [] || count(array_diff($staffIds, $validStaffIds)) > 0;

        if ($invalidStaffSelection
            || ($editId === 0 && (string) ($_POST['participants_confirmed'] ?? '') !== '1')) {
            $problem = t('sup.err_staff');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['assignment_date'])) {
            $problem = t('sup.err_date');
        } elseif (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['start_time']) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $data['end_time'])) {
            $problem = t('sup.err_time');
        } elseif ($data['end_time'] <= $data['start_time']) {
            $problem = t('sup.err_window');
        } elseif ($data['location_name'] === '' || ($data['lat'] == 0.0 && $data['lng'] == 0.0)) {
            $problem = t('sup.err_location');
        } elseif ($data['radius_m'] < 50 || $data['radius_m'] > 5000) {
            $problem = t('sup.err_radius');
        } elseif (mb_strlen($data['purpose']) < 3) {
            $problem = t('sup.err_purpose');
        }

        if ($problem !== null) {
            flash('error', $problem);
            redirect(url('sup.form', $editId > 0 ? ['id' => $editId] : []));
        }

        $data['start_time'] = strlen($data['start_time']) === 5 ? $data['start_time'] . ':00' : $data['start_time'];
        $data['end_time'] = strlen($data['end_time']) === 5 ? $data['end_time'] . ':00' : $data['end_time'];

        if ($assignment !== null) {
            db_exec(
                'UPDATE assignments SET staff_id = ?, assignment_date = ?, start_time = ?, end_time = ?,
                        location_name = ?, lat = ?, lng = ?, radius_m = ?, purpose = ?, updated_at = ?
                  WHERE id = ?',
                [
                    $data['staff_id'], $data['assignment_date'], $data['start_time'], $data['end_time'],
                    $data['location_name'], $data['lat'], $data['lng'], $data['radius_m'], $data['purpose'],
                    gmdate('Y-m-d H:i:s'), $editId,
                ]
            );
            audit_write((int) current_user()['id'], 'assignment_update', 'assignments', $editId, $assignment, $data);
            flash('ok', t('sup.saved'));
        } else {
            $assignmentIds = [];
            $participantGroupId = bin2hex(random_bytes(16));
            db()->beginTransaction();
            try {
                foreach ($staffIds as $staffId) {
                    $newId = db_insert('assignments', [
                        'staff_id'             => $staffId,
                        'participant_group_id' => $participantGroupId,
                        'supervisor_id'        => (int) current_user()['id'],
                        'purpose'              => $data['purpose'],
                        'assignment_date'      => $data['assignment_date'],
                        'start_time'           => $data['start_time'],
                        'end_time'             => $data['end_time'],
                        'location_name'        => $data['location_name'],
                        'lat'                  => $data['lat'],
                        'lng'                  => $data['lng'],
                        'radius_m'             => $data['radius_m'],
                        'status'               => 'dijadualkan',
                    ]);

                    $participantData = $data + ['staff_id' => $staffId];
                    audit_write((int) current_user()['id'], 'assignment_create', 'assignments', $newId, null, $participantData);
                    $assignmentIds[] = [$staffId, $newId];
                }
                db()->commit();
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $e;
            }

            foreach ($assignmentIds as [$staffId, $newId]) {
                notify_event($staffId, 'assign_new', [
                    'purpose'  => $data['purpose'],
                    'date'     => $data['assignment_date'],
                    'time'     => substr($data['start_time'], 0, 5) . ' – ' . substr($data['end_time'], 0, 5),
                    'location' => $data['location_name'],
                    'radius'   => $data['radius_m'],
                ], $newId);
            }

            flash('ok', t('sup.created'));
        }

        redirect(url('sup.assignments'));
    }

    page('sup/form', [
        'title'      => $assignment === null ? t('sup.new') : t('sup.edit'),
        'assignment' => $assignment,
        'staff'      => staff_options(),
        'locations'  => saved_locations(),
        'today'      => now_local()->format('Y-m-d'),
    ]);
}

function sup_places(): void
{
    require_supervisor();

    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) < 2 || mb_strlen($query) > 150) {
        json_out(['error' => 'invalid_query'], 400);
    }

    [$biasLat, $biasLng] = sup_place_bias();

    $places = [];
    $tried = 0;
    $failed = 0;

    // Both free providers are queried together so a search costs one round
    // trip. Nominatim is biased to the area on screen; Photon's fuzzy index
    // finds many Sabah places that Nominatim ranks away or misses entirely.
    $responses = sup_place_search_many([
        'nominatim' => sup_nominatim_url($query, $biasLat, $biasLng),
        'photon'    => sup_photon_url($query, $biasLat, $biasLng),
    ]);

    $tried += 2;
    foreach ([
        'nominatim' => ['OpenStreetMap', 'nominatim_places_results'],
        'photon'    => ['Photon', 'photon_places_results'],
    ] as $provider => [$label, $normalise]) {
        $found = sup_place_response($responses[$provider] ?? null, $label, $normalise);
        if ($found === null) {
            $failed++;
        } else {
            $places = array_merge($places, $found);
        }
    }

    $places = places_finalize($places, $biasLat, $biasLng, 8);

    if ($places !== []) {
        json_out($places);
    }

    if ($tried > 0 && $failed === $tried) {
        json_out(['error' => 'place_search_unavailable'], 502);
    }

    json_out([]);
}

/**
 * Map centre sent by the browser; falls back to Kota Kinabalu.
 *
 * @return array{0:float,1:float}
 */
function sup_place_bias(): array
{
    $lat = (float) ($_GET['lat'] ?? 0);
    $lng = (float) ($_GET['lng'] ?? 0);

    if (!is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180
        || ($lat === 0.0 && $lng === 0.0)) {
        return [5.9804, 116.0735];
    }

    return [$lat, $lng];
}

/**
 * Nominatim search URL, softly biased to the visible map area.
 */
function sup_nominatim_url(string $query, float $lat, float $lng): string
{
    return 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format'          => 'jsonv2',
        'limit'           => 10,
        'countrycodes'    => 'my',
        'accept-language' => current_lang(),
        'viewbox'         => sprintf('%.4f,%.4f,%.4f,%.4f', $lng - 2.5, $lat + 2.5, $lng + 2.5, $lat - 2.5),
        'bounded'         => 0,
        'q'               => $query,
    ]);
}

/**
 * Photon search URL, biased to the visible map area.
 */
function sup_photon_url(string $query, float $lat, float $lng): string
{
    return 'https://photon.komoot.io/api/?' . http_build_query([
        'q'     => $query,
        'limit' => 10,
        'lat'   => $lat,
        'lon'   => $lng,
    ]);
}

/**
 * Decode one provider response; null means the provider was unusable.
 *
 * @param callable(array<mixed>):list<array{lat:string,lon:string,name:string,display_name:string}> $normalise
 * @return list<array{lat:string,lon:string,name:string,display_name:string}>|null
 */
function sup_place_response(?array $response, string $provider, callable $normalise): ?array
{
    if ($response === null || $response['status'] !== 200) {
        error_log('[e-HadirGo] Place search failed: ' . $provider . ' HTTP ' . (int) ($response['status'] ?? 0));

        return null;
    }

    $data = is_string($response['body']) ? json_decode($response['body'], true) : null;
    if (!is_array($data)) {
        error_log('[e-HadirGo] Place search failed: ' . $provider . ' invalid JSON');

        return null;
    }

    return $normalise($data);
}

/**
 * Run geocoder requests in parallel, falling back to one-by-one.
 *
 * @param array<string,string> $urls provider => URL
 * @return array<string,array{status:int,body:string|false}>
 */
function sup_place_search_many(array $urls): array
{
    if (!function_exists('curl_multi_init') || $urls === []) {
        $responses = [];
        foreach ($urls as $provider => $url) {
            $responses[$provider] = sup_place_search_request($url);
        }

        return $responses;
    }

    $multi = curl_multi_init();
    $handles = [];
    foreach ($urls as $provider => $url) {
        $curl = curl_init($url);
        if ($curl === false) {
            continue;
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'e-HadirGo/1.0',
        ]);
        curl_multi_add_handle($multi, $curl);
        $handles[$provider] = $curl;
    }

    do {
        $state = curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 0.5);
        }
    } while ($running > 0 && $state === CURLM_OK);

    $responses = [];
    foreach ($handles as $provider => $curl) {
        $responses[$provider] = [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'body'   => (string) curl_multi_getcontent($curl),
        ];
        curl_multi_remove_handle($multi, $curl);
        curl_close($curl);
    }
    curl_multi_close($multi);

    return $responses;
}

/**
 * Fetch a geocoder response without logging the requested URL.
 *
 * @return array{status:int,body:string|false}
 */
function sup_place_search_request(string $url): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) {
            return ['status' => 0, 'body' => false];
        }
        $configured = curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'e-HadirGo/1.0',
        ]);
        if (!$configured) {
            curl_close($curl);
            return ['status' => 0, 'body' => false];
        }
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => "Accept: application/json\r\nUser-Agent: e-HadirGo/1.0\r\n",
                'timeout'       => 12,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }
    }

    return ['status' => $status, 'body' => $body];
}

function sup_map(): void
{
    require_supervisor();

    $rows = db_all(
        'SELECT a.*, u.name AS staff_name, att.check_in_at, att.outside_radius, att.is_late
           FROM assignments a
           JOIN users u ON u.id = a.staff_id
           LEFT JOIN attendance att ON att.assignment_id = a.id
          WHERE a.assignment_date = ?
            AND (a.supervisor_id = ? OR u.unit_id = (SELECT unit_id FROM users WHERE id = ?))
          ORDER BY a.start_time',
        [now_local()->format('Y-m-d'), (int) current_user()['id'], (int) current_user()['id']]
    );

    page('sup/map', ['title' => t('sup.map_title'), 'rows' => $rows]);
}

function sup_review(): void
{
    require_supervisor();

    page('sup/review', [
        'title' => t('sup.review_title'),
        'rows'  => review_queue((int) current_user()['id']),
    ]);
}

function sup_decide(): void
{
    require_supervisor();
    csrf_verify();

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['note'] ?? ''));

    if (!in_array($decision, ['terima', 'tolak'], true) || mb_strlen($note) > 255) {
        flash('error', t('sup.err_decision'));
        redirect(url('sup.review'));
    }

    $assignment = assignment_by_id($assignmentId);
    if ($assignment === null) {
        flash('error', t('err.notfound'));
        redirect(url('sup.review'));
    }

    db_exec(
        'UPDATE attendance SET supervisor_decision = ?, supervisor_note = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ?
          WHERE assignment_id = ?',
        [$decision, $note !== '' ? $note : null, (int) current_user()['id'], to_utc(now_local()), gmdate('Y-m-d H:i:s'), $assignmentId]
    );

    audit_write((int) current_user()['id'], 'attendance_review', 'attendance', $assignmentId, null, [
        'decision' => $decision,
        'note' => $note !== '' ? $note : null,
    ]);

    notify_event((int) $assignment['staff_id'], 'review_done', [
        'date'     => (string) $assignment['assignment_date'],
        'location' => (string) $assignment['location_name'],
        'decision' => t('decision.' . $decision),
    ], $assignmentId);

    flash('ok', t('sup.reviewed'));
    redirect(url('sup.review'));
}

function sup_report(): void
{
    require_supervisor();

    $filters = [
        'from'   => (string) ($_GET['from'] ?? now_local()->modify('-30 days')->format('Y-m-d')),
        'to'     => (string) ($_GET['to'] ?? now_local()->format('Y-m-d')),
        'status' => (string) ($_GET['status'] ?? ''),
    ];

    page('sup/report', [
        'title'   => t('sup.report_title'),
        'rows'    => report_rows($filters),
        'filters' => $filters,
    ]);
}

function sup_report_csv(): void
{
    require_supervisor();

    $filters = [
        'from'   => (string) ($_GET['from'] ?? ''),
        'to'     => (string) ($_GET['to'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
    ];

    $rows = report_rows($filters, 5000);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            (string) $r['tarikh'],
            (string) $r['masa_mula'],
            (string) $r['staff_name'],
            (string) ($r['daerah'] ?? '') . ' / ' . (string) ($r['bahagian'] ?? ''),
            (string) $r['location_name'],
            (string) $r['status_kehadiran'],
            fmt_dt($r['check_in_at']),
            fmt_dt($r['check_out_at']),
            (string) ($r['distance_m'] ?? ''),
            (string) ($r['late_reason'] ?? ''),
            (string) ($r['outside_note'] ?? ''),
            (string) ($r['supervisor_decision'] ?? ''),
        ];
    }

    csv_download(
        'e-hadirgo-' . date('Ymd-His') . '.csv',
        ['Tarikh', 'Masa', 'Staf', 'Unit', 'Lokasi', 'Status', 'Daftar masuk', 'Daftar keluar', 'Jarak (m)', 'Sebab lewat', 'Catatan luar', 'Keputusan penyelia'],
        $out
    );
}

function sup_report_pdf(): void
{
    require_supervisor();

    $filters = [
        'from'   => (string) ($_GET['from'] ?? now_local()->modify('-30 days')->format('Y-m-d')),
        'to'     => (string) ($_GET['to'] ?? now_local()->format('Y-m-d')),
        'status' => (string) ($_GET['status'] ?? ''),
    ];

    page('sup/report_pdf', [
        'title'     => t('sup.report_title'),
        'rows'      => report_rows($filters, 5000),
        'filters'   => $filters,
        'generated' => now_local(),
        'author'    => (string) current_user()['name'],
    ]);
}
