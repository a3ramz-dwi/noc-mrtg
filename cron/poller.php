<?php declare(strict_types=1);

/**
 * Traffic Poller — runs every 5 minutes via cron.
 *
 * Polls SNMP traffic counters for all monitored interfaces, queues,
 * and PPPoE users on every active router and saves results to traffic_data.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
$appDir = '/var/www/noc';
require $appDir . '/config/app.php';

spl_autoload_register(static function (string $class) use ($appDir): void {
    $map = [
        'NOC\\Core\\'           => $appDir . '/core/',
        'NOC\\SNMP\\'           => $appDir . '/snmp/',
        'NOC\\MRTG\\'           => $appDir . '/mrtg/',
        'NOC\\Modules\\Routers\\'    => $appDir . '/modules/routers/',
        'NOC\\Modules\\Interfaces\\' => $appDir . '/modules/interfaces/',
        'NOC\\Modules\\Queues\\'     => $appDir . '/modules/queues/',
        'NOC\\Modules\\Pppoe\\'      => $appDir . '/modules/pppoe/',
        'NOC\\Modules\\Monitoring\\' => $appDir . '/modules/monitoring/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\SNMP\SnmpManager;
use NOC\SNMP\SnmpPoller;
use NOC\Modules\Routers\RouterModel;
use NOC\Modules\Interfaces\InterfaceModel;
use NOC\Modules\Queues\QueueModel;
use NOC\Modules\Pppoe\PppoeModel;

// ---------------------------------------------------------------------------
// Lock file — prevent concurrent runs
// ---------------------------------------------------------------------------
$lockFile = '/tmp/noc-poller.lock';

$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Poller already running. Exiting.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Logger
// ---------------------------------------------------------------------------
$logFile = LOG_DIR . '/poller.log';

function pollerLog(string $level, string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

// ---------------------------------------------------------------------------
// Counters
// ---------------------------------------------------------------------------
$totalRouters    = 0;
$totalInterfaces = 0;
$totalQueues     = 0;
$totalPppoe      = 0;
$startTime       = microtime(true);

pollerLog('INFO', 'Poller started');

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------
try {
    $db             = Database::getInstance();
    $logger         = Logger::getInstance(LOG_DIR);
    $routerModel    = new RouterModel($db);
    $interfaceModel = new InterfaceModel($db);
    $queueModel     = new QueueModel($db);
    $pppoeModel     = new PppoeModel($db);

    $routers = $db->fetchAll(
        "SELECT * FROM `routers` WHERE `status` = 'active' ORDER BY `id` ASC"
    );

    pollerLog('INFO', 'Found ' . count($routers) . ' active router(s)');

    foreach ($routers as $router) {
        $routerId = (int) $router['id'];
        $routerName = $router['name'];

        try {
            $snmp = new SnmpManager(
                (string) $router['ip_address'],
                (string) ($router['snmp_community'] ?? 'public'),
                (string) ($router['snmp_version']   ?? '2c'),
                (int) SNMP_TIMEOUT,
                (int) SNMP_RETRIES,
                (int) ($router['snmp_port'] ?? 161),
            );

            $poller = new SnmpPoller($db, $logger);

            // Poll interfaces
            $interfaces = $interfaceModel->findByRouter($routerId);
            $monitoredIfaces = array_filter($interfaces, fn($i) => (int)($i['monitored'] ?? 0) === 1);

            foreach ($monitoredIfaces as $iface) {
                try {
                    $poller->pollInterface($snmp, $router, $iface);
                    $totalInterfaces++;
                } catch (\Throwable $e) {
                    pollerLog('WARNING', "Router {$routerName}: interface {$iface['if_name']} poll failed: {$e->getMessage()}");
                }
            }

            // Poll queues
            $queues = $queueModel->findByRouter($routerId);
            $monitoredQueues = array_filter($queues, fn($q) => (int)($q['monitored'] ?? 0) === 1);

            foreach ($monitoredQueues as $queue) {
                try {
                    $poller->pollQueue($snmp, $router, $queue);
                    $totalQueues++;
                } catch (\Throwable $e) {
                    pollerLog('WARNING', "Router {$routerName}: queue {$queue['name']} poll failed: {$e->getMessage()}");
                }
            }

            // Poll PPPoE users
            $pppoeUsers = $pppoeModel->findByRouter($routerId);
            $monitoredPppoe = array_filter($pppoeUsers, fn($p) => (int)($p['monitored'] ?? 0) === 1);

            foreach ($monitoredPppoe as $user) {
                try {
                    $poller->pollPppoe($snmp, $router, $user);
                    $totalPppoe++;
                } catch (\Throwable $e) {
                    pollerLog('WARNING', "Router {$routerName}: pppoe {$user['name']} poll failed: {$e->getMessage()}");
                }
            }

            // Update router last_seen
            $db->execute(
                "UPDATE `routers` SET `last_seen` = NOW(), `status` = 'active' WHERE `id` = ?",
                [$routerId]
            );

            $totalRouters++;
            pollerLog('INFO', "Router {$routerName}: polled " .
                count($monitoredIfaces) . " iface(s), " .
                count($monitoredQueues) . " queue(s), " .
                count($monitoredPppoe) . " pppoe user(s)");

        } catch (\Throwable $e) {
            pollerLog('ERROR', "Router {$routerName} ({$router['ip_address']}): {$e->getMessage()}");
            try {
                $db->execute(
                    "UPDATE `routers` SET `status` = 'error' WHERE `id` = ?",
                    [$routerId]
                );
            } catch (\Throwable) {
                // ignore DB update failure
            }
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $summary = "Polled {$totalRouters} router(s), {$totalInterfaces} interface(s), {$totalQueues} queue(s), {$totalPppoe} pppoe user(s) in {$elapsed}s";
    pollerLog('INFO', $summary);
    echo $summary . PHP_EOL;

} catch (\Throwable $e) {
    pollerLog('CRITICAL', 'Fatal error: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
    exit(1);
}

// ---------------------------------------------------------------------------
// Release lock
// ---------------------------------------------------------------------------
flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockFile);
exit(0);
