<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkStage;
use App\Models\OrderProcessing;
use App\Models\OrderStageHistory;
use App\Models\SortingResult;
use App\Models\Warehouse;
use App\Services\SortingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class OrderTrackingService
{
    /**
     * Initialize order processing stages
     */
    public function initializeOrderStages(Order $order): void
    {
        $workStages = WorkStage::active()->orderBy('order')->get();

        foreach ($workStages as $workStage) {
            $order->orderProcessings()->create([
                'work_stage_id' => $workStage->id,
                'status' => 'pending',
                'stage_color' => $workStage->color,
                'can_skip' => $workStage->can_skip,
                'visual_priority' => $workStage->order,
                'estimated_duration' => $workStage->estimated_duration,
            ]);
        }
    }

    /**
     * Move order to next stage
     */
    public function moveToNextStage(Order $order, User $user): array
    {
        DB::beginTransaction();
        try {
            $currentProcessing = $order->orderProcessings()
                                      ->where('status', 'in_progress')
                                      ->first();

            if (!$currentProcessing) {
                // Start with first pending stage
                $currentProcessing = $order->orderProcessings()
                                          ->where('status', 'pending')
                                          ->orderBy('visual_priority')
                                          ->first();

                if (!$currentProcessing) {
                    return ['success' => false, 'message' => 'No pending stages available'];
                }

                $currentProcessing->update([
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'assigned_to' => $user->id,
                ]);

                $this->recordHistory($order, $currentProcessing->workStage, 'start', $user);
            } else {
                // Complete current stage
                $currentProcessing->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'actual_duration' => $currentProcessing->started_at->diffInMinutes(now()),
                ]);

                $this->recordHistory($order, $currentProcessing->workStage, 'complete', $user);

                // Move to next stage
                $nextProcessing = $order->orderProcessings()
                                       ->where('visual_priority', '>', $currentProcessing->visual_priority)
                                       ->where('status', 'pending')
                                       ->orderBy('visual_priority')
                                       ->first();

                if ($nextProcessing) {
                    $nextProcessing->update([
                        'status' => 'in_progress',
                        'started_at' => now(),
                        'assigned_to' => $user->id,
                    ]);

                    $order->update(['current_stage' => $nextProcessing->workStage->name_ar]);
                    $this->recordHistory($order, $nextProcessing->workStage, 'start', $user);
                }
            }

            DB::commit();
            return ['success' => true, 'message' => 'Stage progression completed'];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Skip a stage
     */
    public function skipStage(Order $order, int $workStageId, User $user, string $reason = null): array
    {
        $processing = $order->orderProcessings()
                           ->where('work_stage_id', $workStageId)
                           ->first();

        if (!$processing) {
            return ['success' => false, 'message' => 'Stage not found for this order'];
        }

        if (!$processing->canBeSkipped()) {
            return ['success' => false, 'message' => 'Stage cannot be skipped'];
        }

        $result = $processing->skip($user, $reason);

        if ($result) {
            return ['success' => true, 'message' => 'Stage skipped successfully'];
        }

        return ['success' => false, 'message' => 'Failed to skip stage'];
    }

    /**
     * Get orders with advanced filtering
     */
    public function getFilteredOrders(array $filters = [], User $user = null): Collection
    {
        $query = Order::query();

        // Apply advanced filters
        $query = $query->advancedFilter($filters);

        // Apply user-specific visibility
        if ($user) {
            $query = $query->visibleToUser($user);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        if ($sortBy === 'stage_priority') {
            $query->byPriority();
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        return $query->get();
    }

    /**
     * Get stage progress for an order
     */
    public function getStageProgress(Order $order): array
    {
        return $order->stage_progress;
    }

    /**
     * Get stage statistics
     */
    public function getStageStatistics(array $filters = []): array
    {
        $query = Order::query();

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $orders = $query->get();

        $stats = [
            'total_orders' => $orders->count(),
            'stage_distribution' => [],
            'average_completion_time' => 0,
            'bottlenecks' => [],
        ];

        // Stage distribution
        $stageCounts = $orders->groupBy('current_stage');
        foreach ($stageCounts as $stage => $stageOrders) {
            $stats['stage_distribution'][$stage] = $stageOrders->count();
        }

        // Calculate bottlenecks (stages with most orders stuck)
        arsort($stats['stage_distribution']);
        $stats['bottlenecks'] = array_slice($stats['stage_distribution'], 0, 3, true);

        return $stats;
    }

    /**
     * Get available stages for user
     */
    public function getAvailableStagesForUser(User $user): Collection
    {
        return WorkStage::active()
                       ->get()
                       ->filter(function ($stage) use ($user) {
                           return $stage->canBeAccessedBy($user);
                       });
    }

    /**
     * Record stage history
     */
    private function recordHistory(Order $order, WorkStage $workStage, string $action, User $user, string $notes = null): void
    {
        OrderStageHistory::create([
            'order_id' => $order->id,
            'work_stage_id' => $workStage->id,
            'previous_stage' => $order->current_stage,
            'new_stage' => $workStage->name_ar,
            'action' => $action,
            'action_by' => $user->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Bulk update stage assignments
     */
    public function bulkAssignStages(Collection $orders, int $workStageId, User $user, User $assignee): array
    {
        $successCount = 0;
        $errors = [];

        foreach ($orders as $order) {
            try {
                $processing = $order->orderProcessings()
                                   ->where('work_stage_id', $workStageId)
                                   ->first();

                if ($processing) {
                    $processing->update(['assigned_to' => $assignee->id]);
                    $successCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "Order {$order->order_number}: {$e->getMessage()}";
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get stage efficiency metrics
     */
    public function getStageEfficiencyMetrics(int $workStageId, array $dateRange = []): array
    {
        $query = OrderProcessing::where('work_stage_id', $workStageId)
                               ->where('status', 'completed');

        if (!empty($dateRange)) {
            $query->whereBetween('completed_at', $dateRange);
        }

        $completedStages = $query->get();

        if ($completedStages->isEmpty()) {
            return [
                'average_duration' => 0,
                'efficiency_rate' => 0,
                'total_completed' => 0,
            ];
        }

        $totalDuration = $completedStages->sum(function ($stage) {
            return $stage->actual_duration ?: $stage->estimated_duration;
        });

        $averageDuration = $totalDuration / $completedStages->count();

        $workStage = WorkStage::find($workStageId);
        $estimatedDuration = $workStage ? $workStage->estimated_duration : 60;

        $efficiencyRate = $estimatedDuration > 0 ? ($estimatedDuration / $averageDuration) * 100 : 0;

        return [
            'average_duration' => round($averageDuration, 2),
            'estimated_duration' => $estimatedDuration,
            'efficiency_rate' => round(min($efficiencyRate, 100), 2), // Cap at 100%
            'total_completed' => $completedStages->count(),
        ];
    }

    /**
     * Approve weight received in sorting stage
     */
    public function approveSortingWeightReceived(OrderProcessing $processing, User $user, float $weight, string $notes = null): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'message' => 'Not a sorting stage'];
        }

        if (!$this->canUserApproveSortingWeight($user, $processing)) {
            return ['success' => false, 'message' => 'User not authorized to approve sorting weight'];
        }

        if ($processing->weight_received > 0) {
            return ['success' => false, 'message' => 'Weight already approved for this stage'];
        }

        DB::beginTransaction();
        try {
            $processing->weight_received = $weight;
            $processing->sorting_notes = $notes;
            $processing->save();

            $this->recordHistory($processing->order, $processing->workStage, 'weight_approved', $user, $notes);

            DB::commit();
            return ['success' => true, 'message' => 'Weight received approved successfully'];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Record sorting results and convert roll to roll1 + roll2 with waste
     */
    public function recordSortingResults(OrderProcessing $processing, User $user, array $sortingData): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'message' => 'Not a sorting stage'];
        }

        if (!$this->canUserPerformSorting($user, $processing)) {
            return ['success' => false, 'message' => 'User not authorized to perform sorting'];
        }

        $sortingService = new SortingService();
        $validationErrors = $sortingService->validateSortingData($sortingData);

        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors];
        }

        DB::beginTransaction();
        try {
            // Record sorting results using the processing method
            $processing->recordSortingResults($sortingData, $user);

            // Update processing status to completed after sorting
            $processing->status = 'completed';
            $processing->completed_at = now();
            $processing->actual_duration = $processing->started_at ? $processing->started_at->diffInMinutes(now()) : 0;
            $processing->save();

            $this->recordHistory($processing->order, $processing->workStage, 'sorting_completed', $user);

            DB::commit();
            return ['success' => true, 'message' => 'Sorting results recorded successfully'];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Manage post-sorting transfer to destination warehouse
     */
    public function managePostSortingTransfer(OrderProcessing $processing, User $user, int $destinationWarehouseId, string $destinationType = 'cutting_warehouse'): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'message' => 'Not a sorting stage'];
        }

        if (!$processing->isSortingApproved()) {
            return ['success' => false, 'message' => 'Sorting must be approved before transfer'];
        }

        if (!$this->canUserManageSortingTransfer($user, $processing)) {
            return ['success' => false, 'message' => 'User not authorized to manage sorting transfer'];
        }

        $sortingService = new SortingService();
        $result = $sortingService->transferToDestination($processing, $user, $destinationWarehouseId, $destinationType);

        if ($result['success']) {
            $this->recordHistory($processing->order, $processing->workStage, 'transfer_completed', $user, "Transferred to {$result['destination']} - {$result['warehouse']}");
        }

        return $result;
    }

    /**
     * Check if user can approve sorting weight received
     */
    public function canUserApproveSortingWeight(User $user, OrderProcessing $processing): bool
    {
        return $user->hasRole('مسؤول_مستودع') && $processing->isSortingStage();
    }

    /**
     * Check if user can perform sorting operations
     */
    public function canUserPerformSorting(User $user, OrderProcessing $processing): bool
    {
        return $processing->canUserPerformSorting($user);
    }

    /**
     * Check if user can manage sorting transfer
     */
    public function canUserManageSortingTransfer(User $user, OrderProcessing $processing): bool
    {
        return $user->hasRole(['مسؤول_مستودع', 'مدير_عمليات']) && $processing->isSortingStage();
    }

    /**
     * Get sorting summary for an order
     */
    public function getSortingSummary(Order $order): array
    {
        $sortingService = new SortingService();
        return $sortingService->getSortingSummary($order);
    }
}
