<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

class SecurityService
{
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Validate domain against license restrictions
     */
    public function validateDomain($domain, $license = null)
    {
        if (!$license || empty($license->allowed_domains)) {
            return ['valid' => true, 'reason' => 'No domain restrictions'];
        }

        $allowedDomains = is_array($license->allowed_domains)
            ? $license->allowed_domains
            : json_decode($license->allowed_domains, true);

        if (!is_array($allowedDomains)) {
            return ['valid' => false, 'reason' => 'Invalid domain configuration'];
        }

        // Check exact match
        if (in_array($domain, $allowedDomains)) {
            return ['valid' => true];
        }

        // Check wildcard domains
        foreach ($allowedDomains as $allowedDomain) {
            if ($this->matchesWildcard($domain, $allowedDomain)) {
                return ['valid' => true];
            }
        }

        Log::warning('Domain validation failed', [
            'domain' => $domain,
            'allowed_domains' => $allowedDomains
        ]);

        return ['valid' => false, 'reason' => 'Domain not allowed'];
    }

    /**
     * Validate IP against license restrictions
     */
    public function validateIp($ip, $license = null)
    {
        // Skip validation for localhost/private IPs in development
        if ($this->isPrivateIp($ip) && app()->environment('local')) {
            return ['valid' => true, 'reason' => 'Private IP in development'];
        }

        if (!$license || empty($license->allowed_ips)) {
            return ['valid' => true, 'reason' => 'No IP restrictions'];
        }

        $allowedIps = is_array($license->allowed_ips)
            ? $license->allowed_ips
            : json_decode($license->allowed_ips, true);

        if (!is_array($allowedIps)) {
            return ['valid' => false, 'reason' => 'Invalid IP configuration'];
        }

        // Check exact match
        if (in_array($ip, $allowedIps)) {
            return ['valid' => true];
        }

        // Check CIDR ranges
        foreach ($allowedIps as $allowedIp) {
            if ($this->ipInRange($ip, $allowedIp)) {
                return ['valid' => true];
            }
        }

        Log::warning('IP validation failed', [
            'ip' => $ip,
            'allowed_ips' => $allowedIps
        ]);

        return ['valid' => false, 'reason' => 'IP not allowed'];
    }

    /**
     * Check if domain matches wildcard pattern
     */
    protected function matchesWildcard($domain, $pattern)
    {
        $pattern = str_replace('\*', '.*', preg_quote($pattern, '/'));
        return preg_match('/^' . $pattern . '$/', $domain);
    }

    /**
     * Check if IP is in CIDR range
     */
    protected function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $mask) = explode('/', $range);

        if (!filter_var($subnet, FILTER_VALIDATE_IP) || !is_numeric($mask) || $mask < 0 || $mask > 32) {
            return false;
        }

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $mask);

        return ($ip & $mask) === ($subnet & $mask);
    }

    /**
     * Check if IP is private/localhost
     */
    protected function isPrivateIp($ip)
    {
        $privateRanges = [
            '127.0.0.0/8',    // localhost
            '10.0.0.0/8',     // private class A
            '172.16.0.0/12',  // private class B
            '192.168.0.0/16', // private class C
            '169.254.0.0/16', // link-local
        ];

        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if IP is using VPN
     */
    public function isVpnIp($ip)
    {
        if (config('license.block_vpn', false)) {
            $cacheKey = 'vpn_check_' . md5($ip);

            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip) {
                try {
                    // Use a VPN detection service (example API)
                    $response = Http::timeout(5)->get("https://ipapi.co/{$ip}/json/");

                    if ($response->successful()) {
                        $data = $response->json();
                        return isset($data['privacy']) && $data['privacy'] === true;
                    }
                } catch (\Exception $e) {
                    Log::debug('VPN check failed', ['ip' => $ip, 'error' => $e->getMessage()]);
                }

                return false;
            });
        }

        return false;
    }

    /**
     * Detect if IP is using Tor
     */
    public function isTorIp($ip)
    {
        if (config('license.block_tor', true)) {
            $cacheKey = 'tor_check_' . md5($ip);

            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($ip) {
                try {
                    // Check against Tor exit node list
                    $response = Http::timeout(5)->get('https://check.torproject.org/exit-addresses');

                    if ($response->successful()) {
                        $content = $response->body();
                        return str_contains($content, $ip);
                    }
                } catch (\Exception $e) {
                    Log::debug('Tor check failed', ['ip' => $ip, 'error' => $e->getMessage()]);
                }

                return false;
            });
        }

        return false;
    }

    /**
     * Get client information
     */
    public function getClientInfo(Request $request)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $domain = $request->getHost();

        return [
            'ip' => $ip,
            'domain' => $domain,
            'user_agent' => $userAgent,
            'is_private_ip' => $this->isPrivateIp($ip),
            'is_vpn' => $this->isVpnIp($ip),
            'is_tor' => $this->isTorIp($ip),
            'headers' => [
                'accept_language' => $request->header('Accept-Language'),
                'referer' => $request->header('Referer'),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'x_real_ip' => $request->header('X-Real-IP'),
            ]
        ];
    }

    /**
     * Block suspicious requests
     */
    public function shouldBlockRequest(Request $request)
    {
        $clientInfo = $this->getClientInfo($request);

        // Block Tor nodes
        if ($clientInfo['is_tor']) {
            Log::warning('Blocked Tor request', $clientInfo);
            return ['block' => true, 'reason' => 'Tor network blocked'];
        }

        // Block VPNs if configured
        if ($clientInfo['is_vpn']) {
            Log::warning('Blocked VPN request', $clientInfo);
            return ['block' => true, 'reason' => 'VPN blocked'];
        }

        // Check for suspicious user agents
        if ($this->isSuspiciousUserAgent($clientInfo['user_agent'])) {
            Log::warning('Blocked suspicious user agent', $clientInfo);
            return ['block' => true, 'reason' => 'Suspicious user agent'];
        }

        return ['block' => false];
    }

    /**
     * Check for suspicious user agents
     */
    protected function isSuspiciousUserAgent($userAgent)
    {
        if (!$userAgent) {
            return true;
        }

        $suspiciousPatterns = [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'python',
            'wget',
            'curl',
            'postman',
            'insomnia',
        ];

        $userAgent = strtolower($userAgent);

        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rate limiting for API endpoints
     */
    public function checkRateLimit($key, $maxAttempts = 100, $decayMinutes = 60)
    {
        $cacheKey = 'rate_limit_' . md5($key);
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
            return ['allowed' => false, 'remaining' => 0, 'reset_in' => Cache::get($cacheKey . '_reset', 0)];
        }

        Cache::put($cacheKey, $attempts + 1, now()->addMinutes($decayMinutes));

        if ($attempts == 0) {
            Cache::put($cacheKey . '_reset', now()->addMinutes($decayMinutes)->timestamp, now()->addMinutes($decayMinutes));
        }

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - $attempts - 1,
            'reset_in' => Cache::get($cacheKey . '_reset', 0)
        ];
    }

    /**
     * Log security events
     */
    public function logSecurityEvent($event, $data = [])
    {
        $data['timestamp'] = now();
        $data['event'] = $event;

        Log::channel('security')->info('Security event: ' . $event, $data);

        // Store in database for audit trail
        try {
            \Illuminate\Support\Facades\DB::table('security_audit_log')->insert([
                'event' => $event,
                'data' => json_encode($data),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log security event to database', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create security audit log table migration
     */
    public static function createAuditLogTable()
    {
        // This would be called during setup
        return "
            CREATE TABLE security_audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event VARCHAR(255) NOT NULL,
                data JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event (event),
                INDEX idx_ip (ip_address),
                INDEX idx_created_at (created_at)
            );
        ";
    }
}