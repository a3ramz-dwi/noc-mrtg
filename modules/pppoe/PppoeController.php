<?php

declare(strict_types=1);

namespace NOC\Modules\Pppoe;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;
use NOC\Modules\Routers\RouterModel;

/**
 * PppoeController — HTTP request handler for the PPPoE module.
 *
 * @package NOC\Modules\Pppoe
 * @version 1.0.0
 */
final class PppoeController
{
    private readonly PppoeService $service;
    private readonly PppoeModel   $model;
    private readonly Session      $session;

    public function __construct(
        ?PppoeService $service = null,
        ?PppoeModel   $model   = null,
        ?Session      $session = null,
        ?Auth         $auth    = null,
    ) {
        $this->service = $service ?? new PppoeService();
        $this->model   = $model   ?? new PppoeModel();
        $this->session = $session ?? new Session();

        ($auth ?? new Auth())->requireAuth();
    }

    // -------------------------------------------------------------------------
    // Listing & detail
    // -------------------------------------------------------------------------

    /**
     * GET /pppoe[?router_id=N] — list PPPoE sessions, optionally filtered.
     */
    public function index(?int $routerId = null): never
    {
        $routerId = $routerId ?? (int) ($_GET['router_id'] ?? 0) ?: null;

        $sessions = $routerId !== null
            ? $this->model->findByRouter($routerId)
            : $this->model->getMonitored();

        if ($this->wantsJson()) {
            Response::success($sessions);
        }

        $routers = (new RouterModel())->findAll();

        Response::view('pppoe/index', [
            'sessions'  => $sessions,
            'router_id' => $routerId,
            'routers'   => $routers,
        ]);
    }

    /**
     * GET /pppoe/{id} — PPPoE session detail with traffic chart data.
     */
    public function show(int $id): never
    {
        $session = $this->model->findById($id);

        if ($session === null) {
            if ($this->wantsJson()) {
                Response::error('PPPoE session not found.', 404);
            }
            $this->session->setFlash('error', 'PPPoE session not found.');
            Response::redirect('/pppoe');
        }

        $period  = (string) ($_GET['period'] ?? 'daily');
        $traffic = $this->service->getTrafficData($id, $period);
        $current = $this->service->getCurrentBandwidth($id);

        if ($this->wantsJson()) {
            Response::success([
                'session' => $session,
                'traffic' => $traffic,
                'current' => $current,
            ]);
        }

        Response::view('pppoe/show', [
            'session' => $session,
            'traffic' => $traffic,
            'current' => $current,
            'period'  => $period,
        ]);
    }

    // -------------------------------------------------------------------------
    // Discovery & sync
    // -------------------------------------------------------------------------

    /**
     * GET /routers/{id}/pppoe/discover — HTML page: discover PPPoE sessions via SNMP.
     */
    public function discoverPage(int $routerId): never
    {
        $router = (new RouterModel())->findById($routerId);

        if ($router === null) {
            $this->session->setFlash('error', 'Router not found.');
            Response::redirect('/pppoe');
        }

        $discovered = $this->service->discoverPppoe($routerId);

        $existing     = $this->model->findByRouter($routerId);
        $existingNames = array_flip(array_column($existing, 'name'));

        if (is_array($discovered)) {
            foreach ($discovered as &$u) {
                $u['imported'] = isset($existingNames[(string) $u['name']]);
            }
            unset($u);
        }

        Response::view('pppoe/discover', [
            'router'     => $router,
            'discovered' => is_array($discovered) ? $discovered : [],
            'error'      => $discovered === false ? 'SNMP discovery failed. Check router SNMP settings.' : null,
            'csrf'       => $this->session->generateCsrfToken(),
        ]);
    }

    /**
     * GET /pppoe/discover/{routerId} — AJAX: discover PPPoE sessions via SNMP.
     */
    public function discover(int $routerId): never
    {
        $discovered = $this->service->discoverPppoe($routerId);

        if ($discovered === false) {
            Response::error('Failed to discover PPPoE sessions. Check SNMP settings.', 503);
        }

        Response::success($discovered, count($discovered) . ' PPPoE sessions discovered.');
    }

    /**
     * POST /pppoe/sync/{routerId} — sync PPPoE sessions with SNMP data.
     */
    public function syncSessions(int $routerId): never
    {
        $this->verifyCsrf();

        $result = $this->service->syncPppoe($routerId);

        Response::success($result, sprintf(
            'Sync complete: %d upserted, %d disconnected.',
            $result['upserted'],
            $result['disconnected'],
        ));
    }

    /**
     * POST /pppoe/import — import selected PPPoE sessions by name.
     *
     * Expects: router_id (int), pppoe_names[] (array of strings).
     */
    public function importSelected(): never
    {
        $this->verifyCsrf();

        $routerId   = (int) ($_POST['router_id']   ?? 0);
        $pppoeNames = array_map('strval', (array) ($_POST['pppoe_names'] ?? []));

        if ($routerId <= 0) {
            Response::error('router_id is required.', 422);
        }

        if ($pppoeNames === []) {
            Response::error('No PPPoE sessions selected.', 422);
        }

        $discovered = $this->service->discoverPppoe($routerId);

        if ($discovered === false) {
            Response::error('Discovery failed. Cannot import sessions.', 503);
        }

        $toImport = array_filter(
            $discovered,
            static fn(array $u): bool => in_array((string) $u['name'], $pppoeNames, true),
        );

        foreach ($toImport as &$user) {
            $user['status']    = 'connected';
            $user['monitored'] = 1;
        }
        unset($user);

        $count = $this->model->bulkUpsert($routerId, array_values($toImport));

        Response::success(['imported' => $count], $count . ' PPPoE sessions imported.');
    }

    // -------------------------------------------------------------------------
    // Monitoring toggle
    // -------------------------------------------------------------------------

    /**
     * POST /pppoe/{id}/toggle — toggle monitoring on/off.
     */
    public function toggleMonitor(int $id): never
    {
        $this->verifyCsrf();

        $pppoe = $this->model->findById($id);

        if ($pppoe === null) {
            Response::error('PPPoE session not found.', 404);
        }

        $newState = !(bool) $pppoe['monitored'];
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
     * POST /pppoe/{id}/delete — delete a PPPoE session record.
     */
    public function destroy(int $id): never
    {
        $this->verifyCsrf();

        $pppoe = $this->model->findById($id);

        if ($pppoe === null) {
            if ($this->wantsJson()) {
                Response::error('PPPoE session not found.', 404);
            }
            $this->session->setFlash('error', 'PPPoE session not found.');
            Response::redirect('/pppoe');
        }

        $this->model->delete($id);

        if ($this->wantsJson()) {
            Response::success(null, 'PPPoE session deleted.');
        }

        $this->session->setFlash('success', 'PPPoE session deleted.');
        Response::redirect('/pppoe?router_id=' . $pppoe['router_id']);
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
