<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Str;

class IntrusionDetectionService
{
    protected $threatDb;
    protected $alertThresholds;
    protected $monitoringChannels = [];

    public function __construct()
    {
        $this->threatDb = config('security.threat_database', []);
        $this->alertThresholds = config('security.alert_thresholds', [
            'failed_logins' => 5,
            'sql_injection_attempts' => 1,
            'xss_attempts' => 1,
            'rate_limit_violations' => 10,
            'suspicious_user_agents' => 1
        ]);
    }

    /**
     * Monitor and detect intrusion attempts
     */
    public function monitorIntrusionAttempt(string $type, array $data): bool
    {
        $severity = $this->assessThreatSeverity($type, $data);
        $blocked = false;

        // Record the attempt
        $this->recordIntrusionAttempt($type, $data, $severity);

        // Check if immediate blocking is required
        if ($this->shouldBlockImmediately($type, $data, $severity)) {
            $this->blockSource($data['ip'] ?? 'unknown', $type, $severity);
            $blocked = true;
        }

        // Update threat score
        $threatScore = $this->updateThreatScore($data['ip'] ?? 'unknown', $type, $severity);

        // Send alerts if thresholds are exceeded
        if ($this->shouldSendAlert($type, $threatScore, $severity)) {
            $this->sendSecurityAlert($type, $data, $severity, $threatScore);
        }

        // Log the event
        $this->logSecurityEvent($type, $data, $severity, $threatScore, $blocked);

        return $blocked;
    }

    /**
     * Assess threat severity based on attack type and context
     */
    protected function assessThreatSeverity(string $type, array $data): string
    {
        $baseSeverity = $this->threatDb[$type]['severity'] ?? 'medium';
        $contextScore = $this->calculateContextScore($data);

        // Adjust severity based on context
        if ($contextScore > 8) {
            return $this->upgradeSeverity($baseSeverity);
        } elseif ($contextScore < 3) {
            return $this->downgradeSeverity($baseSeverity);
        }

        return $baseSeverity;
    }

    /**
     * Upgrade severity based on context
     */
    protected function upgradeSeverity(string $severity): string
    {
        return match($severity) {
            'low' => 'medium',
            'medium' => 'high',
            'high' => 'critical',
            'critical' => 'critical',
            default => 'medium'
        };
    }

    /**
     * Downgrade severity based on context
     */
    protected function downgradeSeverity(string $severity): string
    {
        return match($severity) {
            'critical' => 'high',
            'high' => 'medium',
            'medium' => 'low',
            'low' => 'low',
            default => 'low'
        };
    }

    /**
     * Check if IP is from high-risk location
     */
    protected function isHighRiskLocation(string $ip): bool
    {
        // This would integrate with geolocation services
        // For now, return false as a placeholder
        return false;
    }

    /**
     * Calculate context-based threat score
     */
    protected function calculateContextScore(array $data): int
    {
        $score = 0;
        $ip = $data['ip'] ?? '';
        $userAgent = $data['user_agent'] ?? '';

        // Check if IP is from known threat database
        if ($this->isKnownMaliciousIP($ip)) {
            $score += 5;
        }

        // Check if IP is from high-risk countries
        if ($this->isHighRiskLocation($ip)) {
            $score += 3;
        }

        // Check for suspicious user agent patterns
        if ($this->isSuspiciousUserAgent($userAgent)) {
            $score += 4;
        }

        // Check if attack targets sensitive areas
        if ($this->isTargetingSensitiveArea($data['path'] ?? '')) {
            $score += 3;
        }

        // Check for automation patterns
        if ($this->isAutomatedAttack($data)) {
            $score += 2;
        }

        return min($score, 10); // Cap at 10
    }

    /**
     * Check if intrusion attempt should be blocked immediately
     */
    protected function shouldBlockImmediately(string $type, array $data, string $severity): bool
    {
        $criticalTypes = ['sql_injection', 'command_injection', 'lfi_rfi', 'remote_code_execution'];
        
        if (in_array($type, $criticalTypes)) {
            return true;
        }

        if ($severity === 'critical') {
            return true;
        }

        // Check for repeated attacks from same IP
        $ip = $data['ip'] ?? '';
        if ($this->getRecentAttemptCount($ip, $type) >= 3) {
            return true;
        }

        return false;
    }

    /**
     * Block malicious source
     */
    protected function blockSource(string $ip, string $type, string $severity): void
    {
        $blockDuration = $this->getBlockDuration($severity);
        $blockKey = "blocked_ip:{$ip}";
        
        Cache::put($blockKey, [
            'blocked_at' => now(),
            'reason' => $type,
            'severity' => $severity,
            'expires_at' => now()->addMinutes($blockDuration)
        ], now()->addMinutes($blockDuration));

        // Add to persistent blocking list
        $this->addToPersistentBlocklist($ip, $type, $severity);

        // Log blocking action
        Log::channel('security')->warning('IP blocked', [
            'ip' => $ip,
            'type' => $type,
            'severity' => $severity,
            'duration' => $blockDuration
        ]);
    }

    /**
     * Update threat score for IP address
     */
    protected function updateThreatScore(string $ip, string $type, string $severity): int
    {
        $scoreMap = ['low' => 1, 'medium' => 3, 'high' => 5, 'critical' => 8];
        $score = $scoreMap[$severity] ?? 1;

        $currentScore = Cache::get("threat_score:{$ip}", 0);
        $newScore = min($currentScore + $score, 100);

        Cache::put("threat_score:{$ip}", $newScore, now()->addHours(24));

        return $newScore;
    }

    /**
     * Check if security alert should be sent
     */
    protected function shouldSendAlert(string $type, int $threatScore, string $severity): bool
    {
        // Always alert on critical severity
        if ($severity === 'critical') {
            return true;
        }

        // Check threshold-based alerts
        $recentCount = $this->getRecentAttemptCount($type);
        $threshold = $this->alertThresholds[$type] ?? 5;

        if ($recentCount >= $threshold) {
            return true;
        }

        // Check threat score threshold
        if ($threatScore >= 50) {
            return true;
        }

        return false;
    }

    /**
     * Send security alert
     */
    protected function sendSecurityAlert(string $type, array $data, string $severity, int $threatScore): void
    {
        $alert = [
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'severity' => $severity,
            'threat_score' => $threatScore,
            'timestamp' => now()->toISOString(),
            'source_ip' => $data['ip'] ?? 'unknown',
            'user_agent' => $data['user_agent'] ?? '',
            'path' => $data['path'] ?? '',
            'method' => $data['method'] ?? '',
            'data' => $data
        ];

        // Send to monitoring channels
        foreach ($this->monitoringChannels as $channel) {
            $this->sendToChannel($channel, $alert);
        }

        // Store in database for tracking
        $this->storeSecurityAlert($alert);

        // Send real-time notifications
        if (config('security.enable_real_time_alerts', false)) {
            $this->sendRealTimeNotifications($alert);
        }
    }

    /**
     * Log security event
     */
    protected function logSecurityEvent(string $type, array $data, string $severity, int $threatScore, bool $blocked): void
    {
        $logData = [
            'event_type' => $type,
            'severity' => $severity,
            'threat_score' => $threatScore,
            'blocked' => $blocked,
            'ip' => $data['ip'] ?? 'unknown',
            'user_agent' => $data['user_agent'] ?? '',
            'path' => $data['path'] ?? '',
            'method' => $data['method'] ?? '',
            'user_id' => $data['user_id'] ?? null,
            'session_id' => $data['session_id'] ?? null,
            'timestamp' => now()->toISOString(),
            'additional_data' => $this->sanitizeData($data)
        ];

        $logLevel = match($severity) {
            'critical' => 'critical',
            'high' => 'error',
            'medium' => 'warning',
            'low' => 'info',
            default => 'info'
        };

        Log::channel('security')->log($logLevel, "Intrusion attempt: {$type}", $logData);
    }

    /**
     * Record intrusion attempt for analysis
     */
    protected function recordIntrusionAttempt(string $type, array $data, string $severity): void
    {
        try {
            DB::table('intrusion_attempts')->insert([
                'id' => Str::uuid()->toString(),
                'type' => $type,
                'severity' => $severity,
                'source_ip' => $data['ip'] ?? 'unknown',
                'user_agent' => $data['user_agent'] ?? '',
                'path' => $data['path'] ?? '',
                'method' => $data['method'] ?? '',
                'user_id' => $data['user_id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'attack_data' => json_encode($this->sanitizeData($data)),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to record intrusion attempt', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get recent attempt count for analysis
     */
    protected function getRecentAttemptCount(string $ip, string $type = null): int
    {
        $key = "recent_attempts:{$ip}" . ($type ? ":{$type}" : '');
        $attempts = Cache::get($key, []);
        
        // Clean old attempts (older than 1 hour)
        $recentAttempts = array_filter($attempts, function($timestamp) {
            return Carbon::parse($timestamp)->isAfter(now()->subHour());
        });
        
        Cache::put($key, array_values($recentAttempts), now()->addHours(2));
        
        return count($recentAttempts);
    }

    /**
     * Add IP to persistent blocklist
     */
    protected function addToPersistentBlocklist(string $ip, string $type, string $severity): void
    {
        try {
            DB::table('blocked_ips')->updateOrInsert(
                ['ip_address' => $ip],
                [
                    'block_reason' => $type,
                    'severity' => $severity,
                    'blocked_at' => now(),
                    'expires_at' => now()->addDays(30),
                    'is_permanent' => $severity === 'critical',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to add IP to blocklist', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get block duration based on severity
     */
    protected function getBlockDuration(string $severity): int
    {
        return match($severity) {
            'critical' => 1440, // 24 hours
            'high' => 720,      // 12 hours
            'medium' => 240,    // 4 hours
            'low' => 60,        // 1 hour
            default => 30
        };
    }

    /**
     * Check if IP is from known malicious sources
     */
    protected function isKnownMaliciousIP(string $ip): bool
    {
        if (empty($ip)) return false;

        // Check local cache
        if (Cache::has("malicious_ip:{$ip}")) {
            return true;
        }

        // Check against threat intelligence feeds
        try {
            // This would integrate with threat intelligence APIs
            $response = Http::timeout(5)->get("https://api.threatintel.com/check/{$ip}");
            if ($response->successful() && $response->json('malicious', false)) {
                Cache::put("malicious_ip:{$ip}", true, now()->addDays(7));
                return true;
            }
        } catch (\Exception $e) {
            // Fail silently if threat intel service is unavailable
        }

        return false;
    }

    /**
     * Check if user agent is suspicious
     */
    protected function isSuspiciousUserAgent(string $userAgent): bool
    {
        if (empty($userAgent)) return true; // Empty user agent is suspicious

        $suspiciousPatterns = [
            'sqlmap', 'nikto', 'nessus', 'nmap', 'w3af', 'burp',
            'scanner', 'crawler', 'bot', 'spider', 'harvester',
            'exploit', 'attack', 'hack', 'penetration'
        ];

        $userAgent = strtolower($userAgent);
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if targeting sensitive areas
     */
    protected function isTargetingSensitiveArea(string $path): bool
    {
        $sensitiveAreas = [
            'admin', 'dashboard', 'api/auth', 'user/profile',
            'payment', 'billing', 'admin/login', 'wp-admin',
            'phpmyadmin', 'mysql', 'database'
        ];

        $path = strtolower($path);
        foreach ($sensitiveAreas as $area) {
            if (strpos($path, $area) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if attack shows automation patterns
     */
    protected function isAutomatedAttack(array $data): bool
    {
        // Check for rapid-fire requests
        $ip = $data['ip'] ?? '';
        $recentRequests = Cache::get("request_rate:{$ip}", 0);
        
        if ($recentRequests > 60) { // More than 60 requests per minute
            return true;
        }

        // Check for missing referrer or unusual timing
        if (empty($data['referer']) && !empty($data['path'])) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize sensitive data before logging
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential'];
        $sanitized = $data;

        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Store security alert in database
     */
    protected function storeSecurityAlert(array $alert): void
    {
        try {
            DB::table('security_alerts')->insert($alert);
        } catch (\Exception $e) {
            Log::error('Failed to store security alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send real-time notifications
     */
    protected function sendRealTimeNotifications(array $alert): void
    {
        // Send to Slack/Teams/Webhooks
        if (config('security.slack_webhook')) {
            $this->sendToSlack($alert);
        }

        // Send email alerts for critical issues
        if ($alert['severity'] === 'critical' && config('security.admin_email')) {
            $this->sendEmailAlert($alert);
        }

        // Send to security monitoring systems
        $this->sendToSecuritySystems($alert);
    }

    protected function sendToSlack(array $alert): void
    {
        try {
            $message = [
                'text' => "🚨 Security Alert: {$alert['type']}",
                'attachments' => [
                    [
                        'color' => $this->getAlertColor($alert['severity']),
                        'fields' => [
                            ['title' => 'Severity', 'value' => $alert['severity'], 'short' => true],
                            ['title' => 'IP Address', 'value' => $alert['source_ip'], 'short' => true],
                            ['title' => 'Threat Score', 'value' => $alert['threat_score'], 'short' => true],
                            ['title' => 'Path', 'value' => $alert['path'], 'short' => true]
                        ]
                    ]
                ]
            ];

            Http::post(config('security.slack_webhook'), $message);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', ['error' => $e->getMessage()]);
        }
    }

    protected function sendEmailAlert(array $alert): void
    {
        // Implementation would depend on your email service
        // This is a placeholder
    }

    protected function sendToSecuritySystems(array $alert): void
    {
        // Send to SIEM systems or other security monitoring tools
        // This is a placeholder for integration with external systems
    }

    protected function getAlertColor(string $severity): string
    {
        return match($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => '#ffcc00',
            'low' => 'good',
            default => '#cccccc'
        };
    }

    /**
     * Add monitoring channel
     */
    public function addMonitoringChannel(string $channel, array $config = []): void
    {
        $this->monitoringChannels[$channel] = $config;
    }

    /**
     * Get security statistics
     */
    public function getSecurityStats(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        
        try {
            return [
                'total_attempts' => DB::table('intrusion_attempts')
                    ->where('created_at', '>=', $since)
                    ->count(),
                'blocked_ips' => DB::table('blocked_ips')
                    ->where('created_at', '>=', $since)
                    ->count(),
                'critical_alerts' => DB::table('security_alerts')
                    ->where('created_at', '>=', $since)
                    ->where('severity', 'critical')
                    ->count(),
                'top_attack_types' => DB::table('intrusion_attempts')
                    ->where('created_at', '>=', $since)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get()
                    ->toArray(),
                'top_source_ips' => DB::table('intrusion_attempts')
                    ->where('created_at', '>=', $since)
                    ->selectRaw('source_ip, COUNT(*) as count')
                    ->groupBy('source_ip')
                    ->orderByDesc('count')
                    ->limit(10)
                    ->get()
                    ->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get security statistics', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Send alert to monitoring channel
     */
    protected function sendToChannel(string $channel, array $alert): void
    {
        $config = $this->monitoringChannels[$channel] ?? [];
        
        switch ($channel) {
            case 'database':
                $this->storeSecurityAlert($alert);
                break;
            case 'log':
                Log::channel('security_alerts')->info('Security alert', $alert);
                break;
            case 'webhook':
                if (isset($config['url'])) {
                    try {
                        Http::post($config['url'], $alert);
                    } catch (\Exception $e) {
                        Log::error('Failed to send webhook alert', ['error' => $e->getMessage()]);
                    }
                }
                break;
            case 'email':
                if (isset($config['to'])) {
                    // Send email alert
                    // Implementation would depend on your email service
                }
                break;
            default:
                Log::channel('security_alerts')->info("Security alert for channel: {$channel}", $alert);
        }
    }
}