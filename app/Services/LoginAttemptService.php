<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class LoginAttemptService
{
    /**
     * Record a login attempt.
     */
    public static function recordAttempt(string $identifier, string $ipAddress, bool $success = false, ?User $user = null): array
    {
        $timestamp = now();
        $attemptId = $identifier . '_' . $ipAddress . '_' . $timestamp->timestamp;

        // Store attempt data
        Cache::put("login_attempt:{$attemptId}", [
            'identifier' => $identifier,
            'ip_address' => $ipAddress,
            'success' => $success,
            'user_id' => $user?->id,
            'timestamp' => $timestamp,
            'user_agent' => request()?->header('User-Agent'),
            'attempts_count' => self::getAttemptsCount($identifier, $ipAddress) + 1,
        ], now()->addHours(24));

        // Update statistics
        self::updateStatistics($identifier, $ipAddress, $success, $user);

        // Check for suspicious patterns
        $riskAssessment = self::assessRisk($identifier, $ipAddress);

        // Log security event if needed
        if ($riskAssessment['risk_level'] === 'high') {
            SecurityAuditService::logEvent('suspicious_login_pattern', [
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'user_id' => $user?->id,
                'risk_assessment' => $riskAssessment,
                'attempts_count' => $riskAssessment['attempts_count'],
            ], $user);
        }

        return [
            'attempt_id' => $attemptId,
            'timestamp' => $timestamp,
            'risk_assessment' => $riskAssessment,
            'is_blocked' => self::isBlocked($identifier, $ipAddress),
            'remaining_attempts' => self::getRemainingAttempts($identifier, $ipAddress),
            'block_expires_at' => self::getBlockExpiry($identifier, $ipAddress),
        ];
    }

    /**
     * Get attempts count for identifier and IP combination.
     */
    public static function getAttemptsCount(string $identifier, string $ipAddress): int
    {
        $recentAttempts = DB::table('user_activities')
            ->where('action', 'login_attempt')
            ->where('created_at', '>=', now()->subHour())
            ->where(function ($query) use ($identifier, $ipAddress) {
                $query->where('data->identifier', $identifier)
                      ->orWhere('ip_address', $ipAddress);
            })
            ->count();

        // Also check cache for current session
        $cacheKey = "login_attempts:{$identifier}:{$ipAddress}";
        $cacheCount = Cache::get($cacheKey, 0);

        return max($recentAttempts, $cacheCount);
    }

    /**
     * Check if identifier/IP combination is blocked.
     */
    public static function isBlocked(string $identifier, string $ipAddress): bool
    {
        $blockKey = "login_block:{$identifier}:{$ipAddress}";
        $blockExpiry = Cache::get($blockKey);
        
        return $blockExpiry !== null && $blockExpiry > now();
    }

    /**
     * Get remaining attempts before blocking.
     */
    public static function getRemainingAttempts(string $identifier, string $ipAddress): int
    {
        $maxAttempts = config('auth.max_login_attempts', 5);
        $attempts = self::getAttemptsCount($identifier, $ipAddress);
        
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Get block expiry time.
     */
    public static function getBlockExpiry(string $identifier, string $ipAddress): ?\Carbon\Carbon
    {
        $blockKey = "login_block:{$identifier}:{$ipAddress}";
        $blockExpiry = Cache::get($blockKey);
        
        return $blockExpiry ? \Carbon\Carbon::parse($blockExpiry) : null;
    }

    /**
     * Block identifier/IP combination.
     */
    public static function block(string $identifier, string $ipAddress, int $minutes = 60): void
    {
        $blockKey = "login_block:{$identifier}:{$ipAddress}";
        $expiry = now()->addMinutes($minutes);
        
        Cache::put($blockKey, $expiry, $expiry);
        
        // Log blocking event
        SecurityAuditService::logEvent('login_blocked', [
            'identifier' => $identifier,
            'ip_address' => $ipAddress,
            'block_duration_minutes' => $minutes,
            'expires_at' => $expiry,
        ]);

        // Check if this is a brute force attack
        if (self::isBruteForceAttack($identifier, $ipAddress)) {
            SecurityAuditService::logEvent('brute_force_attack', [
                'identifier' => $identifier,
                'ip_address' => $ipAddress,
                'attempts_count' => self::getAttemptsCount($identifier, $ipAddress),
                'block_duration_minutes' => $minutes,
            ]);
        }
    }

    /**
     * Unblock identifier/IP combination.
     */
    public static function unblock(string $identifier, string $ipAddress): void
    {
        $blockKey = "login_block:{$identifier}:{$ipAddress}";
        Cache::forget($blockKey);
        
        SecurityAuditService::logEvent('login_unblocked', [
            'identifier' => $identifier,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Assess risk level of login attempts.
     */
    protected static function assessRisk(string $identifier, string $ipAddress): array
    {
        $riskScore = 0;
        $riskFactors = [];

        // Check failure rate in last hour
        $recentAttempts = DB::table('user_activities')
            ->where('action', 'login_attempt')
            ->where('created_at', '>=', now()->subHour())
            ->where(function ($query) use ($identifier, $ipAddress) {
                $query->where('data->identifier', $identifier)
                      ->orWhere('ip_address', $ipAddress);
            })
            ->get();

        $failedAttempts = $recentAttempts->where('data->success', false)->count();
        $totalAttempts = $recentAttempts->count();
        $failureRate = $totalAttempts > 0 ? ($failedAttempts / $totalAttempts) : 0;

        if ($failureRate > 0.8) {
            $riskScore += 40;
            $riskFactors[] = 'High failure rate';
        }

        // Check for rapid attempts
        if ($totalAttempts >= 10) {
            $riskScore += 30;
            $riskFactors[] = 'Multiple rapid attempts';
        }

        // Check if IP is from different geographical location
        if (self::isSuspiciousGeoLocation($ipAddress)) {
            $riskScore += 20;
            $riskFactors[] = 'Suspicious geographic location';
        }

        // Check if using multiple identifiers
        $uniqueIdentifiers = $recentAttempts->unique('data->identifier')->count();
        if ($uniqueIdentifiers > 3) {
            $riskScore += 25;
            $riskFactors[] = 'Multiple identifier attempts';
        }

        // Check user agent patterns
        if (self::isSuspiciousUserAgent(request()?->header('User-Agent'))) {
            $riskScore += 15;
            $riskFactors[] = 'Suspicious user agent';
        }

        $riskLevel = match (true) {
            $riskScore >= 70 => 'critical',
            $riskScore >= 50 => 'high',
            $riskScore >= 30 => 'medium',
            default => 'low'
        };

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'attempts_count' => $totalAttempts,
            'failed_attempts' => $failedAttempts,
            'failure_rate' => round($failureRate * 100, 2),
        ];
    }

    /**
     * Check if this is a brute force attack.
     */
    protected static function isBruteForceAttack(string $identifier, string $ipAddress): bool
    {
        $recentFailures = DB::table('user_activities')
            ->where('action', 'login_attempt')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->where(function ($query) use ($identifier, $ipAddress) {
                $query->where('data->identifier', $identifier)
                      ->orWhere('ip_address', $ipAddress);
            })
            ->where('data->success', false)
            ->count();

        return $recentFailures >= 10;
    }

    /**
     * Check for suspicious geographic location.
     */
    protected static function isSuspiciousGeoLocation(string $ipAddress): bool
    {
        // In a real implementation, you would use a geolocation service
        // For now, we'll use a simple heuristic
        $privateRanges = [
            '192.168.',
            '10.',
            '172.',
            '127.',
            'localhost',
        ];

        foreach ($privateRanges as $range) {
            if (str_starts_with($ipAddress, $range)) {
                return false; // Private IP is not suspicious
            }
        }

        // Check for unusual patterns (simplified)
        return !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Check for suspicious user agent.
     */
    protected static function isSuspiciousUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) {
            return true;
        }

        $suspiciousPatterns = [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'curl',
            'wget',
            'python',
            'java',
            'php',
            'unknown',
            'null',
        ];

        $userAgentLower = strtolower($userAgent);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($userAgentLower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update login attempt statistics.
     */
    protected static function updateStatistics(string $identifier, string $ipAddress, bool $success, ?User $user): void
    {
        $cacheKey = "login_attempts:{$identifier}:{$ipAddress}";
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, now()->addHour());

        // Update global statistics
        if ($success) {
            $successKey = "login_success:{$ipAddress}";
            $currentSuccess = Cache::get($successKey, 0);
            Cache::put($successKey, $currentSuccess + 1, now()->addDay());
        } else {
            $failureKey = "login_failure:{$ipAddress}";
            $currentFailures = Cache::get($failureKey, 0);
            Cache::put($failureKey, $currentFailures + 1, now()->addDay());
        }
    }

    /**
     * Get login attempt statistics for an IP.
     */
    public static function getStatistics(string $ipAddress): array
    {
        return [
            'daily_successes' => Cache::get("login_success:{$ipAddress}", 0),
            'daily_failures' => Cache::get("login_failure:{$ipAddress}", 0),
            'current_attempts' => self::getAttemptsCount('any', $ipAddress),
            'is_blocked' => self::isBlocked('any', $ipAddress),
            'block_expires_at' => self::getBlockExpiry('any', $ipAddress),
        ];
    }

    /**
     * Clean up old login attempt data.
     */
    public static function cleanup(): int
    {
        $cleanedCount = 0;
        
        // Clean up cache entries older than 24 hours
        $cacheKeys = Cache::getStore()->getPrefix() ? Cache::getStore()->getPrefix() . '*login_*' : '*login_*';
        
        // This is a simplified cleanup - in production you might want a more sophisticated approach
        $cleanedCount += DB::table('user_activities')
            ->where('action', 'login_attempt')
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        return $cleanedCount;
    }
}