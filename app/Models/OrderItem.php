<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'discount',
        'notes',
        'warehouse_stock_id',
        'cutting_specifications',
        'weight',
        'required_weight',
        'delivered_weight',
        'cutting_fees',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'weight' => 'decimal:2',
        'required_weight' => 'decimal:2',
        'delivered_weight' => 'decimal:2',
        'cutting_fees' => 'decimal:2',
        'cutting_specifications' => 'array',
    ];

    /**
     * Get the order that owns the order item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product that owns the order item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate total price after discount
     */
    public function getTotalAfterDiscountAttribute(): float
    {
        return $this->total_price - $this->discount;
    }

    /**
     * Get the warehouse stock for this order item
     */
    public function warehouseStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'warehouse_stock_id');
    }

    /**
     * Get cutting specifications as formatted string
     */
    public function getCuttingSpecificationsAttribute(): array
    {
        return $this->getAttributes()['cutting_specifications'] ?? [];
    }

    /**
     * Get actual weight (defaults to calculated weight if not specified)
     */
    public function getActualWeightAttribute(): float
    {
        return $this->weight ?? ($this->product?->weight ?? 0);
    }

    /**
     * Get weight difference between required and delivered
     */
    public function getWeightDifferenceAttribute(): float
    {
        return ($this->required_weight ?? 0) - ($this->delivered_weight ?? 0);
    }

    /**
     * Check if cutting specifications are complete
     */
    public function hasCompleteCuttingSpecifications(): bool
    {
        $specs = $this->cutting_specifications;
        return isset($specs['width']) && isset($specs['length']) && isset($specs['thickness']);
    }

    /**
     * Calculate cutting fees per ton
     */
    public function getCuttingFeesPerTonAttribute(): float
    {
        if ($this->delivered_weight && $this->cutting_fees) {
            return ($this->cutting_fees / $this->delivered_weight) * 1000; // Convert to per ton
        }
        return 0;
    }
}
