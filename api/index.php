<?php
declare(strict_types=1);

// API entry point - routes to appropriate v1 handler
$appDir = '/var/www/noc';
require_once $appDir . '/config/app.php';

// CORS headers
$appEnv = getenv('APP_ENV') ?: 'production';
$allowedOriginsEnv = getenv('CORS_ALLOWED_ORIGINS') ?: '';
$allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsEnv)));

if (!empty($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (!empty($allowedOrigins) && in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } elseif (empty($allowedOrigins) && $appEnv !== 'production') {
        // Fallback for non-production environments when no allowlist is configured
        header('Access-Control-Allow-Origin: *');
    }
} elseif ($appEnv !== 'production' && empty($allowedOrigins)) {
    // Non-production fallback when no Origin header is present and no allowlist is configured
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$requestUri    = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Strip query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove /api/v1 prefix
$path = preg_replace('#^/api/v1#', '', $path);
$path = trim($path, '/');

$segments = explode('/', $path);
$resource = $segments[0] ?? '';

// Route to resource file
$resourceFile = __DIR__ . '/v1/' . $resource . '.php';

if (empty($resource) || !file_exists($resourceFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found', 'path' => '/' . $path]);
    exit;
}

// Pass parsed segments and method
$GLOBALS['_API'] = [
    'method'   => $requestMethod,
    'path'     => $path,
    'segments' => $segments,
    'body'     => json_decode(file_get_contents('php://input'), true) ?? [],
];

require_once $resourceFile;
