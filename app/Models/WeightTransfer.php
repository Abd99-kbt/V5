<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Traits\Auditable;

class WeightTransfer extends Model
{
    use Auditable;

    protected $fillable = [
        'order_id',
        'order_material_id',
        'from_stage',
        'to_stage',
        'weight_transferred',
        'transfer_type',
        'requested_by',
        'approved_by',
        'approved_at',
        'status',
        'notes',
        'transferred_at',
        'transfer_metadata',
        // Material specifications tracking
        'roll_number',
        'material_width',
        'material_length',
        'material_grammage',
        'quality_grade',
        'batch_number',
        // New grouped transfer fields
        'transfer_group_id',
        'transfer_category',
        'source_warehouse_id',
        'destination_warehouse_id',
        'requires_sequential_approval',
        'current_approval_level',
        // Cutting-specific fields
        'cutting_result_id',
        'pieces_transferred',
        'cutting_quality_verified',
    ];

    protected $casts = [
        'weight_transferred' => 'decimal:2',
        'approved_at' => 'datetime',
        'transferred_at' => 'datetime',
        'transfer_metadata' => 'array',
        // Material specifications
        'material_width' => 'decimal:2',
        'material_length' => 'decimal:2',
        'material_grammage' => 'decimal:2',
        // New fields
        'requires_sequential_approval' => 'boolean',
        'current_approval_level' => 'integer',
        // Cutting-specific fields
        'pieces_transferred' => 'integer',
        'cutting_quality_verified' => 'boolean',
    ];

    /**
     * Get the order that owns the transfer.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the order material for this transfer.
     */
    public function orderMaterial(): BelongsTo
    {
        return $this->belongsTo(OrderMaterial::class);
    }

    /**
     * Get the user who requested the transfer.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the transfer.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if transfer is pending approval
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transfer is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if transfer is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Approve the transfer
     */
    public function approve(int $userId): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->status = 'approved';

        return $this->save();
    }

    /**
     * Reject the transfer
     */
    public function reject(int $userId, string $reason = null): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->status = 'rejected';
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Rejected: " . $reason;

        return $this->save();
    }

    /**
     * Complete the transfer
     */
    public function complete(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        $this->status = 'completed';
        $this->transferred_at = now();

        return $this->save();
    }

    /**
     * Get transfer status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'blue',
            'rejected' => 'red',
            'completed' => 'green',
            default => 'gray',
        };
    }

    /**
     * Scope for pending transfers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved transfers
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for completed transfers
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for transfers by stage
     */
    public function scopeBetweenStages($query, string $fromStage, string $toStage)
    {
        return $query->where('from_stage', $fromStage)->where('to_stage', $toStage);
    }

    /**
     * Set material specifications for transfer tracking
     */
    public function setMaterialSpecifications(array $specs): bool
    {
        $this->roll_number = $specs['roll_number'] ?? $this->roll_number;
        $this->material_width = $specs['width'] ?? $this->material_width;
        $this->material_length = $specs['length'] ?? $this->material_length;
        $this->material_grammage = $specs['grammage'] ?? $this->material_grammage;
        $this->quality_grade = $specs['quality'] ?? $this->quality_grade;
        $this->batch_number = $specs['batch_number'] ?? $this->batch_number;

        return $this->save();
    }

    /**
     * Get material specifications summary
     */
    public function getMaterialSpecificationsSummary(): array
    {
        return [
            'roll_number' => $this->roll_number,
            'width' => $this->material_width,
            'length' => $this->material_length,
            'grammage' => $this->material_grammage,
            'quality_grade' => $this->quality_grade,
            'batch_number' => $this->batch_number,
        ];
    }

    /**
     * Scope for transfers by roll number
     */
    public function scopeByRollNumber($query, string $rollNumber)
    {
        return $query->where('roll_number', $rollNumber);
    }

    /**
     * Scope for transfers by quality grade
     */
    public function scopeByQualityGrade($query, string $qualityGrade)
    {
        return $query->where('quality_grade', $qualityGrade);
    }

    /**
     * Scope for transfers by group
     */
    public function scopeByTransferGroup($query, string $groupId)
    {
        return $query->where('transfer_group_id', $groupId);
    }

    /**
     * Scope for transfers by category
     */
    public function scopeByTransferCategory($query, string $category)
    {
        return $query->where('transfer_category', $category);
    }

    /**
     * Scope for transfers requiring sequential approval
     */
    public function scopeRequiresSequentialApproval($query)
    {
        return $query->where('requires_sequential_approval', true);
    }

    /**
     * Scope for transfers by warehouse
     */
    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where(function($q) use ($warehouseId) {
            $q->where('source_warehouse_id', $warehouseId)
              ->orWhere('destination_warehouse_id', $warehouseId);
        });
    }

    /**
     * Scope for cutting-related transfers
     */
    public function scopeCuttingTransfers($query)
    {
        return $query->where('transfer_category', 'cut_material');
    }

    /**
     * Scope for transfers by cutting result
     */
    public function scopeByCuttingResult($query, int $cuttingResultId)
    {
        return $query->where('cutting_result_id', $cuttingResultId);
    }

    /**
     * Check if transfer is cutting-related
     */
    public function isCuttingTransfer(): bool
    {
        return in_array($this->transfer_category, ['cut_material', 'cutting_waste', 'cutting_remainder']);
    }

    /**
     * Get the approvals for this transfer.
     */
    public function approvals()
    {
        return $this->hasMany(WeightTransferApproval::class);
    }

    /**
     * Get the source warehouse for this transfer.
     */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    /**
     * Get the destination warehouse for this transfer.
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * Get the cutting result associated with this transfer.
     */
    public function cuttingResult(): BelongsTo
    {
        return $this->belongsTo(CuttingResult::class);
    }

    /**
     * Request approval from the next stage manager
     */
    public function requestApproval(int $approverId, string $notes = null): WeightTransferApproval
    {
        $approval = $this->approvals()->create([
            'approver_id' => $approverId,
            'approval_status' => 'pending',
            'approval_notes' => $notes,
        ]);

        // Send notification to the approver
        $approver = User::find($approverId);
        if ($approver) {
            $approver->notify(new \App\Notifications\WeightTransferApprovalRequested($this));
        }

        return $approval;
    }

    /**
     * Create sequential approvals for multi-warehouse transfer
     */
    public function createSequentialApprovals(): void
    {
        if (!$this->requires_sequential_approval) {
            return;
        }

        // Auto-approve waste transfers
        if ($this->transfer_category === 'waste') {
            $this->autoApproveWasteTransfer();
            return;
        }

        // Create sequential approvals: cutting warehouse manager first, then delivery manager, then packaging warehouse manager
        $approvals = [
            [
                'approval_level' => 'cutting_warehouse_manager',
                'approval_sequence' => 1,
                'is_final_approval' => false,
                'warehouse_id' => $this->destination_warehouse_id, // Cutting warehouse
            ],
            [
                'approval_level' => 'delivery_manager',
                'approval_sequence' => 2,
                'is_final_approval' => false,
                'warehouse_id' => $this->destination_warehouse_id, // Cutting warehouse (delivery manager oversees transfers)
            ],
            [
                'approval_level' => 'packaging_warehouse_manager',
                'approval_sequence' => 3,
                'is_final_approval' => true,
                'warehouse_id' => $this->destination_warehouse_id, // Packaging warehouse
            ],
        ];

        foreach ($approvals as $approvalData) {
            $approver = $this->findApproverForLevel($approvalData['approval_level'], $approvalData['warehouse_id']);

            if ($approver) {
                $this->approvals()->create([
                    'approver_id' => $approver->id,
                    'warehouse_id' => $approvalData['warehouse_id'],
                    'approval_status' => 'pending',
                    'approval_level' => $approvalData['approval_level'],
                    'approval_sequence' => $approvalData['approval_sequence'],
                    'is_final_approval' => $approvalData['is_final_approval'],
                    'approval_notes' => "Approval required for {$this->transfer_category} transfer",
                ]);
            }
        }
    }

    /**
     * Auto-approve waste transfers
     */
    private function autoApproveWasteTransfer(): void
    {
        $this->approvals()->create([
            'approver_id' => 1, // System user
            'approval_status' => 'approved',
            'approval_level' => 'auto_approved',
            'approval_sequence' => 1,
            'is_final_approval' => true,
            'approved_at' => now(),
            'approval_notes' => 'Auto-approved waste transfer - no manual approval required',
        ]);

        $this->status = 'approved';
        $this->approved_by = 1; // System user
        $this->approved_at = now();
        $this->save();

        // Record audit log for auto-approval
        $this->recordApprovalHistory('auto_approved', 1, 'Waste transfer auto-approved per system policy');
    }

    /**
     * Find the appropriate approver for a given approval level and warehouse
     */
    private function findApproverForLevel(string $level, ?int $warehouseId): ?User
    {
        if (!$warehouseId) {
            return null;
        }

        // Find warehouse manager based on level
        $roleName = match($level) {
            'cutting_warehouse_manager' => 'cutting_warehouse_manager',
            'main_warehouse_manager' => 'main_warehouse_manager',
            default => null,
        };

        if (!$roleName) {
            return null;
        }

        return User::whereHas('roles', function($q) use ($roleName) {
            $q->where('name', $roleName);
        })->whereHas('warehouseAssignments', function($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        })->first();
    }

    /**
     * Check if transfer has pending approvals
     */
    public function hasPendingApprovals(): bool
    {
        return $this->approvals()->pending()->exists();
    }

    /**
     * Check if transfer is fully approved
     */
    public function isFullyApproved(): bool
    {
        return $this->approvals()->where('approval_status', '!=', 'approved')->doesntExist();
    }

    /**
     * Get the next stage manager who should approve this transfer
     */
    public function getNextStageApprover(): ?User
    {
        // Find the next stage in the order processing
        $nextStage = OrderProcessing::where('order_id', $this->order_id)
            ->where('work_stage_id', '>', $this->from_stage)
            ->orderBy('work_stage_id')
            ->first();

        if (!$nextStage) {
            return null;
        }

        // Return the assigned user of the next stage
        return $nextStage->assignedUser;
    }

    /**
     * Approve transfer by approver with sequential logic
     */
    public function approveBy(int $approverId, string $notes = null): bool
    {
        $approval = $this->approvals()->where('approver_id', $approverId)->first();

        if (!$approval || !$approval->isPending()) {
            return false;
        }

        // Check if this approval can be processed (sequential order)
        if (!$this->canApproveAtLevel($approval->approval_sequence)) {
            return false;
        }

        $approved = $approval->approve($notes);

        if ($approved) {
            $this->current_approval_level = $approval->approval_sequence;

            // If this is the final approval, mark transfer as approved
            if ($approval->is_final_approval) {
                $this->status = 'approved';
            }

            $this->save();
        }

        return $approved;
    }

    /**
     * Check if approval can be processed at the given level
     */
    private function canApproveAtLevel(int $sequence): bool
    {
        // First level can always be approved if pending
        if ($sequence === 1) {
            return true;
        }

        // Higher levels require previous levels to be approved
        $previousApprovals = $this->approvals()
            ->where('approval_sequence', '<', $sequence)
            ->where('approval_status', '!=', 'approved')
            ->exists();

        return !$previousApprovals;
    }

    /**
     * Reject transfer by approver
     */
    public function rejectBy(int $approverId, string $reason): bool
    {
        $approval = $this->approvals()->where('approver_id', $approverId)->first();

        if (!$approval || !$approval->isPending()) {
            return false;
        }

        return $approval->reject($reason);
    }

    /**
     * Complete the transfer (only if fully approved)
     */
    public function completeTransfer(): bool
    {
        if (!$this->isFullyApproved()) {
            return false;
        }

        try {
            DB::beginTransaction();

            // Update the transfer status to completed
            $completed = $this->complete();

            if (!$completed) {
                DB::rollBack();
                Log::error('Failed to update transfer status to completed', [
                    'transfer_id' => $this->id,
                    'transfer_category' => $this->transfer_category
                ]);
                return false;
            }

            // Update stock quantities for grouped transfers
            if ($this->transfer_group_id) {
                $stockUpdated = $this->updateStockQuantities();
                if (!$stockUpdated) {
                    DB::rollBack();
                    Log::error('Stock update failed during transfer completion', [
                        'transfer_id' => $this->id,
                        'transfer_group_id' => $this->transfer_group_id,
                        'transfer_category' => $this->transfer_category
                    ]);
                    return false;
                }
            } else {
                // Update stage balances for non-grouped transfers
                $this->updateStageBalances();
            }

            // Record completion in audit trail
            $this->recordApprovalHistory('completed', Auth::id(), 'Transfer completed after full approval');

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to complete weight transfer', [
                'transfer_id' => $this->id,
                'transfer_category' => $this->transfer_category,
                'is_cutting_transfer' => $this->isCuttingTransfer(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Create detailed error audit log
            WeightTransferAuditLog::create([
                'weight_transfer_id' => $this->id,
                'user_id' => Auth::id(),
                'stock_change_type' => 'transfer_completion_failed',
                'stock_quantity_change' => 0,
                'warehouse_id' => $this->source_warehouse_id,
                'product_id' => $this->orderMaterial->product_id ?? null,
                'notes' => 'Transfer completion failed: ' . $e->getMessage(),
                'metadata' => [
                    'error_type' => 'completion_exception',
                    'transfer_category' => $this->transfer_category,
                    'is_cutting_transfer' => $this->isCuttingTransfer(),
                    'transfer_group_id' => $this->transfer_group_id,
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                ]
            ]);

            return false;
        }
    }

    /**
     * Update stage balances after transfer completion
     */
    private function updateStageBalances(): void
    {
        // Find the from and to stage processings
        $fromStage = OrderProcessing::where('order_id', $this->order_id)
            ->where('work_stage_id', $this->from_stage)
            ->first();

        $toStage = OrderProcessing::where('order_id', $this->order_id)
            ->where('work_stage_id', $this->to_stage)
            ->first();

        if ($fromStage) {
            $fromStage->recordWeightTransferred($this->weight_transferred);
        }

        if ($toStage) {
            $toStage->recordWeightReceived($this->weight_transferred);
        }
    }

    /**
     * Update stock quantities for grouped transfers
     */
    private function updateStockQuantities(): bool
    {
        try {
            // Handle cutting transfers with special logic
            if ($this->isCuttingTransfer()) {
                return $this->updateCuttingStockQuantities();
            }

            // Get all transfers in the same group
            $groupedTransfers = self::where('transfer_group_id', $this->transfer_group_id)
                ->where('status', 'completed')
                ->get();

            // Calculate total weight transferred from source
            $totalTransferred = $groupedTransfers->sum('weight_transferred');

            // Reduce stock from source warehouse (sorting)
            if ($this->source_warehouse_id) {
                $sourceStock = Stock::where('warehouse_id', $this->source_warehouse_id)
                    ->where('product_id', $this->orderMaterial->product_id ?? null)
                    ->first();

                if ($sourceStock) {
                    $removed = $sourceStock->removeStock($totalTransferred);
                    if (!$removed) {
                        Log::error('Failed to remove stock from source warehouse', [
                            'transfer_id' => $this->id,
                            'warehouse_id' => $this->source_warehouse_id,
                            'quantity' => $totalTransferred
                        ]);
                        return false;
                    }

                    // Log stock change
                    $this->logStockChange($sourceStock, -$totalTransferred, 'transfer_out', 'Stock reduced due to grouped transfer completion');
                }
            }

            // Update destination warehouses based on transfer category
            foreach ($groupedTransfers as $transfer) {
                if ($transfer->destination_warehouse_id && $transfer->transfer_category !== 'waste') {
                    $destStock = Stock::where('warehouse_id', $transfer->destination_warehouse_id)
                        ->where('product_id', $transfer->orderMaterial->product_id ?? null)
                        ->first();

                    if (!$destStock) {
                        // Create new stock entry if doesn't exist
                        $destStock = Stock::create([
                            'product_id' => $transfer->orderMaterial->product_id,
                            'warehouse_id' => $transfer->destination_warehouse_id,
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'unit_cost' => 0,
                            'is_active' => true,
                        ]);
                    }

                    $added = $destStock->addStock($transfer->weight_transferred);
                    if (!$added) {
                        Log::error('Failed to add stock to destination warehouse', [
                            'transfer_id' => $transfer->id,
                            'warehouse_id' => $transfer->destination_warehouse_id,
                            'quantity' => $transfer->weight_transferred
                        ]);
                        return false;
                    }

                    // Log stock change
                    $this->logStockChange($destStock, $transfer->weight_transferred, 'transfer_in', 'Stock added due to grouped transfer completion');
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update stock quantities for grouped transfer', [
                'transfer_id' => $this->id,
                'transfer_group_id' => $this->transfer_group_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update stock quantities specifically for cutting transfers
     */
    private function updateCuttingStockQuantities(): bool
    {
        try {
            // Get the cutting result for detailed information
            $cuttingResult = $this->cuttingResult;
            if (!$cuttingResult) {
                Log::error('Cutting result not found for cutting transfer', [
                    'transfer_id' => $this->id,
                    'cutting_result_id' => $this->cutting_result_id
                ]);
                return false;
            }

            // Get all cutting transfers in the same group
            $cuttingTransfers = self::where('transfer_group_id', $this->transfer_group_id)
                ->where('status', 'completed')
                ->whereIn('transfer_category', ['cut_material', 'cutting_waste', 'cutting_remainder'])
                ->get();

            // Calculate totals from cutting result
            $totalInputWeight = $cuttingResult->input_weight; // 1300kg roll
            $totalCutWeight = $cuttingResult->cut_weight; // Plates weight
            $totalWasteWeight = $cuttingResult->waste_weight; // Waste weight
            $totalRemainderWeight = $cuttingResult->remaining_weight; // Remainder weight

            // 1. Remove the full roll from cutting warehouse (source)
            if ($this->source_warehouse_id) {
                $cuttingWarehouseStock = Stock::where('warehouse_id', $this->source_warehouse_id)
                    ->where('product_id', $this->orderMaterial->product_id ?? null)
                    ->first();

                if ($cuttingWarehouseStock) {
                    // Remove the full input weight (1300kg roll)
                    $removed = $cuttingWarehouseStock->removeStock($totalInputWeight);
                    if (!$removed) {
                        Log::error('Failed to remove roll from cutting warehouse', [
                            'transfer_id' => $this->id,
                            'warehouse_id' => $this->source_warehouse_id,
                            'quantity' => $totalInputWeight
                        ]);
                        return false;
                    }

                    // Log stock change for roll removal
                    $this->logStockChange(
                        $cuttingWarehouseStock,
                        -$totalInputWeight,
                        'cutting_roll_consumed',
                        "1300kg roll consumed in cutting operation - Cutting Result ID: {$cuttingResult->id}"
                    );
                } else {
                    Log::error('No stock found in cutting warehouse for roll consumption', [
                        'transfer_id' => $this->id,
                        'warehouse_id' => $this->source_warehouse_id,
                        'product_id' => $this->orderMaterial->product_id ?? null
                    ]);
                    return false;
                }
            }

            // 2. Add cut material (plates) to packaging warehouse
            $cutMaterialTransfer = $cuttingTransfers->where('transfer_category', 'cut_material')->first();
            if ($cutMaterialTransfer && $cutMaterialTransfer->destination_warehouse_id && $totalCutWeight > 0) {
                $packagingStock = Stock::where('warehouse_id', $cutMaterialTransfer->destination_warehouse_id)
                    ->where('product_id', $cutMaterialTransfer->orderMaterial->product_id ?? null)
                    ->first();

                if (!$packagingStock) {
                    // Create new stock entry for packaging warehouse
                    $packagingStock = Stock::create([
                        'product_id' => $cutMaterialTransfer->orderMaterial->product_id,
                        'warehouse_id' => $cutMaterialTransfer->destination_warehouse_id,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'unit_cost' => 0,
                        'is_active' => true,
                    ]);
                }

                $added = $packagingStock->addStock($totalCutWeight);
                if (!$added) {
                    Log::error('Failed to add cut plates to packaging warehouse', [
                        'transfer_id' => $cutMaterialTransfer->id,
                        'warehouse_id' => $cutMaterialTransfer->destination_warehouse_id,
                        'quantity' => $totalCutWeight
                    ]);
                    return false;
                }

                // Log stock change for plates addition
                $this->logStockChange(
                    $packagingStock,
                    $totalCutWeight,
                    'cutting_plates_produced',
                    "Cut plates added to packaging warehouse - Cutting Result ID: {$cuttingResult->id}, Pieces: {$cuttingResult->pieces_cut}"
                );
            }

            // 3. Handle waste tracking (waste doesn't go to regular stock, but is tracked)
            if ($totalWasteWeight > 0) {
                // Log waste generation for audit purposes
                WeightTransferAuditLog::create([
                    'weight_transfer_id' => $this->id,
                    'user_id' => Auth::id(),
                    'stock_change_type' => 'cutting_waste_generated',
                    'stock_quantity_change' => $totalWasteWeight,
                    'warehouse_id' => $this->source_warehouse_id,
                    'product_id' => $this->orderMaterial->product_id ?? null,
                    'notes' => "Waste generated during cutting operation - Cutting Result ID: {$cuttingResult->id}",
                    'metadata' => [
                        'cutting_result_id' => $cuttingResult->id,
                        'waste_weight' => $totalWasteWeight,
                        'waste_percentage' => $cuttingResult->input_weight > 0 ? ($totalWasteWeight / $cuttingResult->input_weight) * 100 : 0,
                        'transfer_group_id' => $this->transfer_group_id,
                    ]
                ]);
            }

            // 4. Handle remainder material (if any goes back to cutting warehouse or another location)
            $remainderTransfer = $cuttingTransfers->where('transfer_category', 'cutting_remainder')->first();
            if ($remainderTransfer && $totalRemainderWeight > 0) {
                if ($remainderTransfer->destination_warehouse_id) {
                    // Remainder goes to another warehouse
                    $remainderStock = Stock::where('warehouse_id', $remainderTransfer->destination_warehouse_id)
                        ->where('product_id', $remainderTransfer->orderMaterial->product_id ?? null)
                        ->first();

                    if (!$remainderStock) {
                        $remainderStock = Stock::create([
                            'product_id' => $remainderTransfer->orderMaterial->product_id,
                            'warehouse_id' => $remainderTransfer->destination_warehouse_id,
                            'quantity' => 0,
                            'reserved_quantity' => 0,
                            'unit_cost' => 0,
                            'is_active' => true,
                        ]);
                    }

                    $added = $remainderStock->addStock($totalRemainderWeight);
                    if (!$added) {
                        Log::error('Failed to add remainder material to warehouse', [
                            'transfer_id' => $remainderTransfer->id,
                            'warehouse_id' => $remainderTransfer->destination_warehouse_id,
                            'quantity' => $totalRemainderWeight
                        ]);
                        return false;
                    }

                    $this->logStockChange(
                        $remainderStock,
                        $totalRemainderWeight,
                        'cutting_remainder_stored',
                        "Remainder material stored after cutting - Cutting Result ID: {$cuttingResult->id}"
                    );
                } else {
                    // Remainder stays in cutting warehouse (add back)
                    $cuttingWarehouseStock = Stock::where('warehouse_id', $this->source_warehouse_id)
                        ->where('product_id', $this->orderMaterial->product_id ?? null)
                        ->first();

                    if ($cuttingWarehouseStock) {
                        $added = $cuttingWarehouseStock->addStock($totalRemainderWeight);
                        if (!$added) {
                            Log::error('Failed to add remainder back to cutting warehouse', [
                                'transfer_id' => $this->id,
                                'warehouse_id' => $this->source_warehouse_id,
                                'quantity' => $totalRemainderWeight
                            ]);
                            return false;
                        }

                        $this->logStockChange(
                            $cuttingWarehouseStock,
                            $totalRemainderWeight,
                            'cutting_remainder_returned',
                            "Remainder material returned to cutting warehouse - Cutting Result ID: {$cuttingResult->id}"
                        );
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update stock quantities for cutting transfer', [
                'transfer_id' => $this->id,
                'transfer_group_id' => $this->transfer_group_id,
                'cutting_result_id' => $this->cutting_result_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Log critical error for monitoring
            WeightTransferAuditLog::create([
                'weight_transfer_id' => $this->id,
                'user_id' => Auth::id(),
                'stock_change_type' => 'cutting_stock_update_failed',
                'stock_quantity_change' => 0,
                'warehouse_id' => $this->source_warehouse_id,
                'product_id' => $this->orderMaterial->product_id ?? null,
                'notes' => 'Critical error during cutting stock update: ' . $e->getMessage(),
                'metadata' => [
                    'error_type' => 'cutting_stock_update_exception',
                    'transfer_group_id' => $this->transfer_group_id,
                    'cutting_result_id' => $this->cutting_result_id,
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                ]
            ]);

            return false;
        }
    }

    /**
     * Log stock changes for audit trail
     */
    private function logStockChange(Stock $stock, float $quantity, string $changeType, string $notes): void
    {
        // Create a detailed audit log entry
        WeightTransferAuditLog::logStockChange(
            $this->id,
            Auth::id(),
            $stock,
            $quantity,
            $changeType,
            $notes
        );

        // Also log to general application log
        Log::info('Stock quantity changed via weight transfer', [
            'stock_id' => $stock->id,
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'quantity_change' => $quantity,
            'change_type' => $changeType,
            'new_quantity' => $stock->quantity,
            'transfer_id' => $this->id,
            'transfer_group_id' => $this->transfer_group_id,
            'notes' => $notes
        ]);
    }

    /**
     * Record approval history for audit trail
     */
    private function recordApprovalHistory(string $action, ?int $userId, string $notes = null): void
    {
        WeightTransferAuditLog::log(
            $this->id,
            $action,
            $userId,
            $notes
        );
    }

    /**
     * Check approval status with validation
     */
    public function getApprovalStatus(): string
    {
        // Validate approval state consistency
        if ($this->requires_sequential_approval) {
            $pendingCount = $this->approvals()->where('approval_status', 'pending')->count();
            $approvedCount = $this->approvals()->where('approval_status', 'approved')->count();
            $rejectedCount = $this->approvals()->where('approval_status', 'rejected')->count();
            $totalApprovals = $this->approvals()->count();

            // If any approval is rejected, the whole transfer is rejected
            if ($rejectedCount > 0) {
                return 'rejected';
            }

            // If all required approvals are approved
            if ($approvedCount === $totalApprovals && $totalApprovals > 0) {
                return 'approved';
            }

            // If there are pending approvals
            if ($pendingCount > 0) {
                return 'pending_approval';
            }

            // Edge case: no approvals exist but sequential approval is required
            if ($totalApprovals === 0) {
                return 'approval_setup_error';
            }
        }

        // Legacy logic for non-sequential transfers
        if ($this->hasPendingApprovals()) {
            return 'pending_approval';
        }

        if ($this->isFullyApproved()) {
            return 'approved';
        }

        return 'rejected';
    }

    /**
     * Validate transfer data integrity
     */
    public function validateTransferData(): array
    {
        $errors = [];

        // Validate weight
        if ($this->weight_transferred <= 0) {
            $errors[] = 'Transfer weight must be greater than zero';
        }

        // Validate warehouses for grouped transfers
        if ($this->transfer_group_id) {
            if (!$this->source_warehouse_id) {
                $errors[] = 'Source warehouse is required for grouped transfers';
            }
            if (!$this->destination_warehouse_id && !in_array($this->transfer_category, ['waste', 'cutting_waste'])) {
                $errors[] = 'Destination warehouse is required for non-waste transfers';
            }

            // Validate stock availability for source warehouse
            if ($this->source_warehouse_id && $this->orderMaterial) {
                $sourceStock = Stock::where('warehouse_id', $this->source_warehouse_id)
                    ->where('product_id', $this->orderMaterial->product_id)
                    ->first();

                if (!$sourceStock || $sourceStock->available_quantity < $this->weight_transferred) {
                    $errors[] = 'Insufficient stock in source warehouse for transfer';
                }
            }
        }

        // Validate material specifications
        if (!$this->roll_number && !in_array($this->transfer_category, ['waste', 'cutting_waste'])) {
            $errors[] = 'Roll number is required for material transfers';
        }

        // Validate cutting-specific fields
        if ($this->isCuttingTransfer()) {
            if (!$this->cutting_result_id) {
                $errors[] = 'Cutting result ID is required for cutting transfers';
            }

            if ($this->pieces_transferred < 0) {
                $errors[] = 'Pieces transferred cannot be negative';
            }

            // Validate cutting result exists and is approved
            if ($this->cutting_result_id) {
                $cuttingResult = $this->cuttingResult;
                if (!$cuttingResult) {
                    $errors[] = 'Associated cutting result not found';
                } elseif (!$cuttingResult->isApproved()) {
                    $errors[] = 'Cutting result must be approved before transfer';
                } else {
                    // Additional validation for cutting stock availability
                    $validation = $this->validateCuttingStockAvailability($cuttingResult);
                    $errors = array_merge($errors, $validation['errors']);
                }
            }
        }

        // Validate order relationship
        if (!$this->order) {
            $errors[] = 'Transfer must be associated with a valid order';
        }

        // Validate order material relationship
        if (!$this->orderMaterial) {
            $errors[] = 'Transfer must be associated with a valid order material';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate stock availability for cutting transfers
     */
    private function validateCuttingStockAvailability(CuttingResult $cuttingResult): array
    {
        $errors = [];

        // Check if the 1300kg roll is available in the cutting warehouse
        if ($this->source_warehouse_id && $this->orderMaterial) {
            $cuttingWarehouseStock = Stock::where('warehouse_id', $this->source_warehouse_id)
                ->where('product_id', $this->orderMaterial->product_id)
                ->first();

            if (!$cuttingWarehouseStock) {
                $errors[] = 'No stock found in cutting warehouse for the specified roll';
            } elseif ($cuttingWarehouseStock->available_quantity < $cuttingResult->input_weight) {
                $errors[] = sprintf(
                    'Insufficient roll stock in cutting warehouse. Available: %s kg, Required: %s kg',
                    $cuttingWarehouseStock->available_quantity,
                    $cuttingResult->input_weight
                );
            }
        }

        // Validate that destination warehouses have capacity for the cut material
        $cutMaterialTransfer = self::where('transfer_group_id', $this->transfer_group_id)
            ->where('transfer_category', 'cut_material')
            ->first();

        if ($cutMaterialTransfer && $cutMaterialTransfer->destination_warehouse_id && $cuttingResult->cut_weight > 0) {
            // Check if packaging warehouse exists and is operational
            $packagingWarehouse = Warehouse::find($cutMaterialTransfer->destination_warehouse_id);
            if (!$packagingWarehouse) {
                $errors[] = 'Packaging warehouse not found';
            } elseif (!$packagingWarehouse->is_active) {
                $errors[] = 'Packaging warehouse is not active';
            }
        }

        // Validate weight balance from cutting result
        $balance = $cuttingResult->getWeightBalance();
        if (!$balance['is_balanced']) {
            $errors[] = 'Cutting result weight balance is invalid - input vs output mismatch';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
