<?php declare(strict_types=1);

/**
 * Application Configuration
 *
 * Bootstraps environment variables and defines all application constants.
 * Load this file first in the application entry point.
 *
 * @package NOC\Config
 * @version 1.0.0
 */

// ---------------------------------------------------------------------------
// Load .env from parent directory (one level above the project root)
// ---------------------------------------------------------------------------
$envFile = dirname(__DIR__, 1) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($key !== '' && !isset($_ENV[$key])) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

/**
 * Helper: read an environment variable with an optional default.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return match (strtolower((string) $value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        'empty', '(empty)' => '',
        default            => $value,
    };
}

// ---------------------------------------------------------------------------
// Application constants
// ---------------------------------------------------------------------------
defined('APP_DIR')     || define('APP_DIR',     env('APP_DIR',     '/var/www/noc'));
defined('APP_URL')     || define('APP_URL',     rtrim((string) env('APP_URL', 'http://localhost'), '/'));
defined('APP_ENV')     || define('APP_ENV',     env('APP_ENV',     'production'));
defined('APP_SECRET')  || define('APP_SECRET',  env('APP_SECRET',  'change-me-in-production'));
defined('APP_VERSION') || define('APP_VERSION', env('APP_VERSION', '1.0.0'));

// ---------------------------------------------------------------------------
// Database constants
// ---------------------------------------------------------------------------
defined('DB_HOST')    || define('DB_HOST',    env('DB_HOST',    'localhost'));
defined('DB_PORT')    || define('DB_PORT',    (int) env('DB_PORT', 3306));
defined('DB_NAME')    || define('DB_NAME',    env('DB_NAME',    'noc_manager'));
defined('DB_USER')    || define('DB_USER',    env('DB_USER',    'noc_user'));
defined('DB_PASS')    || define('DB_PASS',    env('DB_PASS',    ''));
defined('DB_CHARSET') || define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// ---------------------------------------------------------------------------
// MRTG constants
// ---------------------------------------------------------------------------
defined('MRTG_DIR')     || define('MRTG_DIR',     env('MRTG_DIR',     '/var/www/mrtg'));
defined('MRTG_CFG_DIR') || define('MRTG_CFG_DIR', env('MRTG_CFG',     '/etc/mrtg'));
defined('MRTG_BIN')     || define('MRTG_BIN',     env('MRTG_BIN',     '/usr/bin/mrtg'));

// ---------------------------------------------------------------------------
// Logging constants
// ---------------------------------------------------------------------------
defined('LOG_DIR') || define('LOG_DIR', env('LOG_DIR', '/var/log/noc'));

// ---------------------------------------------------------------------------
// SNMP constants
// ---------------------------------------------------------------------------
defined('SNMP_VERSION')   || define('SNMP_VERSION',   env('SNMP_VERSION',   '2c'));
defined('SNMP_COMMUNITY') || define('SNMP_COMMUNITY', env('SNMP_COMMUNITY', 'public'));
defined('SNMP_TIMEOUT')   || define('SNMP_TIMEOUT',   (int) env('SNMP_TIMEOUT',  5000000));
defined('SNMP_RETRIES')   || define('SNMP_RETRIES',   (int) env('SNMP_RETRIES',  2));
defined('SNMP_PORT')      || define('SNMP_PORT',       (int) env('SNMP_PORT',     161));

// ---------------------------------------------------------------------------
// Error reporting
// ---------------------------------------------------------------------------
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_DIR . '/php-error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors',     '1');
    ini_set('error_log',      LOG_DIR . '/php-error.log');
}

// ---------------------------------------------------------------------------
// Timezone
// ---------------------------------------------------------------------------
date_default_timezone_set('Asia/Jakarta');

// ---------------------------------------------------------------------------
// Session security settings
// ---------------------------------------------------------------------------
ini_set('session.use_strict_mode',      '1');
ini_set('session.use_cookies',          '1');
ini_set('session.use_only_cookies',     '1');
ini_set('session.use_trans_sid',        '0');
ini_set('session.cookie_httponly',      '1');
ini_set('session.cookie_samesite',      'Strict');
ini_set('session.gc_maxlifetime',       '7200');
ini_set('session.gc_probability',       '1');
ini_set('session.gc_divisor',           '100');
ini_set('session.entropy_length',       '32');
ini_set('session.hash_function',        'sha256');

// Use secure cookies only when served over HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
