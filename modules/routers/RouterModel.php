<?php

declare(strict_types=1);

namespace NOC\Modules\Routers;

use NOC\Core\Database;

/**
 * RouterModel — Data-access layer for the `routers` table.
 *
 * All queries use prepared statements. No raw interpolation of external
 * data into SQL strings.
 *
 * @package NOC\Modules\Routers
 * @version 1.0.0
 */
final class RouterModel
{
    private readonly Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Return every router row ordered by name.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `routers` ORDER BY `name` ASC',
        );
    }

    /**
     * Return a single router by primary key, or null if not found.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `routers` WHERE `id` = ? LIMIT 1',
            [$id],
        );
    }

    /**
     * Return a router with aggregated counts from related tables.
     *
     * Uses the v_router_summary view for convenience.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function findWithStats(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `v_router_summary` WHERE `id` = ? LIMIT 1',
            [$id],
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new router row and return the new primary key.
     *
     * @param  array<string,mixed> $data
     * @return int
     */
    public function create(array $data): int
    {
        $id = $this->db->insert('routers', [
            'name'           => $data['name'],
            'ip_address'     => $data['ip_address'],
            'snmp_community' => $data['snmp_community'] ?? 'public',
            'snmp_version'   => $data['snmp_version']   ?? '2c',
            'snmp_port'      => (int) ($data['snmp_port'] ?? 161),
            'username'       => $data['username']        ?? null,
            'password'       => $data['password']        ?? null,
            'status'         => $data['status']          ?? 'active',
        ]);

        return (int) $id;
    }

    /**
     * Update mutable fields on an existing router.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $data
     * @return int  Rows affected.
     */
    public function update(int $id, array $data): int
    {
        $allowed = [
            'name', 'ip_address', 'snmp_community', 'snmp_version',
            'snmp_port', 'username', 'password', 'status',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if ($payload === []) {
            return 0;
        }

        return $this->db->update('routers', $payload, 'id = ?', [$id]);
    }

    /**
     * Hard-delete a router (cascades to interfaces, queues, pppoe_users).
     *
     * @param  int $id
     * @return int  Rows affected.
     */
    public function delete(int $id): int
    {
        return $this->db->delete('routers', 'id = ?', [$id]);
    }

    /**
     * Update only the status column.
     *
     * @param  int    $id
     * @param  string $status  'active'|'inactive'|'error'
     * @return int
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->db->update(
            'routers',
            ['status' => $status],
            'id = ?',
            [$id],
        );
    }

    /**
     * Persist system information retrieved from SNMP.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $sysInfo  Keys: identity, model, router_os_version, uptime
     * @return int
     */
    public function updateSystemInfo(int $id, array $sysInfo): int
    {
        $payload = array_intersect_key(
            $sysInfo,
            array_flip(['identity', 'model', 'router_os_version', 'uptime', 'serial']),
        );

        if ($payload === []) {
            return 0;
        }

        return $this->db->update('routers', $payload, 'id = ?', [$id]);
    }

    // -------------------------------------------------------------------------
    // Aggregates
    // -------------------------------------------------------------------------

    /**
     * Return per-status counts suitable for a dashboard widget.
     *
     * @return array<string,int>  Keys: total, active, inactive, error
     */
    public function getRouterStats(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT `status`, COUNT(*) AS `cnt` FROM `routers` GROUP BY `status`',
        );

        $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'error' => 0];

        foreach ($rows as $row) {
            $s = (string) $row['status'];
            if (array_key_exists($s, $stats)) {
                $stats[$s] = (int) $row['cnt'];
            }
            $stats['total'] += (int) $row['cnt'];
        }

        return $stats;
    }
}
