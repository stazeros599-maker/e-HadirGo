<?php

declare(strict_types=1);

/**
 * Staff routes: dashboard, assignment detail, check-in, check-out, records.
 */

function staff_dashboard(): void
{
    require_login();
    $user = current_user();
    $today = now_local()->format('Y-m-d');
    $weekAhead = now_local()->modify('+7 days')->format('Y-m-d');

    page('staff/dashboard', [
        'title'    => t('staff.dashboard'),
        'today'    => today_for_staff((int) $user['id'], $today),
        'upcoming' => upcoming_for_staff((int) $user['id'], $today, $weekAhead),
        'hasTg'    => db_val(
            'SELECT id FROM notification_channels WHERE user_id = ? AND channel_type = ? AND verified',
            [(int) $user['id'], 'telegram']
        ) !== null,
    ]);
}

function staff_assignment(): void
{
    require_login();
    $user = current_user();
    $id = (int) ($_GET['id'] ?? 0);

    $assignment = assignment_by_id($id);
    if ($assignment === null) {
        http_response_code(404);
        page('error', ['title' => '404', 'code' => 404, 'message' => t('err.notfound')]);

        return;
    }

    $isOwner = (int) $assignment['staff_id'] === (int) $user['id'];
    $isSupervisor = has_role('penyelia') || has_role('super_admin');
    $participantGroupId = (string) ($assignment['participant_group_id'] ?? '');
    $isGroupParticipant = $participantGroupId !== ''
        && assignment_group_contains_staff($participantGroupId, (int) $user['id']);
    if (!$isOwner && !$isSupervisor && !$isGroupParticipant) {
        http_response_code(403);
        page('error', ['title' => '403', 'code' => 403, 'message' => t('err.forbidden')]);

        return;
    }

    $attendance = attendance_by_assignment($id);
    $rules = evaluate_checkin($assignment, now_local(), null, null, null);
    $participants = $participantGroupId !== '' && ($isOwner || $isSupervisor || $isGroupParticipant)
        ? assignment_group_participants($participantGroupId)
        : [];

    page('staff/assignment', [
        'title'       => t('staff.assignment'),
        'assignment'  => $assignment,
        'attendance'  => $attendance,
        'participants' => $participants,
        'graceEnd'    => local_dt((string) $assignment['assignment_date'], (string) $assignment['start_time'])->modify('+15 minutes'),
    ]);
}

function staff_checkin(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    $id = (int) ($_POST['id'] ?? 0);
    $assignment = assignment_by_id($id);

    if ($assignment === null || (int) $assignment['staff_id'] !== (int) $user['id']) {
        flash('error', t('err.forbidden'));
        redirect(url('dashboard'));
    }

    if ($assignment['status'] === 'dibatalkan') {
        flash('error', t('err.cancelled'));
        redirect(url('assignment', ['id' => $id]));
    }

    if (attendance_by_assignment($id) !== null) {
        flash('warn', t('checkin.already'));
        redirect(url('assignment', ['id' => $id]));
    }

    $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
    $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
    $accuracy = isset($_POST['accuracy']) && $_POST['accuracy'] !== '' ? (int) $_POST['accuracy'] : null;

    $note = trim((string) ($_POST['note'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    $now = now_local();
    $rules = evaluate_checkin($assignment, $now, $lat, $lng, $accuracy);

    if ($rules['requires_note'] && mb_strlen($note) < 5) {
        flash('error', t('checkin.note_required'));
        redirect(url('assignment', ['id' => $id]));
    }

    if ($rules['requires_reason'] && mb_strlen($reason) < 5) {
        flash('error', t('checkin.reason_required'));
        redirect(url('assignment', ['id' => $id]));
    }

    $attendanceId = db_insert('attendance', [
        'assignment_id'        => $id,
        'check_in_at'          => to_utc($now),
        'check_in_lat'         => $lat,
        'check_in_lng'         => $lng,
        'accuracy_m'           => $accuracy,
        'distance_m'           => $rules['distance_m'],
        'location_unavailable' => $rules['location_unavailable'],
        'outside_radius'       => $rules['outside_radius'],
        'outside_note'         => $note !== '' ? $note : null,
        'is_early'             => $rules['is_early'],
        'is_late'              => $rules['is_late'],
        'late_reason'          => $reason !== '' ? $reason : null,
        'device_info'          => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'synced_late'          => isset($_POST['queued']),
    ]);

    db_exec('UPDATE assignments SET status = ?, updated_at = ? WHERE id = ?', [
        'dalam_tindakan',
        gmdate('Y-m-d H:i:s'),
        $id,
    ]);

    flash('ok', t('checkin.done', ['status' => status_label($rules['status'])]));

    if ($rules['is_late'] || $rules['outside_radius'] || $rules['location_unavailable']) {
        notify_event((int) $assignment['supervisor_id'], 'late_alert', [
            'staff'    => (string) $assignment['staff_name'],
            'date'     => (string) $assignment['assignment_date'],
            'location' => (string) $assignment['location_name'],
            'status'   => status_label($rules['status']),
            'reason'   => $note !== '' ? $note : $reason,
        ], $id);
    }

    redirect(url('assignment', ['id' => $id]));
}

function staff_checkout(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    $id = (int) ($_POST['id'] ?? 0);
    $assignment = assignment_by_id($id);

    if ($assignment === null || (int) $assignment['staff_id'] !== (int) $user['id']) {
        flash('error', t('err.forbidden'));
        redirect(url('dashboard'));
    }

    $attendance = attendance_by_assignment($id);
    if ($attendance === null) {
        flash('error', t('checkout.no_checkin'));
        redirect(url('assignment', ['id' => $id]));
    }

    if ($attendance['check_out_at'] !== null) {
        flash('warn', t('checkout.already'));
        redirect(url('assignment', ['id' => $id]));
    }

    $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
    $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;

    $distance = ($lat !== null && $lng !== null)
        ? haversine_m($lat, $lng, (float) $assignment['lat'], (float) $assignment['lng'])
        : null;

    db_exec(
        'UPDATE attendance SET check_out_at = ?, check_out_lat = ?, check_out_lng = ?,
                check_out_distance_m = ?, check_out_outside = ?, updated_at = ?
          WHERE id = ?',
        [
            to_utc(now_local()),
            $lat,
            $lng,
            $distance,
            $distance !== null && $distance > (int) $assignment['radius_m'],
            gmdate('Y-m-d H:i:s'),
            (int) $attendance['id'],
        ]
    );

    db_exec('UPDATE assignments SET status = ?, updated_at = ? WHERE id = ?', [
        'selesai',
        gmdate('Y-m-d H:i:s'),
        $id,
    ]);

    flash('ok', t('checkout.done'));
    redirect(url('assignment', ['id' => $id]));
}

function staff_records(): void
{
    require_login();
    $user = current_user();

    $rows = db_all(
        'SELECT a.*, att.check_in_at, att.check_out_at, att.outside_radius, att.is_late,
                att.location_unavailable, att.supervisor_decision
           FROM assignments a
           LEFT JOIN attendance att ON att.assignment_id = a.id
          WHERE a.staff_id = ?
          ORDER BY a.assignment_date DESC, a.start_time DESC
          LIMIT 100',
        [(int) $user['id']]
    );

    page('staff/records', ['title' => t('staff.records'), 'rows' => $rows]);
}
