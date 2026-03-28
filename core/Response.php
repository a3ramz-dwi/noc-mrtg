<?php declare(strict_types=1);

namespace NOC\Core;

/**
 * Response — static HTTP response helper.
 *
 * Provides factory methods for JSON API responses, redirects, and
 * PHP-template view rendering.  All public methods are static so they
 * can be called from anywhere without instantiation.
 *
 * @package NOC\Core
 * @version 1.0.0
 */
final class Response
{
    /** Base directory where view templates live. */
    private const VIEW_PATH = APP_DIR . '/views';

    // -----------------------------------------------------------------------
    // JSON responses
    // -----------------------------------------------------------------------

    /**
     * Send a raw JSON response and halt execution.
     *
     * @param  mixed $data    Anything JSON-serialisable.
     * @param  int   $status  HTTP status code (default 200).
     * @return never
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            $encoded = json_encode([
                'error' => 'JSON encoding failed: ' . json_last_error_msg(),
            ]);
        }

        echo $encoded;
        exit;
    }

    /**
     * Send a standardised success JSON response.
     *
     * @param  mixed  $data
     * @param  string $message
     * @param  int    $status
     * @return never
     */
    public static function success(
        mixed  $data    = null,
        string $message = 'OK',
        int    $status  = 200,
    ): never {
        static::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Send a standardised error JSON response.
     *
     * @param  string  $message
     * @param  int     $status
     * @param  array   $errors   Optional list of validation or detail errors.
     * @return never
     */
    public static function error(
        string $message,
        int    $status = 400,
        array  $errors = [],
    ): never {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        static::json($payload, $status);
    }

    // -----------------------------------------------------------------------
    // Redirect
    // -----------------------------------------------------------------------

    /**
     * Send an HTTP redirect and halt execution.
     *
     * @param  string $url
     * @param  int    $status  301, 302 (default), 303, 307, or 308.
     * @return never
     */
    public static function redirect(string $url, int $status = 302): never
    {
        // Basic safety: only allow relative paths and http(s) URLs.
        if (!preg_match('#^(https?://|/)#i', $url)) {
            $url = '/' . ltrim($url, '/');
        }

        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    // -----------------------------------------------------------------------
    // View rendering
    // -----------------------------------------------------------------------

    /**
     * Render a PHP template and send it as the response body.
     *
     * The template receives all $data keys as local variables.
     * A layout file (views/layouts/main.php) is used when it exists and
     * $layout is not explicitly set to false in $data.
     *
     * @param  string              $template  Relative path, e.g. 'routers/index'.
     * @param  array<string,mixed> $data      Variables extracted into template scope.
     * @param  int                 $status    HTTP status code.
     * @return never
     */
    public static function view(string $template, array $data = [], int $status = 200): never
    {
        $viewDir  = defined('APP_DIR') ? APP_DIR . '/views' : self::VIEW_PATH;
        $filePath = rtrim($viewDir, '/') . '/' . ltrim($template, '/') . '.php';

        if (!is_file($filePath)) {
            http_response_code(500);
            echo htmlspecialchars(
                "View template not found: {$template}",
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            );
            exit;
        }

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');

        // Extract data into scope so templates can use $varName directly.
        extract($data, EXTR_SKIP);

        // Capture the inner template output.
        ob_start();
        require $filePath;
        $content = ob_get_clean();

        // If a layout exists and $layout !== false, wrap the content.
        $useLayout  = $data['layout'] ?? true;
        $layoutFile = rtrim($viewDir, '/') . '/layouts/main.php';

        if ($useLayout && is_file($layoutFile)) {
            ob_start();
            require $layoutFile;
            echo ob_get_clean();
        } else {
            echo $content;
        }

        exit;
    }

    /**
     * Render a view to a string without sending it.
     *
     * @param  string              $template
     * @param  array<string,mixed> $data
     * @return string
     */
    public static function renderToString(string $template, array $data = []): string
    {
        $viewDir  = defined('APP_DIR') ? APP_DIR . '/views' : self::VIEW_PATH;
        $filePath = rtrim($viewDir, '/') . '/' . ltrim($template, '/') . '.php';

        if (!is_file($filePath)) {
            return '';
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $filePath;
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // Security headers helper
    // -----------------------------------------------------------------------

    /**
     * Apply a default set of security headers to the current response.
     *
     * Call this at the top of your front controller before any output.
     */
    public static function applySecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data:; "
            . "font-src 'self';"
        );
    }
}
