<?php

declare(strict_types=1);

namespace NOC\Modules\Interfaces;

use NOC\Core\Database;

/**
 * InterfaceModel — Data-access layer for the `interfaces` table.
 *
 * All queries use prepared statements.
 *
 * @package NOC\Modules\Interfaces
 * @version 1.0.0
 */
final class InterfaceModel
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
     * Return all interfaces for a router ordered by if_index.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function findByRouter(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `interfaces` WHERE `router_id` = ? ORDER BY `if_index` ASC',
            [$routerId],
        );
    }

    /**
     * Return a single interface by primary key.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `interfaces` WHERE `id` = ? LIMIT 1',
            [$id],
        );
    }

    /**
     * Look up an interface by router + SNMP ifIndex.
     *
     * @param  int $routerId
     * @param  int $ifIndex
     * @return array<string,mixed>|null
     */
    public function findByIfIndex(int $routerId, int $ifIndex): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `interfaces` WHERE `router_id` = ? AND `if_index` = ? LIMIT 1',
            [$routerId, $ifIndex],
        );
    }

    /**
     * Return all monitored interfaces, joined with their router name/IP.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getMonitored(): array
    {
        return $this->db->fetchAll(
            'SELECT i.*, r.`name` AS router_name, r.`ip_address`
               FROM `interfaces` i
               JOIN `routers` r ON r.`id` = i.`router_id`
              WHERE i.`monitored` = 1
              ORDER BY r.`name` ASC, i.`name` ASC',
        );
    }

    /**
     * Count all monitored interfaces.
     *
     * @return int
     */
    public function getMonitoredCount(): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `interfaces` WHERE `monitored` = 1',
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a single interface and return its new primary key.
     *
     * @param  array<string,mixed> $data
     * @return int
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert('interfaces', [
            'router_id'    => (int) $data['router_id'],
            'if_index'     => (int) $data['if_index'],
            'name'         => (string) $data['name'],
            'alias'        => $data['alias']        ?? null,
            'description'  => $data['description']  ?? null,
            'type'         => isset($data['type'])   ? (int) $data['type'] : null,
            'mtu'          => isset($data['mtu'])    ? (int) $data['mtu']  : null,
            'speed'        => isset($data['speed'])  ? (int) $data['speed'] : null,
            'mac_address'  => $data['mac_address']   ?? null,
            'admin_status' => isset($data['admin_status']) ? (int) $data['admin_status'] : null,
            'oper_status'  => isset($data['oper_status'])  ? (int) $data['oper_status']  : null,
            'monitored'    => (int) ($data['monitored'] ?? 0),
        ]);
    }

    /**
     * Update a single interface row.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $data
     * @return int  Rows affected.
     */
    public function update(int $id, array $data): int
    {
        $allowed = [
            'name', 'alias', 'description', 'type', 'mtu', 'speed',
            'mac_address', 'admin_status', 'oper_status', 'monitored',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if ($payload === []) {
            return 0;
        }

        return $this->db->update('interfaces', $payload, 'id = ?', [$id]);
    }

    /**
     * Delete an interface row by primary key.
     *
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        return $this->db->delete('interfaces', 'id = ?', [$id]);
    }

    /**
     * Toggle the monitored flag for an interface.
     *
     * @param  int  $id
     * @param  bool $monitored
     * @return int
     */
    public function setMonitored(int $id, bool $monitored): int
    {
        return $this->db->update(
            'interfaces',
            ['monitored' => (int) $monitored],
            'id = ?',
            [$id],
        );
    }

    // -------------------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------------------

    /**
     * Bulk-insert discovered interfaces for a router.
     *
     * Skips duplicates via INSERT IGNORE (unique key: router_id + if_index).
     *
     * @param  int                          $routerId
     * @param  array<int,array<string,mixed>> $interfaces  Each element must have the
     *                                                     same keys as create().
     * @return int  Number of rows inserted.
     */
    public function bulkInsert(int $routerId, array $interfaces): int
    {
        if ($interfaces === []) {
            return 0;
        }

        $inserted = 0;

        $this->db->beginTransaction();

        try {
            foreach ($interfaces as $iface) {
                $iface['router_id'] = $routerId;

                $existing = $this->findByIfIndex($routerId, (int) $iface['if_index']);

                if ($existing === null) {
                    $this->create($iface);
                    ++$inserted;
                } else {
                    // Update dynamic fields that may change (speed, status, alias).
                    $this->update((int) $existing['id'], $iface);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return $inserted;
    }
}
