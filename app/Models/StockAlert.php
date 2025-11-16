<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAlert extends Model
{
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'type',
        'severity',
        'current_quantity',
        'threshold_quantity',
        'message',
        'is_read',
        'is_resolved',
        'resolved_at',
    ];

    protected $casts = [
        'current_quantity' => 'integer',
        'threshold_quantity' => 'integer',
        'is_read' => 'boolean',
        'is_resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the product that owns the stock alert.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that owns the stock alert.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Mark alert as read
     */
    public function markAsRead(): bool
    {
        $this->is_read = true;
        return $this->save();
    }

    /**
     * Mark alert as resolved
     */
    public function markAsResolved(): bool
    {
        $this->is_resolved = true;
        $this->resolved_at = now();
        return $this->save();
    }

    /**
     * Check if alert is critical
     */
    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    /**
     * Check if alert is high priority
     */
    public function isHighPriority(): bool
    {
        return in_array($this->severity, ['high', 'critical']);
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColor(): string
    {
        return match($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'gray'
        };
    }

    /**
     * Get alert type label
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            'low_stock' => 'انخفاض المخزون',
            'out_of_stock' => 'نفاد المخزون',
            'expiring_soon' => 'ينتهي قريباً',
            'expired' => 'منتهي الصلاحية',
            default => $this->type
        };
    }
}
