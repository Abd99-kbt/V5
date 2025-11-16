<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricingService
{
    /**
     * Calculate comprehensive pricing for an order
     */
    public function calculateOrderPricing(Order $order, User $user, array $pricingConfig = []): array
    {
        DB::beginTransaction();
        try {
            // Get cost components
            $materialCost = $order->estimated_material_cost ?? 0;
            $laborCost = $this->calculateLaborCost($order, $pricingConfig);
            $overheadCost = $this->calculateOverheadCost($order, $pricingConfig);

            // Calculate subtotal
            $subtotal = $materialCost + $laborCost + $overheadCost;

            // Apply profit margin
            $profitMarginPercentage = $pricingConfig['profit_margin_percentage'] ?? $this->getDefaultProfitMargin($order);
            $profitMarginAmount = ($subtotal * $profitMarginPercentage) / 100;

            // Calculate final price
            $finalPrice = $subtotal + $profitMarginAmount;

            // Apply discount if any
            $discountAmount = $order->discount ?? 0;
            $finalPrice -= $discountAmount;

            // Create pricing breakdown
            $pricingBreakdown = [
                'material_cost' => [
                    'amount' => $materialCost,
                    'percentage' => $materialCost > 0 ? ($materialCost / $subtotal) * 100 : 0,
                    'details' => $this->getMaterialCostBreakdown($order),
                ],
                'labor_cost' => [
                    'amount' => $laborCost,
                    'percentage' => $laborCost > 0 ? ($laborCost / $subtotal) * 100 : 0,
                    'details' => $this->getLaborCostBreakdown($order, $pricingConfig),
                ],
                'overhead_cost' => [
                    'amount' => $overheadCost,
                    'percentage' => $overheadCost > 0 ? ($overheadCost / $subtotal) * 100 : 0,
                    'details' => $this->getOverheadCostBreakdown($order, $pricingConfig),
                ],
                'subtotal' => $subtotal,
                'profit_margin' => [
                    'percentage' => $profitMarginPercentage,
                    'amount' => $profitMarginAmount,
                ],
                'discount' => [
                    'amount' => $discountAmount,
                    'reason' => $order->discount_reason ?? null,
                ],
                'final_price' => $finalPrice,
                'calculated_at' => now(),
                'calculated_by' => $user->id,
            ];

            // Update order with pricing information
            $order->update([
                'estimated_material_cost' => $materialCost,
                'labor_cost_estimate' => $laborCost,
                'overhead_cost_estimate' => $overheadCost,
                'profit_margin_percentage' => $profitMarginPercentage,
                'profit_margin_amount' => $profitMarginAmount,
                'pricing_breakdown' => $pricingBreakdown,
                'estimated_price' => $finalPrice,
                'final_price' => $finalPrice,
                'pricing_calculated' => true,
                'pricing_calculated_at' => now(),
                'pricing_calculated_by' => $user->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'pricing' => $pricingBreakdown,
                'final_price' => $finalPrice,
                'cost_breakdown' => [
                    'material' => $materialCost,
                    'labor' => $laborCost,
                    'overhead' => $overheadCost,
                    'profit' => $profitMarginAmount,
                    'discount' => $discountAmount,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Pricing calculation failed', [
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
     * Calculate labor cost based on order requirements
     */
    private function calculateLaborCost(Order $order, array $config = []): float
    {
        // Base labor rates per operation
        $laborRates = $config['labor_rates'] ?? [
            'cutting' => 0.5, // per kg
            'sorting' => 0.3,
            'packaging' => 0.2,
            'quality_check' => 0.1,
        ];

        $totalLaborCost = 0;

        // Calculate based on weight and operations
        $totalWeight = $order->required_weight ?? $order->orderItems->sum(function($item) {
            return $item->quantity * ($item->product->weight_per_unit ?? 1);
        });

        foreach ($laborRates as $operation => $ratePerKg) {
            $totalLaborCost += $totalWeight * $ratePerKg;
        }

        // Add complexity factors
        if ($order->is_urgent) {
            $totalLaborCost *= 1.5; // 50% premium for urgent orders
        }

        if ($order->required_width && $order->required_width > 100) {
            $totalLaborCost *= 1.2; // 20% more for wide materials
        }

        return round($totalLaborCost, 2);
    }

    /**
     * Calculate overhead costs
     */
    private function calculateOverheadCost(Order $order, array $config = []): float
    {
        $overheadRate = $config['overhead_rate'] ?? 0.15; // 15% of material + labor cost
        $materialCost = $order->estimated_material_cost ?? 0;
        $laborCost = $this->calculateLaborCost($order, $config);

        return round(($materialCost + $laborCost) * $overheadRate, 2);
    }

    /**
     * Get default profit margin based on order characteristics
     */
    private function getDefaultProfitMargin(Order $order): float
    {
        $baseMargin = 20.0; // 20% default

        // Adjust based on order size
        $totalWeight = $order->required_weight ?? $order->orderItems->sum('quantity');
        if ($totalWeight > 1000) {
            $baseMargin += 5; // Larger orders get higher margin
        } elseif ($totalWeight < 100) {
            $baseMargin -= 5; // Smaller orders get lower margin
        }

        // Adjust for urgency
        if ($order->is_urgent) {
            $baseMargin += 10;
        }

        // Adjust for complexity
        if ($order->required_width && $order->required_width > 150) {
            $baseMargin += 5;
        }

        return max(10, min(50, $baseMargin)); // Between 10% and 50%
    }

    /**
     * Get detailed material cost breakdown
     */
    private function getMaterialCostBreakdown(Order $order): array
    {
        if (empty($order->selected_materials)) {
            return ['note' => 'No materials selected yet'];
        }

        $breakdown = [];
        foreach ($order->selected_materials as $material) {
            $breakdown[] = [
                'product_name' => $material['product_name'] ?? 'Unknown',
                'roll_number' => $material['roll_number'],
                'weight' => $material['allocated_weight'],
                'cost_per_kg' => $material['cost_per_kg'] ?? 0,
                'total_cost' => $material['estimated_cost'] ?? 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Get detailed labor cost breakdown
     */
    private function getLaborCostBreakdown(Order $order, array $config = []): array
    {
        $laborRates = $config['labor_rates'] ?? [
            'cutting' => 0.5,
            'sorting' => 0.3,
            'packaging' => 0.2,
            'quality_check' => 0.1,
        ];

        $totalWeight = $order->required_weight ?? $order->orderItems->sum(function($item) {
            return $item->quantity * ($item->product->weight_per_unit ?? 1);
        });

        $breakdown = [];
        foreach ($laborRates as $operation => $rate) {
            $cost = $totalWeight * $rate;
            $breakdown[] = [
                'operation' => $operation,
                'rate_per_kg' => $rate,
                'weight' => $totalWeight,
                'cost' => $cost,
            ];
        }

        // Add complexity adjustments
        if ($order->is_urgent) {
            $breakdown[] = [
                'operation' => 'urgency_premium',
                'rate_per_kg' => 0.25,
                'weight' => $totalWeight,
                'cost' => $totalWeight * 0.25,
            ];
        }

        return $breakdown;
    }

    /**
     * Get overhead cost breakdown
     */
    private function getOverheadCostBreakdown(Order $order, array $config = []): array
    {
        $overheadRate = $config['overhead_rate'] ?? 0.15;
        $materialCost = $order->estimated_material_cost ?? 0;
        $laborCost = $this->calculateLaborCost($order, $config);
        $baseCost = $materialCost + $laborCost;

        return [
            [
                'category' => 'overhead',
                'rate' => $overheadRate * 100 . '%',
                'base_cost' => $baseCost,
                'overhead_cost' => $baseCost * $overheadRate,
            ]
        ];
    }

    /**
     * Validate pricing configuration
     */
    public function validatePricingConfig(array $config): array
    {
        $errors = [];

        if (isset($config['profit_margin_percentage'])) {
            $margin = $config['profit_margin_percentage'];
            if ($margin < 0 || $margin > 100) {
                $errors[] = 'Profit margin percentage must be between 0 and 100';
            }
        }

        if (isset($config['labor_rates'])) {
            foreach ($config['labor_rates'] as $operation => $rate) {
                if ($rate < 0) {
                    $errors[] = "Labor rate for {$operation} cannot be negative";
                }
            }
        }

        if (isset($config['overhead_rate'])) {
            $rate = $config['overhead_rate'];
            if ($rate < 0 || $rate > 1) {
                $errors[] = 'Overhead rate must be between 0 and 1 (0% to 100%)';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Recalculate pricing if materials or requirements change
     */
    public function recalculatePricing(Order $order, User $user, array $pricingConfig = []): array
    {
        // Reset pricing flags
        $order->update([
            'pricing_calculated' => false,
            'pricing_calculated_at' => null,
            'pricing_calculated_by' => null,
        ]);

        return $this->calculateOrderPricing($order, $user, $pricingConfig);
    }

    /**
     * Get pricing summary for an order
     */
    public function getPricingSummary(Order $order): array
    {
        if (!$order->pricing_calculated || empty($order->pricing_breakdown)) {
            return [
                'calculated' => false,
                'message' => 'Pricing not calculated yet'
            ];
        }

        $breakdown = $order->pricing_breakdown;

        return [
            'calculated' => true,
            'calculated_at' => $order->pricing_calculated_at,
            'calculated_by' => $order->pricingCalculator?->name,
            'summary' => [
                'material_cost' => $breakdown['material_cost']['amount'] ?? 0,
                'labor_cost' => $breakdown['labor_cost']['amount'] ?? 0,
                'overhead_cost' => $breakdown['overhead_cost']['amount'] ?? 0,
                'subtotal' => $breakdown['subtotal'] ?? 0,
                'profit_margin' => $breakdown['profit_margin']['amount'] ?? 0,
                'discount' => $breakdown['discount']['amount'] ?? 0,
                'final_price' => $breakdown['final_price'] ?? 0,
            ],
            'breakdown' => $breakdown,
        ];
    }
}
