<?php declare(strict_types=1);

/**
 * SNMP Configuration
 *
 * Returns an array of SNMP settings and OID maps used throughout the
 * NOC MRTG Manager.  Constants must be defined before loading this file
 * (see config/app.php).
 *
 * @package NOC\Config
 * @version 1.0.0
 */

return [

    // -----------------------------------------------------------------------
    // Connection defaults
    // -----------------------------------------------------------------------
    'default_version'   => defined('SNMP_VERSION')   ? SNMP_VERSION   : '2c',
    'default_community' => defined('SNMP_COMMUNITY') ? SNMP_COMMUNITY : 'public',
    'timeout'           => defined('SNMP_TIMEOUT')   ? SNMP_TIMEOUT   : 5000000, // microseconds
    'retries'           => defined('SNMP_RETRIES')   ? SNMP_RETRIES   : 2,
    'port'              => defined('SNMP_PORT')       ? SNMP_PORT       : 161,

    // -----------------------------------------------------------------------
    // Standard IF-MIB interface OIDs  (RFC 2863 / 2665)
    // -----------------------------------------------------------------------
    'oids' => [
        'interfaces' => [
            // IF-MIB (RFC 2863) — base table
            'ifIndex'        => '.1.3.6.1.2.1.2.2.1.1',
            'ifDescr'        => '.1.3.6.1.2.1.2.2.1.2',
            'ifType'         => '.1.3.6.1.2.1.2.2.1.3',
            'ifMtu'          => '.1.3.6.1.2.1.2.2.1.4',
            'ifSpeed'        => '.1.3.6.1.2.1.2.2.1.5',
            'ifPhysAddress'  => '.1.3.6.1.2.1.2.2.1.6',
            'ifAdminStatus'  => '.1.3.6.1.2.1.2.2.1.7',
            'ifOperStatus'   => '.1.3.6.1.2.1.2.2.1.8',
            'ifInOctets'     => '.1.3.6.1.2.1.2.2.1.10',
            'ifOutOctets'    => '.1.3.6.1.2.1.2.2.1.16',

            // IF-MIB — ifXTable (64-bit counters & aliases)
            'ifHCInOctets'   => '.1.3.6.1.2.1.31.1.1.1.6',
            'ifHCOutOctets'  => '.1.3.6.1.2.1.31.1.1.1.10',
            'ifAlias'        => '.1.3.6.1.2.1.31.1.1.1.18',
            'ifName'         => '.1.3.6.1.2.1.31.1.1.1.1',
        ],

        // -------------------------------------------------------------------
        // MikroTik enterprise — Simple Queue  (MIKROTIK-MIB)
        // Base: .1.3.6.1.4.1.14988.1.1.2
        // -------------------------------------------------------------------
        'queues' => [
            'mtxrQueueSimpleIndex'        => '.1.3.6.1.4.1.14988.1.1.2.1.1',
            'mtxrQueueSimpleName'         => '.1.3.6.1.4.1.14988.1.1.2.1.2',
            'mtxrQueueSimpleSrcAddr'      => '.1.3.6.1.4.1.14988.1.1.2.1.3',
            'mtxrQueueSimpleSrcMask'      => '.1.3.6.1.4.1.14988.1.1.2.1.4',
            'mtxrQueueSimpleDstAddr'      => '.1.3.6.1.4.1.14988.1.1.2.1.5',
            'mtxrQueueSimpleDstMask'      => '.1.3.6.1.4.1.14988.1.1.2.1.6',
            'mtxrQueueSimpleIface'        => '.1.3.6.1.4.1.14988.1.1.2.1.7',
            'mtxrQueueSimpleBytesIn'      => '.1.3.6.1.4.1.14988.1.1.2.1.8',
            'mtxrQueueSimpleBytesOut'     => '.1.3.6.1.4.1.14988.1.1.2.1.9',
            'mtxrQueueSimplePacketsIn'    => '.1.3.6.1.4.1.14988.1.1.2.1.10',
            'mtxrQueueSimplePacketsOut'   => '.1.3.6.1.4.1.14988.1.1.2.1.11',
            'mtxrQueueSimpleQueuesIn'     => '.1.3.6.1.4.1.14988.1.1.2.1.12',
            'mtxrQueueSimpleQueuesOut'    => '.1.3.6.1.4.1.14988.1.1.2.1.13',
            'mtxrQueueSimpleDroppedIn'    => '.1.3.6.1.4.1.14988.1.1.2.1.14',
            'mtxrQueueSimpleDroppedOut'   => '.1.3.6.1.4.1.14988.1.1.2.1.15',
            'mtxrQueueSimpleRateLimitIn'  => '.1.3.6.1.4.1.14988.1.1.2.1.16',
            'mtxrQueueSimpleRateLimitOut' => '.1.3.6.1.4.1.14988.1.1.2.1.17',
        ],

        // -------------------------------------------------------------------
        // MikroTik enterprise — PPPoE Active Sessions  (MIKROTIK-MIB)
        // Base: .1.3.6.1.4.1.14988.1.1.2.2   (mtxrPPPoE)
        // -------------------------------------------------------------------
        'pppoe' => [
            'mtxrPPPoESessionIndex'     => '.1.3.6.1.4.1.14988.1.1.2.2.1.1',
            'mtxrPPPoESessionName'      => '.1.3.6.1.4.1.14988.1.1.2.2.1.2',
            'mtxrPPPoESessionAddress'   => '.1.3.6.1.4.1.14988.1.1.2.2.1.3',
            'mtxrPPPoESessionUptime'    => '.1.3.6.1.4.1.14988.1.1.2.2.1.4',
            'mtxrPPPoESessionService'   => '.1.3.6.1.4.1.14988.1.1.2.2.1.5',
            'mtxrPPPoESessionCallerID'  => '.1.3.6.1.4.1.14988.1.1.2.2.1.6',
            'mtxrPPPoESessionEncoding'  => '.1.3.6.1.4.1.14988.1.1.2.2.1.7',
        ],

        // -------------------------------------------------------------------
        // MikroTik — Wireless (MIKROTIK-MIB)
        // Base: .1.3.6.1.4.1.14988.1.1.1
        // -------------------------------------------------------------------
        'wireless' => [
            'mtxrWlApIndex'         => '.1.3.6.1.4.1.14988.1.1.1.3.1.1',
            'mtxrWlApSsid'          => '.1.3.6.1.4.1.14988.1.1.1.3.1.4',
            'mtxrWlApBssid'         => '.1.3.6.1.4.1.14988.1.1.1.3.1.5',
            'mtxrWlApClientCount'   => '.1.3.6.1.4.1.14988.1.1.1.3.1.6',
            'mtxrWlApFreq'          => '.1.3.6.1.4.1.14988.1.1.1.3.1.7',
            'mtxrWlApBand'          => '.1.3.6.1.4.1.14988.1.1.1.3.1.8',
            'mtxrWlRtabTxRate'      => '.1.3.6.1.4.1.14988.1.1.1.2.1.8',
            'mtxrWlRtabRxRate'      => '.1.3.6.1.4.1.14988.1.1.1.2.1.9',
            'mtxrWlRtabStrength'    => '.1.3.6.1.4.1.14988.1.1.1.2.1.3',
        ],
    ],

    // -----------------------------------------------------------------------
    // Interface type mappings (IANAifType)
    // -----------------------------------------------------------------------
    'interface_types' => [
        1   => 'other',
        6   => 'ethernetCsmacd',
        24  => 'softwareLoopback',
        131 => 'tunnel',
        161 => 'ieee8023adLag',
        166 => 'mpls',
        53  => 'propVirtual',
    ],

    // -----------------------------------------------------------------------
    // SNMP library error suppression in production
    // -----------------------------------------------------------------------
    'suppress_errors' => true,
];
