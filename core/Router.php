<?php declare(strict_types=1);

namespace NOC\Core;

/**
 * Router — lightweight HTTP request dispatcher.
 *
 * Supports static and parameterised routes ({param}), per-route middleware,
 * array [Controller::class, 'method'] handlers, and Closure handlers.
 *
 * @package NOC\Core
 * @version 1.0.0
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, handler: mixed, middleware: list<callable>}>> */
    private array $routes = [];

    /** @var callable|null */
    private mixed $notFoundHandler = null;

    /** @var callable|null */
    private mixed $methodNotAllowedHandler = null;

    /** Global middleware applied to every route. */
    private array $globalMiddleware = [];

    // -----------------------------------------------------------------------
    // Route registration
    // -----------------------------------------------------------------------

    /**
     * Register a GET route.
     *
     * @param  string          $path
     * @param  mixed           $handler    Closure or [Controller::class, 'method']
     * @param  list<callable>  $middleware  Per-route middleware stack.
     * @return static
     */
    public function get(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, mixed $handler, array $middleware = []): static
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Add a global middleware that runs for every matched route.
     *
     * @param  callable $middleware
     * @return static
     */
    public function addGlobalMiddleware(callable $middleware): static
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Register a custom 404 handler.
     *
     * @param  callable $handler
     * @return static
     */
    public function setNotFoundHandler(callable $handler): static
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Register a custom 405 handler.
     *
     * @param  callable $handler
     * @return static
     */
    public function setMethodNotAllowedHandler(callable $handler): static
    {
        $this->methodNotAllowedHandler = $handler;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Dispatch
    // -----------------------------------------------------------------------

    /**
     * Match the incoming request and invoke the appropriate handler.
     *
     * @param  string $method  HTTP verb (e.g. 'GET').
     * @param  string $uri     Request URI path (query string should be stripped).
     * @return void
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri    = '/' . trim(parse_url($uri, PHP_URL_PATH) ?? $uri, '/');

        $allowedMethods = [];

        foreach ($this->routes as $registeredMethod => $routes) {
            foreach ($routes as $route) {
                $params = $this->match($route['pattern'], $uri);

                if ($params === null) {
                    continue;
                }

                // URI matched — record the method as allowed.
                $allowedMethods[] = $registeredMethod;

                if ($registeredMethod !== $method) {
                    continue;
                }

                // Full match — run middleware then handler.
                $this->runMiddleware(
                    array_merge($this->globalMiddleware, $route['middleware']),
                    fn () => $this->callHandler($route['handler'], $params),
                );
                return;
            }
        }

        // URI matched but wrong HTTP method — 405
        if (!empty($allowedMethods)) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            $this->handleMethodNotAllowed($method, $uri, array_unique($allowedMethods));
            return;
        }

        // No match at all — 404
        $this->handleNotFound($method, $uri);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Store a route definition.
     */
    private function addRoute(
        string $method,
        string $path,
        mixed $handler,
        array $middleware,
    ): static {
        $this->routes[$method][] = [
            'pattern'    => $this->buildPattern($path),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * Convert a route path with {param} placeholders into a named-capture regex.
     *
     * @param  string $path  e.g. '/routers/{id}/interfaces/{ifIndex}'
     * @return string        PCRE pattern
     */
    private function buildPattern(string $path): string
    {
        $path    = '/' . trim($path, '/');
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn (array $m) => '(?P<' . $m[1] . '>[^/]+)',
            $path,
        );

        return '#^' . $pattern . '$#u';
    }

    /**
     * Try to match $uri against $pattern.
     *
     * @return array<string, string>|null  Named captures, or null on no match.
     */
    private function match(string $pattern, string $uri): ?array
    {
        if (preg_match($pattern, $uri, $matches) !== 1) {
            return null;
        }

        // Return only named captures (filter out integer-indexed entries).
        return array_filter(
            $matches,
            static fn ($k) => is_string($k),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Invoke the route handler with captured URL parameters.
     *
     * @param  mixed                  $handler  Closure or [ClassName, 'method'].
     * @param  array<string, string>  $params   URL parameters.
     */
    private function callHandler(mixed $handler, array $params): void
    {
        if ($handler instanceof \Closure) {
            $handler($params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                throw new \RuntimeException("Controller class '{$class}' not found.");
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException(
                    "Method '{$method}' not found on controller '{$class}'.",
                );
            }

            $controller->$method($params);
            return;
        }

        if (is_callable($handler)) {
            $handler($params);
            return;
        }

        throw new \InvalidArgumentException(
            'Route handler must be a Closure, callable, or [ClassName, \'method\'] array.',
        );
    }

    /**
     * Execute a middleware stack with a final action.
     *
     * @param  list<callable> $middleware
     * @param  callable       $core        The actual handler invocation.
     */
    private function runMiddleware(array $middleware, callable $core): void
    {
        if (empty($middleware)) {
            $core();
            return;
        }

        $next = $core;

        foreach (array_reverse($middleware) as $mw) {
            $next = static fn () => $mw($next);
        }

        $next();
    }

    /**
     * Invoke the 404 handler or fall back to a default response.
     */
    private function handleNotFound(string $method, string $uri): void
    {
        http_response_code(404);

        if ($this->notFoundHandler !== null) {
            ($this->notFoundHandler)($method, $uri);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Route not found.',
            'path'    => $uri,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Invoke the 405 handler or fall back to a default response.
     *
     * @param  list<string> $allowedMethods
     */
    private function handleMethodNotAllowed(string $method, string $uri, array $allowedMethods): void
    {
        http_response_code(405);

        if ($this->methodNotAllowedHandler !== null) {
            ($this->methodNotAllowedHandler)($method, $uri, $allowedMethods);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'         => false,
            'message'         => "Method '{$method}' not allowed for '{$uri}'.",
            'allowed_methods' => $allowedMethods,
        ], JSON_UNESCAPED_UNICODE);
    }
}
