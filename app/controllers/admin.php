<?php

declare(strict_types=1);

/**
 * Super-admin routes: users and roles, units, saved locations, logs.
 */

function admin_users(): void
{
    require_role('super_admin');

    page('admin/users', [
        'title' => t('admin.users'),
        'rows'  => all_users(),
    ]);
}

function admin_roles(): void
{
    require_role('super_admin');
    csrf_verify();

    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $user = user_by_id($userId);

    if ($user === null || !in_array($action, ['grant', 'revoke'], true)) {
        flash('error', t('err.notfound'));
        redirect(url('admin.users'));
    }

    if ($action === 'grant') {
        $exists = db_val('SELECT user_id FROM user_roles WHERE user_id = ? AND role_code = ?', [$userId, 'penyelia']);
        if ($exists === null) {
            db_insert('user_roles', [
                'user_id'    => $userId,
                'role_code'  => 'penyelia',
                'granted_by' => (int) current_user()['id'],
            ]);
            notify_event($userId, 'role_granted', ['role' => t('role.penyelia')]);
        }
        flash('ok', t('admin.granted', ['name' => (string) $user['name']]));
    } else {
        db_exec('DELETE FROM user_roles WHERE user_id = ? AND role_code = ?', [$userId, 'penyelia']);
        flash('ok', t('admin.revoked', ['name' => (string) $user['name']]));
    }

    audit_write((int) current_user()['id'], 'role_' . $action, 'users', $userId, null, ['role' => 'penyelia']);
    redirect(url('admin.users'));
}

function admin_config(): void
{
    require_role('super_admin');

    page('admin/config', [
        'title'     => t('admin.config'),
        'units'     => db_all('SELECT * FROM units ORDER BY daerah, bahagian'),
        'locations' => saved_locations(false),
    ]);
}

function admin_unit_save(): void
{
    require_role('super_admin');
    csrf_verify();

    $daerah = trim((string) ($_POST['daerah'] ?? ''));
    $bahagian = trim((string) ($_POST['bahagian'] ?? ''));

    if ($daerah === '' || $bahagian === '') {
        flash('error', t('admin.err_unit'));
        redirect(url('admin.config'));
    }

    $exists = db_val(
        'SELECT id FROM units WHERE daerah = ? AND jabatan = ? AND bahagian = ?',
        [$daerah, 'Jabatan Perikanan Sabah', $bahagian]
    );

    if ($exists !== null) {
        flash('warn', t('admin.unit_exists'));
    } else {
        $id = db_insert('units', [
            'daerah'   => $daerah,
            'jabatan'  => 'Jabatan Perikanan Sabah',
            'bahagian' => $bahagian,
        ]);
        audit_write((int) current_user()['id'], 'unit_create', 'units', $id, null, ['daerah' => $daerah, 'bahagian' => $bahagian]);
        flash('ok', t('admin.unit_added'));
    }

    redirect(url('admin.config'));
}

function admin_location_save(): void
{
    require_role('super_admin');
    csrf_verify();

    $name = trim((string) ($_POST['name'] ?? ''));
    $lat = (float) ($_POST['lat'] ?? 0);
    $lng = (float) ($_POST['lng'] ?? 0);
    $radius = (int) ($_POST['radius_m'] ?? 250);

    if ($name === '' || ($lat == 0.0 && $lng == 0.0) || $radius < 50 || $radius > 5000) {
        flash('error', t('admin.err_location'));
        redirect(url('admin.config'));
    }

    $id = db_insert('saved_locations', [
        'name'       => $name,
        'lat'        => $lat,
        'lng'        => $lng,
        'radius_m'   => $radius,
        'created_by' => (int) current_user()['id'],
    ]);

    audit_write((int) current_user()['id'], 'location_create', 'saved_locations', $id, null, ['name' => $name]);
    flash('ok', t('admin.location_added'));
    redirect(url('admin.config'));
}

function admin_logs(): void
{
    require_role('super_admin');

    page('admin/logs', [
        'title' => t('admin.logs'),
        'notifications' => notification_log_recent(100),
        'audit'         => audit_recent(100),
    ]);
}
