<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_key',
        'customer_email',
        'customer_name',
        'product_name',
        'license_type',
        'max_users',
        'max_installations',
        'expires_at',
        'is_active',
        'activation_count',
        'last_activation_at',
        'allowed_domains',
        'allowed_ips',
        'features',
        'metadata',
        'deactivated_at',
        'deactivation_reason'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_activation_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'is_active' => 'boolean',
        'allowed_domains' => 'array',
        'allowed_ips' => 'array',
        'features' => 'array',
        'metadata' => 'array',
        'activation_count' => 'integer',
        'max_users' => 'integer',
        'max_installations' => 'integer'
    ];

    protected $hidden = [
        'license_key'
    ];

    /**
     * Generate a unique license key
     */
    public static function generateLicenseKey($prefix = 'LIC')
    {
        do {
            $key = $prefix . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (self::where('license_key', $key)->exists());

        return $key;
    }

    /**
     * Check if license is valid
     */
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->deactivated_at) {
            return false;
        }

        return true;
    }

    /**
     * Check if license can be activated on current domain/IP
     */
    public function canActivate($domain = null, $ip = null)
    {
        if (!$this->isValid()) {
            return false;
        }

        // Check domain restrictions
        if ($this->allowed_domains && !in_array($domain, $this->allowed_domains)) {
            return false;
        }

        // Check IP restrictions
        if ($this->allowed_ips && !in_array($ip, $this->allowed_ips)) {
            return false;
        }

        // Check installation limits
        if ($this->max_installations && $this->activation_count >= $this->max_installations) {
            return false;
        }

        return true;
    }

    /**
     * Activate license
     */
    public function activate($domain = null, $ip = null)
    {
        if (!$this->canActivate($domain, $ip)) {
            return false;
        }

        $this->update([
            'activation_count' => $this->activation_count + 1,
            'last_activation_at' => now(),
        ]);

        return true;
    }

    /**
     * Deactivate license
     */
    public function deactivate($reason = null)
    {
        $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason
        ]);

        return true;
    }

    /**
     * Extend license expiration
     */
    public function extend(Carbon $newExpiration)
    {
        $this->update(['expires_at' => $newExpiration]);
        return true;
    }

    /**
     * Scope for active licenses
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->whereNull('deactivated_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope for expired licenses
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for licenses expiring soon
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                    ->where('expires_at', '>', now());
    }
}