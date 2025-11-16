<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class SecurityAuditService
{
    /**
     * Log a security event.
     */
    public static function logEvent(string $event, array $data = [], ?User $user = null): void
    {
        try {
            DB::table('security_audit_logs')->insert([
                'event' => $event,
                'user_id' => $user?->id,
                'data' => json_encode($data),
                'ip_address' => $data['ip'] ?? request()?->ip(),
                'user_agent' => $data['user_agent'] ?? request()?->header('User-Agent'),
                'severity' => self::getEventSeverity($event),
                'created_at' => now(),
            ]);

            // Check if we need to send alerts for high-severity events
            self::checkForAlerts($event, $data, $user);

            // Log to file as well for additional redundancy
            self::logToFile($event, $data, $user);

        } catch (\Exception $e) {
            // Log error but don't throw exception to avoid breaking flow
            Log::error('Failed to log security event: ' . $e->getMessage(), [
                'event' => $event,
                'data' => $data,
                'user_id' => $user?->id,
                'exception' => $e
            ]);
        }
    }

    /**
     * Get the severity level of an event.
     */
    protected static function getEventSeverity(string $event): string
    {
        $severityMap = [
            // Critical events
            'successful_login' => 'info',
            'failed_login' => 'warning',
            'failed_mfa_attempt' => 'warning',
            'account_locked' => 'warning',
            'password_changed' => 'info',
            'mfa_enabled' => 'info',
            'mfa_disabled' => 'warning',
            'permissions_changed' => 'warning',
            'user_created' => 'info',
            'user_deactivated' => 'warning',
            
            // Security events
            'suspicious_login' => 'warning',
            'impossible_travel_detected' => 'critical',
            'brute_force_attack' => 'critical',
            'session_hijacking_attempt' => 'critical',
            'sql_injection_attempt' => 'critical',
            'xss_attempt' => 'critical',
            'csrf_attack_attempt' => 'warning',
            
            // System events
            'database_access' => 'info',
            'file_upload' => 'info',
            'admin_access' => 'warning',
            'configuration_change' => 'warning',
            'backup_creation' => 'info',
            'system_maintenance' => 'info',
        ];

        return $severityMap[$event] ?? 'info';
    }

    /**
     * Check if we need to send alerts for this event.
     */
    protected static function checkForAlerts(string $event, array $data, ?User $user): void
    {
        $criticalEvents = [
            'brute_force_attack',
            'sql_injection_attempt',
            'xss_attempt',
            'impossible_travel_detected',
            'session_hijacking_attempt'
        ];

        if (in_array($event, $criticalEvents)) {
            self::sendSecurityAlert($event, $data, $user);
        }

        // Check for multiple failed login attempts
        if ($event === 'failed_login' && ($data['failed_attempts'] ?? 0) >= 5) {
            self::sendSecurityAlert($event, $data, $user);
        }

        // Check for multiple IP addresses in short time
        if ($event === 'suspicious_login') {
            self::sendSecurityAlert($event, $data, $user);
        }
    }

    /**
     * Send security alert email.
     */
    protected static function sendSecurityAlert(string $event, array $data, ?User $user): void
    {
        try {
            $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['super_admin', 'admin']);
            })->where('is_active', true)->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new \App\Mail\SecurityAlertMail([
                    'event' => $event,
                    'user' => $user,
                    'data' => $data,
                    'timestamp' => now(),
                    'admin' => $admin
                ]));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send security alert: ' . $e->getMessage());
        }
    }

    /**
     * Log event to file for additional redundancy.
     */
    protected static function logToFile(string $event, array $data, ?User $user): void
    {
        $logData = [
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'ip_address' => $data['ip'] ?? request()?->ip(),
            'user_agent' => $data['user_agent'] ?? request()?->header('User-Agent'),
            'data' => $data,
            'severity' => self::getEventSeverity($event),
        ];

        $message = json_encode($logData, JSON_UNESCAPED_UNICODE);
        
        $logFile = storage_path('logs/security_' . now()->format('Y-m-d') . '.log');
        
        file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get security events with filtering.
     */
    public static function getEvents(array $filters = [], int $limit = 100, int $offset = 0): \Illuminate\Support\Collection
    {
        $query = DB::table('security_audit_logs')
            ->leftJoin('users', 'security_audit_logs.user_id', '=', 'users.id')
            ->select('security_audit_logs.*', 'users.name as user_name', 'users.username')
            ->orderBy('created_at', 'desc');

        if (isset($filters['user_id'])) {
            $query->where('security_audit_logs.user_id', $filters['user_id']);
        }

        if (isset($filters['event'])) {
            $query->where('security_audit_logs.event', $filters['event']);
        }

        if (isset($filters['severity'])) {
            $query->where('security_audit_logs.severity', $filters['severity']);
        }

        if (isset($filters['ip_address'])) {
            $query->where('security_audit_logs.ip_address', $filters['ip_address']);
        }

        if (isset($filters['date_from'])) {
            $query->where('security_audit_logs.created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('security_audit_logs.created_at', '<=', $filters['date_to']);
        }

        return $query->limit($limit)->offset($offset)->get();
    }

    /**
     * Get security summary statistics.
     */
    public static function getSecuritySummary(int $days = 7): array
    {
        $startDate = now()->subDays($days);
        
        $events = DB::table('security_audit_logs')
            ->where('created_at', '>=', $startDate)
            ->get();

        $summary = [
            'total_events' => $events->count(),
            'events_by_severity' => $events->groupBy('severity')->map->count(),
            'events_by_type' => $events->groupBy('event')->map->count(),
            'unique_users' => $events->unique('user_id')->count(),
            'unique_ips' => $events->unique('ip_address')->count(),
            'critical_events' => $events->where('severity', 'critical')->count(),
            'warning_events' => $events->where('severity', 'warning')->count(),
            'daily_events' => $events->groupBy(function ($event) {
                return $event->created_at->format('Y-m-d');
            })->map->count(),
        ];

        return $summary;
    }

    /**
     * Get top suspicious IP addresses.
     */
    public static function getSuspiciousIPs(int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table('security_audit_logs')
            ->select('ip_address', 
                DB::raw('COUNT(*) as event_count'),
                DB::raw('SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_count'),
                DB::raw('SUM(CASE WHEN event LIKE "%attack%" THEN 1 ELSE 0 END) as attack_count')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('ip_address')
            ->having('event_count', '>', 5) // Only IPs with more than 5 events
            ->orderByRaw('critical_count DESC, attack_count DESC, event_count DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get user security risk score.
     */
    public static function getUserRiskScore(User $user): array
    {
        $events = DB::table('security_audit_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        $riskScore = 0;
        $riskFactors = [];

        // Count failed login attempts
        $failedLogins = $events->where('event', 'failed_login')->count();
        if ($failedLogins > 10) {
            $riskScore += 30;
            $riskFactors[] = "Multiple failed login attempts ({$failedLogins})";
        }

        // Check for critical events
        $criticalEvents = $events->where('severity', 'critical')->count();
        if ($criticalEvents > 0) {
            $riskScore += 50;
            $riskFactors[] = "Critical security events ({$criticalEvents})";
        }

        // Check for attack attempts
        $attackEvents = $events->filter(function ($event) {
            return strpos($event->event, 'attack') !== false;
        })->count();
        if ($attackEvents > 0) {
            $riskScore += 40;
            $riskFactors[] = "Attack attempts ({$attackEvents})";
        }

        // Check for IP changes
        $uniqueIPs = $events->unique('ip_address')->count();
        if ($uniqueIPs > 3) {
            $riskScore += 20;
            $riskFactors[] = "Multiple IP addresses ({$uniqueIPs})";
        }

        // Normalize risk score to 0-100
        $riskScore = min($riskScore, 100);

        $riskLevel = match (true) {
            $riskScore >= 70 => 'high',
            $riskScore >= 40 => 'medium',
            default => 'low'
        };

        return [
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'risk_factors' => $riskFactors,
            'total_events' => $events->count(),
            'last_event' => $events->first()?->created_at,
        ];
    }

    /**
     * Clean up old audit logs.
     */
    public static function cleanupOldLogs(int $daysToKeep = 365): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return DB::table('security_audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Export security events to CSV.
     */
    public static function exportEvents(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): string
    {
        $events = self::getEvents([
            'date_from' => $startDate,
            'date_to' => $endDate
        ], 10000); // Large limit for export

        $filename = 'security_audit_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.csv';
        $path = storage_path('app/exports/' . $filename);

        // Create directory if it doesn't exist
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = fopen($path, 'w');
        
        // Add headers
        fputcsv($file, [
            'Date/Time',
            'Event',
            'User',
            'Username',
            'Severity',
            'IP Address',
            'User Agent',
            'Data'
        ]);

        // Add data rows
        foreach ($events as $event) {
            fputcsv($file, [
                $event->created_at->format('Y-m-d H:i:s'),
                $event->event,
                $event->user_name ?? 'Unknown',
                $event->username ?? 'Unknown',
                $event->severity,
                $event->ip_address ?? 'Unknown',
                $event->user_agent ?? 'Unknown',
                $event->data
            ]);
        }

        fclose($file);

        return $path;
    }
}