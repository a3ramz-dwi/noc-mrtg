<?php

declare(strict_types=1);

namespace NOC\Modules\Auth;

use NOC\Core\Response;
use NOC\Core\Session;
use NOC\Core\Auth as CoreAuth;

/**
 * AuthController — HTTP request handler for login and logout.
 *
 * Delegates all credential verification and rate-limiting to AuthService.
 * CSRF tokens are generated and verified on every state-changing request.
 *
 * @package NOC\Modules\Auth
 * @version 1.0.0
 */
final class AuthController
{
    private readonly AuthService $service;
    private readonly Session     $session;
    private readonly CoreAuth    $coreAuth;

    public function __construct(
        ?AuthService $service  = null,
        ?Session     $session  = null,
        ?CoreAuth    $coreAuth = null,
    ) {
        $this->service  = $service  ?? new AuthService();
        $this->session  = $session  ?? new Session();
        $this->coreAuth = $coreAuth ?? new CoreAuth();

        $this->session->start();
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    /**
     * GET /login — display the login form.
     *
     * Redirects to the dashboard if the user is already authenticated.
     */
    public function showLogin(): never
    {
        if ($this->coreAuth->isLoggedIn()) {
            Response::redirect('/');
        }

        Response::view('auth/login', [
            'csrf'  => $this->session->generateCsrfToken(),
            'error' => $this->session->getFlash('error'),
        ]);
    }

    /**
     * POST /login — handle login form submission.
     *
     * Validates the CSRF token, applies rate-limit guard, authenticates
     * the user, and redirects on success or returns an error on failure.
     */
    public function login(): never
    {
        if ($this->coreAuth->isLoggedIn()) {
            Response::redirect('/');
        }

        // CSRF validation.
        $csrfToken = (string) ($_POST['_csrf'] ?? '');

        if (!$this->session->verifyCsrfToken($csrfToken)) {
            $this->session->setFlash('error', 'Security token expired. Please try again.');
            Response::redirect('/login');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->session->setFlash('error', 'Username and password are required.');
            Response::redirect('/login');
        }

        $ip = $this->resolveClientIp();

        // Check IP-level rate limit before attempting authentication.
        if ($this->service->isRateLimited($ip)) {
            $this->session->setFlash(
                'error',
                'Too many failed login attempts. Please wait 15 minutes before trying again.',
            );
            Response::redirect('/login');
        }

        $ok = $this->service->authenticate($username, $password, $ip);

        if (!$ok) {
            $this->session->setFlash('error', 'Invalid username or password.');
            Response::redirect('/login');
        }

        // Rotate CSRF token after successful login to prevent token fixation.
        $this->session->rotateCsrfToken();

        $redirect = (string) ($_SESSION['_intended'] ?? '/');
        unset($_SESSION['_intended']);

        Response::redirect($redirect);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    /**
     * POST /logout — destroy the session and redirect to the login page.
     *
     * Accepts both POST (with CSRF) and GET for convenience when called
     * from a link rather than a form.
     */
    public function logout(): never
    {
        // Only enforce CSRF on POST to support logout links.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

            if (!$this->session->verifyCsrfToken($token)) {
                $this->session->setFlash('error', 'Security token invalid.');
                Response::redirect('/');
            }
        }

        $this->coreAuth->logout();
        // coreAuth->logout() calls exit internally via Response::redirect.
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the real client IP, honouring X-Forwarded-For when behind a proxy.
     */
    private function resolveClientIp(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwarded !== '') {
            // Take the leftmost (client) address from the chain.
            $parts = explode(',', $forwarded);
            $ip    = trim($parts[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
