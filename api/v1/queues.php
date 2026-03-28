<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth.php';

$appDir = APP_DIR;
require_once $appDir . '/modules/queues/QueueModel.php';
require_once $appDir . '/modules/queues/QueueService.php';
require_once $appDir . '/modules/routers/RouterModel.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'] ?? [];
$body     = apiGetBody();
// segments: [0]=queues, [1]=id|discover, [2]=router_id|toggle-monitor

$seg1 = $segments[1] ?? null;
$seg2 = $segments[2] ?? null;

$db      = \NOC\Core\Database::getInstance();
$model   = new \NOC\Modules\Queues\QueueModel($db);
$service = new \NOC\Modules\Queues\QueueService($db, \NOC\Core\Logger::getInstance());

try {
    // POST /queues/discover/{router_id}
    if ($method === 'POST' && $seg1 === 'discover' && is_numeric($seg2)) {
        $routerId = (int)$seg2;
        $found = $service->discoverQueues($routerId);
        apiSuccess($found, 'Discovery completed');
    }

    // POST /queues/{id}/toggle-monitor
    if ($method === 'POST' && is_numeric($seg1) && $seg2 === 'toggle-monitor') {
        $id = (int)$seg1;
        $q  = $model->findById($id);
        if (!$q) apiError('Queue not found', 404);
        $newVal = $q['monitored'] ? 0 : 1;
        $model->setMonitored($id, (bool)$newVal);
        apiSuccess(['monitored' => (bool)$newVal], 'Monitoring updated');
    }

    if ($method === 'GET' && $seg1 === null) {
        $routerId = isset($_GET['router_id']) ? (int)$_GET['router_id'] : null;
        $list = $routerId ? $model->findByRouter($routerId) : $db->fetchAll('SELECT q.*, r.name AS router_name FROM simple_queues q JOIN routers r ON r.id = q.router_id ORDER BY r.name, q.name');
        apiSuccess($list);
    }

    if ($method === 'GET' && is_numeric($seg1)) {
        $q = $model->findById((int)$seg1);
        if (!$q) apiError('Queue not found', 404);
        apiSuccess($q);
    }

    if ($method === 'POST' && $seg1 === null) {
        $required = ['router_id', 'name'];
        foreach ($required as $f) { if (empty($body[$f])) apiError("Field '$f' is required"); }
        $id = $model->create($body);
        apiSuccess($model->findById($id), 'Queue created', 201);
    }

    if ($method === 'PUT' && is_numeric($seg1)) {
        $id = (int)$seg1;
        if (!$model->findById($id)) apiError('Queue not found', 404);
        $model->update($id, $body);
        apiSuccess($model->findById($id), 'Queue updated');
    }

    if ($method === 'DELETE' && is_numeric($seg1)) {
        $id = (int)$seg1;
        if (!$model->findById($id)) apiError('Queue not found', 404);
        $model->delete($id);
        apiSuccess(null, 'Queue deleted');
    }

    apiError('Not found', 404);
} catch (\Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
