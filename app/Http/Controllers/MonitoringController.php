<?php

namespace App\Http\Controllers;

use App\Services\SystemMonitor;
use App\Services\ApplicationMonitor;
use App\Services\DatabaseMonitor;
use App\Services\LogAnalysisService;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    protected SystemMonitor $systemMonitor;
    protected ApplicationMonitor $applicationMonitor;
    protected DatabaseMonitor $databaseMonitor;
    protected LogAnalysisService $logAnalysisService;
    protected AlertService $alertService;

    public function __construct(
        SystemMonitor $systemMonitor,
        ApplicationMonitor $applicationMonitor,
        DatabaseMonitor $databaseMonitor,
        LogAnalysisService $logAnalysisService,
        AlertService $alertService
    ) {
        $this->systemMonitor = $systemMonitor;
        $this->applicationMonitor = $applicationMonitor;
        $this->databaseMonitor = $databaseMonitor;
        $this->logAnalysisService = $logAnalysisService;
        $this->alertService = $alertService;
    }

    /**
     * Get comprehensive system metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $type = $request->get('type', 'all'); // all, system, application, database
            $refresh = $request->get('refresh', false);
            
            $cacheKey = "metrics_{$type}_" . (Auth::id() ?? 'guest');
            
            if (!$refresh) {
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    return response()->json($cached);
                }
            }
            
            $metrics = [];
            
            switch ($type) {
                case 'system':
                    $metrics['system'] = $this->systemMonitor->getHealthStatus();
                    break;
                case 'application':
                    $metrics['application'] = $this->applicationMonitor->getHealthStatus();
                    break;
                case 'database':
                    $metrics['database'] = $this->databaseMonitor->getHealthStatus();
                    break;
                case 'all':
                default:
                    $metrics = [
                        'system' => $this->systemMonitor->getHealthStatus(),
                        'application' => $this->applicationMonitor->getHealthStatus(),
                        'database' => $this->databaseMonitor->getHealthStatus(),
                    ];
                    break;
            }
            
            $response = [
                'timestamp' => now()->toISOString(),
                'type' => $type,
                'metrics' => $metrics,
                'summary' => $this->generateMetricsSummary($metrics),
            ];
            
            // Cache for 30 seconds
            Cache::put($cacheKey, $response, now()->addSeconds(30));
            
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed health status
     */
    public function healthDetailed(Request $request): JsonResponse
    {
        try {
            $level = $request->get('level', 'standard'); // basic, standard, detailed
            
            $health = [
                'timestamp' => now()->toISOString(),
                'overall_status' => 'unknown',
                'checks' => [],
                'summary' => [],
            ];
            
            // System health
            $health['checks']['system'] = $this->systemMonitor->getHealthStatus();
            
            // Application health
            $health['checks']['application'] = $this->applicationMonitor->getHealthStatus();
            
            // Database health
            $health['checks']['database'] = $this->databaseMonitor->getHealthStatus();
            
            // Additional checks based on level
            if ($level === 'detailed') {
                $health['checks']['infrastructure'] = $this->checkInfrastructureHealth();
                $health['checks']['security'] = $this->checkSecurityHealth();
                $health['checks']['performance'] = $this->checkPerformanceHealth();
            }
            
            // Determine overall status
            $overallStatus = $this->determineOverallHealthStatus($health['checks']);
            $health['overall_status'] = $overallStatus;
            
            // Generate summary
            $health['summary'] = $this->generateHealthSummary($health['checks']);
            
            $statusCode = match($overallStatus) {
                'healthy' => 200,
                'warning' => 206,
                'critical' => 503,
                'error' => 500,
                default => 500,
            };
            
            return response()->json($health, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Health check failed',
                'message' => $e->getMessage(),
                'overall_status' => 'error',
            ], 500);
        }
    }

    /**
     * Alert management endpoints
     */
    public function alerts(Request $request): JsonResponse
    {
        try {
            $action = $request->get('action', 'list');
            
            return match($action) {
                'list' => $this->listAlerts($request),
                'acknowledge' => $this->acknowledgeAlert($request),
                'resolve' => $this->resolveAlert($request),
                'send' => $this->sendAlert($request),
                'active' => $this->getActiveAlerts(),
                default => response()->json(['error' => 'Invalid action'], 400),
            };
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Alert operation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Log analysis and access
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $action = $request->get('action', 'analyze');
            
            return match($action) {
                'analyze' => $this->analyzeLogs($request),
                'recent' => $this->getRecentLogs($request),
                'summary' => $this->getLogSummary($request),
                default => $this->analyzeLogs($request),
            };
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Log operation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Performance metrics and analysis
     */
    public function performance(Request $request): JsonResponse
    {
        try {
            $timeframe = $request->get('timeframe', '1h'); // 5m, 15m, 1h, 6h, 24h
            $type = $request->get('type', 'overview'); // overview, detailed, trends
            
            $performance = [
                'timestamp' => now()->toISOString(),
                'timeframe' => $timeframe,
                'type' => $type,
                'data' => [],
            ];
            
            switch ($type) {
                case 'overview':
                    $performance['data'] = $this->getPerformanceOverview($timeframe);
                    break;
                case 'detailed':
                    $performance['data'] = $this->getDetailedPerformance($timeframe);
                    break;
                case 'trends':
                    $performance['data'] = $this->getPerformanceTrends($timeframe);
                    break;
            }
            
            return response()->json($performance);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Performance analysis failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Private helper methods

    protected function generateMetricsSummary(array $metrics): array
    {
        $summary = [
            'total_checks' => 0,
            'healthy' => 0,
            'warning' => 0,
            'critical' => 0,
            'error' => 0,
        ];
        
        foreach ($metrics as $category => $data) {
            if (isset($data['checks'])) {
                foreach ($data['checks'] as $check) {
                    $summary['total_checks']++;
                    $status = $check['status'] ?? 'unknown';
                    if (isset($summary[$status])) {
                        $summary[$status]++;
                    }
                }
            }
        }
        
        return $summary;
    }

    protected function determineOverallHealthStatus(array $checks): string
    {
        $statuses = [];
        
        foreach ($checks as $category => $data) {
            if (isset($data['overall'])) {
                $statuses[] = $data['overall'];
            } elseif (isset($data['checks'])) {
                // Count statuses within this category
                foreach ($data['checks'] as $check) {
                    if (isset($check['status'])) {
                        $statuses[] = $check['status'];
                    }
                }
            }
        }
        
        if (in_array('error', $statuses) || in_array('critical', $statuses)) {
            return 'critical';
        }
        
        if (in_array('warning', $statuses)) {
            return 'warning';
        }
        
        if (in_array('healthy', $statuses)) {
            return 'healthy';
        }
        
        return 'unknown';
    }

    protected function generateHealthSummary(array $checks): array
    {
        $summary = [
            'timestamp' => now()->toISOString(),
            'uptime' => $this->getSystemUptime(),
            'last_incident' => $this->getLastIncident(),
            'critical_issues' => [],
            'recommendations' => [],
        ];
        
        // Collect critical issues
        foreach ($checks as $category => $data) {
            if (isset($data['checks'])) {
                foreach ($data['checks'] as $checkName => $check) {
                    if (($check['status'] ?? '') === 'critical') {
                        $summary['critical_issues'][] = [
                            'category' => $category,
                            'check' => $checkName,
                            'message' => $check['message'] ?? 'Unknown issue',
                        ];
                    }
                }
            }
        }
        
        // Generate recommendations based on issues
        $summary['recommendations'] = $this->generateHealthRecommendations($checks);
        
        return $summary;
    }

    protected function checkInfrastructureHealth(): array
    {
        return [
            'nginx_status' => $this->checkServiceStatus('nginx'),
            'mysql_status' => $this->checkServiceStatus('mysql'),
            'redis_status' => $this->checkServiceStatus('redis'),
            'php_fpm_status' => $this->checkServiceStatus('php-fpm'),
        ];
    }

    protected function checkSecurityHealth(): array
    {
        return [
            'ssl_certificate' => $this->checkSSLCertificate(),
            'failed_logins' => $this->getFailedLogins24h(),
            'suspicious_activities' => $this->getSuspiciousActivities(),
        ];
    }

    protected function checkPerformanceHealth(): array
    {
        return [
            'response_time_p95' => $this->getResponseTimePercentile(95),
            'error_rate' => $this->getErrorRate(),
            'throughput' => $this->getThroughput(),
        ];
    }

    // Alert management methods

    protected function listAlerts(Request $request): JsonResponse
    {
        $status = $request->get('status', 'all'); // all, active, acknowledged, resolved
        $severity = $request->get('severity', 'all');
        $limit = min($request->get('limit', 50), 100);
        
        $alerts = $this->getStoredAlerts($status, $severity, $limit);
        
        return response()->json([
            'alerts' => $alerts,
            'total' => count($alerts),
            'filters' => [
                'status' => $status,
                'severity' => $severity,
                'limit' => $limit,
            ],
        ]);
    }

    protected function acknowledgeAlert(Request $request): JsonResponse
    {
        $alertId = $request->get('alert_id');
        $acknowledgedBy = Auth::user()->name ?? 'system';
        
        if (!$alertId) {
            return response()->json(['error' => 'Alert ID is required'], 400);
        }
        
        $success = $this->alertService->acknowledgeAlert($alertId, $acknowledgedBy);
        
        if ($success) {
            return response()->json([
                'message' => 'Alert acknowledged successfully',
                'alert_id' => $alertId,
                'acknowledged_by' => $acknowledgedBy,
            ]);
        }
        
        return response()->json(['error' => 'Failed to acknowledge alert'], 500);
    }

    protected function resolveAlert(Request $request): JsonResponse
    {
        $alertId = $request->get('alert_id');
        $resolvedBy = Auth::user()->name ?? 'system';
        
        if (!$alertId) {
            return response()->json(['error' => 'Alert ID is required'], 400);
        }
        
        $success = $this->alertService->resolveAlert($alertId, $resolvedBy);
        
        if ($success) {
            return response()->json([
                'message' => 'Alert resolved successfully',
                'alert_id' => $alertId,
                'resolved_by' => $resolvedBy,
            ]);
        }
        
        return response()->json(['error' => 'Failed to resolve alert'], 500);
    }

    protected function sendAlert(Request $request): JsonResponse
    {
        $request->validate([
            'severity' => 'required|in:emergency,critical,alert,warning,notice,info,debug',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'context' => 'array',
        ]);
        
        $result = $this->alertService->sendAlert(
            $request->severity,
            $request->title,
            $request->message,
            $request->context ?? []
        );
        
        return response()->json([
            'alert_id' => $result['id'] ?? null,
            'status' => $result['status'] ?? 'sent',
            'channels_used' => $result['channels_used'] ?? [],
            'message' => 'Alert sent successfully',
        ]);
    }

    protected function getActiveAlerts(): JsonResponse
    {
        $activeAlerts = $this->alertService->getActiveAlerts();
        
        return response()->json([
            'active_alerts' => array_values($activeAlerts),
            'count' => count($activeAlerts),
        ]);
    }

    // Log analysis methods

    protected function analyzeLogs(Request $request): JsonResponse
    {
        $options = [
            'time_range' => $request->get('time_range', '1h'),
            'levels' => $request->get('levels', ['error', 'warning', 'critical']),
            'source' => $request->get('source', 'application'),
        ];
        
        $analysis = $this->logAnalysisService->analyzeLogs($options);
        
        return response()->json([
            'analysis' => $analysis,
            'options' => $options,
        ]);
    }

    protected function getRecentLogs(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 100), 500);
        $level = $request->get('level');
        
        $logs = $this->getRecentLogEntries($limit, $level);
        
        return response()->json([
            'logs' => $logs,
            'count' => count($logs),
            'filters' => [
                'limit' => $limit,
                'level' => $level,
            ],
        ]);
    }

    protected function getLogSummary(Request $request): JsonResponse
    {
        $timeRange = $request->get('time_range', '24h');
        
        $summary = $this->generateLogSummary($timeRange);
        
        return response()->json([
            'summary' => $summary,
            'time_range' => $timeRange,
        ]);
    }

    // Performance analysis methods

    protected function getPerformanceOverview(string $timeframe): array
    {
        return [
            'response_time_avg' => $this->getAverageResponseTime($timeframe),
            'error_rate' => $this->getErrorRate($timeframe),
            'throughput' => $this->getThroughput($timeframe),
            'concurrent_users' => $this->getConcurrentUsers($timeframe),
        ];
    }

    protected function getDetailedPerformance(string $timeframe): array
    {
        return [
            'response_times' => $this->getResponseTimeBreakdown($timeframe),
            'error_breakdown' => $this->getErrorBreakdown($timeframe),
            'resource_usage' => $this->getResourceUsage($timeframe),
            'database_performance' => $this->getDatabasePerformance($timeframe),
            'cache_performance' => $this->getCachePerformance($timeframe),
        ];
    }

    protected function getPerformanceTrends(string $timeframe): array
    {
        return [
            'response_time_trend' => $this->getResponseTimeTrend($timeframe),
            'error_rate_trend' => $this->getErrorRateTrend($timeframe),
            'throughput_trend' => $this->getThroughputTrend($timeframe),
        ];
    }

    // Placeholder methods for data collection
    // These would typically read from actual monitoring data sources

    protected function getSystemUptime(): int
    {
        return time() - (int) @file_get_contents('/proc/uptime');
    }

    protected function getLastIncident(): ?string
    {
        // This would check alert history
        return null;
    }

    protected function generateHealthRecommendations(array $checks): array
    {
        $recommendations = [];
        
        foreach ($checks as $category => $data) {
            if (isset($data['checks'])) {
                foreach ($data['checks'] as $checkName => $check) {
                    if (($check['status'] ?? '') === 'critical') {
                        $recommendations[] = "Critical issue in {$category}.{$checkName}: immediate attention required";
                    }
                }
            }
        }
        
        return $recommendations;
    }

    protected function checkServiceStatus(string $service): string
    {
        // This would check if a service is running
        $output = shell_exec("systemctl is-active {$service} 2>/dev/null");
        return trim($output) === 'active' ? 'running' : 'stopped';
    }

    protected function checkSSLCertificate(): array
    {
        // This would check SSL certificate status
        return ['status' => 'valid', 'expires_at' => now()->addYear()];
    }

    protected function getFailedLogins24h(): int
    {
        // This would count failed login attempts
        return 0;
    }

    protected function getSuspiciousActivities(): array
    {
        // This would return suspicious activities
        return [];
    }

    protected function getResponseTimePercentile(int $percentile): float
    {
        // This would calculate response time percentile
        return 0.0;
    }

    protected function getErrorRate(): float
    {
        // This would calculate error rate
        return 0.0;
    }

    protected function getThroughput(): float
    {
        // This would calculate requests per second
        return 0.0;
    }

    protected function getStoredAlerts(string $status, string $severity, int $limit): array
    {
        // This would fetch alerts from cache/database
        return [];
    }

    protected function getRecentLogEntries(int $limit, ?string $level): array
    {
        // This would fetch recent log entries
        return [];
    }

    protected function generateLogSummary(string $timeRange): array
    {
        // This would generate log summary
        return [];
    }

    protected function getAverageResponseTime(string $timeframe): float
    {
        // This would calculate average response time
        return 0.0;
    }

    protected function getConcurrentUsers(string $timeframe): int
    {
        // This would get concurrent user count
        return 0;
    }

    protected function getResponseTimeBreakdown(string $timeframe): array
    {
        // This would get response time breakdown
        return [];
    }

    protected function getErrorBreakdown(string $timeframe): array
    {
        // This would get error breakdown
        return [];
    }

    protected function getResourceUsage(string $timeframe): array
    {
        // This would get resource usage data
        return [];
    }

    protected function getDatabasePerformance(string $timeframe): array
    {
        // This would get database performance data
        return [];
    }

    protected function getCachePerformance(string $timeframe): array
    {
        // This would get cache performance data
        return [];
    }

    protected function getResponseTimeTrend(string $timeframe): array
    {
        // This would get response time trend data
        return [];
    }

    protected function getErrorRateTrend(string $timeframe): array
    {
        // This would get error rate trend data
        return [];
    }

    protected function getThroughputTrend(string $timeframe): array
    {
        // This would get throughput trend data
        return [];
    }
}