<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * AuditService - Centralized audit logging service
 *
 * Provides comprehensive audit trail functionality with automatic user detection,
 * request context capture, batch logging, and robust error handling.
 */
class AuditService
{
    /**
     * Log a creation event.
     *
     * @param Model $model The model that was created
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logCreated(Model $model, array $metadata = []): ?AuditLog
    {
        return self::logEvent('created', $model, 'Record created', [], $model->toArray(), $metadata);
    }

    /**
     * Log an update event.
     *
     * @param Model $model The model that was updated
     * @param array $oldValues The old values before update
     * @param array $newValues The new values after update
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logUpdated(Model $model, array $oldValues = [], array $newValues = [], array $metadata = []): ?AuditLog
    {
        return self::logEvent('updated', $model, 'Record updated', $oldValues, $newValues, $metadata);
    }

    /**
     * Log a deletion event.
     *
     * @param Model $model The model that was deleted
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logDeleted(Model $model, array $metadata = []): ?AuditLog
    {
        return self::logEvent('deleted', $model, 'Record deleted', $model->toArray(), [], $metadata);
    }

    /**
     * Log a workflow transition event.
     *
     * @param Model $model The model undergoing workflow transition
     * @param string $fromState The previous state
     * @param string $toState The new state
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logWorkflowTransition(Model $model, string $fromState, string $toState, array $metadata = []): ?AuditLog
    {
        $description = "Workflow transition: {$fromState} → {$toState}";
        $metadata = array_merge($metadata, [
            'workflow_from' => $fromState,
            'workflow_to' => $toState,
        ]);

        return self::logEvent('workflow_transition', $model, $description, ['status' => $fromState], ['status' => $toState], $metadata);
    }

    /**
     * Log a weight change event.
     *
     * @param Model $model The model with weight change
     * @param float $oldWeight The previous weight
     * @param float $newWeight The new weight
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logWeightChange(Model $model, float $oldWeight, float $newWeight, array $metadata = []): ?AuditLog
    {
        $description = "Weight changed: {$oldWeight} → {$newWeight}";
        $metadata = array_merge($metadata, [
            'weight_change' => $newWeight - $oldWeight,
            'weight_unit' => $metadata['unit'] ?? 'kg',
        ]);

        return self::logEvent('weight_change', $model, $description, ['weight' => $oldWeight], ['weight' => $newWeight], $metadata);
    }

    /**
     * Log a custom event.
     *
     * @param string $eventType The event type
     * @param Model $model The related model
     * @param string $description Human-readable description
     * @param array $oldValues Old values (if applicable)
     * @param array $newValues New values (if applicable)
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logCustom(string $eventType, Model $model, string $description, array $oldValues = [], array $newValues = [], array $metadata = []): ?AuditLog
    {
        return self::logEvent($eventType, $model, $description, $oldValues, $newValues, $metadata);
    }

    /**
     * Log an authentication event.
     *
     * @param string $eventType The auth event type (login, logout, failed_login, etc.)
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logAuthEvent(string $eventType, array $metadata = []): ?AuditLog
    {
        $user = Auth::user();
        $description = match($eventType) {
            'login' => 'User logged in',
            'logout' => 'User logged out',
            'failed_login' => 'Failed login attempt',
            'password_changed' => 'Password changed',
            'mfa_enabled' => 'MFA enabled',
            'mfa_disabled' => 'MFA disabled',
            default => ucfirst(str_replace('_', ' ', $eventType))
        };

        return self::logEvent($eventType, $user, $description, [], [], $metadata);
    }

    /**
     * Log multiple events in batch.
     *
     * @param array $events Array of event data, each containing: eventType, model, description, oldValues, newValues, metadata
     * @return Collection Collection of created audit log entries
     */
    public static function logBatch(array $events): Collection
    {
        $logs = collect();

        foreach ($events as $event) {
            $log = self::logEvent(
                $event['eventType'] ?? 'custom',
                $event['model'] ?? null,
                $event['description'] ?? 'Batch event',
                $event['oldValues'] ?? [],
                $event['newValues'] ?? [],
                $event['metadata'] ?? []
            );

            if ($log) {
                $logs->push($log);
            }
        }

        return $logs;
    }

    /**
     * Core logging method with error handling.
     *
     * @param string $eventType The event type
     * @param Model|null $model The related model (can be null for system events)
     * @param string $description Human-readable description
     * @param array $oldValues Old values
     * @param array $newValues New values
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    protected static function logEvent(string $eventType, ?Model $model, string $description, array $oldValues = [], array $newValues = [], array $metadata = []): ?AuditLog
    {
        try {
            // Detect current user
            $user = self::getCurrentUser();

            // Capture request context
            $requestContext = self::captureRequestContext();

            // Prepare audit data - ensure event_description is not null
            $auditData = [
                'auditable_type' => $model ? get_class($model) : null,
                'auditable_id' => $model ? $model->getKey() : null,
                'user_id' => $user?->id,
                'event_type' => $eventType,
                'event_description' => $description ?: 'System event', // Ensure not null
                'old_values' => $oldValues ?: [],
                'new_values' => $newValues ?: [],
                'metadata' => array_merge($metadata ?: [], [
                    'model_class' => $model ? get_class($model) : null,
                    'model_id' => $model ? $model->getKey() : null,
                    'user_id' => $user?->id,
                    'user_name' => $user?->name,
                    'timestamp' => now()->toISOString(),
                ]),
                'ip_address' => $requestContext['ip_address'],
                'user_agent' => $requestContext['user_agent'],
                'session_id' => $requestContext['session_id'],
            ];

            // Create audit log entry
            $auditLog = AuditLog::create($auditData);

            // Return the created log or null if creation failed
            return $auditLog ?: null;

        } catch (Throwable $e) {
            // Fallback logging to Laravel logs
            self::fallbackLog($eventType, $model, $description, $e);
            return null;
        }
    }

    /**
     * Get the current authenticated user.
     *
     * @return Model|null The current user or null
     */
    protected static function getCurrentUser(): ?Model
    {
        try {
            return Auth::user();
        } catch (Throwable $e) {
            // If auth system fails, return null
            return null;
        }
    }

    /**
     * Capture request context information.
     *
     * @return array Request context data
     */
    protected static function captureRequestContext(): array
    {
        try {
            return [
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'session_id' => session()->getId(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
            ];
        } catch (Throwable $e) {
            // If request context fails, return defaults
            return [
                'ip_address' => null,
                'user_agent' => null,
                'session_id' => null,
                'url' => null,
                'method' => null,
            ];
        }
    }

    /**
     * Fallback logging when audit logging fails.
     *
     * @param string $eventType The event type
     * @param Model|null $model The related model
     * @param string $description The description
     * @param Throwable $exception The exception that caused the failure
     * @return void
     */
    protected static function fallbackLog(string $eventType, ?Model $model, string $description, Throwable $exception): void
    {
        try {
            Log::error('AuditService logging failed', [
                'event_type' => $eventType,
                'model_class' => $model ? get_class($model) : null,
                'model_id' => $model ? $model->getKey() : null,
                'description' => $description,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'user_id' => self::getCurrentUser()?->id,
                'ip_address' => Request::ip(),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (Throwable $fallbackException) {
            // If even fallback logging fails, silently fail to prevent infinite loops
            // In production, you might want to send alerts or use external logging
        }
    }

    /**
     * Log a system event (not related to a specific model).
     *
     * @param string $eventType The event type
     * @param string $description Human-readable description
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logSystemEvent(string $eventType, string $description, array $metadata = []): ?AuditLog
    {
        return self::logEvent($eventType, null, $description, [], [], $metadata);
    }

    /**
     * Log a security event.
     *
     * @param string $eventType The security event type
     * @param string $description Human-readable description
     * @param array $metadata Additional metadata including security details
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logSecurityEvent(string $eventType, string $description, array $metadata = []): ?AuditLog
    {
        $metadata = array_merge($metadata, [
            'security_event' => true,
            'severity' => $metadata['severity'] ?? 'medium',
        ]);

        return self::logSystemEvent($eventType, $description, $metadata);
    }

    /**
     * Log a data export event.
     *
     * @param string $exportType The type of export (csv, pdf, etc.)
     * @param array $filters Applied filters
     * @param int $recordCount Number of records exported
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logDataExport(string $exportType, array $filters = [], int $recordCount = 0, array $metadata = []): ?AuditLog
    {
        $description = "Data exported: {$exportType} ({$recordCount} records)";
        $metadata = array_merge($metadata, [
            'export_type' => $exportType,
            'filters' => $filters,
            'record_count' => $recordCount,
        ]);

        return self::logSystemEvent('data_export', $description, $metadata);
    }

    /**
     * Log a configuration change event.
     *
     * @param string $configKey The configuration key that was changed
     * @param mixed $oldValue The old value
     * @param mixed $newValue The new value
     * @param array $metadata Additional metadata
     * @return AuditLog|null The created audit log entry or null on failure
     */
    public static function logConfigChange(string $configKey, $oldValue, $newValue, array $metadata = []): ?AuditLog
    {
        $description = "Configuration changed: {$configKey}";
        $metadata = array_merge($metadata, [
            'config_key' => $configKey,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);

        return self::logSystemEvent('config_change', $description, $metadata);
    }
}