<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutomatedInventoryService
{
    /**
     * Monitor inventory and auto reorder when needed
     */
    public function monitorAndAutoReorder(): array
    {
        try {
            $results = [
                'low_stock_items' => [],
                'auto_orders_created' => [],
                'alerts_sent' => [],
                'timestamp' => now(),
            ];

            // Identify low stock items
            $lowStockItems = $this->identifyLowStockItems();

            foreach ($lowStockItems as $item) {
                $product = $item['product'];
                $stock = $item['stock'];

                // Check if auto reorder is needed
                if ($this->shouldAutoReorder($product, $stock)) {
                    $orderResult = $this->createAutoPurchaseOrder($product, $stock);
                    if ($orderResult) {
                        $results['auto_orders_created'][] = $orderResult;
                    }
                }

                // Send alert regardless
                $alertResult = $this->sendLowStockAlert($product, $stock);
                if ($alertResult) {
                    $results['alerts_sent'][] = $alertResult;
                }

                $results['low_stock_items'][] = $item;
            }

            // Log the monitoring results
            Log::info('Automated inventory monitoring completed', [
                'low_stock_count' => count($results['low_stock_items']),
                'auto_orders_count' => count($results['auto_orders_created']),
                'alerts_count' => count($results['alerts_sent']),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Automated inventory monitoring failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'error' => 'Inventory monitoring failed: ' . $e->getMessage(),
                'low_stock_items' => [],
                'auto_orders_created' => [],
                'alerts_sent' => [],
            ];
        }
    }

    /**
     * Identify items with low stock levels
     */
    public function identifyLowStockItems(): array
    {
        $lowStockItems = [];

        // Get all active products with inventory tracking
        $products = Product::where('is_active', true)
                          ->where('track_inventory', true)
                          ->with(['stocks' => function($query) {
                              $query->where('is_active', true);
                          }])
                          ->get();

        foreach ($products as $product) {
            foreach ($product->stocks as $stock) {
                $availableQuantity = $stock->available_quantity;

                // Check if below minimum stock level
                if ($availableQuantity <= $product->min_stock_level) {
                    $lowStockItems[] = [
                        'product' => $product,
                        'stock' => $stock,
                        'available_quantity' => $availableQuantity,
                        'min_stock_level' => $product->min_stock_level,
                        'severity' => $this->calculateStockSeverity($availableQuantity, $product->min_stock_level),
                        'warehouse' => $stock->warehouse,
                    ];
                }
            }
        }

        // Sort by severity (most critical first)
        usort($lowStockItems, function($a, $b) {
            return $b['severity'] <=> $a['severity'];
        });

        return $lowStockItems;
    }

    /**
     * Create automatic purchase order
     */
    public function createAutoPurchaseOrder(Product $product, Stock $stock): ?array
    {
        try {
            // Calculate reorder quantity
            $reorderQuantity = $this->calculateReorderQuantity($product, $stock);

            if ($reorderQuantity <= 0) {
                return null;
            }

            // Find supplier
            $supplier = $product->supplier;
            if (!$supplier) {
                Log::warning('No supplier found for auto reorder', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                ]);
                return null;
            }

            // Create purchase order
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'type' => 'in', // Purchase order
                'status' => 'pending',
                'supplier_id' => $supplier->id,
                'warehouse_id' => $stock->warehouse_id,
                'order_date' => now(),
                'notes' => 'Auto-generated purchase order for low stock',
                'is_urgent' => $this->isUrgentReorder($product, $stock),
            ]);

            // Create order item
            $order->orderItems()->create([
                'product_id' => $product->id,
                'quantity' => $reorderQuantity,
                'unit_price' => $product->purchase_price,
                'total_price' => $reorderQuantity * $product->purchase_price,
            ]);

            Log::info('Auto purchase order created', [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $reorderQuantity,
            ]);

            return [
                'order' => $order,
                'product' => $product,
                'quantity' => $reorderQuantity,
                'supplier' => $supplier,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create auto purchase order', [
                'product_id' => $product->id,
                'stock_id' => $stock->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send low stock alert notifications
     */
    public function sendLowStockAlert(Product $product, Stock $stock): ?array
    {
        try {
            // Here you would implement actual notification sending
            // For now, we'll just log it and return success

            $alertData = [
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                'current_stock' => $stock->available_quantity,
                'min_stock_level' => $product->min_stock_level,
                'location' => $stock->location,
                'severity' => $this->calculateStockSeverity($stock->available_quantity, $product->min_stock_level),
            ];

            // Log the alert
            Log::warning('Low stock alert', $alertData);

            // TODO: Implement actual email/SMS notifications
            // Mail::to(config('app.inventory_manager_email'))->send(new LowStockAlert($alertData));

            return $alertData;

        } catch (\Exception $e) {
            Log::error('Failed to send low stock alert', [
                'product_id' => $product->id,
                'stock_id' => $stock->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Predict demand for products
     */
    public function predictDemand(Product $product, int $daysAhead = 30): array
    {
        try {
            $historicalData = $this->analyzeHistoricalDemand($product);

            // Simple moving average prediction
            $avgDailyDemand = $historicalData['avg_daily_demand'] ?? 0;

            // Apply trend factor
            $trendFactor = $this->calculateDemandTrend($product);
            $predictedDemand = $avgDailyDemand * $daysAhead * $trendFactor;

            // Calculate confidence interval
            $confidence = $this->calculatePredictionConfidence($historicalData);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'predicted_demand' => round($predictedDemand, 2),
                'period_days' => $daysAhead,
                'avg_daily_demand' => $avgDailyDemand,
                'trend_factor' => $trendFactor,
                'confidence_level' => $confidence,
                'forecast_date' => now()->addDays($daysAhead),
            ];

        } catch (\Exception $e) {
            Log::error('Demand prediction failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'product_id' => $product->id,
                'error' => 'Prediction failed: ' . $e->getMessage(),
                'predicted_demand' => 0,
            ];
        }
    }

    /**
     * Analyze historical demand data
     */
    public function analyzeHistoricalDemand(Product $product, int $months = 6): array
    {
        try {
            $startDate = now()->subMonths($months);

            // Get order items for this product in the period
            $orderItems = OrderItem::where('product_id', $product->id)
                                  ->whereHas('order', function($query) use ($startDate) {
                                      $query->where('created_at', '>=', $startDate)
                                            ->where('type', 'out'); // Sales orders
                                  })
                                  ->with('order')
                                  ->get();

            $totalQuantity = $orderItems->sum('quantity');
            $totalOrders = $orderItems->count();
            $daysInPeriod = now()->diffInDays($startDate);

            $avgDailyDemand = $daysInPeriod > 0 ? $totalQuantity / $daysInPeriod : 0;
            $avgOrderSize = $totalOrders > 0 ? $totalQuantity / $totalOrders : 0;

            // Calculate demand variance
            $dailyDemands = [];
            $currentDate = $startDate->copy();

            while ($currentDate <= now()) {
                $dayQuantity = $orderItems->where('order.created_at', '>=', $currentDate->startOfDay())
                                         ->where('order.created_at', '<=', $currentDate->endOfDay())
                                         ->sum('quantity');
                $dailyDemands[] = $dayQuantity;
                $currentDate->addDay();
            }

            $demandVariance = $this->calculateVariance($dailyDemands);

            return [
                'product_id' => $product->id,
                'period_months' => $months,
                'total_quantity_sold' => $totalQuantity,
                'total_orders' => $totalOrders,
                'avg_daily_demand' => round($avgDailyDemand, 2),
                'avg_order_size' => round($avgOrderSize, 2),
                'demand_variance' => round($demandVariance, 2),
                'demand_std_dev' => round(sqrt($demandVariance), 2),
                'period_days' => $daysInPeriod,
            ];

        } catch (\Exception $e) {
            Log::error('Historical demand analysis failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'product_id' => $product->id,
                'error' => 'Analysis failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Forecast demand for multiple products
     */
    public function forecastDemand(array $productIds = null, int $daysAhead = 30): array
    {
        $forecasts = [];

        $query = Product::where('is_active', true)
                       ->where('track_inventory', true);

        if ($productIds) {
            $query->whereIn('id', $productIds);
        }

        $products = $query->get();

        foreach ($products as $product) {
            $forecasts[] = $this->predictDemand($product, $daysAhead);
        }

        // Sort by predicted demand (highest first)
        usort($forecasts, function($a, $b) {
            return $b['predicted_demand'] <=> $a['predicted_demand'];
        });

        return $forecasts;
    }

    /**
     * Suggest optimal stock levels based on demand analysis
     */
    public function suggestOptimalStockLevels(Product $product): array
    {
        try {
            $historicalData = $this->analyzeHistoricalDemand($product);
            $currentStock = $product->stocks()->sum('quantity');

            // Calculate safety stock (2 weeks worth)
            $safetyStock = $historicalData['avg_daily_demand'] * 14;

            // Calculate reorder point (lead time + safety stock)
            $leadTimeDays = $this->estimateLeadTime($product);
            $reorderPoint = ($historicalData['avg_daily_demand'] * $leadTimeDays) + $safetyStock;

            // Calculate optimal max stock (reorder point + economic order quantity)
            $eoq = $this->calculateEOQ($product, $historicalData);
            $optimalMax = $reorderPoint + $eoq;

            // Calculate current stock status
            $stockStatus = $this->assessStockStatus($currentStock, $reorderPoint, $optimalMax);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'current_stock' => $currentStock,
                'suggested_min_stock' => round($reorderPoint, 2),
                'suggested_max_stock' => round($optimalMax, 2),
                'safety_stock' => round($safetyStock, 2),
                'economic_order_quantity' => round($eoq, 2),
                'lead_time_days' => $leadTimeDays,
                'stock_status' => $stockStatus,
                'recommendation' => $this->generateStockRecommendation($stockStatus, $currentStock, $reorderPoint, $optimalMax),
            ];

        } catch (\Exception $e) {
            Log::error('Stock level suggestion failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'product_id' => $product->id,
                'error' => 'Suggestion failed: ' . $e->getMessage(),
            ];
        }
    }

    // Helper methods

    private function calculateStockSeverity(int $available, int $minLevel): string
    {
        if ($available <= 0) return 'critical';
        if ($available <= $minLevel * 0.5) return 'high';
        if ($available <= $minLevel) return 'medium';
        return 'low';
    }

    private function shouldAutoReorder(Product $product, Stock $stock): bool
    {
        // Only auto reorder if enabled and stock is critically low
        return $product->auto_reorder ?? false &&
               $stock->available_quantity <= ($product->min_stock_level * 0.5);
    }

    private function calculateReorderQuantity(Product $product, Stock $stock): float
    {
        $currentStock = $stock->available_quantity;
        $maxStock = $product->max_stock_level;

        // Reorder to max level
        $reorderQuantity = $maxStock - $currentStock;

        // Ensure minimum order quantity
        $minOrder = $product->min_order_quantity ?? 1;
        return max($reorderQuantity, $minOrder);
    }

    private function isUrgentReorder(Product $product, Stock $stock): bool
    {
        return $stock->available_quantity <= ($product->min_stock_level * 0.2);
    }

    private function calculateDemandTrend(Product $product): float
    {
        // Simple trend calculation - compare recent vs older periods
        $recent = $this->analyzeHistoricalDemand($product, 1);
        $older = $this->analyzeHistoricalDemand($product, 3);

        if ($older['avg_daily_demand'] == 0) return 1.0;

        $trend = $recent['avg_daily_demand'] / $older['avg_daily_demand'];
        return max(0.5, min(2.0, $trend)); // Limit trend factor between 0.5 and 2.0
    }

    private function calculatePredictionConfidence(array $historicalData): float
    {
        // Simple confidence calculation based on data consistency
        $variance = $historicalData['demand_variance'] ?? 0;
        $mean = $historicalData['avg_daily_demand'] ?? 1;

        if ($mean == 0) return 0.0;

        $cv = sqrt($variance) / $mean; // Coefficient of variation

        // Convert to confidence percentage (lower CV = higher confidence)
        return max(0.0, min(1.0, 1 - $cv));
    }

    private function calculateVariance(array $values): float
    {
        if (empty($values)) return 0;

        $mean = array_sum($values) / count($values);
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return $variance / count($values);
    }

    private function estimateLeadTime(Product $product): int
    {
        // Estimate lead time based on supplier or default to 7 days
        return $product->supplier->lead_time_days ?? 7;
    }

    private function calculateEOQ(Product $product, array $historicalData): float
    {
        // Economic Order Quantity formula
        // EOQ = sqrt(2 * Annual Demand * Ordering Cost / Holding Cost)

        $annualDemand = $historicalData['avg_daily_demand'] * 365;
        $orderingCost = $product->ordering_cost ?? 100; // Default ordering cost
        $holdingCost = $product->purchase_price * 0.2; // Assume 20% annual holding cost

        if ($holdingCost == 0) return 0;

        return sqrt((2 * $annualDemand * $orderingCost) / $holdingCost);
    }

    private function assessStockStatus(float $current, float $min, float $max): string
    {
        if ($current <= 0) return 'out_of_stock';
        if ($current <= $min) return 'low_stock';
        if ($current >= $max) return 'overstocked';
        return 'optimal';
    }

    private function generateStockRecommendation(string $status, float $current, float $min, float $max): string
    {
        switch ($status) {
            case 'out_of_stock':
                return 'Urgent: Reorder immediately';
            case 'low_stock':
                return 'Reorder soon to reach optimal levels';
            case 'overstocked':
                return 'Consider reducing stock or adjusting reorder point';
            case 'optimal':
                return 'Stock levels are optimal';
            default:
                return 'Review stock levels';
        }
    }

    /**
     * Run automated inventory management
     */
    public function run(): array
    {
        try {
            $result = $this->monitorAndAutoReorder();

            return [
                'success' => $result['success'] ?? false,
                'low_stock_items' => $result['low_stock_items'] ?? [],
                'auto_orders_created' => $result['auto_orders_created'] ?? [],
                'alerts_sent' => $result['alerts_sent'] ?? [],
                'timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Automated inventory management run failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'low_stock_items' => [],
                'auto_orders_created' => [],
                'alerts_sent' => []
            ];
        }
    }
}