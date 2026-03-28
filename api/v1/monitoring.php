<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth.php';

$appDir = APP_DIR;
require_once $appDir . '/modules/monitoring/MonitoringModel.php';
require_once $appDir . '/modules/monitoring/MonitoringService.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'] ?? [];

// segments[0]=monitoring, [1]=action, [2]=type_or_id, [3]=id
$action = $segments[1] ?? null;
$seg2   = $segments[2] ?? null;
$seg3   = $segments[3] ?? null;

$db      = \NOC\Core\Database::getInstance();
$model   = new \NOC\Modules\Monitoring\MonitoringModel($db);
$service = new \NOC\Modules\Monitoring\MonitoringService($db, \NOC\Core\Logger::getInstance());

try {
    // GET /monitoring/traffic/{type}/{id}?period=daily
    if ($method === 'GET' && $action === 'traffic' && $seg2 && is_numeric($seg3)) {
        $period = $_GET['period'] ?? 'daily';
        $data   = $service->getChartData($seg2, (int)$seg3, $period);
        apiSuccess($data);
    }

    // GET /monitoring/live/{type}/{id}
    if ($method === 'GET' && $action === 'live' && $seg2 && is_numeric($seg3)) {
        $data = $service->getLiveData($seg2, (int)$seg3);
        apiSuccess($data);
    }

    // GET /monitoring/live  (all targets)
    if ($method === 'GET' && $action === 'live' && $seg2 === null) {
        apiSuccess($service->getLiveBandwidthAll());
    }

    // GET /monitoring/top-interfaces
    if ($method === 'GET' && $action === 'top-interfaces') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        apiSuccess($model->getTopTrafficTargets('interface', $limit));
    }

    // GET /monitoring/top-queues
    if ($method === 'GET' && $action === 'top-queues') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        apiSuccess($model->getTopTrafficTargets('queue', $limit));
    }

    // GET /monitoring/network-summary
    if ($method === 'GET' && $action === 'network-summary') {
        apiSuccess($model->getNetworkSummary());
    }

    // GET /monitoring/chart-data/{type}/{id}?period=daily
    if ($method === 'GET' && $action === 'chart-data' && $seg2 && is_numeric($seg3)) {
        $period = $_GET['period'] ?? 'daily';
        apiSuccess($service->getChartData($seg2, (int)$seg3, $period));
    }

    apiError('Not found', 404);
} catch (\Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
