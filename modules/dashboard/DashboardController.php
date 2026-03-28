<?php

declare(strict_types=1);

namespace NOC\Modules\Dashboard;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * DashboardController — Main NOC dashboard request handler.
 *
 * Serves the primary dashboard view and the realtime AJAX refresh
 * endpoint. All data aggregation is delegated to DashboardService.
 *
 * @package NOC\Modules\Dashboard
 * @version 1.0.0
 */
final class DashboardController
{
    private readonly DashboardService $service;
    private readonly Session          $session;

    public function __construct(
        ?DashboardService $service = null,
        ?Session          $session = null,
        ?Auth             $auth    = null,
    ) {
        $this->service = $service ?? new DashboardService();
        $this->session = $session ?? new Session();

        ($auth ?? new Auth())->requireAuth();
    }

    /**
     * GET / — main NOC dashboard.
     *
     * Renders the dashboard view with pre-loaded stats, or returns JSON
     * when requested via AJAX / API.
     */
    public function index(): never
    {
        $stats = $this->service->getDashboardStats();

        if ($this->wantsJson()) {
            Response::success($stats);
        }

        Response::view('dashboard/index', ['stats' => $stats]);
    }

    /**
     * GET /dashboard/realtime — AJAX endpoint for live stat refresh.
     */
    public function realtime(): never
    {
        $stats = $this->service->getRealtimeStats();
        Response::success($stats);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest';
    }
}
