<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SecurityService;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    protected $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Get client information
        $clientInfo = $this->securityService->getClientInfo($request);

        // Check if request should be blocked
        $blockCheck = $this->securityService->shouldBlockRequest($request);

        if ($blockCheck['block']) {
            $this->securityService->logSecurityEvent('request_blocked', [
                'reason' => $blockCheck['reason'],
                'client_info' => $clientInfo
            ]);

            return $this->blockResponse($blockCheck['reason']);
        }

        // Rate limiting for API routes
        if ($request->is('api/*')) {
            $rateLimit = $this->securityService->checkRateLimit(
                $clientInfo['ip'],
                config('license.api.rate_limit', 1000),
                60 // per hour
            );

            if (!$rateLimit['allowed']) {
                $this->securityService->logSecurityEvent('rate_limit_exceeded', [
                    'client_info' => $clientInfo,
                    'reset_in' => $rateLimit['reset_in']
                ]);

                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => 'Too many requests',
                    'reset_in' => $rateLimit['reset_in']
                ], 429)->header('X-RateLimit-Remaining', $rateLimit['remaining']);
            }

            // Add rate limit headers
            $response = $next($request);
            $response->headers->set('X-RateLimit-Remaining', $rateLimit['remaining']);
            $response->headers->set('X-RateLimit-Reset', $rateLimit['reset_in']);

            return $response;
        }

        // Log security events for sensitive routes
        if ($this->isSensitiveRoute($request)) {
            $this->securityService->logSecurityEvent('sensitive_route_accessed', [
                'route' => $request->route() ? $request->route()->getName() : $request->path(),
                'method' => $request->method(),
                'client_info' => $clientInfo
            ]);
        }

        return $next($request);
    }

    /**
     * Check if route is sensitive
     */
    protected function isSensitiveRoute(Request $request)
    {
        $sensitiveRoutes = [
            'admin/*',
            'api/*',
            'filament/*',
            'login',
            'register',
            'password/*',
        ];

        $path = $request->path();

        foreach ($sensitiveRoutes as $route) {
            if (fnmatch($route, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return block response
     */
    protected function blockResponse($reason)
    {
        $message = 'Access denied: ' . $reason;

        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'error' => 'Access Denied',
                'message' => $message,
                'code' => 'ACCESS_DENIED'
            ], 403);
        }

        return response()->view('errors.security', [
            'message' => $message,
            'reason' => $reason
        ], 403);
    }
}