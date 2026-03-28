<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth.php';

$appDir = APP_DIR;
require_once $appDir . '/modules/dashboard/DashboardService.php';

$user     = apiRequireAuth();
$method   = apiGetMethod();
$segments = $GLOBALS['_API']['segments'] ?? [];
$action   = $segments[1] ?? null;

$db      = \NOC\Core\Database::getInstance();
$service = new \NOC\Modules\Dashboard\DashboardService($db, \NOC\Core\Logger::getInstance());

try {
    if ($method === 'GET' && $action === 'stats') {
        apiSuccess($service->getDashboardStats());
    }

    if ($method === 'GET' && $action === 'realtime') {
        apiSuccess($service->getRealtimeStats());
    }

    if ($method === 'GET' && $action === 'router-status') {
        $rows = $db->fetchAll('SELECT id, name, ip_address, status, uptime, identity FROM routers ORDER BY name');
        apiSuccess($rows);
    }

    if ($method === 'GET' && $action === 'traffic-24h') {
        $from = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $rows = $db->fetchAll(
            'SELECT DATE_FORMAT(timestamp, \'%Y-%m-%d %H:%i\') AS ts,
                    SUM(bytes_in) AS bytes_in, SUM(bytes_out) AS bytes_out
             FROM traffic_data
             WHERE timestamp >= ?
             GROUP BY ts
             ORDER BY ts ASC',
            [$from]
        );
        apiSuccess($rows);
    }

    apiError('Not found', 404);
} catch (\Throwable $e) {
    apiError('Server error: ' . $e->getMessage(), 500);
}
