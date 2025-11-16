<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticationService
{
    /**
     * Attempt to authenticate a user with comprehensive security checks.
     */
    public function authenticate(Request $request, string $identifier, string $password, bool $remember = false): User|false
    {
        // Rate limiting check
        if ($this->isRateLimited($request, $identifier)) {
            throw ValidationException::withMessages([
                'login' => __('auth.too_many_attempts')
            ]);
        }

        // Find user by identifier
        $user = User::findForAuth($identifier);

        // Record this login attempt
        $this->recordLoginAttempt($request, $identifier, $user);

        if (!$user) {
            // Increment rate limiter for invalid user
            RateLimiter::hit($this->throttleKey($request, $identifier));
            
            LogSecurityEvent::dispatch('failed_login', [
                'identifier' => $identifier,
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'reason' => 'user_not_found'
            ]);

            return false;
        }

        // Check if account is locked
        if ($user->isAccountLocked()) {
            LogSecurityEvent::dispatch('failed_login', [
                'user_id' => $user->id,
                'identifier' => $identifier,
                'reason' => 'account_locked',
                'locked_until' => $user->locked_until
            ]);

            return false;
        }

        // Check if account is active
        if (!$user->is_active) {
            LogSecurityEvent::dispatch('failed_login', [
                'user_id' => $user->id,
                'identifier' => $identifier,
                'reason' => 'account_inactive'
            ]);

            return false;
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            $user->recordFailedLogin();
            
            LogSecurityEvent::dispatch('failed_login', [
                'user_id' => $user->id,
                'identifier' => $identifier,
                'reason' => 'invalid_password',
                'failed_attempts' => $user->failed_login_attempts
            ]);

            RateLimiter::hit($this->throttleKey($request, $identifier));
            return false;
        }

        // Check MFA if enabled
        if ($user->mfa_enabled) {
            if (!$request->has('mfa_code')) {
                throw ValidationException::withMessages([
                    'mfa' => __('auth.mfa_required')
                ]);
            }

            if (!$this->verifyMfaCode($user, $request->mfa_code)) {
                $user->recordFailedLogin();
                
                LogSecurityEvent::dispatch('failed_mfa_attempt', [
                    'user_id' => $user->id,
                    'identifier' => $identifier
                ]);

                return false;
            }
        }

        // Check for suspicious activity
        if ($this->detectSuspiciousActivity($user, $request)) {
            // Log and potentially flag the user
            LogSecurityEvent::dispatch('suspicious_login', [
                'user_id' => $user->id,
                'identifier' => $identifier,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }

        // Update user login information
        $user->updateLoginInfo($request->ip());
        $user->update(['password_changed_at' => now()]);
        
        // Login the user
        Auth::login($user, $remember);

        // Clear rate limiter on successful login
        RateLimiter::clear($this->throttleKey($request, $identifier));

        // Log successful login
        LogSecurityEvent::dispatch('successful_login', [
            'user_id' => $user->id,
            'identifier' => $identifier,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'remember' => $remember
        ]);

        return $user;
    }

    /**
     * Register a new user with comprehensive validation.
     */
    public function register(array $data, Request $request): User
    {
        // Validate data
        $validatedData = $request->validate(User::rules());

        // Check for duplicate registrations
        $this->checkRegistrationRateLimit($request);

        // Create user
        $user = User::create([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'] ?? null,
            'password' => Hash::make($validatedData['password']),
            'phone' => $validatedData['phone'] ?? null,
            'theme' => $validatedData['theme'] ?? 'light',
            'language' => $validatedData['language'] ?? 'ar',
            'account_type' => $validatedData['account_type'],
            'is_active' => $validatedData['is_active'] ?? true,
            'is_email_verified' => false,
            'password_changed_at' => now(),
            'device_fingerprint' => $this->generateDeviceFingerprint($request),
        ]);

        // Assign default role
        $this->assignDefaultRole($user);

        // Log registration
        LogSecurityEvent::dispatch('user_registered', [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return $user;
    }

    /**
     * Check if login attempts are rate limited.
     */
    public function isRateLimited(Request $request, string $identifier): bool
    {
        $key = $this->throttleKey($request, $identifier);
        $maxAttempts = config('auth.max_login_attempts', 5);
        $decayMinutes = config('auth.lockout_duration', 30);

        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Get remaining time for rate limiting.
     */
    public function getRateLimitRemainingTime(Request $request, string $identifier): int
    {
        $key = $this->throttleKey($request, $identifier);
        return RateLimiter::availableIn($key);
    }

    /**
     * Record a login attempt for monitoring.
     */
    protected function recordLoginAttempt(Request $request, string $identifier, ?User $user): void
    {
        UserActivityService::logActivity('login_attempt', [
            'identifier' => $identifier,
            'user_id' => $user?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_fingerprint' => $this->generateDeviceFingerprint($request),
            'timestamp' => now(),
        ]);
    }

    /**
     * Verify MFA code.
     */
    protected function verifyMfaCode(User $user, string $code): bool
    {
        // For TOTP implementation, you would use something like otpauth
        // For now, we'll use a simple verification method
        return $this->verifyTotpCode($user->mfa_secret, $code);
    }

    /**
     * Verify TOTP code (Google Authenticator style).
     */
    protected function verifyTotpCode(string $secret, string $code): bool
    {
        // This is a simplified TOTP verification
        // In production, use a library like 'spomky-labs/otphp'
        
        $currentTime = floor(time() / 30);
        $codes = [];
        
        for ($i = -1; $i <= 1; $i++) {
            $time = $currentTime + $i;
            $hash = hash_hmac('sha1', pack('N*', 0, $time), base32_decode($secret), true);
            $offset = ord($hash[19]) & 0xf;
            $code = (
                ((ord($hash[$offset+0]) & 0x7f) << 24) |
                ((ord($hash[$offset+1]) & 0xff) << 16) |
                ((ord($hash[$offset+2]) & 0xff) << 8) |
                (ord($hash[$offset+3]) & 0xff)
            ) % pow(10, 6);
            
            $codes[] = sprintf('%06d', $code);
        }
        
        return in_array($code, $codes);
    }

    /**
     * Detect suspicious activity.
     */
    protected function detectSuspiciousActivity(User $user, Request $request): bool
    {
        $suspiciousIndicators = 0;
        
        // Check for new device
        if ($user->isNewDevice()) {
            $suspiciousIndicators++;
        }
        
        // Check for rapid successive logins
        if ($user->last_login_at && $user->last_login_at->isAfter(now()->subMinutes(5))) {
            $suspiciousIndicators++;
        }
        
        // Check for multiple IP addresses
        $recentLogins = User::where('id', $user->id)
            ->where('last_login_at', '>=', now()->subDays(7))
            ->distinct('last_login_ip')
            ->count('last_login_ip');
            
        if ($recentLogins > 3) {
            $suspiciousIndicators++;
        }
        
        // Check for impossible travel (simplified)
        $this->checkImpossibleTravel($user, $request);
        
        return $suspiciousIndicators >= 2;
    }

    /**
     * Check for impossible travel scenarios.
     */
    protected function checkImpossibleTravel(User $user, Request $request): void
    {
        $lastLogin = User::where('id', $user->id)
            ->whereNotNull('last_login_ip')
            ->latest('last_login_at')
            ->first();
            
        if ($lastLogin && $lastLogin->last_login_ip !== $request->ip()) {
            $timeDiff = now()->diffInMinutes($lastLogin->last_login_at);
            // If last login was less than 30 minutes ago and IP is different, flag it
            if ($timeDiff < 30) {
                LogSecurityEvent::dispatch('impossible_travel_detected', [
                    'user_id' => $user->id,
                    'last_ip' => $lastLogin->last_login_ip,
                    'current_ip' => $request->ip(),
                    'time_diff_minutes' => $timeDiff
                ]);
            }
        }
    }

    /**
     * Generate device fingerprint.
     */
    protected function generateDeviceFingerprint(Request $request): string
    {
        $userAgent = $request->header('User-Agent', '');
        $acceptLanguage = $request->header('Accept-Language', '');
        $acceptEncoding = $request->header('Accept-Encoding', '');
        $remoteIp = $request->ip();
        
        $fingerprintData = compact('userAgent', 'acceptLanguage', 'acceptEncoding', 'remoteIp');
        
        return hash('sha256', serialize($fingerprintData));
    }

    /**
     * Assign default role to new user.
     */
    protected function assignDefaultRole(User $user): void
    {
        $defaultRole = match ($user->account_type) {
            'admin' => 'admin',
            'manager' => 'manager',
            'operator' => 'operator',
            'viewer' => 'viewer',
            'guest' => 'guest',
            default => 'viewer'
        };
        
        $user->assignRole($defaultRole);
    }

    /**
     * Check registration rate limit.
     */
    protected function checkRegistrationRateLimit(Request $request): void
    {
        $key = 'registration_' . $request->ip();
        $maxRegistrations = config('auth.max_registrations_per_ip', 10);
        $decayMinutes = config('auth.registration_decay_minutes', 1440); // 24 hours
        
        if (RateLimiter::tooManyAttempts($key, $maxRegistrations)) {
            throw ValidationException::withMessages([
                'registration' => __('auth.registration_rate_limited')
            ]);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
    }

    /**
     * Get throttle key for rate limiting.
     */
    protected function throttleKey(Request $request, string $identifier): string
    {
        return Str::lower($identifier) . '|' . $request->ip();
    }

    /**
     * Invalidate all user sessions.
     */
    public function invalidateAllSessions(User $user): void
    {
        $user->invalidateAllSessions();
        
        LogSecurityEvent::dispatch('sessions_invalidated', [
            'user_id' => $user->id,
            'initiated_by' => auth()->id()
        ]);
    }

    /**
     * Enable MFA for user.
     */
    public function enableMfa(User $user): array
    {
        $secret = $user->generateMfaSecret();
        
        LogSecurityEvent::dispatch('mfa_enabled', [
            'user_id' => $user->id
        ]);
        
        return [
            'secret' => $secret,
            'qr_code' => $this->generateQrCode($user, $secret)
        ];
    }

    /**
     * Disable MFA for user.
     */
    public function disableMfa(User $user): void
    {
        $user->disableMfa();
        
        LogSecurityEvent::dispatch('mfa_disabled', [
            'user_id' => $user->id
        ]);
    }

    /**
     * Generate QR code for MFA setup.
     */
    protected function generateQrCode(User $user, string $secret): string
    {
        // Implementation would use a QR code library
        // For now, return a placeholder
        return "otpauth://totp/LaravelApp:{$user->email}?secret={$secret}&issuer=LaravelApp";
    }
}