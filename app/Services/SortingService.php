<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderMaterial;
use App\Models\OrderProcessing;
use App\Models\SortingResult;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class SortingService
{
    /**
     * Validate sorting data before processing
     */
    public function validateSortingData(array $sortingData): array
    {
        $errors = [];

        foreach ($sortingData as $index => $data) {
            if (!isset($data['order_material_id'])) {
                $errors[] = "Missing order_material_id at index {$index}";
                continue;
            }

            // Validate order material exists
            $orderMaterial = OrderMaterial::find($data['order_material_id']);
            if (!$orderMaterial) {
                $errors[] = "Order material not found for ID {$data['order_material_id']}";
                continue;
            }

            $originalWeight = $data['original_weight'] ?? 0;
            $roll1Weight = $data['roll1_weight'] ?? 0;
            $roll2Weight = $data['roll2_weight'] ?? 0;
            $wasteWeight = $data['waste_weight'] ?? 0;

            // Validate weights are numeric and positive
            if (!is_numeric($originalWeight) || $originalWeight <= 0) {
                $errors[] = "Invalid original weight for material {$data['order_material_id']}";
            }

            if (!is_numeric($roll1Weight) || $roll1Weight < 0) {
                $errors[] = "Invalid roll 1 weight for material {$data['order_material_id']}";
            }

            if (!is_numeric($roll2Weight) || $roll2Weight < 0) {
                $errors[] = "Invalid roll 2 weight for material {$data['order_material_id']}";
            }

            if (!is_numeric($wasteWeight) || $wasteWeight < 0) {
                $errors[] = "Invalid waste weight for material {$data['order_material_id']}";
            }

            $totalOutput = $roll1Weight + $roll2Weight + $wasteWeight;

            if (abs($originalWeight - $totalOutput) > 0.01) {
                $errors[] = "Weight imbalance for material {$data['order_material_id']}: input {$originalWeight}, output {$totalOutput}";
            }

            // Validate that at least one roll has weight
            if ($roll1Weight <= 0 && $roll2Weight <= 0) {
                $errors[] = "At least one roll must have weight greater than 0 for material {$data['order_material_id']}";
            }

            // Validate width specifications
            if (isset($data['roll1_width']) && (!is_numeric($data['roll1_width']) || $data['roll1_width'] <= 0)) {
                $errors[] = "Invalid roll 1 width for material {$data['order_material_id']}";
            }

            if (isset($data['roll2_width']) && (!is_numeric($data['roll2_width']) || $data['roll2_width'] <= 0)) {
                $errors[] = "Invalid roll 2 width for material {$data['order_material_id']}";
            }

            // Validate locations if weights are specified
            if ($roll1Weight > 0 && empty($data['roll1_location'])) {
                $errors[] = "Roll 1 location is required when weight > 0 for material {$data['order_material_id']}";
            }

            if ($roll2Weight > 0 && empty($data['roll2_location'])) {
                $errors[] = "Roll 2 location is required when weight > 0 for material {$data['order_material_id']}";
            }

            // Validate waste reason if waste exists
            if ($wasteWeight > 0 && empty($data['waste_reason'])) {
                $errors[] = "Waste reason is required when waste weight > 0 for material {$data['order_material_id']}";
            }
        }

        return $errors;
    }

    /**
     * Perform sorting operation for an order
     */
    public function performSorting(Order $order, User $user, array $sortingData): array
    {
        // Validate data first
        $validationErrors = $this->validateSortingData($sortingData);
        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors];
        }

        DB::beginTransaction();
        try {
            // Get sorting stage processing
            $sortingProcessing = $order->orderProcessings()
                ->whereHas('workStage', function($q) {
                    $q->where('name_en', 'Sorting');
                })
                ->first();

            if (!$sortingProcessing) {
                throw new \Exception('Sorting stage not found for this order');
            }

            // Record sorting results
            $sortingProcessing->recordSortingResults($sortingData, $user);

            // Update order stage status
            $sortingProcessing->status = 'completed';
            $sortingProcessing->completed_at = now();
            $sortingProcessing->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Sorting completed successfully',
                'sorting_processing_id' => $sortingProcessing->id
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Approve sorting results
     */
    public function approveSorting(OrderProcessing $processing, User $user, string $notes = null): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'error' => 'Not a sorting stage'];
        }

        if (!$processing->canUserApproveSorting($user)) {
            return ['success' => false, 'error' => 'User not authorized to approve sorting'];
        }

        if (!$processing->isSortingWeightBalanced()) {
            return ['success' => false, 'error' => 'Weight balance not correct'];
        }

        if ($processing->approveSorting($user->id, $notes)) {
            return ['success' => true, 'message' => 'Sorting approved successfully'];
        }

        return ['success' => false, 'error' => 'Failed to approve sorting'];
    }

    /**
     * Transfer sorted materials to destination warehouse
     */
    public function transferToDestination(OrderProcessing $processing, User $user, int $destinationWarehouseId, string $destinationType = 'cutting_warehouse'): array
    {
        if (!$processing->isSortingApproved()) {
            return ['success' => false, 'error' => 'Sorting must be approved before transfer'];
        }

        // Validate destination warehouse
        $warehouse = Warehouse::find($destinationWarehouseId);
        if (!$warehouse) {
            return ['success' => false, 'error' => 'Destination warehouse not found'];
        }

        DB::beginTransaction();
        try {
            // Transfer roll 1 (customer specifications)
            if ($processing->roll1_weight > 0) {
                $this->transferRollToWarehouse(
                    $processing,
                    $processing->roll1_weight,
                    $destinationWarehouseId,
                    $user,
                    'roll1'
                );
            }

            // Transfer roll 2 (remaining material)
            if ($processing->roll2_weight > 0) {
                $this->transferRollToWarehouse(
                    $processing,
                    $processing->roll2_weight,
                    $destinationWarehouseId,
                    $user,
                    'roll2'
                );
            }

            // Complete the transfer
            $processing->completePostSortingTransfer($destinationWarehouseId, $user);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Materials transferred successfully',
                'destination' => $destinationType,
                'warehouse' => $warehouse->name
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transfer individual roll to warehouse
     */
    private function transferRollToWarehouse(OrderProcessing $processing, float $weight, int $warehouseId, User $user, string $rollType): void
    {
        // Find the appropriate stock in sorting warehouse
        $sortingWarehouse = Warehouse::where('type', 'sorting')->first();
        if (!$sortingWarehouse) {
            throw new \Exception('Sorting warehouse not found');
        }

        // This would typically involve updating stock records
        // For now, we'll just log the transfer
        // In a real implementation, you'd update Stock model records
    }

    /**
     * Get sorting summary for an order
     */
    public function getSortingSummary(Order $order): array
    {
        $sortingProcessing = $order->orderProcessings()
            ->whereHas('workStage', function($q) {
                $q->where('name_en', 'Sorting');
            })
            ->with('sortingResults')
            ->first();

        if (!$sortingProcessing) {
            return ['error' => 'No sorting stage found for this order'];
        }

        return [
            'processing_id' => $sortingProcessing->id,
            'status' => $sortingProcessing->status,
            'approved' => $sortingProcessing->sorting_approved,
            'approved_at' => $sortingProcessing->sorting_approved_at,
            'approved_by' => $sortingProcessing->sortingApprover?->name,
            'total_input_weight' => $sortingProcessing->weight_received,
            'roll1_total_weight' => $sortingProcessing->roll1_weight,
            'roll1_avg_width' => $sortingProcessing->roll1_width,
            'roll2_total_weight' => $sortingProcessing->roll2_weight,
            'roll2_avg_width' => $sortingProcessing->roll2_width,
            'total_waste' => $sortingProcessing->sorting_waste_weight,
            'total_output' => $sortingProcessing->total_sorted_weight,
            'weight_balanced' => $sortingProcessing->isSortingWeightBalanced(),
            'transfer_completed' => $sortingProcessing->transfer_completed,
            'destination' => $sortingProcessing->post_sorting_destination,
            'destination_warehouse' => $sortingProcessing->destinationWarehouse?->name,
            'results' => $sortingProcessing->sortingResults->map(function($result) {
                return [
                    'material_id' => $result->order_material_id,
                    'product_name' => $result->orderMaterial->material->name,
                    'original_weight' => $result->original_weight,
                    'original_width' => $result->original_width,
                    'roll1_weight' => $result->roll1_weight,
                    'roll1_width' => $result->roll1_width,
                    'roll1_location' => $result->roll1_location,
                    'roll2_weight' => $result->roll2_weight,
                    'roll2_width' => $result->roll2_width,
                    'roll2_location' => $result->roll2_location,
                    'waste_weight' => $result->waste_weight,
                    'waste_reason' => $result->waste_reason,
                    'sorted_by' => $result->sorter->name,
                    'sorted_at' => $result->sorted_at,
                    'validated' => $result->weight_validated,
                ];
            })
        ];
    }

    /**
     * Check if user can perform sorting operations
     */
    public function canUserPerformSorting(User $user, OrderProcessing $processing): bool
    {
        return $processing->canUserPerformSorting($user);
    }

    /**
     * Check if user can approve sorting
     */
    public function canUserApproveSorting(User $user, OrderProcessing $processing): bool
    {
        return $processing->canUserApproveSorting($user);
    }

    /**
     * Approve received weight for sorting stage
     */
    public function approveReceivedWeight(OrderProcessing $processing, User $user, float $approvedWeight, string $notes = null): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'error' => 'Not a sorting stage'];
        }

        if (!$user->hasRole('مسؤول_مستودع')) {
            return ['success' => false, 'error' => 'User not authorized to approve received weight'];
        }

        if ($processing->weight_received <= 0) {
            return ['success' => false, 'error' => 'No weight received to approve'];
        }

        // Validate approved weight matches received weight
        if (abs($approvedWeight - $processing->weight_received) > 0.01) {
            return ['success' => false, 'error' => 'Approved weight must match received weight'];
        }

        DB::beginTransaction();
        try {
            // Update processing with approval
            $processing->weight_received_approved = true;
            $processing->weight_received_approved_by = $user->id;
            $processing->weight_received_approved_at = now();
            $processing->weight_received_notes = $notes;
            $processing->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Received weight approved successfully',
                'approved_weight' => $approvedWeight
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if received weight can be approved
     */
    public function canApproveReceivedWeight(User $user, OrderProcessing $processing): bool
    {
        return $processing->isSortingStage() &&
               $user->hasRole('مسؤول_مستودع') &&
               $processing->weight_received > 0 &&
               !$processing->weight_received_approved;
    }

    /**
     * Convert roll to two rolls with waste calculation
     */
    public function convertRollToRolls(OrderProcessing $processing, array $conversionData, User $user): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'error' => 'Not a sorting stage'];
        }

        if (!$processing->weight_received_approved) {
            return ['success' => false, 'error' => 'Received weight must be approved before conversion'];
        }

        // Validate conversion data
        $validation = $this->validateRollConversionData($conversionData);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        DB::beginTransaction();
        try {
            $totalInputWeight = collect($conversionData)->sum('original_weight');
            $totalOutputWeight = collect($conversionData)->sum(function($data) {
                return $data['roll1_weight'] + $data['roll2_weight'] + $data['waste_weight'];
            });

            // Check weight balance
            if (abs($totalInputWeight - $totalOutputWeight) > 0.01) {
                return ['success' => false, 'error' => 'Weight imbalance in conversion'];
            }

            // Record conversion results
            $processing->recordRollConversion($conversionData, $user);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Roll conversion completed successfully',
                'total_input' => $totalInputWeight,
                'total_output' => $totalOutputWeight,
                'conversion_count' => count($conversionData)
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate roll conversion data
     */
    private function validateRollConversionData(array $conversionData): array
    {
        $errors = [];

        foreach ($conversionData as $index => $data) {
            // Validate required fields
            if (!isset($data['original_weight']) || !is_numeric($data['original_weight']) || $data['original_weight'] <= 0) {
                $errors[] = "Invalid or missing original weight at index {$index}";
            }

            if (!isset($data['roll1_weight']) || !is_numeric($data['roll1_weight']) || $data['roll1_weight'] < 0) {
                $errors[] = "Invalid roll 1 weight at index {$index}";
            }

            if (!isset($data['roll2_weight']) || !is_numeric($data['roll2_weight']) || $data['roll2_weight'] < 0) {
                $errors[] = "Invalid roll 2 weight at index {$index}";
            }

            if (!isset($data['waste_weight']) || !is_numeric($data['waste_weight']) || $data['waste_weight'] < 0) {
                $errors[] = "Invalid waste weight at index {$index}";
            }

            // Validate weight balance
            $totalOutput = ($data['roll1_weight'] ?? 0) + ($data['roll2_weight'] ?? 0) + ($data['waste_weight'] ?? 0);
            if (abs(($data['original_weight'] ?? 0) - $totalOutput) > 0.01) {
                $errors[] = "Weight imbalance for conversion at index {$index}";
            }

            // Validate that at least one roll has weight
            if (($data['roll1_weight'] ?? 0) <= 0 && ($data['roll2_weight'] ?? 0) <= 0) {
                $errors[] = "At least one roll must have weight > 0 at index {$index}";
            }

            // Validate width if provided
            if (isset($data['roll1_width']) && (!is_numeric($data['roll1_width']) || $data['roll1_width'] <= 0)) {
                $errors[] = "Invalid roll 1 width at index {$index}";
            }

            if (isset($data['roll2_width']) && (!is_numeric($data['roll2_width']) || $data['roll2_width'] <= 0)) {
                $errors[] = "Invalid roll 2 width at index {$index}";
            }

            // Validate waste percentage is reasonable (not more than 50%)
            $wastePercentage = $this->calculateWastePercentage($data['original_weight'] ?? 0, $data['waste_weight'] ?? 0);
            if ($wastePercentage > 50) {
                $errors[] = "Waste percentage too high ({$wastePercentage}%) at index {$index}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Calculate waste percentage for roll conversion
     */
    public function calculateWastePercentage(float $originalWeight, float $wasteWeight): float
    {
        if ($originalWeight <= 0) {
            return 0;
        }

        return round(($wasteWeight / $originalWeight) * 100, 2);
    }

    /**
     * Get roll conversion summary
     */
    public function getRollConversionSummary(OrderProcessing $processing): array
    {
        if (!$processing->isSortingStage()) {
            return ['error' => 'Not a sorting stage'];
        }

        $conversions = $processing->roll_conversions ?? [];

        $totalInput = collect($conversions)->sum('original_weight');
        $totalRoll1 = collect($conversions)->sum('roll1_weight');
        $totalRoll2 = collect($conversions)->sum('roll2_weight');
        $totalWaste = collect($conversions)->sum('waste_weight');

        return [
            'processing_id' => $processing->id,
            'total_conversions' => count($conversions),
            'total_input_weight' => $totalInput,
            'total_roll1_weight' => $totalRoll1,
            'total_roll2_weight' => $totalRoll2,
            'total_waste_weight' => $totalWaste,
            'waste_percentage' => $this->calculateWastePercentage($totalInput, $totalWaste),
            'is_balanced' => abs($totalInput - ($totalRoll1 + $totalRoll2 + $totalWaste)) < 0.01,
            'conversions' => $conversions
        ];
    }

    /**
     * Transfer sorted materials with inventory management
     */
    public function transferSortedMaterials(OrderProcessing $processing, User $user, array $transferData): array
    {
        if (!$processing->isSortingApproved()) {
            return ['success' => false, 'error' => 'Sorting must be approved before transfer'];
        }

        if ($processing->transfer_completed) {
            return ['success' => false, 'error' => 'Transfer already completed'];
        }

        // Validate transfer data
        $validation = $this->validateTransferData($transferData);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        DB::beginTransaction();
        try {
            // Get sorting warehouse
            $sortingWarehouse = Warehouse::where('type', 'sorting')->first();
            if (!$sortingWarehouse) {
                throw new \Exception('Sorting warehouse not found');
            }

            $totalTransferred = 0;

            foreach ($transferData as $transfer) {
                $destinationWarehouse = Warehouse::find($transfer['destination_warehouse_id']);
                if (!$destinationWarehouse) {
                    throw new \Exception("Destination warehouse {$transfer['destination_warehouse_id']} not found");
                }

                // Find or create stock in sorting warehouse
                $sortingStock = Stock::where('product_id', $transfer['product_id'])
                    ->where('warehouse_id', $sortingWarehouse->id)
                    ->where('is_active', true)
                    ->first();

                if (!$sortingStock || $sortingStock->available_quantity < $transfer['weight']) {
                    throw new \Exception("Insufficient stock for product {$transfer['product_id']} in sorting warehouse");
                }

                // Transfer to destination warehouse
                $sortingStock->transferToWarehouse($transfer['weight'], $transfer['destination_warehouse_id'], $user->id, 'Post-sorting transfer');

                // Update order material with transfer info
                if (isset($transfer['order_material_id'])) {
                    $orderMaterial = OrderMaterial::find($transfer['order_material_id']);
                    if ($orderMaterial) {
                        $orderMaterial->sorted_weight = ($orderMaterial->sorted_weight ?? 0) + $transfer['weight'];
                        $orderMaterial->save();
                    }
                }

                $totalTransferred += $transfer['weight'];
            }

            // Mark transfer as completed
            $processing->transfer_completed = true;
            $processing->transfer_completed_at = now();
            $processing->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Materials transferred successfully',
                'total_transferred' => $totalTransferred,
                'transfer_count' => count($transferData)
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate transfer data
     */
    private function validateTransferData(array $transferData): array
    {
        $errors = [];

        foreach ($transferData as $index => $transfer) {
            if (!isset($transfer['product_id'])) {
                $errors[] = "Missing product_id at index {$index}";
            }

            if (!isset($transfer['destination_warehouse_id'])) {
                $errors[] = "Missing destination_warehouse_id at index {$index}";
            }

            if (!isset($transfer['weight']) || $transfer['weight'] <= 0) {
                $errors[] = "Invalid weight at index {$index}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get transfer eligibility for sorted materials
     */
    public function getTransferEligibility(OrderProcessing $processing): array
    {
        if (!$processing->isSortingApproved()) {
            return ['eligible' => false, 'reason' => 'Sorting not approved'];
        }

        if ($processing->transfer_completed) {
            return ['eligible' => false, 'reason' => 'Transfer already completed'];
        }

        $sortingWarehouse = Warehouse::where('type', 'sorting')->first();
        if (!$sortingWarehouse) {
            return ['eligible' => false, 'reason' => 'Sorting warehouse not found'];
        }

        // Get available stock in sorting warehouse
        $availableStock = Stock::where('warehouse_id', $sortingWarehouse->id)
            ->where('is_active', true)
            ->where('available_quantity', '>', 0)
            ->with('product')
            ->get();

        return [
            'eligible' => true,
            'sorting_warehouse' => $sortingWarehouse,
            'available_stock' => $availableStock->map(function($stock) {
                return [
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product->name ?? 'Unknown',
                    'available_quantity' => $stock->available_quantity,
                    'unit_cost' => $stock->unit_cost,
                ];
            })
        ];
    }

    /**
     * Manage inventory during sorting operations
     */
    public function manageSortingInventory(OrderProcessing $processing, User $user, array $inventoryData): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'error' => 'Not a sorting stage'];
        }

        DB::beginTransaction();
        try {
            $sortingWarehouse = Warehouse::where('type', 'sorting')->first();
            if (!$sortingWarehouse) {
                throw new \Exception('Sorting warehouse not found');
            }

            $results = [];

            foreach ($inventoryData as $data) {
                $productId = $data['product_id'];
                $weight = $data['weight'];
                $operation = $data['operation']; // 'add' or 'remove'

                // Find or create stock record
                $stock = Stock::where('product_id', $productId)
                    ->where('warehouse_id', $sortingWarehouse->id)
                    ->where('is_active', true)
                    ->first();

                if (!$stock) {
                    $stock = Stock::create([
                        'product_id' => $productId,
                        'warehouse_id' => $sortingWarehouse->id,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'unit_cost' => $data['unit_cost'] ?? 0,
                        'is_active' => true,
                    ]);
                }

                if ($operation === 'add') {
                    $stock->addStock($weight, $data['unit_cost'] ?? null);
                    $results[] = "Added {$weight}kg of product {$productId} to sorting warehouse";
                } elseif ($operation === 'remove') {
                    if (!$stock->removeStock($weight)) {
                        throw new \Exception("Insufficient stock for product {$productId}");
                    }
                    $results[] = "Removed {$weight}kg of product {$productId} from sorting warehouse";
                } else {
                    throw new \Exception("Invalid operation: {$operation}");
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Inventory management completed',
                'results' => $results,
                'warehouse' => $sortingWarehouse->name
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get inventory status for sorting warehouse
     */
    public function getSortingInventoryStatus(): array
    {
        $sortingWarehouse = Warehouse::where('type', 'sorting')->first();
        if (!$sortingWarehouse) {
            return ['error' => 'Sorting warehouse not found'];
        }

        $stocks = Stock::where('warehouse_id', $sortingWarehouse->id)
            ->where('is_active', true)
            ->with('product')
            ->get();

        $totalValue = $stocks->sum(function($stock) {
            return $stock->total_value;
        });

        $lowStockAlerts = $stocks->filter(function($stock) {
            return $stock->available_quantity <= 10; // Low stock threshold
        });

        return [
            'warehouse' => $sortingWarehouse,
            'total_items' => $stocks->count(),
            'total_value' => $totalValue,
            'stocks' => $stocks->map(function($stock) {
                return [
                    'product_id' => $stock->product_id,
                    'product_name' => $stock->product->name ?? 'Unknown',
                    'quantity' => $stock->quantity,
                    'reserved_quantity' => $stock->reserved_quantity,
                    'available_quantity' => $stock->available_quantity,
                    'unit_cost' => $stock->unit_cost,
                    'total_value' => $stock->total_value,
                    'is_low_stock' => $stock->available_quantity <= 10,
                ];
            }),
            'low_stock_alerts' => $lowStockAlerts->map(function($stock) {
                return [
                    'product_name' => $stock->product->name ?? 'Unknown',
                    'available_quantity' => $stock->available_quantity,
                ];
            }),
            'expiring_soon' => $stocks->filter(function($stock) {
                return $stock->isExpiringSoon();
            })->map(function($stock) {
                return [
                    'product_name' => $stock->product->name ?? 'Unknown',
                    'expiry_date' => $stock->expiry_date,
                ];
            })
        ];
    }

    /**
     * Reserve materials for sorting
     */
    public function reserveMaterialsForSorting(OrderProcessing $processing, array $materials, User $user): array
    {
        if (!$processing->isSortingStage()) {
            return ['success' => false, 'error' => 'Not a sorting stage'];
        }

        DB::beginTransaction();
        try {
            $results = [];

            foreach ($materials as $material) {
                $stock = Stock::find($material['stock_id']);
                if (!$stock) {
                    throw new \Exception("Stock not found for ID {$material['stock_id']}");
                }

                if (!$stock->reserve($material['quantity'])) {
                    throw new \Exception("Insufficient available quantity for stock {$material['stock_id']}");
                }

                $results[] = "Reserved {$material['quantity']} units from stock {$material['stock_id']}";
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Materials reserved for sorting',
                'reservations' => $results
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
