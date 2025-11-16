<?php

namespace App\Services;

use App\Models\OrderProcessing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomatedApprovalService
{
    /**
     * Auto approve if the operation is eligible for automatic approval
     */
    public function autoApproveIfEligible(OrderProcessing $processing, User $user = null): array
    {
        try {
            // Check if operation is routine
            if (!$this->isRoutineOperation($processing)) {
                return [
                    'approved' => false,
                    'reason' => 'Operation is not routine and requires manual approval',
                    'auto_approved' => false
                ];
            }

            // Check if passes smart validation
            if (!$this->passesSmartValidation($processing)) {
                return [
                    'approved' => false,
                    'reason' => 'Operation failed smart validation checks',
                    'auto_approved' => false
                ];
            }

            // Grant auto approval
            $result = $this->grantAutoApproval($processing, $user);

            return [
                'approved' => $result,
                'reason' => $result ? 'Auto-approved based on routine operation and validation checks' : 'Auto-approval failed',
                'auto_approved' => $result
            ];

        } catch (\Exception $e) {
            Log::error('Auto approval failed', [
                'processing_id' => $processing->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'approved' => false,
                'reason' => 'Error during auto approval process: ' . $e->getMessage(),
                'auto_approved' => false
            ];
        }
    }

    /**
     * Check if the operation is routine and can be auto-approved
     */
    public function isRoutineOperation(OrderProcessing $processing): bool
    {
        // Check if it's a warehouse stage (Material Reservation) - typically routine
        if ($processing->isWarehouseStage()) {
            return $this->isRoutineWarehouseOperation($processing);
        }

        // Check if it's a sorting stage - can be routine if weight is balanced
        if ($processing->isSortingStage()) {
            return $this->isRoutineSortingOperation($processing);
        }

        // Check if it's a cutting stage - can be routine if specifications are standard
        if ($processing->isCuttingStage()) {
            return $this->isRoutineCuttingOperation($processing);
        }

        // Default: not routine for other stages
        return false;
    }

    /**
     * Grant automatic approval for the processing stage
     */
    public function grantAutoApproval(OrderProcessing $processing, User $user = null): bool
    {
        DB::beginTransaction();
        try {
            $approved = false;

            // Handle different stage types
            if ($processing->isWarehouseStage()) {
                $approved = $this->approveWarehouseStage($processing, $user);
            } elseif ($processing->isSortingStage()) {
                $approved = $this->approveSortingStage($processing, $user);
            } elseif ($processing->isCuttingStage()) {
                $approved = $this->approveCuttingStage($processing, $user);
            }

            if ($approved) {
                // Log the auto approval
                $this->logAutoApproval($processing, $user, 'auto_approval');

                DB::commit();
                return true;
            }

            DB::rollback();
            return false;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Grant auto approval failed', [
                'processing_id' => $processing->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if operation passes smart validation
     */
    public function passesSmartValidation(OrderProcessing $processing): bool
    {
        // Validate weight balance
        if (!$this->validateWeightBalance($processing)) {
            return false;
        }

        // Validate quality standards
        if (!$this->validateQualityStandards($processing)) {
            return false;
        }

        // Validate timeline compliance
        if (!$this->validateTimelineCompliance($processing)) {
            return false;
        }

        // Validate cost efficiency
        if (!$this->validateCostEfficiency($processing)) {
            return false;
        }

        return true;
    }

    /**
     * Grant smart approval based on advanced validation
     */
    public function grantSmartApproval(OrderProcessing $processing, User $user = null): bool
    {
        // Smart approval requires all validations to pass with higher thresholds
        if (!$this->passesSmartValidation($processing)) {
            return false;
        }

        // Additional smart checks
        if (!$this->validateAdvancedCriteria($processing)) {
            return false;
        }

        return $this->grantAutoApproval($processing, $user);
    }

    /**
     * Validate weight balance for the processing stage
     */
    public function validateWeightBalance(OrderProcessing $processing): bool
    {
        // For warehouse stages
        if ($processing->isWarehouseStage()) {
            if ($processing->weight_received <= 0) {
                return false;
            }

            if ($processing->weight_transferred > $processing->weight_received) {
                return false;
            }

            // Allow small tolerance for weight balance (0.1% tolerance)
            $tolerance = $processing->weight_received * 0.001;
            return abs($processing->weight_balance) <= $tolerance;
        }

        // For sorting stages
        if ($processing->isSortingStage()) {
            return $processing->isSortingWeightBalanced();
        }

        // For cutting stages - weight balance is less critical
        return true;
    }

    /**
     * Validate quality standards for the processing stage
     */
    public function validateQualityStandards(OrderProcessing $processing): bool
    {
        // Check delivery specifications validation
        $specErrors = $processing->validateDeliverySpecificationsForStage();
        if (!empty($specErrors)) {
            return false;
        }

        // For sorting stages, check if results are recorded
        if ($processing->isSortingStage()) {
            if (!$processing->sortingResults()->exists()) {
                return false;
            }

            // Check waste percentage is within acceptable range
            $wastePercentage = ($processing->sorting_waste_weight / $processing->weight_received) * 100;
            if ($wastePercentage > 15) { // Max 15% waste allowed
                return false;
            }
        }

        // For cutting stages, check if results are recorded
        if ($processing->isCuttingStage()) {
            if (!$processing->cuttingResults()->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate timeline compliance for the processing stage
     */
    public function validateTimelineCompliance(OrderProcessing $processing): bool
    {
        // Check if stage is not overdue
        if ($processing->workStage->estimated_duration) {
            $expectedCompletion = $processing->started_at?->addMinutes($processing->workStage->estimated_duration);
            if ($expectedCompletion && now()->isAfter($expectedCompletion)) {
                // Allow 20% grace period
                $gracePeriod = $expectedCompletion->addMinutes($processing->workStage->estimated_duration * 0.2);
                if (now()->isAfter($gracePeriod)) {
                    return false;
                }
            }
        }

        // Check if order delivery date allows for this processing
        $order = $processing->order;
        if ($order && $order->delivery_date) {
            $remainingTime = now()->diffInHours($order->delivery_date);
            $stageDuration = $processing->workStage->estimated_duration ?? 60; // Default 1 hour

            // Need at least stage duration + buffer time
            if ($remainingTime < ($stageDuration / 60) + 2) { // 2 hours buffer
                return false;
            }
        }

        return true;
    }

    /**
     * Validate cost efficiency for the processing stage
     */
    public function validateCostEfficiency(OrderProcessing $processing): bool
    {
        $order = $processing->order;

        // Check if order has pricing information
        if (!$order || !$order->final_price) {
            return true; // Skip if no pricing available
        }

        // For sorting stages, check waste cost efficiency
        if ($processing->isSortingStage() && $processing->sorting_waste_weight > 0) {
            $wasteCost = $this->calculateWasteCost($processing);
            $maxAllowedWasteCost = $order->final_price * 0.05; // Max 5% of final price

            if ($wasteCost > $maxAllowedWasteCost) {
                return false;
            }
        }

        // Check labor cost efficiency
        $laborCost = $this->estimateLaborCost($processing);
        $maxAllowedLaborCost = $order->final_price * 0.3; // Max 30% labor cost

        if ($laborCost > $maxAllowedLaborCost) {
            return false;
        }

        return true;
    }

    /**
     * Check if warehouse operation is routine
     */
    private function isRoutineWarehouseOperation(OrderProcessing $processing): bool
    {
        // Routine if weight received is within standard range
        return $processing->weight_received > 0 &&
               $processing->weight_received <= 5000 && // Max 5 tons
               !empty($processing->transfer_destination) &&
               $processing->weight_balance >= -10; // Allow small negative balance
    }

    /**
     * Check if sorting operation is routine
     */
    private function isRoutineSortingOperation(OrderProcessing $processing): bool
    {
        return $processing->weight_received > 0 &&
               $processing->isSortingWeightBalanced() &&
               ($processing->roll1_weight > 0 || $processing->roll2_weight > 0) &&
               $processing->sorting_waste_weight <= ($processing->weight_received * 0.1); // Max 10% waste
    }

    /**
     * Check if cutting operation is routine
     */
    private function isRoutineCuttingOperation(OrderProcessing $processing): bool
    {
        // Routine if specifications are standard and results exist
        return $processing->cuttingResults()->exists() &&
               empty($processing->validateDeliverySpecificationsForStage());
    }

    /**
     * Approve warehouse stage
     */
    private function approveWarehouseStage(OrderProcessing $processing, User $user = null): bool
    {
        if (!$processing->transfer_approved) {
            return $processing->approveTransfer($user?->id, 'Auto-approved for routine warehouse operation');
        }
        return true;
    }

    /**
     * Approve sorting stage
     */
    private function approveSortingStage(OrderProcessing $processing, User $user = null): bool
    {
        if (!$processing->sorting_approved) {
            return $processing->approveSorting($user?->id, 'Auto-approved for routine sorting operation');
        }
        return true;
    }

    /**
     * Approve cutting stage
     */
    private function approveCuttingStage(OrderProcessing $processing, User $user = null): bool
    {
        // For cutting, we need to approve the cutting results
        // This assumes cutting approval is handled separately
        return true; // Placeholder - implement based on actual cutting approval logic
    }

    /**
     * Validate advanced criteria for smart approval
     */
    private function validateAdvancedCriteria(OrderProcessing $processing): bool
    {
        // Check user performance history
        if ($processing->assigned_to) {
            $userPerformance = $this->checkUserPerformance($processing->assigned_to);
            if ($userPerformance < 0.8) { // Require 80% success rate
                return false;
            }
        }

        // Check order priority - high priority orders may need manual review
        if ($processing->order && $processing->order->priority === 'high') {
            return false;
        }

        // Check for any previous issues in the order processing chain
        if ($this->hasProcessingIssues($processing->order)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate waste cost
     */
    private function calculateWasteCost(OrderProcessing $processing): float
    {
        // Estimate waste cost based on material value
        $order = $processing->order;
        if (!$order || !$order->estimated_material_cost) {
            return 0;
        }

        $wasteRatio = $processing->sorting_waste_weight / $processing->weight_received;
        return $order->estimated_material_cost * $wasteRatio;
    }

    /**
     * Estimate labor cost for processing
     */
    private function estimateLaborCost(OrderProcessing $processing): float
    {
        $duration = $processing->actual_duration ?? $processing->workStage->estimated_duration ?? 60;
        $hourlyRate = 50; // Assume $50/hour labor rate

        return ($duration / 60) * $hourlyRate;
    }

    /**
     * Check user performance history
     */
    private function checkUserPerformance(int $userId): float
    {
        // Calculate success rate from recent processing history
        $recentProcessings = OrderProcessing::where('assigned_to', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentProcessings === 0) {
            return 1.0; // New user, assume perfect performance
        }

        $successfulProcessings = OrderProcessing::where('assigned_to', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'completed')
            ->count();

        return $successfulProcessings / $recentProcessings;
    }

    /**
     * Check if order has processing issues
     */
    private function hasProcessingIssues(Order $order): bool
    {
        // Check for failed or cancelled processings
        return $order->processings()
            ->whereIn('status', ['cancelled', 'failed'])
            ->exists();
    }

    /**
     * Log auto approval action
     */
    private function logAutoApproval(OrderProcessing $processing, User $user = null, string $action): void
    {
        Log::info('Automated approval granted', [
            'processing_id' => $processing->id,
            'order_id' => $processing->order_id,
            'stage' => $processing->workStage->name_en ?? 'Unknown',
            'action' => $action,
            'approved_by' => $user?->id ?? 'system',
            'timestamp' => now()
        ]);
    }
}