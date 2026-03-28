<?php

declare(strict_types=1);

namespace NOC\Modules\Pppoe;

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\SNMP\SnmpManager;
use NOC\SNMP\SnmpDiscovery;

/**
 * PppoeService — Business-logic layer for the PPPoE module.
 *
 * Handles discovery and synchronisation of PPPoE sessions via SNMP,
 * plus traffic data retrieval for monitoring charts.
 *
 * @package NOC\Modules\Pppoe
 * @version 1.0.0
 */
final class PppoeService
{
    private readonly PppoeModel $model;
    private readonly Database   $db;
    private readonly Logger     $logger;

    public function __construct(
        ?PppoeModel $model  = null,
        ?Database   $db     = null,
        ?Logger     $logger = null,
    ) {
        $this->model  = $model  ?? new PppoeModel();
        $this->db     = $db     ?? Database::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
    }

    // -------------------------------------------------------------------------
    // Discovery & sync
    // -------------------------------------------------------------------------

    /**
     * Discover active PPPoE sessions on a router via SNMP.
     *
     * Returns the raw discovered list without persisting anything.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>|false  False if router not found or SNMP fails.
     */
    public function discoverPppoe(int $routerId): array|false
    {
        $router = $this->fetchRouter($routerId);

        if ($router === null) {
            return false;
        }

        try {
            $snmp      = $this->buildSnmpManager($router);
            $discovery = new SnmpDiscovery($snmp);
            $result    = $discovery->discoverPppoe();

            if ($result === false) {
                $this->logger->warning('PPPoE discovery failed via SNMP', ['router_id' => $routerId]);
                return false;
            }

            $this->logger->info('PPPoE discovery complete', [
                'router_id' => $routerId,
                'count'     => count($result),
            ]);

            return array_values($result);
        } catch (\Throwable $e) {
            $this->logger->error('PPPoE discovery exception', [
                'router_id' => $routerId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync discovered PPPoE sessions with the database.
     *
     * Upserts active sessions and marks any previously known session that
     * is no longer in the discovered list as 'disconnected'.
     *
     * @param  int $routerId
     * @return array{upserted:int, disconnected:int}
     */
    public function syncPppoe(int $routerId): array
    {
        $discovered = $this->discoverPppoe($routerId);

        if ($discovered === false) {
            return ['upserted' => 0, 'disconnected' => 0];
        }

        // Build a set of currently active names for quick lookup.
        $activeNames = array_column($discovered, 'name');
        $activeSet   = array_flip(array_map('strval', $activeNames));

        // Upsert all discovered sessions as 'connected'.
        foreach ($discovered as &$user) {
            $user['status'] = 'connected';
        }
        unset($user);

        $upserted = $this->model->bulkUpsert($routerId, $discovered);

        // Mark sessions that are no longer present as disconnected.
        $existing     = $this->model->findByRouter($routerId);
        $disconnected = 0;

        foreach ($existing as $row) {
            if ($row['status'] === 'connected' && !isset($activeSet[(string) $row['name']])) {
                $this->model->updateStatus((int) $row['id'], 'disconnected', null);
                ++$disconnected;
            }
        }

        $this->logger->info('PPPoE sync complete', [
            'router_id'    => $routerId,
            'upserted'     => $upserted,
            'disconnected' => $disconnected,
        ]);

        return ['upserted' => $upserted, 'disconnected' => $disconnected];
    }

    // -------------------------------------------------------------------------
    // Traffic data
    // -------------------------------------------------------------------------

    /**
     * Return aggregated traffic data for a PPPoE session over a given period.
     *
     * @param  int    $pppoeId
     * @param  string $period  'daily'|'weekly'|'monthly'
     * @return array<int,array<string,mixed>>
     */
    public function getTrafficData(int $pppoeId, string $period = 'daily'): array
    {
        return match ($period) {
            'weekly'  => $this->db->fetchAll(
                'SELECT `week_start`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_weekly`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `week_start` DESC
                  LIMIT 52',
                ['pppoe', $pppoeId],
            ),
            'monthly' => $this->db->fetchAll(
                'SELECT `month`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_monthly`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `month` DESC
                  LIMIT 24',
                ['pppoe', $pppoeId],
            ),
            default   => $this->db->fetchAll(
                'SELECT `date`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                        `max_bps_in`, `max_bps_out`
                   FROM `traffic_daily`
                  WHERE `target_type` = ? AND `target_id` = ?
                  ORDER BY `date` DESC
                  LIMIT 30',
                ['pppoe', $pppoeId],
            ),
        };
    }

    /**
     * Return the most recent raw traffic reading for a PPPoE session.
     *
     * @param  int $pppoeId
     * @return array<string,mixed>|null
     */
    public function getCurrentBandwidth(int $pppoeId): ?array
    {
        return $this->db->fetch(
            'SELECT `bytes_in`, `bytes_out`, `timestamp`
               FROM `traffic_data`
              WHERE `target_type` = ? AND `target_id` = ?
              ORDER BY `timestamp` DESC
              LIMIT 1',
            ['pppoe', $pppoeId],
        );
    }

    /**
     * Return all active monitored PPPoE sessions with their router info.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getActiveSessions(): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, r.`name` AS router_name, r.`ip_address`
               FROM `pppoe_users` p
               JOIN `routers` r ON r.`id` = p.`router_id`
              WHERE p.`monitored` = 1
                AND p.`status`    = 'connected'
              ORDER BY r.`name` ASC, p.`name` ASC",
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
