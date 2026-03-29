<?php

declare(strict_types=1);

namespace NOC\Modules\Routers;

use NOC\Core\Logger;
use NOC\SNMP\SnmpManager;
use NOC\Modules\Interfaces\InterfaceModel;

/**
 * RouterService — Business-logic layer for router management.
 *
 * Orchestrates RouterModel, SnmpManager, and Logger to provide
 * high-level operations consumed by RouterController.
 *
 * @package NOC\Modules\Routers
 * @version 1.0.0
 */
final class RouterService
{
    private readonly RouterModel    $model;
    private readonly Logger         $logger;
    private readonly InterfaceModel $interfaceModel;

    public function __construct(
        ?RouterModel    $model          = null,
        ?Logger         $logger         = null,
        ?InterfaceModel $interfaceModel = null,
    ) {
        $this->model          = $model          ?? new RouterModel();
        $this->logger         = $logger         ?? Logger::getInstance();
        $this->interfaceModel = $interfaceModel ?? new InterfaceModel();
    }

    // -------------------------------------------------------------------------
    // SNMP connectivity
    // -------------------------------------------------------------------------

    /**
     * Test whether the router responds to SNMP.
     *
     * @param  array<string,mixed> $router  Row from `routers` table.
     * @return bool
     */
    public function testConnection(array $router): bool
    {
        try {
            $snmp = new SnmpManager(
                (string) $router['ip_address'],
                (string) ($router['snmp_community'] ?? 'public'),
                (string) ($router['snmp_version']   ?? '2c'),
                5_000_000,
                1,
                (int) ($router['snmp_port'] ?? 161),
            );

            $result = $snmp->testConnection();

            $this->logger->info('Router SNMP test', [
                'router_id' => $router['id'] ?? 0,
                'ip'        => $router['ip_address'],
                'result'    => $result ? 'ok' : 'failed',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Router SNMP test exception', [
                'router_id' => $router['id'] ?? 0,
                'ip'        => $router['ip_address'] ?? '',
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Poll system information from the router via SNMP and persist it.
     *
     * Updates identity, model, router_os_version, and uptime. Sets the
     * router status to 'active' on success or 'error' on failure.
     *
     * @param  int $routerId
     * @return bool
     */
    public function refreshSystemInfo(int $routerId): bool
    {
        $router = $this->model->findById($routerId);

        if ($router === null) {
            $this->logger->warning('refreshSystemInfo: router not found', ['id' => $routerId]);
            return false;
        }

        try {
            $snmp = new SnmpManager(
                (string) $router['ip_address'],
                (string) ($router['snmp_community'] ?? 'public'),
                (string) ($router['snmp_version']   ?? '2c'),
                5_000_000,
                2,
                (int) ($router['snmp_port'] ?? 161),
            );

            $sysInfo = $snmp->getSystemInfo();

            if ($sysInfo === false) {
                $this->model->updateStatus($routerId, 'error');
                $this->logger->warning('refreshSystemInfo: SNMP getSystemInfo failed', ['id' => $routerId]);
                return false;
            }

            // sysName  → router identity/hostname.
            // sysDescr → full description string e.g. "RouterOS 7.12 (MIPSBE)".
            // sysUpTime is in hundredths of seconds (TimeTicks).
            $sysDescr = (string) ($sysInfo['sysDescr'] ?? '');

            // Extract RouterOS version from sysDescr (e.g. "RouterOS 7.12.1").
            $rosVersion = null;
            if (preg_match('/RouterOS\s+([\d.]+)/i', $sysDescr, $m)) {
                $rosVersion = $m[1];
            }

            $this->model->updateSystemInfo($routerId, [
                'identity'          => $sysInfo['sysName']   ?? null,
                'model'             => $sysDescr !== '' ? $sysDescr : null,
                'router_os_version' => $rosVersion,
                'uptime'            => isset($sysInfo['sysUpTime'])
                    ? (int) $sysInfo['sysUpTime']
                    : null,
            ]);

            $this->model->updateStatus($routerId, 'active');

            $this->logger->info('refreshSystemInfo: success', [
                'id'       => $routerId,
                'identity' => $sysInfo['sysName'] ?? '',
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->model->updateStatus($routerId, 'error');
            $this->logger->error('refreshSystemInfo: exception', [
                'id'    => $routerId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Read operations
    // -------------------------------------------------------------------------

    /**
     * Return a router row augmented with related-entity counts.
     *
     * Falls back to a plain findById() result if the stats query fails,
     * so the detail page still loads even without joined tables.
     *
     * @param  int $id
     * @return array<string,mixed>|null
     */
    public function getRouterWithDetails(int $id): ?array
    {
        try {
            $router = $this->model->findWithStats($id);
        } catch (\Throwable $e) {
            $this->logger->error('getRouterWithDetails: findWithStats failed, falling back to findById', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            $router = $this->model->findById($id);
        }

        if ($router === null) {
            return null;
        }

        // Ensure computed keys are always present with safe defaults.
        $router['interface_count']    ??= 0;
        $router['queue_count']        ??= 0;
        $router['pppoe_active_count'] ??= 0;
        $router['last_seen']          ??= $router['updated_at'] ?? null;

        // Attach the full interface records for the detail view.
        $router['interfaces'] = $this->interfaceModel->findByRouter($id);

        return $router;
    }

    /**
     * Return all routers with status information and interface counts.
     *
     * Falls back to findAll() when the stats query fails so that the
     * router list page still renders (without interface counts).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listRouters(): array
    {
        try {
            $routers = $this->model->findAllWithStats();
        } catch (\Throwable $e) {
            $this->logger->error('listRouters: findAllWithStats failed, falling back to findAll', [
                'error' => $e->getMessage(),
            ]);
            $routers = $this->model->findAll();
        }

        // Ensure computed keys are always present with safe defaults.
        return array_map(static function (array $router): array {
            $router['interface_count'] ??= 0;
            $router['last_seen']       ??= $router['updated_at'] ?? null;
            return $router;
        }, $routers);
    }

    // -------------------------------------------------------------------------
    // Write operations
    // -------------------------------------------------------------------------

    /**
     * Validate input and create a new router.
     *
     * @param  array<string,mixed>          $data
     * @return array{success:bool, id?:int, errors?:array<string,string>}
     */
    public function createRouter(array $data): array
    {
        $errors = $this->validateRouterData($data);

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $id = $this->model->create($data);

        $this->logger->info('Router created', ['id' => $id, 'ip' => $data['ip_address']]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Validate input and update an existing router.
     *
     * @param  int                          $id
     * @param  array<string,mixed>          $data
     * @return array{success:bool, errors?:array<string,string>}
     */
    public function updateRouter(int $id, array $data): array
    {
        if ($this->model->findById($id) === null) {
            return ['success' => false, 'errors' => ['id' => 'Router not found.']];
        }

        $errors = $this->validateRouterData($data, isUpdate: true);

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        $this->model->update($id, $data);

        $this->logger->info('Router updated', ['id' => $id]);

        return ['success' => true];
    }

    /**
     * Delete a router by ID.
     *
     * @param  int $id
     * @return bool
     */
    public function deleteRouter(int $id): bool
    {
        if ($this->model->findById($id) === null) {
            return false;
        }

        $this->model->delete($id);

        $this->logger->info('Router deleted', ['id' => $id]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate router input data.
     *
     * @param  array<string,mixed> $data
     * @param  bool                $isUpdate  Skip required-field checks on update.
     * @return array<string,string>           Field → error message map.
     */
    public function validateRouterData(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if (!$isUpdate || $name !== '') {
            if ($name === '') {
                $errors['name'] = 'Router name is required.';
            } elseif (mb_strlen($name) > 100) {
                $errors['name'] = 'Router name must not exceed 100 characters.';
            }
        }

        $ip = trim((string) ($data['ip_address'] ?? ''));
        if (!$isUpdate || $ip !== '') {
            if ($ip === '') {
                $errors['ip_address'] = 'IP address is required.';
            } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $errors['ip_address'] = 'IP address is not valid.';
            }
        }

        $community = trim((string) ($data['snmp_community'] ?? 'public'));
        if ($community !== '' && mb_strlen($community) > 128) {
            $errors['snmp_community'] = 'Community string must not exceed 128 characters.';
        }

        if (isset($data['snmp_version']) && !in_array($data['snmp_version'], ['1', '2c'], true)) {
            $errors['snmp_version'] = 'SNMP version must be 1 or 2c.';
        }

        $port = (int) ($data['snmp_port'] ?? 161);
        if ($port < 1 || $port > 65535) {
            $errors['snmp_port'] = 'SNMP port must be between 1 and 65535.';
        }

        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'error'], true)) {
            $errors['status'] = 'Status must be active, inactive, or error.';
        }

        return $errors;
    }
}
