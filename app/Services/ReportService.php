<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Category;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Generate inventory report
     */
    public function generateInventoryReport(?int $warehouseId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Stock::with(['product.category', 'warehouse'])
            ->where('is_active', true);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($dateFrom) {
            $query->where('updated_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('updated_at', '<=', $dateTo);
        }

        $stocks = $query->get();

        $report = [
            'summary' => [
                'total_products' => $stocks->count(),
                'total_quantity' => $stocks->sum('quantity'),
                'total_value' => $stocks->sum(function ($stock) {
                    return $stock->quantity * $stock->unit_cost;
                }),
                'low_stock_items' => $stocks->filter(function ($stock) {
                    return $stock->quantity <= $stock->product->min_stock_level;
                })->count(),
                'out_of_stock_items' => $stocks->filter(function ($stock) {
                    return $stock->quantity <= 0;
                })->count(),
            ],
            'by_category' => $this->groupStocksByCategory($stocks),
            'by_warehouse' => $this->groupStocksByWarehouse($stocks),
            'top_products' => $this->getTopProducts($stocks),
            'low_stock_products' => $this->getLowStockProducts($stocks),
        ];

        return $report;
    }

    /**
     * Generate sales report
     */
    public function generateSalesReport(?int $warehouseId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $dateTo ?: now()->endOfMonth()->format('Y-m-d');

        $query = Order::with(['orderItems.product', 'warehouse'])
            ->where('type', 'out')
            ->whereBetween('order_date', [$dateFrom, $dateTo]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $orders = $query->get();

        $report = [
            'summary' => [
                'total_orders' => $orders->count(),
                'total_sales' => $orders->sum('total_amount'),
                'total_items_sold' => $orders->sum(function ($order) {
                    return $order->orderItems->sum('quantity');
                }),
                'average_order_value' => $orders->count() > 0 ? $orders->sum('total_amount') / $orders->count() : 0,
            ],
            'by_product' => $this->groupSalesByProduct($orders),
            'by_category' => $this->groupSalesByCategory($orders),
            'daily_sales' => $this->getDailySales($orders, $dateFrom, $dateTo),
            'top_customers' => $this->getTopCustomers($orders),
        ];

        return $report;
    }

    /**
     * Generate purchase report
     */
    public function generatePurchaseReport(?int $warehouseId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $dateTo ?: now()->endOfMonth()->format('Y-m-d');

        $query = Order::with(['orderItems.product', 'warehouse', 'supplier'])
            ->where('type', 'in')
            ->whereBetween('order_date', [$dateFrom, $dateTo]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $orders = $query->get();

        $report = [
            'summary' => [
                'total_orders' => $orders->count(),
                'total_purchases' => $orders->sum('total_amount'),
                'total_items_purchased' => $orders->sum(function ($order) {
                    return $order->orderItems->sum('quantity');
                }),
                'average_order_value' => $orders->count() > 0 ? $orders->sum('total_amount') / $orders->count() : 0,
            ],
            'by_supplier' => $this->groupPurchasesBySupplier($orders),
            'by_product' => $this->groupPurchasesByProduct($orders),
            'daily_purchases' => $this->getDailyPurchases($orders, $dateFrom, $dateTo),
        ];

        return $report;
    }

    /**
     * Generate warehouse utilization report
     */
    public function generateWarehouseUtilizationReport(): array
    {
        $warehouses = Warehouse::with(['stocks.product'])->get();

        $report = [];

        foreach ($warehouses as $warehouse) {
            $totalCapacity = $warehouse->total_capacity;
            $usedCapacity = $warehouse->used_capacity;
            $availableCapacity = $totalCapacity - $usedCapacity;
            $utilizationPercentage = $totalCapacity > 0 ? ($usedCapacity / $totalCapacity) * 100 : 0;

            $report[] = [
                'warehouse' => $warehouse,
                'total_capacity' => $totalCapacity,
                'used_capacity' => $usedCapacity,
                'available_capacity' => $availableCapacity,
                'utilization_percentage' => round($utilizationPercentage, 2),
                'product_count' => $warehouse->stocks->count(),
                'total_value' => $warehouse->stocks->sum(function ($stock) {
                    return $stock->quantity * $stock->unit_cost;
                }),
            ];
        }

        return [
            'warehouses' => $report,
            'overall_utilization' => $this->calculateOverallUtilization($report),
        ];
    }

    /**
     * Generate stock alert report
     */
    public function generateStockAlertReport(): array
    {
        $alerts = \App\Models\StockAlert::with(['product', 'warehouse'])
            ->where('is_resolved', false)
            ->get();

        return [
            'total_alerts' => $alerts->count(),
            'by_type' => $alerts->groupBy('type')->map->count(),
            'by_severity' => $alerts->groupBy('severity')->map->count(),
            'by_warehouse' => $alerts->groupBy('warehouse.name')->map->count(),
            'critical_alerts' => $alerts->where('severity', 'critical')->values(),
            'high_priority_alerts' => $alerts->whereIn('severity', ['high', 'critical'])->values(),
        ];
    }

    /**
     * Generate profit report
     */
    public function generateProfitReport(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: now()->startOfMonth()->format('Y-m-d');
        $dateTo = $dateTo ?: now()->endOfMonth()->format('Y-m-d');

        $salesOrders = Order::with(['orderItems.product'])
            ->where('type', 'out')
            ->whereBetween('order_date', [$dateFrom, $dateTo])
            ->get();

        $totalSales = $salesOrders->sum('total_amount');
        $totalCost = 0;
        $totalProfit = 0;

        foreach ($salesOrders as $order) {
            foreach ($order->orderItems as $item) {
                $product = $item->product;
                if ($product) {
                    $cost = $product->purchase_price * $item->quantity;
                    $revenue = $item->total_price;
                    $totalCost += $cost;
                    $totalProfit += ($revenue - $cost);
                }
            }
        }

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'summary' => [
                'total_sales' => $totalSales,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'profit_margin' => $totalCost > 0 ? round(($totalProfit / $totalCost) * 100, 2) : 0,
            ],
            'by_product' => $this->groupProfitByProduct($salesOrders),
            'daily_profit' => $this->getDailyProfit($salesOrders, $dateFrom, $dateTo),
        ];
    }

    /**
     * Helper method to group stocks by category
     */
    private function groupStocksByCategory($stocks): array
    {
        return $stocks->groupBy(function ($stock) {
            return $stock->product->category->name ?? 'Uncategorized';
        })->map(function ($group) {
            return [
                'quantity' => $group->sum('quantity'),
                'value' => $group->sum(function ($stock) {
                    return $stock->quantity * $stock->unit_cost;
                }),
                'product_count' => $group->count(),
            ];
        })->toArray();
    }

    /**
     * Helper method to group stocks by warehouse
     */
    private function groupStocksByWarehouse($stocks): array
    {
        return $stocks->groupBy(function ($stock) {
            return $stock->warehouse->name ?? 'Unknown Warehouse';
        })->map(function ($group) {
            return [
                'quantity' => $group->sum('quantity'),
                'value' => $group->sum(function ($stock) {
                    return $stock->quantity * $stock->unit_cost;
                }),
                'product_count' => $group->count(),
            ];
        })->toArray();
    }

    /**
     * Helper method to get top products by stock value
     */
    private function getTopProducts($stocks, int $limit = 10): array
    {
        return $stocks->sortByDesc(function ($stock) {
            return $stock->quantity * $stock->unit_cost;
        })->take($limit)->map(function ($stock) {
            return [
                'product' => $stock->product,
                'warehouse' => $stock->warehouse,
                'quantity' => $stock->quantity,
                'unit_cost' => $stock->unit_cost,
                'total_value' => $stock->quantity * $stock->unit_cost,
            ];
        })->values()->toArray();
    }

    /**
     * Helper method to get low stock products
     */
    private function getLowStockProducts($stocks): array
    {
        return $stocks->filter(function ($stock) {
            return $stock->quantity <= $stock->product->min_stock_level;
        })->map(function ($stock) {
            return [
                'product' => $stock->product,
                'warehouse' => $stock->warehouse,
                'current_quantity' => $stock->quantity,
                'min_required' => $stock->product->min_stock_level,
                'shortage' => $stock->product->min_stock_level - $stock->quantity,
            ];
        })->values()->toArray();
    }

    /**
     * Helper method to group sales by product
     */
    private function groupSalesByProduct($orders): array
    {
        $productSales = collect();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $existing = $productSales->where('product_id', $item->product_id)->first();

                if ($existing) {
                    $existing['quantity'] += $item->quantity;
                    $existing['total'] += $item->total_price;
                } else {
                    $productSales->push([
                        'product_id' => $item->product_id,
                        'product' => $item->product,
                        'quantity' => $item->quantity,
                        'total' => $item->total_price,
                    ]);
                }
            }
        }

        return $productSales->sortByDesc('total')->take(10)->values()->toArray();
    }

    /**
     * Helper method to group sales by category
     */
    private function groupSalesByCategory($orders): array
    {
        $categorySales = collect();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $categoryName = $item->product->category->name ?? 'Uncategorized';
                $existing = $categorySales->where('category_name', $categoryName)->first();

                if ($existing) {
                    $existing['quantity'] += $item->quantity;
                    $existing['total'] += $item->total_price;
                } else {
                    $categorySales->push([
                        'category_name' => $categoryName,
                        'category' => $item->product->category,
                        'quantity' => $item->quantity,
                        'total' => $item->total_price,
                    ]);
                }
            }
        }

        return $categorySales->sortByDesc('total')->values()->toArray();
    }

    /**
     * Helper method to get daily sales
     */
    private function getDailySales($orders, string $dateFrom, string $dateTo): array
    {
        $dailySales = collect();

        $period = \Carbon\CarbonPeriod::create($dateFrom, $dateTo);

        foreach ($period as $date) {
            $dayOrders = $orders->where('order_date', $date->format('Y-m-d'));
            $dailySales->push([
                'date' => $date->format('Y-m-d'),
                'orders_count' => $dayOrders->count(),
                'total_amount' => $dayOrders->sum('total_amount'),
                'items_count' => $dayOrders->sum(function ($order) {
                    return $order->orderItems->sum('quantity');
                }),
            ]);
        }

        return $dailySales->toArray();
    }

    /**
     * Helper method to get top customers
     */
    private function getTopCustomers($orders, int $limit = 10): array
    {
        $customers = collect();

        foreach ($orders as $order) {
            if ($order->customer_name) {
                $existing = $customers->where('name', $order->customer_name)->first();

                if ($existing) {
                    $existing['total_purchases'] += $order->total_amount;
                    $existing['order_count']++;
                } else {
                    $customers->push([
                        'name' => $order->customer_name,
                        'phone' => $order->customer_phone,
                        'total_purchases' => $order->total_amount,
                        'order_count' => 1,
                    ]);
                }
            }
        }

        return $customers->sortByDesc('total_purchases')->take($limit)->values()->toArray();
    }

    /**
     * Helper method to group purchases by supplier
     */
    private function groupPurchasesBySupplier($orders): array
    {
        $supplierPurchases = collect();

        foreach ($orders as $order) {
            if ($order->supplier) {
                $existing = $supplierPurchases->where('supplier_id', $order->supplier_id)->first();

                if ($existing) {
                    $existing['total'] += $order->total_amount;
                    $existing['order_count']++;
                } else {
                    $supplierPurchases->push([
                        'supplier_id' => $order->supplier_id,
                        'supplier' => $order->supplier,
                        'total' => $order->total_amount,
                        'order_count' => 1,
                    ]);
                }
            }
        }

        return $supplierPurchases->sortByDesc('total')->values()->toArray();
    }

    /**
     * Helper method to group purchases by product
     */
    private function groupPurchasesByProduct($orders): array
    {
        $productPurchases = collect();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $existing = $productPurchases->where('product_id', $item->product_id)->first();

                if ($existing) {
                    $existing['quantity'] += $item->quantity;
                    $existing['total'] += $item->total_price;
                } else {
                    $productPurchases->push([
                        'product_id' => $item->product_id,
                        'product' => $item->product,
                        'quantity' => $item->quantity,
                        'total' => $item->total_price,
                    ]);
                }
            }
        }

        return $productPurchases->sortByDesc('total')->take(10)->values()->toArray();
    }

    /**
     * Helper method to get daily purchases
     */
    private function getDailyPurchases($orders, string $dateFrom, string $dateTo): array
    {
        $dailyPurchases = collect();

        $period = \Carbon\CarbonPeriod::create($dateFrom, $dateTo);

        foreach ($period as $date) {
            $dayOrders = $orders->where('order_date', $date->format('Y-m-d'));
            $dailyPurchases->push([
                'date' => $date->format('Y-m-d'),
                'orders_count' => $dayOrders->count(),
                'total_amount' => $dayOrders->sum('total_amount'),
                'items_count' => $dayOrders->sum(function ($order) {
                    return $order->orderItems->sum('quantity');
                }),
            ]);
        }

        return $dailyPurchases->toArray();
    }

    /**
     * Helper method to calculate overall utilization
     */
    private function calculateOverallUtilization(array $warehouseData): array
    {
        $totalCapacity = collect($warehouseData)->sum('total_capacity');
        $usedCapacity = collect($warehouseData)->sum('used_capacity');

        return [
            'total_capacity' => $totalCapacity,
            'used_capacity' => $usedCapacity,
            'available_capacity' => $totalCapacity - $usedCapacity,
            'utilization_percentage' => $totalCapacity > 0 ? round(($usedCapacity / $totalCapacity) * 100, 2) : 0,
        ];
    }

    /**
     * Helper method to group profit by product
     */
    private function groupProfitByProduct($orders): array
    {
        $productProfits = collect();

        foreach ($orders as $order) {
            foreach ($order->orderItems as $item) {
                $product = $item->product;
                if ($product) {
                    $cost = $product->purchase_price * $item->quantity;
                    $revenue = $item->total_price;
                    $profit = $revenue - $cost;

                    $existing = $productProfits->where('product_id', $product->id)->first();

                    if ($existing) {
                        $existing['total_revenue'] += $revenue;
                        $existing['total_cost'] += $cost;
                        $existing['total_profit'] += $profit;
                        $existing['total_quantity'] += $item->quantity;
                    } else {
                        $productProfits->push([
                            'product_id' => $product->id,
                            'product' => $product,
                            'total_revenue' => $revenue,
                            'total_cost' => $cost,
                            'total_profit' => $profit,
                            'total_quantity' => $item->quantity,
                            'profit_margin' => $cost > 0 ? round(($profit / $cost) * 100, 2) : 0,
                        ]);
                    }
                }
            }
        }

        return $productProfits->sortByDesc('total_profit')->take(10)->values()->toArray();
    }

    /**
     * Helper method to get daily profit
     */
    private function getDailyProfit($orders, string $dateFrom, string $dateTo): array
    {
        $dailyProfits = collect();

        $period = \Carbon\CarbonPeriod::create($dateFrom, $dateTo);

        foreach ($period as $date) {
            $dayOrders = $orders->where('order_date', $date->format('Y-m-d'));

            $dayRevenue = 0;
            $dayCost = 0;

            foreach ($dayOrders as $order) {
                foreach ($order->orderItems as $item) {
                    $product = $item->product;
                    if ($product) {
                        $dayRevenue += $item->total_price;
                        $dayCost += $product->purchase_price * $item->quantity;
                    }
                }
            }

            $dailyProfits->push([
                'date' => $date->format('Y-m-d'),
                'revenue' => $dayRevenue,
                'cost' => $dayCost,
                'profit' => $dayRevenue - $dayCost,
                'profit_margin' => $dayCost > 0 ? round((($dayRevenue - $dayCost) / $dayCost) * 100, 2) : 0,
            ]);
        }

        return $dailyProfits->toArray();
    }
}