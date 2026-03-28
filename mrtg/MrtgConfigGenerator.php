<?php declare(strict_types=1);

namespace NOC\MRTG;

use NOC\Core\Database;
use NOC\Core\Logger;

/**
 * MrtgConfigGenerator — Generates MRTG .cfg files for routers and their targets.
 *
 * Produces one configuration file per router containing global MRTG settings
 * followed by one Target block per monitored interface, queue, and PPPoE session.
 * Config files are written to /etc/mrtg/ and a reference is stored in the
 * `mrtg_configs` database table.
 *
 * @package NOC\MRTG
 * @version 1.0.0
 */
final class MrtgConfigGenerator
{
    /** Base filesystem path for MRTG config files */
    private const MRTG_CONFIG_DIR = '/etc/mrtg';

    /** Base web-accessible directory for MRTG HTML and image output */
    private const MRTG_WEB_DIR = '/var/www/mrtg';

    /** Default MRTG polling interval in seconds */
    private const MRTG_INTERVAL = 300;

    public function __construct(
        private readonly Database $db,
        private readonly Logger   $logger,
    ) {}

    // -------------------------------------------------------------------------
    // Bulk generation
    // -------------------------------------------------------------------------

    /**
     * Generate MRTG configuration files for all active routers.
     *
     * @return array{success:int,failed:int}  Count of successes and failures.
     */
    public function generateAll(): array
    {
        $routers = $this->db->fetchAll(
            "SELECT id, name, ip_address, snmp_community, snmp_version, snmp_port
               FROM routers
              WHERE status = 'active'
              ORDER BY id ASC",
        );

        $success = 0;
        $failed  = 0;

        foreach ($routers as $router) {
            if ($this->generateForRouter((int) $router['id'])) {
                ++$success;
            } else {
                ++$failed;
            }
        }

        $this->logger->info('MrtgConfigGenerator: bulk generation complete', [
            'success' => $success,
            'failed'  => $failed,
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Generate the MRTG configuration for a single router.
     *
     * Creates the output directories, builds the config content, writes the
     * .cfg file to disk, and records the result in `mrtg_configs`.
     *
     * @param  int  $routerId  PK from the `routers` table.
     * @return bool            True on success.
     */
    public function generateForRouter(int $routerId): bool
    {
        $router = $this->db->fetch(
            "SELECT id, name, ip_address, snmp_community, snmp_version, snmp_port
               FROM routers
              WHERE id = ? AND status = 'active'",
            [$routerId],
        );

        if ($router === null) {
            $this->logger->warning('MrtgConfigGenerator: router not found', ['router_id' => $routerId]);
            return false;
        }

        try {
            $this->createDirectories($routerId);
        } catch (\Throwable $e) {
            $this->logger->error('MrtgConfigGenerator: failed to create directories', [
                'router_id' => $routerId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }

        $lines = [$this->getGlobalConfig($router)];

        foreach ($this->getMonitoredInterfaces($routerId) as $iface) {
            $lines[] = $this->generateInterfaceTarget($iface, $router);
        }

        foreach ($this->getMonitoredQueues($routerId) as $queue) {
            $lines[] = $this->generateQueueTarget($queue, $router);
        }

        foreach ($this->getMonitoredPppoe($routerId) as $pppoe) {
            $lines[] = $this->generatePppoeTarget($pppoe, $router);
        }

        $content  = implode("\n\n", $lines) . "\n";
        $filename = self::MRTG_CONFIG_DIR . '/router_' . $routerId . '.cfg';

        if (!$this->writeConfigFile($filename, $content)) {
            $this->saveToDatabase($routerId, $filename, $content, 'error');
            return false;
        }

        $this->saveToDatabase($routerId, $filename, $content, 'active');

        $this->logger->info('MrtgConfigGenerator: config generated', [
            'router_id' => $routerId,
            'filename'  => $filename,
        ]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Target block generators
    // -------------------------------------------------------------------------

    /**
     * Build an MRTG Target configuration block for a network interface.
     *
     * Uses OID-based target syntax so MRTG polls the exact in/out octets
     * counters directly, without relying on community@host shorthand which
     * does not expose the full OID path.
     *
     * @param  array<string,mixed> $interface  Row from `interfaces`.
     * @param  array<string,mixed> $router     Row from `routers`.
     * @return string                          Multi-line MRTG config block.
     */
    public function generateInterfaceTarget(array $interface, array $router): string
    {
        $targetId  = $this->makeTargetId('if', $router['id'], $interface['if_index']);
        $ifIndex   = (int) $interface['if_index'];
        $community = $router['snmp_community'];
        $ip        = $router['ip_address'];
        $title     = $this->escapeTitle((string) ($interface['alias'] ?: $interface['name']));
        $label     = $this->escapeTitle((string) $interface['name']);

        // MaxBytes: use interface speed / 8 (bits → bytes), default 125 MB/s (1 Gbps)
        $speedBps  = (int) ($interface['speed'] ?? 0);
        $maxBytes  = $speedBps > 0 ? (int) ($speedBps / 8) : 125_000_000;

        $oidIn  = '.1.3.6.1.2.1.2.2.1.10.' . $ifIndex;
        $oidOut = '.1.3.6.1.2.1.2.2.1.16.' . $ifIndex;

        // For interfaces > 100 Mbps, use HC (64-bit) counters.
        if ($speedBps > 100_000_000) {
            $oidIn  = '.1.3.6.1.2.1.31.1.1.1.6.'  . $ifIndex;
            $oidOut = '.1.3.6.1.2.1.31.1.1.1.10.' . $ifIndex;
        }

        return <<<CFG
            ### Interface: {$interface['name']} (ifIndex {$ifIndex})
            Target[{$targetId}]: {$oidIn}&{$oidOut}:{$community}@{$ip}
            SetEnv[{$targetId}]: MRTG_INT_IP="{$ip}" MRTG_INT_DESCR="{$label}"
            MaxBytes[{$targetId}]: {$maxBytes}
            Title[{$targetId}]: {$title} — Traffic
            PageTop[{$targetId}]: <h1>{$title}</h1>
            Options[{$targetId}]: growright,bits
            YLegend[{$targetId}]: Bits per second
            ShortLegend[{$targetId}]: bps
            Legend1[{$targetId}]: Incoming Traffic
            Legend2[{$targetId}]: Outgoing Traffic
            LegendI[{$targetId}]:  In:
            LegendO[{$targetId}]: Out:
            CFG;
    }

    /**
     * Build an MRTG Target block for a MikroTik Simple Queue entry.
     *
     * @param  array<string,mixed> $queue   Row from `simple_queues`.
     * @param  array<string,mixed> $router  Row from `routers`.
     * @return string
     */
    public function generateQueueTarget(array $queue, array $router): string
    {
        $targetId   = $this->makeTargetId('q', $router['id'], $queue['queue_index']);
        $queueIndex = (int) $queue['queue_index'];
        $community  = $router['snmp_community'];
        $ip         = $router['ip_address'];
        $title      = $this->escapeTitle((string) $queue['name']);
        $target     = $queue['target'] ?? '';

        // MikroTik queue byte counters (upload = in from queue perspective, download = out)
        $oidIn  = '.1.3.6.1.4.1.14988.1.1.2.1.1.8.' . $queueIndex;
        $oidOut = '.1.3.6.1.4.1.14988.1.1.2.1.1.9.' . $queueIndex;

        // Derive MaxBytes from the configured queue limits, default 12.5 MB/s (100 Mbps)
        $limitIn  = (int) ($queue['max_limit_upload']   ?? 0);
        $limitOut = (int) ($queue['max_limit_download'] ?? 0);
        $maxBytes = max($limitIn, $limitOut) > 0
            ? (int) (max($limitIn, $limitOut) / 8)
            : 12_500_000;

        return <<<CFG
            ### Queue: {$queue['name']} (index {$queueIndex}) target={$target}
            Target[{$targetId}]: {$oidIn}&{$oidOut}:{$community}@{$ip}
            SetEnv[{$targetId}]: MRTG_INT_IP="{$ip}" MRTG_INT_DESCR="{$title}"
            MaxBytes[{$targetId}]: {$maxBytes}
            Title[{$targetId}]: Queue: {$title} — Traffic
            PageTop[{$targetId}]: <h1>Queue: {$title}</h1>
            Options[{$targetId}]: growright,bits
            YLegend[{$targetId}]: Bits per second
            ShortLegend[{$targetId}]: bps
            Legend1[{$targetId}]: Upload (In)
            Legend2[{$targetId}]: Download (Out)
            LegendI[{$targetId}]:  Up:
            LegendO[{$targetId}]: Dn:
            CFG;
    }

    /**
     * Build an MRTG Target block for a PPPoE session.
     *
     * PPPoE byte counters are polled via the queue MIB entries associated with
     * each active session when available.  The target ID encodes both router and
     * PPPoE user record PK for uniqueness across router-reuse of usernames.
     *
     * @param  array<string,mixed> $pppoe   Row from `pppoe_users`.
     * @param  array<string,mixed> $router  Row from `routers`.
     * @return string
     */
    public function generatePppoeTarget(array $pppoe, array $router): string
    {
        $targetId  = $this->makeTargetId('ppp', $router['id'], $pppoe['id']);
        $community = $router['snmp_community'];
        $ip        = $router['ip_address'];
        $username  = $this->escapeTitle((string) $pppoe['name']);
        $service   = $this->escapeTitle((string) ($pppoe['service'] ?? ''));

        // PPPoE traffic is best tracked at the interface level; MRTG will
        // resolve the ifIndex via community@host with the ifDescr filter.
        // We use a standard community@host target; MRTG will pick up the right
        // interface because the Target specifies the exact OID pair below.
        // Here we fall back to the generic ifInOctets/ifOutOctets for the
        // dynamic PPPoE interface index (resolved at poll time by MRTG's
        // Perl extension or via an external script — set to 0 as a placeholder
        // that should be updated dynamically by the cron job when if_index is known).
        $ifIndex = (int) ($pppoe['if_index'] ?? 0);

        if ($ifIndex > 0) {
            $oidIn  = '.1.3.6.1.2.1.31.1.1.1.6.'  . $ifIndex;
            $oidOut = '.1.3.6.1.2.1.31.1.1.1.10.' . $ifIndex;
            $target = "{$oidIn}&{$oidOut}:{$community}@{$ip}";
        } else {
            // Fallback: use community@host notation (MRTG will prompt for ifIndex).
            $target = "{$community}@{$ip}";
        }

        return <<<CFG
            ### PPPoE: {$pppoe['name']} (id {$pppoe['id']}) service={$service}
            Target[{$targetId}]: {$target}
            SetEnv[{$targetId}]: MRTG_INT_IP="{$ip}" MRTG_INT_DESCR="PPPoE:{$username}"
            MaxBytes[{$targetId}]: 12500000
            Title[{$targetId}]: PPPoE: {$username} — Traffic
            PageTop[{$targetId}]: <h1>PPPoE: {$username}</h1>
            Options[{$targetId}]: growright,bits
            YLegend[{$targetId}]: Bits per second
            ShortLegend[{$targetId}]: bps
            Legend1[{$targetId}]: Download (In)
            Legend2[{$targetId}]: Upload (Out)
            LegendI[{$targetId}]: Dn:
            LegendO[{$targetId}]: Up:
            CFG;
    }

    // -------------------------------------------------------------------------
    // File I/O
    // -------------------------------------------------------------------------

    /**
     * Write config content to a file on the filesystem.
     *
     * Creates the parent directory if it does not exist.  Uses exclusive file
     * locking to prevent partial writes from concurrent processes.
     *
     * @param  string $filename  Absolute path to target .cfg file.
     * @param  string $content   Full configuration content.
     * @return bool              True on success, false on I/O error.
     */
    public function writeConfigFile(string $filename, string $content): bool
    {
        $dir = dirname($filename);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                $this->logger->error('MrtgConfigGenerator: cannot create config directory', ['dir' => $dir]);
                return false;
            }
        }

        $handle = @fopen($filename, 'wb');
        if ($handle === false) {
            $this->logger->error('MrtgConfigGenerator: cannot open file for writing', ['file' => $filename]);
            return false;
        }

        flock($handle, LOCK_EX);
        $written = fwrite($handle, $content);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($written === false || $written !== strlen($content)) {
            $this->logger->error('MrtgConfigGenerator: incomplete write to file', ['file' => $filename]);
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Global config header
    // -------------------------------------------------------------------------

    /**
     * Build the MRTG global configuration header for a router.
     *
     * @param  array<string,mixed> $router  Row from `routers`.
     * @return string
     */
    public function getGlobalConfig(array $router): string
    {
        $routerId  = (int) $router['id'];
        $webDir    = self::MRTG_WEB_DIR . '/' . $routerId;
        $routerIp  = $router['ip_address'];
        $routerName = $router['name'] ?? "Router {$routerId}";

        return <<<CFG
            ### MRTG Configuration — {$routerName} ({$routerIp})
            ### Generated: {$this->timestamp()}
            ### Router ID: {$routerId}

            WorkDir: {$webDir}
            HtmlDir: {$webDir}
            ImageDir: {$webDir}
            LogDir: {$webDir}

            LogFormat: rrdtool
            PathAdd: /usr/bin
            IconDir: /usr/share/mrtg/icons/

            Language: UTF-8
            Refresh: 300
            Interval: 5

            Options[_]: growright,bits
            EnableIPv6: no

            WriteExpires: yes
            NoMib: yes
            CFG;
    }

    // -------------------------------------------------------------------------
    // Database persistence
    // -------------------------------------------------------------------------

    /**
     * Upsert an MRTG config record in the `mrtg_configs` table.
     *
     * @param  int    $routerId  FK to `routers.id`.
     * @param  string $filename  Absolute path to the .cfg file.
     * @param  string $content   Full configuration content.
     * @param  string $status    'active', 'pending', or 'error'.
     */
    public function saveToDatabase(
        int    $routerId,
        string $filename,
        string $content,
        string $status = 'active',
    ): void {
        try {
            $existing = $this->db->fetch(
                'SELECT id FROM mrtg_configs WHERE filename = ?',
                [$filename],
            );

            $now = date('Y-m-d H:i:s');

            if ($existing !== null) {
                $this->db->update('mrtg_configs', [
                    'config_content' => $content,
                    'status'         => $status,
                    'generated_at'   => $now,
                ], 'id = ?', [(int) $existing['id']]);
            } else {
                $this->db->insert('mrtg_configs', [
                    'router_id'      => $routerId,
                    'target_type'    => 'router',
                    'target_id'      => null,
                    'config_content' => $content,
                    'filename'       => $filename,
                    'status'         => $status,
                    'generated_at'   => $now,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('MrtgConfigGenerator: failed to save config to DB', [
                'router_id' => $routerId,
                'filename'  => $filename,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create the MRTG web output directories for a router.
     *
     * Creates <MRTG_WEB_DIR>/<routerId>/ with 0755 permissions.
     *
     * @param  int $routerId
     * @throws \RuntimeException  If directory creation fails.
     */
    public function createDirectories(int $routerId): void
    {
        $webDir = self::MRTG_WEB_DIR . '/' . $routerId;

        if (!is_dir($webDir)) {
            if (!@mkdir($webDir, 0755, true)) {
                throw new \RuntimeException(
                    "Failed to create MRTG web directory: {$webDir}",
                );
            }
        }

        // Ensure the MRTG config directory also exists.
        if (!is_dir(self::MRTG_CONFIG_DIR)) {
            if (!@mkdir(self::MRTG_CONFIG_DIR, 0755, true)) {
                throw new \RuntimeException(
                    'Failed to create MRTG config directory: ' . self::MRTG_CONFIG_DIR,
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a unique, filesystem-safe MRTG target identifier.
     *
     * Format: <prefix>_r<routerId>_<index>
     *
     * @param  string     $prefix    Short type prefix ('if', 'q', 'ppp').
     * @param  int|string $routerId
     * @param  int|string $index
     * @return string
     */
    private function makeTargetId(string $prefix, int|string $routerId, int|string $index): string
    {
        return sprintf('%s_r%d_%d', $prefix, (int) $routerId, (int) $index);
    }

    /**
     * Escape a string for safe use inside MRTG config values.
     *
     * Strips characters that could break the config file syntax.
     *
     * @param  string $title
     * @return string
     */
    private function escapeTitle(string $title): string
    {
        // Replace < > & and double-quotes with safe equivalents.
        return htmlspecialchars(
            preg_replace('/[\x00-\x1F\x7F]/', '', $title) ?? $title,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }

    /**
     * Return current timestamp string for config file headers.
     */
    private function timestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Retrieve monitored interfaces for the given router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    private function getMonitoredInterfaces(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT id, router_id, if_index, name, alias, speed
               FROM interfaces
              WHERE router_id = ? AND monitored = 1
              ORDER BY if_index ASC',
            [$routerId],
        );
    }

    /**
     * Retrieve monitored Simple Queue entries for the given router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    private function getMonitoredQueues(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT id, router_id, queue_index, name, target, max_limit_upload, max_limit_download
               FROM simple_queues
              WHERE router_id = ? AND monitored = 1
              ORDER BY queue_index ASC',
            [$routerId],
        );
    }

    /**
     * Retrieve monitored PPPoE users for the given router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    private function getMonitoredPppoe(int $routerId): array
    {
        return $this->db->fetchAll(
            "SELECT id, router_id, name, service, remote_address
               FROM pppoe_users
              WHERE router_id = ? AND monitored = 1 AND status = 'connected'
              ORDER BY id ASC",
            [$routerId],
        );
    }
}
