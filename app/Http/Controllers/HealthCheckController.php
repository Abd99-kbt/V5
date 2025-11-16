<?php

namespace App\Http\Controllers;

use App\Services\PerformanceMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class HealthCheckController extends Controller
{
    protected PerformanceMonitor $performanceMonitor;

    public function __construct(PerformanceMonitor $performanceMonitor)
    {
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Basic health check endpoint
     */
    public function index(): JsonResponse
    {
        $health = $this->performanceMonitor->getHealthStatus();
        
        $statusCode = match($health['status']) {
            'healthy' => 200,
            'degraded' => 206, // Partial Content
            'unhealthy' => 503, // Service Unavailable
            default => 500
        };

        return response()->json($health, $statusCode);
    }

    /**
     * Detailed system metrics endpoint
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = [
                'timestamp' => now(),
                'system' => $this->performanceMonitor->getSystemMetrics(),
                'application' => [
                    'uptime' => $this->getApplicationUptime(),
                    'version' => app()->version(),
                    'environment' => app()->environment(),
                ],
                'database' => $this->getDatabaseMetrics(),
                'cache' => $this->getCacheMetrics(),
                'queue' => $this->getQueueMetrics(),
                'memory' => [
                    'usage' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                    'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
                ],
                'performance' => [
                    'response_times' => $this->getResponseTimeMetrics(),
                    'error_rates' => $this->getErrorRateMetrics(),
                ],
            ];

            return response()->json($metrics);
        } catch (\Exception $e) {
            Log::error('Failed to collect metrics', ['error' => $e->getMessage()]);
            
            return response()->json([
                'error' => 'Failed to collect metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Readiness probe for Kubernetes/Docker
     */
    public function readiness(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Database readiness check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ready';
        } catch (\Exception $e) {
            $checks['database'] = 'not_ready';
            $allHealthy = false;
        }

        // Redis readiness check
        try {
            Redis::ping();
            $checks['redis'] = 'ready';
        } catch (\Exception $e) {
            $checks['redis'] = 'not_ready';
            $allHealthy = false;
        }

        // Cache readiness check
        try {
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            if ($value !== 'ok') {
                throw new \Exception('Cache test failed');
            }
            Cache::forget('health_check');
            $checks['cache'] = 'ready';
        } catch (\Exception $e) {
            $checks['cache'] = 'not_ready';
            $allHealthy = false;
        }

        // Queue readiness check
        try {
            $queueSize = Queue::size();
            $checks['queue'] = 'ready';
        } catch (\Exception $e) {
            $checks['queue'] = 'not_ready';
            $allHealthy = false;
        }

        $status = $allHealthy ? 'ready' : 'not_ready';
        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now(),
        ], $statusCode);
    }

    /**
     * Liveness probe for Kubernetes/Docker
     */
    public function liveness(): JsonResponse
    {
        try {
            // Simple checks that should always pass if the application is alive
            $checks = [
                'application_responding' => true,
                'php_execution' => true,
                'timestamp' => now(),
            ];

            return response()->json([
                'status' => 'alive',
                'checks' => $checks,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'dead',
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Performance test endpoint
     */
    public function performanceTest(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $iterations = $request->get('iterations', 100);

        $results = [
            'test_type' => 'performance_benchmark',
            'iterations' => $iterations,
            'timestamp' => now(),
        ];

        // Database performance test
        $dbStart = microtime(true);
        for ($i = 0; $i < min($iterations, 10); $i++) {
            try {
                DB::select('SELECT 1');
            } catch (\Exception $e) {
                // Ignore for this test
            }
        }
        $results['database'] = [
            'avg_time_ms' => (microtime(true) - $dbStart) / min($iterations, 10) * 1000,
            'iterations' => min($iterations, 10),
        ];

        // Cache performance test
        $cacheStart = microtime(true);
        for ($i = 0; $i < min($iterations, 10); $i++) {
            try {
                Cache::put("perf_test_$i", "value_$i", 1);
                Cache::get("perf_test_$i");
                Cache::forget("perf_test_$i");
            } catch (\Exception $e) {
                // Ignore for this test
            }
        }
        $results['cache'] = [
            'avg_time_ms' => (microtime(true) - $cacheStart) / min($iterations, 10) * 1000,
            'iterations' => min($iterations, 10),
        ];

        // Memory test
        $memoryStart = memory_get_usage(true);
        $testData = str_repeat('x', 1024 * 100); // 100KB of data
        $results['memory'] = [
            'test_allocation_mb' => 0.1,
            'current_usage_mb' => round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2),
        ];

        $results['total_time_ms'] = (microtime(true) - $startTime) * 1000;

        return response()->json($results);
    }

    /**
     * Get application uptime
     */
    protected function getApplicationUptime(): int
    {
        // This would need to be implemented based on when the application started
        return time(); // Simplified
    }

    /**
     * Get database metrics
     */
    protected function getDatabaseMetrics(): array
    {
        try {
            $db = DB::connection();
            
            return [
                'connection_status' => 'connected',
                'query_count' => $this->getQueryCount(),
                'slow_queries' => $this->getSlowQueryCount(),
            ];
        } catch (\Exception $e) {
            return [
                'connection_status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache metrics
     */
    protected function getCacheMetrics(): array
    {
        try {
            $stats = [];
            
            // Test Redis if available
            if (extension_loaded('redis')) {
                $redis = Redis::connection();
                $info = $redis->info();
                $stats = [
                    'redis_connected' => true,
                    'redis_used_memory' => $info['used_memory'] ?? 0,
                    'redis_connected_clients' => $info['connected_clients'] ?? 0,
                    'redis_total_commands_processed' => $info['total_commands_processed'] ?? 0,
                ];
            }

            return array_merge([
                'driver' => config('cache.default'),
                'status' => 'operational',
            ], $stats);
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue metrics
     */
    protected function getQueueMetrics(): array
    {
        try {
            return [
                'connection' => config('queue.default'),
                'size' => Queue::size(),
                'failed_jobs' => $this->getFailedJobsCount(),
            ];
        } catch (\Exception $e) {
            return [
                'connection' => config('queue.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get response time metrics
     */
    protected function getResponseTimeMetrics(): array
    {
        // This would need to be implemented with actual data collection
        return [
            'avg_response_time_ms' => 0,
            'p95_response_time_ms' => 0,
            'p99_response_time_ms' => 0,
        ];
    }

    /**
     * Get error rate metrics
     */
    protected function getErrorRateMetrics(): array
    {
        // This would need to be implemented with actual error tracking
        return [
            'error_rate_percent' => 0,
            'total_requests' => 0,
            'total_errors' => 0,
        ];
    }

    /**
     * Get query count
     */
    protected function getQueryCount(): int
    {
        // This would need to be implemented with query logging
        return 0;
    }

    /**
     * Get slow query count
     */
    protected function getSlowQueryCount(): int
    {
        // This would need to be implemented with slow query logging
        return 0;
    }

    /**
     * Get failed jobs count
     */
    protected function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}