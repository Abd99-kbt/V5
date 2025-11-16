<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStageHistory extends Model
{
    protected $fillable = [
        'order_id',
        'work_stage_id',
        'previous_stage',
        'new_stage',
        'action',
        'action_by',
        'action_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'action_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the order that owns the history record.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the work stage for this history record.
     */
    public function workStage(): BelongsTo
    {
        return $this->belongsTo(WorkStage::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function actionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    /**
     * Scope for specific actions
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for specific order
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope for date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('action_at', [$startDate, $endDate]);
    }

    /**
     * Get action description
     */
    public function getActionDescriptionAttribute(): string
    {
        return match($this->action) {
            'start' => 'بدء المرحلة',
            'complete' => 'إكمال المرحلة',
            'skip' => 'تخطي المرحلة',
            'rollback' => 'العودة للمرحلة السابقة',
            'move' => 'نقل لمرحلة',
            default => $this->action
        };
    }
}
