<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Traits\Auditable;

class OrderProcessing extends Model
{
    use Auditable;

    protected $fillable = [
        'order_id',
        'work_stage_id',
        'status',
        'started_at',
        'completed_at',
        'notes',
        'assigned_to',
        'priority',
        'stage_color',
        'can_skip',
        'skip_reason',
        'skipped_at',
        'skipped_by',
        'visual_priority',
        'estimated_duration',
        'actual_duration',
        'stage_metadata',
        'weight_received',
        'weight_transferred',
        'weight_balance',
        'transfer_destination',
        'transfer_approved',
        'transfer_approved_by',
        'transfer_approved_at',
        'transfer_notes',
        'handover_status',
        'handover_from',
        'handover_to',
        'handover_requested_at',
        'handover_completed_at',
        'handover_notes',
        'mandatory_handover',
        // Sorting fields
        'sorting_approved',
        'sorting_approved_by',
        'sorting_approved_at',
        'sorting_notes',
        'roll1_width',
        'roll1_weight',
        'roll1_location',
        'roll2_width',
        'roll2_weight',
        'roll2_location',
        'sorting_waste_weight',
        'post_sorting_destination',
        'destination_warehouse_id',
        'transfer_completed',
        'transfer_completed_at',
        // Weight approval fields
        'weight_received_approved',
        'weight_received_approved_by',
        'weight_received_approved_at',
        'weight_received_notes',
        // Roll conversion fields
        'roll_conversions',
        'conversion_completed_at',
        // Cutting fields
        'cutting_approved',
        'cutting_approved_by',
        'cutting_approved_at',
        'cutting_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'priority' => 'integer',
        'can_skip' => 'boolean',
        'skipped_at' => 'datetime',
        'visual_priority' => 'integer',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
        'stage_metadata' => 'array',
        'weight_received' => 'decimal:2',
        'weight_transferred' => 'decimal:2',
        'weight_balance' => 'decimal:2',
        'transfer_approved' => 'boolean',
        'transfer_approved_at' => 'datetime',
        'handover_requested_at' => 'datetime',
        'handover_completed_at' => 'datetime',
        'mandatory_handover' => 'boolean',
        // Sorting casts
        'sorting_approved' => 'boolean',
        'sorting_approved_at' => 'datetime',
        'roll1_width' => 'decimal:2',
        'roll1_weight' => 'decimal:2',
        'roll2_width' => 'decimal:2',
        'roll2_weight' => 'decimal:2',
        'sorting_waste_weight' => 'decimal:2',
        'post_sorting_destination' => 'string',
        'transfer_completed' => 'boolean',
        'transfer_completed_at' => 'datetime',
        // Weight approval casts
        'weight_received_approved' => 'boolean',
        'weight_received_approved_at' => 'datetime',
        // Roll conversion casts
        'roll_conversions' => 'array',
        'conversion_completed_at' => 'datetime',
        // Cutting fields
        'cutting_approved' => 'boolean',
        'cutting_approved_at' => 'datetime',
    ];

    /**
     * Get the order that owns the processing.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the work stage for the processing.
     */
    public function workStage(): BelongsTo
    {
        return $this->belongsTo(WorkStage::class);
    }

    /**
     * Get the cutting results for this processing.
     */
    public function cuttingResults()
    {
        return $this->hasMany(CuttingResult::class);
    }

    /**
     * Get the user assigned to the processing.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who skipped this stage.
     */
    public function skippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'skipped_by');
    }

    /**
     * Check if stage can be skipped
     */
    public function canBeSkipped(): bool
    {
        return $this->can_skip && in_array($this->status, ['pending', 'in_progress']);
    }

    /**
     * Skip the stage
     */
    public function skip(User $user, string $reason = null): bool
    {
        if (!$this->canBeSkipped()) {
            return false;
        }

        $this->update([
            'status' => 'skipped',
            'skip_reason' => $reason,
            'skipped_at' => now(),
            'skipped_by' => $user->id,
        ]);

        $this->recordHistory('skip', $user->id, $reason);

        return true;
    }

    /**
     * Get duration in minutes
     */
    public function getDurationAttribute(): int
    {
        if ($this->completed_at && $this->started_at) {
            return $this->started_at->diffInMinutes($this->completed_at);
        }
        return 0;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        return match($this->status) {
            'pending' => 0,
            'in_progress' => 50,
            'completed' => 100,
            'skipped' => 100,
            'cancelled' => 0,
            default => 0
        };
    }

    /**
     * Record history for stage actions
     */
    private function recordHistory(string $action, int $userId, string $notes = null): void
    {
        OrderStageHistory::create([
            'order_id' => $this->order_id,
            'work_stage_id' => $this->work_stage_id,
            'previous_stage' => $this->order->current_stage ?? null,
            'new_stage' => $this->workStage->name_en,
            'action' => $action,
            'action_by' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Scope for skippable stages
     */
    public function scopeSkippable($query)
    {
        return $query->where('can_skip', true);
    }

    /**
     * Scope for skipped stages
     */
     public function scopeSkipped($query)
     {
         return $query->where('status', 'skipped');
     }

    /**
     * Get the user who approved the transfer.
     */
    public function transferApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transfer_approved_by');
    }

    /**
     * Get the handover from user.
     */
    public function handoverFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_from');
    }

    /**
     * Get the handover to user.
     */
    public function handoverTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_to');
    }

    /**
     * Get the handover from user.
     */
    public function handoverFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_from');
    }

    /**
     * Get the handover to user.
     */
    public function handoverToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_to');
    }

    /**
     * Get handover audit logs.
     */
    public function handoverAuditLogs()
    {
        return $this->hasMany(HandoverAuditLog::class);
    }

    /**
     * Check if transfer is approved
     */
    public function isTransferApproved(): bool
    {
        return $this->transfer_approved;
    }

    /**
     * Approve weight transfer for this stage
     */
    public function approveTransfer(int $userId, string $notes = null): bool
    {
        if ($this->transfer_approved) {
            return false;
        }

        $this->transfer_approved = true;
        $this->transfer_approved_by = $userId;
        $this->transfer_approved_at = now();
        $this->transfer_notes = $notes;

        return $this->save();
    }

    /**
     * Calculate weight balance
     */
    public function calculateWeightBalance(): void
    {
        $this->weight_balance = $this->weight_received - $this->weight_transferred;
        $this->save();
    }

    /**
     * Record weight received
     */
    public function recordWeightReceived(float $weight): bool
    {
        $this->weight_received = $weight;
        $this->calculateWeightBalance();
        return true;
    }

    /**
     * Record weight transferred
     */
    public function recordWeightTransferred(float $weight, ?string $destination = null): bool
    {
        $this->weight_transferred = $weight;
        if ($destination) {
            $this->transfer_destination = $destination;
        }
        $this->calculateWeightBalance();
        return true;
    }

    /**
     * Check if this is a warehouse stage (Material Reservation)
     */
    public function isWarehouseStage(): bool
    {
        return $this->workStage?->name_en === 'Material Reservation';
    }

    /**
     * Validate warehouse operations
     */
    public function validateWarehouseOperations(): array
    {
        $errors = [];

        if (!$this->isWarehouseStage()) {
            return $errors;
        }

        if ($this->weight_received <= 0) {
            $errors[] = 'Weight received must be greater than 0 for warehouse operations';
        }

        if ($this->weight_transferred > $this->weight_received) {
            $errors[] = 'Weight transferred cannot exceed weight received';
        }

        if ($this->weight_transferred > 0 && empty($this->transfer_destination)) {
            $errors[] = 'Transfer destination must be specified when weight is transferred';
        }

        return $errors;
    }

    /**
     * Execute warehouse transfer
     */
    public function executeWarehouseTransfer(User $user): bool
    {
        if (!$this->isWarehouseStage()) {
            return false;
        }

        $validationErrors = $this->validateWarehouseOperations();
        if (!empty($validationErrors)) {
            return false;
        }

        // Record the transfer in audit log
        $this->recordWeightTransferAudit($user);

        // Update status to completed if transfer is executed
        if ($this->weight_transferred > 0) {
            $this->status = 'completed';
            $this->completed_at = now();
        }

        return $this->save();
    }

    /**
     * Record weight transfer audit
     */
    private function recordWeightTransferAudit(User $user): void
    {
        WeightTransferAuditLog::create([
            'order_processing_id' => $this->id,
            'user_id' => $user->id,
            'weight_received' => $this->weight_received,
            'weight_transferred' => $this->weight_transferred,
            'transfer_destination' => $this->transfer_destination,
            'notes' => $this->transfer_notes,
            'metadata' => [
                'stage_name' => $this->workStage->name_en ?? null,
                'order_number' => $this->order->order_number ?? null,
            ],
        ]);
    }

    /**
     * Check if handover is required
     */
    public function requiresHandover(): bool
    {
        return $this->mandatory_handover || $this->handover_status !== 'not_required';
    }

    /**
     * Request handover to another user
     */
    public function requestHandover(int $fromUserId, int $toUserId, string $notes = null): bool
    {
        if ($this->handover_status !== 'not_required') {
            return false;
        }

        $this->update([
            'handover_status' => 'pending',
            'handover_from' => $fromUserId,
            'handover_to' => $toUserId,
            'handover_requested_at' => now(),
            'handover_notes' => $notes,
        ]);

        $this->logHandoverAction('requested', $fromUserId, $notes);

        return true;
    }

    /**
     * Accept handover request
     */
    public function acceptHandover(int $userId, string $notes = null): bool
    {
        if ($this->handover_status !== 'pending' || $this->handover_to !== $userId) {
            return false;
        }

        $this->update([
            'handover_status' => 'in_progress',
            'assigned_to' => $userId,
        ]);

        $this->logHandoverAction('accepted', $userId, $notes);

        return true;
    }

    /**
     * Complete handover
     */
    public function completeHandover(int $userId, string $notes = null): bool
    {
        if ($this->handover_status !== 'in_progress' || $this->assigned_to !== $userId) {
            return false;
        }

        $this->update([
            'handover_status' => 'completed',
            'handover_completed_at' => now(),
        ]);

        $this->logHandoverAction('completed', $userId, $notes);

        return true;
    }

    /**
     * Reject handover request
     */
    public function rejectHandover(int $userId, string $notes = null): bool
    {
        if ($this->handover_status !== 'pending' || $this->handover_to !== $userId) {
            return false;
        }

        $this->update([
            'handover_status' => 'not_required',
            'handover_from' => null,
            'handover_to' => null,
            'handover_requested_at' => null,
            'handover_notes' => null,
        ]);

        $this->logHandoverAction('rejected', $userId, $notes);

        return true;
    }

    /**
     * Cancel handover
     */
    public function cancelHandover(int $userId, string $notes = null): bool
    {
        if (!in_array($this->handover_status, ['pending', 'in_progress'])) {
            return false;
        }

        $this->update([
            'handover_status' => 'not_required',
            'handover_from' => null,
            'handover_to' => null,
            'handover_requested_at' => null,
            'handover_completed_at' => null,
            'handover_notes' => null,
        ]);

        $this->logHandoverAction('cancelled', $userId, $notes);

        return true;
    }

    /**
     * Log handover action
     */
    private function logHandoverAction(string $action, int $userId, string $notes = null): void
    {
        HandoverAuditLog::create([
            'order_processing_id' => $this->id,
            'user_id' => $userId,
            'action' => $action,
            'notes' => $notes,
            'metadata' => [
                'handover_from' => $this->handover_from,
                'handover_to' => $this->handover_to,
                'stage_name' => $this->workStage->name_en ?? null,
            ],
        ]);
    }

    /**
     * Get handover status badge
     */
    public function getHandoverStatusBadgeAttribute(): string
    {
        return match($this->handover_status) {
            'not_required' => 'secondary',
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            default => 'secondary'
        };
    }

    /**
     * Check if handover can be requested
     */
    public function canRequestHandover(int $userId): bool
    {
        return $this->assigned_to === $userId &&
               in_array($this->status, ['pending', 'in_progress']) &&
               $this->handover_status === 'not_required';
    }

    /**
     * Check if handover can be accepted
     */
    public function canAcceptHandover(int $userId): bool
    {
        return $this->handover_to === $userId &&
               $this->handover_status === 'pending';
    }

    /**
     * Check if handover can be completed
     */
    public function canCompleteHandover(int $userId): bool
    {
        return $this->assigned_to === $userId &&
                $this->handover_status === 'in_progress';
    }

    /**
     * Get sorting results for this processing.
     */
    public function sortingResults()
    {
        return $this->hasMany(SortingResult::class);
    }

    /**
     * Get the user who approved the sorting.
     */
    public function sortingApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorting_approved_by');
    }

    /**
     * Get the destination warehouse for post-sorting transfer.
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    /**
     * Get the user who approved the received weight.
     */
    public function weightReceivedApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'weight_received_approved_by');
    }

    /**
     * Get the user who approved the cutting results.
     */
    public function cuttingApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cutting_approved_by');
    }

    /**
     * Record roll conversion results
     */
    public function recordRollConversion(array $conversionData, User $user): bool
    {
        if (!$this->isSortingStage()) {
            return false;
        }

        $this->roll_conversions = $conversionData;
        $this->conversion_completed_at = now();

        return $this->save();
    }

    /**
     * Check if this is a sorting stage
     */
    public function isSortingStage(): bool
    {
        return $this->workStage?->name_en === 'Sorting';
    }

    /**
     * Check if this is a cutting stage
     */
    public function isCuttingStage(): bool
    {
        return $this->workStage?->name_en === 'Cutting';
    }

    /**
     * Get delivery specifications from the order
     */
    public function getDeliverySpecifications(): array
    {
        return $this->order->getDeliverySpecificationsAttribute();
    }

    /**
     * Validate delivery specifications for this processing stage
     */
    public function validateDeliverySpecificationsForStage(): array
    {
        $errors = [];

        if (!$this->isCuttingStage() && !$this->isSortingStage()) {
            return $errors; // Only validate for cutting and sorting stages
        }

        $specs = $this->getDeliverySpecifications();

        if (empty(array_filter($specs))) {
            $errors[] = 'Delivery specifications are required for ' . ($this->workStage?->name_en ?? 'this') . ' stage';
        }

        // Validate cutting-specific requirements
        if ($this->isCuttingStage()) {
            if (!$specs['width']) {
                $errors[] = 'Delivery width is required for cutting operations';
            }
            if (!$specs['length']) {
                $errors[] = 'Delivery length is required for cutting operations';
            }
        }

        // Validate sorting-specific requirements
        if ($this->isSortingStage()) {
            if (!$specs['weight'] && !$specs['quantity']) {
                $errors[] = 'Either delivery weight or quantity is required for sorting operations';
            }
        }

        return $errors;
    }

    /**
     * Check if cutting is approved
     */
    public function isCuttingApproved(): bool
    {
        return $this->cutting_approved;
    }

    /**
     * Check if cutting can be approved
     */
    public function canApproveCutting(): bool
    {
        return $this->isCuttingStage() &&
                !$this->cutting_approved &&
                $this->cuttingResults()->exists() &&
                empty($this->validateDeliverySpecificationsForStage());
    }

    /**
     * Check if sorting is approved
     */
    public function isSortingApproved(): bool
    {
        return $this->sorting_approved;
    }

    /**
     * Approve sorting results
     */
    public function approveSorting(int $userId, string $notes = null): bool
    {
        if ($this->sorting_approved) {
            return false;
        }

        $this->sorting_approved = true;
        $this->sorting_approved_by = $userId;
        $this->sorting_approved_at = now();
        $this->sorting_notes = $notes;

        return $this->save();
    }

    /**
     * Calculate total sorted weight (roll1 + roll2 + waste)
     */
    public function getTotalSortedWeightAttribute(): float
    {
        return ($this->roll1_weight ?? 0) + ($this->roll2_weight ?? 0) + ($this->sorting_waste_weight ?? 0);
    }

    /**
     * Check if sorting weight balance is correct
     */
    public function isSortingWeightBalanced(): bool
    {
        if (!$this->isSortingStage()) {
            return true;
        }

        return abs($this->weight_received - $this->total_sorted_weight) < 0.01;
    }

    /**
     * Record sorting results
     */
    public function recordSortingResults(array $results, User $user): bool
    {
        if (!$this->isSortingStage()) {
            return false;
        }

        DB::beginTransaction();
        try {
            foreach ($results as $result) {
                SortingResult::create([
                    'order_processing_id' => $this->id,
                    'order_material_id' => $result['order_material_id'],
                    'original_weight' => $result['original_weight'],
                    'original_width' => $result['original_width'],
                    'roll1_weight' => $result['roll1_weight'],
                    'roll1_width' => $result['roll1_width'],
                    'roll1_location' => $result['roll1_location'] ?? null,
                    'roll2_weight' => $result['roll2_weight'],
                    'roll2_width' => $result['roll2_width'],
                    'roll2_location' => $result['roll2_location'] ?? null,
                    'waste_weight' => $result['waste_weight'],
                    'waste_reason' => $result['waste_reason'] ?? null,
                    'sorted_by' => $user->id,
                    'sorted_at' => now(),
                    'sorting_notes' => $result['notes'] ?? null,
                ]);
            }

            // Update processing record with summary
            $this->roll1_weight = collect($results)->sum('roll1_weight');
            $this->roll1_width = collect($results)->avg('roll1_width');
            $this->roll2_weight = collect($results)->sum('roll2_weight');
            $this->roll2_width = collect($results)->avg('roll2_width');
            $this->sorting_waste_weight = collect($results)->sum('waste_weight');

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    /**
     * Complete post-sorting transfer
     */
    public function completePostSortingTransfer(int $warehouseId, User $user): bool
    {
        if (!$this->isSortingApproved()) {
            return false;
        }

        $this->post_sorting_destination = 'cutting_warehouse'; // Default to cutting
        $this->destination_warehouse_id = $warehouseId;
        $this->transfer_completed = true;
        $this->transfer_completed_at = now();

        // Update status to completed
        $this->status = 'completed';
        $this->completed_at = now();

        return $this->save();
    }

    /**
     * Check if user can approve sorting
     */
    public function canUserApproveSorting(User $user): bool
    {
        return $user->hasRole('مسؤول_مستودع') && $this->isSortingStage();
    }

    /**
     * Check if user can perform sorting
     */
    public function canUserPerformSorting(User $user): bool
    {
        return $user->hasRole('مسؤول_فرازة') && $this->isSortingStage() && $this->assigned_to === $user->id;
    }

    /**
     * Get sorting status badge
     */
    public function getSortingStatusBadgeAttribute(): string
    {
        if (!$this->isSortingStage()) {
            return 'secondary';
        }

        if ($this->sorting_approved) {
            return 'success';
        }

        if ($this->roll1_weight > 0 || $this->roll2_weight > 0) {
            return 'warning';
        }

        return 'secondary';
    }

    /**
     * Check if sorting can be started
     */
    public function canStartSorting(): bool
    {
        return $this->isSortingStage() &&
               in_array($this->status, ['pending', 'in_progress']) &&
               !$this->sorting_approved;
    }

    /**
     * Check if sorting can be approved
     */
    public function canApproveSorting(): bool
    {
        return $this->isSortingStage() &&
                !$this->sorting_approved &&
                ($this->roll1_weight > 0 || $this->roll2_weight > 0) &&
                empty($this->validateDeliverySpecificationsForStage());
    }

    /**
     * Validate sorting data before approval
     */
    public function validateSortingData(): array
    {
        $errors = [];

        if (!$this->isSortingStage()) {
            return $errors;
        }

        if ($this->roll1_weight <= 0 && $this->roll2_weight <= 0) {
            $errors[] = 'At least one roll must have weight greater than 0';
        }

        if (!$this->isSortingWeightBalanced()) {
            $errors[] = 'Total sorted weight does not match received weight';
        }

        if (empty($this->roll1_location) && $this->roll1_weight > 0) {
            $errors[] = 'Roll 1 location must be specified';
        }

        if (empty($this->roll2_location) && $this->roll2_weight > 0) {
            $errors[] = 'Roll 2 location must be specified';
        }

        return $errors;
    }

    /**
     * Get sorting progress percentage
     */
    public function getSortingProgressAttribute(): float
    {
        if (!$this->isSortingStage()) {
            return 0;
        }

        if ($this->sorting_approved) {
            return 100;
        }

        if ($this->roll1_weight > 0 || $this->roll2_weight > 0) {
            return 75;
        }

        if ($this->weight_received > 0) {
            return 25;
        }

        return 0;
    }

    /**
     * Check if post-sorting transfer can be initiated
     */
    public function canInitiatePostSortingTransfer(): bool
    {
        return $this->isSortingApproved() &&
               !$this->transfer_completed &&
               !empty($this->post_sorting_destination);
    }

    /**
     * Get available post-sorting destinations
     */
    public static function getPostSortingDestinations(): array
    {
        return [
            'cutting_warehouse' => 'Cutting Warehouse',
            'direct_delivery' => 'Direct Delivery',
            'other_warehouse' => 'Other Warehouse',
        ];
    }

    /**
     * Scope for sorting stages
     */
    public function scopeSortingStages($query)
    {
        return $query->whereHas('workStage', function ($q) {
            $q->where('name_en', 'Sorting');
        });
    }

    /**
     * Scope for approved sorting
     */
    public function scopeSortingApproved($query)
    {
        return $query->where('sorting_approved', true);
    }

    /**
     * Scope for pending sorting approval
     */
    public function scopePendingSortingApproval($query)
    {
        return $query->sortingStages()->where('sorting_approved', false)->where(function ($q) {
            $q->where('roll1_weight', '>', 0)->orWhere('roll2_weight', '>', 0);
        });
    }
}
