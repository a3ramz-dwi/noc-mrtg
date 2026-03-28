<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth.php';

use NOC\Modules\Routers\RouterService;
use NOC\Modules\Routers\RouterModel;

$appDir = defined('APP_DIR') ? APP_DIR : '/var/www/noc';
require_once $appDir . '/modules/routers/RouterModel.php';
require_once $appDir . '/modules/routers/RouterService.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'];
// segments[0] = 'routers', segments[1] = {id} or 'discover', segments[2] = action

$id     = isset($segments[1]) && is_numeric($segments[1]) ? (int) $segments[1] : null;
$action = $id !== null ? ($segments[2] ?? null) : ($segments[1] ?? null);

$service = new RouterService();

// POST /api/v1/routers/{id}/test
// POST /api/v1/routers/{id}/refresh
if ($method === 'POST' && $id !== null && in_array($action, ['test', 'refresh'], true)) {
    $router = (new RouterModel())->findById($id);
    if (!$router) {
        apiError('Router not found', 404);
    }
    if ($action === 'test') {
        $ok = $service->testConnection($router);
        apiSuccess(['reachable' => $ok], $ok ? 'SNMP connection successful' : 'SNMP connection failed');
    }
    // refresh
    $ok = $service->refreshSystemInfo($id);
    apiSuccess(['updated' => $ok], $ok ? 'System info refreshed' : 'Refresh failed');
}

switch ($method) {
    case 'GET':
        if ($id !== null) {
            $router = $service->getRouterWithDetails($id);
            if (!$router) {
                apiError('Router not found', 404);
            }
            apiSuccess($router);
        }
        $routers = $service->listRouters();
        apiSuccess($routers);
        break;

    case 'POST':
        $body   = apiGetBody();
        $errors = $service->validateRouterData($body);
        if ($errors) {
            apiError('Validation failed', 422, $errors);
        }
        try {
            $result = $service->createRouter($body);
            apiSuccess($result, 'Router created successfully', 201);
        } catch (\Throwable $e) {
            apiError('Failed to create router: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        if ($id === null) {
            apiError('Router ID required', 400);
        }
        $body   = apiGetBody();
        $errors = $service->validateRouterData($body, true);
        if ($errors) {
            apiError('Validation failed', 422, $errors);
        }
        try {
            $result = $service->updateRouter($id, $body);
            apiSuccess($result, 'Router updated successfully');
        } catch (\Throwable $e) {
            apiError('Failed to update router: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        if ($id === null) {
            apiError('Router ID required', 400);
        }
        try {
            $ok = $service->deleteRouter($id);
            if (!$ok) {
                apiError('Router not found or could not be deleted', 404);
            }
            apiSuccess(null, 'Router deleted successfully');
        } catch (\Throwable $e) {
            apiError('Failed to delete router: ' . $e->getMessage(), 500);
        }
        break;

    default:
        apiError('Method not allowed', 405);
}
