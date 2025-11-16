<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    // use Backpack\CRUD\app\Models\Traits\CrudTrait; // Temporarily disabled
    
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity',
        'unit_cost',
        'expiry_date',
        'batch_number',
        'location',
        'is_active',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the product that owns the stock.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that owns the stock.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get available quantity (quantity - reserved_quantity)
     */
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    /**
     * Get total value of stock
     */
    public function getTotalValueAttribute(): float
    {
        return $this->quantity * $this->unit_cost;
    }

    /**
     * Check if stock is expired
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if stock is expiring soon (within 30 days)
     */
    public function isExpiringSoon(): bool
    {
        return $this->expiry_date &&
               $this->expiry_date->isFuture() &&
               $this->expiry_date->diffInDays(now()) <= 30;
    }

    /**
     * Reserve quantity for orders
     */
    public function reserve(int $quantity): bool
    {
        if ($this->available_quantity >= $quantity) {
            $this->reserved_quantity += $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Release reserved quantity
     */
    public function release(int $quantity): bool
    {
        if ($this->reserved_quantity >= $quantity) {
            $this->reserved_quantity -= $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Add to stock quantity
     */
    public function addStock(int $quantity, float $unitCost = null): bool
    {
        $this->quantity += $quantity;
        if ($unitCost) {
            $this->unit_cost = $unitCost;
        }
        return $this->save();
    }

    /**
     * Remove from stock quantity
     */
    public function removeStock(int $quantity): bool
    {
        if ($this->available_quantity >= $quantity) {
            $this->quantity -= $quantity;
            return $this->save();
        }
        return false;
    }

    /**
     * Transfer stock to another warehouse
     */
    public function transferToWarehouse(int $quantity, int $targetWarehouseId, int $userId, string $reason = null): bool
    {
        if ($this->available_quantity < $quantity) {
            return false;
        }

        // Create or update stock in target warehouse
        $targetStock = Stock::where('product_id', $this->product_id)
                           ->where('warehouse_id', $targetWarehouseId)
                           ->where('is_active', true)
                           ->first();

        if (!$targetStock) {
            $targetStock = Stock::create([
                'product_id' => $this->product_id,
                'warehouse_id' => $targetWarehouseId,
                'quantity' => 0,
                'reserved_quantity' => 0,
                'unit_cost' => $this->unit_cost,
                'is_active' => true,
            ]);
        }

        // Remove from current stock
        $this->removeStock($quantity);

        // Add to target stock
        $targetStock->addStock($quantity, $this->unit_cost);

        // Log the transfer
        // You might want to create a transfer log here

        return true;
    }

    /**
     * Record weight extraction for order
     */
    public function recordExtraction(float $weight, int $orderId, int $userId): bool
    {
        if ($this->available_quantity < $weight) {
            return false;
        }

        $this->reserved_quantity += $weight;
        return $this->save();
    }

    /**
     * Confirm extraction (move from reserved to actual removal)
     */
    public function confirmExtraction(float $weight): bool
    {
        if ($this->reserved_quantity < $weight) {
            return false;
        }

        $this->reserved_quantity -= $weight;
        $this->quantity -= $weight;
        return $this->save();
    }

    /**
     * Get weight balance report
     */
    public function getWeightBalanceReport(): array
    {
        return [
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'total_quantity' => $this->quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'available_quantity' => $this->available_quantity,
            'unit_cost' => $this->unit_cost,
            'total_value' => $this->total_value,
        ];
    }
}
