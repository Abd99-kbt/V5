<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApplicationMonitor
{
    /**
     * Cache key prefix for application metrics
     */
    protected string $cachePrefix = 'app_monitor_';

    /**
     * Alert thresholds for application metrics
     */
    protected array $thresholds = [
        'response_time_ms' => 2000, // maximum response time
        'error_rate_percent' => 5.0, // maximum error rate
        'memory_usage_mb' => 512, // maximum memory usage
        'concurrent_requests' => 100, // maximum concurrent requests
        'queue_size' => 1000, // maximum queue size
        'database_connections' => 80, // percentage of max connections
        'cache_hit_rate_percent' => 80, // minimum cache hit rate
    ];

    /**
     * Store current request metrics
     */
    protected array $requestMetrics = [];

    /**
     * Get comprehensive application health status
     */
    public function getHealthStatus(): array
    {
        $status = [
            'overall' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => []
        ];

        try {
            // Performance monitoring
            $status['checks']['performance'] = $this->checkPerformanceMetrics();
            
            // Application health
            $status['checks']['application'] = $this->checkApplicationHealth();
            
            // Database performance
            $status['checks']['database'] = $this->checkDatabasePerformance();
            
            // Cache performance
            $status['checks']['cache'] = $this->checkCachePerformance();
            
            // Queue monitoring
            $status['checks']['queue'] = $this->checkQueuePerformance();
            
            // Security monitoring
            $status['checks']['security'] = $this->checkSecurityMetrics();
            
            // Business metrics
            $status['checks']['business'] = $this->checkBusinessMetrics();
            
            // API monitoring
            $status['checks']['api'] = $this->checkApiPerformance();

            // Determine overall status
            $criticalIssues = collect($status['checks'])->filter(fn($check) => $check['status'] === 'critical')->count();
            $warningIssues = collect($status['checks'])->filter(fn($check) => $check['status'] === 'warning')->count();
            
            if ($criticalIssues > 0) {
                $status['overall'] = 'critical';
            } elseif ($warningIssues > 0) {
                $status['overall'] = 'warning';
            }

            // Cache the health status
            $this->cacheHealthStatus($status);

            // Check for application alerts
            $this->checkApplicationAlerts($status);

        } catch (\Exception $e) {
            Log::error('Application health check failed', [
                'error' => $e->getMessage()
            ]);

            $status['overall'] = 'error';
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Check performance metrics
     */
    protected function checkPerformanceMetrics(): array
    {
        try {
            $metrics = $this->getPerformanceMetrics();
            $status = 'healthy';
            $issues = [];

            // Response time check
            if ($metrics['avg_response_time_ms'] > $this->thresholds['response_time_ms']) {
                $status = 'warning';
                $issues[] = "High average response time: {$metrics['avg_response_time_ms']}ms";
            }

            if ($metrics['avg_response_time_ms'] > $this->thresholds['response_time_ms'] * 2) {
                $status = 'critical';
                $issues[] = "Critical response time: {$metrics['avg_response_time_ms']}ms";
            }

            // Memory usage check
            $memoryUsageMB = $metrics['current_memory_mb'];
            if ($memoryUsageMB > $this->thresholds['memory_usage_mb']) {
                $status = 'warning';
                $issues[] = "High memory usage: {$memoryUsageMB}MB";
            }

            // Concurrent requests check
            $concurrentRequests = $metrics['concurrent_requests'];
            if ($concurrentRequests > $this->thresholds['concurrent_requests']) {
                $status = 'warning';
                $issues[] = "High concurrent requests: {$concurrentRequests}";
            }

            return [
                'status' => $status,
                'metrics' => $metrics,
                'issues' => $issues,
                'message' => empty($issues) ? 'Performance metrics normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Performance monitoring failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check overall application health
     */
    protected function checkApplicationHealth(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            // Check if application is responding
            $appResponse = $this->checkApplicationResponse();
            if (!$appResponse['responding']) {
                $status = 'critical';
                $issues[] = 'Application not responding to health checks';
            }

            // Check Laravel-specific health indicators
            $laravelHealth = $this->checkLaravelHealth();
            if ($laravelHealth['status'] !== 'healthy') {
                $status = $laravelHealth['status'];
                $issues = array_merge($issues, $laravelHealth['issues']);
            }

            return [
                'status' => $status,
                'metrics' => [
                    'app_responding' => $appResponse['responding'],
                    'response_time_ms' => $appResponse['response_time_ms'],
                    'laravel_health' => $laravelHealth,
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Application health normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Application health check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check database performance
     */
    protected function checkDatabasePerformance(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            // Check database connection
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $connectionTime = (microtime(true) - $dbStart) * 1000;

            if ($connectionTime > 1000) {
                $status = 'warning';
                $issues[] = "Slow database connection: {$connectionTime}ms";
            }

            // Check query statistics
            $queryStats = $this->getQueryStatistics();
            if ($queryStats['slow_query_count'] > 10) {
                $status = 'warning';
                $issues[] = "High slow query count: {$queryStats['slow_query_count']}";
            }

            // Check database connections
            $connectionStats = $this->getDatabaseConnectionStats();
            $connectionUsagePercent = ($connectionStats['active_connections'] / $connectionStats['max_connections']) * 100;
            
            if ($connectionUsagePercent > $this->thresholds['database_connections']) {
                $status = 'warning';
                $issues[] = "High database connection usage: {$connectionUsagePercent}%";
            }

            return [
                'status' => $status,
                'metrics' => [
                    'connection_time_ms' => round($connectionTime, 2),
                    'query_stats' => $queryStats,
                    'connection_stats' => $connectionStats,
                    'connection_usage_percent' => round($connectionUsagePercent, 2),
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Database performance normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'critical',
                'message' => 'Database performance check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check cache performance
     */
    protected function checkCachePerformance(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            // Check cache hit rate
            $cacheStats = $this->getCacheStatistics();
            $hitRate = $cacheStats['hit_rate'] ?? 0;

            if ($hitRate < $this->thresholds['cache_hit_rate_percent']) {
                $status = 'warning';
                $issues[] = "Low cache hit rate: {$hitRate}%";
            }

            // Check cache memory usage
            $memoryUsage = $cacheStats['memory_usage_mb'] ?? 0;
            if ($memoryUsage > 512) {
                $status = 'warning';
                $issues[] = "High cache memory usage: {$memoryUsage}MB";
            }

            return [
                'status' => $status,
                'metrics' => [
                    'hit_rate_percent' => round($hitRate, 2),
                    'memory_usage_mb' => $memoryUsage,
                    'cache_stats' => $cacheStats,
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Cache performance normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Cache performance check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check queue performance
     */
    protected function checkQueuePerformance(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            $queueSize = Queue::size();
            if ($queueSize > $this->thresholds['queue_size']) {
                $status = 'warning';
                $issues[] = "Large queue size: {$queueSize}";
            }

            if ($queueSize > $this->thresholds['queue_size'] * 2) {
                $status = 'critical';
                $issues[] = "Critical queue size: {$queueSize}";
            }

            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 50) {
                $status = 'warning';
                $issues[] = "High failed jobs count: {$failedJobs}";
            }

            return [
                'status' => $status,
                'metrics' => [
                    'queue_size' => $queueSize,
                    'failed_jobs' => $failedJobs,
                    'queue_connection' => config('queue.default'),
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Queue performance normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Queue performance check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check security metrics
     */
    protected function checkSecurityMetrics(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            // Check for recent security events
            $securityEvents = $this->getSecurityEvents();
            if (!empty($securityEvents)) {
                $status = 'warning';
                $issues[] = count($securityEvents) . ' security events detected';
            }

            // Check authentication failures
            $authFailures = $this->getRecentAuthFailures();
            if ($authFailures['count'] > 10) {
                $status = 'warning';
                $issues[] = "High authentication failures: {$authFailures['count']}";
            }

            // Check for suspicious activities
            $suspiciousActivities = $this->getSuspiciousActivities();
            if (!empty($suspiciousActivities)) {
                $status = 'critical';
                $issues[] = 'Suspicious activities detected';
            }

            return [
                'status' => $status,
                'metrics' => [
                    'security_events' => $securityEvents,
                    'auth_failures' => $authFailures,
                    'suspicious_activities' => $suspiciousActivities,
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Security metrics normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Security metrics check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check business metrics
     */
    protected function checkBusinessMetrics(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            // Check user activity
            $userMetrics = $this->getUserMetrics();
            if ($userMetrics['active_users_24h'] === 0) {
                $status = 'warning';
                $issues[] = 'No active users in last 24 hours';
            }

            // Check order processing
            $orderMetrics = $this->getOrderMetrics();
            if ($orderMetrics['failed_orders_24h'] > 10) {
                $status = 'warning';
                $issues[] = "High failed orders: {$orderMetrics['failed_orders_24h']}";
            }

            // Check revenue metrics
            $revenueMetrics = $this->getRevenueMetrics();
            if ($revenueMetrics['revenue_24h'] < $revenueMetrics['expected_revenue_24h'] * 0.5) {
                $status = 'warning';
                $issues[] = 'Revenue significantly below expected';
            }

            return [
                'status' => $status,
                'metrics' => [
                    'user_metrics' => $userMetrics,
                    'order_metrics' => $orderMetrics,
                    'revenue_metrics' => $revenueMetrics,
                ],
                'issues' => $issues,
                'message' => empty($issues) ? 'Business metrics normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Business metrics check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check API performance
     */
    protected function checkApiPerformance(): array
    {
        try {
            $status = 'healthy';
            $issues = [];

            // Check API endpoints performance
            $apiMetrics = $this->getApiMetrics();
            if (!empty($apiMetrics['slow_endpoints'])) {
                $status = 'warning';
                $issues[] = count($apiMetrics['slow_endpoints']) . ' slow API endpoints';
            }

            // Check API error rates
            $errorRate = $apiMetrics['error_rate_percent'];
            if ($errorRate > $this->thresholds['error_rate_percent']) {
                $status = 'warning';
                $issues[] = "High API error rate: {$errorRate}%";
            }

            return [
                'status' => $status,
                'metrics' => $apiMetrics,
                'issues' => $issues,
                'message' => empty($issues) ? 'API performance normal' : implode(', ', $issues)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'API performance check failed: ' . $e->getMessage()
            ];
        }
    }

    // Helper methods for data collection

    protected function getPerformanceMetrics(): array
    {
        $cacheKey = $this->cachePrefix . 'performance';
        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            return [
                'avg_response_time_ms' => $this->calculateAverageResponseTime(),
                'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'concurrent_requests' => $this->getConcurrentRequests(),
                'request_count_24h' => $this->getRequestCount24h(),
                'error_count_24h' => $this->getErrorCount24h(),
            ];
        });
    }

    protected function checkApplicationResponse(): array
    {
        $start = microtime(true);
        try {
            // Simple Laravel application check
            $response = app()->version();
            $responseTime = (microtime(true) - $start) * 1000;
            
            return [
                'responding' => !empty($response),
                'response_time_ms' => round($responseTime, 2),
            ];
        } catch (\Exception $e) {
            return [
                'responding' => false,
                'response_time_ms' => (microtime(true) - $start) * 1000,
            ];
        }
    }

    protected function checkLaravelHealth(): array
    {
        $issues = [];
        $status = 'healthy';

        // Check configuration
        try {
            app()->environment(); // This should not throw
        } catch (\Exception $e) {
            $status = 'critical';
            $issues[] = 'Laravel configuration error';
        }

        // Check critical services
        $criticalServices = ['database', 'cache', 'queue'];
        foreach ($criticalServices as $service) {
            try {
                match($service) {
                    'database' => DB::connection()->getPdo(),
                    'cache' => Cache::put('health_check', 'ok', 10),
                    'queue' => Queue::size(),
                };
            } catch (\Exception $e) {
                $status = 'critical';
                $issues[] = "Laravel {$service} service unavailable";
            }
        }

        return [
            'status' => $status,
            'issues' => $issues,
        ];
    }

    protected function getQueryStatistics(): array
    {
        try {
            $stats = DB::connection()->select("
                SHOW GLOBAL STATUS WHERE Variable_name IN (
                    'Slow_queries', 'Questions', 'Queries'
                )
            ");
            
            $statsMap = [];
            foreach ($stats as $stat) {
                $statsMap[$stat->Variable_name] = $stat->Value;
            }
            
            return [
                'slow_query_count' => $statsMap['Slow_queries'] ?? 0,
                'total_queries' => $statsMap['Questions'] ?? $statsMap['Queries'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'slow_query_count' => 0,
                'total_queries' => 0,
            ];
        }
    }

    protected function getDatabaseConnectionStats(): array
    {
        try {
            $stats = DB::connection()->select("
                SHOW GLOBAL STATUS WHERE Variable_name IN (
                    'Threads_connected', 'Max_used_connections'
                )
            ");
            
            $maxConnections = DB::connection()->select("SHOW VARIABLES LIKE 'max_connections'")[0]->Value ?? 100;
            
            $statsMap = [];
            foreach ($stats as $stat) {
                $statsMap[$stat->Variable_name] = $stat->Value;
            }
            
            return [
                'active_connections' => $statsMap['Threads_connected'] ?? 0,
                'max_connections' => $maxConnections,
                'max_used_connections' => $statsMap['Max_used_connections'] ?? 0,
            ];
        } catch (\Exception $e) {
            return [
                'active_connections' => 0,
                'max_connections' => 100,
                'max_used_connections' => 0,
            ];
        }
    }

    protected function getCacheStatistics(): array
    {
        // This would depend on the cache driver being used
        // For Redis, you would use Redis::info()
        // For file cache, you would analyze file sizes
        
        try {
            if (config('cache.default') === 'redis') {
                $redis = app('redis');
                $info = $redis->info();
                
                return [
                    'driver' => 'redis',
                    'hit_rate' => $this->calculateRedisHitRate($info),
                    'memory_usage_mb' => round(($info['used_memory'] ?? 0) / 1024 / 1024, 2),
                    'connected_clients' => $info['connected_clients'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            // Fallback for cache statistics
        }
        
        return [
            'driver' => config('cache.default'),
            'hit_rate' => 0,
            'memory_usage_mb' => 0,
        ];
    }

    protected function calculateRedisHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }

    protected function getSecurityEvents(): array
    {
        // This would typically read from security logs
        // For now, return empty array as placeholder
        return [];
    }

    protected function getRecentAuthFailures(): array
    {
        try {
            // This would check authentication failure logs
            // For now, return placeholder data
            return [
                'count' => 0,
                'recent_failures' => [],
            ];
        } catch (\Exception $e) {
            return [
                'count' => 0,
                'recent_failures' => [],
            ];
        }
    }

    protected function getSuspiciousActivities(): array
    {
        // This would analyze logs for suspicious patterns
        // Return placeholder for now
        return [];
    }

    protected function getUserMetrics(): array
    {
        try {
            // Get active users in last 24 hours
            $activeUsers24h = DB::table('users')
                ->where('last_activity_at', '>=', now()->subDay())
                ->count();

            return [
                'active_users_24h' => $activeUsers24h,
                'total_users' => DB::table('users')->count(),
            ];
        } catch (\Exception $e) {
            return [
                'active_users_24h' => 0,
                'total_users' => 0,
            ];
        }
    }

    protected function getOrderMetrics(): array
    {
        try {
            // This would check order-related tables
            // Return placeholder data
            return [
                'total_orders_24h' => 0,
                'failed_orders_24h' => 0,
                'completed_orders_24h' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'total_orders_24h' => 0,
                'failed_orders_24h' => 0,
                'completed_orders_24h' => 0,
            ];
        }
    }

    protected function getRevenueMetrics(): array
    {
        try {
            // This would calculate revenue from order data
            // Return placeholder data
            return [
                'revenue_24h' => 0,
                'expected_revenue_24h' => 10000, // Expected daily revenue
                'transaction_count_24h' => 0,
            ];
        } catch (\Exception $e) {
            return [
                'revenue_24h' => 0,
                'expected_revenue_24h' => 10000,
                'transaction_count_24h' => 0,
            ];
        }
    }

    protected function getApiMetrics(): array
    {
        try {
            // Get API performance metrics
            // This would typically read from API logs or performance tracking
            
            return [
                'total_requests_24h' => 0,
                'error_rate_percent' => 0,
                'slow_endpoints' => [],
                'endpoint_performance' => [],
            ];
        } catch (\Exception $e) {
            return [
                'total_requests_24h' => 0,
                'error_rate_percent' => 0,
                'slow_endpoints' => [],
                'endpoint_performance' => [],
            ];
        }
    }

    // Performance calculation methods

    protected function calculateAverageResponseTime(): float
    {
        // This would typically read from performance logs
        // For now, return a calculated value based on recent requests
        return 0;
    }

    protected function getConcurrentRequests(): int
    {
        // This would track concurrent requests
        // For now, return 0
        return 0;
    }

    protected function getRequestCount24h(): int
    {
        // This would read from access logs or application logs
        return 0;
    }

    protected function getErrorCount24h(): int
    {
        // This would read from error logs
        return 0;
    }

    /**
     * Record a request for metrics collection
     */
    public function recordRequest(array $metrics): void
    {
        $this->requestMetrics = $metrics;
        
        // Store in cache for quick access
        $requestId = $metrics['request_id'] ?? uniqid();
        Cache::put("request_metrics:{$requestId}", $metrics, now()->addHours(24));
        
        // Store in database for long-term analysis
        $this->storeRequestMetrics($metrics);
    }

    /**
     * Store request metrics in database
     */
    protected function storeRequestMetrics(array $metrics): void
    {
        try {
            DB::table('application_metrics')->insert([
                'request_id' => $metrics['request_id'],
                'endpoint' => $metrics['endpoint'],
                'method' => $metrics['method'],
                'response_time_ms' => $metrics['response_time_ms'],
                'status_code' => $metrics['status_code'],
                'memory_usage_mb' => $metrics['memory_usage_mb'],
                'query_count' => $metrics['query_count'] ?? 0,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to store application metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check for application alerts
     */
    protected function checkApplicationAlerts(array $status): void
    {
        foreach ($status['checks'] as $checkName => $check) {
            if ($check['status'] === 'critical' || $check['status'] === 'warning') {
                Log::channel('application_alerts')->alert("Application Alert: {$checkName}", [
                    'check' => $checkName,
                    'status' => $check['status'],
                    'message' => $check['message'],
                    'metrics' => $check['metrics'] ?? [],
                    'timestamp' => now()->toISOString()
                ]);
            }
        }
    }

    /**
     * Cache health status for performance
     */
    protected function cacheHealthStatus(array $status): void
    {
        $cacheKey = $this->cachePrefix . 'health';
        Cache::put($cacheKey, $status, now()->addMinutes(5));
    }

    /**
     * Get cached health status
     */
    public function getCachedHealthStatus(): ?array
    {
        $cacheKey = $this->cachePrefix . 'health';
        return Cache::get($cacheKey);
    }

    /**
     * Generate application performance report
     */
    public function generateApplicationReport(): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'health_status' => $this->getHealthStatus(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'recommendations' => $this->generateRecommendations()
        ];
    }

    /**
     * Generate optimization recommendations
     */
    protected function generateRecommendations(): array
    {
        $recommendations = [];
        $healthStatus = $this->getHealthStatus();
        
        foreach ($healthStatus['checks'] as $checkName => $check) {
            if ($check['status'] === 'warning' || $check['status'] === 'critical') {
                switch ($checkName) {
                    case 'performance':
                        $recommendations[] = 'Consider optimizing slow queries and implementing caching';
                        break;
                    case 'database':
                        $recommendations[] = 'Optimize database queries and consider connection pooling';
                        break;
                    case 'cache':
                        $recommendations[] = 'Review caching strategy and increase cache TTL';
                        break;
                    case 'queue':
                        $recommendations[] = 'Scale queue workers and investigate failed jobs';
                        break;
                }
            }
        }
        
        return $recommendations;
    }
}