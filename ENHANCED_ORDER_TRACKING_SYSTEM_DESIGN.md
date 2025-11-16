# Enhanced Order Tracking System Design

## Current System Analysis

### Existing Models and Relationships

1. **Order Model**
   - Has `current_stage` enum field with stages: إنشاء, مراجعة, حجز_المواد, فرز, قص, تعبئة, فوترة, تسليم
   - Has `orderProcessings()` relationship to OrderProcessing model
   - Has stage color and priority methods

2. **OrderProcessing Model**
   - Links orders to work stages
   - Tracks status, timing, assignment, and priority
   - Basic processing tracking

3. **WorkStage Model**
   - Defines available work stages with English/Arabic names
   - Has order-based sorting
   - Basic stage definitions

4. **OrderStage Model** (from OrderProcessingService)
   - More detailed stage tracking with approval workflow
   - Weight tracking, warehouse transfers, waste recording
   - Approval status and user assignments

### Identified Issues

1. **Dual Stage Tracking Systems**: Order uses `current_stage` field while OrderProcessingService uses OrderStage model
2. **Inconsistent Stage Definitions**: WorkStage seeder is empty, stages defined in service
3. **Limited Visual Indicators**: Basic color coding exists but not comprehensive
4. **No Flexible Progression**: Current system assumes linear progression
5. **Role-Based Visibility**: Basic policies exist but not fully implemented for stages

## Enhanced System Design

### Unified Stage Tracking Architecture

#### Database Schema Enhancements

```sql
-- Enhanced order_processings table
ALTER TABLE order_processings ADD COLUMN (
    stage_color VARCHAR(20) DEFAULT 'gray',
    can_skip BOOLEAN DEFAULT FALSE,
    skip_reason TEXT NULL,
    skipped_at TIMESTAMP NULL,
    skipped_by INT NULL,
    visual_priority INT DEFAULT 1,
    estimated_duration INT NULL, -- minutes
    actual_duration INT NULL, -- minutes
    stage_metadata JSON NULL,
    FOREIGN KEY (skipped_by) REFERENCES users(id)
);

-- Enhanced work_stages table
ALTER TABLE work_stages ADD COLUMN (
    color VARCHAR(20) DEFAULT 'gray',
    icon VARCHAR(50) NULL,
    can_skip BOOLEAN DEFAULT FALSE,
    requires_role VARCHAR(50) NULL,
    estimated_duration INT NULL,
    stage_group VARCHAR(50) NULL, -- 'preparation', 'processing', 'delivery'
    is_mandatory BOOLEAN DEFAULT TRUE,
    skip_conditions JSON NULL
);

-- New order_stage_history table for audit trail
CREATE TABLE order_stage_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    work_stage_id BIGINT NOT NULL,
    previous_stage VARCHAR(100) NULL,
    new_stage VARCHAR(100) NOT NULL,
    action VARCHAR(50) NOT NULL, -- 'start', 'complete', 'skip', 'rollback'
    action_by INT NOT NULL,
    action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    metadata JSON NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (work_stage_id) REFERENCES work_stages(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(id)
);
```

#### Enhanced Models

### OrderProcessing Model Updates

```php
class OrderProcessing extends Model
{
    protected $fillable = [
        // existing fields...
        'stage_color',
        'can_skip',
        'skip_reason',
        'skipped_at',
        'skipped_by',
        'visual_priority',
        'estimated_duration',
        'actual_duration',
        'stage_metadata',
    ];

    protected $casts = [
        // existing casts...
        'can_skip' => 'boolean',
        'skipped_at' => 'datetime',
        'stage_metadata' => 'array',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
    ];

    // New methods for flexible progression
    public function canBeSkipped(): bool
    {
        return $this->can_skip && $this->status === 'pending';
    }

    public function skip(User $user, string $reason = null): bool
    {
        if (!$this->canBeSkipped()) return false;

        $this->update([
            'status' => 'skipped',
            'skip_reason' => $reason,
            'skipped_at' => now(),
            'skipped_by' => $user->id,
        ]);

        // Log to history
        $this->recordHistory('skip', $user->id, $reason);

        return true;
    }

    public function getDurationAttribute(): int
    {
        if ($this->completed_at && $this->started_at) {
            return $this->started_at->diffInMinutes($this->completed_at);
        }
        return 0;
    }

    public function getProgressPercentageAttribute(): float
    {
        return match($this->status) {
            'pending' => 0,
            'in_progress' => 50,
            'completed' => 100,
            'skipped' => 100,
            'cancelled' => 0,
            default => 0
        };
    }

    private function recordHistory(string $action, int $userId, string $notes = null): void
    {
        OrderStageHistory::create([
            'order_id' => $this->order_id,
            'work_stage_id' => $this->work_stage_id,
            'previous_stage' => $this->order->current_stage,
            'new_stage' => $this->workStage->name_en,
            'action' => $action,
            'action_by' => $userId,
            'notes' => $notes,
        ]);
    }
}
```

### WorkStage Model Enhancements

```php
class WorkStage extends Model
{
    protected $fillable = [
        // existing fields...
        'color',
        'icon',
        'can_skip',
        'requires_role',
        'estimated_duration',
        'stage_group',
        'is_mandatory',
        'skip_conditions',
    ];

    protected $casts = [
        // existing casts...
        'can_skip' => 'boolean',
        'is_mandatory' => 'boolean',
        'skip_conditions' => 'array',
        'estimated_duration' => 'integer',
    ];

    // New methods
    public function getRequiredRole(): ?string
    {
        return $this->requires_role;
    }

    public function canBeAccessedBy(User $user): bool
    {
        if (!$this->requires_role) return true;

        return $user->hasRole($this->requires_role);
    }

    public function getStageGroupColor(): string
    {
        return match($this->stage_group) {
            'preparation' => 'blue',
            'processing' => 'orange',
            'delivery' => 'green',
            default => 'gray'
        };
    }
}
```

### Enhanced Order Model Methods

```php
class Order extends Model
{
    // Add new relationship
    public function stageHistory(): HasMany
    {
        return $this->hasMany(OrderStageHistory::class);
    }

    // Enhanced stage progression
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
        $this->update(['current_stage' => $stageName]);

        // Record history
        OrderStageHistory::create([
            'order_id' => $this->id,
            'work_stage_id' => $workStage->id,
            'previous_stage' => $this->current_stage,
            'new_stage' => $stageName,
            'action' => 'move',
            'action_by' => $user->id,
        ]);

        return true;
    }

    // Get visual stage progress
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
                'color' => $processing->stage_color,
                'progress' => $processing->progress_percentage,
                'can_skip' => $processing->can_skip,
                'duration' => $processing->duration,
                'estimated_duration' => $processing->estimated_duration,
            ];
        });
    }

    // Advanced filtering scope
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
        if (isset($filters['stages'])) {
            $query->whereIn('current_stage', $filters['stages']);
        }

        // Status filtering
        if (isset($filters['statuses'])) {
            $query->whereIn('status', $filters['statuses']);
        }

        // User visibility
        if (isset($filters['user'])) {
            $query->visibleToUser($filters['user']);
        }

        return $query;
    }
}
```

### Role-Based Visibility Policies

```php
class OrderPolicy
{
    // Enhanced view method
    public function view(User $user, Order $order): bool
    {
        // Admin sees all
        if ($user->hasRole('مدير_شامل')) return true;

        // Users see orders they created or are assigned to
        if ($order->created_by === $user->id || $order->assigned_to === $user->id) {
            return true;
        }

        // Role-based stage visibility
        return $this->canViewOrderInStage($user, $order);
    }

    private function canViewOrderInStage(User $user, Order $order): bool
    {
        $stageRoles = [
            'فرز' => ['مسؤول_فرازة', 'مدير_شامل'],
            'قص' => ['مسؤول_قصاصة', 'مدير_شامل'],
            'تسليم' => ['مسؤول_تسليم', 'مدير_شامل'],
            'فوترة' => ['محاسب', 'مدير_شامل'],
        ];

        $currentStage = $order->current_stage;

        if (isset($stageRoles[$currentStage])) {
            return $user->hasAnyRole($stageRoles[$currentStage]);
        }

        // Default visibility for other stages
        return $user->hasRole(['مدير_مبيعات', 'مدير_مشتريات', 'مدير_شامل']);
    }
}
```

### Visual Stage Display Component

#### Filament Table Enhancement

```php
// In OrdersTable.php
TextColumn::make('current_stage')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'إنشاء' => 'gray',
        'مراجعة' => 'yellow',
        'حجز_المواد' => 'blue',
        'فرز' => 'purple',
        'قص' => 'orange',
        'تعبئة' => 'indigo',
        'فوترة' => 'green',
        'تسليم' => 'emerald',
        default => 'gray',
    }),

// Add progress column
TextColumn::make('stage_progress')
    ->label('Stage Progress')
    ->view('filament.tables.columns.stage-progress'),
```

#### Stage Progress View Component

```blade
{{-- resources/views/filament/tables/columns/stage-progress.blade.php --}}
<div class="space-y-2">
    @foreach($getRecord()->stage_progress as $stage)
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 rounded-full {{ $stage['color'] === 'green' ? 'bg-green-500' : 'bg-gray-300' }}"></div>
            <span class="text-sm">{{ $stage['name'] }}</span>
            <div class="flex-1 bg-gray-200 rounded-full h-2">
                <div class="bg-{{ $stage['color'] }}-500 h-2 rounded-full" style="width: {{ $stage['progress'] }}%"></div>
            </div>
            @if($stage['can_skip'])
                <button class="text-xs text-blue-500 hover:text-blue-700">Skip</button>
            @endif
        </div>
    @endforeach
</div>
```

### Advanced Filtering System

#### API Controller Enhancement

```php
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query();

        // Apply advanced filters
        $query = $query->advancedFilter([
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'stages' => $request->stages,
            'statuses' => $request->statuses,
            'user' => auth()->user(),
        ]);

        // Sorting
        if ($request->sort_by) {
            $direction = $request->sort_direction ?? 'asc';
            $query->orderBy($request->sort_by, $direction);
        } else {
            $query->byPriority();
        }

        $orders = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'orders' => $orders,
            'filters' => [
                'available_stages' => WorkStage::active()->pluck('name', 'id'),
                'available_statuses' => ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            ]
        ]);
    }
}
```

### WorkStage Seeder Update

```php
class WorkStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            [
                'name_en' => 'Creation',
                'name_ar' => 'إنشاء',
                'description_en' => 'Order creation and initial setup',
                'description_ar' => 'إنشاء الطلب وإعداده الأولي',
                'order' => 1,
                'color' => 'gray',
                'icon' => 'heroicon-o-plus-circle',
                'can_skip' => false,
                'requires_role' => null,
                'estimated_duration' => 30,
                'stage_group' => 'preparation',
                'is_mandatory' => true,
            ],
            [
                'name_en' => 'Review',
                'name_ar' => 'مراجعة',
                'description_en' => 'Order review and approval',
                'description_ar' => 'مراجعة الطلب والموافقة عليه',
                'order' => 2,
                'color' => 'yellow',
                'icon' => 'heroicon-o-eye',
                'can_skip' => false,
                'requires_role' => 'مدير_مبيعات',
                'estimated_duration' => 60,
                'stage_group' => 'preparation',
                'is_mandatory' => true,
            ],
            [
                'name_en' => 'Material Reservation',
                'name_ar' => 'حجز_المواد',
                'description_en' => 'Reserve required materials from warehouse',
                'description_ar' => 'حجز المواد المطلوبة من المستودع',
                'order' => 3,
                'color' => 'blue',
                'icon' => 'heroicon-o-archive-box',
                'can_skip' => true,
                'requires_role' => 'مسؤول_مستودع',
                'estimated_duration' => 45,
                'stage_group' => 'processing',
                'is_mandatory' => false,
                'skip_conditions' => ['materials_available' => false],
            ],
            [
                'name_en' => 'Sorting',
                'name_ar' => 'فرز',
                'description_en' => 'Sort and prepare materials',
                'description_ar' => 'فرز وتحضير المواد',
                'order' => 4,
                'color' => 'purple',
                'icon' => 'heroicon-o-squares-2x2',
                'can_skip' => false,
                'requires_role' => 'مسؤول_فرازة',
                'estimated_duration' => 90,
                'stage_group' => 'processing',
                'is_mandatory' => true,
            ],
            [
                'name_en' => 'Cutting',
                'name_ar' => 'قص',
                'description_en' => 'Cut materials to required specifications',
                'description_ar' => 'قص المواد حسب المواصفات المطلوبة',
                'order' => 5,
                'color' => 'orange',
                'icon' => 'heroicon-o-scissors',
                'can_skip' => false,
                'requires_role' => 'مسؤول_قصاصة',
                'estimated_duration' => 120,
                'stage_group' => 'processing',
                'is_mandatory' => true,
            ],
            [
                'name_en' => 'Packaging',
                'name_ar' => 'تعبئة',
                'description_en' => 'Package finished products',
                'description_ar' => 'تعبئة المنتجات النهائية',
                'order' => 6,
                'color' => 'indigo',
                'icon' => 'heroicon-o-gift',
                'can_skip' => true,
                'requires_role' => null,
                'estimated_duration' => 30,
                'stage_group' => 'processing',
                'is_mandatory' => false,
            ],
            [
                'name_en' => 'Invoicing',
                'name_ar' => 'فوترة',
                'description_en' => 'Create and process invoices',
                'description_ar' => 'إنشاء ومعالجة الفواتير',
                'order' => 7,
                'color' => 'green',
                'icon' => 'heroicon-o-document-text',
                'can_skip' => false,
                'requires_role' => 'محاسب',
                'estimated_duration' => 60,
                'stage_group' => 'delivery',
                'is_mandatory' => true,
            ],
            [
                'name_en' => 'Delivery',
                'name_ar' => 'تسليم',
                'description_en' => 'Deliver order to customer',
                'description_ar' => 'تسليم الطلب للعميل',
                'order' => 8,
                'color' => 'emerald',
                'icon' => 'heroicon-o-truck',
                'can_skip' => false,
                'requires_role' => 'مسؤول_تسليم',
                'estimated_duration' => 45,
                'stage_group' => 'delivery',
                'is_mandatory' => true,
            ],
        ];

        foreach ($stages as $stage) {
            WorkStage::updateOrCreate(
                ['name_en' => $stage['name_en']],
                $stage
            );
        }
    }
}
```

## Implementation Plan

### Phase 1: Database and Model Updates
1. Create migration for enhanced tracking features
2. Update OrderProcessing model with new fields and methods
3. Update WorkStage model with visual and role-based features
4. Create OrderStageHistory model

### Phase 2: Service Layer Enhancements
1. Update OrderProcessingService with flexible progression logic
2. Create OrderTrackingService for advanced filtering and visualization
3. Implement stage history tracking

### Phase 3: UI and API Updates
1. Update Filament resources with visual stage display
2. Implement advanced filtering in API controllers
3. Create stage progress components

### Phase 4: Testing and Validation
1. Unit tests for stage progression logic
2. Integration tests for role-based visibility
3. Performance testing for large datasets

This design provides a comprehensive, flexible order tracking system that supports the requirements for sequential numbering, stage progression, visual indicators, role-based visibility, and advanced filtering.