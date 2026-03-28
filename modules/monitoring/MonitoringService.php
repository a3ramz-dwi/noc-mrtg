<?php

declare(strict_types=1);

namespace NOC\Modules\Monitoring;

use NOC\Core\Database;
use NOC\Core\Logger;

/**
 * MonitoringService — Business-logic layer for the Monitoring module.
 *
 * Formats raw traffic data for Chart.js, aggregates live bandwidth
 * readings, and builds the dashboard dataset.
 *
 * @package NOC\Modules\Monitoring
 * @version 1.0.0
 */
final class MonitoringService
{
    private readonly MonitoringModel $model;
    private readonly Database        $db;
    private readonly Logger          $logger;

    public function __construct(
        ?MonitoringModel $model  = null,
        ?Database        $db     = null,
        ?Logger          $logger = null,
    ) {
        $this->model  = $model  ?? new MonitoringModel();
        $this->db     = $db     ?? Database::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
    }

    // -------------------------------------------------------------------------
    // Chart data formatting
    // -------------------------------------------------------------------------

    /**
     * Return Chart.js-ready data for a target over a given period.
     *
     * @param  string $targetType  'interface'|'queue'|'pppoe'
     * @param  int    $targetId
     * @param  string $period      'daily'|'weekly'|'monthly'|'raw'
     * @return array{labels:string[], datasets:array<int,array<string,mixed>>}
     */
    public function getChartData(string $targetType, int $targetId, string $period): array
    {
        $rows = match ($period) {
            'weekly'  => $this->model->getWeeklyTraffic($targetType, $targetId, 12),
            'monthly' => $this->model->getMonthlyTraffic($targetType, $targetId, 12),
            'raw'     => $this->getRawChartRows($targetType, $targetId),
            default   => $this->model->getDailyTraffic($targetType, $targetId, 30),
        };

        $labels  = [];
        $dataIn  = [];
        $dataOut = [];

        foreach ($rows as $row) {
            $labels[]  = $this->resolveLabel($row, $period);
            // Convert bytes/s averages to Mbps for display.
            $dataIn[]  = round((float) ($row['avg_bps_in']  ?? 0) / 1_000_000, 4);
            $dataOut[] = round((float) ($row['avg_bps_out'] ?? 0) / 1_000_000, 4);
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'           => 'Inbound (Mbps)',
                    'data'            => $dataIn,
                    'borderColor'     => 'rgb(54, 162, 235)',
                    'backgroundColor' => 'rgba(54, 162, 235, 0.1)',
                    'tension'         => 0.3,
                    'fill'            => true,
                ],
                [
                    'label'           => 'Outbound (Mbps)',
                    'data'            => $dataOut,
                    'borderColor'     => 'rgb(255, 99, 132)',
                    'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                    'tension'         => 0.3,
                    'fill'            => true,
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Live bandwidth
    // -------------------------------------------------------------------------

    /**
     * Return the latest bandwidth reading for a single target.
     *
     * Returns bytes_in/out converted to bits per second based on the
     * assumed 5-minute polling interval.
     *
     * @param  string $targetType
     * @param  int    $targetId
     * @return array<string,mixed>
     */
    public function getLiveData(string $targetType, int $targetId): array
    {
        $row = $this->model->getLatestTraffic($targetType, $targetId);

        if ($row === null) {
            return ['bps_in' => 0, 'bps_out' => 0, 'timestamp' => null];
        }

        // 300-second interval → convert delta bytes to bits/s.
        $interval = 300;

        return [
            'bps_in'    => (int) round((int) $row['bytes_in']  * 8 / $interval),
            'bps_out'   => (int) round((int) $row['bytes_out'] * 8 / $interval),
            'timestamp' => $row['timestamp'],
        ];
    }

    /**
     * Return the latest bandwidth reading for every monitored target.
     *
     * Queries each monitored interface, queue, and PPPoE session.
     *
     * @return array{interfaces:array<int,mixed>, queues:array<int,mixed>, pppoe:array<int,mixed>}
     */
    public function getLiveBandwidthAll(): array
    {
        $results = ['interfaces' => [], 'queues' => [], 'pppoe' => []];

        $monitoredInterfaces = $this->db->fetchAll(
            'SELECT `id`, `name`, `router_id` FROM `interfaces` WHERE `monitored` = 1',
        );

        foreach ($monitoredInterfaces as $iface) {
            $live = $this->getLiveData('interface', (int) $iface['id']);
            $results['interfaces'][] = array_merge($iface, $live);
        }

        $monitoredQueues = $this->db->fetchAll(
            'SELECT `id`, `name`, `router_id` FROM `simple_queues` WHERE `monitored` = 1',
        );

        foreach ($monitoredQueues as $queue) {
            $live = $this->getLiveData('queue', (int) $queue['id']);
            $results['queues'][] = array_merge($queue, $live);
        }

        $monitoredPppoe = $this->db->fetchAll(
            "SELECT `id`, `name`, `router_id` FROM `pppoe_users`
              WHERE `monitored` = 1 AND `status` = 'connected'",
        );

        foreach ($monitoredPppoe as $pppoe) {
            $live = $this->getLiveData('pppoe', (int) $pppoe['id']);
            $results['pppoe'][] = array_merge($pppoe, $live);
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Dashboard aggregate
    // -------------------------------------------------------------------------

    /**
     * Return a comprehensive dataset for the main NOC dashboard.
     *
     * @return array<string,mixed>
     */
    public function getDashboardData(): array
    {
        $summary = $this->model->getNetworkSummary();

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
            'SELECT `id`, `name`, `ip_address`, `identity`, `status`, `uptime`, `updated_at`
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

        $traffic24h = $this->getNetworkTotals(24);

        $currentBandwidth = $this->db->fetch(
            'SELECT COALESCE(SUM(`bytes_in`), 0)  AS bytes_in,
                    COALESCE(SUM(`bytes_out`), 0) AS bytes_out
               FROM `traffic_data`
              WHERE `timestamp` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
        );

        $interval   = 300;
        $currentIn  = isset($currentBandwidth['bytes_in'])
            ? (int) round((int) $currentBandwidth['bytes_in']  * 8 / $interval)
            : 0;
        $currentOut = isset($currentBandwidth['bytes_out'])
            ? (int) round((int) $currentBandwidth['bytes_out'] * 8 / $interval)
            : 0;

        return array_merge($summary, [
            'current_in'        => $currentIn,
            'current_out'       => $currentOut,
            'traffic_24h'       => $traffic24h,
            'top_interfaces'    => $topInterfaces,
            'router_status_list'=> $routerStatusList,
            'recent_events'     => $recentEvents,
        ]);
    }

    /**
     * Return total network traffic for a number of hours (for AJAX refresh).
     *
     * @param  int $hours
     * @return array<string,mixed>
     */
    public function getNetworkTotals(int $hours = 24): array
    {
        return $this->model->getTotalNetworkTraffic($hours);
    }

    /**
     * Return a lightweight stats snapshot for realtime AJAX polling.
     *
     * @return array<string,mixed>
     */
    public function getRealtimeStats(): array
    {
        $live = $this->getLiveBandwidthAll();

        $totalBpsIn  = 0;
        $totalBpsOut = 0;

        foreach (array_merge($live['interfaces'], $live['queues'], $live['pppoe']) as $target) {
            $totalBpsIn  += (int) ($target['bps_in']  ?? 0);
            $totalBpsOut += (int) ($target['bps_out'] ?? 0);
        }

        $summary = $this->model->getNetworkSummary();

        return [
            'total_bps_in'   => $totalBpsIn,
            'total_bps_out'  => $totalBpsOut,
            'online_routers' => $summary['online_routers'],
            'active_pppoe'   => $summary['active_pppoe'],
            'timestamp'      => date('Y-m-d H:i:s'),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getRawChartRows(string $targetType, int $targetId): array
    {
        $from = new \DateTime('-24 hours');
        $to   = new \DateTime();

        $raw = $this->model->getTrafficData($targetType, $targetId, $from, $to);

        // Convert raw byte deltas to avg_bps_in / avg_bps_out for uniform handling.
        return array_map(static function (array $row): array {
            $interval = 300;
            return [
                'timestamp'   => $row['timestamp'],
                'avg_bps_in'  => (int) $row['bytes_in']  * 8 / $interval,
                'avg_bps_out' => (int) $row['bytes_out'] * 8 / $interval,
            ];
        }, $raw);
    }

    /** @param array<string,mixed> $row */
    private function resolveLabel(array $row, string $period): string
    {
        return match ($period) {
            'weekly'  => (string) ($row['week_start'] ?? ''),
            'monthly' => (string) ($row['month']      ?? ''),
            'raw'     => isset($row['timestamp'])
                ? (new \DateTime((string) $row['timestamp']))->format('H:i')
                : '',
            default   => (string) ($row['date'] ?? ''),
        };
    }
}
