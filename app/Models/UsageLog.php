<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'action',
        'resource',
        'method',
        'url',
        'request_data',
        'response_status',
        'response_time',
        'memory_usage',
        'cpu_usage',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'request_data' => 'array',
        'metadata' => 'array',
        'response_time' => 'float',
        'memory_usage' => 'integer',
        'cpu_usage' => 'float',
    ];

    /**
     * Relationship with License
     */
    public function license()
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for license usage
     */
    public function scopeForLicense($query, $licenseId)
    {
        return $query->where('license_id', $licenseId);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for errors only
     */
    public function scopeErrors($query)
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Scope for slow requests
     */
    public function scopeSlowRequests($query, $threshold = 1000)
    {
        return $query->where('response_time', '>', $threshold);
    }

    /**
     * Get usage statistics
     */
    public static function getUsageStats($licenseId = null, $days = 30)
    {
        $query = self::where('created_at', '>=', now()->subDays($days));

        if ($licenseId) {
            $query->where('license_id', $licenseId);
        }

        return [
            'total_requests' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'unique_ips' => $query->distinct('ip_address')->count('ip_address'),
            'error_rate' => $query->whereNotNull('error_message')->count() / max($query->count(), 1) * 100,
            'avg_response_time' => $query->avg('response_time'),
            'peak_memory_usage' => $query->max('memory_usage'),
            'most_used_actions' => $query->select('action', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}