<?php

declare(strict_types=1);

namespace NOC\Modules\Mrtg;

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\MRTG\MrtgConfigGenerator;

/**
 * MrtgService — Business-logic layer for the MRTG module.
 *
 * Delegates config generation to MrtgConfigGenerator and exposes
 * higher-level methods consumed by MrtgController.
 *
 * @package NOC\Modules\Mrtg
 * @version 1.0.0
 */
final class MrtgService
{
    private readonly MrtgModel           $model;
    private readonly MrtgConfigGenerator $generator;
    private readonly Database            $db;
    private readonly Logger              $logger;

    public function __construct(
        ?MrtgModel           $model     = null,
        ?MrtgConfigGenerator $generator = null,
        ?Database            $db        = null,
        ?Logger              $logger    = null,
    ) {
        $this->db        = $db        ?? Database::getInstance();
        $this->logger    = $logger    ?? Logger::getInstance();
        $this->model     = $model     ?? new MrtgModel();
        $this->generator = $generator ?? new MrtgConfigGenerator($this->db, $this->logger);
    }

    // -------------------------------------------------------------------------
    // Config generation
    // -------------------------------------------------------------------------

    /**
     * Generate MRTG config for a single router.
     *
     * @param  int $routerId
     * @return bool  True on success.
     */
    public function generateForRouter(int $routerId): bool
    {
        $ok = $this->generator->generateForRouter($routerId);

        $this->logger->info('MrtgService: generateForRouter', [
            'router_id' => $routerId,
            'result'    => $ok ? 'ok' : 'failed',
        ]);

        return $ok;
    }

    /**
     * Generate MRTG configs for all active routers.
     *
     * @return array{success:int, failed:int}
     */
    public function generateAll(): array
    {
        $result = $this->generator->generateAll();

        $this->logger->info('MrtgService: generateAll', $result);

        return $result;
    }

    // -------------------------------------------------------------------------
    // Config listing
    // -------------------------------------------------------------------------

    /**
     * Return all MRTG config records with router names.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getConfigList(): array
    {
        return $this->model->findAll();
    }
}
