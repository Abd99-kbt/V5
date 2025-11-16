<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderMaterial extends Model
{
    protected $fillable = [
        'order_id',
        'material_id',
        'requested_weight',
        'extracted_weight',
        'sorted_weight',
        'cut_weight',
        'delivered_weight',
        'returned_weight',
        'sorting_waste_weight',
        'cutting_waste_weight',
        'total_waste_weight',
        'status',
        'extracted_at',
        'sorted_at',
        'cut_at',
        'delivered_at',
        'returned_at',
        'sorting_waste_reason',
        'cutting_waste_reason',
        'notes',
        'specifications',
        // Roll specifications
        'roll_number',
        'required_width',
        'required_length',
        'required_grammage',
        'actual_width',
        'actual_length',
        'actual_grammage',
        'quality_grade',
        'roll_source_warehouse_id',
        'roll_source_stock_id',
    ];

    protected $casts = [
        'requested_weight' => 'decimal:2',
        'extracted_weight' => 'decimal:2',
        'sorted_weight' => 'decimal:2',
        'cut_weight' => 'decimal:2',
        'delivered_weight' => 'decimal:2',
        'returned_weight' => 'decimal:2',
        'sorting_waste_weight' => 'decimal:2',
        'cutting_waste_weight' => 'decimal:2',
        'total_waste_weight' => 'decimal:2',
        'extracted_at' => 'datetime',
        'sorted_at' => 'datetime',
        'cut_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
        'specifications' => 'array',
        // Roll specifications
        'required_width' => 'decimal:2',
        'required_length' => 'decimal:2',
        'required_grammage' => 'decimal:2',
        'actual_width' => 'decimal:2',
        'actual_length' => 'decimal:2',
        'actual_grammage' => 'decimal:2',
    ];

    /**
     * Get the order that owns the material.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the material (product) for this order material.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    /**
     * Get the weight transfers for this order material.
     */
    public function weightTransfers(): HasMany
    {
        return $this->hasMany(WeightTransfer::class);
    }

    /**
     * Get the source warehouse for the roll.
     */
    public function rollSourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'roll_source_warehouse_id');
    }

    /**
     * Get the source stock for the roll.
     */
    public function rollSourceStock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'roll_source_stock_id');
    }

    /**
     * Record sorting results
     */
    public function recordSorting(float $sortedWeight, float $wasteWeight, string $wasteReason = null, int $userId): bool
    {
        $this->sorted_weight = $sortedWeight;
        $this->sorting_waste_weight = $wasteWeight;
        $this->sorting_waste_reason = $wasteReason;
        $this->sorted_at = now();
        $this->total_waste_weight = $this->sorting_waste_weight + $this->cutting_waste_weight;
        $this->status = 'مفرز';

        return $this->save();
    }

    /**
     * Record cutting results
     */
    public function recordCutting(float $cutWeight, float $wasteWeight, string $wasteReason = null): bool
    {
        $this->cut_weight = $cutWeight;
        $this->cutting_waste_weight = $wasteWeight;
        $this->cutting_waste_reason = $wasteReason;
        $this->cut_at = now();
        $this->total_waste_weight = $this->sorting_waste_weight + $this->cutting_waste_weight;
        $this->status = 'مقصوص';

        return $this->save();
    }

    /**
     * Record delivery
     */
    public function recordDelivery(float $deliveredWeight): bool
    {
        $this->delivered_weight = $deliveredWeight;
        $this->delivered_at = now();
        $this->status = 'مُسلم';

        return $this->save();
    }

    /**
     * Record return
     */
    public function recordReturn(float $returnedWeight): bool
    {
        $this->returned_weight = $returnedWeight;
        $this->returned_at = now();
        $this->status = 'مُعاد';

        return $this->save();
    }

    /**
     * Get weight balance report for this material
     */
    public function getWeightBalanceReport(): array
    {
        $input = $this->extracted_weight;
        $output = $this->delivered_weight + $this->returned_weight + $this->total_waste_weight;

        return [
            'material_id' => $this->material_id,
            'material_name' => $this->material->name ?? 'Unknown',
            'requested_weight' => $this->requested_weight,
            'extracted_weight' => $this->extracted_weight,
            'sorted_weight' => $this->sorted_weight,
            'cut_weight' => $this->cut_weight,
            'delivered_weight' => $this->delivered_weight,
            'returned_weight' => $this->returned_weight,
            'total_waste_weight' => $this->total_waste_weight,
            'balance' => $input - $output,
            'is_balanced' => abs($input - $output) < 0.01,
            'status' => $this->status,
        ];
    }

    /**
     * Check if material can be transferred to next stage
     */
    public function canTransferToStage(string $stageName): bool
    {
        return match($stageName) {
            'فرز' => $this->status === 'مستخرج',
            'قص' => $this->status === 'مفرز',
            'تعبئة' => $this->status === 'مقصوص',
            'فوترة' => $this->status === 'مقصوص',
            'تسليم' => $this->status === 'مقصوص',
            default => false,
        };
    }

    /**
     * Get available weight for transfer
     */
    public function getAvailableWeightForTransfer(): float
    {
        return match($this->status) {
            'مستخرج' => $this->extracted_weight,
            'مفرز' => $this->sorted_weight,
            'مقصوص' => $this->cut_weight,
            default => 0,
        };
    }

    /**
     * Set roll specifications from order requirements
     */
    public function setRollSpecifications(array $specs): bool
    {
        $this->required_width = $specs['width'] ?? null;
        $this->required_length = $specs['length'] ?? null;
        $this->required_grammage = $specs['grammage'] ?? null;
        $this->quality_grade = $specs['quality'] ?? null;

        return $this->save();
    }

    /**
     * Assign actual roll from warehouse stock
     */
    public function assignRollFromStock(Stock $stock, array $rollSpecs): bool
    {
        $this->roll_number = $rollSpecs['roll_number'] ?? null;
        $this->actual_width = $rollSpecs['width'] ?? null;
        $this->actual_length = $rollSpecs['length'] ?? null;
        $this->actual_grammage = $rollSpecs['grammage'] ?? null;
        $this->roll_source_warehouse_id = $stock->warehouse_id;
        $this->roll_source_stock_id = $stock->id;

        return $this->save();
    }

    /**
     * Validate if assigned roll meets requirements
     */
    public function validateRollSpecifications(): array
    {
        $issues = [];

        if ($this->required_width && $this->actual_width && $this->actual_width < $this->required_width) {
            $issues[] = "Roll width {$this->actual_width}cm is less than required {$this->required_width}cm";
        }

        if ($this->required_grammage && $this->actual_grammage && $this->actual_grammage != $this->required_grammage) {
            $issues[] = "Roll grammage {$this->actual_grammage}g/m² does not match required {$this->required_grammage}g/m²";
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'specifications' => [
                'required' => [
                    'width' => $this->required_width,
                    'length' => $this->required_length,
                    'grammage' => $this->required_grammage,
                    'quality' => $this->quality_grade,
                ],
                'actual' => [
                    'width' => $this->actual_width,
                    'length' => $this->actual_length,
                    'grammage' => $this->actual_grammage,
                    'roll_number' => $this->roll_number,
                ]
            ]
        ];
    }

    /**
     * Get roll specification summary
     */
    public function getRollSpecificationSummary(): array
    {
        return [
            'roll_number' => $this->roll_number,
            'required_specs' => [
                'width' => $this->required_width,
                'length' => $this->required_length,
                'grammage' => $this->required_grammage,
                'quality' => $this->quality_grade,
            ],
            'actual_specs' => [
                'width' => $this->actual_width,
                'length' => $this->actual_length,
                'grammage' => $this->actual_grammage,
            ],
            'source_warehouse' => $this->rollSourceWarehouse?->name,
            'source_stock_id' => $this->roll_source_stock_id,
            'validation' => $this->validateRollSpecifications(),
        ];
    }
}
