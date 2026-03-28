<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth.php';

$appDir = APP_DIR;
require_once $appDir . '/modules/pppoe/PppoeModel.php';
require_once $appDir . '/modules/pppoe/PppoeService.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'] ?? [];
$body     = apiGetBody();

$seg1 = $segments[1] ?? null;
$seg2 = $segments[2] ?? null;

$db      = \NOC\Core\Database::getInstance();
$model   = new \NOC\Modules\Pppoe\PppoeModel($db);
$service = new \NOC\Modules\Pppoe\PppoeService($db, \NOC\Core\Logger::getInstance());

try {
    if ($method === 'POST' && $seg1 === 'discover' && is_numeric($seg2)) {
        $found = $service->discoverPppoe((int)$seg2);
        apiSuccess($found, 'PPPoE discovery completed');
    }

    if ($method === 'POST' && is_numeric($seg1) && $seg2 === 'sync') {
        $service->syncPppoe((int)$seg1);
        apiSuccess(null, 'PPPoE sessions synced');
    }

    if ($method === 'POST' && is_numeric($seg1) && $seg2 === 'toggle-monitor') {
        $u = $model->findById((int)$seg1);
        if (!$u) apiError('PPPoE user not found', 404);
        $new = $u['monitored'] ? 0 : 1;
        $model->setMonitored((int)$seg1, (bool)$new);
        apiSuccess(['monitored' => (bool)$new], 'Monitoring updated');
    }

    if ($method === 'GET' && $seg1 === null) {
        $routerId = isset($_GET['router_id']) ? (int)$_GET['router_id'] : null;
        $list = $routerId
            ? $model->findByRouter($routerId)
            : $db->fetchAll('SELECT p.*, r.name AS router_name FROM pppoe_users p JOIN routers r ON r.id = p.router_id ORDER BY r.name, p.name');
        apiSuccess($list);
    }

    if ($method === 'GET' && is_numeric($seg1)) {
        $u = $model->findById((int)$seg1);
        if (!$u) apiError('PPPoE user not found', 404);
        apiSuccess($u);
    }

    if ($method === 'POST' && $seg1 === null) {
        foreach (['router_id', 'name'] as $f) { if (empty($body[$f])) apiError("Field '$f' is required"); }
        $id = $model->create($body);
        apiSuccess($model->findById($id), 'PPPoE user created', 201);
    }

    if ($method === 'PUT' && is_numeric($seg1)) {
        $id = (int)$seg1;
        if (!$model->findById($id)) apiError('PPPoE user not found', 404);
        $model->update($id, $body);
        apiSuccess($model->findById($id), 'PPPoE user updated');
    }

    if ($method === 'DELETE' && is_numeric($seg1)) {
        $id = (int)$seg1;
        if (!$model->findById($id)) apiError('PPPoE user not found', 404);
        $model->delete($id);
        apiSuccess(null, 'PPPoE user deleted');
    }

    apiError('Not found', 404);
} catch (\Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
