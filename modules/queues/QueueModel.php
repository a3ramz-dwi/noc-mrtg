<?php

declare(strict_types=1);

namespace NOC\Modules\Queues;

use NOC\Core\Database;

/**
 * QueueModel — Data-access layer for the `simple_queues` table.
 *
 * All queries use prepared statements.
 *
 * @package NOC\Modules\Queues
 * @version 1.0.0
 */
final class QueueModel
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
     * Return all queues for a router ordered by queue_index.
     *
     * @param  int $routerId
     * @return array<int,array<string,mixed>>
     */
    public function findByRouter(int $routerId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM `simple_queues` WHERE `router_id` = ? ORDER BY `queue_index` ASC',
            [$routerId],
        );
    }

    /**
     * Return a single queue by primary key.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM `simple_queues` WHERE `id` = ? LIMIT 1',
            [$id],
        );
    }

    /**
     * Return all monitored queues joined with router info.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getMonitored(): array
    {
        return $this->db->fetchAll(
            'SELECT q.*, r.`name` AS router_name, r.`ip_address`
               FROM `simple_queues` q
               JOIN `routers` r ON r.`id` = q.`router_id`
              WHERE q.`monitored` = 1
              ORDER BY r.`name` ASC, q.`name` ASC',
        );
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Insert a new queue row and return its primary key.
     *
     * @param  array<string,mixed> $data
     * @return int
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert('simple_queues', [
            'router_id'                 => (int) $data['router_id'],
            'queue_index'               => (int) $data['queue_index'],
            'name'                      => (string) $data['name'],
            'target'                    => $data['target']                    ?? null,
            'max_limit_upload'          => isset($data['max_limit_upload'])   ? (int) $data['max_limit_upload']          : null,
            'max_limit_download'        => isset($data['max_limit_download']) ? (int) $data['max_limit_download']        : null,
            'burst_limit_upload'        => isset($data['burst_limit_upload'])        ? (int) $data['burst_limit_upload']        : null,
            'burst_limit_download'      => isset($data['burst_limit_download'])      ? (int) $data['burst_limit_download']      : null,
            'burst_threshold_upload'    => isset($data['burst_threshold_upload'])    ? (int) $data['burst_threshold_upload']    : null,
            'burst_threshold_download'  => isset($data['burst_threshold_download'])  ? (int) $data['burst_threshold_download']  : null,
            'burst_time_upload'         => isset($data['burst_time_upload'])         ? (int) $data['burst_time_upload']         : null,
            'burst_time_download'       => isset($data['burst_time_download'])       ? (int) $data['burst_time_download']       : null,
            'monitored'                 => (int) ($data['monitored'] ?? 0),
        ]);
    }

    /**
     * Update an existing queue row.
     *
     * @param  int                 $id
     * @param  array<string,mixed> $data
     * @return int  Rows affected.
     */
    public function update(int $id, array $data): int
    {
        $allowed = [
            'name', 'target',
            'max_limit_upload', 'max_limit_download',
            'burst_limit_upload', 'burst_limit_download',
            'burst_threshold_upload', 'burst_threshold_download',
            'burst_time_upload', 'burst_time_download',
            'monitored',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));

        if ($payload === []) {
            return 0;
        }

        return $this->db->update('simple_queues', $payload, 'id = ?', [$id]);
    }

    /**
     * Delete a queue row by primary key.
     *
     * @param  int $id
     * @return int
     */
    public function delete(int $id): int
    {
        return $this->db->delete('simple_queues', 'id = ?', [$id]);
    }

    /**
     * Toggle the monitored flag for a queue.
     *
     * @param  int  $id
     * @param  bool $monitored
     * @return int
     */
    public function setMonitored(int $id, bool $monitored): int
    {
        return $this->db->update(
            'simple_queues',
            ['monitored' => (int) $monitored],
            'id = ?',
            [$id],
        );
    }

    // -------------------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------------------

    /**
     * Bulk-insert or update discovered queues for a router.
     *
     * @param  int                            $routerId
     * @param  array<int,array<string,mixed>> $queues
     * @return int  Rows inserted.
     */
    public function bulkInsert(int $routerId, array $queues): int
    {
        if ($queues === []) {
            return 0;
        }

        $inserted = 0;

        $this->db->beginTransaction();

        try {
            foreach ($queues as $queue) {
                $queue['router_id'] = $routerId;

                $existing = $this->db->fetch(
                    'SELECT `id` FROM `simple_queues`
                      WHERE `router_id` = ? AND `queue_index` = ?
                      LIMIT 1',
                    [$routerId, (int) $queue['queue_index']],
                );

                if ($existing === null) {
                    $this->create($queue);
                    ++$inserted;
                } else {
                    $this->update((int) $existing['id'], $queue);
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
