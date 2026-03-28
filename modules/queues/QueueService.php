<?php

declare(strict_types=1);

namespace NOC\Modules\Queues;

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\SNMP\SnmpManager;
use NOC\SNMP\SnmpDiscovery;

/**
 * QueueService — Business-logic layer for the Queues module.
 *
 * Handles SNMP discovery of MikroTik Simple Queues and traffic
 * data retrieval for monitoring.
 *
 * @package NOC\Modules\Queues
 * @version 1.0.0
 */
final class QueueService
{
    private readonly QueueModel $model;
    private readonly Database   $db;
    private readonly Logger     $logger;

    public function __construct(
        ?QueueModel $model  = null,
        ?Database   $db     = null,
        ?Logger     $logger = null,
    ) {
        $this->model  = $model  ?? new QueueModel();
        $this->db     = $db     ?? Database::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
    }

    // -------------------------------------------------------------------------
    // Discovery & import
    // -------------------------------------------------------------------------

    /**
     * Discover MikroTik Simple Queues on a router via SNMP.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>|false
     */
    public function discoverQueues(int $routerId): array|false
    {
        $router = $this->fetchRouter($routerId);

        if ($router === null) {
            return false;
        }

        try {
            $snmp      = $this->buildSnmpManager($router);
            $discovery = new SnmpDiscovery($snmp);
            $result    = $discovery->discoverQueues();

            if ($result === false) {
                $this->logger->warning('Queue discovery failed via SNMP', ['router_id' => $routerId]);
                return false;
            }

            $this->logger->info('Queue discovery complete', [
                'router_id' => $routerId,
                'count'     => count($result),
            ]);

            return array_values($result);
        } catch (\Throwable $e) {
            $this->logger->error('Queue discovery exception', [
                'router_id' => $routerId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Import selected queues (by queue_index) into the database.
     *
     * @param  int   $routerId
     * @param  int[] $queueIndexes
     * @return int   Number of queues inserted.
     */
    public function importQueues(int $routerId, array $queueIndexes): int
    {
        if ($queueIndexes === []) {
            return 0;
        }

        $discovered = $this->discoverQueues($routerId);

        if ($discovered === false) {
            return 0;
        }

        $toImport = array_filter(
            $discovered,
            static fn(array $q): bool => in_array((int) $q['queue_index'], $queueIndexes, true),
        );

        foreach ($toImport as &$queue) {
            $queue['monitored'] = 1;
        }
        unset($queue);

        return $this->model->bulkInsert($routerId, array_values($toImport));
    }

    // -------------------------------------------------------------------------
    // Traffic data
    // -------------------------------------------------------------------------

    /**
     * Return aggregated traffic data for a queue over a given period.
     *
     * @param  int    $queueId
     * @param  string $period  'daily'|'weekly'|'monthly'
     * @return array<int,array<string,mixed>>
     */
    public function getTrafficData(int $queueId, string $period = 'daily'): array
    {
        return match ($period) {
            'weekly'  => $this->db->fetchAll(
                'SELECT `week_start`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_weekly`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `week_start` DESC
                  LIMIT 52',
                ['queue', $queueId],
            ),
            'monthly' => $this->db->fetchAll(
                'SELECT `month`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_monthly`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `month` DESC
                  LIMIT 24',
                ['queue', $queueId],
            ),
            default   => $this->db->fetchAll(
                'SELECT `date`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_daily`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `date` DESC
                  LIMIT 30',
                ['queue', $queueId],
            ),
        };
    }

    /**
     * Return the most recent raw traffic reading for a queue.
     *
     * @param  int $queueId
     * @return array<string,mixed>|null
     */
    public function getCurrentBandwidth(int $queueId): ?array
    {
        return $this->db->fetch(
            'SELECT `bytes_in`, `bytes_out`, `timestamp`
               FROM `traffic_data`
              WHERE `target_type` = ? AND `target_id` = ?
              ORDER BY `timestamp` DESC
              LIMIT 1',
            ['queue', $queueId],
        );
    }

    /**
     * Return the top N queues by total traffic over the last 24 hours.
     *
     * @param  int $limit
     * @return array<int,array<string,mixed>>
     */
    public function getTopQueues(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT td.`target_id` AS queue_id,
                    q.`name`       AS queue_name,
                    q.`target`     AS queue_target,
                    q.`router_id`,
                    r.`name`       AS router_name,
                    SUM(td.`bytes_in`)  AS total_in,
                    SUM(td.`bytes_out`) AS total_out,
                    SUM(td.`bytes_in` + td.`bytes_out`) AS total_bytes
               FROM `traffic_data` td
               JOIN `simple_queues` q ON q.`id` = td.`target_id`
               JOIN `routers`       r ON r.`id` = q.`router_id`
              WHERE td.`target_type` = ?
                AND td.`timestamp`   >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              GROUP BY td.`target_id`
              ORDER BY total_bytes DESC
              LIMIT ?',
            ['queue', $limit],
        );
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
