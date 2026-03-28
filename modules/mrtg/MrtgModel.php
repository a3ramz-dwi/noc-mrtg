<?php

declare(strict_types=1);

namespace NOC\Modules\Mrtg;

use NOC\Core\Database;

/**
 * MrtgModel — Data-access layer for the `mrtg_configs` table.
 *
 * All queries use prepared statements.
 *
 * @package NOC\Modules\Mrtg
 * @version 1.0.0
 */
final class MrtgModel
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
     * Return all MRTG config records ordered by router and type.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findAll(): array
    {
        return $this->db->fetchAll(
            'SELECT mc.*, r.`name` AS router_name
               FROM `mrtg_configs` mc
               JOIN `routers`      r ON r.`id` = mc.`router_id`
              ORDER BY r.`name` ASC, mc.`target_type` ASC',
        );
    }

    /**
     * Return a single MRTG config by primary key.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `mrtg_configs` WHERE `id` = ? LIMIT 1',
            [$id],
        );
    }

    /**
     * Return all MRTG config rows for a specific router.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function findByRouter(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `mrtg_configs`
              WHERE `router_id` = ?
              ORDER BY `target_type` ASC, `id` ASC',
            [$routerId],
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new MRTG config record and return the new primary key.
     *
     * @param  array<string,mixed> $data
     * @return int
     */
    public function saveConfig(array $data): int
    {
        return (int) $this->db->insert('mrtg_configs', [
            'router_id'      => (int) $data['router_id'],
            'target_type'    => (string) ($data['target_type']    ?? 'router'),
            'target_id'      => isset($data['target_id']) ? (int) $data['target_id'] : null,
            'config_content' => (string) ($data['config_content'] ?? ''),
            'filename'       => (string) ($data['filename']       ?? ''),
            'generated_at'   => (string) ($data['generated_at']   ?? date('Y-m-d H:i:s')),
            'status'         => (string) ($data['status']         ?? 'pending'),
        ]);
    }

    /**
     * Update an existing MRTG config record.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $data
     * @return int  Rows affected.
     */
    public function updateConfig(int $id, array $data): int
    {
        $allowed = ['config_content', 'filename', 'generated_at', 'status', 'target_type', 'target_id'];

        $payload = array_intersect_key($data, array_flip($allowed));

        if ($payload === []) {
            return 0;
        }

        return $this->db->update('mrtg_configs', $payload, 'id = ?', [$id]);
    }

    /**
     * Delete an MRTG config record by primary key.
     *
     * @param  int $id
     * @return int
     */
    public function deleteConfig(int $id): int
    {
        return $this->db->delete('mrtg_configs', 'id = ?', [$id]);
    }
}
