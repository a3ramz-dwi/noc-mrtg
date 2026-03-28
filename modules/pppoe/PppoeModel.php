<?php

declare(strict_types=1);

namespace NOC\Modules\Pppoe;

use NOC\Core\Database;

/**
 * PppoeModel — Data-access layer for the `pppoe_users` table.
 *
 * All queries use prepared statements.
 *
 * @package NOC\Modules\Pppoe
 * @version 1.0.0
 */
final class PppoeModel
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
     * Return all PPPoE users for a router ordered by name.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function findByRouter(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `pppoe_users` WHERE `router_id` = ? ORDER BY `name` ASC',
            [$routerId],
        );
    }

    /**
     * Return a single PPPoE user by primary key.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `pppoe_users` WHERE `id` = ? LIMIT 1',
            [$id],
        );
    }

    /**
     * Look up a PPPoE user by router + username.
     *
     * @param  int    $routerId
     * @param  string $name
     * @return array<string,mixed>|null
     */
    public function findByName(int $routerId, string $name): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `pppoe_users` WHERE `router_id` = ? AND `name` = ? LIMIT 1',
            [$routerId, $name],
        );
    }

    /**
     * Return all monitored PPPoE users joined with their router info.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getMonitored(): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, r.`name` AS router_name, r.`ip_address`
               FROM `pppoe_users` p
               JOIN `routers` r ON r.`id` = p.`router_id`
              WHERE p.`monitored` = 1
              ORDER BY r.`name` ASC, p.`name` ASC',
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new PPPoE user row and return its primary key.
     *
     * @param  array<string,mixed> $data
     * @return int
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert('pppoe_users', [
            'router_id'      => (int) $data['router_id'],
            'name'           => (string) $data['name'],
            'service'        => $data['service']        ?? null,
            'profile'        => $data['profile']        ?? null,
            'remote_address' => $data['remote_address'] ?? null,
            'local_address'  => $data['local_address']  ?? null,
            'caller_id'      => $data['caller_id']      ?? null,
            'uptime'         => isset($data['uptime']) ? (int) $data['uptime'] : null,
            'status'         => $data['status']         ?? 'connected',
            'monitored'      => (int) ($data['monitored'] ?? 0),
        ]);
    }

    /**
     * Update an existing PPPoE user row.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $data
     * @return int  Rows affected.
     */
    public function update(int $id, array $data): int
    {
        $allowed = [
            'name', 'service', 'profile', 'remote_address',
            'local_address', 'caller_id', 'uptime', 'status', 'monitored',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if ($payload === []) {
            return 0;
        }

        return $this->db->update('pppoe_users', $payload, 'id = ?', [$id]);
    }

    /**
     * Delete a PPPoE user by primary key.
     *
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        return $this->db->delete('pppoe_users', 'id = ?', [$id]);
    }

    /**
     * Toggle the monitored flag for a PPPoE user.
     *
     * @param  int  $id
     * @param  bool $monitored
     * @return int
     */
    public function setMonitored(int $id, bool $monitored): int
    {
        return $this->db->update(
            'pppoe_users',
            ['monitored' => (int) $monitored],
            'id = ?',
            [$id],
        );
    }

    /**
     * Update connection status and uptime for a PPPoE session.
     *
     * @param  int      $id
     * @param  string   $status  'connected'|'disconnected'
     * @param  int|null $uptime  Session uptime in seconds, null to leave unchanged.
     * @return int
     */
    public function updateStatus(int $id, string $status, ?int $uptime = null): int
    {
        $payload = ['status' => $status];

        if ($uptime !== null) {
            $payload['uptime'] = $uptime;
        }

        return $this->db->update('pppoe_users', $payload, 'id = ?', [$id]);
    }

    // -------------------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------------------

    /**
     * Upsert a batch of discovered PPPoE sessions for a router.
     *
     * Inserts new sessions and updates existing ones (matched by name).
     * Returns count of rows inserted.
     *
     * @param  int                            $routerId
     * @param  array<int,array<string,mixed>> $pppoeUsers
     * @return int  Rows inserted.
     */
    public function bulkUpsert(int $routerId, array $pppoeUsers): int
    {
        if ($pppoeUsers === []) {
            return 0;
        }

        $inserted = 0;

        $this->db->beginTransaction();

        try {
            foreach ($pppoeUsers as $user) {
                $user['router_id'] = $routerId;

                $existing = $this->findByName($routerId, (string) $user['name']);

                if ($existing === null) {
                    $this->create($user);
                    ++$inserted;
                } else {
                    $this->update((int) $existing['id'], $user);
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
