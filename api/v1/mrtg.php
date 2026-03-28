<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth.php';

$appDir = APP_DIR;
require_once $appDir . '/modules/mrtg/MrtgModel.php';
require_once $appDir . '/modules/mrtg/MrtgService.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'] ?? [];
$seg1     = $segments[1] ?? null;
$seg2     = $segments[2] ?? null;

$db      = \NOC\Core\Database::getInstance();
$model   = new \NOC\Modules\Mrtg\MrtgModel($db);
$service = new \NOC\Modules\Mrtg\MrtgService($model, null, $db, \NOC\Core\Logger::getInstance());

try {
    // POST /mrtg/generate-all
    if ($method === 'POST' && $seg1 === 'generate-all') {
        $result = $service->generateAll();
        apiSuccess($result, 'All MRTG configs generated');
    }

    // POST /mrtg/generate/{router_id}
    if ($method === 'POST' && $seg1 === 'generate' && is_numeric($seg2)) {
        $result = $service->generateForRouter((int)$seg2);
        apiSuccess($result, 'MRTG config generated');
    }

    // GET /mrtg/configs
    if ($method === 'GET' && ($seg1 === 'configs' || $seg1 === null) && $seg2 === null) {
        apiSuccess($service->getConfigList());
    }

    // GET /mrtg/configs/{id}
    if ($method === 'GET' && $seg1 === 'configs' && is_numeric($seg2)) {
        $cfg = $model->findById((int)$seg2);
        if (!$cfg) apiError('Config not found', 404);
        apiSuccess($cfg);
    }

    // DELETE /mrtg/configs/{id}
    if ($method === 'DELETE' && $seg1 === 'configs' && is_numeric($seg2)) {
        $id  = (int)$seg2;
        $cfg = $model->findById($id);
        if (!$cfg) apiError('Config not found', 404);
        $model->deleteConfig($id);
        apiSuccess(null, 'Config deleted');
    }

    apiError('Not found', 404);
} catch (\Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
