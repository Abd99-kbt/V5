<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth as AuthFacade;

class UserActivityService
{
    /**
     * Log user activity.
     */
    public static function logActivity(string $action, array $data = [], ?User $user = null): void
    {
        $user = $user ?? AuthFacade::user();
        
        try {
            DB::table('user_activities')->insert([
                'user_id' => $user?->id,
                'action' => $action,
                'data' => json_encode($data),
                'ip_address' => $data['ip_address'] ?? request()->ip(),
                'user_agent' => $data['user_agent'] ?? request()->header('User-Agent'),
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't throw exception to avoid breaking flow
            Log::error('Failed to log user activity: ' . $e->getMessage(), [
                'action' => $action,
                'data' => $data,
                'user_id' => $user?->id
            ]);
        }
    }

    /**
     * Get user activities with pagination.
     */
    public static function getUserActivities(User $user, int $limit = 50, int $offset = 0): \Illuminate\Support\Collection
    {
        return DB::table('user_activities')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Get recent activities across all users (for admin).
     */
    public static function getRecentActivities(int $limit = 100): \Illuminate\Support\Collection
    {
        return DB::table('user_activities')
            ->join('users', 'user_activities.user_id', '=', 'users.id')
            ->select('user_activities.*', 'users.name as user_name', 'users.username')
            ->orderBy('user_activities.created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activities by action type.
     */
    public static function getActivitiesByAction(string $action, int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table('user_activities')
            ->where('action', $action)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activities by IP address (for security monitoring).
     */
    public static function getActivitiesByIp(string $ipAddress, int $limit = 100): \Illuminate\Support\Collection
    {
        return DB::table('user_activities')
            ->where('ip_address', $ipAddress)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old activities.
     */
    public static function cleanupOldActivities(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return DB::table('user_activities')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Get user's login statistics.
     */
    public static function getUserLoginStats(User $user): array
    {
        $activities = DB::table('user_activities')
            ->where('user_id', $user->id)
            ->whereIn('action', ['successful_login', 'failed_login', 'failed_mfa_attempt'])
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $stats = [
            'successful_logins' => $activities->where('action', 'successful_login')->count(),
            'failed_logins' => $activities->where('action', 'failed_login')->count(),
            'failed_mfa_attempts' => $activities->where('action', 'failed_mfa_attempt')->count(),
            'last_login' => $activities->where('action', 'successful_login')->first()?->created_at,
            'unique_ips' => $activities->unique('ip_address')->count(),
            'unique_user_agents' => $activities->unique('user_agent')->count(),
        ];

        return $stats;
    }

    /**
     * Detect suspicious patterns in user activities.
     */
    public static function detectSuspiciousPatterns(User $user): array
    {
        $suspiciousIndicators = [];
        
        // Check for rapid login attempts
        $rapidAttempts = DB::table('user_activities')
            ->where('user_id', $user->id)
            ->where('action', 'failed_login')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($rapidAttempts >= 5) {
            $suspiciousIndicators[] = [
                'type' => 'rapid_failed_attempts',
                'count' => $rapidAttempts,
                'severity' => 'high',
                'description' => 'Multiple failed login attempts in short time'
            ];
        }

        // Check for multiple IP addresses in short time
        $uniqueIps = DB::table('user_activities')
            ->where('user_id', $user->id)
            ->where('action', 'successful_login')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->distinct('ip_address')
            ->count('ip_address');

        if ($uniqueIps > 1) {
            $suspiciousIndicators[] = [
                'type' => 'multiple_ips',
                'count' => $uniqueIps,
                'severity' => 'medium',
                'description' => 'Logins from multiple IP addresses in short time'
            ];
        }

        // Check for unusual login times
        $unusualHour = now()->hour < 6 || now()->hour > 22;
        if ($unusualHour) {
            $recentLogin = DB::table('user_activities')
                ->where('user_id', $user->id)
                ->where('action', 'successful_login')
                ->where('created_at', '>=', now()->subMinutes(60))
                ->exists();

            if ($recentLogin) {
                $suspiciousIndicators[] = [
                    'type' => 'unusual_login_time',
                    'severity' => 'low',
                    'description' => 'Login during unusual hours'
                ];
            }
        }

        return $suspiciousIndicators;
    }

    /**
     * Get activity summary for a date range.
     */
    public static function getActivitySummary(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        $activities = DB::table('user_activities')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $summary = [
            'total_activities' => $activities->count(),
            'unique_users' => $activities->unique('user_id')->count(),
            'unique_ips' => $activities->unique('ip_address')->count(),
            'action_counts' => $activities->groupBy('action')->map->count(),
            'daily_activity' => $activities->groupBy(function ($activity) {
                return $activity->created_at->format('Y-m-d');
            })->map->count(),
        ];

        return $summary;
    }

    /**
     * Export activities to CSV.
     */
    public static function exportActivities(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): string
    {
        $activities = DB::table('user_activities')
            ->join('users', 'user_activities.user_id', '=', 'users.id')
            ->select('user_activities.*', 'users.name as user_name', 'users.username')
            ->whereBetween('user_activities.created_at', [$startDate, $endDate])
            ->orderBy('user_activities.created_at', 'desc')
            ->get();

        $filename = 'user_activities_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';
        $path = storage_path('app/exports/' . $filename);

        // Create directory if it doesn't exist
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');
        
        // Add headers
        fputcsv($file, [
            'Date/Time',
            'User',
            'Username',
            'Action',
            'IP Address',
            'User Agent',
            'Data'
        ]);

        // Add data rows
        foreach ($activities as $activity) {
            fputcsv($file, [
                $activity->created_at->format('Y-m-d H:i:s'),
                $activity->user_name,
                $activity->username,
                $activity->action,
                $activity->ip_address,
                $activity->user_agent,
                $activity->data
            ]);
        }

        fclose($file);

        return $path;
    }
}