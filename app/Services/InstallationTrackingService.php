<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\License;

class InstallationTrackingService
{
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Track installation
     */
    public function trackInstallation($licenseKey, $hardwareId, $installationData = [])
    {
        try {
            $license = License::where('license_key', $licenseKey)->first();

            if (!$license) {
                Log::warning('Installation tracking failed: License not found', ['license_key' => $licenseKey]);
                return ['success' => false, 'message' => 'License not found'];
            }

            // Check if license allows this installation
            if (!$this->canInstall($license, $hardwareId)) {
                Log::warning('Installation blocked: Limit exceeded', [
                    'license_key' => $licenseKey,
                    'hardware_id' => $hardwareId,
                    'current_installations' => $license->activation_count,
                    'max_installations' => $license->max_installations
                ]);
                return ['success' => false, 'message' => 'Installation limit exceeded'];
            }

            // Record installation
            $installation = $this->recordInstallation($license, $hardwareId, $installationData);

            // Update license activation count
            $license->increment('activation_count');
            $license->update(['last_activation_at' => now()]);

            Log::info('Installation tracked successfully', [
                'license_key' => $licenseKey,
                'hardware_id' => $hardwareId,
                'installation_id' => $installation['id']
            ]);

            return [
                'success' => true,
                'installation_id' => $installation['id'],
                'license' => $license
            ];

        } catch (\Exception $e) {
            Log::error('Installation tracking failed', [
                'license_key' => $licenseKey,
                'hardware_id' => $hardwareId,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'message' => 'Installation tracking failed'];
        }
    }

    /**
     * Check if installation is allowed
     */
    protected function canInstall($license, $hardwareId)
    {
        // Check installation limit
        if ($license->max_installations && $license->activation_count >= $license->max_installations) {
            return false;
        }

        // Check if hardware ID is already used (for same license)
        $existingInstallation = DB::table('license_installations')
            ->where('license_id', $license->id)
            ->where('hardware_id', $hardwareId)
            ->where('is_active', true)
            ->first();

        if ($existingInstallation) {
            // Allow reactivation of existing installation
            return true;
        }

        return true;
    }

    /**
     * Record installation in database
     */
    protected function recordInstallation($license, $hardwareId, $installationData)
    {
        return DB::table('license_installations')->updateOrInsert(
            [
                'license_id' => $license->id,
                'hardware_id' => $hardwareId,
            ],
            [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'operating_system' => $installationData['os'] ?? $this->detectOS(),
                'installation_path' => $installationData['path'] ?? null,
                'version' => $installationData['version'] ?? config('app.version', '1.0.0'),
                'metadata' => json_encode($installationData),
                'is_active' => true,
                'installed_at' => now(),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Deactivate installation
     */
    public function deactivateInstallation($licenseKey, $hardwareId, $reason = null)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        $updated = DB::table('license_installations')
            ->where('license_id', $license->id)
            ->where('hardware_id', $hardwareId)
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => $reason,
                'updated_at' => now(),
            ]);

        if ($updated) {
            // Decrement activation count
            $license->decrement('activation_count');
        }

        Log::info('Installation deactivated', [
            'license_key' => $licenseKey,
            'hardware_id' => $hardwareId,
            'reason' => $reason
        ]);

        return ['success' => true, 'updated' => $updated];
    }

    /**
     * Get installation statistics
     */
    public function getInstallationStats($licenseId = null)
    {
        $query = DB::table('license_installations');

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }

        $stats = [
            'total_installations' => $query->count(),
            'active_installations' => (clone $query)->where('is_active', true)->count(),
            'inactive_installations' => (clone $query)->where('is_active', false)->count(),
            'installations_by_os' => (clone $query)->select('operating_system', DB::raw('count(*) as count'))
                ->whereNotNull('operating_system')
                ->groupBy('operating_system')
                ->get(),
            'recent_installations' => (clone $query)->where('installed_at', '>=', now()->subDays(30))
                ->count(),
        ];

        return $stats;
    }

    /**
     * Heartbeat - update last seen
     */
    public function heartbeat($licenseKey, $hardwareId)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        DB::table('license_installations')
            ->where('license_id', $license->id)
            ->where('hardware_id', $hardwareId)
            ->update([
                'last_seen_at' => now(),
                'ip_address' => request()->ip(),
                'updated_at' => now(),
            ]);

        return ['success' => true];
    }

    /**
     * Detect suspicious installations
     */
    public function detectSuspiciousInstallations($licenseId = null)
    {
        $query = DB::table('license_installations')
            ->where('is_active', true);

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }

        $installations = $query->get();

        $suspicious = [];

        foreach ($installations as $installation) {
            $issues = [];

            // Check for installations on multiple IPs rapidly
            $ipChanges = DB::table('license_installations')
                ->where('license_id', $installation->license_id)
                ->where('hardware_id', '!=', $installation->hardware_id)
                ->where('ip_address', $installation->ip_address)
                ->where('installed_at', '>=', now()->subHours(24))
                ->count();

            if ($ipChanges > 2) {
                $issues[] = 'Multiple installations from same IP';
            }

            // Check for old installations that haven't been seen recently
            if ($installation->last_seen_at && $installation->last_seen_at->diffInDays(now()) > 30) {
                $issues[] = 'Installation not seen recently';
            }

            if (!empty($issues)) {
                $suspicious[] = [
                    'installation' => $installation,
                    'issues' => $issues,
                    'risk_level' => count($issues) > 1 ? 'high' : 'medium'
                ];
            }
        }

        return $suspicious;
    }

    /**
     * Generate hardware ID
     */
    public function generateHardwareId()
    {
        $components = [];

        // CPU info (simplified)
        $components[] = php_uname('m'); // Machine type

        // Disk serial (if available)
        if (function_exists('disk_free_space')) {
            $components[] = md5(disk_free_space('/'));
        }

        // MAC address (simplified)
        if (function_exists('shell_exec')) {
            $mac = shell_exec('cat /sys/class/net/*/address 2>/dev/null | head -1');
            if ($mac) {
                $components[] = trim($mac);
            }
        }

        // System info
        $components[] = php_uname('n'); // Host name
        $components[] = php_uname('r'); // Kernel version

        return hash('sha256', implode('|', $components));
    }

    /**
     * Validate hardware ID format
     */
    public function validateHardwareId($hardwareId)
    {
        // Hardware ID should be a SHA256 hash (64 characters)
        return is_string($hardwareId) && strlen($hardwareId) === 64 && ctype_xdigit($hardwareId);
    }

    /**
     * Detect operating system
     */
    protected function detectOS()
    {
        $userAgent = request()->userAgent();

        if (stripos($userAgent, 'windows') !== false) {
            return 'Windows';
        } elseif (stripos($userAgent, 'mac') !== false) {
            return 'macOS';
        } elseif (stripos($userAgent, 'linux') !== false) {
            return 'Linux';
        } elseif (stripos($userAgent, 'android') !== false) {
            return 'Android';
        } elseif (stripos($userAgent, 'ios') !== false) {
            return 'iOS';
        }

        return php_uname('s'); // Fallback to system uname
    }

    /**
     * Clean up old inactive installations
     */
    public function cleanupOldInstallations($daysOld = 90)
    {
        $cutoffDate = now()->subDays($daysOld);

        $deleted = DB::table('license_installations')
            ->where('is_active', false)
            ->where('deactivated_at', '<', $cutoffDate)
            ->delete();

        Log::info('Cleaned up old installations', [
            'deleted' => $deleted,
            'days_old' => $daysOld
        ]);

        return $deleted;
    }

    /**
     * Get installation details
     */
    public function getInstallationDetails($licenseKey, $hardwareId)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return null;
        }

        return DB::table('license_installations')
            ->where('license_id', $license->id)
            ->where('hardware_id', $hardwareId)
            ->first();
    }

    /**
     * Force reactivation of installation
     */
    public function forceReactivation($licenseKey, $hardwareId)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        DB::table('license_installations')
            ->where('license_id', $license->id)
            ->where('hardware_id', $hardwareId)
            ->update([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivation_reason' => null,
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

        // Ensure activation count is correct
        $activeCount = DB::table('license_installations')
            ->where('license_id', $license->id)
            ->where('is_active', true)
            ->count();

        $license->update(['activation_count' => $activeCount]);

        Log::info('Installation force reactivated', [
            'license_key' => $licenseKey,
            'hardware_id' => $hardwareId
        ]);

        return ['success' => true];
    }
}