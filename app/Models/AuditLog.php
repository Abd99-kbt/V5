<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * AuditLog Model
 *
 * Tracks all changes and events across auditable models in the system.
 * Provides comprehensive audit trail functionality with polymorphic relationships.
 */
class AuditLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'event_type',
        'event_description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the auditable model (polymorphic relationship).
     *
     * @return MorphTo
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by event type.
     *
     * @param Builder $query
     * @param string|array $eventTypes
     * @return Builder
     */
    public function scopeByEventType(Builder $query, string|array $eventTypes): Builder
    {
        return $query->whereIn('event_type', (array) $eventTypes);
    }

    /**
     * Scope to filter by date range.
     *
     * @param Builder $query
     * @param string $startDate
     * @param string|null $endDate
     * @return Builder
     */
    public function scopeDateRange(Builder $query, string $startDate, ?string $endDate = null): Builder
    {
        $query->whereDate('created_at', '>=', $startDate);

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope to filter by user.
     *
     * @param Builder $query
     * @param int|array $userIds
     * @return Builder
     */
    public function scopeByUser(Builder $query, int|array $userIds): Builder
    {
        return $query->whereIn('user_id', (array) $userIds);
    }

    /**
     * Scope to filter by auditable type.
     *
     * @param Builder $query
     * @param string|array $auditableTypes
     * @return Builder
     */
    public function scopeByAuditableType(Builder $query, string|array $auditableTypes): Builder
    {
        return $query->whereIn('auditable_type', (array) $auditableTypes);
    }

    /**
     * Log a creation event.
     *
     * @param Model $model
     * @param User|null $user
     * @param array $metadata
     * @return static
     */
    public static function logCreated(Model $model, ?User $user = null, array $metadata = []): static
    {
        return static::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => $user?->id,
            'event_type' => 'created',
            'event_description' => "Record created",
            'new_values' => $model->toArray(),
            'metadata' => array_merge($metadata, [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Log an update event.
     *
     * @param Model $model
     * @param array $oldValues
     * @param array $newValues
     * @param User|null $user
     * @param array $metadata
     * @return static
     */
    public static function logUpdated(Model $model, array $oldValues, array $newValues, ?User $user = null, array $metadata = []): static
    {
        return static::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => $user?->id,
            'event_type' => 'updated',
            'event_description' => "Record updated",
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => array_merge($metadata, [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'changed_fields' => array_keys(array_diff_assoc($newValues, $oldValues)),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Log a deletion event.
     *
     * @param Model $model
     * @param User|null $user
     * @param array $metadata
     * @return static
     */
    public static function logDeleted(Model $model, ?User $user = null, array $metadata = []): static
    {
        return static::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => $user?->id,
            'event_type' => 'deleted',
            'event_description' => "Record deleted",
            'old_values' => $model->toArray(),
            'metadata' => array_merge($metadata, [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Log a custom event.
     *
     * @param Model $model
     * @param string $eventType
     * @param string $description
     * @param User|null $user
     * @param array $oldValues
     * @param array $newValues
     * @param array $metadata
     * @return static
     */
    public static function logCustom(
        Model $model,
        string $eventType,
        string $description,
        ?User $user = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): static {
        return static::create([
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'event_description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => array_merge($metadata, [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
        ]);
    }

    /**
     * Get the old value for a specific field.
     *
     * @param string $field
     * @return mixed
     */
    public function getOldValue(string $field): mixed
    {
        return $this->old_values[$field] ?? null;
    }

    /**
     * Get the new value for a specific field.
     *
     * @param string $field
     * @return mixed
     */
    public function getNewValue(string $field): mixed
    {
        return $this->new_values[$field] ?? null;
    }

    /**
     * Get the changed fields between old and new values.
     *
     * @return array
     */
    public function getChangedFields(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        return array_keys(array_diff_assoc($this->new_values, $this->old_values));
    }

    /**
     * Check if a specific field was changed.
     *
     * @param string $field
     * @return bool
     */
    public function wasFieldChanged(string $field): bool
    {
        return in_array($field, $this->getChangedFields());
    }

    /**
     * Get a human-readable change summary.
     *
     * @return string
     */
    public function getChangeSummary(): string
    {
        $changedFields = $this->getChangedFields();

        if (empty($changedFields)) {
            return 'No changes recorded';
        }

        return 'Changed: ' . implode(', ', $changedFields);
    }

    /**
     * Get the auditable model name for display.
     *
     * @return string
     */
    public function getAuditableModelName(): string
    {
        return class_basename($this->auditable_type);
    }

    /**
     * Get the event type label for display.
     *
     * @return string
     */
    public function getEventTypeLabel(): string
    {
        return match($this->event_type) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default => ucfirst($this->event_type)
        };
    }
}