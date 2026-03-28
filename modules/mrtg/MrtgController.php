<?php

declare(strict_types=1);

namespace NOC\Modules\Mrtg;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * MrtgController — HTTP request handler for the MRTG module.
 *
 * Allows operators to generate, view, download, and delete MRTG
 * configuration files via the web interface.
 *
 * @package NOC\Modules\Mrtg
 * @version 1.0.0
 */
final class MrtgController
{
    private readonly MrtgService $service;
    private readonly MrtgModel   $model;
    private readonly Session     $session;

    public function __construct(
        ?MrtgService $service = null,
        ?MrtgModel   $model   = null,
        ?Session     $session = null,
        ?Auth        $auth    = null,
    ) {
        $this->service = $service ?? new MrtgService();
        $this->model   = $model   ?? new MrtgModel();
        $this->session = $session ?? new Session();

        ($auth ?? new Auth())->requireAuth();
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    /**
     * GET /mrtg — list all generated MRTG configurations.
     */
    public function index(): never
    {
        $configs = $this->service->getConfigList();

        if ($this->wantsJson()) {
            Response::success($configs);
        }

        Response::view('mrtg/index', [
            'configs'   => $configs,
            'pageTitle' => 'MRTG Manager',
            'csrf'      => $this->session->generateCsrfToken(),
            'success'   => $this->session->getFlash('success'),
            'error'     => $this->session->getFlash('error'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Generation
    // -------------------------------------------------------------------------

    /**
     * POST /mrtg/generate[/{routerId}] — generate MRTG config for one router.
     */
    public function generate(?int $routerId = null): never
    {
        $this->verifyCsrf();

        $routerId = $routerId ?? (int) ($_POST['router_id'] ?? 0);

        if ($routerId <= 0) {
            if ($this->wantsJson()) {
                Response::error('router_id is required.', 422);
            }
            $this->session->setFlash('error', 'Router ID is required.');
            Response::redirect('/mrtg');
        }

        $ok = $this->service->generateForRouter($routerId);

        if (!$ok) {
            if ($this->wantsJson()) {
                Response::error('Failed to generate MRTG config. Check logs.', 503);
            }
            $this->session->setFlash('error', 'Config generation failed. Check the application log.');
            Response::redirect('/mrtg');
        }

        if ($this->wantsJson()) {
            Response::success(null, 'MRTG config generated successfully.');
        }

        $this->session->setFlash('success', 'MRTG config generated successfully.');
        Response::redirect('/mrtg');
    }

    /**
     * POST /mrtg/generate-all — generate MRTG configs for all active routers.
     */
    public function generateAll(): never
    {
        $this->verifyCsrf();

        $result = $this->service->generateAll();

        if ($this->wantsJson()) {
            Response::success($result, sprintf(
                'Generation complete: %d succeeded, %d failed.',
                $result['success'],
                $result['failed'],
            ));
        }

        $this->session->setFlash(
            $result['failed'] === 0 ? 'success' : 'warning',
            sprintf(
                'Generation complete: %d succeeded, %d failed.',
                $result['success'],
                $result['failed'],
            ),
        );

        Response::redirect('/mrtg');
    }

    // -------------------------------------------------------------------------
    // View & download
    // -------------------------------------------------------------------------

    /**
     * GET /mrtg/{id}/view — display MRTG config content in the browser.
     */
    public function viewConfig(int $id): never
    {
        $config = $this->model->findById($id);

        if ($config === null) {
            if ($this->wantsJson()) {
                Response::error('Config not found.', 404);
            }
            $this->session->setFlash('error', 'Config not found.');
            Response::redirect('/mrtg');
        }

        if ($this->wantsJson()) {
            Response::success($config);
        }

        Response::view('mrtg/view', ['config' => $config]);
    }

    /**
     * GET /mrtg/{id}/download — force-download the config file.
     */
    public function download(int $id): never
    {
        $config = $this->model->findById($id);

        if ($config === null) {
            Response::error('Config not found.', 404);
        }

        $filename = basename((string) $config['filename']);
        $content  = (string) $config['config_content'];

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo $content;
        exit;
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    /**
     * POST /mrtg/{id}/delete — delete a MRTG config record.
     */
    public function deleteConfig(int $id): never
    {
        $this->verifyCsrf();

        $config = $this->model->findById($id);

        if ($config === null) {
            if ($this->wantsJson()) {
                Response::error('Config not found.', 404);
            }
            $this->session->setFlash('error', 'Config not found.');
            Response::redirect('/mrtg');
        }

        $this->model->deleteConfig($id);

        if ($this->wantsJson()) {
            Response::success(null, 'Config deleted.');
        }

        $this->session->setFlash('success', 'MRTG config deleted.');
        Response::redirect('/mrtg');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verifyCsrf(): void
    {
        $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$this->session->verifyCsrfToken($token)) {
            if ($this->wantsJson()) {
                Response::error('Invalid or expired CSRF token.', 403);
            }
            $this->session->setFlash('error', 'Security token expired. Please try again.');
            Response::redirect('/mrtg');
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
