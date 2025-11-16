<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Waste extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'reason',
        'notes',
        'is_resolved',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'quantity' => 'integer',
    ];

    /**
     * Get the product that this waste record belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
