<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStage;
use App\Models\Stock;
use App\Models\Product;
use App\Models\User;
use App\Models\WeightTransfer;
use App\Models\OrderMaterial;
use App\Models\SortingResult;
use App\Models\OrderProcessing;
use App\Models\Warehouse;
use App\Models\CuttingResult;
use App\Services\MaterialSpecificationService;
use App\Services\SortingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderProcessingService
{
    protected MaterialSpecificationService $materialSpecService;
    protected SortingService $sortingService;

    public function __construct(
        MaterialSpecificationService $materialSpecService,
        SortingService $sortingService
    ) {
        $this->materialSpecService = $materialSpecService;
        $this->sortingService = $sortingService;
    }

    /**
     * Calculate pricing for an order based on material cost, cutting fees, and discount
     */
    public function calculateOrderPricing(Order $order): array
    {
        $calculation = [
            'material_cost' => 0,
            'cutting_fees' => $order->cutting_fees ?? 0,
            'subtotal' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'breakdown' => [],
            'is_valid' => true,
            'errors' => []
        ];

        // Calculate material cost based on required weight and price per ton
        if ($order->required_weight && $order->price_per_ton) {
            // Convert weight from kg to tons and calculate cost
            $weightInTons = $order->required_weight / 1000;
            $calculation['material_cost'] = $weightInTons * $order->price_per_ton;
            $calculation['breakdown'][] = [
                'type' => 'material_cost',
                'description' => 'Material cost (' . number_format($weightInTons, 3) . ' tons × ' . number_format($order->price_per_ton, 2) . ' per ton)',
                'amount' => $calculation['material_cost']
            ];
        } else {
            $calculation['is_valid'] = false;
            $calculation['errors'][] = 'Required weight and price per ton must be specified';
        }

        // Add cutting fees
        if ($calculation['cutting_fees'] > 0) {
            $calculation['breakdown'][] = [
                'type' => 'cutting_fees',
                'description' => 'Cutting fees',
                'amount' => $calculation['cutting_fees']
            ];
        }

        // Calculate subtotal
        $calculation['subtotal'] = $calculation['material_cost'] + $calculation['cutting_fees'];

        // Apply discount if specified
        if ($order->discount && $order->discount > 0) {
            $calculation['discount_amount'] = ($calculation['subtotal'] * $order->discount) / 100;
            $calculation['breakdown'][] = [
                'type' => 'discount',
                'description' => 'Discount (' . $order->discount . '%)',
                'amount' => -$calculation['discount_amount']
            ];
        }

        // Calculate final total
        $calculation['total_amount'] = $calculation['subtotal'] - $calculation['discount_amount'];

        return $calculation;
    }

    /**
     * Update order pricing and save calculation results
     */
    public function updateOrderPricing(Order $order, User $user): array
    {
        $calculation = $this->calculateOrderPricing($order);

        if (!$calculation['is_valid']) {
            return [
                'success' => false,
                'errors' => $calculation['errors']
            ];
        }

        DB::beginTransaction();
        try {
            // Update order with calculated values
            $order->update([
                'estimated_price' => $calculation['total_amount'],
                'final_price' => $calculation['total_amount'],
                'pricing_breakdown' => $calculation['breakdown'],
                'pricing_calculated' => true,
                'pricing_calculated_at' => now(),
                'pricing_calculated_by' => $user->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'calculation' => $calculation,
                'message' => 'Order pricing calculated successfully'
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'error' => 'Failed to update order pricing: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate pricing inputs for an order
     */
    public function validatePricingInputs(Order $order): array
    {
        $errors = [];

        // Validate required weight
        if (!$order->required_weight || $order->required_weight <= 0) {
            $errors[] = 'Required weight must be greater than 0';
        }

        // Validate price per ton
        if (!$order->price_per_ton || $order->price_per_ton <= 0) {
            $errors[] = 'Price per ton must be greater than 0';
        }

        // Validate cutting fees (can be 0 but not negative)
        if ($order->cutting_fees < 0) {
            $errors[] = 'Cutting fees cannot be negative';
        }

        // Validate discount (must be between 0 and 100)
        if ($order->discount < 0 || $order->discount > 100) {
            $errors[] = 'Discount must be between 0 and 100 percent';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Create order stages for a new order
     */
    public function createOrderStages(Order $order): void
    {
        $stages = [
            [
                'stage_name' => 'إنشاء',
                'stage_order' => 1,
                'status' => 'مكتمل',
                'requires_approval' => false,
            ],
            [
                'stage_name' => 'مراجعة',
                'stage_order' => 2,
                'status' => 'معلق',
                'requires_approval' => true,
            ],
            [
                'stage_name' => 'حجز_المواد',
                'stage_order' => 3,
                'status' => 'معلق',
                'requires_approval' => false,
            ],
            [
                'stage_name' => 'فرز',
                'stage_order' => 4,
                'status' => 'معلق',
                'requires_approval' => true,
            ],
            [
                'stage_name' => 'قص',
                'stage_order' => 5,
                'status' => 'معلق',
                'requires_approval' => true,
            ],
            [
                'stage_name' => 'تعبئة',
                'stage_order' => 6,
                'status' => 'معلق',
                'requires_approval' => false,
            ],
            [
                'stage_name' => 'فوترة',
                'stage_order' => 7,
                'status' => 'معلق',
                'requires_approval' => true,
            ],
            [
                'stage_name' => 'تسليم',
                'stage_order' => 8,
                'status' => 'معلق',
                'requires_approval' => true,
            ],
        ];

        foreach ($stages as $stageData) {
            $order->stages()->create($stageData);
        }
    }

    /**
     * Move order to next stage
     */
    public function moveToNextStage(Order $order, User $user): bool
    {
        // Validate weight transfers are approved before stage completion
        if (!$this->validateStageTransition($order)) {
            return false;
        }

        $currentStage = $order->stages()->where('status', 'قيد_التنفيذ')->first();

        if (!$currentStage) {
            // Start with the first pending stage
            $currentStage = $order->stages()->where('status', 'معلق')->orderBy('stage_order')->first();
            if (!$currentStage) {
                return false;
            }
            $currentStage->start();
            $currentStage->assigned_to = $user->id;
            $currentStage->save();
        }

        // Complete current stage
        $currentStage->complete();

        // Move to next stage
        $nextStage = $order->stages()
                          ->where('stage_order', '>', $currentStage->stage_order)
                          ->orderBy('stage_order')
                          ->first();

        if ($nextStage) {
            $nextStage->start();
            $nextStage->assigned_to = $user->id;
            $nextStage->save();
            $order->current_stage = $nextStage->stage_name;
            $order->save();
        }

        return true;
    }

    /**
     * Extract materials from warehouse for order with specification matching
     */
    public function extractMaterials(Order $order, User $user): array
    {
        $results = [];

        DB::beginTransaction();
        try {
            // Create OrderMaterial records if they don't exist
            if ($order->orderMaterials->isEmpty()) {
                $this->createOrderMaterialsFromOrderItems($order);
            }

            foreach ($order->orderMaterials as $orderMaterial) {
                $product = $orderMaterial->material;
                $requiredWeight = $orderMaterial->requested_weight;

                // Use MaterialSpecificationService for roll matching
                $requiredSpecs = [
                    'width' => $orderMaterial->required_width,
                    'grammage' => $orderMaterial->required_grammage,
                    'quality' => $orderMaterial->quality_grade,
                    'min_length' => $orderMaterial->required_length,
                ];

                $suitableStocks = $this->materialSpecService->findSuitableRolls(
                    $product,
                    $requiredSpecs,
                    $requiredWeight
                );

                if ($suitableStocks->isEmpty()) {
                    throw new \Exception("No suitable rolls found for product: {$product->name} matching specifications");
                }

                $totalAvailable = $suitableStocks->sum('available_quantity');

                if ($totalAvailable < $requiredWeight) {
                    throw new \Exception("Insufficient suitable stock for product: {$product->name}");
                }

                $extractedWeight = 0;
                $assignedRolls = [];

                foreach ($suitableStocks as $stock) {
                    if ($extractedWeight >= $requiredWeight) break;

                    $neededFromThisStock = min($stock->available_quantity, $requiredWeight - $extractedWeight);

                    if ($stock->recordExtraction($neededFromThisStock, $order->id, $user->id)) {
                        $extractedWeight += $neededFromThisStock;

                        // Assign roll specifications to order material
                        $rollSpecs = $this->materialSpecService->getRollSpecifications($stock);
                        $orderMaterial->assignRollFromStock($stock, $rollSpecs);
                        $assignedRolls[] = $rollSpecs;
                    }
                }

                $orderMaterial->extracted_weight = $extractedWeight;
                $orderMaterial->status = 'مستخرج';
                $orderMaterial->extracted_at = now();
                $orderMaterial->save();

                // Validate specifications after assignment
                $validation = $orderMaterial->validateRollSpecifications();

                $results[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'requested_weight' => $requiredWeight,
                    'extracted_weight' => $extractedWeight,
                    'assigned_rolls' => $assignedRolls,
                    'specification_validation' => $validation,
                    'success' => true,
                ];
            }

            // Update order stage
            $extractionStage = $order->stages()->where('stage_name', 'حجز_المواد')->first();
            if ($extractionStage) {
                $extractionStage->complete();
                $extractionStage->weight_output = $order->orderMaterials->sum('extracted_weight');
                $extractionStage->save();
            }

            DB::commit();
            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transfer materials to sorting warehouse
     */
    public function transferToSorting(Order $order, User $user, int $sortingWarehouseId): array
    {
        $results = [];

        DB::beginTransaction();
        try {
            foreach ($order->orderMaterials as $orderMaterial) {
                if ($orderMaterial->status !== 'مستخرج') continue;

                $product = $orderMaterial->material;
                $transferWeight = $orderMaterial->extracted_weight;

                // Find stock in main warehouse
                $mainWarehouseStock = $product->stocks()
                                             ->whereHas('warehouse', function($q) {
                                                 $q->where('type', 'مستودع_رئيسي');
                                             })
                                             ->where('is_active', true)
                                             ->first();

                if (!$mainWarehouseStock || $mainWarehouseStock->reserved_quantity < $transferWeight) {
                    throw new \Exception("Insufficient reserved stock for product: {$product->name}");
                }

                // Transfer to sorting warehouse
                if ($mainWarehouseStock->transferToWarehouse($transferWeight, $sortingWarehouseId, $user->id, 'Transfer to sorting')) {
                    $results[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'transfer_weight' => $transferWeight,
                        'success' => true,
                    ];
                }
            }

            // Update order stage
            $sortingStage = $order->stages()->where('stage_name', 'فرز')->first();
            if ($sortingStage) {
                $sortingStage->start();
                $sortingStage->from_warehouse_id = $order->warehouse_id;
                $sortingStage->to_warehouse_id = $sortingWarehouseId;
                $sortingStage->weight_input = $order->orderMaterials->sum('extracted_weight');
                $sortingStage->save();
            }

            DB::commit();
            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record sorting results
     */
    public function recordSorting(Order $order, User $user, array $sortingData): array
    {
        $results = [];

        DB::beginTransaction();
        try {
            foreach ($sortingData as $orderMaterialId => $data) {
                $orderMaterial = $order->orderMaterials()->find($orderMaterialId);
                if (!$orderMaterial || $orderMaterial->status !== 'مستخرج') continue;

                $sortedWeight = $data['sorted_weight'];
                $wasteWeight = $data['waste_weight'];
                $wasteReason = $data['waste_reason'];

                $orderMaterial->recordSorting($sortedWeight, $wasteWeight, $wasteReason, $user->id);

                $results[] = [
                    'product_id' => $orderMaterial->material_id,
                    'sorted_weight' => $sortedWeight,
                    'waste_weight' => $wasteWeight,
                    'success' => true,
                ];
            }

            // Update sorting stage
            $sortingStage = $order->stages()->where('stage_name', 'فرز')->first();
            if ($sortingStage) {
                $sortingStage->complete();
                $sortingStage->weight_output = $order->orderMaterials->sum('sorted_weight');
                $sortingStage->waste_weight = $order->orderMaterials->sum('sorting_waste_weight');
                $sortingStage->save();
            }

            DB::commit();
            return ['success' => true, 'results' => $results];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Approve stage transition
     */
    public function approveStage(OrderStage $stage, User $user): bool
    {
        if (!$stage->requires_approval || !$stage->isPending()) {
            return false;
        }

        return $stage->approve($user->id);
    }

    /**
     * Reject stage transition
     */
    public function rejectStage(OrderStage $stage, User $user, string $reason): bool
    {
        if (!$stage->requires_approval || !$stage->isPending()) {
            return false;
        }

        return $stage->reject($user->id, $reason);
    }

    /**
     * Request weight transfer between stages
     */
    public function requestWeightTransfer(Order $order, int $orderMaterialId, string $fromStage, string $toStage, float $weight, int $userId, string $notes = null): array
    {
        $orderMaterial = $order->orderMaterials()->find($orderMaterialId);
        if (!$orderMaterial) {
            return ['success' => false, 'error' => 'Order material not found'];
        }

        // Check if material can be transferred to the target stage
        if (!$orderMaterial->canTransferToStage($toStage)) {
            return ['success' => false, 'error' => 'Material cannot be transferred to ' . $toStage . ' stage'];
        }

        // Check available weight
        $availableWeight = $orderMaterial->getAvailableWeightForTransfer();
        if ($weight > $availableWeight) {
            return ['success' => false, 'error' => 'Insufficient weight available for transfer'];
        }

        // Create transfer request with material specifications
        $materialSpecs = $orderMaterial->getRollSpecificationSummary();

        $transfer = WeightTransfer::create([
            'order_id' => $order->id,
            'order_material_id' => $orderMaterialId,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'weight_transferred' => $weight,
            'transfer_type' => 'stage_transfer',
            'requested_by' => $userId,
            'status' => 'pending',
            'notes' => $notes,
            // Include material specifications
            'roll_number' => $materialSpecs['actual_specs']['roll_number'],
            'material_width' => $materialSpecs['actual_specs']['width'],
            'material_length' => $materialSpecs['actual_specs']['length'],
            'material_grammage' => $materialSpecs['actual_specs']['grammage'],
            'quality_grade' => $materialSpecs['required_specs']['quality'],
            'batch_number' => $materialSpecs['actual_specs']['batch_number'] ?? null,
        ]);

        return ['success' => true, 'transfer' => $transfer];
    }

    /**
     * Approve weight transfer
     */
    public function approveWeightTransfer(int $transferId, int $userId, string $notes = null): array
    {
        $transfer = WeightTransfer::find($transferId);
        if (!$transfer) {
            return ['success' => false, 'error' => 'Transfer not found'];
        }

        if (!$transfer->approve($userId)) {
            return ['success' => false, 'error' => 'Unable to approve transfer'];
        }

        // Update transfer notes if provided
        if ($notes) {
            $transfer->notes = ($transfer->notes ? $transfer->notes . "\n" : '') . "Approved: " . $notes;
            $transfer->save();
        }

        return ['success' => true, 'transfer' => $transfer];
    }

    /**
     * Reject weight transfer
     */
    public function rejectWeightTransfer(int $transferId, int $userId, string $reason): array
    {
        $transfer = WeightTransfer::find($transferId);
        if (!$transfer) {
            return ['success' => false, 'error' => 'Transfer not found'];
        }

        if (!$transfer->reject($userId, $reason)) {
            return ['success' => false, 'error' => 'Unable to reject transfer'];
        }

        return ['success' => true, 'transfer' => $transfer];
    }

    /**
     * Complete weight transfer (after approval)
     */
    public function completeWeightTransfer(int $transferId): array
    {
        $transfer = WeightTransfer::find($transferId);
        if (!$transfer) {
            return ['success' => false, 'error' => 'Transfer not found'];
        }

        if (!$transfer->isApproved()) {
            return ['success' => false, 'error' => 'Transfer must be approved before completion'];
        }

        // Update order processing records
        $fromProcessing = $transfer->order->orderProcessings()
            ->whereHas('workStage', function($q) use ($transfer) {
                $q->where('name_ar', $transfer->from_stage)
                  ->orWhere('name_en', $transfer->from_stage);
            })
            ->first();

        $toProcessing = $transfer->order->orderProcessings()
            ->whereHas('workStage', function($q) use ($transfer) {
                $q->where('name_ar', $transfer->to_stage)
                  ->orWhere('name_en', $transfer->to_stage);
            })
            ->first();

        if ($fromProcessing) {
            $fromProcessing->recordWeightTransferred($transfer->weight_transferred);
        }

        if ($toProcessing) {
            $toProcessing->recordWeightReceived($transfer->weight_transferred);
            $toProcessing->approveTransfer($transfer->approved_by, 'Auto-approved via transfer completion');
        }

        // Mark transfer as completed
        $transfer->complete();

        return ['success' => true, 'transfer' => $transfer];
    }

    /**
     * Get pending transfer approvals for user
     */
    public function getPendingTransferApprovals(int $userId): array
    {
        // Get transfers that need approval from stages the user manages
        $transfers = WeightTransfer::with(['order', 'orderMaterial.material', 'requester'])
            ->where('status', 'pending')
            ->whereHas('order.orderProcessings', function($q) use ($userId) {
                $q->where('assigned_to', $userId);
            })
            ->get();

        return $transfers->toArray();
    }

    /**
     * Get order weight balance report
     */
     public function getWeightBalanceReport(Order $order): array
     {
         $report = [
             'order_id' => $order->id,
             'order_number' => $order->order_number,
             'materials' => [],
             'stages' => [],
             'transfers' => [],
             'summary' => [
                 'total_requested' => 0,
                 'total_extracted' => 0,
                 'total_sorted' => 0,
                 'total_cut' => 0,
                 'total_delivered' => 0,
                 'total_waste' => 0,
                 'total_returned' => 0,
                 'total_transferred' => 0,
             ]
         ];

         // Materials balance
         foreach ($order->orderMaterials as $orderMaterial) {
             $materialReport = $orderMaterial->getWeightBalanceReport();
             $report['materials'][] = $materialReport;

             $report['summary']['total_requested'] += $materialReport['requested_weight'];
             $report['summary']['total_extracted'] += $materialReport['extracted_weight'];
             $report['summary']['total_sorted'] += $materialReport['sorted_weight'];
             $report['summary']['total_cut'] += $materialReport['cut_weight'];
             $report['summary']['total_delivered'] += $materialReport['delivered_weight'];
             $report['summary']['total_waste'] += $materialReport['total_waste_weight'];
             $report['summary']['total_returned'] += $materialReport['returned_weight'];
         }

         // Stages balance
         foreach ($order->orderProcessings as $processing) {
             $report['stages'][] = [
                 'stage_name' => $processing->workStage->name ?? 'Unknown',
                 'status' => $processing->status,
                 'weight_received' => $processing->weight_received,
                 'weight_transferred' => $processing->weight_transferred,
                 'weight_balance' => $processing->weight_balance,
                 'transfer_approved' => $processing->transfer_approved,
             ];
         }

         // Transfers
         foreach ($order->weightTransfers as $transfer) {
             $report['transfers'][] = [
                 'id' => $transfer->id,
                 'from_stage' => $transfer->from_stage,
                 'to_stage' => $transfer->to_stage,
                 'weight_transferred' => $transfer->weight_transferred,
                 'status' => $transfer->status,
                 'requested_by' => $transfer->requester->name ?? 'Unknown',
                 'approved_by' => $transfer->approver->name ?? null,
                 'approved_at' => $transfer->approved_at,
             ];
             $report['summary']['total_transferred'] += $transfer->weight_transferred;
         }

         // Calculate balance
         $inputTotal = $report['summary']['total_extracted'];
         $outputTotal = $report['summary']['total_delivered'] + $report['summary']['total_waste'] + $report['summary']['total_returned'];

         $report['summary']['is_balanced'] = abs($inputTotal - $outputTotal) < 0.01;
         $report['summary']['difference'] = $inputTotal - $outputTotal;

         return $report;
     }

     /**
      * Create OrderMaterial records from OrderItems
      */
     private function createOrderMaterialsFromOrderItems(Order $order): void
     {
         foreach ($order->orderItems as $orderItem) {
             $product = $orderItem->product;
 
             OrderMaterial::create([
                 'order_id' => $order->id,
                 'material_id' => $product->id,
                 'requested_weight' => $orderItem->quantity * $product->weight_per_unit,
                 'required_width' => $order->required_width,
                 'required_length' => $order->required_length,
                 'required_grammage' => $product->specifications['grammage'] ?? null,
                 'quality_grade' => $product->specifications['quality'] ?? null,
                 'status' => 'معلق',
             ]);
         }
     }
 
     /**
      * Validate stage transition - ensure weight transfers are approved
      */
     private function validateStageTransition(Order $order): bool
     {
         // Check if there are any pending weight transfers that need approval
         $pendingTransfers = $order->weightTransfers()
             ->where('status', 'pending')
             ->where('to_stage', $order->current_stage)
             ->exists();
 
         if ($pendingTransfers) {
             return false; // Cannot transition if there are pending transfers to this stage
         }
 
         return true;
     }
 
     /**
      * Find suitable rolls in warehouse that match order specifications
      */
     public function findSuitableRolls(Product $product, OrderMaterial $orderMaterial, float $requiredWeight): array
     {
         $requiredSpecs = [
             'width' => $orderMaterial->required_width,
             'grammage' => $orderMaterial->required_grammage,
             'quality' => $orderMaterial->quality_grade,
             'min_length' => $orderMaterial->required_length,
         ];
 
         return $this->materialSpecService->findSuitableRolls($product, $requiredSpecs, $requiredWeight)->toArray();
     }

     /**
      * Get roll specifications from stock
      */
     public function getRollSpecifications(Stock $stock): array
     {
         return $this->materialSpecService->getRollSpecifications($stock);
     }

     /**
      * Validate material specifications for order
      */
     public function validateMaterialSpecifications(Order $order): array
     {
         $validationResults = [];
 
         foreach ($order->orderMaterials as $orderMaterial) {
             $validation = $orderMaterial->validateRollSpecifications();
 
             $validationResults[] = [
                 'order_material_id' => $orderMaterial->id,
                 'product_name' => $orderMaterial->material->name,
                 'validation' => $validation,
                 'roll_summary' => $orderMaterial->getRollSpecificationSummary(),
             ];
         }
 
         $allValid = collect($validationResults)->every(function($result) {
             return $result['validation']['is_valid'];
         });
 
         return [
             'order_id' => $order->id,
             'is_valid' => $allValid,
             'materials' => $validationResults,
             'summary' => [
                 'total_materials' => count($validationResults),
                 'valid_materials' => collect($validationResults)->where('validation.is_valid', true)->count(),
                 'invalid_materials' => collect($validationResults)->where('validation.is_valid', false)->count(),
             ]
         ];
     }
 
     /**
      * Get transfer history and approval status for an order
      */
     public function getTransferHistory(Order $order): array
     {
         $transfers = $order->weightTransfers()
             ->with(['orderMaterial.material', 'requester', 'approver', 'approvals.approver'])
             ->orderBy('created_at', 'desc')
             ->get();
 
         return $transfers->map(function($transfer) {
             return [
                 'id' => $transfer->id,
                 'order_material' => [
                     'id' => $transfer->orderMaterial->id,
                     'product_name' => $transfer->orderMaterial->material->name,
                     'roll_number' => $transfer->roll_number,
                 ],
                 'transfer_details' => [
                     'from_stage' => $transfer->from_stage,
                     'to_stage' => $transfer->to_stage,
                     'weight' => $transfer->weight_transferred,
                     'status' => $transfer->status,
                     'requested_at' => $transfer->created_at,
                     'approved_at' => $transfer->approved_at,
                     'completed_at' => $transfer->transferred_at,
                 ],
                 'approval_status' => $transfer->getApprovalStatus(),
                 'approvals' => $transfer->approvals->map(function($approval) {
                     return [
                         'approver' => $approval->approver->name ?? 'Unknown',
                         'status' => $approval->approval_status,
                         'approved_at' => $approval->approved_at,
                         'notes' => $approval->approval_notes,
                     ];
                 }),
                 'material_specs' => $transfer->getMaterialSpecificationsSummary(),
             ];
         })->toArray();
     }
 
     /**
      * Get pending transfers requiring approval for a user
      */
     public function getPendingTransfersForApproval(int $userId): array
     {
         $transfers = WeightTransfer::with(['order', 'orderMaterial.material', 'requester'])
             ->where('status', 'pending')
             ->whereHas('approvals', function($q) use ($userId) {
                 $q->where('approver_id', $userId)->where('approval_status', 'pending');
             })
             ->get();
 
         return $transfers->map(function($transfer) {
             return [
                 'id' => $transfer->id,
                 'order_number' => $transfer->order->order_number,
                 'product_name' => $transfer->orderMaterial->material->name,
                 'from_stage' => $transfer->from_stage,
                 'to_stage' => $transfer->to_stage,
                 'weight' => $transfer->weight_transferred,
                 'requested_by' => $transfer->requester->name,
                 'requested_at' => $transfer->created_at,
                 'material_specs' => $transfer->getMaterialSpecificationsSummary(),
                 'notes' => $transfer->notes,
             ];
         })->toArray();
     }

     /**
      * Set material specifications for order materials
      */
     public function setOrderMaterialSpecifications(Order $order, array $specifications): array
     {
         $results = [];
 
         DB::beginTransaction();
         try {
             foreach ($specifications as $materialId => $specs) {
                 $orderMaterial = $order->orderMaterials()->find($materialId);
                 if (!$orderMaterial) continue;
 
                 $orderMaterial->setRollSpecifications($specs);
 
                 $results[] = [
                     'material_id' => $materialId,
                     'specifications' => $specs,
                     'success' => true,
                 ];
             }
 
             DB::commit();
             return ['success' => true, 'results' => $results];
 
         } catch (\Exception $e) {
             DB::rollback();
             return ['success' => false, 'error' => $e->getMessage()];
         }
     }
 
     /**
      * Perform sorting operation for an order (wrapper for SortingService)
      */
     public function performSorting(Order $order, User $user, array $sortingData): array
     {
         return $this->sortingService->performSorting($order, $user, $sortingData);
     }
 
     /**
      * Approve sorting results
      */
     public function approveSorting(OrderProcessing $processing, User $user, string $notes = null): array
     {
         return $this->sortingService->approveSorting($processing, $user, $notes);
     }
 
     /**
      * Transfer sorted materials to destination warehouse with grouped approval workflow
      */
     public function transferToDestination(OrderProcessing $processing, User $user, int $destinationWarehouseId, string $destinationType = 'cutting_warehouse'): array
     {
         $result = $this->sortingService->transferToDestination($processing, $user, $destinationWarehouseId, $destinationType);

         if ($result['success']) {
             // Create grouped transfer requests after sorting completion
             $this->createGroupedTransferRequests($processing, $user, $destinationWarehouseId, $destinationType);
         }

         return $result;
     }

     /**
      * Create grouped transfer requests after sorting completion
      */
     private function createGroupedTransferRequests(OrderProcessing $processing, User $user, int $destinationWarehouseId, string $destinationType): void
     {
         $order = $processing->order;
         $sortingResults = $processing->sortingResults;

         if ($sortingResults->isEmpty()) {
             return;
         }

         // Generate unique transfer group ID
         $transferGroupId = 'SORT_' . $order->id . '_' . now()->format('Ymd_His');

         // Find source and destination warehouses
         $sourceWarehouse = $processing->fromWarehouse ?? $order->warehouse;
         $destinationWarehouse = Warehouse::find($destinationWarehouseId);

         if (!$sourceWarehouse || !$destinationWarehouse) {
             Log::error('Warehouses not found for transfer creation', [
                 'order_id' => $order->id,
                 'source_id' => $sourceWarehouse?->id,
                 'destination_id' => $destinationWarehouseId
             ]);
             return;
         }

         DB::beginTransaction();
         try {
             foreach ($sortingResults as $sortingResult) {
                 $orderMaterial = $sortingResult->orderMaterial;

                 // Create transfer for sorted material (roll 1)
                 if ($sortingResult->roll1_weight > 0) {
                     $transfer = WeightTransfer::create([
                         'order_id' => $order->id,
                         'order_material_id' => $orderMaterial->id,
                         'from_stage' => 'فرز',
                         'to_stage' => 'قص',
                         'weight_transferred' => $sortingResult->roll1_weight,
                         'transfer_type' => 'sorted_material_transfer',
                         'requested_by' => $user->id,
                         'status' => 'pending',
                         'notes' => 'Transfer of sorted material (Roll 1) after sorting completion',
                         // Material specifications
                         'roll_number' => $sortingResult->roll_number ?? 'SORT_' . $sortingResult->id . '_R1',
                         'material_width' => $sortingResult->roll1_width,
                         'material_length' => $sortingResult->original_length ?? $orderMaterial->required_length,
                         'material_grammage' => $orderMaterial->required_grammage,
                         'quality_grade' => $orderMaterial->quality_grade,
                         'batch_number' => $orderMaterial->batch_number,
                         // Grouped transfer fields
                         'transfer_group_id' => $transferGroupId,
                         'transfer_category' => 'sorted_material',
                         'source_warehouse_id' => $sourceWarehouse->id,
                         'destination_warehouse_id' => $destinationWarehouseId,
                         'requires_sequential_approval' => true,
                         'current_approval_level' => 1,
                     ]);

                     // Create sequential approvals
                     $transfer->createSequentialApprovals();
                 }

                 // Create transfer for remaining roll (roll 2)
                 if ($sortingResult->roll2_weight > 0) {
                     $transfer = WeightTransfer::create([
                         'order_id' => $order->id,
                         'order_material_id' => $orderMaterial->id,
                         'from_stage' => 'فرز',
                         'to_stage' => 'قص',
                         'weight_transferred' => $sortingResult->roll2_weight,
                         'transfer_type' => 'remaining_roll_transfer',
                         'requested_by' => $user->id,
                         'status' => 'pending',
                         'notes' => 'Transfer of remaining roll (Roll 2) after sorting completion',
                         // Material specifications
                         'roll_number' => $sortingResult->roll_number ? $sortingResult->roll_number . '_R2' : 'SORT_' . $sortingResult->id . '_R2',
                         'material_width' => $sortingResult->roll2_width,
                         'material_length' => $sortingResult->original_length ?? $orderMaterial->required_length,
                         'material_grammage' => $orderMaterial->required_grammage,
                         'quality_grade' => $orderMaterial->quality_grade,
                         'batch_number' => $orderMaterial->batch_number,
                         // Grouped transfer fields
                         'transfer_group_id' => $transferGroupId,
                         'transfer_category' => 'remaining_roll',
                         'source_warehouse_id' => $sourceWarehouse->id,
                         'destination_warehouse_id' => $destinationWarehouseId,
                         'requires_sequential_approval' => true,
                         'current_approval_level' => 1,
                     ]);

                     // Create sequential approvals
                     $transfer->createSequentialApprovals();
                 }

                 // Create waste transfer (auto-approved)
                 if ($sortingResult->waste_weight > 0) {
                     $transfer = WeightTransfer::create([
                         'order_id' => $order->id,
                         'order_material_id' => $orderMaterial->id,
                         'from_stage' => 'فرز',
                         'to_stage' => 'waste',
                         'weight_transferred' => $sortingResult->waste_weight,
                         'transfer_type' => 'waste_transfer',
                         'requested_by' => $user->id,
                         'status' => 'approved', // Auto-approved
                         'notes' => 'Waste transfer after sorting: ' . $sortingResult->waste_reason,
                         // Material specifications
                         'roll_number' => 'WASTE_' . $sortingResult->id,
                         'material_width' => $sortingResult->original_width,
                         'material_length' => $sortingResult->original_length ?? $orderMaterial->required_length,
                         'material_grammage' => $orderMaterial->required_grammage,
                         'quality_grade' => $orderMaterial->quality_grade,
                         'batch_number' => $orderMaterial->batch_number,
                         // Grouped transfer fields
                         'transfer_group_id' => $transferGroupId,
                         'transfer_category' => 'waste',
                         'source_warehouse_id' => $sourceWarehouse->id,
                         'destination_warehouse_id' => null, // Waste doesn't go to a warehouse
                         'requires_sequential_approval' => false, // Auto-approved
                         'current_approval_level' => 1,
                     ]);

                     // Create auto-approval record
                     $transfer->approvals()->create([
                         'approver_id' => 1, // System user
                         'approval_status' => 'approved',
                         'approval_level' => 'auto_approved',
                         'approval_sequence' => 1,
                         'is_final_approval' => true,
                         'approved_at' => now(),
                         'approval_notes' => 'Auto-approved waste transfer',
                     ]);
                 }
             }

             DB::commit();

             Log::info('Grouped transfer requests created after sorting', [
                 'order_id' => $order->id,
                 'transfer_group_id' => $transferGroupId,
                 'sorting_results_count' => $sortingResults->count()
             ]);

         } catch (\Exception $e) {
             DB::rollBack();
             Log::error('Failed to create grouped transfer requests', [
                 'order_id' => $order->id,
                 'error' => $e->getMessage()
             ]);
             throw $e;
         }
     }
 
     /**
      * Get sorting summary for an order
      */
     public function getSortingSummary(Order $order): array
     {
         return $this->sortingService->getSortingSummary($order);
     }
 
     /**
      * Check if user can perform sorting operations
      */
     public function canUserPerformSorting(User $user, OrderProcessing $processing): bool
     {
         return $this->sortingService->canUserPerformSorting($user, $processing);
     }
 
     /**
      * Check if user can approve sorting
      */
     public function canUserApproveSorting(User $user, OrderProcessing $processing): bool
     {
         return $this->sortingService->canUserApproveSorting($user, $processing);
     }
 
     /**
      * Get pending sorting approvals for user
      */
     public function getPendingSortingApprovals(int $userId): array
     {
         // Get sorting stages that need approval and user can approve
         $pendingSortings = \App\Models\OrderProcessing::with(['order', 'workStage'])
             ->whereHas('workStage', function($q) {
                 $q->where('name_en', 'Sorting');
             })
             ->where('status', 'completed')
             ->where('sorting_approved', false)
             ->whereHas('order', function($q) use ($userId) {
                 $q->whereHas('orderProcessings', function($subQ) use ($userId) {
                     $subQ->where('assigned_to', $userId);
                 });
             })
             ->get();

         return $pendingSortings->map(function($processing) {
             return [
                 'id' => $processing->id,
                 'order_number' => $processing->order->order_number,
                 'stage_name' => $processing->workStage->name,
                 'roll1_weight' => $processing->roll1_weight,
                 'roll2_weight' => $processing->roll2_weight,
                 'waste_weight' => $processing->sorting_waste_weight,
                 'total_input' => $processing->weight_received,
                 'is_balanced' => $processing->isSortingWeightBalanced(),
             ];
         })->toArray();
     }

     /**
      * Record cutting results for an order processing
      */
     public function recordCuttingResults(OrderProcessing $processing, User $user, array $cuttingData): array
     {
         $results = [];

         DB::beginTransaction();
         try {
             foreach ($cuttingData as $orderMaterialId => $data) {
                 $orderMaterial = $processing->order->orderMaterials()->find($orderMaterialId);
                 if (!$orderMaterial || $orderMaterial->status !== 'مستخرج') continue;

                 // Create cutting result record
                 $cuttingResult = CuttingResult::create([
                     'order_id' => $processing->order_id,
                     'order_material_id' => $orderMaterialId,
                     'order_processing_id' => $processing->id,
                     'input_weight' => $data['input_weight'],
                     'cut_weight' => $data['cut_weight'],
                     'waste_weight' => $data['waste_weight'] ?? 0,
                     'remaining_weight' => $data['remaining_weight'] ?? 0,
                     'required_length' => $data['required_length'] ?? $orderMaterial->required_length,
                     'required_width' => $data['required_width'] ?? $orderMaterial->required_width,
                     'actual_cut_length' => $data['actual_cut_length'] ?? null,
                     'actual_cut_width' => $data['actual_cut_width'] ?? null,
                     'roll_number' => $data['roll_number'] ?? $orderMaterial->roll_number,
                     'material_width' => $data['material_width'] ?? $orderMaterial->actual_width,
                     'material_grammage' => $data['material_grammage'] ?? $orderMaterial->required_grammage,
                     'quality_grade' => $data['quality_grade'] ?? $orderMaterial->quality_grade,
                     'batch_number' => $data['batch_number'] ?? $orderMaterial->batch_number,
                     'pieces_cut' => $data['pieces_cut'] ?? 0,
                     'cutting_notes' => $data['cutting_notes'] ?? null,
                     'cutting_machine' => $data['cutting_machine'] ?? null,
                     'operator_name' => $data['operator_name'] ?? $user->name,
                     'performed_by' => $user->id,
                     'quality_passed' => $data['quality_passed'] ?? false,
                     'quality_notes' => $data['quality_notes'] ?? null,
                     'quality_measurements' => $data['quality_measurements'] ?? null,
                     'status' => 'completed',
                     'cutting_completed_at' => now(),
                 ]);

                 // Validate cutting result
                 $validation = $cuttingResult->validateData();
                 if (!$validation['is_valid']) {
                     throw new \Exception('Cutting result validation failed: ' . implode(', ', $validation['errors']));
                 }

                 $results[] = [
                     'cutting_result_id' => $cuttingResult->id,
                     'order_material_id' => $orderMaterialId,
                     'input_weight' => $cuttingResult->input_weight,
                     'cut_weight' => $cuttingResult->cut_weight,
                     'waste_weight' => $cuttingResult->waste_weight,
                     'remaining_weight' => $cuttingResult->remaining_weight,
                     'pieces_cut' => $cuttingResult->pieces_cut,
                     'validation' => $validation,
                     'success' => true,
                 ];
             }

             // Update processing stage
             $processing->status = 'completed';
             $processing->completed_at = now();
             $processing->save();

             DB::commit();
             return ['success' => true, 'results' => $results];

         } catch (\Exception $e) {
             DB::rollBack();
             Log::error('Failed to record cutting results', [
                 'processing_id' => $processing->id,
                 'error' => $e->getMessage()
             ]);
             return ['success' => false, 'error' => $e->getMessage()];
         }
     }

     /**
      * Approve cutting results and create output transfers
      */
     public function approveCuttingResults(OrderProcessing $processing, User $user, string $notes = null): array
     {
         DB::beginTransaction();
         try {
             $cuttingResults = $processing->cuttingResults()->where('status', 'completed')->get();

             if ($cuttingResults->isEmpty()) {
                 throw new \Exception('No cutting results found to approve');
             }

             $approvedResults = [];
             foreach ($cuttingResults as $cuttingResult) {
                 // Approve the cutting result
                 $cuttingResult->approve($user->id, $notes);
                 $approvedResults[] = $cuttingResult;

                 // Create output transfers after approval
                 $this->createCuttingOutputTransfers($cuttingResult, $user);
             }

             // Mark processing as approved
             $processing->cutting_approved = true;
             $processing->cutting_approved_at = now();
             $processing->cutting_approved_by = $user->id;
             $processing->save();

             DB::commit();
             return [
                 'success' => true,
                 'approved_results' => count($approvedResults),
                 'transfers_created' => true
             ];

         } catch (\Exception $e) {
             DB::rollBack();
             Log::error('Failed to approve cutting results', [
                 'processing_id' => $processing->id,
                 'error' => $e->getMessage()
             ]);
             return ['success' => false, 'error' => $e->getMessage()];
         }
     }

     /**
      * Create output transfers from approved cutting results
      */
     private function createCuttingOutputTransfers(CuttingResult $cuttingResult, User $user): void
     {
         $order = $cuttingResult->order;
         $transferGroupId = 'CUT_' . $order->id . '_' . $cuttingResult->id . '_' . now()->format('Ymd_His');

         // Find destination warehouse (packaging warehouse)
         $destinationWarehouse = Warehouse::where('type', 'مستودع_تعبئة')->first();
         if (!$destinationWarehouse) {
             Log::warning('Packaging warehouse not found for cutting transfers', [
                 'cutting_result_id' => $cuttingResult->id
             ]);
             return;
         }

         $transfers = [];

         // Create transfer for cut material
         if ($cuttingResult->cut_weight > 0) {
             $transfer = WeightTransfer::create([
                 'order_id' => $order->id,
                 'order_material_id' => $cuttingResult->order_material_id,
                 'cutting_result_id' => $cuttingResult->id,
                 'from_stage' => 'قص',
                 'to_stage' => 'تعبئة',
                 'weight_transferred' => $cuttingResult->cut_weight,
                 'transfer_type' => 'cutting_output',
                 'requested_by' => $user->id,
                 'status' => 'pending',
                 'notes' => 'Transfer of cut material to packaging after cutting approval',
                 // Material specifications
                 'roll_number' => $cuttingResult->roll_number . '_CUT',
                 'material_width' => $cuttingResult->actual_cut_width ?? $cuttingResult->required_width,
                 'material_length' => $cuttingResult->actual_cut_length ?? $cuttingResult->required_length,
                 'material_grammage' => $cuttingResult->material_grammage,
                 'quality_grade' => $cuttingResult->quality_grade,
                 'batch_number' => $cuttingResult->batch_number,
                 // Cutting-specific fields
                 'pieces_transferred' => $cuttingResult->pieces_cut,
                 'cutting_quality_verified' => $cuttingResult->quality_passed,
                 // Grouped transfer fields
                 'transfer_group_id' => $transferGroupId,
                 'transfer_category' => 'cut_material',
                 'source_warehouse_id' => $cuttingResult->orderProcessing->to_warehouse_id,
                 'destination_warehouse_id' => $destinationWarehouse->id,
                 'requires_sequential_approval' => true,
                 'current_approval_level' => 1,
             ]);

             $transfer->createSequentialApprovals();
             $transfers[] = $transfer;
         }

         // Create transfer for remaining material
         if ($cuttingResult->remaining_weight > 0) {
             $transfer = WeightTransfer::create([
                 'order_id' => $order->id,
                 'order_material_id' => $cuttingResult->order_material_id,
                 'cutting_result_id' => $cuttingResult->id,
                 'from_stage' => 'قص',
                 'to_stage' => 'مخلفات',
                 'weight_transferred' => $cuttingResult->remaining_weight,
                 'transfer_type' => 'cutting_remainder',
                 'requested_by' => $user->id,
                 'status' => 'pending',
                 'notes' => 'Transfer of remaining material after cutting',
                 // Material specifications
                 'roll_number' => $cuttingResult->roll_number . '_REM',
                 'material_width' => $cuttingResult->material_width,
                 'material_length' => $cuttingResult->material_length ?? $cuttingResult->required_length,
                 'material_grammage' => $cuttingResult->material_grammage,
                 'quality_grade' => $cuttingResult->quality_grade,
                 'batch_number' => $cuttingResult->batch_number,
                 // Cutting-specific fields
                 'pieces_transferred' => 0,
                 'cutting_quality_verified' => false,
                 // Grouped transfer fields
                 'transfer_group_id' => $transferGroupId,
                 'transfer_category' => 'cutting_remainder',
                 'source_warehouse_id' => $cuttingResult->orderProcessing->to_warehouse_id,
                 'destination_warehouse_id' => null, // Remainder goes to waste
                 'requires_sequential_approval' => false, // Auto-approved
                 'current_approval_level' => 1,
             ]);

             // Auto-approve remainder transfers
             $transfer->approvals()->create([
                 'approver_id' => 1, // System user
                 'approval_status' => 'approved',
                 'approval_level' => 'auto_approved',
                 'approval_sequence' => 1,
                 'is_final_approval' => true,
                 'approved_at' => now(),
                 'approval_notes' => 'Auto-approved cutting remainder transfer',
             ]);

             $transfer->status = 'approved';
             $transfer->approved_by = 1;
             $transfer->approved_at = now();
             $transfer->save();

             $transfers[] = $transfer;
         }

         // Create transfer for cutting waste
         if ($cuttingResult->waste_weight > 0) {
             $transfer = WeightTransfer::create([
                 'order_id' => $order->id,
                 'order_material_id' => $cuttingResult->order_material_id,
                 'cutting_result_id' => $cuttingResult->id,
                 'from_stage' => 'قص',
                 'to_stage' => 'waste',
                 'weight_transferred' => $cuttingResult->waste_weight,
                 'transfer_type' => 'cutting_waste',
                 'requested_by' => $user->id,
                 'status' => 'approved', // Auto-approved
                 'notes' => 'Cutting waste transfer: ' . $cuttingResult->cutting_notes,
                 // Material specifications
                 'roll_number' => 'WASTE_' . $cuttingResult->id,
                 'material_width' => $cuttingResult->material_width,
                 'material_length' => $cuttingResult->material_length ?? $cuttingResult->required_length,
                 'material_grammage' => $cuttingResult->material_grammage,
                 'quality_grade' => $cuttingResult->quality_grade,
                 'batch_number' => $cuttingResult->batch_number,
                 // Cutting-specific fields
                 'pieces_transferred' => 0,
                 'cutting_quality_verified' => false,
                 // Grouped transfer fields
                 'transfer_group_id' => $transferGroupId,
                 'transfer_category' => 'cutting_waste',
                 'source_warehouse_id' => $cuttingResult->orderProcessing->to_warehouse_id,
                 'destination_warehouse_id' => null, // Waste doesn't go to warehouse
                 'requires_sequential_approval' => false, // Auto-approved
                 'current_approval_level' => 1,
             ]);

             // Auto-approve waste transfers
             $transfer->approvals()->create([
                 'approver_id' => 1, // System user
                 'approval_status' => 'approved',
                 'approval_level' => 'auto_approved',
                 'approval_sequence' => 1,
                 'is_final_approval' => true,
                 'approved_at' => now(),
                 'approval_notes' => 'Auto-approved cutting waste transfer',
             ]);

             $transfer->status = 'approved';
             $transfer->approved_by = 1;
             $transfer->approved_at = now();
             $transfer->save();

             $transfers[] = $transfer;
         }

         // Mark cutting result as having transfers created
         $cuttingResult->transfers_created = true;
         $cuttingResult->save();

         Log::info('Cutting output transfers created', [
             'cutting_result_id' => $cuttingResult->id,
             'transfer_group_id' => $transferGroupId,
             'transfers_count' => count($transfers)
         ]);
     }

     /**
      * Get cutting results for an order processing
      */
     public function getCuttingResults(OrderProcessing $processing): array
     {
         $cuttingResults = $processing->cuttingResults()
             ->with(['orderMaterial.material', 'performer', 'approver'])
             ->get();

         return $cuttingResults->map(function($result) {
             return [
                 'id' => $result->id,
                 'order_material' => [
                     'id' => $result->orderMaterial->id,
                     'product_name' => $result->orderMaterial->material->name,
                     'roll_number' => $result->roll_number,
                 ],
                 'cutting_details' => [
                     'input_weight' => $result->input_weight,
                     'cut_weight' => $result->cut_weight,
                     'waste_weight' => $result->waste_weight,
                     'remaining_weight' => $result->remaining_weight,
                     'pieces_cut' => $result->pieces_cut,
                     'yield_percentage' => $result->getWeightBalance()['yield_percentage'],
                 ],
                 'specifications' => $result->getMaterialSpecifications(),
                 'quality' => [
                     'passed' => $result->quality_passed,
                     'notes' => $result->quality_notes,
                     'measurements' => $result->quality_measurements,
                 ],
                 'status' => $result->status,
                 'performed_by' => $result->performer->name ?? 'Unknown',
                 'approved_by' => $result->approver->name ?? null,
                 'cutting_completed_at' => $result->cutting_completed_at,
                 'approved_at' => $result->approved_at,
                 'transfers_created' => $result->transfers_created,
             ];
         })->toArray();
     }

     /**
      * Get pending cutting approvals for user
      */
     public function getPendingCuttingApprovals(int $userId): array
     {
         // Get cutting stages that need approval and user can approve
         $pendingCuttings = OrderProcessing::with(['order', 'workStage'])
             ->whereHas('workStage', function($q) {
                 $q->where('name_en', 'Cutting');
             })
             ->where('status', 'completed')
             ->where('cutting_approved', false)
             ->whereHas('order', function($q) use ($userId) {
                 $q->whereHas('orderProcessings', function($subQ) use ($userId) {
                     $subQ->where('assigned_to', $userId);
                 });
             })
             ->get();

         return $pendingCuttings->map(function($processing) {
             $cuttingResults = $processing->cuttingResults;
             $totalInput = $cuttingResults->sum('input_weight');
             $totalCut = $cuttingResults->sum('cut_weight');
             $totalWaste = $cuttingResults->sum('waste_weight');

             return [
                 'id' => $processing->id,
                 'order_number' => $processing->order->order_number,
                 'stage_name' => $processing->workStage->name,
                 'cutting_results_count' => $cuttingResults->count(),
                 'total_input_weight' => $totalInput,
                 'total_cut_weight' => $totalCut,
                 'total_waste_weight' => $totalWaste,
                 'yield_percentage' => $totalInput > 0 ? ($totalCut / $totalInput) * 100 : 0,
                 'quality_passed_count' => $cuttingResults->where('quality_passed', true)->count(),
                 'is_weight_balanced' => $cuttingResults->every(function($result) {
                     return $result->getWeightBalance()['is_balanced'];
                 }),
             ];
         })->toArray();
     }

     /**
      * Check if user can perform cutting operations
      */
     public function canUserPerformCutting(User $user, OrderProcessing $processing): bool
     {
         // Check if user has cutting role
         if (!$user->hasRole('مسؤول_قصاصة')) {
             return false;
         }

         // Check if processing is assigned to user
         return $processing->assigned_to === $user->id;
     }

     /**
      * Check if user can approve cutting results
      */
     public function canUserApproveCutting(User $user, OrderProcessing $processing): bool
     {
         // Check if user has cutting approval role
         if (!$user->hasRole(['مسؤول_قصاصة', 'مدير_شامل'])) {
             return false;
         }

         // Check if processing is in user's approval chain
         return $processing->order->orderProcessings()
             ->where('assigned_to', $user->id)
             ->exists();
     }
 }