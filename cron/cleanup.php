<?php declare(strict_types=1);

/**
 * Data cleanup — runs daily at midnight.
 *
 * Deletes old traffic_data, traffic_daily, traffic_weekly, login_attempts,
 * and audit_log rows according to configured retention policies, then
 * optimises the affected tables.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$appDir = dirname(__DIR__);
require $appDir . '/config/app.php';

spl_autoload_register(static function (string $class) use ($appDir): void {
    if (str_starts_with($class, 'NOC\\Core\\')) {
        $file = $appDir . '/core/' . str_replace('\\', '/', substr($class, 9)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use NOC\Core\Database;

// ---------------------------------------------------------------------------
// Lock file
// ---------------------------------------------------------------------------
$lockFile = '/tmp/noc-cleanup.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup already running. Exiting.\n";
    exit(0);
}

$logFile = LOG_DIR . '/cleanup.log';

function cleanLog(string $level, string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

cleanLog('INFO', 'Cleanup started');
$startTime = microtime(true);

try {
    $db = Database::getInstance();

    // -------------------------------------------------------------------------
    // Delete raw traffic_data older than 7 days
    // -------------------------------------------------------------------------
    $rows = $db->execute(
        "DELETE FROM `traffic_data` WHERE `timestamp` < NOW() - INTERVAL 7 DAY"
    );
    cleanLog('INFO', "traffic_data: deleted {$rows} row(s) older than 7 days");

    // -------------------------------------------------------------------------
    // Delete traffic_daily older than 90 days
    // -------------------------------------------------------------------------
    $rows = $db->execute(
        "DELETE FROM `traffic_daily` WHERE `date` < CURDATE() - INTERVAL 90 DAY"
    );
    cleanLog('INFO', "traffic_daily: deleted {$rows} row(s) older than 90 days");

    // -------------------------------------------------------------------------
    // Delete traffic_weekly older than 365 days
    // -------------------------------------------------------------------------
    $rows = $db->execute(
        "DELETE FROM `traffic_weekly`
          WHERE STR_TO_DATE(CONCAT(`year`, '-', `week`, ' Monday'), '%X-%V %W')
                < CURDATE() - INTERVAL 365 DAY"
    );
    cleanLog('INFO', "traffic_weekly: deleted {$rows} row(s) older than 365 days");

    // -------------------------------------------------------------------------
    // Delete login_attempts older than 30 days
    // -------------------------------------------------------------------------
    $rows = $db->execute(
        "DELETE FROM `login_attempts` WHERE `attempted_at` < NOW() - INTERVAL 30 DAY"
    );
    cleanLog('INFO', "login_attempts: deleted {$rows} row(s) older than 30 days");

    // -------------------------------------------------------------------------
    // Delete audit_log older than 180 days
    // -------------------------------------------------------------------------
    $rows = $db->execute(
        "DELETE FROM `audit_log` WHERE `created_at` < NOW() - INTERVAL 180 DAY"
    );
    cleanLog('INFO', "audit_log: deleted {$rows} row(s) older than 180 days");

    // -------------------------------------------------------------------------
    // Optimize/vacuum tables
    // -------------------------------------------------------------------------
    $tables = [
        'traffic_data', 'traffic_daily', 'traffic_weekly',
        'traffic_monthly', 'login_attempts', 'audit_log',
    ];

    foreach ($tables as $table) {
        try {
            $db->query("OPTIMIZE TABLE `{$table}`");
            cleanLog('INFO', "Optimized table: {$table}");
        } catch (\Throwable $e) {
            cleanLog('WARNING', "Failed to optimize {$table}: {$e->getMessage()}");
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $summary = "Cleanup complete in {$elapsed}s";
    cleanLog('INFO', $summary);
    echo $summary . PHP_EOL;

} catch (\Throwable $e) {
    cleanLog('CRITICAL', 'Fatal error: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockFile);
exit(0);
