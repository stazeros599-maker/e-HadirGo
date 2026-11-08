-- ============================================================================
--  e-HadirGo System — Database schema
--  Dialect : MySQL 8.0.16+ / MariaDB 10.6+  (CHECK constraints are enforced)
--  Charset : utf8mb4 / utf8mb4_unicode_ci
--  Time    : every DATETIME column stores UTC; render in Asia/Kuching
--
--  Tables  : units, users, user_roles, email_verifications, password_resets,
--            saved_locations, assignments, attendance, notification_channels,
--            notification_log, assignment_reminders, audit_log
--  View    : v_attendance_report
--
--  Usage   : mysql -u USER -p DBNAME < schema.mysql.sql
--  Note    : run on an empty database. To rebuild an existing one, uncomment
--            the DROP block below — it destroys all data.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 1;

-- DROP VIEW  IF EXISTS v_attendance_report;
-- DROP TABLE IF EXISTS audit_log, assignment_reminders, notification_log,
--                      notification_channels, attendance, assignments,
--                      saved_locations, password_resets, email_verifications,
--                      user_roles, users, units;

-- ----------------------------------------------------------------------------
-- 1. units — daerah / jabatan / bahagian
-- ----------------------------------------------------------------------------
CREATE TABLE units (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    daerah      VARCHAR(80)  NOT NULL,
    jabatan     VARCHAR(120) NOT NULL DEFAULT 'Jabatan Perikanan Sabah',
    bahagian    VARCHAR(120) NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_units_path (daerah, jabatan, bahagian)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. users — one row per staff member, supervisor or super admin
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(190) NOT NULL,
    name           VARCHAR(120) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,          -- Argon2id or bcrypt
    phone          VARCHAR(30)  NULL,
    unit_id        BIGINT UNSIGNED NULL,
    email_verified TINYINT(1)   NOT NULL DEFAULT 0,
    is_dummy       TINYINT(1)   NOT NULL DEFAULT 0,
    language       CHAR(2)      NOT NULL DEFAULT 'ms',
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_unit (unit_id),
    CONSTRAINT fk_users_unit FOREIGN KEY (unit_id) REFERENCES units (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_users_language CHECK (language IN ('ms', 'en'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. user_roles — additive roles; a supervisor is also a staff member
-- ----------------------------------------------------------------------------
CREATE TABLE user_roles (
    user_id     BIGINT UNSIGNED NOT NULL,
    role_code   VARCHAR(20) NOT NULL,
    granted_by  BIGINT UNSIGNED NULL,
    granted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_code),
    KEY idx_user_roles_role (role_code),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_user_roles_granted FOREIGN KEY (granted_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_user_roles_code CHECK (role_code IN ('staf', 'penyelia', 'super_admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. email_verifications — 6-digit OTP, hashed at rest
-- ----------------------------------------------------------------------------
CREATE TABLE email_verifications (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ev_user (user_id, expires_at),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. password_resets — single-use token, hashed at rest
-- ----------------------------------------------------------------------------
CREATE TABLE password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,                  -- sha256 hex
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pr_user (user_id, expires_at),
    KEY idx_pr_token (token_hash),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. saved_locations — reusable pins for supervisors
-- ----------------------------------------------------------------------------
CREATE TABLE saved_locations (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(150) NOT NULL,
    lat        DECIMAL(9,6) NOT NULL,
    lng        DECIMAL(9,6) NOT NULL,
    radius_m   SMALLINT UNSIGNED NOT NULL DEFAULT 250,
    created_by BIGINT UNSIGNED NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sl_name (name),
    CONSTRAINT fk_sl_creator FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_sl_lat CHECK (lat BETWEEN -90 AND 90),
    CONSTRAINT chk_sl_lng CHECK (lng BETWEEN -180 AND 180),
    CONSTRAINT chk_sl_radius CHECK (radius_m BETWEEN 50 AND 5000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. assignments — who, when, where
-- ----------------------------------------------------------------------------
CREATE TABLE assignments (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id              BIGINT UNSIGNED NOT NULL,
    participant_group_id  CHAR(32) NULL,
    supervisor_id         BIGINT UNSIGNED NOT NULL,
    purpose               VARCHAR(255) NOT NULL,
    assignment_date       DATE NOT NULL,
    start_time            TIME NOT NULL,
    end_time              TIME NOT NULL,
    location_name         VARCHAR(150) NOT NULL,
    lat                   DECIMAL(9,6) NOT NULL,
    lng                   DECIMAL(9,6) NOT NULL,
    radius_m              SMALLINT UNSIGNED NOT NULL DEFAULT 250,
    status                VARCHAR(20) NOT NULL DEFAULT 'dijadualkan',
    notes                 TEXT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_as_staff_date (staff_id, assignment_date),
    KEY idx_as_participant_group (participant_group_id),
    KEY idx_as_supervisor (supervisor_id, assignment_date),
    KEY idx_as_status_date (status, assignment_date),
    CONSTRAINT fk_as_staff FOREIGN KEY (staff_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_as_supervisor FOREIGN KEY (supervisor_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_as_status CHECK (status IN ('dijadualkan', 'dalam_tindakan', 'selesai', 'tidak_hadir', 'dibatalkan')),
    CONSTRAINT chk_as_lat CHECK (lat BETWEEN -90 AND 90),
    CONSTRAINT chk_as_lng CHECK (lng BETWEEN -180 AND 180),
    CONSTRAINT chk_as_radius CHECK (radius_m BETWEEN 50 AND 5000),
    CONSTRAINT chk_as_window CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. attendance — the evidence record (one per assignment)
-- ----------------------------------------------------------------------------
CREATE TABLE attendance (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id         BIGINT UNSIGNED NOT NULL,

    check_in_at           DATETIME NOT NULL,
    check_in_lat          DECIMAL(9,6) NULL,
    check_in_lng          DECIMAL(9,6) NULL,
    accuracy_m            SMALLINT UNSIGNED NULL,
    distance_m            INT UNSIGNED NULL,
    location_unavailable  TINYINT(1) NOT NULL DEFAULT 0,
    outside_radius        TINYINT(1) NOT NULL DEFAULT 0,
    outside_note          TEXT NULL,

    is_early              TINYINT(1) NOT NULL DEFAULT 0,
    is_late               TINYINT(1) NOT NULL DEFAULT 0,
    late_reason           TEXT NULL,

    selfie_url            VARCHAR(255) NULL,
    device_info           VARCHAR(255) NULL,

    check_out_at          DATETIME NULL,
    check_out_lat         DECIMAL(9,6) NULL,
    check_out_lng         DECIMAL(9,6) NULL,
    check_out_distance_m  INT UNSIGNED NULL,
    check_out_outside     TINYINT(1) NOT NULL DEFAULT 0,
    auto_closed           TINYINT(1) NOT NULL DEFAULT 0,
    synced_late           TINYINT(1) NOT NULL DEFAULT 0,

    supervisor_decision   VARCHAR(10) NULL,
    supervisor_note       TEXT NULL,
    reviewed_by           BIGINT UNSIGNED NULL,
    reviewed_at           DATETIME NULL,

    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_att_assignment (assignment_id),
    KEY idx_att_checkin (check_in_at),
    KEY idx_att_flags (outside_radius, is_late),
    KEY idx_att_review (supervisor_decision),

    CONSTRAINT fk_att_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_att_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT chk_att_review CHECK (supervisor_decision IS NULL OR supervisor_decision IN ('terima', 'tolak')),
    CONSTRAINT chk_att_note CHECK (
        (outside_radius = 0 AND location_unavailable = 0)
        OR (outside_note IS NOT NULL AND outside_note <> '')
    ),
    CONSTRAINT chk_att_late CHECK (
        is_late = 0 OR (late_reason IS NOT NULL AND late_reason <> '')
    ),
    CONSTRAINT chk_att_coords CHECK (
        (check_in_lat IS NULL OR check_in_lat BETWEEN -90 AND 90)
        AND (check_in_lng IS NULL OR check_in_lng BETWEEN -180 AND 180)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. notification_channels — telegram chat id or verified email
-- ----------------------------------------------------------------------------
CREATE TABLE notification_channels (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    channel_type VARCHAR(10) NOT NULL,
    identifier   VARCHAR(190) NOT NULL,
    verified     TINYINT(1) NOT NULL DEFAULT 0,
    is_primary   TINYINT(1) NOT NULL DEFAULT 0,
    linked_at    DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_nc_user_channel (user_id, channel_type),
    KEY idx_nc_identifier (identifier),
    CONSTRAINT fk_nc_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_nc_type CHECK (channel_type IN ('telegram', 'email'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. notification_log — every delivery attempt
-- ----------------------------------------------------------------------------
CREATE TABLE notification_log (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NULL,
    assignment_id BIGINT UNSIGNED NULL,
    event_code    VARCHAR(40) NOT NULL,
    channel_type  VARCHAR(10) NOT NULL,
    status        VARCHAR(12) NOT NULL DEFAULT 'queued',
    provider_ref  VARCHAR(120) NULL,
    error_text    TEXT NULL,
    sent_at       DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nl_user (user_id, created_at),
    KEY idx_nl_status (status),
    KEY idx_nl_assignment (assignment_id),
    CONSTRAINT fk_nl_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_nl_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_nl_status CHECK (status IN ('queued', 'sent', 'failed', 'skipped'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. assignment_reminders — dedupe for H-1 / H-30 / start-time nudges
-- ----------------------------------------------------------------------------
CREATE TABLE assignment_reminders (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    assignment_id BIGINT UNSIGNED NOT NULL,
    kind          VARCHAR(10) NOT NULL,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ar_assignment_kind (assignment_id, kind),
    CONSTRAINT fk_ar_assignment FOREIGN KEY (assignment_id) REFERENCES assignments (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_ar_kind CHECK (kind IN ('h1', 'h30', 'start'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. audit_log — who changed what, with before/after snapshots
-- ----------------------------------------------------------------------------
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id    BIGINT UNSIGNED NULL,
    action      VARCHAR(60) NOT NULL,
    entity      VARCHAR(40) NOT NULL,
    entity_id   BIGINT UNSIGNED NULL,
    before_json JSON NULL,
    after_json  JSON NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_al_entity (entity, entity_id),
    KEY idx_al_actor (actor_id, created_at),
    KEY idx_al_created (created_at),
    CONSTRAINT fk_al_actor FOREIGN KEY (actor_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- View: one row per assignment, with the derived attendance status
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_attendance_report AS
SELECT
    ass.id              AS assignment_id,
    ass.assignment_date AS tarikh,
    ass.start_time      AS masa_mula,
    ass.end_time        AS masa_tamat,
    ass.location_name,
    ass.radius_m,
    ass.status          AS assignment_status,
    u.id                AS staff_id,
    u.name              AS staff_name,
    u.email             AS staff_email,
    un.daerah,
    un.jabatan,
    un.bahagian,
    s.name              AS supervisor_name,
    att.check_in_at,
    att.check_out_at,
    att.distance_m,
    att.accuracy_m,
    att.outside_radius,
    att.is_early,
    att.is_late,
    att.outside_note,
    att.late_reason,
    att.supervisor_decision,
    att.reviewed_at,
    CASE
        WHEN ass.status = 'dibatalkan' THEN 'dibatalkan'
        WHEN att.id IS NULL THEN 'tidak_hadir'
        WHEN att.outside_radius = 1 OR att.location_unavailable = 1 THEN 'luar_kawasan'
        WHEN att.is_late = 1 THEN 'lewat'
        WHEN att.is_early = 1 THEN 'awal'
        ELSE 'tepat_masa'
    END AS status_kehadiran
FROM assignments ass
JOIN users u  ON u.id = ass.staff_id
JOIN users s  ON s.id = ass.supervisor_id
LEFT JOIN units un      ON un.id = u.unit_id
LEFT JOIN attendance att ON att.assignment_id = ass.id;

-- ============================================================================
-- Seed: organisational units (idempotent)
-- ============================================================================
INSERT IGNORE INTO units (daerah, jabatan, bahagian) VALUES
    ('Kota Kinabalu', 'Jabatan Perikanan Sabah', 'Pengurusan'),
    ('Kota Kinabalu', 'Jabatan Perikanan Sabah', 'Perikanan Tangkapan'),
    ('Kota Kinabalu', 'Jabatan Perikanan Sabah', 'Akuakultur'),
    ('Kota Kinabalu', 'Jabatan Perikanan Sabah', 'Penguatkuasaan'),
    ('Sandakan',      'Jabatan Perikanan Sabah', 'Akuakultur'),
    ('Sandakan',      'Jabatan Perikanan Sabah', 'Pengurusan'),
    ('Tawau',         'Jabatan Perikanan Sabah', 'Perikanan Tangkapan'),
    ('Tawau',         'Jabatan Perikanan Sabah', 'Akuakultur'),
    ('Lahad Datu',    'Jabatan Perikanan Sabah', 'Penguatkuasaan'),
    ('Kudat',         'Jabatan Perikanan Sabah', 'Perikanan Tangkapan');

-- ============================================================================
-- Seed: saved locations (created_by left NULL; assign an admin afterwards)
-- ============================================================================
INSERT IGNORE INTO saved_locations (name, lat, lng, radius_m) VALUES
    ('Jeti Kota Kinabalu',                5.980400, 116.073500, 250),
    ('Pejabat Perikanan Daerah Sandakan', 5.840200, 118.117900, 150),
    ('Pusat Inkubasi Tuaran',             6.121000, 116.221000, 200),
    ('Stesen Perikanan Tawau',            4.244800, 117.891200, 250);
