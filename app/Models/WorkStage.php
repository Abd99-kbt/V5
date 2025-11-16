<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkStage extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'order',
        'is_active',
        'color',
        'icon',
        'can_skip',
        'requires_role',
        'estimated_duration',
        'stage_group',
        'is_mandatory',
        'skip_conditions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
        'can_skip' => 'boolean',
        'estimated_duration' => 'integer',
        'is_mandatory' => 'boolean',
        'skip_conditions' => 'array',
    ];

    /**
     * Get the order processings for the work stage.
     */
    public function orderProcessings(): HasMany
    {
        return $this->hasMany(OrderProcessing::class);
    }

    /**
     * Get the work stage name based on current locale
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * Get the work stage description based on current locale
     */
    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->description_ar : $this->description_en;
    }

    /**
     * Get required role for this stage
     */
    public function getRequiredRole(): ?string
    {
        return $this->requires_role;
    }

    /**
     * Check if user can access this stage
     */
    public function canBeAccessedBy(User $user): bool
    {
        if (!$this->requires_role) {
            return true;
        }

        return $user->hasRole($this->requires_role);
    }

    /**
     * Get stage group color
     */
    public function getStageGroupColor(): string
    {
        return match($this->stage_group) {
            'preparation' => 'blue',
            'processing' => 'orange',
            'delivery' => 'green',
            default => 'gray'
        };
    }

    /**
     * Check if stage can be skipped based on conditions
     */
    public function canBeSkipped(array $conditions = []): bool
    {
        if (!$this->can_skip) {
            return false;
        }

        if (!$this->skip_conditions) {
            return true;
        }

        // Check skip conditions
        foreach ($this->skip_conditions as $key => $value) {
            if (!isset($conditions[$key]) || $conditions[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scope for active stages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for mandatory stages
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope for skippable stages
     */
    public function scopeSkippable($query)
    {
        return $query->where('can_skip', true);
    }

    /**
     * Scope for stages requiring specific role
     */
    public function scopeRequiringRole($query, $role)
    {
        return $query->where('requires_role', $role);
    }

    /**
     * Scope for stages by group
     */
    public function scopeInGroup($query, $group)
    {
        return $query->where('stage_group', $group);
    }
}
