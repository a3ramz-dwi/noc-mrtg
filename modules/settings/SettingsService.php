<?php

declare(strict_types=1);

namespace NOC\Modules\Settings;

use NOC\Core\Database;
use NOC\Core\Logger;

/**
 * SettingsService — business logic for application settings.
 *
 * Reads and writes key-value settings from the `app_settings` table.
 * Falls back gracefully when the table does not exist yet.
 *
 * @package NOC\Modules\Settings
 * @version 1.0.0
 */
final class SettingsService
{
    private readonly Database $db;
    private readonly Logger   $logger;

    public function __construct(?Database $db = null, ?Logger $logger = null)
    {
        $this->db     = $db     ?? Database::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
    }

    /**
     * Return all settings as a key-value array, merged with env defaults.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $defaults = $this->envDefaults();

        try {
            $rows = $this->db->fetchAll(
                'SELECT `key`, `value` FROM `app_settings` ORDER BY `key`',
            );

            foreach ($rows as $row) {
                $defaults[(string) $row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Settings table unavailable, using env defaults.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $defaults;
    }

    /**
     * Persist a batch of settings.
     *
     * @param  array<string, mixed> $settings
     * @return bool
     */
    public function saveAll(array $settings): bool
    {
        $allowed = array_keys($this->envDefaults());

        try {
            $this->db->beginTransaction();

            foreach ($settings as $key => $value) {
                if (!in_array($key, $allowed, true)) {
                    continue;
                }

                $this->db->execute(
                    'INSERT INTO `app_settings` (`key`, `value`)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                    [(string) $key, (string) $value],
                );
            }

            $this->db->commit();
            $this->logger->info('Settings saved.', ['keys' => array_keys($settings)]);
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to save settings.', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Build the default settings map from environment / constants. */
    private function envDefaults(): array
    {
        return [
            'app_name'           => env('APP_NAME', 'NOC Manager'),
            'app_url'            => defined('APP_URL')     ? APP_URL     : '',
            'app_env'            => defined('APP_ENV')     ? APP_ENV     : 'production',
            'app_version'        => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
            'mrtg_dir'           => defined('MRTG_DIR')     ? MRTG_DIR     : '/var/www/mrtg',
            'mrtg_cfg_dir'       => defined('MRTG_CFG_DIR') ? MRTG_CFG_DIR : '/etc/mrtg',
            'mrtg_bin'           => defined('MRTG_BIN')     ? MRTG_BIN     : '/usr/bin/mrtg',
            'snmp_version'       => defined('SNMP_VERSION')   ? SNMP_VERSION   : '2c',
            'snmp_community'     => defined('SNMP_COMMUNITY') ? SNMP_COMMUNITY : 'public',
            'snmp_timeout'       => defined('SNMP_TIMEOUT')   ? SNMP_TIMEOUT   : 5000000,
            'snmp_retries'       => defined('SNMP_RETRIES')   ? SNMP_RETRIES   : 2,
            'snmp_port'          => defined('SNMP_PORT')      ? SNMP_PORT      : 161,
            'log_dir'            => defined('LOG_DIR') ? LOG_DIR : '/var/log/noc',
        ];
    }
}
