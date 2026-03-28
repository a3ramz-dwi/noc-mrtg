<?php declare(strict_types=1);

/**
 * MRTG config regeneration — runs every 15 minutes.
 *
 * Checks whether any router/interface/queue/pppoe record has been modified
 * since the last generation and, if so, regenerates MRTG configs.
 */

if (PHP_SAPI !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$appDir = dirname(__DIR__);
require $appDir . '/config/app.php';

spl_autoload_register(static function (string $class) use ($appDir): void {
    $map = [
        'NOC\\Core\\'    => $appDir . '/core/',
        'NOC\\SNMP\\'    => $appDir . '/snmp/',
        'NOC\\MRTG\\'    => $appDir . '/mrtg/',
        'NOC\\Modules\\' => $appDir . '/modules/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            // Modules follow subdirectory pattern, e.g. Routers/RouterModel
            $file = $dir . $relative . '.php';
            if (is_file($file)) {
                require $file;
                return;
            }
            // Try lower-casing the first segment for module subdirs
            $parts   = explode('/', $relative, 2);
            $subFile = $dir . strtolower($parts[0]) . '/' . ($parts[1] ?? $parts[0]) . '.php';
            if (is_file($subFile)) {
                require $subFile;
            }
            return;
        }
    }
});

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\MRTG\MrtgConfigGenerator;

// ---------------------------------------------------------------------------
// Lock file
// ---------------------------------------------------------------------------
$lockFile = '/tmp/noc-mrtg-gen.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] MRTG generator already running. Exiting.\n";
    exit(0);
}

$logFile = LOG_DIR . '/mrtg_gen.log';

function mrtgLog(string $level, string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

mrtgLog('INFO', 'MRTG generator started');
$startTime = microtime(true);

try {
    $db        = Database::getInstance();
    $logger    = Logger::getInstance(LOG_DIR);
    $generator = new MrtgConfigGenerator($db, $logger);

    // Determine the timestamp of the last generation from the mrtg_configs table
    $lastGen = (string) ($db->fetchColumn(
        'SELECT MAX(`updated_at`) FROM `mrtg_configs`'
    ) ?? '1970-01-01 00:00:00');

    // Check whether anything changed since lastGen
    $changedRouters = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM `routers` WHERE `updated_at` > ?",
        [$lastGen]
    );
    $changedIfaces = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM `interfaces` WHERE `updated_at` > ?",
        [$lastGen]
    );
    $changedQueues = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM `simple_queues` WHERE `updated_at` > ?",
        [$lastGen]
    );
    $changedPppoe = (int) $db->fetchColumn(
        "SELECT COUNT(*) FROM `pppoe_users` WHERE `updated_at` > ?",
        [$lastGen]
    );

    $totalChanges = $changedRouters + $changedIfaces + $changedQueues + $changedPppoe;

    if ($totalChanges === 0 && $lastGen !== '1970-01-01 00:00:00') {
        mrtgLog('INFO', 'No changes detected since last generation. Skipping.');
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($lockFile);
        exit(0);
    }

    mrtgLog('INFO', "Changes detected — routers: {$changedRouters}, interfaces: {$changedIfaces}, " .
        "queues: {$changedQueues}, pppoe: {$changedPppoe}. Regenerating...");

    $ok = $generator->generateAll();

    if (!$ok) {
        mrtgLog('ERROR', 'MrtgConfigGenerator::generateAll() returned false');
    } else {
        mrtgLog('INFO', 'MRTG configs regenerated successfully');
    }

    // Run cfgmaker / indexmaker if binaries exist
    $cfgDir  = defined('MRTG_CFG_DIR') ? MRTG_CFG_DIR : '/etc/mrtg';
    $webDir  = defined('MRTG_DIR')     ? MRTG_DIR     : '/var/www/mrtg';

    $cfgmaker   = '/usr/bin/cfgmaker';
    $indexmaker = '/usr/bin/indexmaker';

    if (is_executable($cfgmaker)) {
        $routers = $db->fetchAll(
            "SELECT * FROM `routers` WHERE `status` = 'active'"
        );
        foreach ($routers as $router) {
            $community  = escapeshellarg((string) ($router['snmp_community'] ?? 'public'));
            $ip         = escapeshellarg((string) $router['ip_address']);
            $outputFile = escapeshellarg($cfgDir . '/router-' . $router['id'] . '.cfg');
            $cmd = "{$cfgmaker} {$community}@{$ip} > {$outputFile} 2>&1";
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                mrtgLog('WARNING', "cfgmaker failed for router {$router['name']}: exit {$rc}");
            }
        }
    }

    if (is_executable($indexmaker)) {
        $cfgFiles = glob($cfgDir . '/*.cfg') ?: [];
        if ($cfgFiles !== []) {
            $fileList   = implode(' ', array_map('escapeshellarg', $cfgFiles));
            $outputHtml = escapeshellarg($webDir . '/index.html');
            $cmd        = "{$indexmaker} {$fileList} > {$outputHtml} 2>&1";
            exec($cmd, $out, $rc);
            if ($rc !== 0) {
                mrtgLog('WARNING', "indexmaker failed: exit {$rc}");
            } else {
                mrtgLog('INFO', 'indexmaker updated ' . $webDir . '/index.html');
            }
        }
    }

    $elapsed = round(microtime(true) - $startTime, 2);
    $summary = "MRTG generation complete in {$elapsed}s";
    mrtgLog('INFO', $summary);
    echo $summary . PHP_EOL;

} catch (\Throwable $e) {
    mrtgLog('CRITICAL', 'Fatal error: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
@unlink($lockFile);
exit(0);
