<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\UsageLog;
use Carbon\Carbon;

class UsageTrackingService
{
    protected $startTime;
    protected $startMemory;

    /**
     * Start tracking a request
     */
    public function startTracking(Request $request = null)
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);

        // Store tracking data in request if available
        if ($request) {
            $request->merge([
                'tracking_start_time' => $this->startTime,
                'tracking_start_memory' => $this->startMemory,
            ]);
        }

        return $this;
    }

    /**
     * Log usage data
     */
    public function logUsage(array $data)
    {
        try {
            // Get license info from request or session
            $licenseId = $this->getCurrentLicenseId();
            $userId = auth()->id();
            $sessionId = session()->getId();

            $usageData = array_merge([
                'license_id' => $licenseId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ], $data);

            // Only log if we have meaningful data
            if (!empty($usageData['action']) || !empty($usageData['resource'])) {
                UsageLog::create($usageData);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log usage data', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }

    /**
     * Log API request
     */
    public function logApiRequest(Request $request, $response = null, $error = null)
    {
        $startTime = $request->get('tracking_start_time', microtime(true));
        $startMemory = $request->get('tracking_start_memory', memory_get_usage(true));

        $responseTime = (microtime(true) - $startTime) * 1000; // milliseconds
        $memoryUsage = memory_get_usage(true) - $startMemory;
        $cpuUsage = $this->getCpuUsage();

        $this->logUsage([
            'action' => 'api_request',
            'resource' => $request->path(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'request_data' => $this->sanitizeRequestData($request->all()),
            'response_status' => $response ? $response->getStatusCode() : null,
            'response_time' => round($responseTime, 3),
            'memory_usage' => $memoryUsage,
            'cpu_usage' => $cpuUsage,
            'error_message' => $error,
            'metadata' => [
                'route_name' => $request->route() ? $request->route()->getName() : null,
                'middleware' => $request->route() ? $request->route()->middleware() : [],
                'headers' => $this->getImportantHeaders($request),
            ]
        ]);
    }

    /**
     * Log user action
     */
    public function logUserAction($action, $resource = null, $metadata = [])
    {
        $this->logUsage([
            'action' => $action,
            'resource' => $resource,
            'metadata' => $metadata
        ]);
    }

    /**
     * Log feature usage
     */
    public function logFeatureUsage($feature, $metadata = [])
    {
        $this->logUsage([
            'action' => 'feature_used',
            'resource' => $feature,
            'metadata' => array_merge($metadata, [
                'feature_name' => $feature,
                'timestamp' => now()->toISOString()
            ])
        ]);
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetrics($operation, $duration, $memoryUsed = null, $metadata = [])
    {
        $this->logUsage([
            'action' => 'performance_metric',
            'resource' => $operation,
            'response_time' => $duration,
            'memory_usage' => $memoryUsed,
            'metadata' => array_merge($metadata, [
                'operation' => $operation,
                'duration_ms' => $duration
            ])
        ]);
    }

    /**
     * Get usage statistics
     */
    public function getUsageStatistics($licenseId = null, $period = '30 days')
    {
        $startDate = Carbon::parse("-{$period}");

        $query = UsageLog::where('created_at', '>=', $startDate);

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }

        return [
            'period' => $period,
            'total_requests' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'unique_sessions' => $query->distinct('session_id')->count('session_id'),
            'avg_response_time' => round($query->avg('response_time'), 2),
            'max_response_time' => $query->max('response_time'),
            'error_count' => $query->whereNotNull('error_message')->count(),
            'error_rate' => $query->count() > 0 ? round(($query->whereNotNull('error_message')->count() / $query->count()) * 100, 2) : 0,
            'memory_peak' => $query->max('memory_usage'),
            'top_actions' => $query->select('action', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->whereNotNull('action')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'top_resources' => $query->select('resource', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->whereNotNull('resource')
                ->groupBy('resource')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'hourly_distribution' => $query->select(\Illuminate\Support\Facades\DB::raw('HOUR(created_at) as hour'), \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get(),
        ];
    }

    /**
     * Monitor system health
     */
    public function getSystemHealth()
    {
        return [
            'memory_usage' => $this->getMemoryUsage(),
            'cpu_usage' => $this->getCpuUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'uptime' => $this->getSystemUptime(),
            'load_average' => $this->getLoadAverage(),
            'database_connections' => $this->getDatabaseConnections(),
            'cache_hit_rate' => $this->getCacheHitRate(),
        ];
    }

    /**
     * Detect anomalies in usage patterns
     */
    public function detectAnomalies($licenseId = null, $hours = 24)
    {
        $startDate = now()->subHours($hours);
        $query = UsageLog::where('created_at', '>=', $startDate);

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }

        $recentStats = $this->calculateStats($query->get());

        // Compare with baseline (last 7 days average)
        $baselineStart = now()->subDays(7);
        $baselineEnd = $startDate;
        $baselineQuery = UsageLog::whereBetween('created_at', [$baselineStart, $baselineEnd]);

        if ($licenseId) {
            $baselineQuery->where('license_id', $licenseId);
        }

        $baselineStats = $this->calculateStats($baselineQuery->get());

        return [
            'anomalies_detected' => $this->compareWithBaseline($recentStats, $baselineStats),
            'recent_stats' => $recentStats,
            'baseline_stats' => $baselineStats,
            'period_hours' => $hours,
        ];
    }

    /**
     * Clean old usage logs
     */
    public function cleanupOldLogs($daysToKeep = 90)
    {
        $cutoffDate = now()->subDays($daysToKeep);

        $deleted = UsageLog::where('created_at', '<', $cutoffDate)->delete();

        Log::info('Cleaned up old usage logs', [
            'deleted_records' => $deleted,
            'days_kept' => $daysToKeep
        ]);

        return $deleted;
    }

    /**
     * Get current license ID
     */
    protected function getCurrentLicenseId()
    {
        // Try to get from request attributes (set by middleware)
        $licenseInfo = request()->get('license_info');
        if ($licenseInfo && isset($licenseInfo['license'])) {
            return $licenseInfo['license']->id;
        }

        // Try to get from session
        return session('license_id');
    }

    /**
     * Sanitize request data for logging
     */
    protected function sanitizeRequestData($data)
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeRequestData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get important headers
     */
    protected function getImportantHeaders(Request $request)
    {
        $importantHeaders = [
            'accept',
            'accept-language',
            'user-agent',
            'referer',
            'x-requested-with',
            'x-forwarded-for',
            'x-real-ip',
        ];

        $headers = [];
        foreach ($importantHeaders as $header) {
            $value = $request->header($header);
            if ($value) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Calculate statistics from usage logs
     */
    protected function calculateStats($logs)
    {
        if ($logs->isEmpty()) {
            return [
                'avg_response_time' => 0,
                'error_rate' => 0,
                'total_requests' => 0,
                'unique_ips' => 0,
            ];
        }

        return [
            'avg_response_time' => $logs->avg('response_time') ?? 0,
            'error_rate' => $logs->whereNotNull('error_message')->count() / $logs->count() * 100,
            'total_requests' => $logs->count(),
            'unique_ips' => $logs->unique('ip_address')->count(),
        ];
    }

    /**
     * Compare recent stats with baseline
     */
    protected function compareWithBaseline($recent, $baseline)
    {
        $anomalies = [];

        $thresholds = [
            'response_time_increase' => 50, // 50% increase
            'error_rate_increase' => 100,   // 100% increase
            'request_volume_change' => 200, // 200% change
        ];

        if ($baseline['avg_response_time'] > 0) {
            $responseTimeChange = (($recent['avg_response_time'] - $baseline['avg_response_time']) / $baseline['avg_response_time']) * 100;
            if ($responseTimeChange > $thresholds['response_time_increase']) {
                $anomalies[] = [
                    'type' => 'high_response_time',
                    'message' => "Response time increased by {$responseTimeChange}%",
                    'current' => $recent['avg_response_time'],
                    'baseline' => $baseline['avg_response_time']
                ];
            }
        }

        if ($baseline['error_rate'] > 0) {
            $errorRateChange = (($recent['error_rate'] - $baseline['error_rate']) / $baseline['error_rate']) * 100;
            if ($errorRateChange > $thresholds['error_rate_increase']) {
                $anomalies[] = [
                    'type' => 'high_error_rate',
                    'message' => "Error rate increased by {$errorRateChange}%",
                    'current' => $recent['error_rate'],
                    'baseline' => $baseline['error_rate']
                ];
            }
        }

        $requestChange = abs(($recent['total_requests'] - $baseline['total_requests']) / max($baseline['total_requests'], 1) * 100);
        if ($requestChange > $thresholds['request_volume_change']) {
            $anomalies[] = [
                'type' => 'unusual_traffic',
                'message' => "Request volume changed by {$requestChange}%",
                'current' => $recent['total_requests'],
                'baseline' => $baseline['total_requests']
            ];
        }

        return $anomalies;
    }

    // System monitoring helpers
    protected function getMemoryUsage() { return memory_get_peak_usage(true); }
    protected function getCpuUsage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] ?? 0;
        }
        return 0;
    }
    protected function getDiskUsage() {
        $path = base_path();
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        return $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;
    }
    protected function getSystemUptime() {
        if (function_exists('posix_times')) {
            $times = posix_times();
            return $times['uptime'] ?? 0;
        }
        return 0;
    }
    protected function getLoadAverage() {
        return function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
    }
    protected function getDatabaseConnections() {
        try {
            return \Illuminate\Support\Facades\DB::getConnections();
        } catch (\Exception $e) {
            return 0;
        }
    }
    protected function getCacheHitRate() {
        // This would require cache statistics tracking
        return Cache::store()->getStore() instanceof \Illuminate\Cache\RedisStore ? 'N/A' : 'N/A';
    }
}