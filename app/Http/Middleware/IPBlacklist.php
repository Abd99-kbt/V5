<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class IPBlacklist
{
    protected $exemptRoutes = [
        'health-check',
        'ping',
        'api/v1/health',
        'webhook'
    ];

    protected $whitelistPatterns = [
        '127.0.0.1',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16'
    ];

    /**
     * Handle an incoming request with enhanced IP security.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $this->getClientIP($request);
        
        // Skip checks for exempt routes
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        // Check if IP is whitelisted
        if ($this->isWhitelisted($ip)) {
            return $next($request);
        }

        // Comprehensive IP security checks
        if ($this->isBlocked($ip, $request)) {
            $this->logSecurityEvent($ip, 'IP_BLOCKED', 'IP address blocked', $request);
            return $this->blockedResponse();
        }

        // Monitor and analyze request patterns
        $this->monitorRequestPatterns($ip, $request);

        // Check for geo-blocking if enabled
        if ($this->isGeoBlocked($ip)) {
            $this->logSecurityEvent($ip, 'GEO_BLOCKED', 'Geographic location blocked', $request);
            return $this->blockedResponse();
        }

        // Check for proxy/Tor detection
        if ($this->isSuspiciousProxy($ip, $request)) {
            $this->addToBlacklist($ip, 'Suspicious proxy detected');
            $this->logSecurityEvent($ip, 'PROXY_BLOCKED', 'Suspicious proxy detected', $request);
            return $this->blockedResponse();
        }

        // Dynamic threat assessment
        $threatLevel = $this->assessThreatLevel($ip, $request);
        if ($threatLevel >= config('security.threat_level_block', 8)) {
            $this->addToBlacklist($ip, 'High threat level');
            $this->logSecurityEvent($ip, 'THREAT_LEVEL_BLOCKED', 'High threat level detected', $request);
            return $this->blockedResponse();
        }

        // Enhanced logging
        $this->logRequest($ip, $request, $threatLevel);

        $response = $next($request);

        // Add security response headers
        $this->addSecurityResponseHeaders($response, $ip);

        return $response;
    }

    protected function getClientIP(Request $request): string
    {
        // Check various headers for real IP
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                
                return $ip; // Fallback to first IP even if private
            }
        }

        return $request->ip();
    }

    protected function isExemptRoute(Request $request): bool
    {
        $path = $request->path();
        
        foreach ($this->exemptRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }

    protected function isWhitelisted(string $ip): bool
    {
        foreach ($this->whitelistPatterns as $pattern) {
            if ($this->ipInRange($ip, $pattern)) {
                return true;
            }
        }

        // Check cached whitelist
        return Cache::has("whitelist:{$ip}");
    }

    protected function isBlocked(string $ip, Request $request): bool
    {
        // Check multiple blacklist sources
        return $this->isInStaticBlacklist($ip) ||
               $this->isInDynamicBlacklist($ip) ||
               $this->isInDatabaseBlacklist($ip) ||
               $this->isInThreatIntelligence($ip);
    }

    protected function isInStaticBlacklist(string $ip): bool
    {
        $staticBlacklist = config('security.static_blacklist_ips', []);
        return in_array($ip, $staticBlacklist);
    }

    protected function isInDynamicBlacklist(string $ip): bool
    {
        return Cache::has("blacklist:dynamic:{$ip}");
    }

    protected function isInDatabaseBlacklist(string $ip): bool
    {
        try {
            return DB::table('security_ip_blacklist')
                ->where('ip_address', $ip)
                ->where('expires_at', '>', now())
                ->exists();
        } catch (\Exception $e) {
            Log::warning('Database blacklist check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    protected function isInThreatIntelligence(string $ip): bool
    {
        // Check against threat intelligence feeds
        $threatFeeds = config('security.threat_intelligence_feeds', []);
        
        foreach ($threatFeeds as $feed) {
            if ($this->checkThreatFeed($ip, $feed)) {
                return true;
            }
        }

        return false;
    }

    protected function checkThreatFeed(string $ip, string $feed): bool
    {
        // Implementation for checking threat intelligence feeds
        // This could integrate with services like VirusTotal, AbuseIPDB, etc.
        $cacheKey = "threat_feed:{$feed}:{$ip}";
        
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Simulate API call to threat intelligence service
        // In real implementation, this would make actual API calls
        Cache::put($cacheKey, false, now()->addHours(1));
        
        return false;
    }

    protected function monitorRequestPatterns(string $ip, Request $request): void
    {
        $patterns = [
            'requests_per_minute' => $this->checkRequestRate($ip),
            'failed_attempts' => $this->checkFailedAttempts($ip),
            'concurrent_connections' => $this->checkConcurrentConnections($ip),
            'user_agent_analysis' => $this->analyzeUserAgent($request),
            'request_size' => $this->analyzeRequestSize($request),
            'access_patterns' => $this->analyzeAccessPatterns($ip, $request)
        ];

        foreach ($patterns as $pattern => $score) {
            if ($score > config('security.pattern_threshold', 5)) {
                $this->incrementThreatScore($ip, $pattern, $score);
            }
        }
    }

    protected function checkRequestRate(string $ip): int
    {
        $cacheKey = "rate:{$ip}";
        $requests = Cache::get($cacheKey, 0);
        
        // Increment counter
        Cache::put($cacheKey, $requests + 1, now()->addMinute());
        
        return $requests + 1;
    }

    protected function checkFailedAttempts(string $ip): int
    {
        $cacheKey = "failed:{$ip}";
        return Cache::get($cacheKey, 0);
    }

    protected function checkConcurrentConnections(string $ip): int
    {
        $cacheKey = "connections:{$ip}";
        return Cache::get($cacheKey, 0);
    }

    protected function analyzeUserAgent(Request $request): int
    {
        $userAgent = $request->userAgent() ?? '';
        $suspiciousPatterns = [
            'sqlmap', 'nikto', 'nessus', 'nmap', 'openvas', 'w3af',
            'curl', 'wget', 'python', 'requests', 'scrapy'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return 10;
            }
        }

        // Check for missing or suspicious user agents
        if (empty($userAgent) || strlen($userAgent) < 10) {
            return 5;
        }

        return 0;
    }

    protected function analyzeRequestSize(Request $request): int
    {
        $size = $request->header('Content-Length', 0);
        
        // Flag very large requests
        if ($size > config('security.max_request_size', 10485760)) { // 10MB default
            return 8;
        }

        return 0;
    }

    protected function analyzeAccessPatterns(string $ip, Request $request): int
    {
        $patterns = [
            'multiple_404s' => $this->checkMultiple404s($ip),
            'sensitive_paths' => $this->checkSensitivePaths($request),
            'unusual_hours' => $this->checkUnusualHours(),
            'rapid_fire' => $this->checkRapidFireRequests($ip)
        ];

        return array_sum($patterns);
    }

    protected function checkMultiple404s(string $ip): int
    {
        $cacheKey = "404s:{$ip}";
        $count = Cache::get($cacheKey, 0);
        
        if ($count > config('security.max_404s', 10)) {
            return 7;
        }

        return 0;
    }

    protected function checkSensitivePaths(Request $request): int
    {
        $sensitivePaths = ['admin', 'wp-admin', 'phpmyadmin', 'config', '.env'];
        $path = strtolower($request->path());

        foreach ($sensitivePaths as $sensitivePath) {
            if (str_contains($path, $sensitivePath)) {
                return 6;
            }
        }

        return 0;
    }

    protected function checkUnusualHours(): int
    {
        $hour = now()->hour;
        
        // Flag requests during unusual hours (very early morning)
        if ($hour >= 1 && $hour <= 5) {
            return 3;
        }

        return 0;
    }

    protected function checkRapidFireRequests(string $ip): int
    {
        $cacheKey = "rapid:{$ip}";
        $timestamps = Cache::get($cacheKey, []);
        
        $now = microtime(true);
        $recentRequests = array_filter($timestamps, function($timestamp) use ($now) {
            return ($now - $timestamp) < 1; // Within last second
        });

        if (count($recentRequests) > config('security.max_requests_per_second', 10)) {
            return 9;
        }

        // Store current timestamp
        $timestamps[] = $now;
        Cache::put($cacheKey, $timestamps, now()->addMinutes(5));

        return 0;
    }

    protected function incrementThreatScore(string $ip, string $pattern, int $score): void
    {
        $cacheKey = "threat_score:{$ip}";
        $currentScore = Cache::get($cacheKey, 0);
        $newScore = $currentScore + $score;
        
        Cache::put($cacheKey, $newScore, now()->addHours(2));

        // If score exceeds threshold, add to temporary blacklist
        if ($newScore >= config('security.threat_score_threshold', 15)) {
            $this->addToBlacklist($ip, "High threat score: {$pattern}");
        }
    }

    protected function assessThreatLevel(string $ip, Request $request): int
    {
        $threatIndicators = [
            'blocked_before' => $this->wasBlockedBefore($ip) ? 10 : 0,
            'reputation_score' => $this->getReputationScore($ip),
            'behavioral_analysis' => $this->getBehavioralScore($ip, $request),
            'velocity_attacks' => $this->detectVelocityAttacks($ip) ? 8 : 0
        ];

        return array_sum($threatIndicators);
    }

    protected function wasBlockedBefore(string $ip): bool
    {
        $cacheKey = "previous_block:{$ip}";
        return Cache::has($cacheKey);
    }

    protected function getReputationScore(string $ip): int
    {
        // Integration with IP reputation services
        $cacheKey = "reputation:{$ip}";
        return Cache::get($cacheKey, 5); // Default neutral score
    }

    protected function getBehavioralScore(string $ip, Request $request): int
    {
        // Analyze historical behavior patterns
        $behaviorKey = "behavior:{$ip}";
        $behaviorData = Cache::get($behaviorKey, []);
        
        $score = 0;
        
        // Check for consistent patterns that indicate automation
        if (isset($behaviorData['user_agents'])) {
            $uniqueAgents = count($behaviorData['user_agents']);
            if ($uniqueAgents > 10) {
                $score += 3;
            }
        }

        // Store current behavior
        $behaviorData['user_agents'][] = $request->userAgent();
        $behaviorData['last_activity'] = now();
        
        Cache::put($behaviorKey, $behaviorData, now()->addDays(7));
        
        return $score;
    }

    protected function detectVelocityAttacks(string $ip): bool
    {
        $cacheKey = "velocity:{$ip}";
        $requests = Cache::get($cacheKey, []);
        
        $now = microtime(true);
        $recentRequests = array_filter($requests, function($timestamp) use ($now) {
            return ($now - $timestamp) < 5; // Within 5 seconds
        });

        if (count($recentRequests) > 20) { // More than 20 requests in 5 seconds
            return true;
        }

        // Add current request
        $requests[] = $now;
        Cache::put($cacheKey, $requests, now()->addMinutes(10));

        return false;
    }

    protected function isGeoBlocked(string $ip): bool
    {
        if (!config('security.enable_geo_blocking', false)) {
            return false;
        }

        $allowedCountries = config('security.allowed_countries', []);
        $blockedCountries = config('security.blocked_countries', []);

        // Get country from IP (using a geo-location service)
        $country = $this->getCountryFromIP($ip);
        
        if (in_array($country, $blockedCountries)) {
            return true;
        }

        if (!empty($allowedCountries) && !in_array($country, $allowedCountries)) {
            return true;
        }

        return false;
    }

    protected function getCountryFromIP(string $ip): string
    {
        // Implementation would use a geo-location service
        // For now, return a default country
        return config('security.default_country', 'US');
    }

    protected function isSuspiciousProxy(string $ip, Request $request): bool
    {
        $proxyHeaders = [
            'HTTP_VIA',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];

        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                // Additional checks for proxy indicators
                $value = $_SERVER[$header];
                if (strpos($value, ',') !== false || strpos($value, ':') !== false) {
                    return true;
                }
            }
        }

        // Check if IP is from known proxy ranges
        $proxyRanges = config('security.proxy_ranges', []);
        foreach ($proxyRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    protected function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') !== false) {
            // CIDR notation
            list($subnet, $mask) = explode('/', $range);
            return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet);
        } else {
            // Single IP
            return $ip === $range;
        }
    }

    protected function addToBlacklist(string $ip, string $reason): void
    {
        $duration = now()->addHours(config('security.blacklist_duration_hours', 24));
        
        // Add to dynamic blacklist
        Cache::put("blacklist:dynamic:{$ip}", [
            'reason' => $reason,
            'timestamp' => now(),
            'expires_at' => $duration
        ], $duration);

        // Add to database blacklist
        try {
            DB::table('security_ip_blacklist')->updateOrInsert(
                ['ip_address' => $ip],
                [
                    'reason' => $reason,
                    'created_at' => now(),
                    'expires_at' => $duration
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to add IP to database blacklist', ['error' => $e->getMessage()]);
        }

        // Track for future reference
        Cache::put("previous_block:{$ip}", true, now()->addDays(30));

        // Send alert if configured
        $this->sendBlockAlert($ip, $reason);
    }

    protected function sendBlockAlert(string $ip, string $reason): void
    {
        if (config('security.enable_block_alerts', false)) {
            Log::channel('security')->alert('IP Blocked', [
                'ip' => $ip,
                'reason' => $reason,
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    protected function blockedResponse(): Response
    {
        return response()->json([
            'error' => 'Access denied',
            'message' => 'Your IP address has been blocked due to suspicious activity',
            'timestamp' => now()->toISOString(),
            'retry_after' => config('security.block_duration_minutes', 60) * 60
        ], 403);
    }

    protected function logSecurityEvent(string $ip, string $event, string $message, Request $request = null): void
    {
        $data = [
            'event' => $event,
            'ip' => $ip,
            'timestamp' => now()->toISOString(),
            'fingerprint' => hash('sha256', $ip . now()->toDateString())
        ];

        if ($request) {
            $data = array_merge($data, [
                'path' => $request->path(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('Referer'),
                'content_length' => $request->header('Content-Length')
            ]);
        }

        Log::channel('security')->warning($message, $data);
    }

    protected function logRequest(string $ip, Request $request, int $threatLevel): void
    {
        if (!config('security.log_all_requests', false) && $threatLevel < 3) {
            return;
        }

        $this->logSecurityEvent($ip, 'REQUEST_LOGGED', 'Request logged', $request);
    }

    protected function addSecurityResponseHeaders(Response $response, string $ip): void
    {
        $response->headers->set('X-Blocked-IP', $ip);
        $response->headers->set('X-Request-ID', Str::uuid()->toString());
        $response->headers->set('X-Security-Policy', 'strict');
    }
}