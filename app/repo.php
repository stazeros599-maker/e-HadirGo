<?php

declare(strict_types=1);

/**
 * Data access. Every function returns plain arrays; SQL stays here so pages
 * never build queries inline.
 */

function units_all(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM units';
    if ($activeOnly) {
        $sql .= ' WHERE is_active';
    }

    return db_all($sql . ' ORDER BY daerah, bahagian');
}

function unit_by_id(int $id): ?array
{
    return db_one('SELECT * FROM units WHERE id = ?', [$id]);
}

function user_by_id(int $id): ?array
{
    return db_one('SELECT * FROM users WHERE id = ?', [$id]);
}

function user_roles(int $userId): array
{
    return array_column(db_all('SELECT role_code FROM user_roles WHERE user_id = ?', [$userId]), 'role_code');
}

function all_users(): array
{
    $users = db_all('SELECT u.*, un.daerah, un.bahagian FROM users u LEFT JOIN units un ON un.id = u.unit_id ORDER BY u.name');
    $roles = db_all('SELECT user_id, role_code FROM user_roles');
    $byUser = [];

    foreach ($roles as $r) {
        $byUser[(int) $r['user_id']][] = $r['role_code'];
    }

    foreach ($users as &$u) {
        $u['roles'] = $byUser[(int) $u['id']] ?? [];
    }

    return $users;
}

function staff_options(): array
{
    return db_all(
        'SELECT u.id, u.name, u.email, un.daerah, un.bahagian
           FROM users u
           LEFT JOIN units un ON un.id = u.unit_id
          WHERE u.is_active
          ORDER BY u.name'
    );
}

function saved_locations(bool $activeOnly = true): array
{
    return db_all('SELECT * FROM saved_locations' . ($activeOnly ? ' WHERE is_active' : '') . ' ORDER BY name');
}

/**
 * Assignments visible to a staff member, with their attendance record.
 */
function assignments_for_staff(int $staffId, ?string $from = null, int $limit = 60): array
{
    $sql = 'SELECT a.*, att.id AS attendance_id, att.check_in_at, att.check_out_at,
                   att.outside_radius, att.is_late, att.is_early, att.supervisor_decision
              FROM assignments a
              LEFT JOIN attendance att ON att.assignment_id = a.id
             WHERE a.staff_id = ?';
    $params = [$staffId];

    if ($from !== null) {
        $sql .= ' AND a.assignment_date >= ?';
        $params[] = $from;
    }

    $sql .= ' ORDER BY a.assignment_date DESC, a.start_time DESC LIMIT ' . max(1, $limit);

    return db_all($sql, $params);
}

function today_for_staff(int $staffId, string $date): array
{
    return db_all(
        'SELECT a.*, att.id AS attendance_id, att.check_in_at, att.check_out_at, att.outside_radius, att.is_late
           FROM assignments a
           LEFT JOIN attendance att ON att.assignment_id = a.id
          WHERE a.staff_id = ? AND a.assignment_date = ?
          ORDER BY a.start_time',
        [$staffId, $date]
    );
}

function upcoming_for_staff(int $staffId, string $from, string $to): array
{
    return db_all(
        'SELECT a.*, att.id AS attendance_id
           FROM assignments a
           LEFT JOIN attendance att ON att.assignment_id = a.id
          WHERE a.staff_id = ? AND a.assignment_date > ? AND a.assignment_date <= ?
          ORDER BY a.assignment_date, a.start_time',
        [$staffId, $from, $to]
    );
}

function assignment_by_id(int $id): ?array
{
    return db_one(
        'SELECT a.*, u.name AS staff_name, u.email AS staff_email, u.unit_id,
                s.name AS supervisor_name, un.daerah, un.bahagian
           FROM assignments a
           JOIN users u ON u.id = a.staff_id
           JOIN users s ON s.id = a.supervisor_id
           LEFT JOIN units un ON un.id = u.unit_id
          WHERE a.id = ?',
        [$id]
    );
}

function assignment_group_contains_staff(string $groupId, int $staffId): bool
{
    return db_val(
        'SELECT id FROM assignments WHERE participant_group_id = ? AND staff_id = ? LIMIT 1',
        [$groupId, $staffId]
    ) !== null;
}

function assignment_group_participants(string $groupId): array
{
    return db_all(
        'SELECT a.id, a.staff_id, a.status, u.name AS staff_name,
                att.check_in_at, att.check_out_at, att.distance_m, att.outside_radius, att.location_unavailable,
                att.is_late, att.is_early
           FROM assignments a
           JOIN users u ON u.id = a.staff_id
           LEFT JOIN attendance att ON att.assignment_id = a.id
          WHERE a.participant_group_id = ?
          ORDER BY u.name',
        [$groupId]
    );
}

function attendance_by_assignment(int $assignmentId): ?array
{
    return db_one('SELECT * FROM attendance WHERE assignment_id = ?', [$assignmentId]);
}

/**
 * Assignments a supervisor may see: their own, or any in their unit.
 */
function supervisor_assignments(int $supervisorId, array $filters = []): array
{
    $sql = 'SELECT a.*, u.name AS staff_name, s.name AS supervisor_name,
                   att.id AS attendance_id, att.check_in_at, att.outside_radius, att.is_late,
                   att.supervisor_decision
              FROM assignments a
              JOIN users u ON u.id = a.staff_id
              JOIN users s ON s.id = a.supervisor_id
              LEFT JOIN attendance att ON att.assignment_id = a.id
             WHERE (a.supervisor_id = ? OR u.unit_id = (SELECT unit_id FROM users WHERE id = ?))';
    $params = [$supervisorId, $supervisorId];

    if (!empty($filters['date'])) {
        $sql .= ' AND a.assignment_date = ?';
        $params[] = $filters['date'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND a.status = ?';
        $params[] = $filters['status'];
    }

    $sql .= ' ORDER BY a.assignment_date DESC, a.start_time DESC LIMIT 200';

    return db_all($sql, $params);
}

/**
 * Rows awaiting supervisor review: flagged, late, absent, or auto-closed.
 */
function review_queue(int $supervisorId): array
{
    return db_all(
        'SELECT a.id AS assignment_id, a.assignment_date, a.location_name, a.radius_m,
                u.name AS staff_name, un.daerah, un.bahagian,
                att.*
           FROM assignments a
           JOIN users u ON u.id = a.staff_id
           LEFT JOIN units un ON un.id = u.unit_id
           JOIN attendance att ON att.assignment_id = a.id
          WHERE (a.supervisor_id = ? OR u.unit_id = (SELECT unit_id FROM users WHERE id = ?))
            AND att.supervisor_decision IS NULL
            AND (att.outside_radius OR att.is_late OR att.location_unavailable)
          ORDER BY a.assignment_date DESC, att.check_in_at DESC
          LIMIT 100',
        [$supervisorId, $supervisorId]
    );
}

function report_rows(array $filters, int $limit = 500): array
{
    $sql = 'SELECT * FROM v_attendance_report WHERE 1 = 1';
    $params = [];

    if (!empty($filters['from'])) {
        $sql .= ' AND tarikh >= ?';
        $params[] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $sql .= ' AND tarikh <= ?';
        $params[] = $filters['to'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND status_kehadiran = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['staff_id'])) {
        $sql .= ' AND staff_id = ?';
        $params[] = (int) $filters['staff_id'];
    }

    $sql .= ' ORDER BY tarikh DESC, masa_mula DESC LIMIT ' . max(1, $limit);

    return db_all($sql, $params);
}

function dashboard_counts(int $supervisorId): array
{
    $today = now_local()->format('Y-m-d');

    $row = db_one(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN att.id IS NOT NULL THEN 1 ELSE 0 END) AS checked_in,
            SUM(CASE WHEN att.outside_radius THEN 1 ELSE 0 END) AS outside,
            SUM(CASE WHEN att.is_late THEN 1 ELSE 0 END) AS late
           FROM assignments a
           LEFT JOIN attendance att ON att.assignment_id = a.id
          WHERE a.assignment_date = ?
            AND (a.supervisor_id = ? OR a.staff_id IN (SELECT id FROM users WHERE unit_id = (SELECT unit_id FROM users WHERE id = ?)))',
        [$today, $supervisorId, $supervisorId]
    );

    return [
        'total'      => (int) ($row['total'] ?? 0),
        'checked_in' => (int) ($row['checked_in'] ?? 0),
        'outside'    => (int) ($row['outside'] ?? 0),
        'late'       => (int) ($row['late'] ?? 0),
    ];
}

function notification_log_recent(int $limit = 100): array
{
    return db_all(
        'SELECT n.*, u.name AS user_name
           FROM notification_log n
           LEFT JOIN users u ON u.id = n.user_id
          ORDER BY n.id DESC LIMIT ' . max(1, $limit)
    );
}

function audit_recent(int $limit = 100): array
{
    return db_all(
        'SELECT a.*, u.name AS actor_name
           FROM audit_log a
           LEFT JOIN users u ON u.id = a.actor_id
          ORDER BY a.id DESC LIMIT ' . max(1, $limit)
    );
}

function audit_write(?int $actorId, string $action, string $entity, ?int $entityId, $before = null, $after = null): void
{
    db_insert('audit_log', [
        'actor_id'    => $actorId,
        'action'      => $action,
        'entity'      => $entity,
        'entity_id'   => $entityId,
        'before_json' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        'after_json'  => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
        'ip_address'  => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}
