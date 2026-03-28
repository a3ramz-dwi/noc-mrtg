<?php

declare(strict_types=1);

namespace NOC\Modules\Monitoring;

use NOC\Core\Database;

/**
 * MonitoringModel — Data-access layer for traffic and monitoring data.
 *
 * Queries the traffic_data, traffic_daily, traffic_weekly, and
 * traffic_monthly tables using the polymorphic target_type/target_id
 * pattern defined in the schema.
 *
 * @package NOC\Modules\Monitoring
 * @version 1.0.0
 */
final class MonitoringModel
{
    private readonly Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Raw traffic data
    // -------------------------------------------------------------------------

    /**
     * Return raw traffic_data rows for a target within a time range.
     *
     * @param  string    $targetType  'interface'|'queue'|'pppoe'
     * @param  int       $targetId
     * @param  \DateTime $from
     * @param  \DateTime $to
     * @return array<int,array<string,mixed>>
     */
    public function getTrafficData(
        string    $targetType,
        int       $targetId,
        \DateTime $from,
        \DateTime $to,
    ): array {
        return $this->db->fetchAll(
            'SELECT `bytes_in`, `bytes_out`, `timestamp`
               FROM `traffic_data`
              WHERE `target_type` = ?
                AND `target_id`   = ?
                AND `timestamp`   BETWEEN ? AND ?
              ORDER BY `timestamp` ASC',
            [
                $targetType,
                $targetId,
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Aggregated traffic
    // -------------------------------------------------------------------------

    /**
     * Return daily traffic rows for the last N days.
     *
     * @param  string $targetType
     * @param  int    $targetId
     * @param  int    $days
     * @return array<int,array<string,mixed>>
     */
    public function getDailyTraffic(string $targetType, int $targetId, int $days = 30): array
    {
        return $this->db->fetchAll(
            'SELECT `date`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                    `max_bps_in`, `max_bps_out`, `samples`
               FROM `traffic_daily`
              WHERE `target_type` = ? AND `target_id` = ?
                AND `date`        >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              ORDER BY `date` ASC',
            [$targetType, $targetId, $days],
        );
    }

    /**
     * Return weekly traffic rows for the last N weeks.
     *
     * @param  string $targetType
     * @param  int    $targetId
     * @param  int    $weeks
     * @return array<int,array<string,mixed>>
     */
    public function getWeeklyTraffic(string $targetType, int $targetId, int $weeks = 12): array
    {
        return $this->db->fetchAll(
            'SELECT `week_start`, `week_number`, `year`, `bytes_in`, `bytes_out`,
                    `avg_bps_in`, `avg_bps_out`, `max_bps_in`, `max_bps_out`, `samples`
               FROM `traffic_weekly`
              WHERE `target_type` = ? AND `target_id` = ?
                AND `week_start`  >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
              ORDER BY `week_start` ASC',
            [$targetType, $targetId, $weeks],
        );
    }

    /**
     * Return monthly traffic rows for the last N months.
     *
     * @param  string $targetType
     * @param  int    $targetId
     * @param  int    $months
     * @return array<int,array<string,mixed>>
     */
    public function getMonthlyTraffic(string $targetType, int $targetId, int $months = 12): array
    {
        return $this->db->fetchAll(
            'SELECT `month`, `bytes_in`, `bytes_out`, `avg_bps_in`, `avg_bps_out`,
                    `max_bps_in`, `max_bps_out`, `samples`
               FROM `traffic_monthly`
              WHERE `target_type` = ? AND `target_id` = ?
                AND `month`       >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
              ORDER BY `month` ASC',
            [$targetType, $targetId, $months],
        );
    }

    /**
     * Return the most recent traffic_data row for a target.
     *
     * @param  string $targetType
     * @param  int    $targetId
     * @return array<string,mixed>|null
     */
    public function getLatestTraffic(string $targetType, int $targetId): ?array
    {
        return $this->db->fetch(
            'SELECT `bytes_in`, `bytes_out`, `timestamp`
               FROM `traffic_data`
              WHERE `target_type` = ? AND `target_id` = ?
              ORDER BY `timestamp` DESC
              LIMIT 1',
            [$targetType, $targetId],
        );
    }

    // -------------------------------------------------------------------------
    // Aggregates across all targets
    // -------------------------------------------------------------------------

    /**
     * Return total bytes in/out across all targets for the last N hours.
     *
     * @param  int $hours
     * @return array<string,mixed>  Keys: total_in, total_out
     */
    public function getTotalNetworkTraffic(int $hours = 24): array
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(`bytes_in`), 0)  AS total_in,
                    COALESCE(SUM(`bytes_out`), 0) AS total_out
               FROM `traffic_data`
              WHERE `timestamp` >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [$hours],
        );

        return $row ?? ['total_in' => 0, 'total_out' => 0];
    }

    /**
     * Return top N targets by total traffic in the last 24 hours.
     *
     * @param  string $targetType  'interface'|'queue'|'pppoe'
     * @param  int    $limit
     * @param  string $period      'today'|'hour' — grouping hint (used in WHERE clause)
     * @return array<int,array<string,mixed>>
     */
    public function getTopTrafficTargets(
        string $targetType,
        int    $limit  = 10,
        string $period = 'today',
    ): array {
        $interval = match ($period) {
            'hour'  => '1 HOUR',
            default => '24 HOUR',
        };

        return $this->db->fetchAll(
            "SELECT `target_id`,
                    SUM(`bytes_in`)  AS total_in,
                    SUM(`bytes_out`) AS total_out,
                    SUM(`bytes_in` + `bytes_out`) AS total_bytes
               FROM `traffic_data`
              WHERE `target_type` = ?
                AND `timestamp`   >= DATE_SUB(NOW(), INTERVAL {$interval})
              GROUP BY `target_id`
              ORDER BY total_bytes DESC
              LIMIT ?",
            [$targetType, $limit],
        );
    }

    /**
     * Return a snapshot of counts and totals suitable for the main dashboard.
     *
     * @return array<string,mixed>
     */
    public function getNetworkSummary(): array
    {
        $totalRouters = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `routers`');

        $onlineRouters = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `routers` WHERE `status` = 'active'",
        );

        $totalInterfaces = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `interfaces`');

        $monitoredInterfaces = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `interfaces` WHERE `monitored` = 1',
        );

        $totalQueues = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM `simple_queues`');

        $activePppoe = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `pppoe_users` WHERE `status` = 'connected'",
        );

        $traffic = $this->getTotalNetworkTraffic(24);

        return [
            'total_routers'        => $totalRouters,
            'online_routers'       => $onlineRouters,
            'offline_routers'      => $totalRouters - $onlineRouters,
            'total_interfaces'     => $totalInterfaces,
            'monitored_interfaces' => $monitoredInterfaces,
            'total_queues'         => $totalQueues,
            'active_pppoe'         => $activePppoe,
            'total_in_24h'         => (int) $traffic['total_in'],
            'total_out_24h'        => (int) $traffic['total_out'],
        ];
    }
}
