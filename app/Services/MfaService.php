<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Writer;
use BaconQrCode\Writer\Png;
use BaconQrCode\Writer\Raw;
use BaconQrCode\Writer\Eps;
use BaconQrCode\Writer\Svg;
use BaconQrCode\Writer\Jpeg;
use BaconQrCode\Writer\Wbmp;
use BaconQrCode\Writer\Gif;
use BaconQrCode\Writer\Html;
use BaconQrCode\Writer\Csv;
use BaconQrCode\Writer\Wbf;
use BaconQrCode\Writer\Wmf;
use BaconQrCode\Writer\Graphics;
use BaconQrCode\Writer\Dwoo;
use BaconQrCode\Writer\Html\InlineTag;
use BaconQrCode\Writer\Svg\Color;
use BaconQrCode\Writer\Svg\Shape;
use BaconQrCode\Writer\Svg\Style;
use BaconQrCode\Writer\Svg\BorderStyle;

class MfaService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Generate MFA secret for user.
     */
    public function generateSecret(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();
        
        // Store secret temporarily with expiration (not enabling MFA yet)
        cache([
            "mfa_secret:{$user->id}" => $secret,
            "mfa_secret:{$user->id}:expires" => now()->addMinutes(10)
        ]);

        return $secret;
    }

    /**
     * Generate QR code for MFA setup.
     */
    public function generateQrCode(string $secret, User $user): array
    {
        $issuer = config('app.name', 'Laravel App');
        $companyName = config('app.company_name', $issuer);
        
        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            $companyName,
            $user->email,
            $secret
        );

        // Generate QR code image
        $qrCodeImage = $this->generateQrCodeImage($otpauthUrl);

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_code' => $qrCodeImage,
            'manual_entry_key' => $secret,
            'backup_codes' => $this->generateBackupCodes(),
        ];
    }

    /**
     * Verify MFA token.
     */
    public function verifyToken(string $secret, string $token): bool
    {
        // Allow for time drift (± 1 step)
        $valid = $this->google2fa->verifyKey($secret, $token, 1);
        
        // Also check previous and next tokens for robustness
        if (!$valid) {
            $timeStep = $this->google2fa->getTimeStep();
            $currentTime = time();
            
            // Check previous time step
            $valid = $this->google2fa->verifyKey($secret, $token, 1, $currentTime - $timeStep);
            
            // Check next time step
            if (!$valid) {
                $valid = $this->google2fa->verifyKey($secret, $token, 1, $currentTime + $timeStep);
            }
        }

        return $valid;
    }

    /**
     * Enable MFA for user.
     */
    public function enableMfa(User $user, string $secret, string $token): bool
    {
        if (!$this->verifyToken($secret, $token)) {
            return false;
        }

        // Remove temporary secret
        cache()->forget("mfa_secret:{$user->id}");
        cache()->forget("mfa_secret:{$user->id}:expires");

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();
        
        // Store backup codes encrypted
        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_backup_codes' => encrypt($backupCodes),
            'mfa_enabled_at' => now(),
        ]);

        // Log security event
        SecurityAuditService::logEvent('mfa_enabled', [
            'user_id' => $user->id,
            'enabled_at' => now(),
        ], $user);

        return true;
    }

    /**
     * Disable MFA for user.
     */
    public function disableMfa(User $user, ?string $confirmationToken = null): bool
    {
        // If confirmation token is provided, verify it
        if ($confirmationToken && !$this->verifyBackupCode($user, $confirmationToken)) {
            return false;
        }

        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_backup_codes' => null,
            'mfa_enabled_at' => null,
            'mfa_disabled_at' => now(),
        ]);

        // Log security event
        SecurityAuditService::logEvent('mfa_disabled', [
            'user_id' => $user->id,
            'disabled_at' => now(),
            'requires_password_reset' => true,
        ], $user);

        // Require password change
        $user->update(['password_changed_at' => now()]);

        return true;
    }

    /**
     * Generate backup codes for emergency access.
     */
    public function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(Str::random(8));
        }
        return $codes;
    }

    /**
     * Verify backup code.
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        if (!$user->mfa_backup_codes) {
            return false;
        }

        $backupCodes = decrypt($user->mfa_backup_codes);
        
        $code = strtoupper($code);
        
        $key = array_search($code, $backupCodes);
        
        if ($key !== false) {
            // Remove used backup code
            unset($backupCodes[$key]);
            $user->update([
                'mfa_backup_codes' => encrypt(array_values($backupCodes))
            ]);

            // Log backup code usage
            SecurityAuditService::logEvent('backup_code_used', [
                'user_id' => $user->id,
                'remaining_codes' => count($backupCodes),
            ], $user);

            return true;
        }

        return false;
    }

    /**
     * Get remaining backup codes count.
     */
    public function getRemainingBackupCodes(User $user): int
    {
        if (!$user->mfa_backup_codes) {
            return 0;
        }

        $backupCodes = decrypt($user->mfa_backup_codes);
        return count($backupCodes);
    }

    /**
     * Generate new backup codes (requires MFA verification).
     */
    public function regenerateBackupCodes(User $user, string $mfaToken): array
    {
        if (!$this->verifyToken($user->mfa_secret, $mfaToken)) {
            return [];
        }

        $newBackupCodes = $this->generateBackupCodes();
        
        $user->update([
            'mfa_backup_codes' => encrypt($newBackupCodes),
        ]);

        SecurityAuditService::logEvent('backup_codes_regenerated', [
            'user_id' => $user->id,
            'regenerated_at' => now(),
        ], $user);

        return $newBackupCodes;
    }

    /**
     * Check if MFA is required for user.
     */
    public function isMfaRequired(User $user): bool
    {
        // Always require MFA for admin users
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // Check if MFA is enabled
        if ($user->mfa_enabled) {
            return true;
        }

        // Check if user is in high-risk category
        $riskScore = SecurityAuditService::getUserRiskScore($user);
        return $riskScore['risk_level'] === 'high' || $riskScore['risk_level'] === 'critical';
    }

    /**
     * Get MFA setup status.
     */
    public function getSetupStatus(User $user): array
    {
        $secret = cache()->get("mfa_secret:{$user->id}");
        $expires = cache()->get("mfa_secret:{$user->id}:expires");
        
        return [
            'mfa_enabled' => $user->mfa_enabled,
            'has_secret' => $user->mfa_secret !== null,
            'pending_setup' => $secret !== null && $expires && $expires->isFuture(),
            'setup_expires_at' => $expires,
            'backup_codes_count' => $this->getRemainingBackupCodes($user),
            'enabled_at' => $user->mfa_enabled_at,
        ];
    }

    /**
     * Validate MFA setup token.
     */
    public function validateSetupToken(User $user, string $token): bool
    {
        $secret = cache()->get("mfa_secret:{$user->id}");
        $expires = cache()->get("mfa_secret:{$user->id}:expires");
        
        if (!$secret || !$expires || $expires->isPast()) {
            return false;
        }

        return $this->verifyToken($secret, $token);
    }

    /**
     * Generate QR code image.
     */
    protected function generateQrCodeImage(string $otpauthUrl): string
    {
        try {
            // Generate SVG QR code for better quality
            $renderer = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\Svg\SvgWriter());
            $renderer->writeString($otpauthUrl);
            
            return $renderer->getString();
        } catch (\Exception $e) {
            // Fallback to simple image generation
            return $this->generateSimpleQrCode($otpauthUrl);
        }
    }

    /**
     * Generate simple QR code (fallback).
     */
    protected function generateSimpleQrCode(string $data): string
    {
        // Simple fallback - in production you'd use a proper QR library
        $url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($data);
        return $url;
    }

    /**
     * Get authentication rate limiting info.
     */
    public function getRateLimitInfo(User $user): array
    {
        $key = "mfa_attempts:{$user->id}";
        $maxAttempts = 5;
        $decayMinutes = 15;
        
        $attempts = cache()->get($key, 0);
        $remaining = max(0, $maxAttempts - $attempts);
        $lockedUntil = cache()->get("mfa_locked:{$user->id}");
        
        return [
            'max_attempts' => $maxAttempts,
            'attempts' => $attempts,
            'remaining' => $remaining,
            'locked_until' => $lockedUntil,
            'is_locked' => $lockedUntil && $lockedUntil->isFuture(),
        ];
    }

    /**
     * Record MFA attempt.
     */
    public function recordAttempt(User $user, bool $success = false): void
    {
        $key = "mfa_attempts:{$user->id}";
        $attempts = cache()->get($key, 0);
        
        if ($success) {
            cache()->forget($key);
            cache()->forget("mfa_locked:{$user->id}");
        } else {
            $attempts++;
            cache()->put($key, $attempts, now()->addMinutes(15));
            
            if ($attempts >= 5) {
                cache()->put("mfa_locked:{$user->id}", now()->addMinutes(15), now()->addMinutes(15));
                
                SecurityAuditService::logEvent('mfa_locked', [
                    'user_id' => $user->id,
                    'attempts' => $attempts,
                    'locked_until' => now()->addMinutes(15),
                ], $user);
            }
        }
    }

    /**
     * Check if MFA is temporarily locked.
     */
    public function isLocked(User $user): bool
    {
        return cache()->get("mfa_locked:{$user->id}")?->isFuture() ?? false;
    }

    /**
     * Get trusted devices for user.
     */
    public function getTrustedDevices(User $user): array
    {
        $devices = DB::table('mfa_trusted_devices')
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->get();

        return $devices->map(function ($device) {
            return [
                'id' => $device->id,
                'device_name' => $device->device_name,
                'ip_address' => $device->ip_address,
                'user_agent' => $device->user_agent,
                'created_at' => $device->created_at,
                'expires_at' => $device->expires_at,
            ];
        })->toArray();
    }

    /**
     * Trust current device.
     */
    public function trustDevice(User $user, int $days = 30): void
    {
        DB::table('mfa_trusted_devices')->insert([
            'user_id' => $user->id,
            'device_name' => $this->getDeviceName(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->header('User-Agent'),
            'fingerprint' => hash('sha256', request()?->header('User-Agent', '') . request()?->ip()),
            'expires_at' => now()->addDays($days),
            'created_at' => now(),
        ]);

        SecurityAuditService::logEvent('mfa_device_trusted', [
            'user_id' => $user->id,
            'device_name' => $this->getDeviceName(),
            'trust_duration_days' => $days,
        ], $user);
    }

    /**
     * Get device name from user agent.
     */
    protected function getDeviceName(): string
    {
        $userAgent = request()?->header('User-Agent', '');
        
        if (strpos($userAgent, 'Chrome') !== false) {
            return 'Chrome Browser';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            return 'Firefox Browser';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            return 'Safari Browser';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            return 'Edge Browser';
        } elseif (strpos($userAgent, 'Mobile') !== false) {
            return 'Mobile Device';
        }
        
        return 'Unknown Device';
    }

    /**
     * Remove trusted device.
     */
    public function removeTrustedDevice(User $user, int $deviceId): bool
    {
        $deleted = DB::table('mfa_trusted_devices')
            ->where('id', $deviceId)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted) {
            SecurityAuditService::logEvent('mfa_device_untrusted', [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ], $user);
        }

        return $deleted > 0;
    }
}