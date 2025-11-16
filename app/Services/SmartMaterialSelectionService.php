<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SmartMaterialSelectionService
{
    /**
     * Automatically select materials for an order based on requirements
     */
    public function autoSelectMaterials(Order $order, User $user = null): array
    {
        try {
            // Analyze order requirements
            $requirements = $this->analyzeOrderRequirements($order);

            if (empty($requirements)) {
                return [
                    'success' => false,
                    'error' => 'No material requirements found for this order'
                ];
            }

            // Find optimal materials
            $optimalMaterials = $this->findOptimalMaterials($requirements, $order);

            if ($optimalMaterials->isEmpty()) {
                Log::warning('SmartMaterialSelection: No optimal materials found', [
                    'order_id' => $order->id,
                    'requirements' => $requirements,
                    'warehouse_id' => $order->warehouse_id
                ]);

                return [
                    'success' => false,
                    'error' => 'No suitable materials found matching requirements'
                ];
            }

            // Rank materials by priority
            $rankedMaterials = $this->rankMaterialsByPriority($optimalMaterials, $requirements);

            // Suggest quantities
            $materialSuggestions = $this->suggestQuantities($rankedMaterials, $requirements, $order);

            // Update order with selected materials
            $order->update([
                'selected_materials' => $materialSuggestions,
                'materials_selected_at' => now(),
                'materials_selected_by' => $user?->id,
                'auto_material_selection' => true,
            ]);

            return [
                'success' => true,
                'selected_materials' => $materialSuggestions,
                'total_materials' => count($materialSuggestions),
                'estimated_cost' => collect($materialSuggestions)->sum('estimated_cost'),
                'total_weight' => collect($materialSuggestions)->sum('suggested_weight'),
            ];

        } catch (\Exception $e) {
            Log::error('Smart material selection failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyze order requirements and extract material specifications
     */
    public function analyzeOrderRequirements(Order $order): array
    {
        $requirements = [];

        // Check delivery specifications
        $deliverySpecs = $order->getDeliverySpecificationsAttribute();
        if (!empty(array_filter($deliverySpecs))) {
            $requirements['delivery_specs'] = [
                'width' => $order->delivery_width,
                'length' => $order->delivery_length,
                'thickness' => $order->delivery_thickness,
                'grammage' => $order->delivery_grammage,
                'quality' => $order->delivery_quality,
                'quantity' => $order->delivery_quantity,
                'weight' => $order->delivery_weight,
            ];
        }

        // Check order-level specifications
        $orderSpecs = [
            'material_type' => $order->material_type,
            'required_weight' => $order->required_weight,
            'required_length' => $order->required_length,
            'required_width' => $order->required_width,
            'required_plates' => $order->required_plates,
        ];

        // Filter out null values
        $orderSpecs = array_filter($orderSpecs, function($value) {
            return $value !== null;
        });

        if (!empty($orderSpecs)) {
            $requirements['order_specs'] = $orderSpecs;
        }

        // Analyze order items
        $itemRequirements = [];
        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            if ($product) {
                $itemRequirements[] = [
                    'order_item_id' => $orderItem->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $orderItem->quantity,
                    'specifications' => [
                        'type' => $product->type,
                        'grammage' => $product->grammage,
                        'quality' => $product->quality,
                        'width' => $product->width,
                        'length' => $product->length,
                        'thickness' => $product->thickness,
                        'weight' => $product->weight,
                    ],
                    'estimated_weight_needed' => $this->calculateEstimatedWeight($orderItem, $product),
                ];
            }
        }

        if (!empty($itemRequirements)) {
            $requirements['item_requirements'] = $itemRequirements;
        }

        // Check material requirements field
        if ($order->material_requirements) {
            $requirements['custom_requirements'] = $order->material_requirements;
        }

        return $requirements;
    }

    /**
     * Find optimal materials based on requirements
     */
    public function findOptimalMaterials(array $requirements, Order $order): Collection
    {
        $availableMaterials = collect();

        // Get warehouse constraint
        $warehouseId = $order->warehouse_id;

        // Build query for available products - ensure we have stocks with available quantity
        $query = Product::whereHas('stocks', function($q) use ($warehouseId) {
            $q->where('is_active', true)
              ->whereColumn('quantity', '>', 'reserved_quantity')
              ->where('quantity', '>', 0); // Ensure positive quantity

            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        })->where('is_active', true); // Ensure product is active

        // Apply filters based on requirements - make them optional to find more materials
        $hasFilters = false;

        if (isset($requirements['delivery_specs'])) {
            $specs = $requirements['delivery_specs'];

            if ($specs['grammage']) {
                $query->where('grammage', '>=', $specs['grammage'] - 10) // Allow 10gsm tolerance
                      ->where('grammage', '<=', $specs['grammage'] + 10);
                $hasFilters = true;
            }

            if ($specs['quality']) {
                $query->where('quality', $specs['quality']);
                $hasFilters = true;
            }

            if ($specs['width']) {
                $query->where('width', '>=', $specs['width']);
                $hasFilters = true;
            }
        }

        if (isset($requirements['order_specs'])) {
            $specs = $requirements['order_specs'];

            if ($specs['material_type']) {
                $query->where('type', $specs['material_type']);
                $hasFilters = true;
            }
        }

        // If no specific filters, still ensure we have basic material availability
        if (!$hasFilters) {
            Log::info('SmartMaterialSelection: No specific filters applied, using general availability', [
                'order_id' => $order->id
            ]);
        }

        // Get matching products with their stocks
        $products = $query->with(['stocks' => function($q) use ($warehouseId) {
            $q->where('is_active', true)
              ->whereColumn('quantity', '>', 'reserved_quantity')
              ->where('quantity', '>', 0); // Ensure positive quantity

            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        }])->get();

        // Debug: Log found products
        Log::info('SmartMaterialSelection: Found products', [
            'product_count' => $products->count(),
            'warehouse_id' => $warehouseId,
            'requirements' => $requirements
        ]);

        // Convert to material collection
        foreach ($products as $product) {
            foreach ($product->stocks as $stock) {
                // Calculate available quantity properly
                $availableQuantity = max(0, $stock->quantity - ($stock->reserved_quantity ?? 0));

                if ($availableQuantity > 0) { // Only include if there's actually available quantity
                    $availableMaterials->push([
                        'product' => $product,
                        'stock' => $stock,
                        'available_weight' => $availableQuantity,
                        'specifications' => [
                            'type' => $product->type,
                            'grammage' => $product->grammage,
                            'quality' => $product->quality,
                            'width' => $product->width,
                            'length' => $product->length,
                            'thickness' => $product->thickness,
                            'roll_number' => $stock->specifications['roll_number'] ?? null,
                        ],
                        'cost_per_kg' => $product->material_cost_per_ton ? $product->material_cost_per_ton / 1000 : 0,
                        'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                        'location' => $stock->location,
                    ]);
                }
            }
        }

        // Debug: Log available materials count
        Log::info('SmartMaterialSelection: Available materials after filtering', [
            'materials_count' => $availableMaterials->count(),
            'order_id' => $order->id
        ]);

        return $availableMaterials;
    }

    /**
     * Rank materials by priority based on requirements matching
     */
    public function rankMaterialsByPriority(Collection $materials, array $requirements): Collection
    {
        return $materials->map(function($material) use ($requirements) {
            $score = 0;
            $matches = [];

            // Check delivery specs matching
            if (isset($requirements['delivery_specs'])) {
                $specs = $requirements['delivery_specs'];

                // Grammage matching (higher score for exact match)
                if ($specs['grammage'] && $material['specifications']['grammage']) {
                    $grammageDiff = abs($specs['grammage'] - $material['specifications']['grammage']);
                    if ($grammageDiff === 0) {
                        $score += 100;
                        $matches[] = 'exact_grammage';
                    } elseif ($grammageDiff <= 5) {
                        $score += 80;
                        $matches[] = 'close_grammage';
                    } elseif ($grammageDiff <= 10) {
                        $score += 50;
                        $matches[] = 'acceptable_grammage';
                    }
                }

                // Quality matching
                if ($specs['quality'] && $material['specifications']['quality'] === $specs['quality']) {
                    $score += 90;
                    $matches[] = 'quality_match';
                }

                // Width matching
                if ($specs['width'] && $material['specifications']['width'] >= $specs['width']) {
                    $score += 70;
                    $matches[] = 'sufficient_width';
                }
            }

            // Check order specs matching
            if (isset($requirements['order_specs'])) {
                $specs = $requirements['order_specs'];

                if ($specs['material_type'] && $material['specifications']['type'] === $specs['material_type']) {
                    $score += 60;
                    $matches[] = 'type_match';
                }
            }

            // Availability bonus
            if ($material['available_weight'] > 100) {
                $score += 30;
                $matches[] = 'good_availability';
            } elseif ($material['available_weight'] > 50) {
                $score += 20;
                $matches[] = 'moderate_availability';
            }

            // Cost efficiency (prefer lower cost materials)
            if ($material['cost_per_kg'] > 0) {
                $costScore = max(0, 50 - ($material['cost_per_kg'] * 10));
                $score += $costScore;
                $matches[] = 'cost_efficient';
            }

            $material['priority_score'] = $score;
            $material['matching_criteria'] = $matches;

            return $material;
        })->sortByDesc('priority_score');
    }

    /**
     * Suggest quantities for selected materials
     */
    public function suggestQuantities(Collection $rankedMaterials, array $requirements, Order $order): array
    {
        $suggestions = [];
        $totalRequiredWeight = 0;

        // Calculate total required weight
        if (isset($requirements['item_requirements'])) {
            $totalRequiredWeight = collect($requirements['item_requirements'])->sum('estimated_weight_needed');
        }

        if (isset($requirements['order_specs']['required_weight'])) {
            $totalRequiredWeight = max($totalRequiredWeight, $requirements['order_specs']['required_weight']);
        }

        if ($totalRequiredWeight <= 0) {
            return $suggestions;
        }

        $remainingWeight = $totalRequiredWeight;
        $materialIndex = 0;

        // Distribute weight across available materials
        foreach ($rankedMaterials as $material) {
            if ($remainingWeight <= 0) break;

            $availableWeight = $material['available_weight'];
            $suggestedWeight = min($remainingWeight, $availableWeight);

            // Add 5% buffer for waste
            $suggestedWeight *= 1.05;

            // Round to reasonable precision
            $suggestedWeight = round($suggestedWeight, 2);

            if ($suggestedWeight > 0) {
                $estimatedCost = $suggestedWeight * $material['cost_per_kg'];

                $suggestions[] = [
                    'product_id' => $material['product']->id,
                    'product_name' => $material['product']->name,
                    'stock_id' => $material['stock']->id,
                    'roll_number' => $material['specifications']['roll_number'],
                    'specifications' => $material['specifications'],
                    'available_weight' => $availableWeight,
                    'suggested_weight' => $suggestedWeight,
                    'estimated_cost' => round($estimatedCost, 2),
                    'priority_score' => $material['priority_score'],
                    'matching_criteria' => $material['matching_criteria'],
                    'warehouse_name' => $material['warehouse_name'],
                    'location' => $material['location'],
                ];

                $remainingWeight -= ($suggestedWeight / 1.05); // Subtract actual needed weight
                $materialIndex++;
            }

            // Limit to top 5 materials to avoid over-selection
            if ($materialIndex >= 5) break;
        }

        return $suggestions;
    }

    /**
     * Calculate estimated weight needed for an order item
     */
    private function calculateEstimatedWeight($orderItem, Product $product): float
    {
        // If weight per unit is specified, use it
        if ($product->weight && $orderItem->quantity) {
            return $product->weight * $orderItem->quantity;
        }

        // Estimate based on dimensions if available
        if ($product->length && $product->width && $product->thickness && $product->grammage) {
            $area = ($product->length * $product->width) / 1000000; // Convert to m²
            $weightPerSheet = ($area * $product->grammage) / 1000; // Convert to kg
            return $weightPerSheet * $orderItem->quantity;
        }

        // Fallback to quantity as weight estimate
        return $orderItem->quantity;
    }

    /**
     * Run automated material selection for pending orders
     */
    public function run(): array
    {
        try {
            $pendingOrders = Order::where('status', 'pending')
                                ->whereNull('materials_selected_at')
                                ->where('auto_material_selection', true)
                                ->get();

            $results = [];
            foreach ($pendingOrders as $order) {
                $result = $this->autoSelectMaterials($order);
                $results[] = [
                    'order_id' => $order->id,
                    'success' => $result['success'],
                    'materials_selected' => $result['selected_materials'] ?? [],
                    'error' => $result['error'] ?? null
                ];
            }

            return [
                'success' => true,
                'orders_processed' => count($pendingOrders),
                'results' => $results,
                'timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Automated material selection run failed', [
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