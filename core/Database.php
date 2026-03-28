<?php declare(strict_types=1);

namespace NOC\Core;

use PDO;
use PDOStatement;
use PDOException;

/**
 * Database — PDO singleton wrapper.
 *
 * Provides a thin, fluent layer over PDO with convenience methods for
 * querying, fetching, inserting, updating and deleting rows.
 * Automatically reconnects when the MySQL server has gone away.
 *
 * @package NOC\Core
 * @version 1.0.0
 */
final class Database
{
    /** Singleton instance */
    private static ?self $instance = null;

    /** Active PDO connection */
    private PDO $pdo;

    /** Connection configuration (merged from config/database.php) */
    private readonly array $config;

    // -----------------------------------------------------------------------
    // Singleton
    // -----------------------------------------------------------------------

    /**
     * Return (or create) the singleton instance.
     *
     * @param  array|null $config  Override connection config on first call.
     * @return static
     */
    public static function getInstance(?array $config = null): static
    {
        if (self::$instance === null) {
            self::$instance = new self($config ?? self::loadConfig());
        }

        return self::$instance;
    }

    /**
     * Reset the singleton (useful in tests or after a fatal reconnect failure).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    // -----------------------------------------------------------------------
    // Connection
    // -----------------------------------------------------------------------

    /**
     * Open a PDO connection using the stored configuration.
     *
     * @throws PDOException
     */
    private function connect(): void
    {
        $cfg = $this->config;

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host']    ?? 'localhost',
            $cfg['port']    ?? 3306,
            $cfg['dbname']  ?? '',
            $cfg['charset'] ?? 'utf8mb4',
        );

        $this->pdo = new PDO(
            $dsn,
            $cfg['username'] ?? '',
            $cfg['password'] ?? '',
            $cfg['options']  ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ],
        );
    }

    /**
     * Attempt to reconnect after a dropped connection.
     *
     * MySQL error 2006 (server has gone away) and 2013 (lost connection)
     * are the two most common cases that warrant a silent reconnect.
     */
    private function reconnectIfNeeded(PDOException $e): void
    {
        $reconnectCodes = ['2006', '2013', 'HY000'];

        if (in_array($e->getCode(), $reconnectCodes, true)) {
            $this->connect();
            return;
        }

        throw $e;
    }

    // -----------------------------------------------------------------------
    // Core execute helper
    // -----------------------------------------------------------------------

    /**
     * Prepare and execute a statement, reconnecting once on disconnect errors.
     *
     * @param  string  $sql
     * @param  array   $params
     * @return PDOStatement
     * @throws PDOException
     */
    private function run(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Attempt a single reconnect for connection-loss errors.
            $this->reconnectIfNeeded($e);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        }
    }

    // -----------------------------------------------------------------------
    // Public query API
    // -----------------------------------------------------------------------

    /**
     * Execute a raw SQL statement and return the PDOStatement.
     *
     * @param  string $sql
     * @param  array  $params
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        return $this->run($sql, $params);
    }

    /**
     * Fetch a single row as an associative array (or null if not found).
     *
     * @param  string     $sql
     * @param  array      $params
     * @return array|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all matching rows as an array of associative arrays.
     *
     * @param  string $sql
     * @param  array  $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch the value of the first column of the first row.
     *
     * @param  string $sql
     * @param  array  $params
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $result = $this->run($sql, $params)->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Execute a statement and return the number of affected rows.
     *
     * @param  string $sql
     * @param  array  $params
     * @return int
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    // -----------------------------------------------------------------------
    // Convenience CRUD helpers
    // -----------------------------------------------------------------------

    /**
     * Insert a row into $table and return the last insert ID.
     *
     * @param  string $table
     * @param  array  $data   Associative array of column => value.
     * @return string         Last insert ID (string as returned by PDO).
     * @throws \InvalidArgumentException
     */
    public function insert(string $table, array $data): string
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty.');
        }

        $table   = $this->quoteIdentifier($table);
        $columns = implode(', ', array_map([$this, 'quoteIdentifier'], array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->run($sql, array_values($data));

        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows in $table and return the number of affected rows.
     *
     * @param  string $table
     * @param  array  $data         Associative array of column => value to SET.
     * @param  string $where        WHERE clause (e.g. "id = ?").
     * @param  array  $whereParams  Positional parameters for the WHERE clause.
     * @return int
     * @throws \InvalidArgumentException
     */
    public function update(
        string $table,
        array $data,
        string $where,
        array $whereParams = [],
    ): int {
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty.');
        }

        $table = $this->quoteIdentifier($table);
        $set   = implode(', ', array_map(
            fn (string $col) => $this->quoteIdentifier($col) . ' = ?',
            array_keys($data),
        ));

        $sql    = "UPDATE {$table} SET {$set} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);

        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Delete rows from $table and return the number of affected rows.
     *
     * @param  string $table
     * @param  string $where   WHERE clause (e.g. "id = ?").
     * @param  array  $params  Positional parameters for the WHERE clause.
     * @return int
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $table = $this->quoteIdentifier($table);
        return $this->run("DELETE FROM {$table} WHERE {$where}", $params)->rowCount();
    }

    // -----------------------------------------------------------------------
    // Transaction helpers
    // -----------------------------------------------------------------------

    /**
     * Begin a database transaction.
     */
    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    /**
     * Roll back the current transaction.
     */
    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // -----------------------------------------------------------------------
    // Misc
    // -----------------------------------------------------------------------

    /**
     * Return the last auto-generated ID inserted by an INSERT statement.
     *
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Expose the underlying PDO instance for edge-case use.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Wrap an identifier in back-ticks (MySQL style).
     * Strips any existing back-ticks to prevent injection.
     *
     * @param  string $identifier
     * @return string
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '', $identifier) . '`';
    }

    /**
     * Load database configuration from config/database.php.
     *
     * @return array
     */
    private static function loadConfig(): array
    {
        $configFile = dirname(__DIR__) . '/config/database.php';

        if (!is_file($configFile)) {
            return [];
        }

        return require $configFile;
    }
}
