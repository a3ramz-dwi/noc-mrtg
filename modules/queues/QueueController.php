<?php

declare(strict_types=1);

namespace NOC\Modules\Queues;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * QueueController — HTTP request handler for the Queues module.
 *
 * @package NOC\Modules\Queues
 * @version 1.0.0
 */
final class QueueController
{
    private readonly QueueService $service;
    private readonly QueueModel   $model;
    private readonly Session      $session;

    public function __construct(
        ?QueueService $service = null,
        ?QueueModel   $model   = null,
        ?Session      $session = null,
        ?Auth         $auth    = null,
    ) {
        $this->service = $service ?? new QueueService();
        $this->model   = $model   ?? new QueueModel();
        $this->session = $session ?? new Session();

        ($auth ?? new Auth())->requireAuth();
    }

    // -------------------------------------------------------------------------
    // Listing & detail
    // -------------------------------------------------------------------------

    /**
     * GET /queues[?router_id=N] — list queues, optionally filtered by router.
     */
    public function index(?int $routerId = null): never
    {
        $routerId = $routerId ?? (int) ($_GET['router_id'] ?? 0) ?: null;

        $queues = $routerId !== null
            ? $this->model->findByRouter($routerId)
            : $this->model->getMonitored();

        if ($this->wantsJson()) {
            Response::success($queues);
        }

        Response::view('queues/index', [
            'queues'    => $queues,
            'router_id' => $routerId,
        ]);
    }

    /**
     * GET /queues/{id} — queue detail with traffic chart data.
     */
    public function show(int $id): never
    {
        $queue = $this->model->findById($id);

        if ($queue === null) {
            if ($this->wantsJson()) {
                Response::error('Queue not found.', 404);
            }
            $this->session->setFlash('error', 'Queue not found.');
            Response::redirect('/queues');
        }

        $period  = (string) ($_GET['period'] ?? 'daily');
        $traffic = $this->service->getTrafficData($id, $period);
        $current = $this->service->getCurrentBandwidth($id);

        if ($this->wantsJson()) {
            Response::success([
                'queue'   => $queue,
                'traffic' => $traffic,
                'current' => $current,
            ]);
        }

        Response::view('queues/show', [
            'queue'   => $queue,
            'traffic' => $traffic,
            'current' => $current,
            'period'  => $period,
        ]);
    }

    // -------------------------------------------------------------------------
    // Discovery & import
    // -------------------------------------------------------------------------

    /**
     * GET /queues/discover/{routerId} — AJAX: discover queues via SNMP.
     */
    public function discover(int $routerId): never
    {
        $discovered = $this->service->discoverQueues($routerId);

        if ($discovered === false) {
            Response::error('Failed to discover queues. Check SNMP settings.', 503);
        }

        Response::success($discovered, count($discovered) . ' queues discovered.');
    }

    /**
     * POST /queues/import — import selected queues.
     *
     * Expects: router_id (int), queue_indexes[] (array of int).
     */
    public function importSelected(): never
    {
        $this->verifyCsrf();

        $routerId     = (int) ($_POST['router_id']     ?? 0);
        $queueIndexes = array_map('intval', (array) ($_POST['queue_indexes'] ?? []));

        if ($routerId <= 0) {
            Response::error('router_id is required.', 422);
        }

        if ($queueIndexes === []) {
            Response::error('No queues selected.', 422);
        }

        $count = $this->service->importQueues($routerId, $queueIndexes);

        Response::success(['imported' => $count], $count . ' queues imported.');
    }

    // -------------------------------------------------------------------------
    // Monitoring toggle
    // -------------------------------------------------------------------------

    /**
     * POST /queues/{id}/toggle — toggle monitoring on/off.
     */
    public function toggleMonitor(int $id): never
    {
        $this->verifyCsrf();

        $queue = $this->model->findById($id);

        if ($queue === null) {
            Response::error('Queue not found.', 404);
        }

        $newState = !(bool) $queue['monitored'];
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
     * POST /queues/{id}/delete — delete a queue.
     */
    public function destroy(int $id): never
    {
        $this->verifyCsrf();

        $queue = $this->model->findById($id);

        if ($queue === null) {
            if ($this->wantsJson()) {
                Response::error('Queue not found.', 404);
            }
            $this->session->setFlash('error', 'Queue not found.');
            Response::redirect('/queues');
        }

        $this->model->delete($id);

        if ($this->wantsJson()) {
            Response::success(null, 'Queue deleted.');
        }

        $this->session->setFlash('success', 'Queue deleted.');
        Response::redirect('/queues?router_id=' . $queue['router_id']);
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
