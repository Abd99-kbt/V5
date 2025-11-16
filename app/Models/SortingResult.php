<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SortingResult extends Model
{
    protected $fillable = [
        'order_processing_id',
        'order_material_id',
        'original_weight',
        'original_width',
        'roll1_weight',
        'roll1_width',
        'roll1_location',
        'roll2_weight',
        'roll2_width',
        'roll2_location',
        'waste_weight',
        'waste_reason',
        'sorted_by',
        'sorted_at',
        'sorting_notes',
        'weight_validated',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'original_weight' => 'decimal:2',
        'original_width' => 'decimal:2',
        'roll1_weight' => 'decimal:2',
        'roll1_width' => 'decimal:2',
        'roll2_weight' => 'decimal:2',
        'roll2_width' => 'decimal:2',
        'waste_weight' => 'decimal:2',
        'sorted_at' => 'datetime',
        'validated_at' => 'datetime',
        'weight_validated' => 'boolean',
    ];

    /**
     * Get the order processing that owns the sorting result.
     */
    public function orderProcessing(): BelongsTo
    {
        return $this->belongsTo(OrderProcessing::class);
    }

    /**
     * Get the order material that owns the sorting result.
     */
    public function orderMaterial(): BelongsTo
    {
        return $this->belongsTo(OrderMaterial::class);
    }

    /**
     * Get the user who performed the sorting.
     */
    public function sorter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by');
    }

    /**
     * Get the user who validated the sorting.
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * Calculate total output weight (roll1 + roll2 + waste)
     */
    public function getTotalOutputWeightAttribute(): float
    {
        return $this->roll1_weight + $this->roll2_weight + $this->waste_weight;
    }

    /**
     * Check if weight balance is correct
     */
    public function isWeightBalanced(): bool
    {
        return abs($this->original_weight - $this->total_output_weight) < 0.01;
    }

    /**
     * Validate sorting results
     */
    public function validateResults(User $user): bool
    {
        if (!$this->isWeightBalanced()) {
            return false;
        }

        $this->weight_validated = true;
        $this->validated_by = $user->id;
        $this->validated_at = now();

        return $this->save();
    }

    /**
     * Get weight difference
     */
    public function getWeightDifferenceAttribute(): float
    {
        return $this->original_weight - $this->total_output_weight;
    }
}
