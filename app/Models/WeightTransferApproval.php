<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightTransferApproval extends Model
{
    protected $fillable = [
        'weight_transfer_id',
        'approver_id',
        'approval_status',
        'approval_notes',
        'approved_at',
        'rejection_reason',
        'approval_metadata',
        // New warehouse-specific fields
        'warehouse_id',
        'approval_level',
        'approval_sequence',
        'is_final_approval',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'approval_metadata' => 'array',
        'approval_sequence' => 'integer',
        'is_final_approval' => 'boolean',
    ];

    /**
     * Get the weight transfer that owns the approval.
     */
    public function weightTransfer(): BelongsTo
    {
        return $this->belongsTo(WeightTransfer::class);
    }

    /**
     * Get the user who is the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Get the warehouse for this approval.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Check if approval is pending
     */
    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    /**
     * Check if approval is approved
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Check if approval is rejected
     */
    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    /**
     * Approve the transfer
     */
    public function approve(string $notes = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->approval_status = 'approved';
        $this->approved_at = now();
        $this->approval_notes = $notes;

        return $this->save();
    }

    /**
     * Reject the transfer
     */
    public function reject(string $reason): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->approval_status = 'rejected';
        $this->approved_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /**
     * Get approval status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->approval_status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    /**
     * Scope for pending approvals
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Scope for approved approvals
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope for rejected approvals
     */
    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    /**
     * Scope for approvals by user
     */
    public function scopeByApprover($query, int $userId)
    {
        return $query->where('approver_id', $userId);
    }

    /**
     * Scope for approvals by warehouse
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope for approvals by level
     */
    public function scopeByApprovalLevel($query, string $level)
    {
        return $query->where('approval_level', $level);
    }

    /**
     * Scope for approvals by sequence
     */
    public function scopeBySequence($query, int $sequence)
    {
        return $query->where('approval_sequence', $sequence);
    }

    /**
     * Scope for final approvals
     */
    public function scopeFinalApprovals($query)
    {
        return $query->where('is_final_approval', true);
    }

    /**
     * Check if this approval is the next in sequence
     */
    public function isNextInSequence(): bool
    {
        $transfer = $this->weightTransfer;

        if (!$transfer) {
            return false;
        }

        // Check if all previous approvals are approved
        $previousApprovals = $transfer->approvals()
            ->where('approval_sequence', '<', $this->approval_sequence)
            ->where('approval_status', '!=', 'approved')
            ->exists();

        return !$previousApprovals;
    }
}
