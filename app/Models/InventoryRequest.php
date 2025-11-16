<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequest extends Model
{
    protected $fillable = [
        'weight_transfer_id',
        'warehouse_id',
        'requested_by',
        'request_type',
        'status',
        'request_notes',
        'inventory_data',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'inventory_data' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the weight transfer that owns the inventory request.
     */
    public function weightTransfer(): BelongsTo
    {
        return $this->belongsTo(WeightTransfer::class);
    }

    /**
     * Get the warehouse for this inventory request.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who requested the inventory check.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Check if request is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if request is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Complete the inventory request
     */
    public function complete(array $inventoryData = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = 'completed';
        $this->completed_at = now();

        if ($inventoryData) {
            $this->inventory_data = $inventoryData;
        }

        return $this->save();
    }

    /**
     * Cancel the inventory request
     */
    public function cancel(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = 'cancelled';
        return $this->save();
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed requests
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for requests by warehouse
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope for requests by transfer
     */
    public function scopeByTransfer($query, int $transferId)
    {
        return $query->where('weight_transfer_id', $transferId);
    }

    /**
     * Scope for requests by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('request_type', $type);
    }
}
