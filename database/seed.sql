-- =============================================================================
-- NOC MRTG Manager - Default Seed Data
-- Database:   noc_manager
-- Compatible: MariaDB 11.x
-- Version:    1.0.0
--
-- Usage:
--   mariadb -u root -p noc_manager < seed.sql
--
-- This file inserts:
--   1. Default admin user      (admin / Admin@123)
--   2. Default application settings
--   3. Example router          (commented out – uncomment to activate)
--
-- SECURITY: Change the admin password immediately after first login.
-- =============================================================================

SET SQL_MODE   = 'NO_AUTO_VALUE_ON_ZERO,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone  = '+07:00';
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- =============================================================================
-- 1. DEFAULT USERS
-- =============================================================================
-- Password: Admin@123
-- Hash generated with: password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12])
-- Verify with PHP: password_verify('Admin@123', '<hash>')
-- =============================================================================

INSERT INTO `users`
    (`id`, `username`, `password_hash`, `full_name`, `email`, `role`, `status`, `created_at`)
VALUES
    (
        1,
        'admin',
        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Administrator',
        'admin@noc.triraintiutama.local',
        'admin',
        'active',
        NOW()
    )
ON DUPLICATE KEY UPDATE
    `username`      = VALUES(`username`),
    `full_name`     = VALUES(`full_name`),
    `role`          = VALUES(`role`),
    `status`        = VALUES(`status`);

-- =============================================================================
-- 2. DEFAULT APPLICATION SETTINGS
-- =============================================================================

INSERT INTO `settings` (`key`, `value`, `description`) VALUES

-- ── Application ──────────────────────────────────────────────────────────────
('app_name',
 'NOC MRTG Manager',
 'Application display name shown in the browser title and header'),

('app_version',
 '1.0.0',
 'Current application version number'),

('app_timezone',
 'Asia/Jakarta',
 'Server and display timezone (PHP timezone string)'),

('app_language',
 'en',
 'UI language code (en = English)'),

('app_theme',
 'light',
 'Default UI theme: light or dark'),

('app_logo_url',
 '/assets/img/logo.png',
 'Path to the application logo image (relative to DocumentRoot)'),

-- ── SNMP Defaults ─────────────────────────────────────────────────────────────
('snmp_timeout',
 '3000000',
 'SNMP request timeout in microseconds (3,000,000 = 3 seconds)'),

('snmp_retries',
 '2',
 'Number of SNMP retries before marking a target as unreachable'),

('snmp_version',
 '2c',
 'Default SNMP version for new routers: 1 or 2c'),

('snmp_default_community',
 'public',
 'Default SNMP community string applied to new routers'),

('snmp_port',
 '161',
 'Default UDP port for SNMP polling'),

-- ── Polling ───────────────────────────────────────────────────────────────────
('poll_interval',
 '5',
 'Traffic polling interval in minutes (must match crontab schedule)'),

('poll_max_concurrent',
 '10',
 'Maximum number of routers to poll concurrently in a single cron run'),

-- ── Data Retention ────────────────────────────────────────────────────────────
('retention_raw_hours',
 '72',
 'Hours to retain raw 5-minute traffic_data samples before cleanup'),

('retention_daily_days',
 '365',
 'Days to retain aggregated daily traffic records'),

('retention_weekly_weeks',
 '104',
 'Weeks to retain aggregated weekly traffic records (default: 2 years)'),

('retention_monthly_months',
 '60',
 'Months to retain aggregated monthly traffic records (default: 5 years)'),

-- ── MRTG ──────────────────────────────────────────────────────────────────────
('mrtg_bin',
 '/usr/bin/mrtg',
 'Absolute path to the mrtg binary'),

('mrtg_cfgmaker_bin',
 '/usr/bin/cfgmaker',
 'Absolute path to the cfgmaker binary'),

('mrtg_cfg_dir',
 '/etc/mrtg',
 'Directory where MRTG .cfg files are stored'),

('mrtg_data_dir',
 '/var/www/mrtg',
 'Directory where MRTG graph output and .log files are stored'),

('mrtg_interval',
 '5',
 'MRTG polling interval in minutes (must match crontab)'),

('mrtg_graph_format',
 'png',
 'Output format for MRTG graphs: png'),

('mrtg_log_format',
 'rrdtool',
 'MRTG log format: rrdtool or mrtg (legacy)'),

-- ── Security & Authentication ─────────────────────────────────────────────────
('session_lifetime',
 '1800',
 'Session inactivity timeout in seconds (1800 = 30 minutes)'),

('max_login_attempts',
 '5',
 'Maximum failed login attempts before account lockout'),

('login_lockout_minutes',
 '15',
 'Minutes to lock an account after exceeding max_login_attempts'),

('password_min_length',
 '8',
 'Minimum password length for new user accounts'),

('require_strong_password',
 '1',
 '1=require uppercase + lowercase + digit + special char, 0=length only'),

-- ── Email / Notifications ─────────────────────────────────────────────────────
('smtp_enabled',
 '0',
 '1=send email alerts, 0=disabled'),

('smtp_host',
 'localhost',
 'SMTP server hostname or IP'),

('smtp_port',
 '587',
 'SMTP server port (587=STARTTLS, 465=SSL, 25=plain)'),

('smtp_username',
 '',
 'SMTP authentication username (leave empty if no auth)'),

('smtp_password',
 '',
 'SMTP authentication password (leave empty if no auth)'),

('smtp_from_address',
 'noc@triraintiutama.local',
 'From address for outgoing email notifications'),

('smtp_from_name',
 'NOC MRTG Manager',
 'From display name for outgoing email notifications'),

-- ── Dashboard ─────────────────────────────────────────────────────────────────
('dashboard_refresh_seconds',
 '60',
 'Dashboard auto-refresh interval in seconds (0 = disabled)'),

('dashboard_top_n',
 '10',
 'Number of top bandwidth consumers to display on dashboard'),

-- ── Reports ───────────────────────────────────────────────────────────────────
('report_default_period',
 '24h',
 'Default time period for traffic reports: 1h, 6h, 24h, 7d, 30d'),

('report_max_export_rows',
 '50000',
 'Maximum rows to include in a single CSV export'),

-- ── Chart Display ─────────────────────────────────────────────────────────────
('chart_bytes_unit',
 'auto',
 'Traffic chart unit: auto, bits, bytes, Kbps, Mbps, Gbps'),

('chart_color_in',
 '#0d6efd',
 'Chart.js color for inbound traffic (Bootstrap primary blue)'),

('chart_color_out',
 '#198754',
 'Chart.js color for outbound traffic (Bootstrap success green)')

ON DUPLICATE KEY UPDATE
    `value`       = VALUES(`value`),
    `description` = VALUES(`description`),
    `updated_at`  = CURRENT_TIMESTAMP;

-- =============================================================================
-- 3. EXAMPLE ROUTER (commented out – uncomment and edit to add first router)
-- =============================================================================

/*
INSERT INTO `routers`
    (`name`, `ip_address`, `snmp_community`, `snmp_version`, `snmp_port`,
     `username`, `status`)
VALUES
    (
        'Router-Core-01',
        '192.168.1.1',
        'public',
        '2c',
        161,
        'admin',
        'active'
    );

-- After adding the router, run the SNMP discovery from the web UI or via:
-- php /var/www/noc/cron/poll_snmp.php
*/

-- =============================================================================
-- 4. EXAMPLE OPERATOR USER (commented out – uncomment to add)
-- =============================================================================
-- Password: Operator@123
-- Hash: password_hash('Operator@123', PASSWORD_BCRYPT, ['cost' => 12])

/*
INSERT INTO `users`
    (`username`, `password_hash`, `full_name`, `email`, `role`, `status`)
VALUES
    (
        'operator',
        '$2y$12$pBjMKgOlCTG/2.9wvEhAr.oJuQxwJ7OzrCGzqKZ3MSqizAUiCRmry',
        'NOC Operator',
        'operator@noc.triraintiutama.local',
        'operator',
        'active'
    );
*/

-- =============================================================================
-- 5. EXAMPLE VIEWER USER (commented out – uncomment to add)
-- =============================================================================
-- Password: Viewer@123
-- Hash: password_hash('Viewer@123', PASSWORD_BCRYPT, ['cost' => 12])

/*
INSERT INTO `users`
    (`username`, `password_hash`, `full_name`, `email`, `role`, `status`)
VALUES
    (
        'viewer',
        '$2y$12$Z4nxgaGKf3EGkrGNKv3LuO4lGQBc1qBX7/kTmVDsHT0E3y6BNKGBS',
        'NOC Viewer',
        'viewer@noc.triraintiutama.local',
        'viewer',
        'active'
    );
*/

-- =============================================================================
-- 6. INITIAL AUDIT LOG ENTRY
-- =============================================================================

INSERT INTO `audit_log`
    (`user_id`, `action`, `module`, `target_id`, `details`, `ip_address`)
VALUES
    (1, 'create', 'system', NULL,
     '{"event":"database_initialized","version":"1.0.0","note":"Seed data loaded successfully"}',
     '127.0.0.1');

-- =============================================================================
-- Re-enable foreign key checks
-- =============================================================================
SET foreign_key_checks = 1;

-- =============================================================================
-- Verification query – run after import to confirm seed data is correct
-- =============================================================================
-- SELECT 'users' AS tbl, COUNT(*) AS rows FROM users
-- UNION ALL SELECT 'settings', COUNT(*) FROM settings
-- UNION ALL SELECT 'audit_log', COUNT(*) FROM audit_log;
--
-- Expected result:
-- +----------+------+
-- | tbl      | rows |
-- +----------+------+
-- | users    |    1 |
-- | settings |   34 |
-- | audit_log|    1 |
-- +----------+------+
