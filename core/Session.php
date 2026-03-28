<?php declare(strict_types=1);

namespace NOC\Core;

/**
 * Session — secure session lifecycle manager.
 *
 * Wraps PHP's native session functions with security hardening:
 * strict mode, HttpOnly / SameSite cookies, CSRF token management,
 * and flash messages.
 *
 * @package NOC\Core
 * @version 1.0.0
 */
final class Session
{
    /** Session key namespace for flash messages. */
    private const FLASH_KEY = '_flash';

    /** Session key for the CSRF token. */
    private const CSRF_KEY = '_csrf_token';

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    /**
     * Start (or resume) the session with hardened settings.
     *
     * Safe to call multiple times — a no-op if the session is already active.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Security settings (may be overridden by config/app.php ini_set calls,
        // but set them here too so the class is self-contained).
        session_set_cookie_params([
            'lifetime' => 0,           // session cookie (expires on browser close)
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_name('NOC_SESSION');
        session_start();

        // Rotate expired flash messages out on every request.
        $this->expireFlash();
    }

    /**
     * Destroy the session completely (data + cookie).
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Delete the client-side cookie.
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 3600,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Strict',
                ],
            );

            session_destroy();
        }
    }

    /**
     * Regenerate the session ID (call after privilege escalation / login).
     *
     * @param  bool $deleteOld  Whether to delete the old session file.
     */
    public function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    // -----------------------------------------------------------------------
    // Data access
    // -----------------------------------------------------------------------

    /**
     * Store a value in the session under the given key.
     *
     * @param  string $key
     * @param  mixed  $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve a value from the session, or $default if not present.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Return true if the session contains the given key.
     *
     * @param  string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a key from the session.
     *
     * @param  string $key
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Return all session data (excluding internal keys).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $this->ensureStarted();
        return array_filter(
            $_SESSION,
            static fn (string $k) => !str_starts_with($k, '_'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    // -----------------------------------------------------------------------
    // Flash messages
    // -----------------------------------------------------------------------

    /**
     * Store a flash message that will be available on the *next* request only.
     *
     * @param  string $key
     * @param  mixed  $message
     */
    public function setFlash(string $key, mixed $message): void
    {
        $this->ensureStarted();
        $_SESSION[self::FLASH_KEY]['new'][$key] = $message;
    }

    /**
     * Retrieve a flash message from the current request's flash bag.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[self::FLASH_KEY]['current'][$key] ?? $default;
    }

    /**
     * Return true if a flash message exists under $key for this request.
     *
     * @param  string $key
     * @return bool
     */
    public function hasFlash(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[self::FLASH_KEY]['current'][$key]);
    }

    /**
     * Move 'new' flash messages into 'current' and discard old 'current' ones.
     * Called automatically in start().
     */
    private function expireFlash(): void
    {
        $flash = $_SESSION[self::FLASH_KEY] ?? [];
        $_SESSION[self::FLASH_KEY] = [
            'current' => $flash['new'] ?? [],
            'new'     => [],
        ];
    }

    // -----------------------------------------------------------------------
    // CSRF protection
    // -----------------------------------------------------------------------

    /**
     * Return the current CSRF token, generating one if none exists.
     *
     * @return string  64-character hex token.
     */
    public function generateCsrfToken(): string
    {
        $this->ensureStarted();

        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_KEY];
    }

    /**
     * Verify a CSRF token submitted by the client using a timing-safe compare.
     *
     * @param  string $token
     * @return bool
     */
    public function verifyCsrfToken(string $token): bool
    {
        $this->ensureStarted();

        $stored = $_SESSION[self::CSRF_KEY] ?? '';

        if ($stored === '' || $token === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    /**
     * Rotate the CSRF token (call after successful form submission).
     *
     * @return string  The new token.
     */
    public function rotateCsrfToken(): string
    {
        $this->ensureStarted();
        unset($_SESSION[self::CSRF_KEY]);
        return $this->generateCsrfToken();
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Ensure the session is started before accessing $_SESSION.
     */
    private function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->start();
        }
    }

    /**
     * Return true if the current request is served over HTTPS.
     */
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }
}
