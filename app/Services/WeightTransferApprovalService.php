<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeightTransfer;
use App\Models\WeightTransferApproval;
use App\Models\OrderProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeightTransferApprovalService
{
    /**
     * Validate if a user can approve a weight transfer
     */
    public function canUserApproveTransfer(User $user, WeightTransfer $transfer): bool
    {
        // For sequential multi-warehouse approvals
        if ($transfer->requires_sequential_approval) {
            return $this->canUserApproveSequentialTransfer($user, $transfer);
        }

        // Legacy logic for single approvals
        $nextStageProcessing = OrderProcessing::where('order_id', $transfer->order_id)
            ->where('work_stage_id', '>', $transfer->from_stage)
            ->orderBy('work_stage_id')
            ->first();

        if (!$nextStageProcessing) {
            return false;
        }

        return $nextStageProcessing->assigned_to === $user->id;
    }

    /**
     * Check if user can approve sequential multi-warehouse transfer
     */
    private function canUserApproveSequentialTransfer(User $user, WeightTransfer $transfer): bool
    {
        // Find the next pending approval for this transfer
        $nextApproval = $transfer->approvals()
            ->where('approval_status', 'pending')
            ->orderBy('approval_sequence')
            ->first();

        if (!$nextApproval) {
            return false;
        }

        // Check if this approval is the next in sequence
        if (!$nextApproval->isNextInSequence()) {
            return false;
        }

        // Check if user is the assigned approver
        if ($nextApproval->approver_id !== $user->id) {
            return false;
        }

        // Check if user has the required role for the approval level
        $requiredRole = match($nextApproval->approval_level) {
            'cutting_warehouse_manager' => 'cutting_warehouse_manager',
            'delivery_manager' => 'delivery_manager',
            'packaging_warehouse_manager' => 'packaging_warehouse_manager',
            'auto_approved' => null,
            default => null,
        };

        if (!$requiredRole) {
            return false;
        }

        return $user->hasRole($requiredRole) &&
               $user->warehouseAssignments()->where('warehouse_id', $nextApproval->warehouse_id)->exists();
    }

    /**
     * Validate if a user can reject a weight transfer
     */
    public function canUserRejectTransfer(User $user, WeightTransfer $transfer): bool
    {
        return $this->canUserApproveTransfer($user, $transfer);
    }

    /**
     * Process approval request for a weight transfer
     */
    public function requestApproval(WeightTransfer $transfer, string $notes = null): bool
    {
        try {
            DB::beginTransaction();

            if ($transfer->requires_sequential_approval) {
                // Create sequential approvals for multi-warehouse workflow
                $transfer->createSequentialApprovals();
            } else {
                // Legacy single approval logic
                $approver = $transfer->getNextStageApprover();

                if (!$approver) {
                    Log::warning('No approver found for weight transfer', [
                        'transfer_id' => $transfer->id,
                        'order_id' => $transfer->order_id
                    ]);
                    return false;
                }

                $transfer->requestApproval($approver->id, $notes);
            }

            // Create inventory requests for warehouse checks
            $inventoryService = app(\App\Services\InventoryRequestService::class);
            $inventoryResult = $inventoryService->createInventoryRequestsForTransfer($transfer);

            if (!$inventoryResult['success']) {
                Log::warning('Failed to create inventory requests for transfer', [
                    'transfer_id' => $transfer->id,
                    'error' => $inventoryResult['message']
                ]);
                // Don't fail the entire approval request if inventory requests fail
            }

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to request approval for weight transfer', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process approval of a weight transfer with enhanced validation
     */
    public function approveTransfer(User $user, WeightTransfer $transfer, string $notes = null): array
    {
        // Validate user authorization
        if (!$this->canUserApproveTransfer($user, $transfer)) {
            return [
                'success' => false,
                'message' => 'You are not authorized to approve this transfer.',
                'error_code' => 'UNAUTHORIZED'
            ];
        }

        // Validate transfer state
        if ($transfer->status === 'approved') {
            return [
                'success' => false,
                'message' => 'Transfer is already approved.',
                'error_code' => 'ALREADY_APPROVED'
            ];
        }

        if ($transfer->status === 'rejected') {
            return [
                'success' => false,
                'message' => 'Transfer has been rejected and cannot be approved.',
                'error_code' => 'ALREADY_REJECTED'
            ];
        }

        if ($transfer->status === 'completed') {
            return [
                'success' => false,
                'message' => 'Transfer has already been completed.',
                'error_code' => 'ALREADY_COMPLETED'
            ];
        }

        // Validate warehouse assignment for sequential approvals
        if ($transfer->requires_sequential_approval) {
            $approval = $transfer->approvals()
                ->where('approver_id', $user->id)
                ->where('approval_status', 'pending')
                ->first();

            if (!$approval) {
                return [
                    'success' => false,
                    'message' => 'No pending approval found for your account.',
                    'error_code' => 'NO_PENDING_APPROVAL'
                ];
            }

            if (!$approval->isNextInSequence()) {
                return [
                    'success' => false,
                    'message' => 'Previous approvals must be completed first.',
                    'error_code' => 'SEQUENCE_VIOLATION'
                ];
            }
        }

        // Check if inventory requests are completed before allowing approval
        $inventoryService = app(\App\Services\InventoryRequestService::class);
        if (!$inventoryService->areAllRequestsCompletedForTransfer($transfer->id)) {
            return [
                'success' => false,
                'message' => 'All inventory requests must be completed before approving this transfer.',
                'error_code' => 'INVENTORY_REQUESTS_PENDING'
            ];
        }

        try {
            DB::beginTransaction();

            $approved = $transfer->approveBy($user->id, $notes);

            if (!$approved) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to approve transfer.',
                    'error_code' => 'APPROVAL_FAILED'
                ];
            }

            // If fully approved, complete the transfer
            if ($transfer->isFullyApproved()) {
                // Validate stock availability before completing grouped transfers
                if ($transfer->transfer_group_id) {
                    $validation = $this->validateGroupedTransferStock($transfer);
                    if (!$validation['valid']) {
                        DB::rollBack();
                        return [
                            'success' => false,
                            'message' => 'Stock validation failed: ' . implode(', ', $validation['errors']),
                            'error_code' => 'STOCK_VALIDATION_FAILED'
                        ];
                    }

                    // For cutting transfers, perform additional cutting-specific validations
                    if ($transfer->isCuttingTransfer()) {
                        $cuttingValidation = $this->validateCuttingTransferCompletion($transfer);
                        if (!$cuttingValidation['valid']) {
                            DB::rollBack();
                            return [
                                'success' => false,
                                'message' => 'Cutting transfer validation failed: ' . implode(', ', $cuttingValidation['errors']),
                                'error_code' => 'CUTTING_VALIDATION_FAILED'
                            ];
                        }
                    }
                }

                $completed = $transfer->completeTransfer();
                if (!$completed) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Transfer approved but completion failed.',
                        'error_code' => 'COMPLETION_FAILED'
                    ];
                }
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Transfer approved successfully.',
                'transfer_status' => $transfer->status,
                'is_fully_approved' => $transfer->isFullyApproved()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve weight transfer', [
                'transfer_id' => $transfer->id,
                'user_id' => $user->id,
                'transfer_category' => $transfer->transfer_category,
                'is_cutting_transfer' => $transfer->isCuttingTransfer(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Create audit log for approval failure
            \App\Models\WeightTransferAuditLog::create([
                'weight_transfer_id' => $transfer->id,
                'user_id' => $user->id,
                'stock_change_type' => 'transfer_approval_failed',
                'stock_quantity_change' => 0,
                'warehouse_id' => $transfer->source_warehouse_id,
                'product_id' => $transfer->orderMaterial->product_id ?? null,
                'notes' => 'Transfer approval failed: ' . $e->getMessage(),
                'metadata' => [
                    'error_type' => 'approval_exception',
                    'transfer_category' => $transfer->transfer_category,
                    'is_cutting_transfer' => $transfer->isCuttingTransfer(),
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                ]
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while approving the transfer.',
                'error_code' => 'EXCEPTION_OCCURRED',
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * Process rejection of a weight transfer with enhanced validation
     */
    public function rejectTransfer(User $user, WeightTransfer $transfer, string $reason): array
    {
        // Validate rejection reason
        if (empty(trim($reason))) {
            return [
                'success' => false,
                'message' => 'Rejection reason is required.',
                'error_code' => 'MISSING_REASON'
            ];
        }

        if (strlen($reason) < 10) {
            return [
                'success' => false,
                'message' => 'Rejection reason must be at least 10 characters long.',
                'error_code' => 'REASON_TOO_SHORT'
            ];
        }

        // Validate user authorization
        if (!$this->canUserRejectTransfer($user, $transfer)) {
            return [
                'success' => false,
                'message' => 'You are not authorized to reject this transfer.',
                'error_code' => 'UNAUTHORIZED'
            ];
        }

        // Validate transfer state
        if ($transfer->status === 'approved') {
            return [
                'success' => false,
                'message' => 'Approved transfers cannot be rejected.',
                'error_code' => 'ALREADY_APPROVED'
            ];
        }

        if ($transfer->status === 'rejected') {
            return [
                'success' => false,
                'message' => 'Transfer is already rejected.',
                'error_code' => 'ALREADY_REJECTED'
            ];
        }

        if ($transfer->status === 'completed') {
            return [
                'success' => false,
                'message' => 'Completed transfers cannot be rejected.',
                'error_code' => 'ALREADY_COMPLETED'
            ];
        }

        // For sequential approvals, validate that user can reject at current level
        if ($transfer->requires_sequential_approval) {
            $approval = $transfer->approvals()
                ->where('approver_id', $user->id)
                ->where('approval_status', 'pending')
                ->first();

            if (!$approval) {
                return [
                    'success' => false,
                    'message' => 'No pending approval found for your account.',
                    'error_code' => 'NO_PENDING_APPROVAL'
                ];
            }
        }

        try {
            DB::beginTransaction();

            $rejected = $transfer->rejectBy($user->id, $reason);

            if (!$rejected) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to reject transfer.',
                    'error_code' => 'REJECTION_FAILED'
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Transfer rejected successfully.',
                'transfer_status' => $transfer->status
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject weight transfer', [
                'transfer_id' => $transfer->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => 'An error occurred while rejecting the transfer.',
                'error_code' => 'EXCEPTION_OCCURRED',
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ];
        }
    }

    /**
     * Get approval status for a transfer
     */
    public function getApprovalStatus(WeightTransfer $transfer): array
    {
        $approvals = $transfer->approvals;

        // Get inventory requests for this transfer
        $inventoryService = app(\App\Services\InventoryRequestService::class);
        $inventoryRequests = $inventoryService->getInventoryRequestsForTransfer($transfer->id);

        $status = [
            'status' => $transfer->getApprovalStatus(),
            'requires_sequential_approval' => $transfer->requires_sequential_approval,
            'current_approval_level' => $transfer->current_approval_level,
            'approvals' => $approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'approver_name' => $approval->approver->name ?? 'Unknown',
                    'warehouse_name' => $approval->warehouse->name ?? 'Unknown',
                    'approval_level' => $approval->approval_level,
                    'approval_sequence' => $approval->approval_sequence,
                    'is_final_approval' => $approval->is_final_approval,
                    'status' => $approval->approval_status,
                    'notes' => $approval->approval_notes,
                    'approved_at' => $approval->approved_at,
                    'rejection_reason' => $approval->rejection_reason,
                    'is_next_in_sequence' => $approval->isNextInSequence(),
                ];
            }),
            'inventory_requests' => $inventoryRequests,
            'all_inventory_requests_completed' => $inventoryService->areAllRequestsCompletedForTransfer($transfer->id),
            'can_be_completed' => $transfer->isFullyApproved(),
        ];

        // Add next required approval info
        if ($transfer->requires_sequential_approval && !$transfer->isFullyApproved()) {
            $nextApproval = $transfer->approvals()
                ->where('approval_status', 'pending')
                ->orderBy('approval_sequence')
                ->first();

            if ($nextApproval && $nextApproval->isNextInSequence()) {
                $status['next_required_approval'] = [
                    'approver_name' => $nextApproval->approver->name ?? 'Unknown',
                    'warehouse_name' => $nextApproval->warehouse->name ?? 'Unknown',
                    'approval_level' => $nextApproval->approval_level,
                    'sequence' => $nextApproval->approval_sequence,
                ];
            }
        }

        return $status;
    }

    /**
     * Validate stock availability for grouped transfers
     */
    private function validateGroupedTransferStock(WeightTransfer $transfer): array
    {
        $errors = [];

        if (!$transfer->transfer_group_id) {
            return ['valid' => true, 'errors' => []];
        }

        // Get all transfers in the group
        $groupedTransfers = WeightTransfer::where('transfer_group_id', $transfer->transfer_group_id)->get();
        $totalWeight = $groupedTransfers->sum('weight_transferred');

        // Check source warehouse stock
        if ($transfer->source_warehouse_id && $transfer->orderMaterial) {
            $sourceStock = \App\Models\Stock::where('warehouse_id', $transfer->source_warehouse_id)
                ->where('product_id', $transfer->orderMaterial->product_id)
                ->first();

            if (!$sourceStock) {
                $errors[] = 'No stock found in source warehouse';
            } elseif ($sourceStock->available_quantity < $totalWeight) {
                $errors[] = sprintf(
                    'Insufficient stock in source warehouse. Available: %s, Required: %s',
                    $sourceStock->available_quantity,
                    $totalWeight
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate cutting transfer completion requirements
     */
    private function validateCuttingTransferCompletion(WeightTransfer $transfer): array
    {
        $errors = [];

        // Ensure cutting result exists and is approved
        $cuttingResult = $transfer->cuttingResult;
        if (!$cuttingResult) {
            $errors[] = 'Cutting result not found for transfer';
            return ['valid' => false, 'errors' => $errors];
        }

        if (!$cuttingResult->isApproved()) {
            $errors[] = 'Cutting result must be approved before transfer completion';
        }

        // Validate that all required transfer types are present in the group
        $transferGroupId = $transfer->transfer_group_id;
        if ($transferGroupId) {
            $existingTransfers = WeightTransfer::where('transfer_group_id', $transferGroupId)->get();

            $hasCutMaterial = $existingTransfers->where('transfer_category', 'cut_material')->isNotEmpty();
            $hasWaste = $existingTransfers->where('transfer_category', 'cutting_waste')->isNotEmpty();

            if (!$hasCutMaterial) {
                $errors[] = 'Cut material transfer is required for cutting operation';
            }

            // Waste transfer is optional but should be tracked if waste was generated
            if ($cuttingResult->waste_weight > 0 && !$hasWaste) {
                $errors[] = 'Waste transfer must be created when waste is generated';
            }
        }

        // Validate weight consistency between cutting result and transfers
        $totalTransferredWeight = WeightTransfer::where('transfer_group_id', $transferGroupId)
            ->where('status', 'approved')
            ->whereIn('transfer_category', ['cut_material', 'cutting_waste', 'cutting_remainder'])
            ->sum('weight_transferred');

        $expectedTotal = $cuttingResult->cut_weight + $cuttingResult->waste_weight + $cuttingResult->remaining_weight;

        if (abs($totalTransferredWeight - $expectedTotal) > 0.01) {
            $errors[] = sprintf(
                'Transfer weights do not match cutting result. Expected: %s kg, Transferred: %s kg',
                $expectedTotal,
                $totalTransferredWeight
            );
        }

        // Validate that quality verification is completed
        if (!$transfer->cutting_quality_verified) {
            $errors[] = 'Cutting quality verification must be completed before transfer';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get pending approvals for a specific user across all warehouses
     */
    public function getPendingApprovalsForUser(int $userId): array
    {
        $approvals = WeightTransferApproval::with(['weightTransfer.order', 'weightTransfer.orderMaterial.material', 'warehouse'])
            ->where('approver_id', $userId)
            ->where('approval_status', 'pending')
            ->whereHas('weightTransfer', function($q) {
                $q->where('requires_sequential_approval', true);
            })
            ->orderBy('approval_sequence')
            ->get();

        return $approvals->filter(function($approval) {
            return $approval->isNextInSequence();
        })->map(function($approval) {
            $transfer = $approval->weightTransfer;
            $cuttingInfo = null;

            // Add cutting-specific information if this is a cutting transfer
            if ($transfer->isCuttingTransfer()) {
                $cuttingResult = $transfer->cuttingResult;
                if ($cuttingResult) {
                    $cuttingInfo = [
                        'cutting_result_id' => $cuttingResult->id,
                        'pieces_cut' => $cuttingResult->pieces_cut,
                        'quality_passed' => $cuttingResult->quality_passed,
                        'cutting_machine' => $cuttingResult->cutting_machine,
                        'operator_name' => $cuttingResult->operator_name,
                    ];
                }
            }

            return [
                'id' => $approval->id,
                'transfer_id' => $approval->weight_transfer_id,
                'order_number' => $approval->weightTransfer->order->order_number,
                'product_name' => $approval->weightTransfer->orderMaterial->material->name,
                'weight' => $approval->weightTransfer->weight_transferred,
                'transfer_category' => $approval->weightTransfer->transfer_category,
                'warehouse_name' => $approval->warehouse->name ?? 'Unknown',
                'approval_level' => $approval->approval_level,
                'sequence' => $approval->approval_sequence,
                'material_specs' => $approval->weightTransfer->getMaterialSpecificationsSummary(),
                'cutting_info' => $cuttingInfo,
                'notes' => $approval->weightTransfer->notes,
            ];
        })->toArray();
    }
}
