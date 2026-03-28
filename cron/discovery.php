<?php declare(strict_types=1);

/**
 * Auto-discovery cron — runs hourly.
 *
 * Discovers interfaces, queues, and PPPoE users via SNMP for all active
 * routers and syncs the database (upsert / mark offline).
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$appDir = dirname(__DIR__);
require $appDir . '/config/app.php';

spl_autoload_register(static function (string $class) use ($appDir): void {
    $map = [
        'NOC\\Core\\'                => $appDir . '/core/',
        'NOC\\SNMP\\'                => $appDir . '/snmp/',
        'NOC\\MRTG\\'                => $appDir . '/mrtg/',
        'NOC\\Modules\\Routers\\'    => $appDir . '/modules/routers/',
        'NOC\\Modules\\Interfaces\\' => $appDir . '/modules/interfaces/',
        'NOC\\Modules\\Queues\\'     => $appDir . '/modules/queues/',
        'NOC\\Modules\\Pppoe\\'      => $appDir . '/modules/pppoe/',
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
use NOC\SNMP\SnmpDiscovery;

// ---------------------------------------------------------------------------
// Lock file
// ---------------------------------------------------------------------------
$lockFile = '/tmp/noc-discovery.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Discovery already running. Exiting.\n";
    exit(0);
}

$logFile = LOG_DIR . '/discovery.log';

function discoveryLog(string $level, string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

$totalRouters    = 0;
$totalInterfaces = 0;
$totalQueues     = 0;
$totalPppoe      = 0;
$startTime       = microtime(true);

discoveryLog('INFO', 'Discovery started');

try {
    $db     = Database::getInstance();
    $logger = Logger::getInstance(LOG_DIR);

    $routers = $db->fetchAll(
        "SELECT * FROM `routers` WHERE `status` = 'active' ORDER BY `id` ASC"
    );

    discoveryLog('INFO', 'Found ' . count($routers) . ' active router(s)');

    foreach ($routers as $router) {
        $routerId   = (int) $router['id'];
        $routerName = (string) $router['name'];

        try {
            $snmp = new SnmpManager(
                (string) $router['ip_address'],
                (string) ($router['snmp_community'] ?? 'public'),
                (string) ($router['snmp_version']   ?? '2c'),
                (int) SNMP_TIMEOUT,
                (int) SNMP_RETRIES,
                (int) ($router['snmp_port'] ?? 161),
            );

            $discovery = new SnmpDiscovery($snmp);

            // --- Interfaces ---------------------------------------------------
            try {
                $discovered = $discovery->discoverInterfaces();
                $newIfaces  = 0;

                foreach ($discovered as $iface) {
                    $existing = $db->fetch(
                        'SELECT `id` FROM `interfaces` WHERE `router_id` = ? AND `if_index` = ? LIMIT 1',
                        [$routerId, (int) $iface['if_index']]
                    );

                    if ($existing === null) {
                        $db->insert('interfaces', [
                            'router_id'   => $routerId,
                            'if_index'    => (int) ($iface['if_index'] ?? 0),
                            'if_name'     => $iface['if_name']  ?? '',
                            'if_alias'    => $iface['if_alias'] ?? '',
                            'if_type'     => $iface['if_type']  ?? '',
                            'if_speed'    => (int) ($iface['speed'] ?? 0),
                            'mac_address' => $iface['mac_address'] ?? null,
                            'monitored'   => 0,
                            'status'      => $iface['oper_status'] ?? 'unknown',
                        ]);
                        $newIfaces++;
                        $totalInterfaces++;
                    } else {
                        $db->update('interfaces', [
                            'if_name'     => $iface['if_name']  ?? '',
                            'if_alias'    => $iface['if_alias'] ?? '',
                            'if_speed'    => (int) ($iface['speed'] ?? 0),
                            'status'      => $iface['oper_status'] ?? 'unknown',
                            'updated_at'  => date('Y-m-d H:i:s'),
                        ], 'router_id = ? AND if_index = ?', [$routerId, (int) $iface['if_index']]);
                    }
                }

                discoveryLog('INFO', "Router {$routerName}: {$newIfaces} new interface(s) discovered");
            } catch (\Throwable $e) {
                discoveryLog('WARNING', "Router {$routerName}: interface discovery failed: {$e->getMessage()}");
            }

            // --- Queues -------------------------------------------------------
            try {
                $queues  = $discovery->discoverQueues();
                $newQ    = 0;

                foreach ($queues as $queue) {
                    $existing = $db->fetch(
                        'SELECT `id` FROM `simple_queues` WHERE `router_id` = ? AND `name` = ? LIMIT 1',
                        [$routerId, (string) ($queue['name'] ?? '')]
                    );

                    if ($existing === null) {
                        $db->insert('simple_queues', [
                            'router_id'   => $routerId,
                            'queue_index' => (int) ($queue['queue_index'] ?? 0),
                            'name'        => $queue['name']       ?? '',
                            'src_address' => $queue['src_address'] ?? null,
                            'monitored'   => 0,
                        ]);
                        $newQ++;
                        $totalQueues++;
                    } else {
                        $db->update('simple_queues', [
                            'src_address' => $queue['src_address'] ?? null,
                            'updated_at'  => date('Y-m-d H:i:s'),
                        ], 'router_id = ? AND name = ?', [$routerId, (string) ($queue['name'] ?? '')]);
                    }
                }

                discoveryLog('INFO', "Router {$routerName}: {$newQ} new queue(s) discovered");
            } catch (\Throwable $e) {
                discoveryLog('WARNING', "Router {$routerName}: queue discovery failed: {$e->getMessage()}");
            }

            // --- PPPoE --------------------------------------------------------
            try {
                $pppoeUsers    = $discovery->discoverPppoe();
                $discoveredNames = [];
                $newP          = 0;

                foreach ($pppoeUsers as $user) {
                    $userName          = (string) ($user['name'] ?? '');
                    $discoveredNames[] = $userName;

                    $existing = $db->fetch(
                        'SELECT `id` FROM `pppoe_users` WHERE `router_id` = ? AND `name` = ? LIMIT 1',
                        [$routerId, $userName]
                    );

                    if ($existing === null) {
                        $db->insert('pppoe_users', [
                            'router_id'  => $routerId,
                            'name'       => $userName,
                            'ip_address' => $user['ip_address'] ?? null,
                            'mac_address'=> $user['caller_id']  ?? null,
                            'service'    => $user['service']    ?? null,
                            'uptime'     => (int) ($user['uptime'] ?? 0),
                            'status'     => 'online',
                            'monitored'  => 0,
                        ]);
                        $newP++;
                        $totalPppoe++;
                    } else {
                        $db->update('pppoe_users', [
                            'ip_address' => $user['ip_address'] ?? null,
                            'mac_address'=> $user['caller_id']  ?? null,
                            'uptime'     => (int) ($user['uptime'] ?? 0),
                            'status'     => 'online',
                            'last_seen'  => date('Y-m-d H:i:s'),
                        ], 'router_id = ? AND name = ?', [$routerId, $userName]);
                    }
                }

                // Mark users not in the discovered list as offline
                if ($discoveredNames !== []) {
                    $placeholders = implode(',', array_fill(0, count($discoveredNames), '?'));
                    $db->execute(
                        "UPDATE `pppoe_users` SET `status` = 'offline'
                          WHERE `router_id` = ?
                            AND `name` NOT IN ({$placeholders})",
                        array_merge([$routerId], $discoveredNames)
                    );
                } else {
                    $db->execute(
                        "UPDATE `pppoe_users` SET `status` = 'offline' WHERE `router_id` = ?",
                        [$routerId]
                    );
                }

                discoveryLog('INFO', "Router {$routerName}: {$newP} new PPPoE user(s) discovered");
            } catch (\Throwable $e) {
                discoveryLog('WARNING', "Router {$routerName}: PPPoE discovery failed: {$e->getMessage()}");
            }

            $totalRouters++;

        } catch (\Throwable $e) {
            discoveryLog('ERROR', "Router {$routerName}: {$e->getMessage()}");
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $summary = "Discovery done: {$totalRouters} router(s), {$totalInterfaces} new interface(s), " .
               "{$totalQueues} new queue(s), {$totalPppoe} new pppoe user(s) in {$elapsed}s";
    discoveryLog('INFO', $summary);
    echo $summary . PHP_EOL;

} catch (\Throwable $e) {
    discoveryLog('CRITICAL', 'Fatal error: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockFile);
exit(0);
