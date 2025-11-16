<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CuttingResult extends Model
{
    protected $fillable = [
        'order_id',
        'order_material_id',
        'order_processing_id',
        'input_weight',
        'cut_weight',
        'waste_weight',
        'remaining_weight',
        'required_length',
        'required_width',
        'actual_cut_length',
        'actual_cut_width',
        'roll_number',
        'material_width',
        'material_grammage',
        'quality_grade',
        'batch_number',
        'pieces_cut',
        'cutting_notes',
        'cutting_machine',
        'operator_name',
        'status',
        'cutting_completed_at',
        'approved_at',
        'approved_by',
        'performed_by',
        'quality_passed',
        'quality_notes',
        'quality_measurements',
        'transfer_group_id',
        'transfers_created',
    ];

    protected $casts = [
        'input_weight' => 'decimal:2',
        'cut_weight' => 'decimal:2',
        'waste_weight' => 'decimal:2',
        'remaining_weight' => 'decimal:2',
        'required_length' => 'decimal:2',
        'required_width' => 'decimal:2',
        'actual_cut_length' => 'decimal:2',
        'actual_cut_width' => 'decimal:2',
        'material_width' => 'decimal:2',
        'material_grammage' => 'decimal:2',
        'pieces_cut' => 'integer',
        'cutting_completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'quality_passed' => 'boolean',
        'quality_measurements' => 'array',
        'transfers_created' => 'boolean',
    ];

    /**
     * Get the order that owns the cutting result.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the order material for this cutting result.
     */
    public function orderMaterial(): BelongsTo
    {
        return $this->belongsTo(OrderMaterial::class);
    }

    /**
     * Get the order processing record.
     */
    public function orderProcessing(): BelongsTo
    {
        return $this->belongsTo(OrderProcessing::class);
    }

    /**
     * Get the user who performed the cutting.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the user who approved the cutting result.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if cutting result is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if cutting result is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if cutting result is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Complete the cutting operation
     */
    public function complete(array $cuttingData): bool
    {
        DB::beginTransaction();
        try {
            $this->update([
                'cut_weight' => $cuttingData['cut_weight'] ?? 0,
                'waste_weight' => $cuttingData['waste_weight'] ?? 0,
                'remaining_weight' => $cuttingData['remaining_weight'] ?? 0,
                'actual_cut_length' => $cuttingData['actual_cut_length'] ?? null,
                'actual_cut_width' => $cuttingData['actual_cut_width'] ?? null,
                'pieces_cut' => $cuttingData['pieces_cut'] ?? 0,
                'cutting_notes' => $cuttingData['cutting_notes'] ?? null,
                'cutting_machine' => $cuttingData['cutting_machine'] ?? null,
                'operator_name' => $cuttingData['operator_name'] ?? null,
                'quality_passed' => $cuttingData['quality_passed'] ?? false,
                'quality_notes' => $cuttingData['quality_notes'] ?? null,
                'quality_measurements' => $cuttingData['quality_measurements'] ?? null,
                'status' => 'completed',
                'cutting_completed_at' => now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Approve the cutting result
     */
    public function approve(int $userId, string $notes = null): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->status = 'approved';

        if ($notes) {
            $this->quality_notes = ($this->quality_notes ? $this->quality_notes . "\n" : '') . "Approval: " . $notes;
        }

        return $this->save();
    }

    /**
     * Reject the cutting result
     */
    public function reject(int $userId, string $reason): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->status = 'rejected';
        $this->quality_notes = ($this->quality_notes ? $this->quality_notes . "\n" : '') . "Rejected: " . $reason;

        return $this->save();
    }

    /**
     * Get weight balance summary
     */
    public function getWeightBalance(): array
    {
        $expectedOutput = $this->input_weight;
        $actualOutput = $this->cut_weight + $this->waste_weight + $this->remaining_weight;
        $difference = $expectedOutput - $actualOutput;

        return [
            'input_weight' => $this->input_weight,
            'cut_weight' => $this->cut_weight,
            'waste_weight' => $this->waste_weight,
            'remaining_weight' => $this->remaining_weight,
            'total_output' => $actualOutput,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01,
            'yield_percentage' => $expectedOutput > 0 ? ($this->cut_weight / $expectedOutput) * 100 : 0,
        ];
    }

    /**
     * Get material specifications summary
     */
    public function getMaterialSpecifications(): array
    {
        return [
            'roll_number' => $this->roll_number,
            'material_width' => $this->material_width,
            'material_grammage' => $this->material_grammage,
            'quality_grade' => $this->quality_grade,
            'batch_number' => $this->batch_number,
            'required_length' => $this->required_length,
            'required_width' => $this->required_width,
            'actual_cut_length' => $this->actual_cut_length,
            'actual_cut_width' => $this->actual_cut_width,
        ];
    }

    /**
     * Scope for pending cutting results
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed cutting results
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for approved cutting results
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for cutting results by order
     */
    public function scopeByOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope for cutting results by roll number
     */
    public function scopeByRollNumber($query, string $rollNumber)
    {
        return $query->where('roll_number', $rollNumber);
    }

    /**
     * Validate cutting result data
     */
    public function validateData(): array
    {
        $errors = [];

        // Validate weights
        if ($this->input_weight <= 0) {
            $errors[] = 'Input weight must be greater than zero';
        }

        // Check weight balance
        $balance = $this->getWeightBalance();
        if (!$balance['is_balanced']) {
            $errors[] = 'Weight balance check failed: input vs output difference of ' . abs($balance['difference']) . ' kg';
        }

        // Validate required fields
        if (!$this->roll_number) {
            $errors[] = 'Roll number is required';
        }

        if ($this->pieces_cut < 0) {
            $errors[] = 'Pieces cut cannot be negative';
        }

        // Validate quality measurements if provided
        if ($this->quality_measurements) {
            if (!is_array($this->quality_measurements)) {
                $errors[] = 'Quality measurements must be a valid JSON array';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
