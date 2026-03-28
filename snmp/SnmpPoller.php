<?php declare(strict_types=1);

namespace NOC\SNMP;

use NOC\Core\Database;
use NOC\Core\Logger;

/**
 * SnmpPoller — Polls SNMP traffic counters for monitored targets.
 *
 * Fetches raw SNMP counter values from routers, computes per-interval deltas
 * (handling 32-bit and 64-bit counter wraps), and persists the results to the
 * `traffic_data` table.
 *
 * Counter state between polls is persisted to a JSON state file so that the
 * poller can be invoked from a cron job without retaining in-memory state.
 * Default state file: <project_root>/logs/poll_state.json
 *
 * @package NOC\SNMP
 * @version 1.0.0
 */
final class SnmpPoller
{
    // IF-MIB 32-bit counters
    private const OID_IF_IN_OCTETS    = '.1.3.6.1.2.1.2.2.1.10';
    private const OID_IF_OUT_OCTETS   = '.1.3.6.1.2.1.2.2.1.16';

    // IF-MIB 64-bit HC counters (ifXTable)
    private const OID_IF_HC_IN_OCTETS  = '.1.3.6.1.2.1.31.1.1.1.6';
    private const OID_IF_HC_OUT_OCTETS = '.1.3.6.1.2.1.31.1.1.1.10';

    // MikroTik Simple Queue byte counters
    private const OID_QUEUE_BYTES_IN  = '.1.3.6.1.4.1.14988.1.1.2.1.1.8';
    private const OID_QUEUE_BYTES_OUT = '.1.3.6.1.4.1.14988.1.1.2.1.1.9';

    /** Speed threshold above which HC (64-bit) counters are preferred: 100 Mbps in bps */
    private const HC_SPEED_THRESHOLD = 100_000_000;

    /** Maximum reasonable bits/second per interface for counter-jump sanity checking (100 Gbps) */
    private const MAX_REASONABLE_BPS = 100_000_000_000;
    private const COUNTER32_MAX = 4_294_967_295;

    /** Maximum value of a 64-bit counter before wrap */
    private const COUNTER64_MAX = 18_446_744_073_709_551_615;

    /** Absolute path to the JSON state file */
    private readonly string $stateFile;

    /** In-memory copy of the poll state loaded from disk */
    private array $state = [];

    public function __construct(
        private readonly Database $db,
        private readonly Logger   $logger,
        ?string $stateFile = null,
    ) {
        $this->stateFile = $stateFile
            ?? dirname(__DIR__) . '/logs/poll_state.json';

        $this->loadState();
    }

    // -------------------------------------------------------------------------
    // Top-level poll orchestration
    // -------------------------------------------------------------------------

    /**
     * Poll all monitored targets for a specific router.
     *
     * Loads the router record, creates an SnmpManager, then polls all monitored
     * interfaces, queues, and PPPoE users in sequence.
     *
     * @param  int  $routerId  PK from the `routers` table.
     * @return bool            True if polling completed without fatal errors.
     */
    public function pollRouter(int $routerId): bool
    {
        $router = $this->db->fetch(
            'SELECT id, ip_address, snmp_community, snmp_version, snmp_port
               FROM routers
              WHERE id = ? AND status = ?',
            [$routerId, 'active'],
        );

        if ($router === null) {
            $this->logger->warning('SnmpPoller: router not found or inactive', ['router_id' => $routerId]);
            return false;
        }

        $snmp = new SnmpManager(
            $router['ip_address'],
            $router['snmp_community'],
            $router['snmp_version'],
            5_000_000,
            2,
            (int) $router['snmp_port'],
        );

        if (!$snmp->testConnection()) {
            $this->logger->error('SnmpPoller: device unreachable', [
                'router_id' => $routerId,
                'ip'        => $router['ip_address'],
            ]);
            $this->db->update('routers', ['status' => 'error'], 'id = ?', [$routerId]);
            return false;
        }

        $success = true;

        foreach ($this->getMonitoredInterfaces($routerId) as $iface) {
            $success = $this->pollInterface($iface, $snmp) && $success;
        }

        foreach ($this->getMonitoredQueues($routerId) as $queue) {
            $success = $this->pollQueue($queue, $snmp) && $success;
        }

        foreach ($this->getMonitoredPppoe($routerId) as $pppoe) {
            $success = $this->pollPppoe($pppoe, $snmp) && $success;
        }

        $this->persistState();
        return $success;
    }

    // -------------------------------------------------------------------------
    // Per-target pollers
    // -------------------------------------------------------------------------

    /**
     * Poll inbound and outbound octets for a single network interface.
     *
     * Uses HC (64-bit) counters for interfaces faster than 100 Mbps to avoid
     * counter-wrap issues.  Falls back to 32-bit counters if HC OIDs fail.
     *
     * @param  array<string,mixed> $interface  Row from the `interfaces` table.
     * @param  SnmpManager         $snmp
     * @return bool                            True on success.
     */
    public function pollInterface(array $interface, SnmpManager $snmp): bool
    {
        $ifIndex = (int) $interface['if_index'];
        $speed   = (int) ($interface['speed'] ?? 0);
        $id      = (int) $interface['id'];

        $useHc = $speed > self::HC_SPEED_THRESHOLD;

        if ($useHc) {
            $rawIn  = $snmp->get(self::OID_IF_HC_IN_OCTETS  . '.' . $ifIndex);
            $rawOut = $snmp->get(self::OID_IF_HC_OUT_OCTETS . '.' . $ifIndex);

            // Fall back to 32-bit on HC fetch failure.
            if ($rawIn === false || $rawOut === false) {
                $useHc  = false;
                $rawIn  = $snmp->get(self::OID_IF_IN_OCTETS  . '.' . $ifIndex);
                $rawOut = $snmp->get(self::OID_IF_OUT_OCTETS . '.' . $ifIndex);
            }
        } else {
            $rawIn  = $snmp->get(self::OID_IF_IN_OCTETS  . '.' . $ifIndex);
            $rawOut = $snmp->get(self::OID_IF_OUT_OCTETS . '.' . $ifIndex);
        }

        if ($rawIn === false || $rawOut === false) {
            $this->logger->warning('SnmpPoller: failed to read interface counters', [
                'interface_id' => $id,
                'if_index'     => $ifIndex,
            ]);
            return false;
        }

        $currentIn  = (int) $rawIn;
        $currentOut = (int) $rawOut;
        $now        = time();
        $stateKey   = 'iface_' . $id;
        $maxCounter = $useHc ? self::COUNTER64_MAX : self::COUNTER32_MAX;

        [$deltaIn, $deltaOut] = $this->computeDeltas(
            $stateKey,
            $currentIn,
            $currentOut,
            $now,
            $maxCounter,
        );

        if ($deltaIn === null || $deltaOut === null) {
            return true; // First poll — nothing to save yet.
        }

        return $this->saveTrafficData('interface', $id, $deltaIn, $deltaOut);
    }

    /**
     * Poll byte counters for a MikroTik Simple Queue entry.
     *
     * @param  array<string,mixed> $queue  Row from `simple_queues`.
     * @param  SnmpManager         $snmp
     * @return bool
     */
    public function pollQueue(array $queue, SnmpManager $snmp): bool
    {
        $queueIndex = (int) $queue['queue_index'];
        $id         = (int) $queue['id'];

        $rawIn  = $snmp->get(self::OID_QUEUE_BYTES_IN  . '.' . $queueIndex);
        $rawOut = $snmp->get(self::OID_QUEUE_BYTES_OUT . '.' . $queueIndex);

        if ($rawIn === false || $rawOut === false) {
            $this->logger->warning('SnmpPoller: failed to read queue counters', [
                'queue_id'    => $id,
                'queue_index' => $queueIndex,
            ]);
            return false;
        }

        $currentIn  = (int) $rawIn;
        $currentOut = (int) $rawOut;
        $now        = time();
        $stateKey   = 'queue_' . $id;

        [$deltaIn, $deltaOut] = $this->computeDeltas(
            $stateKey,
            $currentIn,
            $currentOut,
            $now,
            self::COUNTER64_MAX,
        );

        if ($deltaIn === null || $deltaOut === null) {
            return true;
        }

        return $this->saveTrafficData('queue', $id, $deltaIn, $deltaOut);
    }

    /**
     * Poll traffic counters for an active PPPoE session.
     *
     * MikroTik exposes per-session byte counters through the same queue MIB
     * entries that back the PPPoE interfaces.  This method looks up the
     * corresponding interface by the session's remote_address so we can read
     * its HC octets from IF-MIB.
     *
     * @param  array<string,mixed> $pppoe  Row from `pppoe_users`.
     * @param  SnmpManager         $snmp
     * @return bool
     */
    public function pollPppoe(array $pppoe, SnmpManager $snmp): bool
    {
        $id      = (int) $pppoe['id'];
        $pppoeIp = $pppoe['remote_address'] ?? '';

        if ($pppoeIp === '') {
            return false;
        }

        // Attempt to find the dynamic PPPoE interface via ifDescr matching.
        $ifTable = $snmp->getIfTable();
        if ($ifTable === false) {
            return false;
        }

        $ifIndex = null;
        foreach ($ifTable as $idx => $iface) {
            $descr = strtolower((string) ($iface['description'] ?? ''));
            $name  = strtolower((string) ($iface['name']        ?? ''));
            // MikroTik names PPPoE sub-interfaces like "<ppp-out1>" or uses the
            // username as alias. Match on alias containing the username.
            $username = strtolower($pppoe['name'] ?? '');
            $alias    = strtolower((string) ($iface['alias'] ?? ''));

            if ($username !== '' && (str_contains($alias, $username) || str_contains($name, $username))) {
                $ifIndex = $idx;
                break;
            }
        }

        if ($ifIndex === null) {
            $this->logger->debug('SnmpPoller: PPPoE interface not found via ifTable', [
                'pppoe_id' => $id,
                'username' => $pppoe['name'],
            ]);
            return false;
        }

        $rawIn  = $snmp->get(self::OID_IF_HC_IN_OCTETS  . '.' . $ifIndex);
        $rawOut = $snmp->get(self::OID_IF_HC_OUT_OCTETS . '.' . $ifIndex);

        if ($rawIn === false || $rawOut === false) {
            $rawIn  = $snmp->get(self::OID_IF_IN_OCTETS  . '.' . $ifIndex);
            $rawOut = $snmp->get(self::OID_IF_OUT_OCTETS . '.' . $ifIndex);
        }

        if ($rawIn === false || $rawOut === false) {
            $this->logger->warning('SnmpPoller: failed to read PPPoE counters', [
                'pppoe_id' => $id,
                'if_index' => $ifIndex,
            ]);
            return false;
        }

        $currentIn  = (int) $rawIn;
        $currentOut = (int) $rawOut;
        $now        = time();
        $stateKey   = 'pppoe_' . $id;

        [$deltaIn, $deltaOut] = $this->computeDeltas(
            $stateKey,
            $currentIn,
            $currentOut,
            $now,
            self::COUNTER64_MAX,
        );

        if ($deltaIn === null || $deltaOut === null) {
            return true;
        }

        return $this->saveTrafficData('pppoe', $id, $deltaIn, $deltaOut);
    }

    // -------------------------------------------------------------------------
    // Database persistence
    // -------------------------------------------------------------------------

    /**
     * Persist a traffic delta sample to the `traffic_data` table.
     *
     * @param  string $targetType  One of 'interface', 'queue', 'pppoe'.
     * @param  int    $targetId    PK of the associated target record.
     * @param  int    $bytesIn     Bytes received during this interval.
     * @param  int    $bytesOut    Bytes transmitted during this interval.
     * @return bool
     */
    public function saveTrafficData(
        string $targetType,
        int    $targetId,
        int    $bytesIn,
        int    $bytesOut,
    ): bool {
        try {
            $this->db->insert('traffic_data', [
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'bytes_in'    => $bytesIn,
                'bytes_out'   => $bytesOut,
                'timestamp'   => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('SnmpPoller: failed to save traffic data', [
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Rate calculation
    // -------------------------------------------------------------------------

    /**
     * Calculate a bytes-per-second rate from two counter samples.
     *
     * @param  int   $current   Current counter value (bytes).
     * @param  int   $previous  Previous counter value (bytes).
     * @param  int   $interval  Elapsed seconds between samples.
     * @return float            Rate in bytes/second; 0.0 on invalid input.
     */
    public function calculateRate(int $current, int $previous, int $interval): float
    {
        if ($interval <= 0 || $current < 0 || $previous < 0) {
            return 0.0;
        }

        $delta = $current - $previous;

        if ($delta < 0) {
            return 0.0;
        }

        return $delta / $interval;
    }

    // -------------------------------------------------------------------------
    // Database query helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve all active routers eligible for polling.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAllRouters(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, ip_address, snmp_community, snmp_version, snmp_port
               FROM routers
              WHERE status = 'active'
              ORDER BY id ASC",
        );
    }

    /**
     * Retrieve all monitored interfaces for a given router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function getMonitoredInterfaces(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT id, router_id, if_index, name, speed
               FROM interfaces
              WHERE router_id = ? AND monitored = 1
              ORDER BY if_index ASC',
            [$routerId],
        );
    }

    /**
     * Retrieve all monitored Simple Queue entries for a given router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function getMonitoredQueues(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT id, router_id, queue_index, name
               FROM simple_queues
              WHERE router_id = ? AND monitored = 1
              ORDER BY queue_index ASC',
            [$routerId],
        );
    }

    /**
     * Retrieve all monitored PPPoE sessions for a given router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function getMonitoredPppoe(int $routerId): array
    {
        return $this->db->fetchAll(
            "SELECT id, router_id, name, remote_address
               FROM pppoe_users
              WHERE router_id = ? AND monitored = 1 AND status = 'connected'
              ORDER BY id ASC",
            [$routerId],
        );
    }

    // -------------------------------------------------------------------------
    // Counter-delta computation with wrap detection
    // -------------------------------------------------------------------------

    /**
     * Compute inbound and outbound deltas, handling counter wraps and storing
     * the new sample in the in-memory state (persisted later by persistState()).
     *
     * Returns [null, null] on the first sample for a given key (no prior
     * reference point), and [deltaIn, deltaOut] on subsequent polls.
     *
     * @param  string $stateKey    Unique state key for this target counter pair.
     * @param  int    $currentIn   Current raw in-counter value.
     * @param  int    $currentOut  Current raw out-counter value.
     * @param  int    $now         Current Unix timestamp.
     * @param  int    $maxCounter  Maximum counter value (COUNTER32_MAX or COUNTER64_MAX).
     * @return array{int|null,int|null}  [deltaIn, deltaOut].
     */
    private function computeDeltas(
        string $stateKey,
        int    $currentIn,
        int    $currentOut,
        int    $now,
        int    $maxCounter,
    ): array {
        $prev = $this->state[$stateKey] ?? null;

        // Always update state with current values.
        $this->state[$stateKey] = [
            'in'  => $currentIn,
            'out' => $currentOut,
            'ts'  => $now,
        ];

        if ($prev === null) {
            return [null, null];
        }

        $interval = $now - (int) $prev['ts'];
        if ($interval <= 0) {
            return [null, null];
        }

        $prevIn  = (int) $prev['in'];
        $prevOut = (int) $prev['out'];

        // Detect and compensate for counter wrap.
        $deltaIn  = $currentIn  >= $prevIn
            ? $currentIn  - $prevIn
            : ($maxCounter - $prevIn) + $currentIn + 1;

        $deltaOut = $currentOut >= $prevOut
            ? $currentOut - $prevOut
            : ($maxCounter - $prevOut) + $currentOut + 1;

        // Sanity: reject implausibly large deltas (e.g. device reboot reset counters).
        // Allow up to MAX_REASONABLE_BPS bits/s worth of bytes over the interval.
        $maxReasonableBytes = (int) (self::MAX_REASONABLE_BPS / 8 * $interval);
        if ($deltaIn > $maxReasonableBytes || $deltaOut > $maxReasonableBytes) {
            $this->logger->warning('SnmpPoller: counter jump detected, discarding sample', [
                'state_key' => $stateKey,
                'delta_in'  => $deltaIn,
                'delta_out' => $deltaOut,
                'interval'  => $interval,
            ]);
            return [null, null];
        }

        return [$deltaIn, $deltaOut];
    }

    // -------------------------------------------------------------------------
    // State persistence (JSON file)
    // -------------------------------------------------------------------------

    /**
     * Load the poll state from the JSON state file into $this->state.
     * Silently initialises to an empty array if the file does not exist.
     */
    private function loadState(): void
    {
        if (!is_file($this->stateFile)) {
            $this->state = [];
            return;
        }

        $raw = @file_get_contents($this->stateFile);
        if ($raw === false) {
            $this->state = [];
            return;
        }

        $decoded = json_decode($raw, true);
        $this->state = is_array($decoded) ? $decoded : [];
    }

    /**
     * Write the current in-memory poll state back to the JSON state file.
     * Creates parent directories if they do not exist.
     */
    private function persistState(): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        $json = json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->logger->error('SnmpPoller: failed to encode state JSON');
            return;
        }

        $handle = @fopen($this->stateFile, 'wb');
        if ($handle === false) {
            $this->logger->error('SnmpPoller: cannot open state file for writing', [
                'file' => $this->stateFile,
            ]);
            return;
        }

        flock($handle, LOCK_EX);
        fwrite($handle, $json);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
