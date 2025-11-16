<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use App\Traits\Auditable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Auditable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'theme',
        'language',
        'phone',
        'avatar',
        'is_active',
        'is_email_verified',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
        'mfa_enabled',
        'mfa_secret',
        'mfa_backup_codes',
        'mfa_enabled_at',
        'mfa_disabled_at',
        'oauth_provider',
        'oauth_id',
        'oauth_token',
        'oauth_refresh_token',
        'device_fingerprint',
        'security_questions',
        'password_changed_at',
        'account_type',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_backup_codes',
        'oauth_token',
        'oauth_refresh_token',
        'security_questions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_email_verified' => 'boolean',
            'mfa_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'mfa_enabled_at' => 'datetime',
            'mfa_disabled_at' => 'datetime',
            'security_questions' => 'encrypted:array',
            'failed_login_attempts' => 'integer',
            'account_type' => 'string',
        ];
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        $userRoles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $this->id)
            ->pluck('roles.name')
            ->toArray();
        
        return count(array_intersect($roles, $userRoles)) > 0;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermissionTo($permission, $guardName = 'web'): bool
    {
        // First check direct permissions
        $directPermissions = DB::table('model_has_permissions')
            ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_permissions.model_id', $this->id)
            ->pluck('permissions.name')
            ->toArray();
        
        if (in_array($permission, $directPermissions)) {
            return true;
        }
        
        // Check permissions through roles
        $rolePermissions = DB::table('model_has_roles')
            ->join('role_has_permissions', 'model_has_roles.role_id', '=', 'role_has_permissions.role_id')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('model_has_roles.model_id', $this->id)
            ->pluck('permissions.name')
            ->toArray();
        
        return in_array($permission, $rolePermissions);
    }
    
    /**
     * Get role names for user
     */
    public function getRoleNames(): \Illuminate\Support\Collection
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $this->id)
            ->pluck('roles.name');
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole($roles): bool
    {
        return $this->hasRole($roles);
    }
    
    /**
     * Check if user has all specified roles
     */
    public function hasAllRoles($roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        $userRoles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $this->id)
            ->pluck('roles.name')
            ->toArray();
        
        return count(array_intersect($roles, $userRoles)) === count($roles);
    }
    
    /**
     * Check if user has any of the specified permissions
     */
    public function hasAnyPermission($permissions): bool
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has all specified permissions
     */
    public function hasAllPermissions($permissions): bool
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        
        foreach ($permissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validation rules for creating users.
     */
    public static function rules(): array
    {
        return [
            'name' => 'required|string|max:255|regex:/^[\p{Arabic}\s\w\-\.]+$/u',
            'username' => 'required|string|max:255|unique:users,username|regex:/^[a-zA-Z0-9_\-\p{Arabic}]+$/u',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:12|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&].+$/',
            'theme' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:255',
            'phone' => 'nullable|string|regex:/^[\+]?[0-9\s\-\(\)]+$/',
            'account_type' => 'required|in:admin,manager,operator,viewer,guest',
            'is_active' => 'boolean',
            'is_email_verified' => 'boolean',
        ];
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return $this->password;
    }

    /**
     * Attempt to authenticate the user with flexible login (username or email).
     */
    public function findForPassport($identifier)
    {
        return $this->where(function ($query) use ($identifier) {
            $query->where('username', $identifier)
                  ->orWhere('email', $identifier);
        })->first();
    }

    /**
     * Find user by username or email for authentication.
     */
    public static function findForAuth($identifier)
    {
        return static::where(function ($query) use ($identifier) {
            $query->where('username', $identifier)
                  ->orWhere('email', $identifier);
        })->where('is_active', true)->first();
    }

    /**
     * Validate credentials for authentication.
     */
    public static function validateCredentials($credentials)
    {
        $user = static::findForAuth($credentials['login'] ?? $credentials['username'] ?? $credentials['email']);

        if (!$user) {
            return false;
        }

        // Check if account is locked
        if ($user->isAccountLocked()) {
            return false;
        }

        return Hash::check($credentials['password'], $user->password) ? $user : false;
    }

    /**
     * Check if account is locked.
     */
    public function isAccountLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if password needs to be changed (90 days default).
     */
    public function needsPasswordChange(): bool
    {
        if (!$this->password_changed_at) {
            return true; // Never changed password
        }

        $passwordExpiryDays = config('auth.password_expiry_days', 90);
        return $this->password_changed_at->addDays($passwordExpiryDays)->isPast();
    }

    /**
     * Check if user is considered high risk based on login patterns.
     */
    public function isHighRisk(): bool
    {
        // Failed login attempts in last hour
        $recentFailures = static::where('id', $this->id)
            ->where('updated_at', '>=', now()->subHour())
            ->sum('failed_login_attempts');

        return $recentFailures >= 5 || 
               ($this->last_login_ip && $this->isNewDevice()) ||
               ($this->last_login_at && $this->last_login_at->isAfter(now()->subMinutes(5)));
    }

    /**
     * Check if this is a new device login.
     */
    public function isNewDevice(): bool
    {
        if (!$this->device_fingerprint) {
            return false;
        }

        $currentFingerprint = $this->generateDeviceFingerprint();
        return $this->device_fingerprint !== $currentFingerprint;
    }

    /**
     * Generate device fingerprint from request.
     */
    public function generateDeviceFingerprint(): string
    {
        $userAgent = request()->header('User-Agent', '');
        $acceptLanguage = request()->header('Accept-Language', '');
        $acceptEncoding = request()->header('Accept-Encoding', '');
        $remoteIp = request()->ip();
        
        $fingerprintData = compact('userAgent', 'acceptLanguage', 'acceptEncoding', 'remoteIp');
        
        return hash('sha256', serialize($fingerprintData));
    }

    /**
     * Update last login information.
     */
    public function updateLoginInfo(string $ipAddress = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress ?? request()->ip(),
            'device_fingerprint' => $this->generateDeviceFingerprint(),
            'failed_login_attempts' => 0, // Reset failed attempts on successful login
            'locked_until' => null,
        ]);
    }

    /**
     * Record failed login attempt.
     */
    public function recordFailedLogin(): void
    {
        $maxAttempts = config('auth.max_login_attempts', 5);
        $lockoutMinutes = config('auth.lockout_duration', 30);

        $this->increment('failed_login_attempts');

        if ($this->failed_login_attempts >= $maxAttempts) {
            $this->update([
                'locked_until' => now()->addMinutes($lockoutMinutes),
            ]);
        }
    }

    /**
     * Get user's active sessions count.
     */
    public function getActiveSessionsCount(): int
    {
        $sessionDriver = config('session.driver');
        
        if ($sessionDriver === 'database') {
            return DB::table('sessions')
                ->where('user_id', $this->id)
                ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime')))
                ->count();
        }

        // For other drivers like redis, we can't count directly
        return 1; // Assume at least current session
    }

    /**
     * Invalidate all user sessions.
     */
    public function invalidateAllSessions(): void
    {
        $sessionDriver = config('session.driver');
        
        if ($sessionDriver === 'database') {
            DB::table('sessions')
                ->where('user_id', $this->id)
                ->delete();
        }
        
        // For other session drivers, sessions are managed per-request
        // Current session will be invalidated on logout
    }

    /**
     * Generate MFA secret.
     */
    public function generateMfaSecret(): string
    {
        $secret = Str::random(32);
        $this->update(['mfa_secret' => $secret]);
        return $secret;
    }

    /**
     * Enable MFA for user.
     */
    public function enableMfa(): void
    {
        $this->update([
            'mfa_enabled' => true,
            'mfa_secret' => $this->generateMfaSecret(),
            'mfa_enabled_at' => now(),
        ]);
    }

    /**
     * Disable MFA for user.
     */
    public function disableMfa(): void
    {
        $this->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
            'mfa_backup_codes' => null,
            'mfa_enabled_at' => null,
            'mfa_disabled_at' => now(),
        ]);
    }

    /**
     * Get user's role hierarchy level.
     */
    public function getRoleLevel(): int
    {
        $roleLevels = [
            'super_admin' => 10,
            'admin' => 9,
            'manager' => 8,
            'operator' => 7,
            'viewer' => 6,
            'guest' => 5,
        ];

        $userRole = $this->getRoleNames()->first();
        return $roleLevels[$userRole] ?? 0;
    }

    /**
     * Check if user can access another user account.
     */
    public function canAccessAccount(User $targetUser): bool
    {
        // Super admin can access any account
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Users can only access their own account
        return $this->id === $targetUser->id;
    }

    /**
     * Scope to get active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get users by account type.
     */
    public function scopeByAccountType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope to get users with recent activity.
     */
    public function scopeRecentlyActive($query, int $days = 30)
    {
        return $query->where('last_login_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get users who need password change.
     */
    public function scopeNeedsPasswordChange($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('password_changed_at')
              ->orWhere('password_changed_at', '<=', now()->subDays(config('auth.password_expiry_days', 90)));
        });
    }

    /**
     * Get user's full name with username.
     */
    public function getDisplayName(): string
    {
        return $this->name . ' (' . $this->username . ')';
    }

    /**
     * Get user's status as text.
     */
    public function getStatusText(): string
    {
        if (!$this->is_active) {
            return 'غير مفعل';
        }

        if ($this->isAccountLocked()) {
            return 'محظور مؤقتاً';
        }

        return 'نشط';
    }

    /**
     * Check if user has OAuth authentication enabled.
     */
    public function hasOAuthProvider(string $provider = null): bool
    {
        if (!$this->oauth_provider) {
            return false;
        }
        
        return $provider ? $this->oauth_provider === $provider : true;
    }

    /**
     * Get OAuth provider name in Arabic.
     */
    public function getOAuthProviderName(): string
    {
        $providers = [
            'google' => 'جوجل',
            'microsoft' => 'مايكروسوفت',
            'facebook' => 'فيسبوك',
            'twitter' => 'تويتر',
        ];

        return $providers[$this->oauth_provider] ?? 'غير محدد';
    }

    /**
     * Check if user can perform sensitive operations.
     */
    public function canPerformSensitiveOperations(): bool
    {
        // Require MFA for sensitive operations
        if ($this->hasRole(['super_admin', 'admin'])) {
            return $this->mfa_enabled === true;
        }

        return true;
    }

    /**
     * Get security profile summary.
     */
    public function getSecurityProfile(): array
    {
        return [
            'mfa_enabled' => $this->mfa_enabled,
            'account_locked' => $this->isAccountLocked(),
            'last_login' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'failed_attempts' => $this->failed_login_attempts,
            'password_expiry' => $this->needsPasswordChange(),
            'risk_level' => $this->isHighRisk() ? 'high' : 'low',
            'oauth_connected' => $this->hasOAuthProvider(),
            'active_sessions' => $this->getActiveSessionsCount(),
        ];
    }
}
