<?php

declare(strict_types=1);

namespace NOC\Modules\Settings;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth;

/**
 * SettingsController — HTTP request handler for application settings.
 *
 * @package NOC\Modules\Settings
 * @version 1.0.0
 */
final class SettingsController
{
    private readonly SettingsService $service;
    private readonly Session         $session;
    private readonly Auth            $auth;

    public function __construct(
        ?SettingsService $service = null,
        ?Session         $session = null,
        ?Auth            $auth    = null,
    ) {
        $this->service = $service ?? new SettingsService();
        $this->session = $session ?? new Session();
        $this->auth    = $auth    ?? new Auth();

        $this->auth->requireAuth();
    }

    /**
     * GET /settings — show settings page.
     */
    public function index(array $params = []): never
    {
        $settings = $this->service->getAll();
        $user     = $this->auth->getCurrentUser();

        Response::view('settings/index', [
            'pageTitle' => 'Settings',
            'settings'  => $settings,
            'user'      => $user,
            'csrf'      => $this->session->generateCsrfToken(),
            'flash'     => $this->session->getFlash(),
        ]);
    }

    /**
     * POST /settings — save settings.
     */
    public function update(array $params = []): never
    {
        $this->verifyCsrf();

        $data   = $_POST;
        $result = $this->service->saveAll($data);

        if (!$result['success']) {
            $this->session->setFlash('error', $result['message'] ?? 'Failed to save settings.');
            Response::redirect('/settings');
        }

        $this->session->setFlash('success', 'Settings saved successfully.');
        Response::redirect('/settings');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function verifyCsrf(): void
    {
        $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$this->session->verifyCsrfToken($token)) {
            $this->session->setFlash('error', 'Security token expired. Please try again.');
            Response::redirect('/settings');
        }
    }
}
