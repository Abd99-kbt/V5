<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class Order extends Model
{
    use Auditable;

    // use Backpack\CRUD\app\Models\Traits\CrudTrait; // Temporarily disabled
    
    protected $fillable = [
        'order_number',
        'type',
        'status',
        'current_stage',
        'warehouse_id',
        'supplier_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'order_date',
        'required_date',
        'shipped_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'shipping_cost',
        'total_amount',
        'notes',
        'tracking_number',
        'is_paid',
        'paid_at',
        'created_by',
        'assigned_to',
        'material_type',
        'required_weight',
        'required_length',
        'required_width',
        'required_plates',
        'delivery_method',
        'delivery_address',
        'estimated_price',
        'final_price',
        'paid_amount',
        'remaining_amount',
        'discount',
        'submitted_at',
        'approved_at',
        'started_at',
        'completed_at',
        'specifications',
        'is_urgent',
        'material_requirements',
        'estimated_material_cost',
        'labor_cost_estimate',
        'overhead_cost_estimate',
        'profit_margin_percentage',
        'profit_margin_amount',
        'pricing_breakdown',
        'auto_material_selection',
        'selected_materials',
        'materials_selected_at',
        'materials_selected_by',
        'pricing_calculated',
        'pricing_calculated_at',
        'pricing_calculated_by',
        'delivery_width',
        'delivery_length',
        'delivery_thickness',
        'delivery_grammage',
        'delivery_quality',
        'delivery_quantity',
        'delivery_weight',
        'price_per_ton',
        'cutting_fees',
        'requestor_name',
        'delivered_weight',
        'delivery_location',
        'number_of_plates',
        'cutting_fees_per_ton',
    ];

    protected $casts = [
        'order_date' => 'date',
        'required_date' => 'date',
        'shipped_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
        'required_weight' => 'decimal:2',
        'required_length' => 'decimal:2',
        'required_width' => 'decimal:2',
        'estimated_price' => 'decimal:2',
        'final_price' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'specifications' => 'array',
        'is_urgent' => 'boolean',
        // Order entry workflow casts
        'material_requirements' => 'array',
        'estimated_material_cost' => 'decimal:2',
        'labor_cost_estimate' => 'decimal:2',
        'overhead_cost_estimate' => 'decimal:2',
        'profit_margin_percentage' => 'decimal:2',
        'profit_margin_amount' => 'decimal:2',
        'pricing_breakdown' => 'array',
        'auto_material_selection' => 'boolean',
        'selected_materials' => 'array',
        'materials_selected_at' => 'datetime',
        'pricing_calculated' => 'boolean',
        'pricing_calculated_at' => 'datetime',
        // Delivery specifications casts
        'delivery_width' => 'decimal:2',
        'delivery_length' => 'decimal:2',
        'delivery_thickness' => 'decimal:2',
        'delivery_grammage' => 'decimal:2',
        'delivery_quantity' => 'integer',
        'delivery_weight' => 'decimal:2',
        // Pricing field casts
        'price_per_ton' => 'decimal:2',
        'cutting_fees' => 'decimal:2',
        // Additional field casts
        'delivered_weight' => 'decimal:2',
        'number_of_plates' => 'integer',
        'cutting_fees_per_ton' => 'decimal:2',
    ];

    /**
     * Get the warehouse that owns the order.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the supplier for the order (for purchase orders).
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the customer for the order (for sales orders).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get cached customer relationship
     */
    public function getCachedCustomer()
    {
        return \App\Services\CacheManager::rememberRelation($this, 'customer', 1800); // 30 minutes
    }

    /**
     * Get the creator of the order.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the assigned user for the order.
     */
     public function assignedUser(): BelongsTo
     {
         return $this->belongsTo(User::class, 'assigned_to');
     }

    /**
     * Get the user who selected materials.
     */
    public function materialSelector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'materials_selected_by');
    }

    /**
     * Get the user who calculated pricing.
     */
    public function pricingCalculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pricing_calculated_by');
    }

    /**
     * Get the order items for the order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the order processings for the order.
     */
     public function orderProcessings(): HasMany
     {
         return $this->hasMany(OrderProcessing::class);
     }

    /**
     * Get the order materials for the order.
     */
     public function orderMaterials(): HasMany
     {
         return $this->hasMany(OrderMaterial::class);
     }

    /**
     * Get the weight transfers for the order.
     */
     public function weightTransfers(): HasMany
     {
         return $this->hasMany(WeightTransfer::class);
     }

    /**
     * Get the stage history for the order.
     */
    public function stageHistory(): HasMany
    {
        return $this->hasMany(OrderStageHistory::class);
    }

    /**
     * Get the order stages for the order.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(OrderStage::class);
    }

    /**
     * Get the products for the order through order items.
     */
    public function products()
    {
        return $this->hasManyThrough(Product::class, OrderItem::class, 'order_id', 'id', 'id', 'product_id');
    }

    /**
     * Check if order is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'ملغي';
    }

    /**
     * Check if user can approve this order
     */
    public function canBeApprovedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Admin can approve all orders
        if ($user->hasRole('مدير_شامل') || $user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // Check if user has approval permission
        return $user->hasPermission('orders.approve');
    }

    /**
     * Cancel order with reason
     */
    public function cancel(string $reason = null): bool
    {
        if (!in_array($this->status, ['مسودة', 'قيد_المراجعة', 'مؤكد'])) {
            return false;
        }

        $this->update([
            'status' => 'ملغي',
            'notes' => $this->notes . "\n\nسبب الإلغاء: " . $reason,
        ]);

        // Release reserved materials
        foreach ($this->orderMaterials as $orderMaterial) {
            $orderMaterial->material->releaseWeight($orderMaterial->requested_weight);
        }

        return true;
    }

    /**
     * Check if order is a purchase order (incoming)
     */
    public function isPurchaseOrder(): bool
    {
        return $this->type === 'in';
    }

    /**
     * Check if order is a sales order (outgoing)
     */
    public function isSalesOrder(): bool
    {
        return $this->type === 'out';
    }

    /**
     * Get current stage color for UI
     */
    public function getStageColorAttribute(): string
    {
        return match($this->current_stage) {
            'إنشاء' => 'gray',
            'مراجعة' => 'yellow',
            'حجز_المواد' => 'blue',
            'فرز' => 'purple',
            'قص' => 'orange',
            'تعبئة' => 'indigo',
            'فوترة' => 'green',
            'تسليم' => 'emerald',
            default => 'gray'
        };
    }

    /**
     * Get stage priority for ordering
     */
    public function getStagePriorityAttribute(): int
    {
        $stages = [
            'إنشاء' => 1,
            'مراجعة' => 2,
            'حجز_المواد' => 3,
            'فرز' => 4,
            'قص' => 5,
            'تعبئة' => 6,
            'فوترة' => 7,
            'تسليم' => 8,
        ];

        return $stages[$this->current_stage] ?? 0;
    }

    /**
     * Check if user can modify this order
     */
    public function canBeModifiedBy($user): bool
    {
        if (!$user) return false;

        // Admin can modify any order
        if ($user->hasRole('مدير_شامل')) return true;

        // Order creator can modify draft orders
        if ($this->created_by == $user->id && $this->status === 'مسودة') return true;

        // Assigned user can modify in certain stages
        if ($this->assigned_to == $user->id && in_array($this->status, ['مسودة', 'قيد_المراجعة'])) return true;

        return false;
    }

    /**
     * Check if user can cancel this order
     */
    public function canBeCancelledBy($user): bool
    {
        if (!$user) return false;

        // Admin can cancel any order
        if ($user->hasRole('مدير_شامل')) return true;

        // Can cancel only if not completed or delivered
        if (in_array($this->status, ['مكتمل', 'delivered'])) return false;

        // Order creator can cancel their orders
        if ($this->created_by == $user->id) return true;

        return false;
    }

    /**
     * Mark order as paid
     */
    public function markAsPaid(): bool
    {
        $this->is_paid = true;
        $this->paid_at = now();
        return $this->save();
    }

    /**
     * Update order status
     */
    public function updateStatus(string $status): bool
    {
        $allowedStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

        if (in_array($status, $allowedStatuses)) {
            $this->status = $status;

            if ($status === 'shipped') {
                $this->shipped_date = now();
            }

            return $this->save();
        }

        return false;
    }

    /**
     * Get total items quantity
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->orderItems()->sum('quantity');
    }

    /**
     * Calculate totals
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->orderItems()->sum('total_price');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_cost - $this->discount_amount;
        $this->save();
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $lastOrder = self::whereDate('created_at', today())
                         ->orderBy('id', 'desc')
                         ->first();

        $sequence = $lastOrder ? intval(substr($lastOrder->order_number, -4)) + 1 : 1;
        return 'ORD-' . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope for filtering by stage
     */
    public function scopeInStage($query, $stage)
    {
        return $query->where('current_stage', $stage);
    }

    /**
     * Scope for urgent orders
     */
    public function scopeUrgent($query)
    {
        return $query->where('is_urgent', true);
    }

    /**
     * Scope for orders that can be seen by user
     */
    public function scopeVisibleToUser($query, $user)
    {
        if (!$user) return $query;

        // Admin sees all orders
        if ($user->hasRole('مدير_شامل')) {
            return $query;
        }

        // Users see orders they created or are assigned to
        return $query->where(function($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhere('assigned_to', $user->id);
        });
    }

    /**
     * Get orders by priority (urgent first, then by stage priority)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('is_urgent', 'desc')
                    ->orderByRaw("FIELD(current_stage, 'إنشاء', 'مراجعة', 'حجز_المواد', 'فرز', 'قص', 'تعبئة', 'فوترة', 'تسليم')")
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Get cached order statistics
     */
    public static function getCachedStats()
    {
        return \App\Services\CacheManager::remember('orders.stats', function () {
            return [
                'total_orders' => self::count(),
                'pending_orders' => self::where('status', 'pending')->count(),
                'completed_orders' => self::where('status', 'completed')->count(),
                'total_revenue' => self::sum('final_price'),
            ];
        }, 300); // 5 minutes
    }

    /**
     * Move order to specific stage
     */
    public function moveToStage(string $stageName, User $user, bool $skipValidation = false): bool
    {
        $workStage = WorkStage::where('name_en', $stageName)
                              ->orWhere('name_ar', $stageName)
                              ->first();

        if (!$workStage) return false;

        // Check role permissions
        if (!$skipValidation && !$workStage->canBeAccessedBy($user)) {
            return false;
        }

        // Find or create processing record
        $processing = $this->orderProcessings()
                          ->where('work_stage_id', $workStage->id)
                          ->first();

        if (!$processing) {
            $processing = $this->orderProcessings()->create([
                'work_stage_id' => $workStage->id,
                'status' => 'pending',
                'stage_color' => $workStage->color,
                'can_skip' => $workStage->can_skip,
                'visual_priority' => $workStage->order,
                'estimated_duration' => $workStage->estimated_duration,
            ]);
        }

        // Update order current stage
        $previousStage = $this->current_stage;
        $this->update(['current_stage' => $stageName]);

        // Record history
        OrderStageHistory::create([
            'order_id' => $this->id,
            'work_stage_id' => $workStage->id,
            'previous_stage' => $previousStage,
            'new_stage' => $stageName,
            'action' => 'move',
            'action_by' => $user->id,
        ]);

        return true;
    }

    /**
     * Get visual stage progress
     */
    public function getStageProgressAttribute(): array
    {
        $stages = $this->orderProcessings()
                      ->with('workStage')
                      ->orderBy('visual_priority')
                      ->get();

        return $stages->map(function ($processing) {
            return [
                'name' => $processing->workStage->name,
                'status' => $processing->status,
                'color' => $processing->stage_color ?: $processing->workStage->color,
                'progress' => $processing->progress_percentage,
                'can_skip' => $processing->can_skip,
                'duration' => $processing->duration,
                'estimated_duration' => $processing->estimated_duration,
                'work_stage_id' => $processing->work_stage_id,
            ];
        })->toArray();
    }

    /**
     * Advanced filtering scope
     */
    public function scopeAdvancedFilter($query, array $filters)
    {
        // Date range filtering
        if (isset($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('order_date', '<=', $filters['date_to']);
        }

        // Stage filtering
        if (isset($filters['stages']) && is_array($filters['stages'])) {
            $query->whereIn('current_stage', $filters['stages']);
        }

        // Status filtering
        if (isset($filters['statuses']) && is_array($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        // User visibility
        if (isset($filters['user'])) {
            $query->visibleToUser($filters['user']);
        }

        // Warehouse filtering
        if (isset($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        // Urgent orders
        if (isset($filters['urgent_only']) && $filters['urgent_only']) {
            $query->where('is_urgent', true);
        }

        return $query;
    }

    /**
     * Get stage history summary
     */
    public function getStageHistorySummaryAttribute(): array
    {
        return $this->stageHistory()
                   ->with(['workStage', 'actionUser'])
                   ->orderBy('action_at', 'desc')
                   ->get()
                   ->map(function ($history) {
                       return [
                           'stage' => $history->workStage->name,
                           'action' => $history->action_description,
                           'user' => $history->actionUser->name ?? 'Unknown',
                           'timestamp' => $history->action_at,
                           'notes' => $history->notes,
                       ];
                   })
                   ->toArray();
    }

    /**
     * Get delivery specifications as array
     */
    public function getDeliverySpecificationsAttribute(): array
    {
        return [
            'width' => $this->delivery_width,
            'length' => $this->delivery_length,
            'thickness' => $this->delivery_thickness,
            'grammage' => $this->delivery_grammage,
            'quality' => $this->delivery_quality,
            'quantity' => $this->delivery_quantity,
            'weight' => $this->delivery_weight,
        ];
    }

    /**
     * Validate delivery specifications compatibility
     */
    public function validateDeliverySpecifications(): array
    {
        $errors = [];

        // Check if specifications are provided
        if (!$this->delivery_width && !$this->delivery_length && !$this->delivery_thickness &&
            !$this->delivery_grammage && !$this->delivery_quality && !$this->delivery_quantity && !$this->delivery_weight) {
            return $errors; // No specifications provided, no validation needed
        }

        // Validate dimensions
        if ($this->delivery_width && $this->delivery_width <= 0) {
            $errors[] = 'Delivery width must be greater than 0';
        }

        if ($this->delivery_length && $this->delivery_length <= 0) {
            $errors[] = 'Delivery length must be greater than 0';
        }

        if ($this->delivery_thickness && $this->delivery_thickness <= 0) {
            $errors[] = 'Delivery thickness must be greater than 0';
        }

        // Validate grammage
        if ($this->delivery_grammage && $this->delivery_grammage <= 0) {
            $errors[] = 'Delivery grammage must be greater than 0';
        }

        // Validate quantity
        if ($this->delivery_quantity && $this->delivery_quantity <= 0) {
            $errors[] = 'Delivery quantity must be greater than 0';
        }

        // Validate weight
        if ($this->delivery_weight && $this->delivery_weight <= 0) {
            $errors[] = 'Delivery weight must be greater than 0';
        }

        return $errors;
    }

    /**
     * Check if delivery specifications match material specifications
     */
    public function checkSpecificationCompatibility($material): array
    {
        $warnings = [];

        if (!$material) {
            return $warnings;
        }

        // Check width compatibility
        if ($this->delivery_width && $material->width && abs($this->delivery_width - $material->width) > 0.1) {
            $warnings[] = "Delivery width ({$this->delivery_width}) differs from material width ({$material->width})";
        }

        // Check grammage compatibility
        if ($this->delivery_grammage && $material->grammage && abs($this->delivery_grammage - $material->grammage) > 1) {
            $warnings[] = "Delivery grammage ({$this->delivery_grammage}) differs from material grammage ({$material->grammage})";
        }

        // Check quality compatibility
        if ($this->delivery_quality && $material->quality && $this->delivery_quality !== $material->quality) {
            $warnings[] = "Delivery quality ({$this->delivery_quality}) differs from material quality ({$material->quality})";
        }

        return $warnings;
    }

    /**
     * Get external order display fields (for customer-facing displays)
     */
    public function getExternalDisplayFields(): array
    {
        return [
            'sequence_number' => $this->sequence_number ?? $this->order_number,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'order_time' => $this->created_at?->format('H:i:s'),
            'requestor_name' => $this->requestor_name ?? ($this->creator?->name ?? 'N/A'),
            'customer_name' => $this->customer?->name ?? $this->customer_name ?? 'N/A',
            'material_name' => $this->orderItems->first()?->product?->name ?? 'N/A',
            'required_weight' => $this->required_weight ?? 0,
            'delivered_weight' => $this->delivered_weight ?? 0,
            'price_per_ton' => $this->price_per_ton ?? 0,
            'value' => $this->calculateOrderValue(),
            'discount_value' => $this->discount_amount ?? 0,
            'cutting_fees_per_ton' => $this->cutting_fees_per_ton ?? 0,
            'delivery_location' => $this->delivery_location ?? $this->delivery_address ?? 'N/A',
            'delivery_date' => $this->required_date?->format('Y-m-d'),
        ];
    }

    /**
     * Get internal order display fields (for internal use only)
     */
    public function getInternalDisplayFields(): array
    {
        $externalFields = $this->getExternalDisplayFields();
        
        // Add internal-only fields
        $internalFields = [
            'warehouse_materials' => $this->getWarehouseMaterialDetails(),
            'customer_details' => $this->getCustomerDetails(),
            'delivery_measurements' => $this->delivery_specifications,
            'number_of_plates' => $this->number_of_plates ?? 0,
        ];

        return array_merge($externalFields, $internalFields);
    }

    /**
     * Calculate total order value
     */
    private function calculateOrderValue(): float
    {
        if ($this->price_per_ton && $this->delivered_weight) {
            return ($this->price_per_ton * $this->delivered_weight) / 1000; // Convert to tons
        }
        
        return $this->total_amount ?? 0;
    }

    /**
     * Get warehouse material details for internal display
     */
    private function getWarehouseMaterialDetails(): array
    {
        $materials = [];
        
        foreach ($this->orderItems as $item) {
            $product = $item->product;
            if ($product) {
                $materials[] = [
                    'material_name' => $product->name,
                    'material_description' => $product->description,
                    'available_weight_kg' => $product->weight ?? 0,
                    'length_cm' => $product->length ?? 0,
                    'width_cm' => $product->width ?? 0,
                    'type' => $product->type,
                    'grammage' => $product->grammage ?? 0,
                    'purchase_invoice_number' => $product->purchase_invoice_number,
                    'quality' => $product->quality,
                    'roll_number' => $product->roll_number,
                    'warehouse_name' => $product->stocks->first()?->warehouse?->name ?? 'N/A',
                    'product_source' => $product->source,
                ];
            }
        }

        return $materials;
    }

    /**
     * Get customer details for internal display
     */
    private function getCustomerDetails(): ?array
    {
        if (!$this->customer) {
            return null;
        }

        return [
            'customer_name' => $this->customer->name,
            'customer_location' => $this->customer->customer_location,
            'phone_number' => $this->customer->mobile_number,
            'account_representative' => $this->customer->account_representative,
        ];
    }

    /**
     * Get sequence number (for display purposes)
     */
    public function getSequenceNumberAttribute(): ?string
    {
        return $this->getAttributes()['sequence_number'] ?? sprintf('%06d', $this->id);
    }
}
