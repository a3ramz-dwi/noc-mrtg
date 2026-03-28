<?php declare(strict_types=1);

namespace NOC\Core;

/**
 * Auth — authentication and authorisation manager.
 *
 * Handles login, logout, session-based auth, CSRF / API token generation,
 * bcrypt password hashing, and brute-force rate limiting.
 *
 * @package NOC\Core
 * @version 1.0.0
 */
final class Auth
{
    /** Maximum consecutive failures before the account is temporarily blocked. */
    private const MAX_ATTEMPTS = 5;

    /** Lock-out window in seconds (15 minutes). */
    private const LOCKOUT_WINDOW = 900;

    /** Session key that holds the authenticated user record. */
    private const SESSION_USER_KEY = '_auth_user';

    /** Session key tracking login attempt metadata. */
    private const SESSION_ATTEMPTS_KEY = '_auth_attempts';

    private readonly Database $db;
    private readonly Session  $session;
    private readonly Logger   $logger;

    public function __construct(
        ?Database $db      = null,
        ?Session  $session = null,
        ?Logger   $logger  = null,
    ) {
        $this->db      = $db      ?? Database::getInstance();
        $this->session = $session ?? new Session();
        $this->logger  = $logger  ?? Logger::getInstance();
    }

    // -----------------------------------------------------------------------
    // Login / Logout
    // -----------------------------------------------------------------------

    /**
     * Validate credentials and, on success, populate the session.
     *
     * @param  string $username
     * @param  string $password  Plain-text password.
     * @return bool
     */
    public function login(string $username, string $password): bool
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return false;
        }

        // Rate-limit check.
        if ($this->isRateLimited($username)) {
            $this->logger->warning('Login blocked (rate limit)', ['username' => $username]);
            return false;
        }

        // Fetch user record.
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ? AND status = 'active' LIMIT 1",
            [$username],
        );

        if ($user === null || !$this->verifyPassword($password, (string) $user['password_hash'])) {
            $this->recordFailedAttempt($username);
            $this->logger->warning('Failed login attempt', ['username' => $username]);
            return false;
        }

        // Success — clear attempts, regenerate session, store user.
        $this->clearAttempts($username);
        $this->session->regenerate();
        $this->session->set(self::SESSION_USER_KEY, [
            'id'         => $user['id'],
            'username'   => $user['username'],
            'email'      => $user['email']      ?? '',
            'role'       => $user['role']        ?? 'viewer',
            'full_name'  => $user['full_name']   ?? $username,
            'logged_at'  => time(),
        ]);

        // Persist last-login timestamp.
        $this->db->update(
            'users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$user['id']],
        );

        $this->logger->info('User logged in', ['username' => $username, 'id' => $user['id']]);

        return true;
    }

    /**
     * Destroy the current session and redirect to the login page.
     */
    public function logout(): void
    {
        $user = $this->getCurrentUser();

        if ($user !== null) {
            $this->logger->info('User logged out', ['username' => $user['username']]);
        }

        $this->session->destroy();
        $loginUrl = (defined('APP_URL') ? APP_URL : '') . '/login';
        header('Location: ' . $loginUrl);
        exit;
    }

    // -----------------------------------------------------------------------
    // Session checks
    // -----------------------------------------------------------------------

    /**
     * Return true if a user is currently authenticated.
     */
    public function isLoggedIn(): bool
    {
        $user = $this->session->get(self::SESSION_USER_KEY);

        if (!is_array($user) || empty($user['id'])) {
            return false;
        }

        // Sliding-window session: block sessions older than gc_maxlifetime.
        $maxLifetime = (int) ini_get('session.gc_maxlifetime') ?: 7200;
        if ((time() - (int) ($user['logged_at'] ?? 0)) > $maxLifetime) {
            $this->session->destroy();
            return false;
        }

        return true;
    }

    /**
     * Return the current user array, or null if not authenticated.
     *
     * @return array<string, mixed>|null
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $user = $this->session->get(self::SESSION_USER_KEY);
        return is_array($user) ? $user : null;
    }

    /**
     * Redirect to the login page if the visitor is not authenticated.
     * Stores the originally requested URL so it can be restored after login.
     */
    public function requireAuth(): void
    {
        if ($this->isLoggedIn()) {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->session->set('_auth_redirect', $requestUri);

        $loginUrl = (defined('APP_URL') ? APP_URL : '') . '/login';
        header('Location: ' . $loginUrl);
        exit;
    }

    // -----------------------------------------------------------------------
    // Password hashing
    // -----------------------------------------------------------------------

    /**
     * Hash a plain-text password using bcrypt.
     *
     * @param  string $password
     * @return string
     */
    public function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password.');
        }

        return $hash;
    }

    /**
     * Verify a plain-text password against a bcrypt hash.
     *
     * @param  string $password
     * @param  string $hash
     * @return bool
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // -----------------------------------------------------------------------
    // Token generation
    // -----------------------------------------------------------------------

    /**
     * Generate a cryptographically secure random token.
     *
     * @param  int $bytes  Number of random bytes (default: 32 → 64 hex chars).
     * @return string      Hex-encoded token.
     */
    public function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Verify a token against a stored expected value using a timing-safe compare.
     *
     * @param  string $token
     * @param  string $expected
     * @return bool
     */
    public function verifyToken(string $token, string $expected): bool
    {
        return hash_equals($expected, $token);
    }

    // -----------------------------------------------------------------------
    // Rate limiting (session-backed)
    // -----------------------------------------------------------------------

    /**
     * Return true if the username is currently rate-limited.
     *
     * @param  string $username
     * @return bool
     */
    public function isRateLimited(string $username): bool
    {
        $data = $this->getAttemptData($username);

        if ($data === null) {
            return false;
        }

        // Expire the window if enough time has passed.
        if ((time() - $data['first_at']) >= self::LOCKOUT_WINDOW) {
            $this->clearAttempts($username);
            return false;
        }

        return $data['count'] >= self::MAX_ATTEMPTS;
    }

    /**
     * Return the number of remaining login attempts for a username.
     *
     * @param  string $username
     * @return int
     */
    public function remainingAttempts(string $username): int
    {
        $data = $this->getAttemptData($username);

        if ($data === null) {
            return self::MAX_ATTEMPTS;
        }

        return max(0, self::MAX_ATTEMPTS - $data['count']);
    }

    /**
     * Return the UNIX timestamp when the lock-out expires, or null.
     */
    public function lockoutExpiresAt(string $username): ?int
    {
        $data = $this->getAttemptData($username);

        if ($data === null || $data['count'] < self::MAX_ATTEMPTS) {
            return null;
        }

        return $data['first_at'] + self::LOCKOUT_WINDOW;
    }

    // -----------------------------------------------------------------------
    // Private rate-limit helpers
    // -----------------------------------------------------------------------

    private function getAttemptData(string $username): ?array
    {
        $all = $this->session->get(self::SESSION_ATTEMPTS_KEY, []);

        if (!is_array($all) || !isset($all[$username])) {
            return null;
        }

        return $all[$username];
    }

    private function recordFailedAttempt(string $username): void
    {
        $all = $this->session->get(self::SESSION_ATTEMPTS_KEY, []);

        if (!is_array($all)) {
            $all = [];
        }

        if (!isset($all[$username])) {
            $all[$username] = ['count' => 0, 'first_at' => time()];
        }

        // Reset window if it has expired.
        if ((time() - $all[$username]['first_at']) >= self::LOCKOUT_WINDOW) {
            $all[$username] = ['count' => 0, 'first_at' => time()];
        }

        $all[$username]['count']++;
        $this->session->set(self::SESSION_ATTEMPTS_KEY, $all);
    }

    private function clearAttempts(string $username): void
    {
        $all = $this->session->get(self::SESSION_ATTEMPTS_KEY, []);

        if (is_array($all)) {
            unset($all[$username]);
            $this->session->set(self::SESSION_ATTEMPTS_KEY, $all);
        }
    }
}
