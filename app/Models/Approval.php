<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'type',
        'status',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'status' => 'string',
        'type' => 'string',
    ];

    /**
     * Get the user that owns the approval.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
