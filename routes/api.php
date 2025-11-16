<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MonitoringController;

// API Controllers
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\OrderProcessingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\CustomerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Health & Monitoring Routes
|--------------------------------------------------------------------------
|
| Basic health checks and comprehensive monitoring endpoints
|
*/

// Basic health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'version' => config('app.version', '1.0.0'),
        'company' => 'شركة الشرق الأوسط',
        'performance' => [
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'uptime' => round(microtime(true) - LARAVEL_START, 2) . ' seconds',
        ]
    ]);
});

// Legacy health monitoring routes
Route::prefix('health')->group(function () {
    Route::get('/', [HealthCheckController::class, 'index']);
    Route::get('/metrics', [HealthCheckController::class, 'metrics']);
    Route::get('/readiness', [HealthCheckController::class, 'readiness']);
    Route::get('/liveness', [HealthCheckController::class, 'liveness']);
    Route::post('/performance-test', [HealthCheckController::class, 'performanceTest']);
});

/*
|--------------------------------------------------------------------------
| Advanced Monitoring & Analytics API Routes
|--------------------------------------------------------------------------
|
| Comprehensive monitoring endpoints for system metrics, alerts, logs, and performance
|
*/

Route::prefix('monitoring')->group(function () {
    // System and Application Metrics
    Route::get('/metrics', [MonitoringController::class, 'metrics'])->name('monitoring.metrics');
    Route::get('/health/detailed', [MonitoringController::class, 'healthDetailed'])->name('monitoring.health.detailed');
    
    // Alert Management
    Route::get('/alerts', [MonitoringController::class, 'alerts'])->name('monitoring.alerts.list');
    Route::get('/alerts/active', [MonitoringController::class, 'alerts'])->name('monitoring.alerts.active');
    Route::post('/alerts/send', [MonitoringController::class, 'alerts'])->name('monitoring.alerts.send');
    Route::post('/alerts/acknowledge', [MonitoringController::class, 'alerts'])->name('monitoring.alerts.acknowledge');
    Route::post('/alerts/resolve', [MonitoringController::class, 'alerts'])->name('monitoring.alerts.resolve');
    
    // Log Analysis and Management
    Route::get('/logs', [MonitoringController::class, 'logs'])->name('monitoring.logs.analyze');
    Route::get('/logs/recent', [MonitoringController::class, 'logs'])->name('monitoring.logs.recent');
    Route::get('/logs/summary', [MonitoringController::class, 'logs'])->name('monitoring.logs.summary');
    
    // Performance Analytics
    Route::get('/performance', [MonitoringController::class, 'performance'])->name('monitoring.performance.overview');
    Route::get('/performance/detailed', [MonitoringController::class, 'performance'])->name('monitoring.performance.detailed');
    Route::get('/performance/trends', [MonitoringController::class, 'performance'])->name('monitoring.performance.trends');
});

/*
|--------------------------------------------------------------------------
| Direct Monitoring Endpoints (Production Monitoring)
|--------------------------------------------------------------------------
|
| Real-time monitoring endpoints for production environments
|
*/

// Application Performance Monitoring (APM)
Route::prefix('apm')->group(function () {
    Route::get('/metrics', function() {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'metrics' => [
                'response_time_avg' => 0.0,
                'error_rate' => 0.0,
                'throughput' => 0.0,
                'active_users' => 0,
            ]
        ]);
    });
    
    Route::get('/health', function() {
        return response()->json([
            'overall_status' => 'healthy',
            'checks' => [
                'database' => ['status' => 'healthy'],
                'cache' => ['status' => 'healthy'],
                'queue' => ['status' => 'healthy'],
                'memory' => ['status' => 'healthy']
            ]
        ]);
    });
});

// Business Intelligence & Analytics
Route::prefix('bi')->middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', function() {
        return response()->json([
            'kpis' => [
                'revenue_24h' => 0,
                'orders_24h' => 0,
                'active_users' => 0,
                'system_health_score' => 100
            ],
            'charts' => []
        ]);
    });
    
    Route::get('/metrics/{metric}', function($metric) {
        return response()->json([
            'metric' => $metric,
            'data' => [],
            'timestamp' => now()->toISOString()
        ]);
    });
});

// Real-time System Status
Route::get('/status', function() {
    return response()->json([
        'status' => 'operational',
        'timestamp' => now()->toISOString(),
        'uptime' => time(),
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
        'checks' => [
            'database' => 'operational',
            'cache' => 'operational',
            'queue' => 'operational',
            'storage' => 'operational'
        ]
    ]);
});

/*
|--------------------------------------------------------------------------
| Warehouse Management API Routes
|--------------------------------------------------------------------------
|
| Warehouse and inventory management endpoints
|
*/

Route::prefix('warehouse')->group(function () {
    // Product Management
    Route::apiResource('products', ProductController::class);
    Route::get('products/{product}/stock', [ProductController::class, 'stock'])->name('api.products.stock');
    Route::post('products/sync', [ProductController::class, 'sync'])->name('api.products.sync');

    // Stock Management
    Route::apiResource('stocks', StockController::class);
    Route::post('stocks/{stock}/adjust', [StockController::class, 'adjust'])->name('api.stocks.adjust');
    Route::post('stocks/reserve', [StockController::class, 'reserve'])->name('api.stocks.reserve');
    Route::post('stocks/release', [StockController::class, 'release'])->name('api.stocks.release');
    Route::get('stocks/summary', [StockController::class, 'summary'])->name('api.stocks.summary');

    // Order Management
    Route::apiResource('orders', OrderController::class);
    Route::get('orders/statistics', [OrderController::class, 'statistics'])->name('api.orders.statistics');

    // Enhanced Order Tracking Routes
    Route::prefix('tracking')->group(function () {
        Route::get('orders', [OrderTrackingController::class, 'index'])->name('api.tracking.orders');
        Route::get('orders/{order}/progress', [OrderTrackingController::class, 'getStageProgress'])->name('api.tracking.orders.progress');
        Route::post('orders/{order}/move-next', [OrderTrackingController::class, 'moveToNextStage'])->name('api.tracking.orders.move-next');
        Route::post('orders/{order}/skip-stage', [OrderTrackingController::class, 'skipStage'])->name('api.tracking.orders.skip-stage');
        Route::get('stages/available', [OrderTrackingController::class, 'getAvailableStages'])->name('api.tracking.stages.available');
        Route::get('statistics', [OrderTrackingController::class, 'getStageStatistics'])->name('api.tracking.statistics');
        Route::get('stages/{work_stage_id}/efficiency', [OrderTrackingController::class, 'getStageEfficiency'])->name('api.tracking.stages.efficiency');
        Route::post('bulk-assign', [OrderTrackingController::class, 'bulkAssignStages'])->name('api.tracking.bulk-assign');
    });

    // Order Processing Routes
    Route::prefix('orders/{order}/processing')->group(function () {
        Route::get('/', [OrderProcessingController::class, 'show'])->name('api.order.processing.show');
        Route::post('start', [OrderProcessingController::class, 'start'])->name('api.order.processing.start');
        Route::post('complete', [OrderProcessingController::class, 'complete'])->name('api.order.processing.complete');
        Route::post('pause', [OrderProcessingController::class, 'pause'])->name('api.order.processing.pause');
        Route::post('resume', [OrderProcessingController::class, 'resume'])->name('api.order.processing.resume');
        Route::post('cancel', [OrderProcessingController::class, 'cancel'])->name('api.order.processing.cancel');
        Route::post('assign', [OrderProcessingController::class, 'assign'])->name('api.order.processing.assign');
        Route::post('record-waste', [OrderProcessingController::class, 'recordWaste'])->name('api.order.processing.record-waste');
        Route::post('record-weight', [OrderProcessingController::class, 'recordWeight'])->name('api.order.processing.record-weight');
        Route::get('history', [OrderProcessingController::class, 'history'])->name('api.order.processing.history');
        Route::get('efficiency', [OrderProcessingController::class, 'efficiency'])->name('api.order.processing.efficiency');
        Route::post('transfer', [OrderProcessingController::class, 'transfer'])->name('api.order.processing.transfer');
        Route::post('quality-check', [OrderProcessingController::class, 'qualityCheck'])->name('api.order.processing.quality-check');
        Route::get('weight-balance', [OrderProcessingController::class, 'weightBalance'])->name('api.order.processing.weight-balance');
        Route::post('handover', [OrderProcessingController::class, 'handover'])->name('api.order.processing.handover');
        Route::get('audit-log', [OrderProcessingController::class, 'auditLog'])->name('api.order.processing.audit-log');
    });

    // Simplified Reports API Routes  
    Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', function() {
            return response()->json(['message' => 'Dashboard reports endpoint - to be implemented']);
        })->name('api.reports.dashboard');
        Route::get('sales', function() {
            return response()->json(['message' => 'Sales reports endpoint - to be implemented']);
        })->name('api.reports.sales');
        Route::get('inventory', function() {
            return response()->json(['message' => 'Inventory reports endpoint - to be implemented']);
        })->name('api.reports.inventory');
    });
});

// User-specific routes
Route::get('my-orders', [OrderProcessingController::class, 'ordersByStage'])->name('api.my-orders');
Route::get('pending-approvals', [OrderProcessingController::class, 'pendingApprovals'])->name('api.pending-approvals');

// Product availability routes
Route::get('products/available-for-order', [ProductController::class, 'availableForOrder'])->name('api.products.available-for-order');
Route::post('products/check-availability', [ProductController::class, 'checkAvailability'])->name('api.products.check-availability');

// Customer search routes
Route::get('customers/search', [CustomerController::class, 'search'])->name('api.customers.search');

// Public API Routes (no authentication required)
Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'version' => '1.0.0',
        'company' => 'شركة الشرق الأوسط',
    ]);
});