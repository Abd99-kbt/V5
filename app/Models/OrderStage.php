<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStage extends Model
{
    protected $fillable = [
        'order_id',
        'stage_name',
        'stage_order',
        'status',
        'started_at',
        'completed_at',
        'assigned_to',
        'approved_by',
        'approved_at',
        'notes',
        'weight_input',
        'weight_output',
        'waste_weight',
        'waste_reason',
        'from_warehouse_id',
        'to_warehouse_id',
        'requires_approval',
        'approval_status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'weight_input' => 'decimal:2',
        'weight_output' => 'decimal:2',
        'waste_weight' => 'decimal:2',
        'requires_approval' => 'boolean',
    ];

    /**
     * Get the order that owns the stage.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user assigned to this stage.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who approved this stage.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the source warehouse.
     */
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    /**
     * Get the destination warehouse.
     */
    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    /**
     * Check if stage is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'معلق';
    }

    /**
     * Check if stage is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'قيد_التنفيذ';
    }

    /**
     * Check if stage is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'مكتمل';
    }

    /**
     * Check if stage requires approval
     */
    public function requiresApproval(): bool
    {
        return $this->requires_approval;
    }

    /**
     * Check if stage is approved
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'معتمد';
    }

    /**
     * Start the stage
     */
    public function start(): bool
    {
        if ($this->status !== 'معلق') {
            return false;
        }

        $this->status = 'قيد_التنفيذ';
        $this->started_at = now();
        return $this->save();
    }

    /**
     * Complete the stage
     */
    public function complete(): bool
    {
        if ($this->status !== 'قيد_التنفيذ') {
            return false;
        }

        $this->status = 'مكتمل';
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Approve the stage
     */
    public function approve($userId): bool
    {
        if (!$this->requires_approval || $this->approval_status === 'معتمد') {
            return false;
        }

        $this->approval_status = 'معتمد';
        $this->approved_by = $userId;
        $this->approved_at = now();
        return $this->save();
    }

    /**
     * Reject the stage
     */
    public function reject($userId, $reason = null): bool
    {
        if (!$this->requires_approval || $this->approval_status === 'مرفوض') {
            return false;
        }

        $this->approval_status = 'مرفوض';
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . "مرفوض: " . $reason;
        return $this->save();
    }

    /**
     * Record weight movement
     */
    public function recordWeightMovement($inputWeight, $outputWeight, $wasteWeight = 0, $wasteReason = null): bool
    {
        $this->weight_input = $inputWeight;
        $this->weight_output = $outputWeight;
        $this->waste_weight = $wasteWeight;
        $this->waste_reason = $wasteReason;
        return $this->save();
    }

    /**
     * Get stage color for UI
     */
    public function getStageColorAttribute(): string
    {
        return match($this->status) {
            'معلق' => 'gray',
            'قيد_التنفيذ' => 'blue',
            'مكتمل' => 'green',
            'مرفوض' => 'red',
            default => 'gray'
        };
    }

    /**
     * Scope for pending stages
     */
    public function scopePending($query)
    {
        return $query->where('status', 'معلق');
    }

    /**
     * Scope for in progress stages
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'قيد_التنفيذ');
    }

    /**
     * Scope for completed stages
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'مكتمل');
    }

    /**
     * Scope for stages requiring approval
     */
    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    /**
     * Scope for approved stages
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'معتمد');
    }
}