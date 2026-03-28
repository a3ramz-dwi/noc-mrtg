<?php declare(strict_types=1);

namespace NOC\SNMP;

/**
 * SnmpManager — Complete SNMP operations wrapper.
 *
 * Provides a clean abstraction over PHP's procedural SNMP functions
 * (snmp2_get, snmp2_walk, snmp2_getnext, snmp2_set) for polling
 * MikroTik and other SNMP-capable devices.
 *
 * Global SNMP output settings are applied once per constructor call:
 *   - SNMP_VALUE_PLAIN   → strip type prefix from returned values
 *   - SNMP_OID_OUTPUT_NUMERIC → return numeric OID strings
 *
 * @package NOC\SNMP
 * @version 1.0.0
 */
final class SnmpManager
{
    /** Standard IF-MIB OID bases */
    private const OID_SYS_DESCR    = '.1.3.6.1.2.1.1.1.0';
    private const OID_SYS_NAME     = '.1.3.6.1.2.1.1.5.0';
    private const OID_SYS_UPTIME   = '.1.3.6.1.2.1.1.3.0';
    private const OID_SYS_LOCATION = '.1.3.6.1.2.1.1.6.0';

    private const OID_IF_DESCR       = '.1.3.6.1.2.1.2.2.1.2';
    private const OID_IF_TYPE        = '.1.3.6.1.2.1.2.2.1.3';
    private const OID_IF_MTU         = '.1.3.6.1.2.1.2.2.1.4';
    private const OID_IF_SPEED       = '.1.3.6.1.2.1.2.2.1.5';
    private const OID_IF_PHYS_ADDR   = '.1.3.6.1.2.1.2.2.1.6';
    private const OID_IF_ADMIN_STATUS = '.1.3.6.1.2.1.2.2.1.7';
    private const OID_IF_OPER_STATUS  = '.1.3.6.1.2.1.2.2.1.8';
    private const OID_IF_NAME        = '.1.3.6.1.2.1.31.1.1.1.1';
    private const OID_IF_ALIAS       = '.1.3.6.1.2.1.31.1.1.1.18';

    /** SNMP connection parameters */
    private readonly string $host;
    private readonly string $community;
    private readonly string $version;
    private readonly int    $timeout;
    private readonly int    $retries;
    private readonly int    $port;

    /**
     * @param string $ip        Target device IP address.
     * @param string $community SNMP community string.
     * @param string $version   SNMP version: '1', '2c'.
     * @param int    $timeout   Timeout in microseconds (default 5 s).
     * @param int    $retries   Number of retries on timeout.
     * @param int    $port      UDP port (default 161).
     */
    public function __construct(
        string $ip,
        string $community = 'public',
        string $version   = '2c',
        int    $timeout   = 5_000_000,
        int    $retries   = 2,
        int    $port      = 161,
    ) {
        $this->community = $community;
        $this->version   = $version;
        $this->timeout   = $timeout;
        $this->retries   = $retries;
        $this->port      = $port;

        // Embed non-standard port into host string for SNMP functions.
        $this->host = ($port !== 161)
            ? sprintf('%s:%d', $ip, $port)
            : $ip;

        snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        snmp_set_quick_print(true);
    }

    // -------------------------------------------------------------------------
    // Core SNMP operations
    // -------------------------------------------------------------------------

    /**
     * Perform an SNMP GET for a single OID.
     *
     * @param  string      $oid  Numeric or textual OID.
     * @return string|false      Scalar value string, or false on error.
     */
    public function get(string $oid): string|false
    {
        try {
            $result = match ($this->version) {
                '1'     => snmpget($this->host, $this->community, $oid, $this->timeout, $this->retries),
                default => snmp2_get($this->host, $this->community, $oid, $this->timeout, $this->retries),
            };

            if ($result === false) {
                return false;
            }

            return $this->parseValue((string) $result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform an SNMP WALK starting at the given OID.
     *
     * @param  string       $oid  Base OID to walk.
     * @return array<string,string>|false  Associative array of OID => value, or false on error.
     */
    public function walk(string $oid): array|false
    {
        try {
            $result = match ($this->version) {
                '1'     => snmpwalk($this->host, $this->community, $oid, $this->timeout, $this->retries),
                default => snmp2_walk($this->host, $this->community, $oid, $this->timeout, $this->retries),
            };

            if ($result === false || !is_array($result)) {
                return false;
            }

            // snmp2_walk returns a flat array; re-walk with real_walk to get OID keys.
            $realResult = match ($this->version) {
                '1'     => snmprealwalk($this->host, $this->community, $oid, $this->timeout, $this->retries),
                default => snmp2_real_walk($this->host, $this->community, $oid, $this->timeout, $this->retries),
            };

            if ($realResult === false || !is_array($realResult)) {
                return false;
            }

            $parsed = [];
            foreach ($realResult as $walkOid => $value) {
                $parsed[$walkOid] = $this->parseValue((string) $value);
            }

            return $parsed;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform an SNMP GETNEXT for the OID immediately following the given one.
     *
     * @param  string      $oid
     * @return string|false  Value string or false on error.
     */
    public function getNext(string $oid): string|false
    {
        try {
            $result = match ($this->version) {
                '1'     => snmpgetnext($this->host, $this->community, $oid, $this->timeout, $this->retries),
                default => snmp2_getnext($this->host, $this->community, $oid, $this->timeout, $this->retries),
            };

            if ($result === false) {
                return false;
            }

            return $this->parseValue((string) $result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Perform an SNMP SET to write a value to the device.
     *
     * @param  string $oid    Target OID.
     * @param  string $type   SNMP type character: i=INTEGER, s=STRING, x=HEX-STRING,
     *                        d=DECIMAL, n=NULL, o=OID, t=TIMETICKS, a=IPADDRESS, b=BITS.
     * @param  string $value  Value to write.
     * @return bool           True on success, false on failure.
     */
    public function set(string $oid, string $type, string $value): bool
    {
        try {
            $result = match ($this->version) {
                '1'     => snmpset($this->host, $this->community, $oid, $type, $value, $this->timeout, $this->retries),
                default => snmp2_set($this->host, $this->community, $oid, $type, $value, $this->timeout, $this->retries),
            };

            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Connectivity test
    // -------------------------------------------------------------------------

    /**
     * Test whether the device is reachable and responding to SNMP.
     *
     * Performs a GET on sysDescr.0; any valid response means the device
     * is up and the community string is correct.
     *
     * @return bool True if device responded, false otherwise.
     */
    public function testConnection(): bool
    {
        $result = $this->get(self::OID_SYS_DESCR);
        return $result !== false && $result !== '';
    }

    // -------------------------------------------------------------------------
    // Value parsing
    // -------------------------------------------------------------------------

    /**
     * Parse a raw SNMP value string and strip any type prefix.
     *
     * With SNMP_VALUE_PLAIN set, PHP should already strip prefixes, but
     * some builds still return strings like "STRING: foo" or "INTEGER: 6".
     * This method normalises those cases.
     *
     * @param  string $rawValue  Raw value as returned by a SNMP function.
     * @return string            Clean scalar value.
     */
    public function parseValue(string $rawValue): string
    {
        // Remove known type prefixes left by some PHP SNMP builds.
        $prefixes = [
            'STRING: ', 'INTEGER: ', 'Gauge32: ', 'Counter32: ', 'Counter64: ',
            'Timeticks: ', 'IpAddress: ', 'OID: ', 'Hex-STRING: ', 'BITS: ',
            'NULL', 'Network Address: ', 'Opaque: ',
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($rawValue, $prefix)) {
                $rawValue = substr($rawValue, strlen($prefix));
                break;
            }
        }

        // Strip surrounding double-quotes that some builds add for strings.
        $rawValue = trim($rawValue, '"');

        // Collapse timeticks parenthetical — e.g. "(12345) 0:02:03.45" → "12345"
        if (preg_match('/^\((\d+)\)/', $rawValue, $m)) {
            $rawValue = $m[1];
        }

        return trim($rawValue);
    }

    /**
     * Parse a raw octet-string value into a colon-delimited hex MAC address.
     *
     * SNMP_VALUE_PLAIN returns MAC addresses as a space-separated hex string,
     * e.g. "0 c 29 3e a1 b2".  This method normalises that to "00:0C:29:3E:A1:B2".
     *
     * @param  string $value  Raw octet string from SNMP.
     * @return string         Formatted MAC address, or empty string if not parseable.
     */
    public function parseOctets(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '0:0:0:0:0:0') {
            return '';
        }

        // Handle "Hex-STRING: 00 0C 29 3E A1 B2" format.
        if (preg_match('/^([0-9a-fA-F]{1,2}[\s:]+){5}[0-9a-fA-F]{1,2}$/', $value)) {
            $parts = preg_split('/[\s:]+/', $value);
            if ($parts !== false && count($parts) === 6) {
                return implode(':', array_map(
                    static fn (string $p) => strtoupper(str_pad($p, 2, '0', STR_PAD_LEFT)),
                    $parts,
                ));
            }
        }

        return '';
    }

    // -------------------------------------------------------------------------
    // High-level IF-MIB helpers
    // -------------------------------------------------------------------------

    /**
     * Retrieve the full interface table (IF-MIB + ifXTable) as an indexed array.
     *
     * Each element is a keyed array with fields: if_index, name, alias, type,
     * mtu, speed, mac_address, admin_status, oper_status.
     *
     * @return array<int,array<string,mixed>>|false  Array indexed by ifIndex, or false on failure.
     */
    public function getIfTable(): array|false
    {
        // Walk each required column and collect by index.
        $walks = [
            'name'         => self::OID_IF_NAME,
            'description'  => self::OID_IF_DESCR,
            'alias'        => self::OID_IF_ALIAS,
            'type'         => self::OID_IF_TYPE,
            'mtu'          => self::OID_IF_MTU,
            'speed'        => self::OID_IF_SPEED,
            'mac_raw'      => self::OID_IF_PHYS_ADDR,
            'admin_status' => self::OID_IF_ADMIN_STATUS,
            'oper_status'  => self::OID_IF_OPER_STATUS,
        ];

        $columns = [];
        foreach ($walks as $field => $baseOid) {
            $data = $this->walk($baseOid);
            if ($data === false) {
                // Non-fatal: some devices lack ifXTable entries (name/alias).
                if (in_array($field, ['name', 'alias'], true)) {
                    $columns[$field] = [];
                    continue;
                }
                return false;
            }
            $columns[$field] = $data;
        }

        // Index all columns by the numeric suffix (ifIndex).
        $interfaces = [];
        foreach ($columns['description'] as $oid => $descr) {
            $index = $this->extractIndex($oid);
            if ($index === null) {
                continue;
            }

            $macRaw = $this->findByIndex($columns['mac_raw'], $index);
            $name   = $this->findByIndex($columns['name'],    $index) ?: $descr;
            $alias  = $this->findByIndex($columns['alias'],   $index);

            $interfaces[$index] = [
                'if_index'     => $index,
                'name'         => $name,
                'alias'        => $alias !== false ? $alias : null,
                'description'  => $descr,
                'type'         => (int) ($this->findByIndex($columns['type'],         $index) ?: 0),
                'mtu'          => (int) ($this->findByIndex($columns['mtu'],          $index) ?: 0),
                'speed'        => (int) ($this->findByIndex($columns['speed'],        $index) ?: 0),
                'mac_address'  => $macRaw !== false ? $this->parseOctets($macRaw) : null,
                'admin_status' => (int) ($this->findByIndex($columns['admin_status'], $index) ?: 0),
                'oper_status'  => (int) ($this->findByIndex($columns['oper_status'],  $index) ?: 0),
            ];
        }

        return $interfaces;
    }

    /**
     * Retrieve core system information OIDs.
     *
     * @return array{sysDescr:string,sysName:string,sysUpTime:string,sysLocation:string}|false
     *         Associative array of system info, or false on SNMP failure.
     */
    public function getSystemInfo(): array|false
    {
        $oids = [
            'sysDescr'    => self::OID_SYS_DESCR,
            'sysName'     => self::OID_SYS_NAME,
            'sysUpTime'   => self::OID_SYS_UPTIME,
            'sysLocation' => self::OID_SYS_LOCATION,
        ];

        $info = [];
        foreach ($oids as $key => $oid) {
            $value = $this->get($oid);
            if ($value === false) {
                return false;
            }
            $info[$key] = $value;
        }

        /** @var array{sysDescr:string,sysName:string,sysUpTime:string,sysLocation:string} $info */
        return $info;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a fully-qualified OID path by appending an index to a base OID.
     *
     * @param  string     $baseOid  Base OID (e.g. ".1.3.6.1.2.1.2.2.1.10").
     * @param  int|string $index    Leaf index to append.
     * @return string               Full OID (e.g. ".1.3.6.1.2.1.2.2.1.10.3").
     */
    private function buildOidPath(string $baseOid, int|string $index): string
    {
        return rtrim($baseOid, '.') . '.' . $index;
    }

    /**
     * Extract the final numeric index from a dotted OID string.
     *
     * @param  string   $oid  Full numeric OID.
     * @return int|null       Leaf integer, or null if not parseable.
     */
    private function extractIndex(string $oid): ?int
    {
        if (preg_match('/\.(\d+)$/', $oid, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Find a value in a walk result array by its leaf index.
     *
     * @param  array<string,string> $walkData  OID-keyed walk result.
     * @param  int                  $index     Leaf index to look up.
     * @return string|false                    Found value or false.
     */
    private function findByIndex(array $walkData, int $index): string|false
    {
        foreach ($walkData as $oid => $value) {
            if ($this->extractIndex($oid) === $index) {
                return $value;
            }
        }
        return false;
    }
}
