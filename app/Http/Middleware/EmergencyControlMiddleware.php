<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RemoteControlService;
use Symfony\Component\HttpFoundation\Response;

class EmergencyControlMiddleware
{
    protected $remoteControlService;

    public function __construct(RemoteControlService $remoteControlService)
    {
        $this->remoteControlService = $remoteControlService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Check if system is in emergency shutdown
        if (RemoteControlService::isEmergencyShutdown()) {
            $shutdownInfo = RemoteControlService::getEmergencyShutdownInfo();

            return response()->view('errors.emergency-shutdown', [
                'shutdown_info' => $shutdownInfo,
                'message' => $shutdownInfo['reason'] ?? 'System is temporarily unavailable due to maintenance'
            ], 503);
        }

        // Check if IP is blocked
        if (RemoteControlService::isIpBlocked($request->ip())) {
            return response()->view('errors.blocked', [
                'message' => 'Access denied from this IP address'
            ], 403);
        }

        // Check for emergency commands (run periodically)
        if (rand(1, 100) <= 10) { // 10% chance to check on each request
            try {
                $this->remoteControlService->checkEmergencyCommands();
            } catch (\Exception $e) {
                // Log but don't fail the request
                \Illuminate\Support\Facades\Log::error('Emergency command check failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $next($request);
    }
}