<?php declare(strict_types=1);

namespace NOC\Core;

/**
 * Logger — PSR-3-inspired file-based logging with automatic rotation.
 *
 * Writes structured log lines to /var/log/noc/app-{Y-m-d}.log and
 * prunes files older than 30 days on each instantiation.
 *
 * Format:
 *   [2025-01-01 12:00:00] [LEVEL] message {"context":"json"}
 *
 * @package NOC\Core
 * @version 1.0.0
 */
final class Logger
{
    /** Log severity levels. */
    public const DEBUG    = 'DEBUG';
    public const INFO     = 'INFO';
    public const WARNING  = 'WARNING';
    public const ERROR    = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    /** Number of daily log files to keep. */
    private const RETENTION_DAYS = 30;

    /** Ordered level weights used to filter by minimum level. */
    private const LEVEL_WEIGHTS = [
        self::DEBUG    => 0,
        self::INFO     => 1,
        self::WARNING  => 2,
        self::ERROR    => 3,
        self::CRITICAL => 4,
    ];

    private static ?self $instance = null;

    private readonly string $logDir;
    private readonly string $minimumLevel;

    // -----------------------------------------------------------------------
    // Singleton
    // -----------------------------------------------------------------------

    /**
     * Return the singleton Logger instance.
     *
     * @param  string|null $logDir        Override the log directory.
     * @param  string      $minimumLevel  Minimum level to write (default DEBUG).
     * @return static
     */
    public static function getInstance(
        ?string $logDir       = null,
        string  $minimumLevel = self::DEBUG,
    ): static {
        if (self::$instance === null) {
            self::$instance = new self(
                $logDir ?? (defined('LOG_DIR') ? LOG_DIR : '/var/log/noc'),
                $minimumLevel,
            );
        }

        return self::$instance;
    }

    /** Reset singleton (primarily for testing). */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    // -----------------------------------------------------------------------
    // Constructor
    // -----------------------------------------------------------------------

    private function __construct(string $logDir, string $minimumLevel)
    {
        $this->logDir       = rtrim($logDir, '/');
        $this->minimumLevel = $minimumLevel;

        $this->ensureLogDirectory();
        $this->rotateLogs();
    }

    // -----------------------------------------------------------------------
    // Public logging API
    // -----------------------------------------------------------------------

    /**
     * Write a log entry at the given level.
     *
     * @param  string               $level    One of the class constants.
     * @param  string               $message
     * @param  array<string, mixed> $context  Arbitrary key-value data.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);

        if (!$this->shouldLog($level)) {
            return;
        }

        $line = $this->format($level, $message, $context);
        $file = $this->logFilePath();

        // Use locking to prevent interleaving from concurrent processes.
        $handle = @fopen($file, 'ab');
        if ($handle === false) {
            // Fallback: try error_log so the message is never silently dropped.
            error_log("[{$level}] {$message}");
            return;
        }

        flock($handle, LOCK_EX);
        fwrite($handle, $line . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build the formatted log line.
     *
     * @param  string               $level
     * @param  string               $message
     * @param  array<string, mixed> $context
     * @return string
     */
    private function format(string $level, string $message, array $context): string
    {
        $timestamp   = date('Y-m-d H:i:s');
        $contextJson = empty($context)
            ? ''
            : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "[{$timestamp}] [{$level}] {$message}{$contextJson}";
    }

    /**
     * Absolute path to today's log file.
     */
    private function logFilePath(): string
    {
        return $this->logDir . '/app-' . date('Y-m-d') . '.log';
    }

    /**
     * Return true if the given level meets or exceeds the minimum threshold.
     */
    private function shouldLog(string $level): bool
    {
        $levelWeight = self::LEVEL_WEIGHTS[$level]           ?? 0;
        $minWeight   = self::LEVEL_WEIGHTS[$this->minimumLevel] ?? 0;
        return $levelWeight >= $minWeight;
    }

    /**
     * Create the log directory if it does not already exist.
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logDir)) {
            if (!@mkdir($this->logDir, 0750, true)) {
                // If we cannot create the directory, fall back silently.
                // Callers will get an error_log fallback on first write.
            }
        }
    }

    /**
     * Delete log files older than RETENTION_DAYS.
     * Runs at most once per process to avoid repeated stat calls.
     */
    private function rotateLogs(): void
    {
        if (!is_dir($this->logDir)) {
            return;
        }

        $cutoff = time() - (self::RETENTION_DAYS * 86400);

        $pattern = $this->logDir . '/app-*.log';
        $files   = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
