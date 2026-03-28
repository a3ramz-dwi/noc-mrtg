<?php

declare(strict_types=1);

namespace NOC\Modules\Monitoring;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * MonitoringController — HTTP request handler for the Monitoring module.
 *
 * Serves both HTML pages for the monitoring UI and AJAX JSON endpoints
 * consumed by the live dashboard and chart widgets.
 *
 * @package NOC\Modules\Monitoring
 * @version 1.0.0
 */
final class MonitoringController
{
    private readonly MonitoringService $service;
    private readonly Session           $session;

    public function __construct(
        ?MonitoringService $service = null,
        ?Session           $session = null,
        ?Auth              $auth    = null,
    ) {
        $this->service = $service ?? new MonitoringService();
        $this->session = $session ?? new Session();

        ($auth ?? new Auth())->requireAuth();
    }

    // -------------------------------------------------------------------------
    // HTML pages
    // -------------------------------------------------------------------------

    /**
     * GET /monitoring/interfaces — interface monitoring page.
     */
    public function interfaces(): never
    {
        Response::view('monitoring/interfaces', []);
    }

    /**
     * GET /monitoring/queues — queue monitoring page.
     */
    public function queues(): never
    {
        Response::view('monitoring/queues', []);
    }

    /**
     * GET /monitoring/pppoe — PPPoE monitoring page.
     */
    public function pppoe(): never
    {
        Response::view('monitoring/pppoe', []);
    }

    /**
     * GET /monitoring/live — live bandwidth view.
     */
    public function live(): never
    {
        Response::view('monitoring/live', []);
    }

    // -------------------------------------------------------------------------
    // AJAX endpoints
    // -------------------------------------------------------------------------

    /**
     * GET /monitoring/live-data — return live bandwidth for all monitored targets.
     */
    public function liveData(): never
    {
        $data = $this->service->getLiveBandwidthAll();
        Response::success($data);
    }

    /**
     * GET /monitoring/chart/{targetType}/{targetId}/{period}
     *
     * Return Chart.js-formatted datasets for a specific target and period.
     *
     * @param  string $targetType  'interface'|'queue'|'pppoe'
     * @param  int    $targetId
     * @param  string $period      'daily'|'weekly'|'monthly'|'raw'
     */
    public function chartData(string $targetType, int $targetId, string $period): never
    {
        $allowed = ['interface', 'queue', 'pppoe'];

        if (!in_array($targetType, $allowed, true)) {
            Response::error('Invalid target type. Must be interface, queue, or pppoe.', 422);
        }

        $allowedPeriods = ['daily', 'weekly', 'monthly', 'raw'];

        if (!in_array($period, $allowedPeriods, true)) {
            Response::error('Invalid period. Must be daily, weekly, monthly, or raw.', 422);
        }

        $chartData = $this->service->getChartData($targetType, $targetId, $period);

        Response::success($chartData);
    }

    /**
     * GET /monitoring/network-summary — AJAX: aggregated network summary for the dashboard.
     */
    public function networkSummary(): never
    {
        $model   = new MonitoringModel();
        $summary = $model->getNetworkSummary();

        Response::success($summary);
    }
}
