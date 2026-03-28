<?php

declare(strict_types=1);

namespace NOC\Modules\Interfaces;

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\SNMP\SnmpManager;
use NOC\SNMP\SnmpDiscovery;

/**
 * InterfaceService — Business-logic layer for the Interfaces module.
 *
 * Handles SNMP discovery, traffic data retrieval, and monitoring
 * management for network interfaces.
 *
 * @package NOC\Modules\Interfaces
 * @version 1.0.0
 */
final class InterfaceService
{
    private readonly InterfaceModel $model;
    private readonly Database       $db;
    private readonly Logger         $logger;

    public function __construct(
        ?InterfaceModel $model  = null,
        ?Database       $db     = null,
        ?Logger         $logger = null,
    ) {
        $this->model  = $model  ?? new InterfaceModel();
        $this->db     = $db     ?? Database::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
    }

    // -------------------------------------------------------------------------
    // Discovery & import
    // -------------------------------------------------------------------------

    /**
     * Discover interfaces on a router via SNMP.
     *
     * Returns the raw discovered list without persisting anything.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>|false  False if router not found or SNMP fails.
     */
    public function discoverInterfaces(int $routerId): array|false
    {
        $router = $this->fetchRouter($routerId);

        if ($router === null) {
            return false;
        }

        try {
            $snmp      = $this->buildSnmpManager($router);
            $discovery = new SnmpDiscovery($snmp);
            $result    = $discovery->discoverInterfaces();

            if ($result === false) {
                $this->logger->warning('Interface discovery failed via SNMP', ['router_id' => $routerId]);
                return false;
            }

            $this->logger->info('Interface discovery complete', [
                'router_id' => $routerId,
                'count'     => count($result),
            ]);

            return array_values($result);
        } catch (\Throwable $e) {
            $this->logger->error('Interface discovery exception', [
                'router_id' => $routerId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Import selected interfaces (by ifIndex list) into the database.
     *
     * Runs a fresh SNMP discovery and persists only those whose ifIndex
     * is in the provided list, enabling monitoring on each.
     *
     * @param  int        $routerId
     * @param  int[]      $ifIndexes  List of ifIndex values to import.
     * @return int        Number of interfaces inserted.
     */
    public function importInterfaces(int $routerId, array $ifIndexes): int
    {
        if ($ifIndexes === []) {
            return 0;
        }

        $discovered = $this->discoverInterfaces($routerId);

        if ($discovered === false) {
            return 0;
        }

        $toImport = array_filter(
            $discovered,
            static fn(array $iface): bool => in_array((int) $iface['if_index'], $ifIndexes, true),
        );

        foreach ($toImport as &$iface) {
            $iface['monitored'] = 1;
        }
        unset($iface);

        return $this->model->bulkInsert($routerId, array_values($toImport));
    }

    /**
     * Sync discovered interfaces with the database.
     *
     * Inserts new entries and updates existing ones. Does not delete
     * interfaces that have disappeared (they may be temporarily down).
     *
     * @param  int $routerId
     * @return array{inserted:int, updated:int}
     */
    public function syncInterfaces(int $routerId): array
    {
        $discovered = $this->discoverInterfaces($routerId);

        if ($discovered === false) {
            return ['inserted' => 0, 'updated' => 0];
        }

        $inserted = 0;
        $updated  = 0;

        foreach ($discovered as $iface) {
            $iface['router_id'] = $routerId;
            $existing = $this->model->findByIfIndex($routerId, (int) $iface['if_index']);

            if ($existing === null) {
                $this->model->create($iface);
                ++$inserted;
            } else {
                $this->model->update((int) $existing['id'], $iface);
                ++$updated;
            }
        }

        $this->logger->info('Interface sync complete', [
            'router_id' => $routerId,
            'inserted'  => $inserted,
            'updated'   => $updated,
        ]);

        return ['inserted' => $inserted, 'updated' => $updated];
    }

    // -------------------------------------------------------------------------
    // Traffic data
    // -------------------------------------------------------------------------

    /**
     * Return aggregated traffic data for an interface over a given period.
     *
     * @param  int    $interfaceId
     * @param  string $period  'daily'|'weekly'|'monthly'
     * @return array<int,array<string,mixed>>
     */
    public function getTrafficData(int $interfaceId, string $period = 'daily'): array
    {
        return match ($period) {
            'weekly'  => $this->db->fetchAll(
                'SELECT `week_start`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_weekly`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `week_start` DESC
                  LIMIT 52',
                ['interface', $interfaceId],
            ),
            'monthly' => $this->db->fetchAll(
                'SELECT `month`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_monthly`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `month` DESC
                  LIMIT 24',
                ['interface', $interfaceId],
            ),
            default   => $this->db->fetchAll(
                'SELECT `date`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_daily`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `date` DESC
                  LIMIT 30',
                ['interface', $interfaceId],
            ),
        };
    }

    /**
     * Return the most recent raw traffic reading for an interface.
     *
     * @param  int $interfaceId
     * @return array<string,mixed>|null
     */
    public function getCurrentBandwidth(int $interfaceId): ?array
    {
        return $this->db->fetch(
            'SELECT `bytes_in`, `bytes_out`, `timestamp`
               FROM `traffic_data`
              WHERE `target_type` = ? AND `target_id` = ?
              ORDER BY `timestamp` DESC
              LIMIT 1',
            ['interface', $interfaceId],
        );
    }

    /**
     * Return the top N interfaces by total traffic over the last 24 hours.
     *
     * @param  int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getTopInterfaces(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT td.`target_id` AS interface_id,
                    i.`name`       AS interface_name,
                    i.`router_id`,
                    r.`name`       AS router_name,
                    SUM(td.`bytes_in`)  AS total_in,
                    SUM(td.`bytes_out`) AS total_out,
                    SUM(td.`bytes_in` + td.`bytes_out`) AS total_bytes
               FROM `traffic_data` td
               JOIN `interfaces` i ON i.`id` = td.`target_id`
               JOIN `routers`    r ON r.`id` = i.`router_id`
              WHERE td.`target_type` = ?
                AND td.`timestamp`   >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              GROUP BY td.`target_id`
              ORDER BY total_bytes DESC
              LIMIT ?',
            ['interface', $limit],
        );
    }

    /**
     * Return aggregate stats for a single interface.
     *
     * @param  int $interfaceId
     * @return array<string,mixed>
     */
    public function getInterfaceStats(int $interfaceId): array
    {
        $row = $this->db->fetch(
            'SELECT SUM(`bytes_in`)  AS total_in,
                    SUM(`bytes_out`) AS total_out,
                    MAX(`bytes_in`)  AS peak_in,
                    MAX(`bytes_out`) AS peak_out,
                    COUNT(*)         AS samples
               FROM `traffic_data`
              WHERE `target_type` = ? AND `target_id` = ?
                AND `timestamp`   >= DATE_SUB(NOW(), INTERVAL 24 HOUR)',
            ['interface', $interfaceId],
        );

        return $row ?? [
            'total_in'  => 0,
            'total_out' => 0,
            'peak_in'   => 0,
            'peak_out'  => 0,
            'samples'   => 0,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function fetchRouter(int $routerId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `routers` WHERE `id` = ? LIMIT 1',
            [$routerId],
        );
    }

    /** @param array<string,mixed> $router */
    private function buildSnmpManager(array $router): SnmpManager
    {
        return new SnmpManager(
            (string) $router['ip_address'],
            (string) ($router['snmp_community'] ?? 'public'),
            (string) ($router['snmp_version']   ?? '2c'),
            5_000_000,
            2,
            (int) ($router['snmp_port'] ?? 161),
        );
    }
}
