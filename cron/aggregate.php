<?php declare(strict_types=1);

/**
 * Traffic data aggregation — runs every hour.
 *
 * Aggregates raw traffic_data into traffic_daily, traffic_weekly,
 * and traffic_monthly using INSERT … ON DUPLICATE KEY UPDATE.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$appDir = '/var/www/noc';
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
$lockFile = '/tmp/noc-aggregate.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Aggregation already running. Exiting.\n";
    exit(0);
}

$logFile = LOG_DIR . '/aggregate.log';

function aggLog(string $level, string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

// ---------------------------------------------------------------------------
// Optional period argument: hourly | daily | weekly | monthly | (all)
// ---------------------------------------------------------------------------
$requestedPeriod = $argv[1] ?? $_SERVER['argv'][1] ?? null;
if ($requestedPeriod !== null && !in_array($requestedPeriod, ['hourly', 'daily', 'weekly', 'monthly'], true)) {
    echo "Unknown period '{$requestedPeriod}'. Valid values: hourly, daily, weekly, monthly\n";
    exit(1);
}

aggLog('INFO', 'Aggregation started' . ($requestedPeriod !== null ? " (period: {$requestedPeriod})" : ' (all periods)'));
$startTime = microtime(true);

try {
    $db = Database::getInstance();

    $dailyRows  = 0;
    $weeklyRows = 0;
    $monthlyRows = 0;

    // -------------------------------------------------------------------------
    // 1. Raw → traffic_daily (last 48 h of raw data to catch any gap)
    //    Runs for periods: hourly, daily, or all
    // -------------------------------------------------------------------------
    if ($requestedPeriod === null || $requestedPeriod === 'hourly' || $requestedPeriod === 'daily') {
        $dailySql = "
            INSERT INTO `traffic_daily`
                (`target_type`, `target_id`, `date`,
                 `avg_bytes_in`, `avg_bytes_out`,
                 `max_bytes_in`, `max_bytes_out`,
                 `total_bytes_in`, `total_bytes_out`,
                 `samples`)
            SELECT
                `target_type`,
                `target_id`,
                DATE(`timestamp`) AS `date`,
                AVG(`bytes_in`)   AS `avg_bytes_in`,
                AVG(`bytes_out`)  AS `avg_bytes_out`,
                MAX(`bytes_in`)   AS `max_bytes_in`,
                MAX(`bytes_out`)  AS `max_bytes_out`,
                SUM(`bytes_in`)   AS `total_bytes_in`,
                SUM(`bytes_out`)  AS `total_bytes_out`,
                COUNT(*)          AS `samples`
            FROM `traffic_data`
            WHERE `timestamp` >= NOW() - INTERVAL 48 HOUR
            GROUP BY `target_type`, `target_id`, DATE(`timestamp`)
            ON DUPLICATE KEY UPDATE
                `avg_bytes_in`    = VALUES(`avg_bytes_in`),
                `avg_bytes_out`   = VALUES(`avg_bytes_out`),
                `max_bytes_in`    = GREATEST(`max_bytes_in`,  VALUES(`max_bytes_in`)),
                `max_bytes_out`   = GREATEST(`max_bytes_out`, VALUES(`max_bytes_out`)),
                `total_bytes_in`  = VALUES(`total_bytes_in`),
                `total_bytes_out` = VALUES(`total_bytes_out`),
                `samples`         = VALUES(`samples`)
        ";

        $dailyRows = $db->execute($dailySql);
        aggLog('INFO', "Daily aggregation: {$dailyRows} row(s) inserted/updated");
    }

    // -------------------------------------------------------------------------
    // 2. traffic_daily → traffic_weekly (last 60 days of daily data)
    //    Runs for periods: weekly, or all
    // -------------------------------------------------------------------------
    if ($requestedPeriod === null || $requestedPeriod === 'weekly') {
        $weeklySql = "
            INSERT INTO `traffic_weekly`
                (`target_type`, `target_id`, `year`, `week`,
                 `avg_bytes_in`, `avg_bytes_out`,
                 `max_bytes_in`, `max_bytes_out`,
                 `total_bytes_in`, `total_bytes_out`,
                 `samples`)
            SELECT
                `target_type`,
                `target_id`,
                YEAR(`date`)            AS `year`,
                WEEK(`date`, 1)         AS `week`,
                AVG(`avg_bytes_in`)     AS `avg_bytes_in`,
                AVG(`avg_bytes_out`)    AS `avg_bytes_out`,
                MAX(`max_bytes_in`)     AS `max_bytes_in`,
                MAX(`max_bytes_out`)    AS `max_bytes_out`,
                SUM(`total_bytes_in`)   AS `total_bytes_in`,
                SUM(`total_bytes_out`)  AS `total_bytes_out`,
                SUM(`samples`)          AS `samples`
            FROM `traffic_daily`
            WHERE `date` >= CURDATE() - INTERVAL 60 DAY
            GROUP BY `target_type`, `target_id`, YEAR(`date`), WEEK(`date`, 1)
            ON DUPLICATE KEY UPDATE
                `avg_bytes_in`    = VALUES(`avg_bytes_in`),
                `avg_bytes_out`   = VALUES(`avg_bytes_out`),
                `max_bytes_in`    = GREATEST(`max_bytes_in`,  VALUES(`max_bytes_in`)),
                `max_bytes_out`   = GREATEST(`max_bytes_out`, VALUES(`max_bytes_out`)),
                `total_bytes_in`  = VALUES(`total_bytes_in`),
                `total_bytes_out` = VALUES(`total_bytes_out`),
                `samples`         = VALUES(`samples`)
        ";

        $weeklyRows = $db->execute($weeklySql);
        aggLog('INFO', "Weekly aggregation: {$weeklyRows} row(s) inserted/updated");
    }

    // -------------------------------------------------------------------------
    // 3. traffic_daily → traffic_monthly
    //    Runs for periods: monthly, or all
    // -------------------------------------------------------------------------
    if ($requestedPeriod === null || $requestedPeriod === 'monthly') {
        $monthlySql = "
            INSERT INTO `traffic_monthly`
                (`target_type`, `target_id`, `year`, `month`,
                 `avg_bytes_in`, `avg_bytes_out`,
                 `max_bytes_in`, `max_bytes_out`,
                 `total_bytes_in`, `total_bytes_out`,
                 `samples`)
            SELECT
                `target_type`,
                `target_id`,
                YEAR(`date`)           AS `year`,
                MONTH(`date`)          AS `month`,
                AVG(`avg_bytes_in`)    AS `avg_bytes_in`,
                AVG(`avg_bytes_out`)   AS `avg_bytes_out`,
                MAX(`max_bytes_in`)    AS `max_bytes_in`,
                MAX(`max_bytes_out`)   AS `max_bytes_out`,
                SUM(`total_bytes_in`)  AS `total_bytes_in`,
                SUM(`total_bytes_out`) AS `total_bytes_out`,
                SUM(`samples`)         AS `samples`
            FROM `traffic_daily`
            WHERE `date` >= CURDATE() - INTERVAL 24 MONTH
            GROUP BY `target_type`, `target_id`, YEAR(`date`), MONTH(`date`)
            ON DUPLICATE KEY UPDATE
                `avg_bytes_in`    = VALUES(`avg_bytes_in`),
                `avg_bytes_out`   = VALUES(`avg_bytes_out`),
                `max_bytes_in`    = GREATEST(`max_bytes_in`,  VALUES(`max_bytes_in`)),
                `max_bytes_out`   = GREATEST(`max_bytes_out`, VALUES(`max_bytes_out`)),
                `total_bytes_in`  = VALUES(`total_bytes_in`),
                `total_bytes_out` = VALUES(`total_bytes_out`),
                `samples`         = VALUES(`samples`)
        ";

        $monthlyRows = $db->execute($monthlySql);
        aggLog('INFO', "Monthly aggregation: {$monthlyRows} row(s) inserted/updated");
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $summary = "Aggregation complete in {$elapsed}s — daily: {$dailyRows}, weekly: {$weeklyRows}, monthly: {$monthlyRows}";
    aggLog('INFO', $summary);
    echo $summary . PHP_EOL;

} catch (\Throwable $e) {
    aggLog('CRITICAL', 'Fatal error: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockFile);
exit(0);
