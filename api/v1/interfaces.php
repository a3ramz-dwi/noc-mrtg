<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth.php';

use NOC\Modules\Interfaces\InterfaceModel;
use NOC\Modules\Interfaces\InterfaceService;

$appDir = defined('APP_DIR') ? APP_DIR : '/var/www/noc';
require_once $appDir . '/modules/interfaces/InterfaceModel.php';
require_once $appDir . '/modules/interfaces/InterfaceService.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'];
// segments[0] = 'interfaces', segments[1] = {id}|'discover', segments[2] = {router_id}|'toggle-monitor'

$seg1   = $segments[1] ?? null;
$seg2   = $segments[2] ?? null;

$id       = is_numeric($seg1) ? (int) $seg1 : null;
$action   = $id !== null ? $seg2 : $seg1;
$routerId = ($action === 'discover' && is_numeric($seg2)) ? (int) $seg2 : null;

$model   = new InterfaceModel();
$service = new InterfaceService();

// POST /api/v1/interfaces/discover/{router_id}
if ($method === 'POST' && $action === 'discover' && $routerId !== null) {
    $discovered = $service->discoverInterfaces($routerId);
    if ($discovered === false) {
        apiError('SNMP discovery failed for router', 502);
    }
    apiSuccess($discovered, 'Interfaces discovered successfully');
}

// POST /api/v1/interfaces/{id}/toggle-monitor
if ($method === 'POST' && $id !== null && $action === 'toggle-monitor') {
    $iface = $model->findById($id);
    if (!$iface) {
        apiError('Interface not found', 404);
    }
    $newState = !(bool) $iface['monitored'];
    $model->setMonitored($id, $newState);
    apiSuccess(['id' => $id, 'monitored' => $newState], 'Monitor state updated');
}

switch ($method) {
    case 'GET':
        if ($id !== null) {
            $iface = $model->findById($id);
            if (!$iface) {
                apiError('Interface not found', 404);
            }
            apiSuccess($iface);
        }
        $filterRouterId = isset($_GET['router_id']) && is_numeric($_GET['router_id'])
            ? (int) $_GET['router_id']
            : null;
        if ($filterRouterId !== null) {
            $interfaces = $model->findByRouter($filterRouterId);
        } else {
            $interfaces = $model->getMonitored();
        }
        apiSuccess($interfaces);
        break;

    case 'POST':
        $body = apiGetBody();
        if (empty($body['router_id']) || empty($body['if_index'])) {
            apiError('Validation failed', 422, ['router_id' => 'Required', 'if_index' => 'Required']);
        }
        try {
            $newId  = $model->create($body);
            $result = $model->findById($newId);
            apiSuccess($result, 'Interface created successfully', 201);
        } catch (\Throwable $e) {
            apiError('Failed to create interface: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
        if ($id === null) {
            apiError('Interface ID required', 400);
        }
        $body = apiGetBody();
        if (!$model->findById($id)) {
            apiError('Interface not found', 404);
        }
        try {
            $model->update($id, $body);
            $result = $model->findById($id);
            apiSuccess($result, 'Interface updated successfully');
        } catch (\Throwable $e) {
            apiError('Failed to update interface: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        if ($id === null) {
            apiError('Interface ID required', 400);
        }
        if (!$model->findById($id)) {
            apiError('Interface not found', 404);
        }
        try {
            $model->delete($id);
            apiSuccess(null, 'Interface deleted successfully');
        } catch (\Throwable $e) {
            apiError('Failed to delete interface: ' . $e->getMessage(), 500);
        }
        break;

    default:
        apiError('Method not allowed', 405);
}
