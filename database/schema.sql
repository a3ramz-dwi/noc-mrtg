-- =============================================================================
-- NOC MRTG Manager - Production Database Schema
-- Database:   noc_manager
-- Engine:     InnoDB
-- Charset:    utf8mb4
-- Collation:  utf8mb4_unicode_ci
-- Compatible: MariaDB 11.x
-- Version:    1.0.0
-- =============================================================================

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+07:00';
SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ---------------------------------------------------------------------------
-- TABLE: routers
-- MikroTik router connection details and discovered system information.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `routers`;
CREATE TABLE `routers` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(100)     NOT NULL                   COMMENT 'Human-readable label for this router',
    `ip_address`       VARCHAR(45)      NOT NULL                   COMMENT 'IPv4 or IPv6 address for SNMP polling',
    `snmp_community`   VARCHAR(128)     NOT NULL DEFAULT 'public'  COMMENT 'SNMP v2c community string',
    `snmp_version`     ENUM('1','2c')   NOT NULL DEFAULT '2c'      COMMENT 'SNMP protocol version',
    `snmp_port`        SMALLINT UNSIGNED NOT NULL DEFAULT 161      COMMENT 'UDP port for SNMP (default 161)',
    `username`         VARCHAR(64)      DEFAULT NULL               COMMENT 'RouterOS API username (optional)',
    `password`         VARCHAR(255)     DEFAULT NULL               COMMENT 'RouterOS API password (optional, not used for SNMP)',
    `router_os_version` VARCHAR(32)     DEFAULT NULL               COMMENT 'RouterOS firmware version discovered via SNMP',
    `identity`         VARCHAR(100)     DEFAULT NULL               COMMENT 'Router identity/hostname from sysName',
    `model`            VARCHAR(100)     DEFAULT NULL               COMMENT 'Board model name from MikroTik MIB',
    `serial`           VARCHAR(64)      DEFAULT NULL               COMMENT 'Serial number from MikroTik MIB',
    `uptime`           BIGINT UNSIGNED  DEFAULT NULL               COMMENT 'System uptime in hundredths of seconds (sysUpTime)',
    `status`           ENUM('active','inactive','error') NOT NULL DEFAULT 'active'
                                                                   COMMENT 'active=monitored, inactive=disabled, error=unreachable',
    `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_routers_ip` (`ip_address`),
    KEY `idx_routers_status` (`status`),
    KEY `idx_routers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='MikroTik router inventory and SNMP connection parameters';

-- ---------------------------------------------------------------------------
-- TABLE: interfaces
-- Network interfaces discovered from the standard IF-MIB via SNMP walk.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `interfaces`;
CREATE TABLE `interfaces` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `router_id`        INT UNSIGNED     NOT NULL                   COMMENT 'FK → routers.id',
    `if_index`         INT UNSIGNED     NOT NULL                   COMMENT 'SNMP ifIndex value (.1.3.6.1.2.1.2.2.1.1)',
    `name`             VARCHAR(100)     NOT NULL                   COMMENT 'Interface name from ifName/ifDescr',
    `alias`            VARCHAR(255)     DEFAULT NULL               COMMENT 'Interface alias/description from ifAlias',
    `description`      VARCHAR(255)     DEFAULT NULL               COMMENT 'ifDescr OID value',
    `type`             SMALLINT UNSIGNED DEFAULT NULL              COMMENT 'ifType (e.g. 6=ethernetCsmacd, 131=tunnel)',
    `mtu`              INT UNSIGNED     DEFAULT NULL               COMMENT 'Maximum Transmission Unit in bytes',
    `speed`            BIGINT UNSIGNED  DEFAULT NULL               COMMENT 'Interface speed in bits/s (ifHighSpeed * 1000000)',
    `mac_address`      VARCHAR(17)      DEFAULT NULL               COMMENT 'ifPhysAddress formatted as XX:XX:XX:XX:XX:XX',
    `admin_status`     TINYINT UNSIGNED DEFAULT NULL               COMMENT '1=up 2=down 3=testing (ifAdminStatus)',
    `oper_status`      TINYINT UNSIGNED DEFAULT NULL               COMMENT '1=up 2=down 3=testing 4=unknown 5=dormant (ifOperStatus)',
    `monitored`        TINYINT(1)       NOT NULL DEFAULT 0         COMMENT '1=poll this interface, 0=skip',
    `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_iface_router_ifindex` (`router_id`, `if_index`),
    KEY `idx_iface_router_id` (`router_id`),
    KEY `idx_iface_monitored` (`monitored`),
    KEY `idx_iface_oper_status` (`oper_status`),
    CONSTRAINT `fk_interfaces_router`
        FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Network interfaces discovered from IF-MIB via SNMP';

-- ---------------------------------------------------------------------------
-- TABLE: simple_queues
-- MikroTik Simple Queue entries from the MikroTik enterprise MIB.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `simple_queues`;
CREATE TABLE `simple_queues` (
    `id`                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `router_id`                 INT UNSIGNED  NOT NULL                   COMMENT 'FK → routers.id',
    `queue_index`               INT UNSIGNED  NOT NULL                   COMMENT 'MIB index (.1.3.6.1.4.1.14988.1.1.2.1.1.1)',
    `name`                      VARCHAR(100)  NOT NULL                   COMMENT 'Queue name from mtxrQueueSimpleName',
    `target`                    VARCHAR(255)  DEFAULT NULL               COMMENT 'Target IP/subnet from mtxrQueueSimpleSrcAddr',
    `max_limit_upload`          BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Upload max limit in bits/s',
    `max_limit_download`        BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Download max limit in bits/s',
    `burst_limit_upload`        BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Burst limit upload in bits/s',
    `burst_limit_download`      BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Burst limit download in bits/s',
    `burst_threshold_upload`    BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Burst threshold upload in bits/s',
    `burst_threshold_download`  BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Burst threshold download in bits/s',
    `burst_time_upload`         INT UNSIGNED  DEFAULT NULL               COMMENT 'Burst time upload in seconds',
    `burst_time_download`       INT UNSIGNED  DEFAULT NULL               COMMENT 'Burst time download in seconds',
    `monitored`                 TINYINT(1)    NOT NULL DEFAULT 0         COMMENT '1=active polling, 0=skip',
    `created_at`                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_queue_router_index` (`router_id`, `queue_index`),
    KEY `idx_queue_router_id` (`router_id`),
    KEY `idx_queue_monitored` (`monitored`),
    KEY `idx_queue_name` (`name`),
    CONSTRAINT `fk_queues_router`
        FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='MikroTik Simple Queue entries from enterprise MIB';

-- ---------------------------------------------------------------------------
-- TABLE: pppoe_users
-- PPPoE active sessions from MikroTik PPPoE MIB.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `pppoe_users`;
CREATE TABLE `pppoe_users` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `router_id`      INT UNSIGNED  NOT NULL                   COMMENT 'FK → routers.id',
    `name`           VARCHAR(100)  NOT NULL                   COMMENT 'PPPoE username from mtxrPPPoESessionName',
    `service`        VARCHAR(100)  DEFAULT NULL               COMMENT 'PPPoE service name from mtxrPPPoESessionService',
    `profile`        VARCHAR(100)  DEFAULT NULL               COMMENT 'User profile name (fetched via API or RADIUS)',
    `remote_address` VARCHAR(45)   DEFAULT NULL               COMMENT 'Assigned IP from mtxrPPPoESessionAddress',
    `local_address`  VARCHAR(45)   DEFAULT NULL               COMMENT 'Local/gateway IP address',
    `caller_id`      VARCHAR(64)   DEFAULT NULL               COMMENT 'Caller ID / MAC from mtxrPPPoESessionCaller',
    `uptime`         BIGINT UNSIGNED DEFAULT NULL             COMMENT 'Session uptime in seconds from mtxrPPPoESessionUptime',
    `status`         ENUM('connected','disconnected') NOT NULL DEFAULT 'connected'
                                                               COMMENT 'Current session state',
    `monitored`      TINYINT(1)    NOT NULL DEFAULT 0         COMMENT '1=track traffic for this user',
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pppoe_router_id` (`router_id`),
    KEY `idx_pppoe_name` (`name`),
    KEY `idx_pppoe_status` (`status`),
    KEY `idx_pppoe_monitored` (`monitored`),
    KEY `idx_pppoe_remote_address` (`remote_address`),
    CONSTRAINT `fk_pppoe_router`
        FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='PPPoE active sessions from MikroTik PPPoE MIB';

-- ---------------------------------------------------------------------------
-- TABLE: traffic_data
-- Raw 5-minute traffic counter deltas. High-volume table.
-- target_type is polymorphic: 'interface', 'queue', or 'pppoe'.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `traffic_data`;
CREATE TABLE `traffic_data` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `target_type` ENUM('interface','queue','pppoe') NOT NULL
                                              COMMENT 'Polymorphic type reference',
    `target_id`   INT UNSIGNED    NOT NULL    COMMENT 'FK to interfaces.id / simple_queues.id / pppoe_users.id',
    `bytes_in`    BIGINT UNSIGNED NOT NULL DEFAULT 0
                                              COMMENT 'Delta bytes received in this polling interval',
    `bytes_out`   BIGINT UNSIGNED NOT NULL DEFAULT 0
                                              COMMENT 'Delta bytes transmitted in this polling interval',
    `timestamp`   DATETIME        NOT NULL    COMMENT 'Timestamp of this measurement (UTC+7)',
    PRIMARY KEY (`id`),
    KEY `idx_td_lookup` (`target_type`, `target_id`, `timestamp`),
    KEY `idx_td_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Raw 5-minute traffic samples (polymorphic target)'
  ROW_FORMAT=COMPRESSED;

-- ---------------------------------------------------------------------------
-- TABLE: traffic_daily
-- Aggregated daily traffic totals. Pre-computed from traffic_data.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `traffic_daily`;
CREATE TABLE `traffic_daily` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `target_type` ENUM('interface','queue','pppoe') NOT NULL,
    `target_id`   INT UNSIGNED    NOT NULL,
    `date`        DATE            NOT NULL             COMMENT 'The calendar date for this aggregate',
    `bytes_in`    BIGINT UNSIGNED NOT NULL DEFAULT 0   COMMENT 'Total bytes received that day',
    `bytes_out`   BIGINT UNSIGNED NOT NULL DEFAULT 0   COMMENT 'Total bytes transmitted that day',
    `avg_bps_in`  DOUBLE          NOT NULL DEFAULT 0   COMMENT 'Average inbound bits/s over the day',
    `avg_bps_out` DOUBLE          NOT NULL DEFAULT 0   COMMENT 'Average outbound bits/s over the day',
    `max_bps_in`  DOUBLE          NOT NULL DEFAULT 0   COMMENT 'Peak inbound bits/s observed that day',
    `max_bps_out` DOUBLE          NOT NULL DEFAULT 0   COMMENT 'Peak outbound bits/s observed that day',
    `samples`     INT UNSIGNED    NOT NULL DEFAULT 0   COMMENT 'Number of 5-minute samples aggregated',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_daily` (`target_type`, `target_id`, `date`),
    KEY `idx_daily_date` (`date`),
    KEY `idx_daily_lookup` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pre-aggregated daily traffic totals per target';

-- ---------------------------------------------------------------------------
-- TABLE: traffic_weekly
-- Aggregated weekly traffic totals (ISO week, starting Monday).
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `traffic_weekly`;
CREATE TABLE `traffic_weekly` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `target_type` ENUM('interface','queue','pppoe') NOT NULL,
    `target_id`   INT UNSIGNED    NOT NULL,
    `week_start`  DATE            NOT NULL             COMMENT 'Monday of the ISO week (YYYY-MM-DD)',
    `week_number` TINYINT UNSIGNED NOT NULL            COMMENT 'ISO week number (1–53)',
    `year`        SMALLINT UNSIGNED NOT NULL           COMMENT 'ISO year',
    `bytes_in`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `bytes_out`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `avg_bps_in`  DOUBLE          NOT NULL DEFAULT 0,
    `avg_bps_out` DOUBLE          NOT NULL DEFAULT 0,
    `max_bps_in`  DOUBLE          NOT NULL DEFAULT 0,
    `max_bps_out` DOUBLE          NOT NULL DEFAULT 0,
    `samples`     INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_weekly` (`target_type`, `target_id`, `week_start`),
    KEY `idx_weekly_week_start` (`week_start`),
    KEY `idx_weekly_lookup` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pre-aggregated weekly traffic totals per target';

-- ---------------------------------------------------------------------------
-- TABLE: traffic_monthly
-- Aggregated monthly traffic totals.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `traffic_monthly`;
CREATE TABLE `traffic_monthly` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `target_type` ENUM('interface','queue','pppoe') NOT NULL,
    `target_id`   INT UNSIGNED    NOT NULL,
    `month`       DATE            NOT NULL             COMMENT 'First day of month (YYYY-MM-01)',
    `bytes_in`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `bytes_out`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `avg_bps_in`  DOUBLE          NOT NULL DEFAULT 0,
    `avg_bps_out` DOUBLE          NOT NULL DEFAULT 0,
    `max_bps_in`  DOUBLE          NOT NULL DEFAULT 0,
    `max_bps_out` DOUBLE          NOT NULL DEFAULT 0,
    `samples`     INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_monthly` (`target_type`, `target_id`, `month`),
    KEY `idx_monthly_month` (`month`),
    KEY `idx_monthly_lookup` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pre-aggregated monthly traffic totals per target';

-- ---------------------------------------------------------------------------
-- TABLE: mrtg_configs
-- Tracks MRTG configuration files generated for each router/target.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `mrtg_configs`;
CREATE TABLE `mrtg_configs` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `router_id`      INT UNSIGNED  NOT NULL                   COMMENT 'FK → routers.id',
    `target_type`    ENUM('interface','queue','router') NOT NULL DEFAULT 'interface'
                                                               COMMENT 'Type of MRTG target in this config',
    `target_id`      INT UNSIGNED  DEFAULT NULL               COMMENT 'FK to interfaces.id or simple_queues.id, NULL for per-router configs',
    `config_content` MEDIUMTEXT    NOT NULL                   COMMENT 'Full content of the generated .cfg file',
    `filename`       VARCHAR(255)  NOT NULL                   COMMENT 'Absolute path to .cfg file e.g. /etc/mrtg/router_1.cfg',
    `generated_at`   DATETIME      DEFAULT NULL               COMMENT 'Timestamp of last config generation',
    `status`         ENUM('active','pending','error') NOT NULL DEFAULT 'pending'
                                                               COMMENT 'active=deployed, pending=needs write, error=write failed',
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mrtg_filename` (`filename`),
    KEY `idx_mrtg_router_id` (`router_id`),
    KEY `idx_mrtg_status` (`status`),
    CONSTRAINT `fk_mrtg_router`
        FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='MRTG configuration file metadata and content';

-- ---------------------------------------------------------------------------
-- TABLE: users
-- Application user accounts with RBAC roles.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `username`      VARCHAR(64)   NOT NULL                   COMMENT 'Unique login username (lowercase, alphanumeric + underscore)',
    `password_hash` VARCHAR(255)  NOT NULL                   COMMENT 'Bcrypt hash from PHP password_hash(..., PASSWORD_BCRYPT)',
    `full_name`     VARCHAR(150)  NOT NULL                   COMMENT 'Display name for UI',
    `email`         VARCHAR(255)  DEFAULT NULL               COMMENT 'Email address (optional, for notifications)',
    `role`          ENUM('admin','operator','viewer') NOT NULL DEFAULT 'viewer'
                                                             COMMENT 'admin=full access, operator=read/write, viewer=read-only',
    `last_login`    DATETIME      DEFAULT NULL               COMMENT 'Timestamp of most recent successful login',
    `status`        ENUM('active','inactive','locked') NOT NULL DEFAULT 'active'
                                                             COMMENT 'active=can login, inactive=disabled, locked=brute-force lockout',
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application user accounts and role assignments';

-- ---------------------------------------------------------------------------
-- TABLE: settings
-- Global application configuration as key-value pairs.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `key`         VARCHAR(100)  NOT NULL                   COMMENT 'Unique setting identifier (snake_case)',
    `value`       TEXT          NOT NULL                   COMMENT 'Setting value; use JSON for complex types',
    `description` VARCHAR(500)  DEFAULT NULL               COMMENT 'Human-readable description of this setting',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Application configuration key-value store';

-- ---------------------------------------------------------------------------
-- TABLE: audit_log
-- Immutable audit trail for all write operations and security events.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    DEFAULT NULL               COMMENT 'FK → users.id (NULL for system/cron actions)',
    `action`     ENUM('create','update','delete','login','logout','export','config_change','discovery')
                 NOT NULL                                   COMMENT 'Type of action performed',
    `module`     VARCHAR(50)     NOT NULL                   COMMENT 'Module where action occurred (e.g. routers, users)',
    `target_id`  INT UNSIGNED    DEFAULT NULL               COMMENT 'Primary key of the affected record',
    `details`    TEXT            DEFAULT NULL               COMMENT 'JSON-encoded before/after values or action details',
    `ip_address` VARCHAR(45)     NOT NULL                   COMMENT 'Client IP address (supports IPv6)',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_user_id` (`user_id`),
    KEY `idx_audit_action` (`action`),
    KEY `idx_audit_module` (`module`),
    KEY `idx_audit_created_at` (`created_at`),
    CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable security and operational audit trail';

-- ---------------------------------------------------------------------------
-- TABLE: login_attempts
-- Tracks login attempts for brute-force detection and rate limiting.
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`     VARCHAR(64)     NOT NULL               COMMENT 'Username attempted (may not exist in users table)',
    `ip_address`   VARCHAR(45)     NOT NULL               COMMENT 'Source IP address of the login attempt',
    `attempted_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                          COMMENT 'Timestamp of this login attempt',
    `success`      TINYINT(1)      NOT NULL DEFAULT 0     COMMENT '1=successful login, 0=failed',
    PRIMARY KEY (`id`),
    KEY `idx_la_username_time` (`username`, `attempted_at`),
    KEY `idx_la_ip_time` (`ip_address`, `attempted_at`),
    KEY `idx_la_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Login attempt log for brute-force protection';

-- ---------------------------------------------------------------------------
-- Re-enable foreign key checks
-- ---------------------------------------------------------------------------
SET foreign_key_checks = 1;

-- ---------------------------------------------------------------------------
-- VIEWS (optional helper views for common queries)
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS `v_router_summary`;
CREATE VIEW `v_router_summary` AS
SELECT
    r.id,
    r.name,
    r.ip_address,
    r.identity,
    r.model,
    r.router_os_version,
    r.status,
    r.uptime,
    COUNT(DISTINCT i.id)  AS interface_count,
    COUNT(DISTINCT sq.id) AS queue_count,
    COUNT(DISTINCT pu.id) AS pppoe_active_count,
    r.updated_at          AS last_seen
FROM routers r
LEFT JOIN interfaces   i  ON i.router_id  = r.id
LEFT JOIN simple_queues sq ON sq.router_id = r.id
LEFT JOIN pppoe_users  pu ON pu.router_id = r.id AND pu.status = 'connected'
GROUP BY r.id;

DROP VIEW IF EXISTS `v_top_interfaces_today`;
CREATE VIEW `v_top_interfaces_today` AS
SELECT
    td.target_id                    AS interface_id,
    i.name                          AS interface_name,
    i.router_id,
    r.name                          AS router_name,
    SUM(td.bytes_in)                AS total_bytes_in,
    SUM(td.bytes_out)               AS total_bytes_out,
    SUM(td.bytes_in + td.bytes_out) AS total_bytes
FROM traffic_data td
JOIN interfaces i ON i.id = td.target_id
JOIN routers    r ON r.id = i.router_id
WHERE td.target_type = 'interface'
  AND DATE(td.timestamp) = CURDATE()
GROUP BY td.target_id
ORDER BY total_bytes DESC;

-- TABLE: app_settings
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
    `key`        VARCHAR(100)   NOT NULL,
    `value`      TEXT           NOT NULL,
    `updated_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Application key-value settings store';
