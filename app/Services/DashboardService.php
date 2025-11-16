<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Cache key prefix for dashboard data
     */
    protected string $cachePrefix = 'dashboard_';

    /**
     * Dashboard refresh intervals (in seconds)
     */
    protected array $refreshIntervals = [
        'realtime' => 5,      // 5 seconds
        'frequent' => 30,     // 30 seconds
        'normal' => 60,       // 1 minute
        'slow' => 300,        // 5 minutes
    ];

    /**
     * Get real-time monitoring dashboard data
     */
    public function getRealtimeDashboard(string $type = 'overview'): array
    {
        $cacheKey = "realtime_{$type}_" . now()->format('Y-m-d-H-i-s');
        
        return Cache::remember($cacheKey, $this->refreshIntervals['realtime'], function() use ($type) {
            return match($type) {
                'overview' => $this->getOverviewDashboard(),
                'performance' => $this->getPerformanceDashboard(),
                'system' => $this->getSystemDashboard(),
                'application' => $this->getApplicationDashboard(),
                'database' => $this->getDatabaseDashboard(),
                'alerts' => $this->getAlertsDashboard(),
                'business' => $this->getBusinessDashboard(),
                default => $this->getOverviewDashboard(),
            };
        });
    }

    /**
     * Get overview dashboard with key metrics
     */
    protected function getOverviewDashboard(): array
    {
        $now = now();
        $timeRange = [
            'start' => $now->subHour(),
            'end' => $now
        ];

        return [
            'timestamp' => $now->toISOString(),
            'type' => 'overview',
            'refresh_interval' => $this->refreshIntervals['realtime'],
            'system_health' => $this->getSystemHealthStatus(),
            'key_metrics' => $this->getKeyMetrics($timeRange),
            'alerts_summary' => $this->getAlertsSummary(),
            'quick_stats' => $this->getQuickStats(),
            'charts' => $this->getOverviewCharts($timeRange),
            'recommendations' => $this->getSystemRecommendations(),
        ];
    }

    /**
     * Get performance dashboard
     */
    protected function getPerformanceDashboard(): array
    {
        $now = now();
        $timeRange = [
            'start' => $now->subHour(),
            'end' => $now
        ];

        return [
            'timestamp' => $now->toISOString(),
            'type' => 'performance',
            'refresh_interval' => $this->refreshIntervals['frequent'],
            'response_times' => $this->getResponseTimeMetrics($timeRange),
            'throughput' => $this->getThroughputMetrics($timeRange),
            'error_rates' => $this->getErrorRateMetrics($timeRange),
            'resource_usage' => $this->getResourceUsageMetrics($timeRange),
            'performance_trends' => $this->getPerformanceTrends($timeRange),
            'slowest_endpoints' => $this->getSlowestEndpoints(),
            'top_errors' => $this->getTopErrors(),
        ];
    }

    /**
     * Get system dashboard
     */
    protected function getSystemDashboard(): array
    {
        $now = now();
        $timeRange = [
            'start' => $now->subDay(),
            'end' => $now
        ];

        return [
            'timestamp' => $now->toISOString(),
            'type' => 'system',
            'refresh_interval' => $this->refreshIntervals['normal'],
            'cpu_metrics' => $this->getCPUMetrics($timeRange),
            'memory_metrics' => $this->getMemoryMetrics($timeRange),
            'disk_metrics' => $this->getDiskMetrics($timeRange),
            'network_metrics' => $this->getNetworkMetrics($timeRange),
            'process_metrics' => $this->getProcessMetrics($timeRange),
            'service_status' => $this->getServiceStatus(),
        ];
    }

    /**
     * Get application dashboard
     */
    protected function getApplicationDashboard(): array
    {
        $now = now();
        $timeRange = [
            'start' => $now->subDay(),
            'end' => $now
        ];

        return [
            'timestamp' => $now->toISOString(),
            'type' => 'application',
            'refresh_interval' => $this->refreshIntervals['normal'],
            'request_metrics' => $this->getRequestMetrics($timeRange),
            'session_metrics' => $this->getSessionMetrics($timeRange),
            'cache_metrics' => $this->getCacheMetrics($timeRange),
            'queue_metrics' => $this->getQueueMetrics($timeRange),
            'user_metrics' => $this->getUserMetrics($timeRange),
            'application_health' => $this->getApplicationHealthStatus(),
        ];
    }

    /**
     * Get database dashboard
     */
    protected function getDatabaseDashboard(): array
    {
        $now = now();
        $timeRange = [
            'start' => $now->subDay(),
            'end' => $now
        ];

        return [
            'timestamp' => $now->toISOString(),
            'type' => 'database',
            'refresh_interval' => $this->refreshIntervals['normal'],
            'connection_metrics' => $this->getDatabaseConnectionMetrics(),
            'query_metrics' => $this->getQueryMetrics($timeRange),
            'performance_metrics' => $this->getDatabasePerformanceMetrics($timeRange),
            'storage_metrics' => $this->getDatabaseStorageMetrics(),
            'replication_metrics' => $this->getDatabaseReplicationMetrics(),
        ];
    }

    /**
     * Get alerts dashboard
     */
    protected function getAlertsDashboard(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'type' => 'alerts',
            'refresh_interval' => $this->refreshIntervals['realtime'],
            'active_alerts' => $this->getActiveAlertsDetailed(),
            'alert_history' => $this->getAlertsHistory(),
            'alert_trends' => $this->getAlertsTrends(),
            'alert_rules' => $this->getAlertRules(),
            'notification_channels' => $this->getNotificationChannelsStatus(),
        ];
    }

    /**
     * Get business metrics dashboard
     */
    protected function getBusinessDashboard(): array
    {
        $now = now();
        $timeRange = [
            'start' => $now->subDay(),
            'end' => $now
        ];

        return [
            'timestamp' => $now->toISOString(),
            'type' => 'business',
            'refresh_interval' => $this->refreshIntervals['slow'],
            'revenue_metrics' => $this->getRevenueMetrics($timeRange),
            'user_metrics' => $this->getBusinessUserMetrics($timeRange),
            'order_metrics' => $this->getOrderMetrics($timeRange),
            'conversion_metrics' => $this->getConversionMetrics($timeRange),
            'growth_trends' => $this->getGrowthTrends($timeRange),
        ];
    }

    // Protected helper methods for data collection

    protected function getSystemHealthStatus(): array
    {
        try {
            $systemMonitor = new SystemMonitor();
            $applicationMonitor = new ApplicationMonitor();
            $databaseMonitor = new DatabaseMonitor();
            
            $status = [
                'overall' => 'unknown',
                'components' => []
            ];
            
            // System status
            $systemStatus = $systemMonitor->getHealthStatus();
            $status['components']['system'] = [
                'status' => $systemStatus['overall'],
                'last_check' => $systemStatus['timestamp'],
                'issues' => collect($systemStatus['checks'])->filter(fn($check) => $check['status'] !== 'healthy')->count()
            ];
            
            // Application status
            $appStatus = $applicationMonitor->getHealthStatus();
            $status['components']['application'] = [
                'status' => $appStatus['overall'],
                'last_check' => $appStatus['timestamp'],
                'issues' => collect($appStatus['checks'])->filter(fn($check) => $check['status'] !== 'healthy')->count()
            ];
            
            // Database status
            $dbStatus = $databaseMonitor->getHealthStatus();
            $status['components']['database'] = [
                'status' => $dbStatus['overall'],
                'last_check' => $dbStatus['timestamp'],
                'issues' => collect($dbStatus['checks'])->filter(fn($check) => $check['status'] !== 'healthy')->count()
            ];
            
            // Determine overall status
            $statuses = array_column($status['components'], 'status');
            if (in_array('critical', $statuses) || in_array('error', $statuses)) {
                $status['overall'] = 'critical';
            } elseif (in_array('warning', $statuses)) {
                $status['overall'] = 'warning';
            } elseif (!in_array('healthy', $statuses)) {
                $status['overall'] = 'unknown';
            } else {
                $status['overall'] = 'healthy';
            }
            
            return $status;
        } catch (\Exception $e) {
            return [
                'overall' => 'error',
                'components' => [
                    'system' => ['status' => 'error', 'message' => 'Unable to collect system metrics'],
                    'application' => ['status' => 'error', 'message' => 'Unable to collect application metrics'],
                    'database' => ['status' => 'error', 'message' => 'Unable to collect database metrics'],
                ],
                'error' => $e->getMessage()
            ];
        }
    }

    protected function getKeyMetrics(array $timeRange): array
    {
        return [
            'response_time_avg' => $this->getAverageResponseTime($timeRange),
            'requests_per_second' => $this->getRequestsPerSecond($timeRange),
            'error_rate' => $this->getErrorRate($timeRange),
            'active_users' => $this->getActiveUsers($timeRange),
            'memory_usage_percent' => $this->getMemoryUsagePercent(),
            'cpu_usage_percent' => $this->getCPUUsagePercent(),
            'disk_usage_percent' => $this->getDiskUsagePercent(),
        ];
    }

    protected function getAlertsSummary(): array
    {
        try {
            $alertService = new AlertService();
            $activeAlerts = $alertService->getActiveAlerts();
            
            $summary = [
                'total_active' => count($activeAlerts),
                'by_severity' => [
                    'critical' => 0,
                    'warning' => 0,
                    'info' => 0,
                ],
                'by_component' => [
                    'system' => 0,
                    'application' => 0,
                    'database' => 0,
                ]
            ];
            
            foreach ($activeAlerts as $alert) {
                $severity = $alert['severity'] ?? 'info';
                $component = $alert['component'] ?? 'system';
                
                if (isset($summary['by_severity'][$severity])) {
                    $summary['by_severity'][$severity]++;
                }
                
                if (isset($summary['by_component'][$component])) {
                    $summary['by_component'][$component]++;
                }
            }
            
            return $summary;
        } catch (\Exception $e) {
            return [
                'total_active' => 0,
                'error' => 'Unable to fetch alerts'
            ];
        }
    }

    protected function getQuickStats(): array
    {
        return [
            'uptime_hours' => $this->getSystemUptime() / 3600,
            'total_requests_24h' => $this->getTotalRequests24h(),
            'total_errors_24h' => $this->getTotalErrors24h(),
            'avg_response_time_24h' => $this->getAverageResponseTime24h(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'database_connections' => $this->getDatabaseConnectionCount(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    protected function getOverviewCharts(array $timeRange): array
    {
        return [
            'response_time_trend' => $this->generateResponseTimeTrendChart($timeRange),
            'throughput_trend' => $this->generateThroughputTrendChart($timeRange),
            'error_rate_trend' => $this->generateErrorRateTrendChart($timeRange),
            'resource_usage' => $this->generateResourceUsageChart($timeRange),
        ];
    }

    protected function getSystemRecommendations(): array
    {
        $recommendations = [];
        
        try {
            $systemMonitor = new SystemMonitor();
            $healthStatus = $systemMonitor->getHealthStatus();
            
            foreach ($healthStatus['checks'] as $checkName => $check) {
                if ($check['status'] === 'critical') {
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => 'system',
                        'issue' => "Critical issue in {$checkName}",
                        'recommendation' => $this->getSystemRecommendation($checkName),
                        'impact' => 'high'
                    ];
                } elseif ($check['status'] === 'warning') {
                    $recommendations[] = [
                        'priority' => 'medium',
                        'category' => 'system',
                        'issue' => "Warning in {$checkName}",
                        'recommendation' => $this->getSystemRecommendation($checkName),
                        'impact' => 'medium'
                    ];
                }
            }
        } catch (\Exception $e) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'system',
                'issue' => 'Unable to collect system recommendations',
                'recommendation' => 'Check system health manually',
                'impact' => 'unknown'
            ];
        }
        
        return array_slice($recommendations, 0, 5); // Return top 5 recommendations
    }

    // Placeholder methods for metric collection
    // These would typically read from actual monitoring data sources

    protected function getResponseTimeMetrics(array $timeRange): array
    {
        return [
            'average' => 0.0,
            'p95' => 0.0,
            'p99' => 0.0,
            'trend' => []
        ];
    }

    protected function getThroughputMetrics(array $timeRange): array
    {
        return [
            'requests_per_second' => 0.0,
            'peak_rps' => 0.0,
            'trend' => []
        ];
    }

    protected function getErrorRateMetrics(array $timeRange): array
    {
        return [
            'error_rate_percent' => 0.0,
            'total_errors' => 0,
            'trend' => []
        ];
    }

    protected function getResourceUsageMetrics(array $timeRange): array
    {
        return [
            'cpu_usage_percent' => 0.0,
            'memory_usage_percent' => 0.0,
            'disk_usage_percent' => 0.0,
            'network_io' => 0.0,
        ];
    }

    protected function getPerformanceTrends(array $timeRange): array
    {
        return [
            'response_time_trend' => [],
            'throughput_trend' => [],
            'error_rate_trend' => [],
        ];
    }

    protected function getSlowestEndpoints(): array
    {
        return [];
    }

    protected function getTopErrors(): array
    {
        return [];
    }

    protected function getCPUMetrics(array $timeRange): array
    {
        return [
            'usage_percent' => 0.0,
            'load_average' => 0.0,
            'trend' => []
        ];
    }

    protected function getMemoryMetrics(array $timeRange): array
    {
        return [
            'usage_percent' => 0.0,
            'available_mb' => 0.0,
            'trend' => []
        ];
    }

    protected function getDiskMetrics(array $timeRange): array
    {
        return [
            'usage_percent' => 0.0,
            'free_space_gb' => 0.0,
            'io_operations' => 0,
        ];
    }

    protected function getNetworkMetrics(array $timeRange): array
    {
        return [
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'packets_sent' => 0,
            'packets_received' => 0,
        ];
    }

    protected function getProcessMetrics(array $timeRange): array
    {
        return [
            'total_processes' => 0,
            'running_processes' => 0,
            'zombie_processes' => 0,
        ];
    }

    protected function getServiceStatus(): array
    {
        return [
            'nginx' => 'unknown',
            'mysql' => 'unknown',
            'redis' => 'unknown',
            'php-fpm' => 'unknown',
        ];
    }

    protected function getRequestMetrics(array $timeRange): array
    {
        return [
            'total_requests' => 0,
            'avg_response_time' => 0.0,
            'error_count' => 0,
        ];
    }

    protected function getSessionMetrics(array $timeRange): array
    {
        return [
            'active_sessions' => 0,
            'new_sessions' => 0,
        ];
    }

    protected function getCacheMetrics(array $timeRange): array
    {
        return [
            'hit_rate' => 0.0,
            'memory_usage' => 0.0,
        ];
    }

    protected function getQueueMetrics(array $timeRange): array
    {
        return [
            'jobs_processed' => 0,
            'failed_jobs' => 0,
            'queue_size' => 0,
        ];
    }

    protected function getUserMetrics(array $timeRange): array
    {
        return [
            'active_users' => 0,
            'new_users' => 0,
        ];
    }

    protected function getApplicationHealthStatus(): array
    {
        return [
            'status' => 'unknown',
            'issues' => [],
        ];
    }

    protected function getDatabaseConnectionMetrics(): array
    {
        return [
            'active_connections' => 0,
            'max_connections' => 0,
            'connection_usage_percent' => 0.0,
        ];
    }

    protected function getQueryMetrics(array $timeRange): array
    {
        return [
            'total_queries' => 0,
            'slow_queries' => 0,
            'avg_query_time' => 0.0,
        ];
    }

    protected function getDatabasePerformanceMetrics(array $timeRange): array
    {
        return [
            'query_cache_hit_rate' => 0.0,
            'lock_wait_time' => 0.0,
        ];
    }

    protected function getDatabaseStorageMetrics(): array
    {
        return [
            'total_size_gb' => 0.0,
            'free_space_gb' => 0.0,
        ];
    }

    protected function getDatabaseReplicationMetrics(): array
    {
        return [
            'replication_lag' => 0.0,
            'replication_status' => 'unknown',
        ];
    }

    protected function getActiveAlertsDetailed(): array
    {
        return [];
    }

    protected function getAlertsHistory(): array
    {
        return [];
    }

    protected function getAlertsTrends(): array
    {
        return [];
    }

    protected function getAlertRules(): array
    {
        return [];
    }

    protected function getNotificationChannelsStatus(): array
    {
        return [];
    }

    protected function getRevenueMetrics(array $timeRange): array
    {
        return [
            'revenue_24h' => 0.0,
            'revenue_growth' => 0.0,
        ];
    }

    protected function getBusinessUserMetrics(array $timeRange): array
    {
        return [
            'active_users' => 0,
            'new_registrations' => 0,
        ];
    }

    protected function getOrderMetrics(array $timeRange): array
    {
        return [
            'orders_24h' => 0,
            'completed_orders' => 0,
            'failed_orders' => 0,
        ];
    }

    protected function getConversionMetrics(array $timeRange): array
    {
        return [
            'conversion_rate' => 0.0,
            'bounce_rate' => 0.0,
        ];
    }

    protected function getGrowthTrends(array $timeRange): array
    {
        return [];
    }

    protected function getSystemUptime(): int
    {
        return time() - (int) @file_get_contents('/proc/uptime');
    }

    protected function getTotalRequests24h(): int
    {
        return 0;
    }

    protected function getTotalErrors24h(): int
    {
        return 0;
    }

    protected function getAverageResponseTime24h(): float
    {
        return 0.0;
    }

    protected function getCacheHitRate(): float
    {
        return 0.0;
    }

    protected function getDatabaseConnectionCount(): int
    {
        return 0;
    }

    protected function getMemoryUsagePercent(): float
    {
        $memory = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        return ($memory / $limit) * 100;
    }

    protected function getCPUUsagePercent(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $cores = (int) shell_exec('nproc') ?: 1;
            return min($load[0] * 100 / $cores, 100);
        }
        return 0.0;
    }

    protected function getDiskUsagePercent(): float
    {
        $total = disk_total_space('.');
        $free = disk_free_space('.');
        $used = $total - $free;
        return $total > 0 ? ($used / $total) * 100 : 0.0;
    }

    protected function generateResponseTimeTrendChart(array $timeRange): array
    {
        return [];
    }

    protected function generateThroughputTrendChart(array $timeRange): array
    {
        return [];
    }

    protected function generateErrorRateTrendChart(array $timeRange): array
    {
        return [];
    }

    protected function generateResourceUsageChart(array $timeRange): array
    {
        return [];
    }

    protected function getAverageResponseTime(array $timeRange): float
    {
        return 0.0;
    }

    protected function getRequestsPerSecond(array $timeRange): float
    {
        return 0.0;
    }

    protected function getErrorRate(array $timeRange): float
    {
        return 0.0;
    }

    protected function getActiveUsers(array $timeRange): int
    {
        return 0;
    }

    protected function getSystemRecommendation(string $checkName): string
    {
        return match($checkName) {
            'cpu' => 'Consider optimizing CPU-intensive processes or scaling CPU resources',
            'memory' => 'Consider adding more RAM or optimizing memory usage',
            'disk' => 'Clean up disk space or consider adding more storage',
            'network' => 'Check network connectivity and bandwidth',
            default => 'Monitor this component closely',
        };
    }

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

    /**
     * Clear dashboard cache
     */
    public function clearCache(string $type = null): void
    {
        try {
            $patterns = [
                "{$this->cachePrefix}realtime_*",
                "{$this->cachePrefix}overview_*",
                "{$this->cachePrefix}performance_*",
            ];
            
            if ($type) {
                $patterns = ["{$this->cachePrefix}{$type}_*"];
            }
            
            // Clear by forgetting specific cache patterns
            foreach ($patterns as $pattern) {
                $cacheKeys = [
                    str_replace('*', 'overview', $pattern),
                    str_replace('*', 'performance', $pattern),
                    str_replace('*', 'system', $pattern),
                    str_replace('*', 'application', $pattern),
                    str_replace('*', 'database', $pattern),
                ];
                
                foreach ($cacheKeys as $key) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear dashboard cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get dashboard configuration
     */
    public function getDashboardConfig(): array
    {
        return [
            'refresh_intervals' => $this->refreshIntervals,
            'cache_ttl' => $this->refreshIntervals['normal'],
            'supported_dashboards' => [
                'overview',
                'performance',
                'system',
                'application',
                'database',
                'alerts',
                'business'
            ],
            'chart_types' => [
                'line',
                'bar',
                'area',
                'pie',
                'gauge',
                'heatmap'
            ]
        ];
    }
}