<?php

namespace App\Traits;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Auditable trait for automatic audit logging on models
 *
 * This trait automatically logs created, updated, and deleted events,
 * tracks changes in model attributes, supports custom audit events,
 * handles soft deletes properly, and is configurable per model.
 */
trait Auditable
{
    /**
     * The audit events that should be logged.
     * Can be overridden in the model.
     */
    protected $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * Whether auditing is enabled for this model.
     */
    protected static $auditingEnabled = true;

    /**
     * The attributes that should be excluded from audit logging.
     */
    protected $auditExclude = ['created_at', 'updated_at'];

    /**
     * Boot the auditable trait for a model.
     */
    public static function bootAuditable()
    {
        // Only boot if auditing is enabled
        if (!static::auditingEnabled()) {
            return;
        }

        static::created(function (Model $model) {
            if (in_array('created', $model->getAuditEvents())) {
                AuditService::logCreated($model);
            }
        });

        static::updating(function (Model $model) {
            if (in_array('updated', $model->getAuditEvents())) {
                $oldValues = $model->getOriginal();
                $newValues = $model->getAttributes();

                // Filter out excluded attributes and unchanged values
                $oldValues = $model->filterAuditAttributes($oldValues);
                $newValues = $model->filterAuditAttributes($newValues);

                // Only log if there are actual changes
                $changes = array_diff_assoc($newValues, $oldValues);
                if (!empty($changes)) {
                    AuditService::logUpdated($model, $oldValues, $newValues);
                }
            }
        });

        static::updated(function (Model $model) {
            // Additional logic can be added here if needed after update
        });

        static::deleting(function (Model $model) {
            if (in_array('deleted', $model->getAuditEvents())) {
                // Handle soft deletes
                if ($model->usesSoftDeletes()) {
                    AuditService::logCustom('soft_deleted', $model, 'Record soft deleted', $model->toArray(), []);
                } else {
                    AuditService::logDeleted($model);
                }
            }
        });

        static::deleted(function (Model $model) {
            // Additional logic can be added here if needed after deletion
        });

        // Handle soft delete restoration
        if (static::usesSoftDeletes()) {
            static::restoring(function (Model $model) {
                if (in_array('restored', $model->getAuditEvents())) {
                    AuditService::logCustom('restored', $model, 'Record restored from soft delete', [], $model->toArray());
                }
            });

            static::restored(function (Model $model) {
                // Additional logic can be added here if needed after restoration
            });
        }
    }

    /**
     * Check if auditing is enabled for this model.
     */
    public static function auditingEnabled(): bool
    {
        return static::$auditingEnabled;
    }

    /**
     * Enable auditing for this model.
     */
    public static function enableAuditing(): void
    {
        static::$auditingEnabled = true;
    }

    /**
     * Disable auditing for this model.
     */
    public static function disableAuditing(): void
    {
        static::$auditingEnabled = false;
    }

    /**
     * Get the audit events that should be logged.
     */
    public function getAuditEvents(): array
    {
        return $this->auditEvents;
    }

    /**
     * Set the audit events that should be logged.
     */
    public function setAuditEvents(array $events): void
    {
        $this->auditEvents = $events;
    }

    /**
     * Enable specific audit events.
     */
    public function enableAuditEvents(array $events): void
    {
        $this->auditEvents = array_unique(array_merge($this->auditEvents, $events));
    }

    /**
     * Disable specific audit events.
     */
    public function disableAuditEvents(array $events): void
    {
        $this->auditEvents = array_diff($this->auditEvents, $events);
    }

    /**
     * Get the attributes that should be excluded from audit logging.
     */
    public function getAuditExclude(): array
    {
        return $this->auditExclude;
    }

    /**
     * Set the attributes that should be excluded from audit logging.
     */
    public function setAuditExclude(array $attributes): void
    {
        $this->auditExclude = $attributes;
    }

    /**
     * Filter attributes for audit logging.
     */
    protected function filterAuditAttributes(array $attributes): array
    {
        return array_diff_key($attributes, array_flip($this->getAuditExclude()));
    }

    /**
     * Log a custom audit event.
     */
    public function auditCustom(string $eventType, string $description, array $oldValues = [], array $newValues = [], array $metadata = []): ?Model
    {
        if (!static::$auditingEnabled) {
            return null;
        }

        return AuditService::logCustom($eventType, $this, $description, $oldValues, $newValues, $metadata);
    }

    /**
     * Log a workflow transition.
     */
    public function auditWorkflowTransition(string $fromState, string $toState, array $metadata = []): ?Model
    {
        if (!static::$auditingEnabled) {
            return null;
        }

        return AuditService::logWorkflowTransition($this, $fromState, $toState, $metadata);
    }

    /**
     * Log a weight change.
     */
    public function auditWeightChange(float $oldWeight, float $newWeight, array $metadata = []): ?Model
    {
        if (!static::$auditingEnabled) {
            return null;
        }

        return AuditService::logWeightChange($this, $oldWeight, $newWeight, $metadata);
    }

    /**
     * Check if the model uses soft deletes.
     */
    protected static function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::class));
    }

    /**
     * Get the changes made to the model.
     */
    public function getChanges(): array
    {
        $changes = [];
        $original = $this->getOriginal();
        $current = $this->getAttributes();

        foreach ($current as $key => $value) {
            if (!in_array($key, $this->getAuditExclude()) && isset($original[$key]) && $original[$key] != $value) {
                $changes[$key] = [
                    'old' => $original[$key],
                    'new' => $value
                ];
            }
        }

        return $changes;
    }
}