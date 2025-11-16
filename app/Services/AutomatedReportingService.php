<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Waste;
use App\Models\Invoice;
use App\Models\StockAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutomatedReportingService
{
    /**
     * Generate daily reports
     */
    public function generateDailyReports(): array
    {
        try {
            $today = now()->toDateString();
            $reports = [];

            // Production report
            $reports['production'] = $this->generateProductionReport($today, $today);

            // Waste report
            $reports['waste'] = $this->generateWasteReport($today, $today);

            // Sales report
            $reports['sales'] = $this->generateSalesReport($today, $today);

            // Efficiency report
            $reports['efficiency'] = $this->generateEfficiencyReport($today, $today);

            Log::info('Daily reports generated successfully', [
                'date' => $today,
                'reports_count' => count($reports)
            ]);

            return [
                'success' => true,
                'date' => $today,
                'reports' => $reports,
                'timestamp' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate daily reports', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to generate daily reports: ' . $e->getMessage(),
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Generate production report
     */
    public function generateProductionReport(string $startDate, string $endDate): array
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Get orders completed in the period
            $completedOrders = Order::whereBetween('completed_at', [$start, $end])
                                   ->where('status', 'delivered')
                                   ->with(['orderItems.product', 'warehouse'])
                                   ->get();

            // Calculate production metrics
            $totalOrders = $completedOrders->count();
            $totalWeight = $completedOrders->sum('delivered_weight');
            $totalValue = $completedOrders->sum('total_amount');

            // Group by warehouse
            $warehouseStats = $completedOrders->groupBy('warehouse_id')->map(function ($orders) {
                return [
                    'warehouse_name' => $orders->first()->warehouse->name ?? 'Unknown',
                    'orders_count' => $orders->count(),
                    'total_weight' => $orders->sum('delivered_weight'),
                    'total_value' => $orders->sum('total_amount'),
                ];
            });

            // Group by product type
            $productStats = [];
            foreach ($completedOrders as $order) {
                foreach ($order->orderItems as $item) {
                    $productType = $item->product->type ?? 'unknown';
                    if (!isset($productStats[$productType])) {
                        $productStats[$productType] = [
                            'type' => $productType,
                            'quantity' => 0,
                            'weight' => 0,
                            'value' => 0,
                        ];
                    }
                    $productStats[$productType]['quantity'] += $item->quantity;
                    $productStats[$productType]['weight'] += $item->weight ?? 0;
                    $productStats[$productType]['value'] += $item->total_price;
                }
            }

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_orders' => $totalOrders,
                    'total_weight_kg' => round($totalWeight, 2),
                    'total_value' => round($totalValue, 2),
                    'average_order_value' => $totalOrders > 0 ? round($totalValue / $totalOrders, 2) : 0,
                ],
                'warehouse_breakdown' => $warehouseStats->values(),
                'product_breakdown' => array_values($productStats),
                'generated_at' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate production report', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to generate production report: ' . $e->getMessage(),
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            ];
        }
    }

    /**
     * Generate waste report
     */
    public function generateWasteReport(string $startDate, string $endDate): array
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Get waste records in the period - use created_at as fallback
            try {
                // Try to use waste_date if it exists
                $wasteRecords = Waste::whereBetween('waste_date', [$start, $end])
                                    ->with('product')
                                    ->get();
            } catch (\Exception $e) {
                // Fallback to created_at if waste_date doesn't exist
                Log::info('Waste report: waste_date column not found, using created_at', [
                    'error' => $e->getMessage()
                ]);
                $wasteRecords = Waste::whereBetween('created_at', [$start, $end])
                                    ->with('product')
                                    ->get();
            }

            // Calculate waste metrics
            $totalWaste = $wasteRecords->sum('quantity');
            $wasteByReason = $wasteRecords->groupBy('reason')->map(function ($records) {
                return [
                    'reason' => $records->first()->reason,
                    'quantity' => $records->sum('quantity'),
                    'count' => $records->count(),
                    'percentage' => 0, // Will be calculated below
                ];
            });

            // Calculate percentages
            if ($totalWaste > 0) {
                $wasteByReason = $wasteByReason->map(function ($item) use ($totalWaste) {
                    $item['percentage'] = round(($item['quantity'] / $totalWaste) * 100, 2);
                    return $item;
                });
            }

            // Waste by product
            $wasteByProduct = $wasteRecords->groupBy('product_id')->map(function ($records) {
                $product = $records->first()->product;
                return [
                    'product_name' => $product->name ?? 'Unknown',
                    'product_sku' => $product->sku ?? 'N/A',
                    'quantity' => $records->sum('quantity'),
                    'count' => $records->count(),
                ];
            });

            // Calculate waste ratio (waste vs production)
            $productionWeight = Order::whereBetween('completed_at', [$start, $end])
                                    ->where('status', 'delivered')
                                    ->sum('delivered_weight');

            $wasteRatio = $productionWeight > 0 ? round(($totalWaste / $productionWeight) * 100, 2) : 0;

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_waste' => $totalWaste,
                    'waste_records_count' => $wasteRecords->count(),
                    'waste_ratio_percentage' => $wasteRatio,
                    'production_weight_kg' => round($productionWeight, 2),
                ],
                'waste_by_reason' => $wasteByReason->values(),
                'waste_by_product' => $wasteByProduct->values(),
                'generated_at' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate waste report', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to generate waste report: ' . $e->getMessage(),
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            ];
        }
    }

    /**
     * Generate efficiency report
     */
    public function generateEfficiencyReport(string $startDate, string $endDate): array
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Get orders processed in the period
            $orders = Order::whereBetween('created_at', [$start, $end])
                          ->with(['orderProcessings', 'stageHistory'])
                          ->get();

            // Calculate processing times
            $processingStats = [];
            $stageEfficiency = [];

            foreach ($orders as $order) {
                $createdAt = $order->created_at;
                $completedAt = $order->completed_at;

                if ($createdAt && $completedAt) {
                    $totalTime = $createdAt->diffInHours($completedAt);
                    $processingStats[] = [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_processing_hours' => $totalTime,
                        'is_urgent' => $order->is_urgent,
                        'status' => $order->status,
                    ];
                }

                // Stage efficiency
                foreach ($order->orderProcessings as $processing) {
                    $stageName = $processing->workStage->name ?? 'Unknown';
                    if (!isset($stageEfficiency[$stageName])) {
                        $stageEfficiency[$stageName] = [
                            'stage_name' => $stageName,
                            'orders_count' => 0,
                            'total_duration' => 0,
                            'completed_count' => 0,
                        ];
                    }

                    $stageEfficiency[$stageName]['orders_count']++;
                    if ($processing->status === 'completed' && $processing->duration) {
                        $stageEfficiency[$stageName]['completed_count']++;
                        $stageEfficiency[$stageName]['total_duration'] += $processing->duration;
                    }
                }
            }

            // Calculate averages
            $totalOrders = count($processingStats);
            $avgProcessingTime = $totalOrders > 0 ? collect($processingStats)->avg('total_processing_hours') : 0;

            // Stage efficiency averages
            foreach ($stageEfficiency as &$stage) {
                $stage['average_duration'] = $stage['completed_count'] > 0
                    ? round($stage['total_duration'] / $stage['completed_count'], 2)
                    : 0;
                $stage['completion_rate'] = $stage['orders_count'] > 0
                    ? round(($stage['completed_count'] / $stage['orders_count']) * 100, 2)
                    : 0;
            }

            // On-time delivery rate
            $deliveredOrders = $orders->where('status', 'delivered');
            $onTimeDeliveries = $deliveredOrders->filter(function ($order) {
                return $order->required_date && $order->shipped_date &&
                       $order->shipped_date->lte($order->required_date);
            });
            $onTimeRate = $deliveredOrders->count() > 0
                ? round(($onTimeDeliveries->count() / $deliveredOrders->count()) * 100, 2)
                : 0;

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_orders_processed' => $totalOrders,
                    'average_processing_time_hours' => round($avgProcessingTime, 2),
                    'on_time_delivery_rate' => $onTimeRate,
                    'delivered_orders_count' => $deliveredOrders->count(),
                ],
                'processing_times' => $processingStats,
                'stage_efficiency' => array_values($stageEfficiency),
                'generated_at' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate efficiency report', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to generate efficiency report: ' . $e->getMessage(),
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            ];
        }
    }

    /**
     * Generate sales report
     */
    public function generateSalesReport(string $startDate, string $endDate): array
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            // Get sales orders (outgoing) in the period
            $salesOrders = Order::where('type', 'out')
                               ->whereBetween('order_date', [$start, $end])
                               ->with(['customer', 'orderItems.product', 'warehouse'])
                               ->get();

            // Calculate sales metrics
            $totalSales = $salesOrders->sum('total_amount');
            $totalOrders = $salesOrders->count();
            $totalWeight = $salesOrders->sum('delivered_weight');

            // Sales by customer
            $salesByCustomer = $salesOrders->groupBy('customer_id')->map(function ($orders) {
                $customer = $orders->first()->customer;
                return [
                    'customer_name' => $customer->name ?? 'Unknown',
                    'orders_count' => $orders->count(),
                    'total_sales' => $orders->sum('total_amount'),
                    'total_weight' => $orders->sum('delivered_weight'),
                ];
            });

            // Sales by product
            $salesByProduct = [];
            foreach ($salesOrders as $order) {
                foreach ($order->orderItems as $item) {
                    $productId = $item->product_id;
                    if (!isset($salesByProduct[$productId])) {
                        $salesByProduct[$productId] = [
                            'product_name' => $item->product->name ?? 'Unknown',
                            'product_sku' => $item->product->sku ?? 'N/A',
                            'quantity_sold' => 0,
                            'total_revenue' => 0,
                            'orders_count' => 0,
                        ];
                    }
                    $salesByProduct[$productId]['quantity_sold'] += $item->quantity;
                    $salesByProduct[$productId]['total_revenue'] += $item->total_price;
                    $salesByProduct[$productId]['orders_count']++;
                }
            }

            // Sales by warehouse
            $salesByWarehouse = $salesOrders->groupBy('warehouse_id')->map(function ($orders) {
                return [
                    'warehouse_name' => $orders->first()->warehouse->name ?? 'Unknown',
                    'orders_count' => $orders->count(),
                    'total_sales' => $orders->sum('total_amount'),
                    'total_weight' => $orders->sum('delivered_weight'),
                ];
            });

            // Daily sales trend
            $dailySales = $salesOrders->groupBy(function ($order) {
                return $order->order_date->format('Y-m-d');
            })->map(function ($orders) {
                return [
                    'date' => $orders->first()->order_date->format('Y-m-d'),
                    'orders_count' => $orders->count(),
                    'total_sales' => $orders->sum('total_amount'),
                    'total_weight' => $orders->sum('delivered_weight'),
                ];
            })->sortBy('date');

            return [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_sales' => round($totalSales, 2),
                    'total_orders' => $totalOrders,
                    'total_weight_kg' => round($totalWeight, 2),
                    'average_order_value' => $totalOrders > 0 ? round($totalSales / $totalOrders, 2) : 0,
                ],
                'sales_by_customer' => $salesByCustomer->values(),
                'sales_by_product' => array_values($salesByProduct),
                'sales_by_warehouse' => $salesByWarehouse->values(),
                'daily_trend' => $dailySales->values(),
                'generated_at' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate sales report', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to generate sales report: ' . $e->getMessage(),
                'period' => ['start_date' => $startDate, 'end_date' => $endDate],
            ];
        }
    }

    /**
     * Send automated alerts
     */
    public function sendAutomatedAlerts(): array
    {
        try {
            $results = [
                'low_stock_alerts' => $this->sendLowStockAlerts(),
                'delay_alerts' => $this->sendDelayAlerts(),
                'quality_alerts' => $this->sendQualityAlerts(),
                'maintenance_alerts' => $this->sendMaintenanceAlerts(),
                'timestamp' => now(),
            ];

            $totalSent = collect($results)->filter(function ($result) {
                return is_array($result) && isset($result['sent_count']);
            })->sum('sent_count');

            Log::info('Automated alerts sent', [
                'total_alerts_sent' => $totalSent,
                'alert_types' => array_keys($results),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Failed to send automated alerts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'error' => 'Failed to send automated alerts: ' . $e->getMessage(),
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Send low stock alerts
     */
    public function sendLowStockAlerts(): array
    {
        try {
            // Get low stock items - handle missing columns gracefully
            try {
                $lowStockItems = Product::where('is_active', true)
                                       ->where('track_inventory', true)
                                       ->whereHas('stocks', function($query) {
                                           $query->where('is_active', true)
                                                 ->whereRaw('quantity <= (SELECT min_stock_level FROM products WHERE id = product_id)');
                                       })
                                       ->with(['stocks' => function($query) {
                                           $query->where('is_active', true);
                                       }])
                                       ->get();
            } catch (\Exception $e) {
                // Fallback if track_inventory or min_stock_level columns don't exist
                Log::info('Low stock alerts: inventory tracking columns not found, skipping alerts', [
                    'error' => $e->getMessage()
                ]);
                $lowStockItems = collect();
            }

            $alertsSent = [];
            foreach ($lowStockItems as $product) {
                foreach ($product->stocks as $stock) {
                    if ($stock->available_quantity <= ($product->min_stock_level ?? 0)) {
                        $minStockLevel = $product->min_stock_level ?? 0;
                        $alertData = [
                            'product_name' => $product->name,
                            'product_sku' => $product->sku,
                            'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                            'current_stock' => $stock->available_quantity,
                            'min_stock_level' => $minStockLevel,
                            'severity' => $this->calculateStockSeverity($stock->available_quantity, $minStockLevel),
                        ];

                        // Log alert (implement actual email/SMS sending)
                        Log::warning('Low stock alert sent', $alertData);

                        // TODO: Implement actual notification sending
                        // Mail::to(config('app.inventory_manager_email'))->send(new LowStockAlert($alertData));

                        $alertsSent[] = $alertData;
                    }
                }
            }

            return [
                'alert_type' => 'low_stock',
                'sent_count' => count($alertsSent),
                'alerts' => $alertsSent,
                'timestamp' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send low stock alerts', [
                'error' => $e->getMessage(),
            ]);

            return [
                'alert_type' => 'low_stock',
                'error' => 'Failed to send low stock alerts: ' . $e->getMessage(),
                'sent_count' => 0,
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Send delay alerts
     */
    public function sendDelayAlerts(): array
    {
        try {
            // Get delayed orders (past due date but not completed)
            $delayedOrders = Order::where('required_date', '<', now())
                                 ->whereNotIn('status', ['delivered', 'cancelled'])
                                 ->where('is_urgent', true) // Only alert for urgent orders
                                 ->with(['customer', 'assignedUser'])
                                 ->get();

            $alertsSent = [];
            foreach ($delayedOrders as $order) {
                $delayDays = now()->diffInDays($order->required_date);

                $alertData = [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer->name ?? $order->customer_name ?? 'Unknown',
                    'required_date' => $order->required_date->format('Y-m-d'),
                    'delay_days' => $delayDays,
                    'current_stage' => $order->current_stage,
                    'assigned_to' => $order->assignedUser->name ?? 'Unassigned',
                    'is_urgent' => $order->is_urgent,
                ];

                // Log alert
                Log::warning('Delay alert sent', $alertData);

                // TODO: Implement actual notification sending
                // Mail::to($order->assignedUser->email ?? config('app.manager_email'))->send(new DelayAlert($alertData));

                $alertsSent[] = $alertData;
            }

            return [
                'alert_type' => 'delay',
                'sent_count' => count($alertsSent),
                'alerts' => $alertsSent,
                'timestamp' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send delay alerts', [
                'error' => $e->getMessage(),
            ]);

            return [
                'alert_type' => 'delay',
                'error' => 'Failed to send delay alerts: ' . $e->getMessage(),
                'sent_count' => 0,
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Send quality alerts
     */
    public function sendQualityAlerts(): array
    {
        try {
            // Get recent waste records indicating quality issues
            try {
                $recentWaste = Waste::where('created_at', '>=', now()->subDays(7))
                                   ->where('is_resolved', false)
                                   ->with('product')
                                   ->get();
            } catch (\Exception $e) {
                // Fallback if is_resolved column doesn't exist
                Log::info('Quality alerts: is_resolved column not found, getting all recent waste', [
                    'error' => $e->getMessage()
                ]);
                $recentWaste = Waste::where('created_at', '>=', now()->subDays(7))
                                   ->with('product')
                                   ->get();
            }

            // Group by product and reason to identify patterns
            $qualityIssues = $recentWaste->groupBy(['product_id', 'reason'])->map(function ($records) {
                $product = $records->first()->product;
                $reason = $records->first()->reason;
                $totalQuantity = $records->sum('quantity');

                return [
                    'product_name' => $product->name ?? 'Unknown',
                    'product_sku' => $product->sku ?? 'N/A',
                    'reason' => $reason,
                    'total_waste_quantity' => $totalQuantity,
                    'occurrences' => $records->count(),
                    'severity' => $this->calculateQualitySeverity($totalQuantity, $records->count()),
                ];
            })->filter(function ($issue) {
                return $issue['severity'] !== 'low';
            });

            $alertsSent = [];
            foreach ($qualityIssues as $issue) {
                // Log alert
                Log::warning('Quality alert sent', $issue);

                // TODO: Implement actual notification sending
                // Mail::to(config('app.quality_manager_email'))->send(new QualityAlert($issue));

                $alertsSent[] = $issue;
            }

            return [
                'alert_type' => 'quality',
                'sent_count' => count($alertsSent),
                'alerts' => $alertsSent,
                'timestamp' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send quality alerts', [
                'error' => $e->getMessage(),
            ]);

            return [
                'alert_type' => 'quality',
                'error' => 'Failed to send quality alerts: ' . $e->getMessage(),
                'sent_count' => 0,
                'timestamp' => now(),
            ];
        }
    }

    /**
     * Send maintenance alerts
     */
    public function sendMaintenanceAlerts(): array
    {
        try {
            // This is a placeholder for maintenance alerts
            // In a real system, you would check equipment maintenance schedules,
            // machine performance metrics, etc.

            $maintenanceAlerts = [];

            // Example: Check for orders that have been in processing too long
            $stuckOrders = Order::where('updated_at', '<', now()->subHours(24))
                               ->whereIn('status', ['processing', 'قيد_المعالجة'])
                               ->whereIn('current_stage', ['قص', 'تعبئة']) // Stages that use equipment
                               ->with(['assignedUser'])
                               ->get();

            foreach ($stuckOrders as $order) {
                $alertData = [
                    'order_number' => $order->order_number,
                    'current_stage' => $order->current_stage,
                    'assigned_to' => $order->assignedUser->name ?? 'Unassigned',
                    'last_updated' => $order->updated_at->diffForHumans(),
                    'alert_type' => 'equipment_stuck',
                    'severity' => 'medium',
                ];

                // Log alert
                Log::warning('Maintenance alert sent', $alertData);

                // TODO: Implement actual notification sending
                // Mail::to(config('app.maintenance_manager_email'))->send(new MaintenanceAlert($alertData));

                $maintenanceAlerts[] = $alertData;
            }

            return [
                'alert_type' => 'maintenance',
                'sent_count' => count($maintenanceAlerts),
                'alerts' => $maintenanceAlerts,
                'timestamp' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send maintenance alerts', [
                'error' => $e->getMessage(),
            ]);

            return [
                'alert_type' => 'maintenance',
                'error' => 'Failed to send maintenance alerts: ' . $e->getMessage(),
                'sent_count' => 0,
                'timestamp' => now(),
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

    private function calculateQualitySeverity(float $wasteQuantity, int $occurrences): string
    {
        if ($wasteQuantity > 1000 || $occurrences > 10) return 'high';
        if ($wasteQuantity > 500 || $occurrences > 5) return 'medium';
        return 'low';
    }

    /**
     * Run automated reporting
     */
    public function run(): array
    {
        try {
            $dailyReports = $this->generateDailyReports();
            $alerts = $this->sendAutomatedAlerts();

            return [
                'success' => $dailyReports['success'] ?? false,
                'reports_generated' => $dailyReports['reports'] ?? [],
                'alerts_sent' => $alerts,
                'timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Automated reporting run failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'reports_generated' => [],
                'alerts_sent' => []
            ];
        }
    }
}