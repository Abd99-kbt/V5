<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    // use Backpack\CRUD\app\Models\Traits\CrudTrait; // Temporarily disabled
    
    protected $fillable = [
        'name_en',
        'name_ar',
        'sku',
        'barcode',
        'description_en',
        'description_ar',
        'image',
        'type',
        'grammage',
        'quality',
        'roll_number',
        'source',
        'length',
        'width',
        'thickness',
        'purchase_price',
        'selling_price',
        'wholesale_price',
        'material_cost_per_ton',
        'min_stock_level',
        'max_stock_level',
        'unit',
        'weight',
        'reserved_weight',
        'is_active',
        'track_inventory',
        'category_id',
        'supplier_id',
        'purchase_invoice_number',
    ];

    protected $casts = [
        'grammage' => 'integer',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'thickness' => 'decimal:3',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'material_cost_per_ton' => 'decimal:2',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'weight' => 'decimal:2',
        'reserved_weight' => 'decimal:2',
        'is_active' => 'boolean',
        'track_inventory' => 'boolean',
        // Warehouse tracking casts
        'purchase_invoice_number' => 'string',
        'available_weight_kg' => 'decimal:2',
    ];

    /**
     * Get the category that owns the product.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the supplier that owns the product.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the stocks for the product.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Get available stocks from warehouses
     */
    public function availableStocks()
    {
        return $this->stocks()->where('is_active', true)
                             ->whereRaw('quantity > reserved_quantity');
    }

    /**
     * Get stock in specific warehouse
     */
    public function getStockInWarehouse($warehouseId)
    {
        return $this->stocks()->where('warehouse_id', $warehouseId)
                             ->where('is_active', true)
                             ->first();
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the stock alerts for the product.
     */
    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class);
    }

    /**
     * Get the product name based on current locale
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * Get the product description based on current locale
     */
    public function getDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->description_ar : $this->description_en;
    }

    /**
     * Get total stock quantity across all warehouses
     */
    public function getTotalStockAttribute(): float
    {
        return $this->stocks()->sum('quantity');
    }

    /**
     * Get available stock quantity (not reserved in orders)
     */
    public function getAvailableStockAttribute(): float
    {
        return $this->weight - $this->reserved_weight;
    }

    /**
     * Get total reserved weight across all orders
     */
    public function getTotalReservedWeightAttribute(): float
    {
        return $this->orderItems()
            ->whereHas('order', function ($query) {
                $query->where('status', '!=', 'completed')
                      ->where('status', '!=', 'cancelled');
            })
            ->sum('weight');
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->available_stock <= $this->min_stock_level;
    }

    /**
     * Check if product is out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->available_stock <= 0;
    }

    /**
     * Get profit margin percentage
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->purchase_price == 0) {
            return 0;
        }
        return round((($this->selling_price - $this->purchase_price) / $this->purchase_price) * 100, 2);
    }

    /**
     * Get product area in square meters
     */
    public function getAreaAttribute(): float
    {
        if ($this->length && $this->width) {
            return ($this->length * $this->width) / 10000; // Convert cm² to m²
        }
        return 0;
    }

    /**
     * Get product volume in cubic meters
     */
    public function getVolumeAttribute(): float
    {
        if ($this->length && $this->width && $this->thickness) {
            return ($this->length * $this->width * $this->thickness) / 1000000000; // Convert cm³ to m³
        }
        return 0;
    }

    /**
     * Reserve weight for orders
     */
    public function reserveWeight(float $weight): bool
    {
        if ($this->available_stock >= $weight) {
            $this->reserved_weight += $weight;
            return $this->save();
        }
        return false;
    }

    /**
     * Release reserved weight
     */
    public function releaseWeight(float $weight): bool
    {
        if ($this->reserved_weight >= $weight) {
            $this->reserved_weight -= $weight;
            return $this->save();
        }
        return false;
    }

    /**
     * Get product type label in Arabic
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'roll' => 'لفة',
            'digma' => 'ديجما',
            'bale' => 'بالة',
            'sheet' => 'شريحة',
            default => $this->type
        };
    }

    /**
     * Get quality label in Arabic
     */
    public function getQualityLabelAttribute(): string
    {
        return match($this->quality) {
            'standard' => 'قياسي',
            'stock' => 'مخزون',
            'premium' => 'ممتاز',
            default => $this->quality ?? 'غير محدد'
        };
    }

    /**
     * Calculate price per ton based on weight
     */
    public function calculatePricePerTon(float $weight): float
    {
        if ($weight <= 0) {
            return 0;
        }
        return $this->selling_price * (1000 / $weight);
    }

    /**
     * Get product specifications as formatted string
     */
    public function getSpecificationsAttribute(): string
    {
        $specs = [];

        if ($this->type) {
            $specs[] = __('Type') . ': ' . $this->type_label;
        }

        if ($this->grammage) {
            $specs[] = __('Grammage') . ': ' . $this->grammage . 'gsm';
        }

        if ($this->quality) {
            $specs[] = __('Quality') . ': ' . $this->quality_label;
        }

        if ($this->length && $this->width) {
            $specs[] = __('Dimensions') . ': ' . $this->length . 'cm × ' . $this->width . 'cm';
        }

        if ($this->thickness) {
            $specs[] = __('Thickness') . ': ' . $this->thickness . 'mm';
        }

        return implode(' | ', $specs);
    }

    /**
     * Get available products for order creation (from warehouses)
     */
    public static function getAvailableForOrder($warehouseId = null, $filters = [])
    {
        $query = self::whereHas('stocks', function($q) use ($warehouseId) {
            $q->where('is_active', true)
              ->whereRaw('quantity > reserved_quantity');

            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        });

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['quality'])) {
            $query->where('quality', $filters['quality']);
        }

        if (isset($filters['min_grammage'])) {
            $query->where('grammage', '>=', $filters['min_grammage']);
        }

        if (isset($filters['max_grammage'])) {
            $query->where('grammage', '<=', $filters['max_grammage']);
        }

        return $query->with(['stocks' => function($q) use ($warehouseId) {
            $q->where('is_active', true)
              ->whereRaw('quantity > reserved_quantity');

            if ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            }
        }])->get();
    }

    /**
     * Check if product can be used for order (has available stock)
     */
    public function canBeUsedForOrder($requiredWeight, $warehouseId = null): bool
    {
        $availableStocks = $warehouseId
            ? $this->stocks()->where('warehouse_id', $warehouseId)->where('is_active', true)
            : $this->stocks()->where('is_active', true);

        $totalAvailable = $availableStocks->sum(DB::raw('quantity - reserved_quantity'));

        return $totalAvailable >= $requiredWeight;
    }

    /**
     * Get warehouse stock info for this product
     */
    public function getWarehouseStockInfo($warehouseId)
    {
        $stock = $this->getStockInWarehouse($warehouseId);

        return $stock ? [
            'quantity' => $stock->quantity,
            'available_quantity' => $stock->available_quantity,
            'reserved_quantity' => $stock->reserved_quantity,
            'unit_cost' => $stock->unit_cost,
            'location' => $stock->location,
            'batch_number' => $stock->batch_number,
            'expiry_date' => $stock->expiry_date,
        ] : null;
    }
}
