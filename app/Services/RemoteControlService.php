<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\License;

class RemoteControlService
{
    protected $apiUrl;
    protected $apiKey;
    protected $cacheTtl = 300; // 5 minutes

    public function __construct()
    {
        $this->apiUrl = config('license.remote_control_url', 'https://license-api.yourdomain.com/api');
        $this->apiKey = config('license.remote_api_key', env('REMOTE_API_KEY'));
    }

    /**
     * Check license revocation status remotely
     */
    public function checkLicenseRevocation($licenseKey)
    {
        $cacheKey = 'license_revocation_' . md5($licenseKey);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($licenseKey) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'X-License-Key' => $licenseKey,
                ])->timeout(10)->get("{$this->apiUrl}/license/check-revocation");

                if ($response->successful()) {
                    $data = $response->json();

                    if ($data['revoked'] ?? false) {
                        // License is revoked, deactivate locally
                        $this->deactivateLicenseLocally($licenseKey, $data['reason'] ?? 'Remote revocation');
                        return ['revoked' => true, 'reason' => $data['reason'] ?? 'License revoked by server'];
                    }

                    return ['revoked' => false];
                }

                Log::warning('Failed to check license revocation', [
                    'license_key' => $licenseKey,
                    'response_status' => $response->status()
                ]);

            } catch (\Exception $e) {
                Log::error('Remote license check failed', [
                    'license_key' => $licenseKey,
                    'error' => $e->getMessage()
                ]);
            }

            // Return false on error (fail open for connectivity issues)
            return ['revoked' => false, 'error' => true];
        });
    }

    /**
     * Send usage report to remote server
     */
    public function sendUsageReport($licenseKey, $usageData)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-License-Key' => $licenseKey,
            ])->timeout(15)->post("{$this->apiUrl}/license/usage-report", [
                'license_key' => $licenseKey,
                'usage_data' => $usageData,
                'timestamp' => now()->toISOString(),
                'server_info' => $this->getServerInfo()
            ]);

            if ($response->successful()) {
                return ['success' => true];
            }

            Log::warning('Failed to send usage report', [
                'license_key' => $licenseKey,
                'response_status' => $response->status()
            ]);

        } catch (\Exception $e) {
            Log::error('Usage report send failed', [
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);
        }

        return ['success' => false];
    }

    /**
     * Check for emergency commands
     */
    public function checkEmergencyCommands()
    {
        $cacheKey = 'emergency_commands';

        return Cache::remember($cacheKey, 60, function () { // Check every minute
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])->timeout(10)->get("{$this->apiUrl}/emergency/commands");

                if ($response->successful()) {
                    $commands = $response->json();

                    foreach ($commands as $command) {
                        $this->executeEmergencyCommand($command);
                    }

                    return $commands;
                }

            } catch (\Exception $e) {
                Log::error('Emergency commands check failed', [
                    'error' => $e->getMessage()
                ]);
            }

            return [];
        });
    }

    /**
     * Execute emergency command
     */
    protected function executeEmergencyCommand($command)
    {
        $commandId = $command['id'];
        $action = $command['action'];
        $parameters = $command['parameters'] ?? [];

        Log::warning('Executing emergency command', [
            'command_id' => $commandId,
            'action' => $action,
            'parameters' => $parameters
        ]);

        switch ($action) {
            case 'shutdown':
                $this->executeShutdown($parameters);
                break;

            case 'deactivate_license':
                $this->executeLicenseDeactivation($parameters);
                break;

            case 'block_ip':
                $this->executeIpBlock($parameters);
                break;

            case 'update_config':
                $this->executeConfigUpdate($parameters);
                break;

            case 'run_command':
                $this->executeSystemCommand($parameters);
                break;

            default:
                Log::warning('Unknown emergency command action', ['action' => $action]);
        }

        // Report command execution
        $this->reportCommandExecution($commandId, 'completed');
    }

    /**
     * Emergency shutdown
     */
    protected function executeShutdown($parameters)
    {
        $reason = $parameters['reason'] ?? 'Emergency shutdown';
        $delay = $parameters['delay'] ?? 0;

        Log::critical('Emergency shutdown initiated', [
            'reason' => $reason,
            'delay' => $delay
        ]);

        // Set emergency mode
        Cache::put('emergency_shutdown', [
            'reason' => $reason,
            'initiated_at' => now(),
            'delay' => $delay
        ], now()->addHours(24));

        if ($delay > 0) {
            // Schedule shutdown
            $delaySeconds = $delay;
            dispatch(function () use ($reason, $delaySeconds) {
                sleep($delaySeconds);
                $this->performShutdown($reason);
            })->delay(now()->addSeconds($delay));
        } else {
            $this->performShutdown($reason);
        }
    }

    /**
     * Perform actual shutdown
     */
    protected function performShutdown($reason)
    {
        Log::critical('Performing emergency shutdown', ['reason' => $reason]);

        // Disable all routes
        Cache::put('system_disabled', true, now()->addHours(24));

        // Clear all sessions
        Artisan::call('cache:clear');
        Artisan::call('config:clear');

        // Log shutdown
        Log::critical('System shut down due to emergency command', [
            'reason' => $reason,
            'timestamp' => now()
        ]);
    }

    /**
     * Deactivate specific license
     */
    protected function executeLicenseDeactivation($parameters)
    {
        $licenseKey = $parameters['license_key'] ?? null;
        $reason = $parameters['reason'] ?? 'Remote deactivation';

        if (!$licenseKey) {
            Log::error('License deactivation command missing license key');
            return;
        }

        $licenseService = app(LicenseService::class);
        $result = $licenseService->deactivateLicense($licenseKey, $reason);

        Log::warning('License deactivated via emergency command', [
            'license_key' => $licenseKey,
            'reason' => $reason,
            'success' => $result['success']
        ]);
    }

    /**
     * Block IP address
     */
    protected function executeIpBlock($parameters)
    {
        $ip = $parameters['ip'] ?? null;
        $duration = $parameters['duration'] ?? 3600; // 1 hour

        if (!$ip) {
            Log::error('IP block command missing IP address');
            return;
        }

        Cache::put("blocked_ip_{$ip}", true, now()->addSeconds($duration));

        Log::warning('IP blocked via emergency command', [
            'ip' => $ip,
            'duration' => $duration
        ]);
    }

    /**
     * Update configuration
     */
    protected function executeConfigUpdate($parameters)
    {
        $configKey = $parameters['key'] ?? null;
        $configValue = $parameters['value'] ?? null;

        if (!$configKey) {
            Log::error('Config update command missing key');
            return;
        }

        // Store in cache (temporary) or update config file
        Cache::put("remote_config_{$configKey}", $configValue, now()->addHours(24));

        Log::warning('Configuration updated via emergency command', [
            'key' => $configKey,
            'value' => $configValue
        ]);
    }

    /**
     * Execute system command (dangerous - use with caution)
     */
    protected function executeSystemCommand($parameters)
    {
        $command = $parameters['command'] ?? null;
        $allowedCommands = config('license.allowed_remote_commands', []);

        if (!$command || !in_array($command, $allowedCommands)) {
            Log::error('System command not allowed or missing', [
                'command' => $command,
                'allowed' => $allowedCommands
            ]);
            return;
        }

        try {
            $output = shell_exec($command);
            Log::warning('System command executed via emergency command', [
                'command' => $command,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            Log::error('System command execution failed', [
                'command' => $command,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Report command execution back to server
     */
    protected function reportCommandExecution($commandId, $status)
    {
        try {
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->timeout(10)->post("{$this->apiUrl}/emergency/command-executed", [
                'command_id' => $commandId,
                'status' => $status,
                'executed_at' => now()->toISOString(),
                'server_info' => $this->getServerInfo()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to report command execution', [
                'command_id' => $commandId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Deactivate license locally
     */
    protected function deactivateLicenseLocally($licenseKey, $reason)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if ($license) {
            $license->deactivate($reason);
            Log::warning('License deactivated due to remote revocation', [
                'license_key' => $licenseKey,
                'reason' => $reason
            ]);
        }
    }

    /**
     * Get server information
     */
    protected function getServerInfo()
    {
        return [
            'hostname' => gethostname(),
            'ip' => request()->ip(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Check if system is in emergency shutdown mode
     */
    public static function isEmergencyShutdown()
    {
        return Cache::has('emergency_shutdown') || Cache::has('system_disabled');
    }

    /**
     * Get emergency shutdown info
     */
    public static function getEmergencyShutdownInfo()
    {
        return Cache::get('emergency_shutdown');
    }

    /**
     * Clear emergency shutdown
     */
    public function clearEmergencyShutdown()
    {
        Cache::forget('emergency_shutdown');
        Cache::forget('system_disabled');

        Log::info('Emergency shutdown cleared');
    }

    /**
     * Check if IP is blocked
     */
    public static function isIpBlocked($ip)
    {
        return Cache::has("blocked_ip_{$ip}");
    }
}