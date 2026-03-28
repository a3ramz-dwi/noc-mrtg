<?php
declare(strict_types=1);

$appDir = '/var/www/noc';
require $appDir . '/config/app.php';

use NOC\Core\Router;
use NOC\Core\Auth;
use NOC\Core\Session;
use NOC\Modules\Auth\AuthController;
use NOC\Modules\Dashboard\DashboardController;
use NOC\Modules\Routers\RouterController;
use NOC\Modules\Interfaces\InterfaceController;
use NOC\Modules\Queues\QueueController;
use NOC\Modules\Pppoe\PppoeController;
use NOC\Modules\Monitoring\MonitoringController;
use NOC\Modules\Mrtg\MrtgController;
use NOC\Modules\Settings\SettingsController;

// Start session
Auth::startSession();

$router = new Router();

// Auth routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);

// Router routes
$router->get('/routers', [RouterController::class, 'index']);
$router->post('/routers', [RouterController::class, 'store']);
$router->get('/routers/create', [RouterController::class, 'create']);
$router->get('/routers/{id}', [RouterController::class, 'show']);
$router->put('/routers/{id}', [RouterController::class, 'update']);
$router->delete('/routers/{id}', [RouterController::class, 'destroy']);
$router->get('/routers/{id}/edit', [RouterController::class, 'edit']);
$router->post('/routers/{id}/test', [RouterController::class, 'testConnection']);
$router->post('/routers/{id}/refresh', [RouterController::class, 'refreshInfo']);

// Interface routes
$router->get('/interfaces', [InterfaceController::class, 'index']);
$router->get('/interfaces/{id}', [InterfaceController::class, 'show']);
$router->get('/routers/{id}/interfaces/discover', [InterfaceController::class, 'discover']);
$router->post('/interfaces/import', [InterfaceController::class, 'importSelected']);
$router->post('/interfaces/{id}/toggle-monitor', [InterfaceController::class, 'toggleMonitor']);
$router->delete('/interfaces/{id}', [InterfaceController::class, 'destroy']);

// Queue routes
$router->get('/queues', [QueueController::class, 'index']);
$router->get('/queues/{id}', [QueueController::class, 'show']);
$router->get('/routers/{id}/queues/discover', [QueueController::class, 'discover']);
$router->post('/queues/import', [QueueController::class, 'importSelected']);
$router->post('/queues/{id}/toggle-monitor', [QueueController::class, 'toggleMonitor']);
$router->delete('/queues/{id}', [QueueController::class, 'destroy']);

// PPPoE routes
$router->get('/pppoe', [PppoeController::class, 'index']);
$router->get('/pppoe/{id}', [PppoeController::class, 'show']);
$router->get('/routers/{id}/pppoe/discover', [PppoeController::class, 'discover']);
$router->post('/pppoe/import', [PppoeController::class, 'importSelected']);
$router->post('/pppoe/{id}/toggle-monitor', [PppoeController::class, 'toggleMonitor']);
$router->delete('/pppoe/{id}', [PppoeController::class, 'destroy']);

// Monitoring routes
$router->get('/monitoring/interfaces', [MonitoringController::class, 'interfaces']);
$router->get('/monitoring/queues', [MonitoringController::class, 'queues']);
$router->get('/monitoring/pppoe', [MonitoringController::class, 'pppoe']);
$router->get('/monitoring/live', [MonitoringController::class, 'live']);
$router->get('/monitoring/live-data', [MonitoringController::class, 'liveData']);
$router->get('/monitoring/chart-data/{type}/{id}', [MonitoringController::class, 'chartData']);

// MRTG routes
$router->get('/mrtg', [MrtgController::class, 'index']);
$router->post('/mrtg/generate', [MrtgController::class, 'generate']);

// Settings
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings', [SettingsController::class, 'update']);

// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
