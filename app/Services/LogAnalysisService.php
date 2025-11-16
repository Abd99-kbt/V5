<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class LogAnalysisService
{
    /**
     * Cache key prefix for log analysis
     */
    protected string $cachePrefix = 'log_analysis_';

    /**
     * Patterns to mask sensitive data in logs
     */
    protected array $sensitivePatterns = [
        'password' => '[REDACTED_PASSWORD]',
        'token' => '[REDACTED_TOKEN]',
        'api_key' => '[REDACTED_API_KEY]',
        'secret' => '[REDACTED_SECRET]',
        'credit_card' => '[REDACTED_CC]',
        'ssn' => '[REDACTED_SSN]',
        'email' => '[REDACTED_EMAIL]',
        'phone' => '[REDACTED_PHONE]',
    ];

    /**
     * Log levels for production (RFC 5424)
     */
    protected array $logLevels = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /**
     * Analyze recent logs and extract insights
     */
    public function analyzeLogs(array $options = []): array
    {
        $timeRange = $options['time_range'] ?? '1h';
        $levels = $options['levels'] ?? ['error', 'warning', 'critical'];
        $source = $options['source'] ?? 'application';
        
        $analysis = [
            'timestamp' => now()->toISOString(),
            'time_range' => $timeRange,
            'source' => $source,
            'summary' => [],
            'patterns' => [],
            'errors' => [],
            'performance_issues' => [],
            'security_events' => [],
            'recommendations' => [],
        ];

        try {
            // Get log data based on source
            $logs = $this->getLogs($timeRange, $levels, $source);
            
            // Analyze log patterns
            $analysis['patterns'] = $this->analyzeLogPatterns($logs);
            
            // Extract errors and issues
            $analysis['errors'] = $this->extractErrors($logs);
            
            // Identify performance issues
            $analysis['performance_issues'] = $this->identifyPerformanceIssues($logs);
            
            // Detect security events
            $analysis['security_events'] = $this->detectSecurityEvents($logs);
            
            // Generate summary statistics
            $analysis['summary'] = $this->generateLogSummary($logs);
            
            // Generate recommendations
            $analysis['recommendations'] = $this->generateRecommendations($analysis);
            
            // Cache analysis results
            $this->cacheAnalysisResults($analysis);
            
        } catch (\Exception $e) {
            Log::error('Log analysis failed', ['error' => $e->getMessage()]);
            $analysis['error'] = $e->getMessage();
        }

        return $analysis;
    }

    /**
     * Get logs from various sources
     */
    protected function getLogs(string $timeRange, array $levels, string $source): array
    {
        $logs = [];
        $since = $this->parseTimeRange($timeRange);
        
        switch ($source) {
            case 'application':
                $logs = $this->getApplicationLogs($since, $levels);
                break;
            case 'system':
                $logs = $this->getSystemLogs($since, $levels);
                break;
            case 'database':
                $logs = $this->getDatabaseLogs($since, $levels);
                break;
            case 'nginx':
                $logs = $this->getNginxLogs($since, $levels);
                break;
            case 'laravel':
                $logs = $this->getLaravelLogs($since, $levels);
                break;
        }
        
        return $logs;
    }

    /**
     * Get Laravel application logs
     */
    protected function getApplicationLogs(Carbon $since, array $levels): array
    {
        $logs = [];
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            return $logs;
        }

        $logContent = file_get_contents($logPath);
        $lines = explode("\n", $logContent);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parsedLog = $this->parseLogLine($line);
            if ($parsedLog && $this->shouldIncludeLog($parsedLog, $since, $levels)) {
                $logs[] = $this->maskSensitiveData($parsedLog);
            }
        }
        
        return array_reverse($logs); // Most recent first
    }

    /**
     * Get system logs
     */
    protected function getSystemLogs(Carbon $since, array $levels): array
    {
        $logs = [];
        
        // Read system logs (varies by OS)
        $logFiles = [
            '/var/log/syslog',
            '/var/log/messages',
            '/var/log/kern.log',
        ];
        
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile) && is_readable($logFile)) {
                $content = file_get_contents($logFile);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    $parsedLog = $this->parseSystemLogLine($line);
                    if ($parsedLog && $this->shouldIncludeLog($parsedLog, $since, $levels)) {
                        $logs[] = $this->maskSensitiveData($parsedLog);
                    }
                }
            }
        }
        
        return array_slice($logs, -1000); // Limit to last 1000 entries
    }

    /**
     * Get database logs
     */
    protected function getDatabaseLogs(Carbon $since, array $levels): array
    {
        $logs = [];
        
        try {
            // Get slow query log from database
            $slowQueries = DB::connection()->select("
                SELECT * FROM mysql.slow_log 
                WHERE start_time >= ? 
                ORDER BY start_time DESC 
                LIMIT 100
            ", [$since]);
            
            foreach ($slowQueries as $query) {
                $logs[] = [
                    'timestamp' => $query->start_time,
                    'level' => 'warning',
                    'source' => 'database',
                    'message' => 'Slow query detected',
                    'query_time' => $query->query_time,
                    'lock_time' => $query->lock_time,
                    'rows_sent' => $query->rows_sent,
                    'rows_examined' => $query->rows_examined,
                    'sql_text' => $query->sql_text,
                    'context' => [
                        'db' => $query->db,
                        'user' => $query->user_host,
                    ]
                ];
            }
        } catch (\Exception $e) {
            // Database log access might be restricted
        }
        
        return $logs;
    }

    /**
     * Get Nginx access logs
     */
    protected function getNginxLogs(Carbon $since, array $levels): array
    {
        $logs = [];
        $logPath = '/var/log/nginx/access.log';
        
        if (!file_exists($logPath) || !is_readable($logPath)) {
            return $logs;
        }

        $logContent = file_get_contents($logPath);
        $lines = explode("\n", $logContent);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parsedLog = $this->parseNginxLogLine($line);
            if ($parsedLog && $parsedLog['timestamp'] >= $since && 
                in_array($this->mapStatusCodeToLevel($parsedLog['status_code']), $levels)) {
                $logs[] = $this->maskSensitiveData($parsedLog);
            }
        }
        
        return array_slice($logs, -500); // Limit to last 500 entries
    }

    /**
     * Parse Laravel log line
     */
    protected function parseLogLine(string $line): ?array
    {
        // Laravel log format: [YYYY-MM-DD HH:MM:SS] LEVEL: message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] ([A-Z]+): (.+)$/', $line, $matches)) {
            return [
                'timestamp' => Carbon::parse($matches[1]),
                'level' => strtolower($matches[2]),
                'source' => 'laravel',
                'message' => $matches[3],
                'context' => $this->extractContextFromMessage($matches[3]),
            ];
        }
        
        // JSON format logs
        if (str_starts_with($line, '{')) {
            $json = json_decode($line, true);
            if ($json && isset($json['timestamp']) && isset($json['level'])) {
                return array_merge($json, [
                    'timestamp' => Carbon::parse($json['timestamp']),
                    'source' => $json['source'] ?? 'laravel',
                ]);
            }
        }
        
        return null;
    }

    /**
     * Parse system log line
     */
    protected function parseSystemLogLine(string $line): ?array
    {
        // Basic syslog format: Month Day Time Hostname service[pid]: message
        if (preg_match('/^([A-Za-z]{3}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+(\S+)\s+([^\s]+)(?:\[(\d+)\])?:\s+(.+)$/', $line, $matches)) {
            return [
                'timestamp' => Carbon::parse($matches[1] . ' ' . date('Y')), // Add current year
                'level' => $this->mapSyslogFacilityToLevel($matches[3]),
                'source' => 'system',
                'host' => $matches[2],
                'service' => $matches[3],
                'pid' => $matches[4] ?? null,
                'message' => $matches[5],
                'context' => [],
            ];
        }
        
        return null;
    }

    /**
     * Parse Nginx log line
     */
    protected function parseNginxLogLine(string $line): ?array
    {
        // Nginx access log format: IP - - [timestamp] "METHOD PATH" status size "referer" "user-agent"
        if (preg_match('/^(\S+)\s+-\s+-\s+\[([^\]]+)\]\s+"([^"]+)"\s+(\d{3})\s+(\S+)\s+"([^"]*)"\s+"([^"]*)"/', $line, $matches)) {
            $requestParts = explode(' ', $matches[3]);
            
            return [
                'timestamp' => Carbon::parse(str_replace(':', ' ', $matches[2])),
                'level' => $this->mapStatusCodeToLevel((int)$matches[4]),
                'source' => 'nginx',
                'ip' => $matches[1],
                'method' => $requestParts[0] ?? '',
                'path' => $requestParts[1] ?? '',
                'status_code' => (int)$matches[4],
                'response_size' => $matches[5],
                'referer' => $matches[6],
                'user_agent' => $matches[7],
                'message' => "HTTP {$matches[4]} - " . ($requestParts[1] ?? ''),
                'context' => [],
            ];
        }
        
        return null;
    }

    /**
     * Extract context data from log message
     */
    protected function extractContextFromMessage(string $message): array
    {
        $context = [];
        
        // Look for JSON in message
        if (preg_match('/\{.*\}/', $message, $matches)) {
            try {
                $jsonData = json_decode($matches[0], true);
                if ($jsonData) {
                    $context = $jsonData;
                }
            } catch (\Exception $e) {
                // Invalid JSON, ignore
            }
        }
        
        // Look for key=value pairs
        if (preg_match_all('/(\w+)=([^\s]+)/', $message, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $context[$match[1]] = $match[2];
            }
        }
        
        return $context;
    }

    /**
     * Mask sensitive data in logs
     */
    public function maskSensitiveData(array $logEntry): array
    {
        $logEntry = $this->deepMask($logEntry);
        
        // Apply regex patterns for sensitive data
        foreach (['message', 'context', 'sql_text'] as $field) {
            if (isset($logEntry[$field]) && is_string($logEntry[$field])) {
                $logEntry[$field] = $this->applyMaskingPatterns($logEntry[$field]);
            }
        }
        
        return $logEntry;
    }

    /**
     * Apply masking patterns to text
     */
    protected function applyMaskingPatterns(string $text): string
    {
        // Mask email addresses
        $text = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[REDACTED_EMAIL]', $text);
        
        // Mask credit card numbers
        $text = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[REDACTED_CC]', $text);
        
        // Mask phone numbers
        $text = preg_replace('/\b(\+?\d{1,3}[-.\s]?)?(\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}\b/', '[REDACTED_PHONE]', $text);
        
        // Mask API keys and tokens
        $text = preg_replace('/(api[_-]?key|token|secret)["\s]*[:=]["\s]*([^"\s,}]+)/i', '$1: [REDACTED]', $text);
        $text = preg_replace('/(Bearer\s+)[A-Za-z0-9\-._~+/]+=*/', '$1[REDACTED_TOKEN]', $text);
        
        // Mask passwords
        $text = preg_replace('/(password|passwd|pwd)["\s]*[:=]["\s]*([^"\s,}]+)/i', '$1: [REDACTED_PASSWORD]', $text);
        
        return $text;
    }

    /**
     * Deep mask array values recursively
     */
    protected function deepMask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->deepMask($value);
            } elseif (is_string($value)) {
                // Check if the key indicates sensitive data
                foreach (array_keys($this->sensitivePatterns) as $sensitiveKey) {
                    if (stripos($key, $sensitiveKey) !== false) {
                        $data[$key] = $this->sensitivePatterns[$sensitiveKey];
                        break;
                    }
                }
            }
        }
        
        return $data;
    }

    /**
     * Analyze log patterns
     */
    protected function analyzeLogPatterns(array $logs): array
    {
        $patterns = [
            'error_patterns' => [],
            'frequency_analysis' => [],
            'time_distribution' => [],
        ];
        
        // Analyze error patterns
        $errorMessages = array_filter($logs, fn($log) => in_array($log['level'], ['error', 'critical', 'emergency']));
        $patternCounts = [];
        
        foreach ($errorMessages as $log) {
            $pattern = $this->extractErrorPattern($log['message']);
            $patternCounts[$pattern] = ($patternCounts[$pattern] ?? 0) + 1;
        }
        
        $patterns['error_patterns'] = array_slice(collect($patternCounts)->sortDesc()->toArray(), 0, 10, true);
        
        // Analyze time distribution
        $hourlyDistribution = [];
        foreach ($logs as $log) {
            $hour = $log['timestamp']->hour;
            $hourlyDistribution[$hour] = ($hourlyDistribution[$hour] ?? 0) + 1;
        }
        $patterns['time_distribution'] = $hourlyDistribution;
        
        return $patterns;
    }

    /**
     * Extract error pattern from message
     */
    protected function extractErrorPattern(string $message): string
    {
        // Remove dynamic parts like IDs, timestamps, etc.
        $pattern = preg_replace('/\b\d{1,10}\b/', 'N', $message);
        $pattern = preg_replace('/\b[A-Fa-f0-9]{8,}\b/', 'HEX', $pattern);
        $pattern = preg_replace('/\b[\w.-]+@[\w.-]+\.\w+\b/', 'EMAIL', $pattern);
        
        return $pattern;
    }

    /**
     * Extract and categorize errors
     */
    protected function extractErrors(array $logs): array
    {
        $errors = [];
        
        foreach ($logs as $log) {
            if (in_array($log['level'], ['error', 'critical', 'emergency'])) {
                $errors[] = [
                    'timestamp' => $log['timestamp'],
                    'level' => $log['level'],
                    'source' => $log['source'],
                    'message' => $log['message'],
                    'context' => $log['context'] ?? [],
                    'count' => 1,
                ];
            }
        }
        
        // Group similar errors
        $groupedErrors = [];
        foreach ($errors as $error) {
            $pattern = $this->extractErrorPattern($error['message']);
            $key = $pattern . '_' . $error['level'];
            
            if (!isset($groupedErrors[$key])) {
                $groupedErrors[$key] = $error;
                $groupedErrors[$key]['first_occurrence'] = $error['timestamp'];
                $groupedErrors[$key]['last_occurrence'] = $error['timestamp'];
            } else {
                $groupedErrors[$key]['count']++;
                if ($error['timestamp'] > $groupedErrors[$key]['last_occurrence']) {
                    $groupedErrors[$key]['last_occurrence'] = $error['timestamp'];
                }
            }
        }
        
        return array_values($groupedErrors);
    }

    /**
     * Identify performance issues from logs
     */
    protected function identifyPerformanceIssues(array $logs): array
    {
        $issues = [];
        
        // Look for slow query indicators
        foreach ($logs as $log) {
            if (str_contains(strtolower($log['message']), 'slow') || 
                (isset($log['query_time']) && $log['query_time'] > 1.0)) {
                $issues[] = [
                    'type' => 'slow_query',
                    'timestamp' => $log['timestamp'],
                    'message' => $log['message'],
                    'severity' => 'warning',
                ];
            }
            
            // Look for memory issues
            if (str_contains(strtolower($log['message']), 'memory') || 
                str_contains(strtolower($log['message']), 'exhausted')) {
                $issues[] = [
                    'type' => 'memory_issue',
                    'timestamp' => $log['timestamp'],
                    'message' => $log['message'],
                    'severity' => 'critical',
                ];
            }
            
            // Look for timeout issues
            if (str_contains(strtolower($log['message']), 'timeout') || 
                str_contains(strtolower($log['message']), 'timed out')) {
                $issues[] = [
                    'type' => 'timeout',
                    'timestamp' => $log['timestamp'],
                    'message' => $log['message'],
                    'severity' => 'warning',
                ];
            }
        }
        
        return $issues;
    }

    /**
     * Detect security events
     */
    protected function detectSecurityEvents(array $logs): array
    {
        $events = [];
        
        foreach ($logs as $log) {
            $message = strtolower($log['message']);
            
            // Authentication failures
            if (str_contains($message, 'authentication failed') || 
                str_contains($message, 'login failed') ||
                str_contains($message, 'invalid credentials')) {
                $events[] = [
                    'type' => 'auth_failure',
                    'timestamp' => $log['timestamp'],
                    'message' => $log['message'],
                    'source' => $log['source'],
                    'severity' => 'warning',
                ];
            }
            
            // Potential attacks
            if (str_contains($message, 'sql injection') || 
                str_contains($message, 'xss') ||
                str_contains($message, 'csrf') ||
                str_contains($message, 'unauthorized')) {
                $events[] = [
                    'type' => 'potential_attack',
                    'timestamp' => $log['timestamp'],
                    'message' => $log['message'],
                    'source' => $log['source'],
                    'severity' => 'critical',
                ];
            }
            
            // Rate limiting
            if (str_contains($message, 'rate limit') || 
                str_contains($message, 'too many requests')) {
                $events[] = [
                    'type' => 'rate_limiting',
                    'timestamp' => $log['timestamp'],
                    'message' => $log['message'],
                    'source' => $log['source'],
                    'severity' => 'info',
                ];
            }
        }
        
        return $events;
    }

    /**
     * Generate log summary statistics
     */
    protected function generateLogSummary(array $logs): array
    {
        $summary = [
            'total_logs' => count($logs),
            'level_distribution' => [],
            'source_distribution' => [],
            'time_span' => [
                'start' => $logs[array_key_last($logs)]['timestamp'] ?? now(),
                'end' => $logs[0]['timestamp'] ?? now(),
            ],
        ];
        
        // Level distribution
        $levels = array_count_values(array_column($logs, 'level'));
        $summary['level_distribution'] = $levels;
        
        // Source distribution
        $sources = array_count_values(array_column($logs, 'source'));
        $summary['source_distribution'] = $sources;
        
        return $summary;
    }

    /**
     * Generate recommendations based on analysis
     */
    protected function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        // Error-based recommendations
        $errorCount = count($analysis['errors']);
        if ($errorCount > 50) {
            $recommendations[] = 'High error count detected - investigate root causes and implement fixes';
        }
        
        // Performance recommendations
        $perfIssues = array_filter($analysis['performance_issues'], fn($issue) => $issue['severity'] === 'critical');
        if (!empty($perfIssues)) {
            $recommendations[] = 'Critical performance issues detected - prioritize optimization';
        }
        
        // Security recommendations
        $securityEvents = array_filter($analysis['security_events'], fn($event) => $event['severity'] === 'critical');
        if (!empty($securityEvents)) {
            $recommendations[] = 'Critical security events detected - review security measures immediately';
        }
        
        // Log volume recommendations
        $logCount = $analysis['summary']['total_logs'] ?? 0;
        if ($logCount > 10000) {
            $recommendations[] = 'High log volume - consider implementing log rotation and aggregation';
        }
        
        return $recommendations;
    }

    // Helper methods

    protected function parseTimeRange(string $timeRange): Carbon
    {
        $now = now();
        
        return match(strtolower($timeRange)) {
            '5m' => $now->subMinutes(5),
            '15m' => $now->subMinutes(15),
            '30m' => $now->subMinutes(30),
            '1h' => $now->subHour(),
            '6h' => $now->subHours(6),
            '24h' => $now->subDay(),
            '7d' => $now->subDays(7),
            '30d' => $now->subDays(30),
            default => $now->subHour(),
        };
    }

    protected function shouldIncludeLog(array $log, Carbon $since, array $levels): bool
    {
        return $log['timestamp'] >= $since && in_array($log['level'], $levels);
    }

    protected function mapStatusCodeToLevel(int $statusCode): string
    {
        if ($statusCode >= 500) return 'error';
        if ($statusCode >= 400) return 'warning';
        if ($statusCode >= 300) return 'info';
        return 'debug';
    }

    protected function mapSyslogFacilityToLevel(string $service): string
    {
        // Map common system services to log levels
        $serviceLevels = [
            'auth' => 'warning',
            'cron' => 'info',
            'sshd' => 'warning',
            'nginx' => 'info',
            'mysql' => 'warning',
        ];
        
        return $serviceLevels[$service] ?? 'info';
    }

    protected function getLaravelLogs(Carbon $since, array $levels): array
    {
        // This is the same as getApplicationLogs for Laravel
        return $this->getApplicationLogs($since, $levels);
    }

    protected function cacheAnalysisResults(array $analysis): void
    {
        $cacheKey = $this->cachePrefix . 'latest';
        Cache::put($cacheKey, $analysis, now()->addMinutes(15));
    }

    public function getCachedAnalysis(): ?array
    {
        $cacheKey = $this->cachePrefix . 'latest';
        return Cache::get($cacheKey);
    }
}