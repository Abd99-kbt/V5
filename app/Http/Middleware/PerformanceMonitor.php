<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = ($endTime - $startTime) * 1000; // ms
        $memoryUsage = ($endMemory - $startMemory) / 1024 / 1024; // MB

        // Log slow requests (> 1 second)
        if ($executionTime > 1000) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time_ms' => round($executionTime, 2),
                'memory_usage_mb' => round($memoryUsage, 2),
                'user_id' => Auth::check() ? Auth::id() : null,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
        }

        // Log very slow requests (> 5 seconds)
        if ($executionTime > 5000) {
            Log::error('Very slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time_ms' => round($executionTime, 2),
                'memory_usage_mb' => round($memoryUsage, 2),
                'user_id' => Auth::check() ? Auth::id() : null,
                'query_params' => $request->query(),
                'post_data' => $request->method() === 'POST' ? $request->all() : null,
            ]);
        }

        // Add performance headers for debugging
        if (config('app.debug')) {
            $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
            $response->headers->set('X-Memory-Usage', round($memoryUsage, 2) . 'MB');
        }

        return $response;
    }
}