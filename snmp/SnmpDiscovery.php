<?php declare(strict_types=1);

namespace NOC\SNMP;

/**
 * SnmpDiscovery — Auto-discovery of interfaces, queues, and PPPoE sessions.
 *
 * Uses an SnmpManager instance to walk MikroTik-specific and standard
 * IF-MIB OIDs, returning normalised arrays suitable for database upsert.
 *
 * @package NOC\SNMP
 * @version 1.0.0
 */
final class SnmpDiscovery
{
    // -------------------------------------------------------------------------
    // MikroTik Simple Queue OIDs (MIKROTIK-MIB)
    // Base: .1.3.6.1.4.1.14988.1.1.2.1
    // -------------------------------------------------------------------------
    private const OID_QUEUE_BASE        = '.1.3.6.1.4.1.14988.1.1.2.1';
    private const OID_QUEUE_NAME        = '.1.3.6.1.4.1.14988.1.1.2.1.1.2';
    private const OID_QUEUE_SRC_ADDR    = '.1.3.6.1.4.1.14988.1.1.2.1.1.3';
    private const OID_QUEUE_BYTES_IN    = '.1.3.6.1.4.1.14988.1.1.2.1.1.8';
    private const OID_QUEUE_BYTES_OUT   = '.1.3.6.1.4.1.14988.1.1.2.1.1.9';
    private const OID_QUEUE_RATE_IN     = '.1.3.6.1.4.1.14988.1.1.2.1.1.16';
    private const OID_QUEUE_RATE_OUT    = '.1.3.6.1.4.1.14988.1.1.2.1.1.17';

    // -------------------------------------------------------------------------
    // MikroTik PPPoE Session OIDs (MIKROTIK-MIB)
    // Base: .1.3.6.1.4.1.14988.1.1.2.2.1
    // -------------------------------------------------------------------------
    private const OID_PPPOE_NAME        = '.1.3.6.1.4.1.14988.1.1.2.2.1.2';
    private const OID_PPPOE_ADDRESS     = '.1.3.6.1.4.1.14988.1.1.2.2.1.3';
    private const OID_PPPOE_UPTIME      = '.1.3.6.1.4.1.14988.1.1.2.2.1.4';
    private const OID_PPPOE_SERVICE     = '.1.3.6.1.4.1.14988.1.1.2.2.1.5';
    private const OID_PPPOE_CALLER_ID   = '.1.3.6.1.4.1.14988.1.1.2.2.1.6';

    /** @var array<int,string> IANAifType integer to string mappings */
    private const INTERFACE_TYPES = [
        1   => 'other',
        6   => 'ethernetCsmacd',
        23  => 'ppp',
        24  => 'softwareLoopback',
        53  => 'propVirtual',
        131 => 'tunnel',
        161 => 'ieee8023adLag',
        166 => 'mpls',
        188 => 'pppMultilinkBundle',
    ];

    public function __construct(
        private readonly SnmpManager $snmp,
    ) {}

    // -------------------------------------------------------------------------
    // Interface discovery
    // -------------------------------------------------------------------------

    /**
     * Discover all network interfaces via SNMP IF-MIB walk.
     *
     * Returns an array of interface data arrays, each containing:
     *   - if_index (int)
     *   - name (string) — from ifName / ifDescr
     *   - alias (string|null) — from ifAlias
     *   - type (string) — human-readable IANAifType string
     *   - mtu (int)
     *   - speed (int) — bits/s
     *   - mac_address (string|null) — colon-delimited hex
     *   - admin_status (string) — 'up' or 'down'
     *   - oper_status (string) — 'up', 'down', or 'lowerLayerDown'
     *
     * @return array<int,array<string,mixed>>|false  Indexed by ifIndex, or false on failure.
     */
    public function discoverInterfaces(): array|false
    {
        $ifTable = $this->snmp->getIfTable();

        if ($ifTable === false) {
            return false;
        }

        $interfaces = [];
        foreach ($ifTable as $index => $raw) {
            $interfaces[$index] = [
                'if_index'     => $raw['if_index'],
                'name'         => $raw['name'],
                'alias'        => $raw['alias'],
                'description'  => $raw['description'] ?? $raw['name'],
                'type'         => $this->parseInterfaceType($raw['type']),
                'mtu'          => $raw['mtu'],
                'speed'        => $raw['speed'],
                'mac_address'  => $raw['mac_address'],
                'admin_status' => $this->parseAdminStatus($raw['admin_status']),
                'oper_status'  => $this->parseOperStatus($raw['oper_status']),
            ];
        }

        return $interfaces;
    }

    // -------------------------------------------------------------------------
    // MikroTik Simple Queue discovery
    // -------------------------------------------------------------------------

    /**
     * Discover MikroTik Simple Queues via enterprise OIDs.
     *
     * Returns an array of queue data, each containing:
     *   - queue_index (int)
     *   - name (string)
     *   - target (string|null) — source address / target subnet
     *   - max_limit_upload (int) — bits/s from OID …1.16
     *   - max_limit_download (int) — bits/s from OID …1.17
     *
     * @return array<int,array<string,mixed>>|false  Indexed by queue entry order, or false on failure.
     */
    public function discoverQueues(): array|false
    {
        $names = $this->snmp->walk(self::OID_QUEUE_NAME);
        if ($names === false) {
            return false;
        }

        $srcAddrs  = $this->snmp->walk(self::OID_QUEUE_SRC_ADDR)  ?: [];
        $ratesIn   = $this->snmp->walk(self::OID_QUEUE_RATE_IN)   ?: [];
        $ratesOut  = $this->snmp->walk(self::OID_QUEUE_RATE_OUT)  ?: [];

        $queues = [];
        foreach ($names as $oid => $name) {
            $index = $this->extractIndex($oid);
            if ($index === null) {
                continue;
            }

            $target      = $this->findValueByIndex($srcAddrs,  $index);
            $limitUpload = $this->findValueByIndex($ratesIn,   $index);
            $limitDl     = $this->findValueByIndex($ratesOut,  $index);

            $queues[] = [
                'queue_index'        => $index,
                'name'               => $name,
                'target'             => $target !== false ? $target : null,
                'max_limit_upload'   => $limitUpload !== false ? (int) $limitUpload : 0,
                'max_limit_download' => $limitDl     !== false ? (int) $limitDl     : 0,
            ];
        }

        return $queues;
    }

    // -------------------------------------------------------------------------
    // MikroTik PPPoE session discovery
    // -------------------------------------------------------------------------

    /**
     * Discover active MikroTik PPPoE sessions via enterprise OIDs.
     *
     * Returns an array of session data, each containing:
     *   - pppoe_index (int)
     *   - name (string) — PPPoE username
     *   - service (string|null)
     *   - caller_id (string|null) — MAC address of CPE
     *   - remote_address (string|null) — assigned IP
     *   - uptime (int) — session uptime in seconds
     *
     * @return array<int,array<string,mixed>>|false  Or false on SNMP failure.
     */
    public function discoverPppoe(): array|false
    {
        $names = $this->snmp->walk(self::OID_PPPOE_NAME);
        if ($names === false) {
            // Device may not support MikroTik PPPoE MIB — return empty rather
            // than false so callers can distinguish "no sessions" from errors.
            return [];
        }

        $addresses  = $this->snmp->walk(self::OID_PPPOE_ADDRESS)   ?: [];
        $uptimes    = $this->snmp->walk(self::OID_PPPOE_UPTIME)    ?: [];
        $services   = $this->snmp->walk(self::OID_PPPOE_SERVICE)   ?: [];
        $callerIds  = $this->snmp->walk(self::OID_PPPOE_CALLER_ID) ?: [];

        $sessions = [];
        foreach ($names as $oid => $name) {
            $index = $this->extractIndex($oid);
            if ($index === null) {
                continue;
            }

            $address  = $this->findValueByIndex($addresses, $index);
            $uptime   = $this->findValueByIndex($uptimes,   $index);
            $service  = $this->findValueByIndex($services,  $index);
            $callerId = $this->findValueByIndex($callerIds, $index);

            // sysUpTime timeticks are in hundredths of a second; PPPoE uptime
            // is stored as timeticks too — convert to whole seconds.
            $uptimeSeconds = 0;
            if ($uptime !== false && is_numeric($uptime)) {
                $uptimeSeconds = (int) ((int) $uptime / 100);
            }

            $sessions[] = [
                'pppoe_index'    => $index,
                'name'           => $name,
                'service'        => $service  !== false ? $service  : null,
                'caller_id'      => $callerId !== false ? $this->cleanMac($callerId) : null,
                'remote_address' => $address  !== false ? $address  : null,
                'uptime'         => $uptimeSeconds,
            ];
        }

        return $sessions;
    }

    // -------------------------------------------------------------------------
    // Value parsers
    // -------------------------------------------------------------------------

    /**
     * Convert an IANAifType integer to a human-readable type string.
     *
     * @param  int    $typeId  ifType integer.
     * @return string          Known type name or "unknown({typeId})".
     */
    public function parseInterfaceType(int $typeId): string
    {
        return self::INTERFACE_TYPES[$typeId] ?? sprintf('unknown(%d)', $typeId);
    }

    /**
     * Convert an ifAdminStatus integer to a status string.
     *
     * @param  int    $status  1=up, 2=down, 3=testing.
     * @return string          'up', 'down', or 'testing'.
     */
    public function parseAdminStatus(int $status): string
    {
        return match ($status) {
            1       => 'up',
            2       => 'down',
            3       => 'testing',
            default => 'unknown',
        };
    }

    /**
     * Convert an ifOperStatus integer to a status string.
     *
     * @param  int    $status  1=up, 2=down, 3=testing, 4=unknown, 5=dormant, 7=lowerLayerDown.
     * @return string
     */
    public function parseOperStatus(int $status): string
    {
        return match ($status) {
            1       => 'up',
            2       => 'down',
            3       => 'testing',
            4       => 'unknown',
            5       => 'dormant',
            6       => 'notPresent',
            7       => 'lowerLayerDown',
            default => 'unknown',
        };
    }

    /**
     * Clean and normalise a raw MAC address string.
     *
     * Accepts colon, hyphen, or space-delimited hex octets and returns
     * an uppercase colon-delimited MAC, e.g. "00:0C:29:3E:A1:B2".
     * Returns empty string for null/invalid inputs.
     *
     * @param  string $rawMac  Raw MAC string from SNMP.
     * @return string          Formatted MAC or empty string.
     */
    public function cleanMac(string $rawMac): string
    {
        $rawMac = trim($rawMac);

        if ($rawMac === '') {
            return '';
        }

        // Split on common MAC delimiters: colon, hyphen, or whitespace.
        $parts = preg_split('/[:\-\s]+/', $rawMac);

        if ($parts === false || count($parts) !== 6) {
            return '';
        }

        foreach ($parts as $part) {
            if (!ctype_xdigit($part) || strlen($part) > 2) {
                return '';
            }
        }

        return implode(':', array_map(
            static fn (string $p) => strtoupper(str_pad($p, 2, '0', STR_PAD_LEFT)),
            $parts,
        ));
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the last numeric component from a dotted OID string.
     *
     * @param  string   $oid
     * @return int|null
     */
    private function extractIndex(string $oid): ?int
    {
        if (preg_match('/\.(\d+)$/', $oid, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Find a value in a walk result by its leaf OID index.
     *
     * @param  array<string,string> $walkData
     * @param  int                  $index
     * @return string|false
     */
    private function findValueByIndex(array $walkData, int $index): string|false
    {
        foreach ($walkData as $oid => $value) {
            if ($this->extractIndex($oid) === $index) {
                return $value;
            }
        }
        return false;
    }
}
