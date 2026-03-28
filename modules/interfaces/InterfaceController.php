<?php

declare(strict_types=1);

namespace NOC\Modules\Interfaces;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * InterfaceController — HTTP request handler for the Interfaces module.
 *
 * @package NOC\Modules\Interfaces
 * @version 1.0.0
 */
final class InterfaceController
{
    private readonly InterfaceService $service;
    private readonly InterfaceModel   $model;
    private readonly Session          $session;

    public function __construct(
        ?InterfaceService $service = null,
        ?InterfaceModel   $model   = null,
        ?Session          $session = null,
        ?Auth             $auth    = null,
    ) {
        $this->service = $service ?? new InterfaceService();
        $this->model   = $model   ?? new InterfaceModel();
        $this->session = $session ?? new Session();

        ($auth ?? new Auth())->requireAuth();
    }

    // -------------------------------------------------------------------------
    // Listing & detail
    // -------------------------------------------------------------------------

    /**
     * GET /interfaces[?router_id=N] — list interfaces, optionally filtered.
     */
    public function index(?int $routerId = null): never
    {
        $routerId = $routerId ?? (int) ($_GET['router_id'] ?? 0) ?: null;

        $interfaces = $routerId !== null
            ? $this->model->findByRouter($routerId)
            : $this->model->getMonitored();

        if ($this->wantsJson()) {
            Response::success($interfaces);
        }

        Response::view('interfaces/index', [
            'interfaces' => $interfaces,
            'router_id'  => $routerId,
        ]);
    }

    /**
     * GET /interfaces/{id} — interface detail with traffic chart data.
     */
    public function show(int $id): never
    {
        $interface = $this->model->findById($id);

        if ($interface === null) {
            if ($this->wantsJson()) {
                Response::error('Interface not found.', 404);
            }
            $this->session->setFlash('error', 'Interface not found.');
            Response::redirect('/interfaces');
        }

        $period  = (string) ($_GET['period'] ?? 'daily');
        $traffic = $this->service->getTrafficData($id, $period);
        $stats   = $this->service->getInterfaceStats($id);
        $current = $this->service->getCurrentBandwidth($id);

        if ($this->wantsJson()) {
            Response::success([
                'interface' => $interface,
                'traffic'   => $traffic,
                'stats'     => $stats,
                'current'   => $current,
            ]);
        }

        Response::view('interfaces/show', [
            'interface' => $interface,
            'traffic'   => $traffic,
            'stats'     => $stats,
            'current'   => $current,
            'period'    => $period,
        ]);
    }

    // -------------------------------------------------------------------------
    // Discovery & import
    // -------------------------------------------------------------------------

    /**
     * GET /interfaces/discover/{routerId} — AJAX: discover interfaces via SNMP.
     */
    public function discover(int $routerId): never
    {
        $discovered = $this->service->discoverInterfaces($routerId);

        if ($discovered === false) {
            Response::error('Failed to discover interfaces. Check SNMP settings.', 503);
        }

        Response::success($discovered, count($discovered) . ' interfaces discovered.');
    }

    /**
     * POST /interfaces/import — import selected interfaces.
     *
     * Expects: router_id (int), if_indexes[] (array of int).
     */
    public function importSelected(): never
    {
        $this->verifyCsrf();

        $routerId  = (int) ($_POST['router_id'] ?? 0);
        $ifIndexes = array_map('intval', (array) ($_POST['if_indexes'] ?? []));

        if ($routerId <= 0) {
            Response::error('router_id is required.', 422);
        }

        if ($ifIndexes === []) {
            Response::error('No interfaces selected.', 422);
        }

        $count = $this->service->importInterfaces($routerId, $ifIndexes);

        Response::success(['imported' => $count], $count . ' interfaces imported.');
    }

    // -------------------------------------------------------------------------
    // Monitoring toggle
    // -------------------------------------------------------------------------

    /**
     * POST /interfaces/{id}/toggle — toggle monitoring on/off.
     */
    public function toggleMonitor(int $id): never
    {
        $this->verifyCsrf();

        $interface = $this->model->findById($id);

        if ($interface === null) {
            Response::error('Interface not found.', 404);
        }

        $newState = !(bool) $interface['monitored'];
        $this->model->setMonitored($id, $newState);

        Response::success(
            ['monitored' => $newState],
            'Monitoring ' . ($newState ? 'enabled' : 'disabled') . '.',
        );
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    /**
     * POST /interfaces/{id}/delete — delete an interface.
     */
    public function destroy(int $id): never
    {
        $this->verifyCsrf();

        $interface = $this->model->findById($id);

        if ($interface === null) {
            if ($this->wantsJson()) {
                Response::error('Interface not found.', 404);
            }
            $this->session->setFlash('error', 'Interface not found.');
            Response::redirect('/interfaces');
        }

        $this->model->delete($id);

        if ($this->wantsJson()) {
            Response::success(null, 'Interface deleted.');
        }

        $this->session->setFlash('success', 'Interface deleted.');
        Response::redirect('/interfaces?router_id=' . $interface['router_id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verifyCsrf(): void
    {
        $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$this->session->verifyCsrfToken($token)) {
            Response::error('Invalid or expired CSRF token.', 403);
        }
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest';
    }
}
