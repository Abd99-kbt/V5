<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeightTransferAuditLog extends Model
{
    protected $fillable = [
        'order_processing_id',
        'weight_transfer_id',
        'user_id',
        'weight_received',
        'weight_transferred',
        'transfer_destination',
        'stock_change_type',
        'stock_quantity_change',
        'warehouse_id',
        'product_id',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'weight_received' => 'decimal:2',
        'weight_transferred' => 'decimal:2',
        'stock_quantity_change' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the order processing.
     */
    public function orderProcessing(): BelongsTo
    {
        return $this->belongsTo(OrderProcessing::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the weight transfer.
     */
    public function weightTransfer(): BelongsTo
    {
        return $this->belongsTo(WeightTransfer::class);
    }

    /**
     * Get the warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get transfer destination label
     */
    public function getTransferDestinationLabelAttribute(): string
    {
        return match($this->transfer_destination) {
            'sorting' => 'Sorting Stage',
            'cutting' => 'Cutting Stage',
            'final_delivery' => 'Final Delivery',
            default => $this->transfer_destination ?? 'Unknown'
        };
    }

    /**
     * Log stock change for audit trail
     */
    public static function logStockChange(int $weightTransferId, int $userId, Stock $stock, float $quantityChange, string $changeType, string $notes = null): void
    {
        self::create([
            'weight_transfer_id' => $weightTransferId,
            'user_id' => $userId,
            'stock_change_type' => $changeType,
            'stock_quantity_change' => $quantityChange,
            'warehouse_id' => $stock->warehouse_id,
            'product_id' => $stock->product_id,
            'notes' => $notes,
            'metadata' => [
                'previous_quantity' => $stock->quantity - $quantityChange,
                'new_quantity' => $stock->quantity,
                'change_type' => $changeType,
                'transfer_group_id' => WeightTransfer::find($weightTransferId)?->transfer_group_id,
            ]
        ]);
    }
}
