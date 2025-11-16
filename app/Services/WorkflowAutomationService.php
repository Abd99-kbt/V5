<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderProcessing;
use App\Models\WorkStage;
use App\Models\OrderStageHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WorkflowAutomationService
{
    /**
     * Auto advance workflow for an order
     */
    public function autoAdvanceWorkflow(Order $order): array
    {
        try {
            $result = [
                'success' => false,
                'advanced' => false,
                'current_stage' => $order->current_stage,
                'next_stage' => null,
                'message' => '',
                'transitions' => [],
            ];

            // Check if auto advance is possible
            if (!$this->canAutoAdvance($order)) {
                $result['message'] = 'Auto advance not possible for this order';
                return $result;
            }

            // Determine next stage
            $nextStage = $this->determineNextStage($order);
            if (!$nextStage) {
                $result['message'] = 'No next stage available';
                return $result;
            }

            $result['next_stage'] = $nextStage->name_en;

            // Validate next stage readiness
            $validation = $this->validateNextStageReadiness($order, $nextStage);
            if (!$validation['ready']) {
                $result['message'] = 'Next stage not ready: ' . implode(', ', $validation['errors']);
                return $result;
            }

            // Execute stage transition
            $transitionResult = $this->executeStageTransition($order, $nextStage);
            if (!$transitionResult['success']) {
                $result['message'] = 'Failed to execute stage transition: ' . $transitionResult['message'];
                return $result;
            }

            $result['success'] = true;
            $result['advanced'] = true;
            $result['current_stage'] = $order->fresh()->current_stage;
            $result['transitions'] = $transitionResult['transitions'];
            $result['message'] = 'Workflow advanced successfully to ' . $nextStage->name_en;

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to auto advance workflow', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'advanced' => false,
                'current_stage' => $order->current_stage,
                'next_stage' => null,
                'message' => 'Failed to auto advance workflow: ' . $e->getMessage(),
                'transitions' => [],
            ];
        }
    }

    /**
     * Check if workflow can auto advance
     */
    public function canAutoAdvance(Order $order): bool
    {
        // Check if order is in a valid state for auto advance
        if (in_array($order->status, ['cancelled', 'delivered', 'ملغي'])) {
            return false;
        }

        // Check if current stage processing is completed
        $currentProcessing = $order->orderProcessings()
            ->whereHas('workStage', function ($query) use ($order) {
                $query->where('name_en', $order->current_stage)
                      ->orWhere('name_ar', $order->current_stage);
            })
            ->first();

        if (!$currentProcessing || $currentProcessing->status !== 'completed') {
            return false;
        }

        // Check if there are any pending approvals or validations
        if ($this->hasPendingApprovals($order)) {
            return false;
        }

        return true;
    }

    /**
     * Determine the next stage for an order
     */
    public function determineNextStage(Order $order): ?WorkStage
    {
        // Get all work stages ordered by their sequence
        $stages = WorkStage::active()
            ->orderBy('order')
            ->get();

        // Find current stage index
        $currentStageIndex = null;
        foreach ($stages as $index => $stage) {
            if ($stage->name_en === $order->current_stage || $stage->name_ar === $order->current_stage) {
                $currentStageIndex = $index;
                break;
            }
        }

        if ($currentStageIndex === null) {
            return null;
        }

        // Check subsequent stages
        for ($i = $currentStageIndex + 1; $i < $stages->count(); $i++) {
            $nextStage = $stages[$i];

            // Check if stage can be skipped
            if ($nextStage->canBeSkipped($this->getSkipConditions($order))) {
                continue;
            }

            // Check if stage is mandatory or has requirements met
            if ($nextStage->is_mandatory || $this->checkStageRequirements($order, $nextStage)) {
                return $nextStage;
            }
        }

        return null;
    }

    /**
     * Validate if next stage is ready
     */
    public function validateNextStageReadiness(Order $order, WorkStage $nextStage): array
    {
        $errors = [];

        // Check stage requirements
        $requirements = $this->getStageRequirements($nextStage);
        foreach ($requirements as $requirement) {
            if (!$this->isRequirementMet($order, $requirement)) {
                $errors[] = $requirement['message'] ?? 'Requirement not met: ' . $requirement['type'];
            }
        }

        // Check for any blocking conditions
        if ($this->hasBlockingConditions($order, $nextStage)) {
            $errors[] = 'Stage has blocking conditions';
        }

        return [
            'ready' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get requirements for a stage
     */
    public function getStageRequirements(WorkStage $stage): array
    {
        $requirements = [];

        switch ($stage->name_en) {
            case 'Material Reservation':
                $requirements[] = [
                    'type' => 'materials_selected',
                    'message' => 'Materials must be selected before reservation',
                    'check' => function ($order) {
                        return $order->materials_selected_at !== null;
                    }
                ];
                break;

            case 'Sorting':
                $requirements[] = [
                    'type' => 'weight_received',
                    'message' => 'Weight must be received before sorting',
                    'check' => function ($order) {
                        $processing = $order->orderProcessings()
                            ->whereHas('workStage', function ($q) {
                                $q->where('name_en', 'Material Reservation');
                            })
                            ->first();
                        return $processing && $processing->weight_received > 0;
                    }
                ];
                break;

            case 'Cutting':
                $requirements[] = [
                    'type' => 'sorting_completed',
                    'message' => 'Sorting must be completed before cutting',
                    'check' => function ($order) {
                        $processing = $order->orderProcessings()
                            ->whereHas('workStage', function ($q) {
                                $q->where('name_en', 'Sorting');
                            })
                            ->first();
                        return $processing && $processing->status === 'completed';
                    }
                ];
                $requirements[] = [
                    'type' => 'delivery_specs',
                    'message' => 'Delivery specifications must be provided',
                    'check' => function ($order) {
                        return !empty(array_filter($order->getDeliverySpecificationsAttribute()));
                    }
                ];
                break;

            case 'Packaging':
                $requirements[] = [
                    'type' => 'cutting_completed',
                    'message' => 'Cutting must be completed before packaging',
                    'check' => function ($order) {
                        $processing = $order->orderProcessings()
                            ->whereHas('workStage', function ($q) {
                                $q->where('name_en', 'Cutting');
                            })
                            ->first();
                        return $processing && $processing->status === 'completed';
                    }
                ];
                break;

            case 'Billing':
                $requirements[] = [
                    'type' => 'packaging_completed',
                    'message' => 'Packaging must be completed before billing',
                    'check' => function ($order) {
                        $processing = $order->orderProcessings()
                            ->whereHas('workStage', function ($q) {
                                $q->where('name_en', 'Packaging');
                            })
                            ->first();
                        return $processing && $processing->status === 'completed';
                    }
                ];
                break;

            case 'Delivery':
                $requirements[] = [
                    'type' => 'billing_completed',
                    'message' => 'Billing must be completed before delivery',
                    'check' => function ($order) {
                        $processing = $order->orderProcessings()
                            ->whereHas('workStage', function ($q) {
                                $q->where('name_en', 'Billing');
                            })
                            ->first();
                        return $processing && $processing->status === 'completed';
                    }
                ];
                $requirements[] = [
                    'type' => 'payment_received',
                    'message' => 'Payment must be received before delivery',
                    'check' => function ($order) {
                        return $order->is_paid;
                    }
                ];
                break;
        }

        return $requirements;
    }

    /**
     * Check if a requirement is met
     */
    public function isRequirementMet(Order $order, array $requirement): bool
    {
        if (isset($requirement['check']) && is_callable($requirement['check'])) {
            return $requirement['check']($order);
        }

        return false;
    }

    /**
     * Execute stage transition
     */
    public function executeStageTransition(Order $order, WorkStage $nextStage, User $user = null): array
    {
        DB::beginTransaction();
        try {
            $transitions = [];

            // Create or update processing record for next stage
            $processing = $order->orderProcessings()
                ->where('work_stage_id', $nextStage->id)
                ->first();

            if (!$processing) {
                $processing = $order->orderProcessings()->create([
                    'work_stage_id' => $nextStage->id,
                    'status' => 'pending',
                    'stage_color' => $nextStage->color,
                    'can_skip' => $nextStage->can_skip,
                    'visual_priority' => $nextStage->order,
                    'estimated_duration' => $nextStage->estimated_duration,
                ]);
                $transitions[] = 'Created processing record for ' . $nextStage->name_en;
            }

            // Update order current stage
            $previousStage = $order->current_stage;
            $order->update(['current_stage' => $nextStage->name_en]);
            $transitions[] = 'Updated order stage from ' . $previousStage . ' to ' . $nextStage->name_en;

            // Log the transition
            $this->logWorkflowTransition($order, $previousStage, $nextStage->name_en, 'auto_advance', $user);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Stage transition executed successfully',
                'transitions' => $transitions,
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to execute stage transition', [
                'order_id' => $order->id,
                'next_stage' => $nextStage->name_en,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to execute stage transition: ' . $e->getMessage(),
                'transitions' => [],
            ];
        }
    }

    /**
     * Log workflow transition
     */
    public function logWorkflowTransition(Order $order, string $previousStage, string $newStage, string $action = 'transition', User $user = null): void
    {
        OrderStageHistory::create([
            'order_id' => $order->id,
            'work_stage_id' => WorkStage::where('name_en', $newStage)->orWhere('name_ar', $newStage)->first()?->id,
            'previous_stage' => $previousStage,
            'new_stage' => $newStage,
            'action' => $action,
            'action_by' => $user ? $user->id : null,
            'notes' => 'Automated workflow transition',
        ]);

        Log::info('Workflow transition logged', [
            'order_id' => $order->id,
            'previous_stage' => $previousStage,
            'new_stage' => $newStage,
            'action' => $action,
            'user_id' => $user ? $user->id : null,
        ]);
    }

    /**
     * Check if order has pending approvals
     */
    private function hasPendingApprovals(Order $order): bool
    {
        // Check for pending weight transfer approvals
        $pendingTransfers = $order->orderProcessings()
            ->where('transfer_approved', false)
            ->where('weight_transferred', '>', 0)
            ->exists();

        if ($pendingTransfers) {
            return true;
        }

        // Check for pending sorting approvals
        $pendingSorting = $order->orderProcessings()
            ->where('sorting_approved', false)
            ->where(function ($query) {
                $query->where('roll1_weight', '>', 0)
                      ->orWhere('roll2_weight', '>', 0);
            })
            ->exists();

        if ($pendingSorting) {
            return true;
        }

        // Check for pending cutting approvals
        $pendingCutting = $order->orderProcessings()
            ->where('cutting_approved', false)
            ->whereHas('cuttingResults')
            ->exists();

        return $pendingCutting;
    }

    /**
     * Get skip conditions for an order
     */
    private function getSkipConditions(Order $order): array
    {
        return [
            'is_urgent' => $order->is_urgent,
            'has_special_requirements' => !empty($order->specifications),
            'is_small_order' => $order->required_weight < 100, // Example threshold
        ];
    }

    /**
     * Check if stage requirements are met
     */
    private function checkStageRequirements(Order $order, WorkStage $stage): bool
    {
        $requirements = $this->getStageRequirements($stage);
        foreach ($requirements as $requirement) {
            if (!$this->isRequirementMet($order, $requirement)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check for blocking conditions
     */
    private function hasBlockingConditions(Order $order, WorkStage $stage): bool
    {
        // Check if order is on hold
        if ($order->status === 'on_hold') {
            return true;
        }

        // Check if there are quality issues
        $hasQualityIssues = $order->orderProcessings()
            ->where('status', 'blocked')
            ->exists();

        return $hasQualityIssues;
    }

    /**
     * Run automated workflow automation
     */
    public function run(): array
    {
        try {
            $pendingOrders = Order::whereIn('status', ['processing', 'قيد_المعالجة'])
                                ->where('auto_workflow_enabled', true)
                                ->get();

            $results = [];
            foreach ($pendingOrders as $order) {
                $result = $this->autoAdvanceWorkflow($order);
                $results[] = [
                    'order_id' => $order->id,
                    'advanced' => $result['advanced'],
                    'current_stage' => $result['current_stage'],
                    'next_stage' => $result['next_stage'],
                    'message' => $result['message']
                ];
            }

            return [
                'success' => true,
                'orders_processed' => count($pendingOrders),
                'results' => $results,
                'timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Automated workflow automation run failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'orders_processed' => 0,
                'results' => []
            ];
        }
    }
}