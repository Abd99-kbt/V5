<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\UsageTrackingService;
use Symfony\Component\HttpFoundation\Response;

class UsageTrackingMiddleware
{
    protected $usageTrackingService;

    public function __construct(UsageTrackingService $usageTrackingService)
    {
        $this->usageTrackingService = $usageTrackingService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Start tracking
        $this->usageTrackingService->startTracking($request);

        // Add tracking to request
        $request->merge(['usage_tracking_enabled' => true]);

        // Get response
        $response = $next($request);

        // Log the request after processing
        try {
            $this->usageTrackingService->logApiRequest($request, $response);
        } catch (\Exception $e) {
            Log::error('Failed to log usage', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
        }

        return $response;
    }
}