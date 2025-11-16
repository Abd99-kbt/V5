<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseEmployeeAssignment extends Model
{
    protected $fillable = [
        'warehouse_id',
        'user_id',
        'role',
        'is_active',
        'assigned_at',
        'unassigned_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    /**
     * Get the warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific role.
     */
    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
