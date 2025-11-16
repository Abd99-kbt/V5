<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;

class PerformanceMonitor
{
    protected array $metrics = [];
    protected float $startTime;
    protected array $queryMetrics = [];
    protected array $cacheMetrics = [];
    protected array $queueMetrics = [];

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->metrics = [
            'start_time' => now(),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true),
            'queries' => [],
            'cache_operations' => [],
            'queue_operations' => [],
            'system_info' => $this->getSystemInfo(),
        ];
    }

    /**
     * Record query execution time and metrics
     */
    public function recordQuery(string $query, float $time, array $bindings = []): void
    {
        $this->queryMetrics[] = [
            'query' => $query,
            'time' => $time,
            'bindings' => $bindings,
            'timestamp' => now(),
        ];
    }

    /**
     * Record cache operation metrics
     */
    public function recordCacheOperation(string $operation, string $key, bool $hit, int $ttl = null): void
    {
        $this->cacheMetrics[] = [
            'operation' => $operation,
            'key' => $key,
            'hit' => $hit,
            'ttl' => $ttl,
            'timestamp' => now(),
        ];
    }

    /**
     * Record queue operation metrics
     */
    public function recordQueueOperation(string $operation, string $queue, int $jobsProcessed = 0, float $processingTime = 0): void
    {
        $this->queueMetrics[] = [
            'operation' => $operation,
            'queue' => $queue,
            'jobs_processed' => $jobsProcessed,
            'processing_time' => $processingTime,
            'timestamp' => now(),
        ];
    }

    /**
     * Get current performance metrics
     */
    public function getMetrics(): array
    {
        $endTime = microtime(true);
        
        return array_merge($this->metrics, [
            'end_time' => now(),
            'total_execution_time' => $endTime - $this->startTime,
            'memory_peak' => memory_get_peak_usage(true),
            'memory_current' => memory_get_usage(true),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'query_metrics' => $this->queryMetrics,
            'cache_metrics' => $this->cacheMetrics,
            'queue_metrics' => $this->queueMetrics,
            'query_count' => count($this->queryMetrics),
            'total_query_time' => array_sum(array_column($this->queryMetrics, 'time')),
            'cache_hit_rate' => $this->calculateCacheHitRate(),
            'average_query_time' => $this->calculateAverageQueryTime(),
        ]);
    }

    /**
     * Store performance metrics for analysis
     */
    public function storeMetrics(string $endpoint = null): void
    {
        $metrics = $this->getMetrics();
        $metrics['endpoint'] = $endpoint;
        $metrics['request_id'] = request()->header('X-Request-ID', uniqid());

        // Store in Redis for quick access
        Cache::put("metrics:performance:" . $metrics['request_id'], $metrics, now()->addHours(24));

        // Store in database for long-term analysis
        $this->storeMetricsInDatabase($metrics);

        // Check for performance alerts
        $this->checkPerformanceAlerts($metrics);
    }

    /**
     * Get system performance metrics
     */
    public function getSystemMetrics(): array
    {
        return [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'network' => $this->getNetworkStats(),
            'load_average' => sys_getloadavg(),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get application health status
     */
    public function getHealthStatus(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now(),
            'checks' => [],
        ];

        // Database health check
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $dbTime = microtime(true) - $dbStart;
            
            $health['checks']['database'] = [
                'status' => $dbTime < 1.0 ? 'healthy' : 'warning',
                'response_time' => $dbTime,
                'message' => 'Database connection OK',
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        // Cache health check
        try {
            $cacheStart = microtime(true);
            Redis::ping();
            $cacheTime = microtime(true) - $cacheStart;
            
            $health['checks']['cache'] = [
                'status' => $cacheTime < 0.1 ? 'healthy' : 'warning',
                'response_time' => $cacheTime,
                'message' => 'Cache connection OK',
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        // Queue health check
        try {
            $queueStats = Queue::size();
            $health['checks']['queue'] = [
                'status' => $queueStats < 1000 ? 'healthy' : 'warning',
                'queue_size' => $queueStats,
                'message' => 'Queue processing normal',
            ];
        } catch (\Exception $e) {
            $health['checks']['queue'] = [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }

        // Memory usage check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        
        $memoryPercent = ($memoryUsage / $memoryLimitBytes) * 100;
        $health['checks']['memory'] = [
            'status' => $memoryPercent < 80 ? 'healthy' : ($memoryPercent < 90 ? 'warning' : 'unhealthy'),
            'usage_percent' => round($memoryPercent, 2),
            'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'limit_mb' => round($memoryLimitBytes / 1024 / 1024, 2),
        ];

        if ($memoryPercent >= 80) {
            $health['status'] = $memoryPercent >= 90 ? 'unhealthy' : 'degraded';
        }

        return $health;
    }

    /**
     * Store metrics in database for long-term analysis
     */
    protected function storeMetricsInDatabase(array $metrics): void
    {
        try {
            DB::table('performance_metrics')->insert([
                'request_id' => $metrics['request_id'],
                'endpoint' => $metrics['endpoint'],
                'execution_time' => $metrics['total_execution_time'],
                'memory_usage_mb' => $metrics['memory_current_mb'],
                'peak_memory_mb' => $metrics['memory_peak_mb'],
                'query_count' => $metrics['query_count'],
                'total_query_time' => $metrics['total_query_time'],
                'cache_hit_rate' => $metrics['cache_hit_rate'],
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to store performance metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check for performance alerts
     */
    protected function checkPerformanceAlerts(array $metrics): void
    {
        $alerts = [];

        // Response time alerts
        if ($metrics['total_execution_time'] > 5.0) {
            $alerts[] = [
                'type' => 'slow_response',
                'severity' => 'warning',
                'message' => 'Response time exceeded 5 seconds',
                'value' => $metrics['total_execution_time'],
            ];
        }

        if ($metrics['total_execution_time'] > 10.0) {
            $alerts[] = [
                'type' => 'very_slow_response',
                'severity' => 'critical',
                'message' => 'Response time exceeded 10 seconds',
                'value' => $metrics['total_execution_time'],
            ];
        }

        // Memory usage alerts
        if ($metrics['memory_current_mb'] > 512) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'severity' => 'warning',
                'message' => 'Memory usage exceeded 512MB',
                'value' => $metrics['memory_current_mb'],
            ];
        }

        // Query performance alerts
        if ($metrics['total_query_time'] > 2.0) {
            $alerts[] = [
                'type' => 'slow_database_queries',
                'severity' => 'warning',
                'message' => 'Total query time exceeded 2 seconds',
                'value' => $metrics['total_query_time'],
            ];
        }

        // Cache hit rate alerts
        if ($metrics['cache_hit_rate'] < 0.8) {
            $alerts[] = [
                'type' => 'low_cache_hit_rate',
                'severity' => 'warning',
                'message' => 'Cache hit rate below 80%',
                'value' => $metrics['cache_hit_rate'],
            ];
        }

        // Log alerts
        foreach ($alerts as $alert) {
            Log::warning('Performance Alert', $alert);
        }
    }

    /**
     * Calculate cache hit rate
     */
    protected function calculateCacheHitRate(): float
    {
        if (empty($this->cacheMetrics)) {
            return 0.0;
        }

        $hits = array_filter($this->cacheMetrics, fn($metric) => $metric['hit']);
        return count($hits) / count($this->cacheMetrics);
    }

    /**
     * Calculate average query time
     */
    protected function calculateAverageQueryTime(): float
    {
        if (empty($this->queryMetrics)) {
            return 0.0;
        }

        return array_sum(array_column($this->queryMetrics, 'time')) / count($this->queryMetrics);
    }

    /**
     * Get system information
     */
    protected function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operating_system' => PHP_OS,
            'php_memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    /**
     * Get CPU usage
     */
    protected function getCpuUsage(): array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                'load_1min' => $load[0] ?? 0,
                'load_5min' => $load[1] ?? 0,
                'load_15min' => $load[2] ?? 0,
            ];
        }
        return ['load_1min' => 0, 'load_5min' => 0, 'load_15min' => 0];
    }

    /**
     * Get memory usage
     */
    protected function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak' => memory_get_peak_usage(true),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'limit_mb' => round($this->parseMemoryLimit(ini_get('memory_limit')) / 1024 / 1024, 2),
        ];
    }

    /**
     * Get disk usage
     */
    protected function getDiskUsage(): array
    {
        $total = disk_total_space('.');
        $free = disk_free_space('.');
        
        return [
            'total' => $total,
            'total_mb' => round($total / 1024 / 1024, 2),
            'free' => $free,
            'free_mb' => round($free / 1024 / 1024, 2),
            'used_percent' => $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get network statistics
     */
    protected function getNetworkStats(): array
    {
        // This would need to be implemented based on the specific environment
        return ['status' => 'not_available'];
    }

    /**
     * Get system uptime
     */
    protected function getUptime(): int
    {
        if (function_exists('sys_getloadavg')) {
            // This is a simplified uptime calculation
            return time() - (int) @file_get_contents('/proc/uptime');
        }
        return 0;
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