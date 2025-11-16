<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockAlert;
use App\Models\Product;
use Illuminate\Support\Collection;

class StockAlertService
{
    /**
     * Check all stock levels and create alerts if needed
     */
    public function checkStockLevels(): array
    {
        $alertsCreated = 0;
        $alertsResolved = 0;

        // Get all active stocks with their products
        $stocks = Stock::with('product')
            ->where('is_active', true)
            ->get();

        foreach ($stocks as $stock) {
            $product = $stock->product;

            // Skip if product doesn't track inventory
            if (!$product->track_inventory) {
                continue;
            }

            // Check for low stock
            if ($stock->quantity <= $product->min_stock_level && $stock->quantity > 0) {
                $this->createAlert($product, $stock, 'low_stock', 'medium');
                $alertsCreated++;
            }

            // Check for out of stock
            if ($stock->quantity <= 0) {
                $this->createAlert($product, $stock, 'out_of_stock', 'critical');
                $alertsCreated++;
            }

            // Check for expiring products
            if ($stock->expiry_date && $stock->expiry_date->isFuture()) {
                $daysUntilExpiry = $stock->expiry_date->diffInDays(now());

                if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
                    $severity = $daysUntilExpiry <= 7 ? 'high' : 'medium';
                    $this->createAlert($product, $stock, 'expiring_soon', $severity);
                    $alertsCreated++;
                }
            }

            // Check for expired products
            if ($stock->expiry_date && $stock->expiry_date->isPast()) {
                $this->createAlert($product, $stock, 'expired', 'high');
                $alertsCreated++;
            }
        }

        // Resolve alerts for products that no longer need them
        $alertsResolved = $this->resolveObsoleteAlerts();

        return [
            'alerts_created' => $alertsCreated,
            'alerts_resolved' => $alertsResolved,
        ];
    }

    /**
     * Create a stock alert
     */
    private function createAlert(Product $product, Stock $stock, string $type, string $severity): void
    {
        // Check if alert already exists
        $existingAlert = StockAlert::where('product_id', $product->id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->where('type', $type)
            ->where('is_resolved', false)
            ->first();

        if (!$existingAlert) {
            $message = $this->generateAlertMessage($product, $stock, $type);

            StockAlert::create([
                'product_id' => $product->id,
                'warehouse_id' => $stock->warehouse_id,
                'type' => $type,
                'severity' => $severity,
                'current_quantity' => $stock->quantity,
                'threshold_quantity' => $type === 'low_stock' ? $product->min_stock_level : 0,
                'message' => $message,
                'is_read' => false,
                'is_resolved' => false,
            ]);
        }
    }

    /**
     * Generate alert message based on type
     */
    private function generateAlertMessage(Product $product, Stock $stock, string $type): string
    {
        $productName = app()->getLocale() === 'ar' ? $product->name_ar : $product->name_en;
        $warehouseName = app()->getLocale() === 'ar' ? $stock->warehouse->name_ar : $stock->warehouse->name_en;

        return match($type) {
            'low_stock' => "المنتج {$productName} في المستودع {$warehouseName} وصل إلى الحد الأدنى للمخزون. الكمية الحالية: {$stock->quantity}",
            'out_of_stock' => "المنتج {$productName} في المستودع {$warehouseName} نفد من المخزون",
            'expiring_soon' => "المنتج {$productName} في المستودع {$warehouseName} سينتهي قريباً في {$stock->expiry_date->format('Y-m-d')}",
            'expired' => "المنتج {$productName} في المستودع {$warehouseName} انتهت صلاحيته في {$stock->expiry_date->format('Y-m-d')}",
            default => "تنبيه مخزون للمنتج {$productName}",
        };
    }

    /**
     * Resolve obsolete alerts
     */
    private function resolveObsoleteAlerts(): int
    {
        $resolved = 0;

        // Get all unresolved alerts
        $alerts = StockAlert::where('is_resolved', false)->get();

        foreach ($alerts as $alert) {
            $shouldResolve = false;

            switch ($alert->type) {
                case 'low_stock':
                    $currentStock = Stock::where('product_id', $alert->product_id)
                        ->where('warehouse_id', $alert->warehouse_id)
                        ->sum('quantity');
                    $shouldResolve = $currentStock > $alert->product->min_stock_level;
                    break;

                case 'out_of_stock':
                    $currentStock = Stock::where('product_id', $alert->product_id)
                        ->where('warehouse_id', $alert->warehouse_id)
                        ->sum('quantity');
                    $shouldResolve = $currentStock > 0;
                    break;

                case 'expiring_soon':
                case 'expired':
                    $stock = Stock::where('product_id', $alert->product_id)
                        ->where('warehouse_id', $alert->warehouse_id)
                        ->first();
                    $shouldResolve = !$stock || !$stock->expiry_date || $stock->expiry_date->isPast();
                    break;
            }

            if ($shouldResolve) {
                $alert->markAsResolved();
                $resolved++;
            }
        }

        return $resolved;
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): Collection
    {
        return StockAlert::with(['product', 'warehouse'])
            ->where('is_resolved', false)
            ->orderBy('severity')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get alerts by severity
     */
    public function getAlertsBySeverity(string $severity): Collection
    {
        return StockAlert::with(['product', 'warehouse'])
            ->where('severity', $severity)
            ->where('is_resolved', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get alerts count by type
     */
    public function getAlertsCount(): array
    {
        return [
            'total' => StockAlert::where('is_resolved', false)->count(),
            'critical' => StockAlert::where('severity', 'critical')->where('is_resolved', false)->count(),
            'high' => StockAlert::where('severity', 'high')->where('is_resolved', false)->count(),
            'medium' => StockAlert::where('severity', 'medium')->where('is_resolved', false)->count(),
            'low' => StockAlert::where('severity', 'low')->where('is_resolved', false)->count(),
        ];
    }

    /**
     * Mark alert as read
     */
    public function markAsRead(StockAlert $alert): bool
    {
        return $alert->markAsRead();
    }

    /**
     * Mark alert as resolved
     */
    public function markAsResolved(StockAlert $alert): bool
    {
        return $alert->markAsResolved();
    }

    /**
     * Create stock alert for specific product and warehouse
     */
    public function createCustomAlert(Product $product, $warehouseId, string $type, string $message, string $severity = 'medium'): StockAlert
    {
        return StockAlert::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'severity' => $severity,
            'current_quantity' => Stock::where('product_id', $product->id)->where('warehouse_id', $warehouseId)->sum('quantity'),
            'threshold_quantity' => 0,
            'message' => $message,
            'is_read' => false,
            'is_resolved' => false,
        ]);
    }
}