<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\LicenseService;
use Symfony\Component\HttpFoundation\Response;

class LicenseMiddleware
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Skip license check if disabled
        if (!config('license.enabled') || !config('license.require_license')) {
            return $next($request);
        }

        // Get license key from various sources
        $licenseKey = $this->getLicenseKey($request);

        if (!$licenseKey) {
            Log::warning('License check failed: No license key provided', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent()
            ]);

            return $this->licenseErrorResponse('No license key provided');
        }

        // Get current domain and IP
        $domain = $request->getHost();
        $ip = $request->ip();

        // Validate license
        $validation = $this->licenseService->validateLicense($licenseKey, $domain, $ip);

        if (!$validation['valid']) {
            Log::warning('License validation failed', [
                'license_key' => $licenseKey,
                'domain' => $domain,
                'ip' => $ip,
                'reason' => $validation['reason'],
                'url' => $request->fullUrl()
            ]);

            // Track failed attempts
            $this->trackFailedAttempt($ip);

            return $this->licenseErrorResponse($validation['reason']);
        }

        // Store license info in request for later use
        $request->merge(['license_info' => $validation]);

        // Log successful access
        if (config('license.enable_audit_log')) {
            Log::info('Licensed access granted', [
                'license_key' => $licenseKey,
                'domain' => $domain,
                'ip' => $ip,
                'url' => $request->fullUrl(),
                'user_id' => auth()->check() ? auth()->id() : null
            ]);
        }

        return $next($request);
    }

    /**
     * Get license key from various sources
     */
    protected function getLicenseKey(Request $request)
    {
        // Check header
        $licenseKey = $request->header('X-License-Key');

        if ($licenseKey) {
            return $licenseKey;
        }

        // Check query parameter
        $licenseKey = $request->query('license_key');

        if ($licenseKey) {
            return $licenseKey;
        }

        // Check session
        $licenseKey = session('license_key');

        if ($licenseKey) {
            return $licenseKey;
        }

        // Check environment variable
        $licenseKey = env('APP_LICENSE_KEY');

        if ($licenseKey) {
            return $licenseKey;
        }

        // Check config
        $licenseKey = config('license.default_key');

        return $licenseKey;
    }

    /**
     * Return license error response
     */
    protected function licenseErrorResponse($reason)
    {
        $message = 'License validation failed: ' . $reason;

        // Check if request expects JSON
        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'error' => 'License Error',
                'message' => $message,
                'code' => 'LICENSE_INVALID'
            ], 403);
        }

        // Return HTML error page
        return response()->view('errors.license', [
            'message' => $message,
            'reason' => $reason
        ], 403);
    }

    /**
     * Track failed license attempts
     */
    protected function trackFailedAttempt($ip)
    {
        $cacheKey = 'license_failed_attempts_' . $ip;
        $attempts = Cache::get($cacheKey, 0) + 1;

        Cache::put($cacheKey, $attempts, now()->addHours(24));

        // Check if IP should be blocked
        $maxAttempts = config('license.max_failed_attempts', 5);
        if ($attempts >= $maxAttempts) {
            $blockKey = 'license_blocked_ip_' . $ip;
            Cache::put($blockKey, true, now()->addHours(config('license.lockout_duration', 3600) / 3600));

            Log::warning('IP blocked due to excessive failed license attempts', [
                'ip' => $ip,
                'attempts' => $attempts
            ]);
        }
    }

    /**
     * Check if IP is blocked
     */
    public static function isIpBlocked($ip)
    {
        $blockKey = 'license_blocked_ip_' . $ip;
        return Cache::has($blockKey);
    }
}