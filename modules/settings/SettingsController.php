<?php

declare(strict_types=1);

namespace NOC\Modules\Settings;

use NOC\Core\Auth;
use NOC\Core\Response;
use NOC\Core\Session;

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
     * GET /settings — display settings form.
     */
    public function index(): never
    {
        $settings = $this->service->getAll();

        Response::view('settings/index', [
            'pageTitle' => 'Settings',
            'settings'  => $settings,
            'success'   => $this->session->getFlash('success'),
            'error'     => $this->session->getFlash('error'),
            'csrf'      => $this->session->generateCsrfToken(),
        ]);
    }

    /**
     * POST /settings — persist settings.
     */
    public function update(): never
    {
        $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!$this->session->verifyCsrfToken($token)) {
            $this->session->setFlash('error', 'Security token expired. Please try again.');
            Response::redirect('/settings');
        }

        // Only collect whitelisted keys from POST
        $allowed = [
            'app_name', 'mrtg_dir', 'mrtg_cfg_dir', 'mrtg_bin',
            'snmp_community', 'snmp_version', 'snmp_timeout', 'snmp_retries', 'snmp_port',
            'log_dir',
        ];

        $data = [];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $data[$key] = trim((string) $_POST[$key]);
            }
        }

        $ok = $this->service->saveAll($data);

        if ($ok) {
            $this->session->setFlash('success', 'Settings saved successfully.');
        } else {
            $this->session->setFlash('error', 'Failed to save settings. Check the database connection.');
        }

        Response::redirect('/settings');
    }
}
