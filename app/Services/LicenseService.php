<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LicenseService
{
    protected $cachePrefix = 'license_';
    protected $cacheTtl = 3600; // 1 hour

    /**
     * Generate a new license
     */
    public function generateLicense(array $data)
    {
        $licenseKey = License::generateLicenseKey();

        $licenseData = array_merge($data, [
            'license_key' => $licenseKey,
            'activation_count' => 0,
            'is_active' => true
        ]);

        return License::create($licenseData);
    }

    /**
     * Validate license key
     */
    public function validateLicense($licenseKey, $domain = null, $ip = null)
    {
        $cacheKey = $this->cachePrefix . md5($licenseKey . $domain . $ip);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($licenseKey, $domain, $ip) {
            $license = License::where('license_key', $licenseKey)->first();

            if (!$license) {
                Log::warning('License validation failed: License not found', ['license_key' => $licenseKey]);
                return ['valid' => false, 'reason' => 'License not found'];
            }

            if (!$license->isValid()) {
                Log::warning('License validation failed: License invalid', [
                    'license_key' => $licenseKey,
                    'is_active' => $license->is_active,
                    'expired' => $license->expires_at && $license->expires_at->isPast(),
                    'deactivated' => $license->deactivated_at
                ]);
                return ['valid' => false, 'reason' => 'License is invalid or expired'];
            }

            if (!$license->canActivate($domain, $ip)) {
                Log::warning('License validation failed: Domain/IP restriction', [
                    'license_key' => $licenseKey,
                    'domain' => $domain,
                    'ip' => $ip,
                    'allowed_domains' => $license->allowed_domains,
                    'allowed_ips' => $license->allowed_ips
                ]);
                return ['valid' => false, 'reason' => 'Domain or IP not allowed'];
            }

            // Log successful validation
            Log::info('License validation successful', [
                'license_key' => $licenseKey,
                'domain' => $domain,
                'ip' => $ip
            ]);

            return [
                'valid' => true,
                'license' => $license,
                'features' => $license->features ?? [],
                'expires_at' => $license->expires_at,
                'license_type' => $license->license_type
            ];
        });
    }

    /**
     * Activate license
     */
    public function activateLicense($licenseKey, $domain = null, $ip = null, $hardwareId = null)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        if (!$license->canActivate($domain, $ip)) {
            return ['success' => false, 'message' => 'Cannot activate license on this domain/IP'];
        }

        $license->activate($domain, $ip);

        // Clear cache
        $this->clearLicenseCache($licenseKey);

        Log::info('License activated', [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'ip' => $ip,
            'hardware_id' => $hardwareId
        ]);

        return ['success' => true, 'license' => $license];
    }

    /**
     * Deactivate license
     */
    public function deactivateLicense($licenseKey, $reason = null)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        $license->deactivate($reason);

        // Clear cache
        $this->clearLicenseCache($licenseKey);

        Log::info('License deactivated', [
            'license_key' => $licenseKey,
            'reason' => $reason
        ]);

        return ['success' => true];
    }

    /**
     * Check license status
     */
    public function getLicenseStatus($licenseKey)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return null;
        }

        return [
            'license_key' => $license->license_key,
            'is_active' => $license->is_active,
            'expires_at' => $license->expires_at,
            'activation_count' => $license->activation_count,
            'max_installations' => $license->max_installations,
            'license_type' => $license->license_type,
            'customer_email' => $license->customer_email,
            'features' => $license->features ?? []
        ];
    }

    /**
     * Extend license
     */
    public function extendLicense($licenseKey, Carbon $newExpiration)
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            return ['success' => false, 'message' => 'License not found'];
        }

        $license->extend($newExpiration);

        // Clear cache
        $this->clearLicenseCache($licenseKey);

        return ['success' => true, 'license' => $license];
    }

    /**
     * Get licenses expiring soon
     */
    public function getExpiringLicenses($days = 30)
    {
        return License::active()->expiringSoon($days)->get();
    }

    /**
     * Get expired licenses
     */
    public function getExpiredLicenses()
    {
        return License::expired()->get();
    }

    /**
     * Validate license with remote server (for online validation)
     */
    public function validateWithServer($licenseKey, $domain = null, $ip = null)
    {
        try {
            $response = Http::timeout(10)->post(config('license.validation_url'), [
                'license_key' => $licenseKey,
                'domain' => $domain,
                'ip' => $ip,
                'timestamp' => now()->timestamp
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data;
            }

            return ['valid' => false, 'reason' => 'Server validation failed'];
        } catch (\Exception $e) {
            Log::error('Remote license validation failed', [
                'license_key' => $licenseKey,
                'error' => $e->getMessage()
            ]);

            // Fallback to local validation
            return $this->validateLicense($licenseKey, $domain, $ip);
        }
    }

    /**
     * Clear license cache
     */
    protected function clearLicenseCache($licenseKey)
    {
        $patterns = [
            $this->cachePrefix . '*',
            'license_validation_' . md5($licenseKey) . '*'
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Generate trial license
     */
    public function generateTrialLicense($customerEmail, $customerName, $days = 30)
    {
        return $this->generateLicense([
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'license_type' => 'trial',
            'expires_at' => now()->addDays($days),
            'max_users' => 5,
            'max_installations' => 1,
            'features' => ['basic_features', 'trial_watermark']
        ]);
    }

    /**
     * Generate enterprise license
     */
    public function generateEnterpriseLicense($customerEmail, $customerName, $maxUsers = 100, $maxInstallations = 5)
    {
        return $this->generateLicense([
            'customer_email' => $customerEmail,
            'customer_name' => $customerName,
            'license_type' => 'enterprise',
            'max_users' => $maxUsers,
            'max_installations' => $maxInstallations,
            'features' => ['all_features', 'api_access', 'custom_integrations', 'priority_support']
        ]);
    }
}