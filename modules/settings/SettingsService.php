<?php

declare(strict_types=1);

namespace NOC\Modules\Settings;

use NOC\Core\Database;

/**
 * SettingsService — business logic for application settings stored in the DB.
 *
 * Settings are stored as key/value pairs in the `settings` table:
 *   CREATE TABLE IF NOT EXISTS `settings` (
 *     `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
 *     `value`      TEXT         NOT NULL DEFAULT '',
 *     `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * Falls back to environment / app constants when the table does not exist.
 *
 * @package NOC\Modules\Settings
 * @version 1.0.0
 */
final class SettingsService
{
    /** Keys that must never be written directly from user input. */
    private const PROTECTED_KEYS = ['app_secret', 'db_pass'];

    /** Allowed/known setting keys with their defaults. */
    private const DEFAULTS = [
        'app_name'       => 'NOC Manager',
        'app_url'        => '',
        'timezone'       => 'Asia/Jakarta',
        'snmp_community' => 'public',
        'snmp_version'   => '2c',
        'snmp_port'      => '161',
        'snmp_timeout'   => '5000000',
        'snmp_retries'   => '2',
        'poll_interval'  => '300',
        'log_retention'  => '90',
        'mrtg_dir'       => '/var/www/mrtg',
        'mrtg_cfg'       => '/etc/mrtg',
    ];

    private readonly Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return all settings as an associative array (key => value).
     * Merges defaults with DB-stored values.
     *
     * @return array<string, string>
     */
    public function getAll(): array
    {
        $settings = self::DEFAULTS;

        try {
            $rows = $this->db->fetchAll('SELECT `key`, `value` FROM `settings`');
            foreach ($rows as $row) {
                $settings[(string) $row['key']] = (string) $row['value'];
            }
        } catch (\Throwable) {
            // Table may not exist yet — return defaults.
        }

        // Overlay env/constant values for display.
        $settings['app_url']        = defined('APP_URL')        ? APP_URL        : $settings['app_url'];
        $settings['mrtg_dir']       = defined('MRTG_DIR')       ? MRTG_DIR       : $settings['mrtg_dir'];
        $settings['snmp_community'] = defined('SNMP_COMMUNITY') ? SNMP_COMMUNITY : $settings['snmp_community'];
        $settings['snmp_version']   = defined('SNMP_VERSION')   ? SNMP_VERSION   : $settings['snmp_version'];
        $settings['snmp_port']      = defined('SNMP_PORT')      ? (string) SNMP_PORT : $settings['snmp_port'];

        return $settings;
    }

    /**
     * Persist an array of settings.
     *
     * @param  array<string, mixed> $data  Raw POST data.
     * @return array{success: bool, message: string}
     */
    public function saveAll(array $data): array
    {
        $allowed = array_keys(self::DEFAULTS);

        try {
            foreach ($allowed as $key) {
                if (in_array($key, self::PROTECTED_KEYS, true)) {
                    continue;
                }

                if (!array_key_exists($key, $data)) {
                    continue;
                }

                $value = trim((string) $data[$key]);
                $this->upsert($key, $value);
            }

            return ['success' => true, 'message' => 'Settings saved.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    /**
     * Get a single setting value.
     */
    public function get(string $key, string $default = ''): string
    {
        $all = $this->getAll();
        return $all[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function upsert(string $key, string $value): void
    {
        $this->db->execute(
            'INSERT INTO `settings` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value],
        );
    }
}
