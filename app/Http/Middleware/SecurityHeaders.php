<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class SecurityHeaders
{
    protected $exemptRoutes = [
        'health-check',
        'ping',
        'api/v1/health'
    ];

    /**
     * Handle an incoming request with enhanced security.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip security headers for exempt routes
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        // Log security events
        $this->logSecurityEvent($request);

        // Rate limiting for API endpoints
        $this->applyRateLimit($request);

        $response = $next($request);

        // Enhanced security headers configuration
        $securityHeaders = $this->getSecurityHeaders();

        // Add HSTS header only in production with HTTPS
        if ($this->shouldAddHsts()) {
            $securityHeaders['Strict-Transport-Security'] = $this->getHstsHeader();
        }

        // Enhanced CSP for production
        if (app()->environment('production')) {
            $securityHeaders['Content-Security-Policy'] = $this->getContentSecurityPolicy();
        }

        // Apply all security headers
        foreach ($securityHeaders as $header => $value) {
            $response->headers->set($header, $value, false);
        }

        // Add security cache headers
        $this->addSecurityCacheHeaders($response);

        // Add request fingerprint for tracking
        $this->addRequestFingerprint($response, $request);

        return $response;
    }

    protected function isExemptRoute(Request $request): bool
    {
        $currentPath = $request->path();
        return in_array($currentPath, $this->exemptRoutes);
    }

    protected function logSecurityEvent(Request $request): void
    {
        if (config('app.log_security_events', true)) {
            Log::channel('security')->info('Security Headers Applied', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'method' => $request->method(),
                'timestamp' => now()->toISOString(),
                'fingerprint' => $this->generateRequestFingerprint($request)
            ]);
        }
    }

    protected function applyRateLimit(Request $request): void
    {
        if ($request->is('api/*')) {
            $key = 'api_rate_limit:' . $request->ip();
            $maxAttempts = config('app.api_rate_limit', 60); // requests per minute
            $decayMinutes = 1;

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                Log::channel('security')->warning('Rate Limit Exceeded', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'attempts' => RateLimiter::attempts($key),
                    'available_in' => RateLimiter::availableIn($key)
                ]);

                abort(429, 'Too Many Requests');
            }

            RateLimiter::hit($key, $decayMinutes * 60);
        }
    }

    protected function getSecurityHeaders(): array
    {
        return [
            // Core security headers
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => $this->getFrameOptions(),
            'X-XSS-Protection' => '1; mode=block; report=/api/v1/xss-report',
            'Referrer-Policy' => 'strict-origin-when-cross-origin, no-referrer-when-downgrade',
            'Permissions-Policy' => $this->getPermissionsPolicy(),
            'X-Download-Options' => 'noopen',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            
            // Advanced security headers
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
            'Cross-Origin-Resource-Policy' => 'same-site',
            'Origin-Agent-Cluster' => '?1',
            
            // Privacy headers
            'Clear-Site-Data' => 'cache, cookies, storage, executionContexts',
            'Server' => 'Secure-Server', // Hide server information
            
            // Anti-clickjacking
            'X-Frame-Options' => 'DENY',
            
            // Content sniffing protection
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    protected function shouldAddHsts(): bool
    {
        return app()->environment('production') &&
               env('FORCE_HTTPS', false) &&
               $this->isSecure($this->request ?? request());
    }

    protected function isSecure(Request $request): bool
    {
        if ($request->server('HTTP_X_FORWARDED_PROTO') === 'https') {
            return true;
        }
        
        if ($request->server('HTTPS') === 'on') {
            return true;
        }
        
        if ($request->server('SERVER_PORT') == 443) {
            return true;
        }
        
        return false;
    }

    protected function getHstsHeader(): string
    {
        $maxAge = config('app.hsts_max_age', 31536000); // 1 year
        $includeSubDomains = config('app.hsts_include_subdomains', true) ? '; includeSubDomains' : '';
        $preload = config('app.hsts_preload', false) ? '; preload' : '';
        
        return "max-age={$maxAge}{$includeSubDomains}{$preload}";
    }

    protected function getContentSecurityPolicy(): string
    {
        $policy = [
            "default-src 'self'",
            "script-src 'self' 'strict-dynamic' 'nonce-{nonce}' 'unsafe-inline'", // nonce for inline scripts
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https: data:",
            "connect-src 'self' https: wss:",
            "media-src 'self'",
            "object-src 'none'",
            "child-src 'self'",
            "worker-src 'self'",
            "frame-ancestors 'none'", // Prevent clickjacking
            "base-uri 'self'",
            "form-action 'self'",
            "frame-src 'none'",
            "upgrade-insecure-requests",
            "block-all-mixed-content"
        ];
        
        return implode('; ', $policy);
    }

    protected function getFrameOptions(): string
    {
        $frameOptions = config('app.frame_options', 'DENY');
        
        if ($frameOptions === 'SAMEORIGIN') {
            return 'SAMEORIGIN';
        }
        
        return 'DENY';
    }

    protected function getPermissionsPolicy(): string
    {
        return 'accelerometer=(), autoplay=(), camera=(), cross-origin-isolated=(), display-capture=(), ' .
               'document-domain=(), encrypted-media=(), fullscreen=(), geolocation=(), ' .
               'gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), ' .
               'midi=(), navigation-override=(), payment=(), usb=(), screen-wake-lock=(), ' .
               'sync-xhr=(), xr-spatial-tracking=(), clipboard-read=(), clipboard-write=(), ' .
               'gamepad=(), speaker-selection=(), trust-token-redemption=(), web-share=()';
    }

    protected function addSecurityCacheHeaders(Response $response): void
    {
        // Prevent caching of sensitive pages
        $sensitivePaths = ['/admin', '/api/auth', '/dashboard', '/profile'];
        $currentPath = request()->path();
        
        foreach ($sensitivePaths as $path) {
            if (str_contains($currentPath, $path)) {
                $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');
                break;
            }
        }
    }

    protected function addRequestFingerprint(Response $response, Request $request): void
    {
        $fingerprint = $this->generateRequestFingerprint($request);
        $response->headers->set('X-Request-Fingerprint', $fingerprint);
        $response->headers->set('X-Security-Timestamp', now()->timestamp);
    }

    protected function generateRequestFingerprint(Request $request): string
    {
        return hash('sha256',
            $request->ip() .
            $request->userAgent() .
            $request->path() .
            $request->getQueryString()
        );
    }
}