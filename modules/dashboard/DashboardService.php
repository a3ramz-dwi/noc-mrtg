<?php

declare(strict_types=1);

namespace NOC\Modules\Dashboard;

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\Modules\Monitoring\MonitoringService;
use NOC\Modules\Monitoring\MonitoringModel;
use NOC\Modules\Routers\RouterModel;

/**
 * DashboardService — Aggregates data from all modules for the main NOC dashboard.
 *
 * Combines router stats, interface/queue/PPPoE counts, live bandwidth,
 * top traffic targets, and recent audit log entries into a single
 * payload consumed by DashboardController.
 *
 * @package NOC\Modules\Dashboard
 * @version 1.0.0
 */
final class DashboardService
{
    private readonly MonitoringService $monitoringService;
    private readonly MonitoringModel   $monitoringModel;
    private readonly RouterModel       $routerModel;
    private readonly Database          $db;
    private readonly Logger            $logger;

    public function __construct(
        ?MonitoringService $monitoringService = null,
        ?MonitoringModel   $monitoringModel   = null,
        ?RouterModel       $routerModel       = null,
        ?Database          $db                = null,
        ?Logger            $logger            = null,
    ) {
        $this->db                = $db                ?? Database::getInstance();
        $this->logger            = $logger            ?? Logger::getInstance();
        $this->monitoringModel   = $monitoringModel   ?? new MonitoringModel();
        $this->monitoringService = $monitoringService ?? new MonitoringService(
            $this->monitoringModel,
            $this->db,
            $this->logger,
        );
        $this->routerModel = $routerModel ?? new RouterModel($this->db);
    }

    // -------------------------------------------------------------------------
    // Full dashboard payload
    // -------------------------------------------------------------------------

    /**
     * Build and return the complete dashboard data array.
     *
     * @return array{
     *   total_routers: int,
     *   online_routers: int,
     *   offline_routers: int,
     *   total_interfaces: int,
     *   monitored_interfaces: int,
     *   total_queues: int,
     *   total_pppoe: int,
     *   current_in: int,
     *   current_out: int,
     *   traffic_24h: array<string,mixed>,
     *   top_interfaces: array<int,array<string,mixed>>,
     *   router_status_list: array<int,array<string,mixed>>,
     *   recent_events: array<int,array<string,mixed>>,
     * }
     */
    public function getDashboardStats(): array
    {
        $routerStats = $this->routerModel->getRouterStats();

        $totalInterfaces = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `interfaces`',
        );

        $monitoredInterfaces = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `interfaces` WHERE `monitored` = 1',
        );

        $totalQueues = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `simple_queues`',
        );

        $totalPppoe = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `pppoe_users`',
        );

        // Current bandwidth: sum of all traffic_data rows in the last 5 minutes.
        $interval = 300;
        $recent   = $this->db->fetch(
            'SELECT COALESCE(SUM(`bytes_in`), 0)  AS bytes_in,
                    COALESCE(SUM(`bytes_out`), 0) AS bytes_out
               FROM `traffic_data`
              WHERE `timestamp` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
        );

        $currentIn  = isset($recent['bytes_in'])
            ? (int) round((int) $recent['bytes_in']  * 8 / $interval)
            : 0;
        $currentOut = isset($recent['bytes_out'])
            ? (int) round((int) $recent['bytes_out'] * 8 / $interval)
            : 0;

        $traffic24h = $this->monitoringModel->getTotalNetworkTraffic(24);

        $topInterfaces = $this->db->fetchAll(
            'SELECT td.`target_id` AS interface_id,
                    i.`name`       AS interface_name,
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
              LIMIT 10',
            ['interface'],
        );

        $routerStatusList = $this->db->fetchAll(
            'SELECT `id`, `name`, `ip_address`, `identity`, `model`,
                    `status`, `uptime`, `updated_at`
               FROM `routers`
              ORDER BY `name` ASC',
        );

        $recentEvents = $this->db->fetchAll(
            'SELECT al.`id`, al.`action`, al.`module`, al.`target_id`,
                    al.`details`, al.`ip_address`, al.`created_at`,
                    u.`username`
               FROM `audit_log` al
               LEFT JOIN `users` u ON u.`id` = al.`user_id`
              ORDER BY al.`created_at` DESC
              LIMIT 10',
        );

        return [
            'total_routers'        => $routerStats['total'],
            'online_routers'       => $routerStats['active'],
            'offline_routers'      => $routerStats['inactive'] + $routerStats['error'],
            'total_interfaces'     => $totalInterfaces,
            'monitored_interfaces' => $monitoredInterfaces,
            'total_queues'         => $totalQueues,
            'total_pppoe'          => $totalPppoe,
            'current_in'           => $currentIn,
            'current_out'          => $currentOut,
            'traffic_24h'          => $traffic24h,
            'top_interfaces'       => $topInterfaces,
            'router_status_list'   => $routerStatusList,
            'recent_events'        => $recentEvents,
        ];
    }

    // -------------------------------------------------------------------------
    // Realtime refresh
    // -------------------------------------------------------------------------

    /**
     * Return a lightweight stats snapshot for AJAX polling.
     *
     * @return array<string,mixed>
     */
    public function getRealtimeStats(): array
    {
        return $this->monitoringService->getRealtimeStats();
    }
}
