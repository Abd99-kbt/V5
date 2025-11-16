<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HandoverAuditLog extends Model
{
    protected $fillable = [
        'order_processing_id',
        'user_id',
        'action',
        'notes',
        'metadata',
    ];

    protected $casts = [
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
}
