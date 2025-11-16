<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = [
        'source_warehouse_id',
        'destination_warehouse_id',
        'product_id',
        'quantity',
        'unit',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'executed_by',
        'approved_at',
        'executed_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'معلق' => 'warning',
            'معتمد' => 'success',
            'منفذ' => 'primary',
            'ملغي' => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'معلق' => 'معلق',
            'معتمد' => 'معتمد',
            'منفذ' => 'منفذ',
            'ملغي' => 'ملغي',
            default => 'غير محدد',
        };
    }
}
