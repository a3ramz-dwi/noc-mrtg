<?php

declare(strict_types=1);

namespace NOC\Modules\Routers;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * RouterController — HTTP request handler for the Routers module.
 *
 * Relies on RouterService for all business logic. Returns HTML views
 * for browser requests and JSON for AJAX/API calls.
 *
 * @package NOC\Modules\Routers
 * @version 1.0.0
 */
final class RouterController
{
    private readonly RouterService $service;
    private readonly Session       $session;
    private readonly Auth          $auth;

    public function __construct(
        ?RouterService $service = null,
        ?Session       $session = null,
        ?Auth          $auth    = null,
    ) {
        $this->service = $service ?? new RouterService();
        $this->session = $session ?? new Session();
        $this->auth    = $auth    ?? new Auth();

        $this->auth->requireAuth();
    }

    // -------------------------------------------------------------------------
    // Listing & detail
    // -------------------------------------------------------------------------

    /**
     * GET /routers — list all routers.
     *
     * Returns HTML for browser requests, JSON when Accept header is
     * application/json.
     */
    public function index(): never
    {
        $routers = $this->service->listRouters();

        if ($this->wantsJson()) {
            Response::success($routers, 'Routers retrieved.');
        }

        Response::view('routers/index', ['routers' => $routers]);
    }

    /**
     * GET /routers/{id} — router detail page.
     */
    public function show(int $id): never
    {
        $router = $this->service->getRouterWithDetails($id);

        if ($router === null) {
            if ($this->wantsJson()) {
                Response::error('Router not found.', 404);
            }
            $this->session->setFlash('error', 'Router not found.');
            Response::redirect('/routers');
        }

        if ($this->wantsJson()) {
            Response::success($router);
        }

        Response::view('routers/show', ['router' => $router]);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    /**
     * GET /routers/create — show the create form.
     */
    public function create(): never
    {
        Response::view('routers/create', [
            'csrf' => $this->session->generateCsrfToken(),
        ]);
    }

    /**
     * POST /routers — create a new router.
     */
    public function store(): never
    {
        $this->verifyCsrf();

        $data   = $this->collectInput();
        $result = $this->service->createRouter($data);

        if (!$result['success']) {
            if ($this->wantsJson()) {
                Response::error('Validation failed.', 422, $result['errors'] ?? []);
            }
            $this->session->setFlash('errors', $result['errors'] ?? []);
            $this->session->setFlash('old', $data);
            Response::redirect('/routers/create');
        }

        if ($this->wantsJson()) {
            Response::success(['id' => $result['id']], 'Router created.', 201);
        }

        $this->session->setFlash('success', 'Router created successfully.');
        Response::redirect('/routers/' . $result['id']);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    /**
     * GET /routers/{id}/edit — show the edit form.
     */
    public function edit(int $id): never
    {
        $router = $this->service->getRouterWithDetails($id);

        if ($router === null) {
            $this->session->setFlash('error', 'Router not found.');
            Response::redirect('/routers');
        }

        Response::view('routers/edit', [
            'router' => $router,
            'csrf'   => $this->session->generateCsrfToken(),
        ]);
    }

    /**
     * POST /routers/{id} (method override PUT) — update a router.
     */
    public function update(int $id): never
    {
        $this->verifyCsrf();

        $data   = $this->collectInput();
        $result = $this->service->updateRouter($id, $data);

        if (!$result['success']) {
            if ($this->wantsJson()) {
                Response::error('Validation failed.', 422, $result['errors'] ?? []);
            }
            $this->session->setFlash('errors', $result['errors'] ?? []);
            Response::redirect('/routers/' . $id . '/edit');
        }

        if ($this->wantsJson()) {
            Response::success(null, 'Router updated.');
        }

        $this->session->setFlash('success', 'Router updated successfully.');
        Response::redirect('/routers/' . $id);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    /**
     * POST /routers/{id}/delete (method override DELETE) — delete a router.
     */
    public function destroy(int $id): never
    {
        $this->verifyCsrf();

        $deleted = $this->service->deleteRouter($id);

        if (!$deleted) {
            if ($this->wantsJson()) {
                Response::error('Router not found.', 404);
            }
            $this->session->setFlash('error', 'Router not found.');
            Response::redirect('/routers');
        }

        if ($this->wantsJson()) {
            Response::success(null, 'Router deleted.');
        }

        $this->session->setFlash('success', 'Router deleted.');
        Response::redirect('/routers');
    }

    // -------------------------------------------------------------------------
    // AJAX actions
    // -------------------------------------------------------------------------

    /**
     * POST /routers/{id}/test — AJAX: test SNMP connectivity.
     */
    public function testConnection(int $id): never
    {
        $router = (new RouterModel())->findById($id);

        if ($router === null) {
            Response::error('Router not found.', 404);
        }

        $ok = $this->service->testConnection($router);

        Response::success(
            ['reachable' => $ok],
            $ok ? 'Router is reachable.' : 'Router did not respond to SNMP.',
        );
    }

    /**
     * POST /routers/{id}/refresh — refresh system info via SNMP.
     */
    public function refreshInfo(int $id): never
    {
        $ok = $this->service->refreshSystemInfo($id);

        if (!$ok) {
            Response::error('Failed to refresh router info. Check SNMP settings.', 503);
        }

        $router = $this->service->getRouterWithDetails($id);

        Response::success($router, 'Router info refreshed.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function collectInput(): array
    {
        return [
            'name'           => trim((string) ($_POST['name']           ?? '')),
            'ip_address'     => trim((string) ($_POST['ip_address']     ?? '')),
            'snmp_community' => trim((string) ($_POST['snmp_community'] ?? 'public')),
            'snmp_version'   => trim((string) ($_POST['snmp_version']   ?? '2c')),
            'snmp_port'      => (int) ($_POST['snmp_port'] ?? 161),
            'username'       => trim((string) ($_POST['username']       ?? '')) ?: null,
            'password'       => ($_POST['password'] ?? '') !== '' ? $_POST['password'] : null,
            'status'         => trim((string) ($_POST['status']         ?? 'active')),
        ];
    }

    private function verifyCsrf(): void
    {
        $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$this->session->verifyCsrfToken($token)) {
            if ($this->wantsJson()) {
                Response::error('Invalid or expired CSRF token.', 403);
            }
            $this->session->setFlash('error', 'Security token expired. Please try again.');
            Response::redirect('/routers');
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
