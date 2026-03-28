<?php

declare(strict_types=1);

namespace NOC\Modules\Auth;

use NOC\Core\Database;
use NOC\Core\Logger;
use NOC\Core\Auth as CoreAuth;

/**
 * AuthService — Application-layer authentication service.
 *
 * Provides credential verification, rate limiting via the `login_attempts`
 * table, and login audit logging. Delegates password hashing and
 * session management to the core Auth class.
 *
 * @package NOC\Modules\Auth
 * @version 1.0.0
 */
final class AuthService
{
    /** Maximum failed attempts within the rate-limit window before blocking. */
    private const MAX_ATTEMPTS = 5;

    /** Rate-limit window in seconds (15 minutes). */
    private const WINDOW_SECONDS = 900;

    private readonly Database  $db;
    private readonly Logger    $logger;
    private readonly CoreAuth  $auth;

    public function __construct(
        ?Database  $db     = null,
        ?Logger    $logger = null,
        ?CoreAuth  $auth   = null,
    ) {
        $this->db     = $db     ?? Database::getInstance();
        $this->logger = $logger ?? Logger::getInstance();
        $this->auth   = $auth   ?? new CoreAuth($this->db, null, $this->logger);
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Verify credentials, enforce rate limiting, update last-login, and
     * write an audit record.
     *
     * @param  string $username
     * @param  string $password  Plain-text password.
     * @param  string $ip        Client IP address for rate-limit tracking.
     * @return bool   True on successful authentication.
     */
    public function authenticate(string $username, string $password, string $ip): bool
    {
        $username = trim($username);

        if ($this->isRateLimited($ip)) {
            $this->logger->warning('AuthService: login blocked by IP rate limit', [
                'ip'       => $ip,
                'username' => $username,
            ]);
            $this->logAttempt($username, $ip, false);
            return false;
        }

        $success = $this->auth->login($username, $password);

        $this->logAttempt($username, $ip, $success);

        if ($success) {
            $user = $this->getUserByUsername($username);

            if ($user !== null) {
                $this->updateLastLogin((int) $user['id']);
            }
        }

        return $success;
    }

    // -------------------------------------------------------------------------
    // User lookup
    // -------------------------------------------------------------------------

    /**
     * Return the user record for a given username, or null if not found.
     *
     * @param  string $username
     * @return array<string,mixed>|null
     */
    public function getUserByUsername(string $username): ?array
    {
        return $this->db->fetch(
            "SELECT `id`, `username`, `full_name`, `email`, `role`, `status`, `last_login`
               FROM `users`
              WHERE `username` = ?
                AND `status`   = 'active'
              LIMIT 1",
            [$username],
        );
    }

    /**
     * Update the last_login timestamp for a user.
     *
     * @param  int $userId
     * @return int  Rows affected.
     */
    public function updateLastLogin(int $userId): int
    {
        return $this->db->update(
            'users',
            ['last_login' => date('Y-m-d H:i:s')],
            'id = ?',
            [$userId],
        );
    }

    // -------------------------------------------------------------------------
    // Audit & rate limiting
    // -------------------------------------------------------------------------

    /**
     * Write a login attempt record to the `login_attempts` table.
     *
     * @param  string $username
     * @param  string $ip
     * @param  bool   $success
     */
    public function logAttempt(string $username, string $ip, bool $success): void
    {
        $this->db->insert('login_attempts', [
            'username'     => $username,
            'ip_address'   => $ip,
            'attempted_at' => date('Y-m-d H:i:s'),
            'success'      => (int) $success,
        ]);
    }

    /**
     * Check whether an IP address has exceeded the failed-login threshold
     * within the rate-limit window.
     *
     * @param  string $ip
     * @return bool   True if the IP is currently rate-limited.
     */
    public function isRateLimited(string $ip): bool
    {
        $count = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM `login_attempts`
              WHERE `ip_address`   = ?
                AND `success`      = 0
                AND `attempted_at` >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [$ip, self::WINDOW_SECONDS],
        );

        return $count >= self::MAX_ATTEMPTS;
    }
}
