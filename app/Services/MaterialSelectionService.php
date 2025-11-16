<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\MaterialSpecificationService;
use App\Services\SpecificationValidationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialSelectionService
{
    protected MaterialSpecificationService $materialSpecService;
    protected SpecificationValidationService $specValidationService;

    public function __construct(
        MaterialSpecificationService $materialSpecService,
        SpecificationValidationService $specValidationService
    ) {
        $this->materialSpecService = $materialSpecService;
        $this->specValidationService = $specValidationService;
    }

    /**
     * Automatically select materials for an order based on requirements
     */
    public function selectMaterialsForOrder(Order $order, User $user, bool $autoSelect = true): array
    {
        if (!$autoSelect && empty($order->material_requirements)) {
            return [
                'success' => false,
                'error' => 'Manual material selection requires material requirements to be specified'
            ];
        }

        DB::beginTransaction();
        try {
            $selectedMaterials = [];

            if ($autoSelect) {
                $selectedMaterials = $this->autoSelectMaterials($order);
            } else {
                $selectedMaterials = $this->manualSelectMaterials($order, $order->material_requirements);
            }

            // Update order with selected materials
            $order->update([
                'selected_materials' => $selectedMaterials,
                'materials_selected_at' => now(),
                'materials_selected_by' => $user->id,
                'auto_material_selection' => $autoSelect,
            ]);

            // Calculate estimated material cost
            $materialCost = $this->calculateMaterialCost($selectedMaterials);
            $order->update(['estimated_material_cost' => $materialCost]);

            DB::commit();

            return [
                'success' => true,
                'selected_materials' => $selectedMaterials,
                'estimated_cost' => $materialCost,
                'total_weight' => collect($selectedMaterials)->sum('allocated_weight'),
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Material selection failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Automatically select materials based on order specifications
     */
    private function autoSelectMaterials(Order $order): array
    {
        $selectedMaterials = [];

        // Get order items and their requirements
        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            $requiredWeight = $orderItem->quantity * ($product->weight_per_unit ?? 1);

            // Get material specifications from order or product defaults
            $requiredSpecs = $this->getMaterialSpecifications($order, $orderItem);

            // Validate delivery specifications compatibility
            $compatibilityWarnings = $this->specValidationService->checkSpecificationCompatibility(
                $order,
                $product
            );

            if (!empty($compatibilityWarnings)) {
                Log::warning('Specification compatibility warnings for order', [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'warnings' => $compatibilityWarnings
                ]);
            }

            // Find optimal material combination
            $optimalCombination = $this->materialSpecService->findOptimalRollCombination(
                $product,
                $requiredSpecs,
                $requiredWeight,
                $order->warehouse
            );

            if (!$optimalCombination['success']) {
                throw new \Exception("No suitable materials found for product: {$product->name}");
            }

            // Add to selected materials
            foreach ($optimalCombination['rolls'] as $roll) {
                $selectedMaterials[] = [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'stock_id' => $roll['stock']->id,
                    'roll_number' => $roll['specifications']['roll_number'],
                    'required_weight' => $requiredWeight,
                    'allocated_weight' => $roll['allocated_weight'],
                    'specifications' => $roll['specifications'],
                    'validation' => $roll['validation'],
                    'efficiency' => $roll['efficiency'],
                    'estimated_cost' => $this->calculateRollCost($roll['stock'], $roll['allocated_weight']),
                ];
            }
        }

        return $selectedMaterials;
    }

    /**
     * Manually select materials based on provided requirements
     */
    private function manualSelectMaterials(Order $order, array $requirements): array
    {
        $selectedMaterials = [];

        foreach ($requirements as $requirement) {
            $stock = Stock::find($requirement['stock_id']);
            if (!$stock) {
                throw new \Exception("Stock not found: {$requirement['stock_id']}");
            }

            // Validate the selection
            $validation = $this->validateManualSelection($stock, $requirement);

            if (!$validation['is_valid']) {
                throw new \Exception("Invalid material selection: " . implode(', ', $validation['errors']));
            }

            $selectedMaterials[] = [
                'order_item_id' => $requirement['order_item_id'] ?? null,
                'product_id' => $stock->product_id,
                'stock_id' => $stock->id,
                'roll_number' => $stock->specifications['roll_number'] ?? null,
                'required_weight' => $requirement['required_weight'],
                'allocated_weight' => $requirement['allocated_weight'],
                'specifications' => $this->materialSpecService->getRollSpecifications($stock),
                'validation' => $validation,
                'estimated_cost' => $this->calculateRollCost($stock, $requirement['allocated_weight']),
            ];
        }

        return $selectedMaterials;
    }

    /**
     * Get material specifications for order item
     */
    private function getMaterialSpecifications(Order $order, $orderItem): array
    {
        // Check if order has specific requirements
        if ($order->material_requirements && isset($order->material_requirements[$orderItem->id])) {
            return $order->material_requirements[$orderItem->id];
        }

        // Use delivery specifications if available
        $deliverySpecs = $order->getDeliverySpecificationsAttribute();
        if (!empty(array_filter($deliverySpecs))) {
            return [
                'width' => $order->delivery_width,
                'length' => $order->delivery_length,
                'thickness' => $order->delivery_thickness,
                'grammage' => $order->delivery_grammage,
                'quality' => $order->delivery_quality,
                'quantity' => $order->delivery_quantity,
                'weight' => $order->delivery_weight,
            ];
        }

        // Use order-level specifications
        return [
            'width' => $order->required_width,
            'length' => $order->required_length,
            'grammage' => $orderItem->product->specifications['grammage'] ?? null,
            'quality' => $orderItem->product->specifications['quality'] ?? null,
            'min_length' => $order->required_length,
        ];
    }

    /**
     * Calculate cost for a roll allocation
     */
    private function calculateRollCost(Stock $stock, float $weight): float
    {
        $costPerKg = $stock->product->cost_per_unit ?? 0;
        return $costPerKg * $weight;
    }

    /**
     * Calculate total material cost
     */
    private function calculateMaterialCost(array $selectedMaterials): float
    {
        return collect($selectedMaterials)->sum('estimated_cost');
    }

    /**
     * Validate manual material selection
     */
    private function validateManualSelection(Stock $stock, array $requirement): array
    {
        $errors = [];

        // Check if stock has sufficient quantity
        if ($stock->available_quantity < $requirement['allocated_weight']) {
            $errors[] = 'Insufficient stock quantity';
        }

        // Check specifications match requirements
        $specs = $this->materialSpecService->getRollSpecifications($stock);
        $validation = $this->materialSpecService->validateRollSpecifications(
            $requirement['specifications'] ?? [],
            $specs
        );

        if (!$validation['is_valid']) {
            $errors = array_merge($errors, $validation['issues']);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'stock_specs' => $specs,
        ];
    }

    /**
     * Reserve selected materials for the order
     */
    public function reserveSelectedMaterials(Order $order, User $user): array
    {
        if (empty($order->selected_materials)) {
            return [
                'success' => false,
                'error' => 'No materials selected for reservation'
            ];
        }

        DB::beginTransaction();
        try {
            $reservations = [];

            foreach ($order->selected_materials as $material) {
                $stock = Stock::find($material['stock_id']);
                if (!$stock) continue;

                // Reserve the material
                $reservation = $stock->reserveQuantity(
                    $material['allocated_weight'],
                    $order->id,
                    $user->id,
                    'Order material reservation'
                );

                if ($reservation) {
                    $reservations[] = [
                        'stock_id' => $stock->id,
                        'reserved_weight' => $material['allocated_weight'],
                        'reservation_id' => $reservation->id,
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'reservations' => $reservations,
                'total_reserved' => collect($reservations)->sum('reserved_weight'),
            ];

        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get material availability report
     */
    public function getMaterialAvailabilityReport(Order $order): array
    {
        $report = [
            'order_id' => $order->id,
            'materials' => [],
            'summary' => [
                'total_required' => 0,
                'total_available' => 0,
                'total_shortage' => 0,
                'availability_percentage' => 0,
            ]
        ];

        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            $requiredWeight = $orderItem->quantity * ($product->weight_per_unit ?? 1);

            $availableWeight = $this->getAvailableWeightForProduct($product, $order->warehouse);

            $report['materials'][] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'required_weight' => $requiredWeight,
                'available_weight' => $availableWeight,
                'shortage' => max(0, $requiredWeight - $availableWeight),
                'availability_percentage' => $requiredWeight > 0 ? min(100, ($availableWeight / $requiredWeight) * 100) : 0,
            ];

            $report['summary']['total_required'] += $requiredWeight;
            $report['summary']['total_available'] += $availableWeight;
            $report['summary']['total_shortage'] += max(0, $requiredWeight - $availableWeight);
        }

        $report['summary']['availability_percentage'] =
            $report['summary']['total_required'] > 0 ?
            ($report['summary']['total_available'] / $report['summary']['total_required']) * 100 : 0;

        return $report;
    }

    /**
     * Get available weight for a product in warehouse
     */
    private function getAvailableWeightForProduct(Product $product, ?Warehouse $warehouse = null): float
    {
        $query = $product->stocks()->where('is_active', true);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        return $query->sum('available_quantity');
    }
}
