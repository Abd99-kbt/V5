<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomatedPricingService
{
    /**
     * Calculate comprehensive price for an order
     */
    public function calculateComprehensivePrice(Order $order, User $user = null): array
    {
        try {
            // استخدام التخزين المؤقت للبيانات التاريخية
            $historicalData = \App\Services\CacheManager::remember(
                "pricing.historical.{$order->material_type}",
                function () use ($order) {
                    return \App\Models\Order::where('material_type', $order->material_type)
                        ->where('status', 'completed')
                        ->where('created_at', '>=', now()->subMonths(6))
                        ->selectRaw('AVG(final_price) as avg_price, AVG(profit_margin_percentage) as avg_margin')
                        ->first();
                },
                3600 // ساعة واحدة
            );

            // Calculate base price
            $basePrice = $this->calculateBasePrice($order);

            // Calculate additional costs
            $additionalCosts = $this->calculateAdditionalCosts($order);

            // Estimate waste costs
            $wasteCosts = $this->estimateWasteCosts($order);

            // Calculate overhead costs
            $overheadCosts = $this->calculateOverheadCosts($order);

            // Calculate optimal profit margin
            $profitMargin = $this->calculateOptimalProfitMargin($order);

            // Calculate total price
            $totalPrice = $this->calculateTotalPrice($basePrice, $additionalCosts, $wasteCosts, $overheadCosts, $profitMargin);

            // Generate price breakdown
            $priceBreakdown = $this->generatePriceBreakdown($basePrice, $additionalCosts, $wasteCosts, $overheadCosts, $profitMargin, $totalPrice);

            // Update order with pricing information
            $order->update([
                'estimated_material_cost' => $basePrice,
                'labor_cost_estimate' => $additionalCosts['labor_cost'],
                'overhead_cost_estimate' => $overheadCosts,
                'profit_margin_percentage' => $profitMargin['percentage'],
                'profit_margin_amount' => $profitMargin['amount'],
                'final_price' => $totalPrice,
                'pricing_breakdown' => $priceBreakdown,
                'pricing_calculated' => true,
                'pricing_calculated_at' => now(),
                'pricing_calculated_by' => $user?->id,
            ]);

            // مسح التخزين المؤقت عند التحديث
            \App\Services\CacheManager::invalidatePattern("pricing.{$order->id}");

            return [
                'success' => true,
                'base_price' => $basePrice,
                'additional_costs' => $additionalCosts,
                'waste_costs' => $wasteCosts,
                'overhead_costs' => $overheadCosts,
                'profit_margin' => $profitMargin,
                'total_price' => $totalPrice,
                'price_breakdown' => $priceBreakdown,
            ];

        } catch (\Exception $e) {
            Log::error('Comprehensive pricing calculation failed', [
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
     * Calculate base price based on material costs
     */
    public function calculateBasePrice(Order $order): float
    {
        $basePrice = 0;

        // If materials are already selected, use their costs
        if ($order->selected_materials && is_array($order->selected_materials)) {
            foreach ($order->selected_materials as $material) {
                $basePrice += $material['estimated_cost'] ?? 0;
            }
        } else {
            // Calculate based on order items
            foreach ($order->orderItems as $orderItem) {
                $product = $orderItem->product;
                if ($product && $product->material_cost_per_ton) {
                    // Estimate weight needed
                    $weightNeeded = $this->estimateWeightNeeded($orderItem, $product);
                    $basePrice += ($product->material_cost_per_ton * $weightNeeded) / 1000; // Convert to kg
                }
            }
        }

        return round($basePrice, 2);
    }

    /**
     * Calculate additional costs (labor, cutting, etc.)
     */
    public function calculateAdditionalCosts(Order $order): array
    {
        $additionalCosts = [
            'labor_cost' => 0,
            'cutting_cost' => 0,
            'transport_cost' => 0,
            'packaging_cost' => 0,
            'other_costs' => 0,
        ];

        // Calculate labor cost based on weight and complexity
        $totalWeight = $order->required_weight ?? $this->calculateTotalRequiredWeight($order);
        $laborRatePerKg = $this->getLaborRatePerKg($order);
        $additionalCosts['labor_cost'] = $totalWeight * $laborRatePerKg;

        // Calculate cutting costs
        if ($order->cutting_fees_per_ton) {
            $additionalCosts['cutting_cost'] = ($order->cutting_fees_per_ton * $totalWeight) / 1000;
        } else {
            $cuttingRatePerKg = $this->getCuttingRatePerKg($order);
            $additionalCosts['cutting_cost'] = $totalWeight * $cuttingRatePerKg;
        }

        // Estimate transport cost
        $additionalCosts['transport_cost'] = $this->estimateTransportCost($order);

        // Estimate packaging cost
        $additionalCosts['packaging_cost'] = $this->estimatePackagingCost($order);

        // Round all costs
        foreach ($additionalCosts as $key => $cost) {
            $additionalCosts[$key] = round($cost, 2);
        }

        return $additionalCosts;
    }

    /**
     * Estimate waste costs based on material type and processing requirements
     */
    public function estimateWasteCosts(Order $order): float
    {
        $wastePercentage = $this->getWastePercentage($order);
        $basePrice = $this->calculateBasePrice($order);

        return round($basePrice * ($wastePercentage / 100), 2);
    }

    /**
     * Calculate overhead costs (fixed costs allocation)
     */
    public function calculateOverheadCosts(Order $order): float
    {
        // Get total overhead costs from historical data or configuration
        $totalOverheadCosts = $this->getTotalOverheadCosts();

        // Get total revenue from recent orders
        $totalRevenue = $this->getTotalRevenueFromRecentOrders();

        if ($totalRevenue > 0) {
            $overheadRate = $totalOverheadCosts / $totalRevenue;
            $estimatedRevenue = $this->calculateBasePrice($order) + $this->calculateAdditionalCosts($order)['labor_cost'];

            return round($estimatedRevenue * $overheadRate, 2);
        }

        // Fallback: use fixed overhead rate
        $fixedOverheadRate = 0.15; // 15% of base price
        $basePrice = $this->calculateBasePrice($order);

        return round($basePrice * $fixedOverheadRate, 2);
    }

    /**
     * Calculate optimal profit margin using historical data
     */
    public function calculateOptimalProfitMargin(Order $order): array
    {
        // Analyze historical profit margins for similar orders
        $historicalMargins = $this->analyzeHistoricalProfitMargins($order);

        if (!empty($historicalMargins)) {
            $averageMargin = collect($historicalMargins)->avg();
            $optimalMargin = $this->optimizeProfitMargin($averageMargin, $order);

            $basePrice = $this->calculateBasePrice($order);
            $additionalCosts = array_sum($this->calculateAdditionalCosts($order));
            $wasteCosts = $this->estimateWasteCosts($order);
            $overheadCosts = $this->calculateOverheadCosts($order);

            $costBase = $basePrice + $additionalCosts + $wasteCosts + $overheadCosts;
            $profitAmount = $costBase * ($optimalMargin / 100);

            return [
                'percentage' => round($optimalMargin, 2),
                'amount' => round($profitAmount, 2),
            ];
        }

        // Default profit margin
        $defaultMargin = 20.0; // 20%
        $basePrice = $this->calculateBasePrice($order);
        $additionalCosts = array_sum($this->calculateAdditionalCosts($order));
        $wasteCosts = $this->estimateWasteCosts($order);
        $overheadCosts = $this->calculateOverheadCosts($order);

        $costBase = $basePrice + $additionalCosts + $wasteCosts + $overheadCosts;
        $profitAmount = $costBase * ($defaultMargin / 100);

        return [
            'percentage' => $defaultMargin,
            'amount' => round($profitAmount, 2),
        ];
    }

    /**
     * Calculate total price
     */
    public function calculateTotalPrice(float $basePrice, array $additionalCosts, float $wasteCosts, float $overheadCosts, array $profitMargin): float
    {
        $totalCost = $basePrice + array_sum($additionalCosts) + $wasteCosts + $overheadCosts;
        $totalPrice = $totalCost + $profitMargin['amount'];

        return round($totalPrice, 2);
    }

    /**
     * Generate detailed price breakdown
     */
    public function generatePriceBreakdown(float $basePrice, array $additionalCosts, float $wasteCosts, float $overheadCosts, array $profitMargin, float $totalPrice): array
    {
        $totalCost = $basePrice + array_sum($additionalCosts) + $wasteCosts + $overheadCosts;

        return [
            'material_cost' => [
                'amount' => $basePrice,
                'percentage' => $totalCost > 0 ? round(($basePrice / $totalCost) * 100, 2) : 0,
                'description' => 'تكلفة المواد الأساسية'
            ],
            'labor_cost' => [
                'amount' => $additionalCosts['labor_cost'],
                'percentage' => $totalCost > 0 ? round(($additionalCosts['labor_cost'] / $totalCost) * 100, 2) : 0,
                'description' => 'تكلفة العمالة'
            ],
            'cutting_cost' => [
                'amount' => $additionalCosts['cutting_cost'],
                'percentage' => $totalCost > 0 ? round(($additionalCosts['cutting_cost'] / $totalCost) * 100, 2) : 0,
                'description' => 'تكلفة القص'
            ],
            'transport_cost' => [
                'amount' => $additionalCosts['transport_cost'],
                'percentage' => $totalCost > 0 ? round(($additionalCosts['transport_cost'] / $totalCost) * 100, 2) : 0,
                'description' => 'تكلفة النقل'
            ],
            'packaging_cost' => [
                'amount' => $additionalCosts['packaging_cost'],
                'percentage' => $totalCost > 0 ? round(($additionalCosts['packaging_cost'] / $totalCost) * 100, 2) : 0,
                'description' => 'تكلفة التعبئة'
            ],
            'waste_cost' => [
                'amount' => $wasteCosts,
                'percentage' => $totalCost > 0 ? round(($wasteCosts / $totalCost) * 100, 2) : 0,
                'description' => 'تكلفة الهدر المقدرة'
            ],
            'overhead_cost' => [
                'amount' => $overheadCosts,
                'percentage' => $totalCost > 0 ? round(($overheadCosts / $totalCost) * 100, 2) : 0,
                'description' => 'تكاليف عامة'
            ],
            'profit_margin' => [
                'amount' => $profitMargin['amount'],
                'percentage' => $profitMargin['percentage'],
                'description' => 'هامش الربح'
            ],
            'total_cost' => $totalCost,
            'total_price' => $totalPrice,
            'profit_percentage' => $totalPrice > 0 ? round(($profitMargin['amount'] / $totalPrice) * 100, 2) : 0,
        ];
    }

    /**
     * Estimate weight needed for an order item
     */
    private function estimateWeightNeeded($orderItem, Product $product): float
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
     * Calculate total required weight for order
     */
    private function calculateTotalRequiredWeight(Order $order): float
    {
        $totalWeight = 0;

        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            if ($product) {
                $totalWeight += $this->estimateWeightNeeded($orderItem, $product);
            }
        }

        return $totalWeight;
    }

    /**
     * Get labor rate per kg based on order complexity
     */
    private function getLaborRatePerKg(Order $order): float
    {
        // Base labor rate
        $baseRate = 0.5; // 0.5 per kg

        // Adjust based on material type
        $materialMultiplier = match($order->material_type) {
            'paper' => 1.0,
            'cardboard' => 1.2,
            'kraft' => 1.1,
            default => 1.0
        };

        // Adjust based on urgency
        $urgencyMultiplier = $order->is_urgent ? 1.5 : 1.0;

        return $baseRate * $materialMultiplier * $urgencyMultiplier;
    }

    /**
     * Get cutting rate per kg
     */
    private function getCuttingRatePerKg(Order $order): float
    {
        // Base cutting rate
        $baseRate = 0.3; // 0.3 per kg

        // Adjust based on complexity (number of different sizes)
        $complexityMultiplier = 1.0;
        if ($order->orderItems->count() > 1) {
            $complexityMultiplier = 1.2;
        }

        return $baseRate * $complexityMultiplier;
    }

    /**
     * Estimate transport cost
     */
    private function estimateTransportCost(Order $order): float
    {
        $totalWeight = $order->required_weight ?? $this->calculateTotalRequiredWeight($order);

        // Base transport rate per kg
        $transportRatePerKg = 0.1;

        // Minimum transport cost
        $minimumCost = 50;

        $calculatedCost = $totalWeight * $transportRatePerKg;

        return max($calculatedCost, $minimumCost);
    }

    /**
     * Estimate packaging cost
     */
    private function estimatePackagingCost(Order $order): float
    {
        $totalWeight = $order->required_weight ?? $this->calculateTotalRequiredWeight($order);

        // Packaging rate per kg
        $packagingRatePerKg = 0.05;

        return $totalWeight * $packagingRatePerKg;
    }

    /**
     * Get waste percentage based on material type and processing
     */
    private function getWastePercentage(Order $order): float
    {
        // Base waste percentage
        $baseWaste = 5.0; // 5%

        // Adjust based on material type
        $materialWaste = match($order->material_type) {
            'paper' => 3.0,
            'cardboard' => 7.0,
            'kraft' => 4.0,
            default => 5.0
        };

        // Adjust based on processing complexity
        $processingWaste = $order->orderItems->count() > 1 ? 2.0 : 0;

        return $baseWaste + $materialWaste + $processingWaste;
    }

    /**
     * Get total overhead costs from configuration or historical data
     */
    private function getTotalOverheadCosts(): float
    {
        // This could be from configuration or calculated from historical data
        // For now, return a fixed amount
        return 10000; // Monthly overhead costs
    }

    /**
     * Get total revenue from recent orders
     */
    private function getTotalRevenueFromRecentOrders(): float
    {
        // Get revenue from last 30 days
        return Order::where('created_at', '>=', now()->subDays(30))
                   ->whereNotNull('final_price')
                   ->sum('final_price');
    }

    /**
     * Analyze historical profit margins for similar orders
     */
    private function analyzeHistoricalProfitMargins(Order $order): array
    {
        $similarOrders = Order::where('material_type', $order->material_type)
                             ->where('created_at', '>=', now()->subMonths(6))
                             ->whereNotNull('profit_margin_percentage')
                             ->where('profit_margin_percentage', '>', 0)
                             ->pluck('profit_margin_percentage')
                             ->toArray();

        return $similarOrders;
    }

    /**
     * Optimize profit margin based on market conditions and order characteristics
     */
    private function optimizeProfitMargin(float $averageMargin, Order $order): float
    {
        $optimizedMargin = $averageMargin;

        // Adjust based on order size (larger orders get better margins)
        $totalWeight = $order->required_weight ?? $this->calculateTotalRequiredWeight($order);
        if ($totalWeight > 1000) {
            $optimizedMargin += 2; // Increase margin for large orders
        } elseif ($totalWeight < 100) {
            $optimizedMargin -= 1; // Decrease margin for small orders
        }

        // Adjust based on urgency
        if ($order->is_urgent) {
            $optimizedMargin += 5; // Higher margin for urgent orders
        }

        // Ensure margin is within reasonable bounds
        return max(10, min(40, $optimizedMargin)); // Between 10% and 40%
    }
}