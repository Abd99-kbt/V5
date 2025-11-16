<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Warehouse extends Model
{
    use HasFactory;
    // use Backpack\CRUD\app\Models\Traits\CrudTrait; // Temporarily disabled
    
    protected $fillable = [
        'name_en',
        'name_ar',
        'name',
        'code',
        'address_en',
        'address_ar',
        'phone',
        'manager_name',
        'type',
        'total_capacity',
        'used_capacity',
        'reserved_capacity',
        'is_active',
        'is_main',
        'accepts_transfers',
        'requires_approval',
    ];

    protected $casts = [
        'total_capacity' => 'decimal:2',
        'used_capacity' => 'decimal:2',
        'reserved_capacity' => 'decimal:2',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'accepts_transfers' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    /**
     * Get the stocks for the warehouse.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Get the orders for the warehouse.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the employee assignments for the warehouse.
     */
    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(WarehouseEmployeeAssignment::class);
    }

    /**
     * Get active employees for the warehouse.
     */
    public function activeEmployees()
    {
        return $this->belongsToMany(User::class, 'warehouse_employee_assignments')
                    ->wherePivot('is_active', true)
                    ->withPivot('role', 'assigned_at');
    }

    /**
     * Get the warehouse name based on current locale
     */
    public function getNameAttribute(): ?string
    {
        $locale = app()->getLocale();
        $name = $locale === 'ar' ? $this->name_ar : $this->name_en;

        // Fallback to virtual column if both names are null
        if ($name === null) {
            return $this->getAttributes()['name'] ?? 'Unnamed Warehouse';
        }

        return $name;
    }

    /**
     * Get the address based on current locale
     */
    public function getAddressAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' ? $this->address_ar : $this->address_en;
    }

    /**
     * Get capacity utilization percentage
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->total_capacity == 0) {
            return 0;
        }
        return round(($this->used_capacity / $this->total_capacity) * 100, 2);
    }

    /**
     * Get available capacity
     */
    public function getAvailableCapacityAttribute(): float
    {
        return $this->total_capacity - $this->used_capacity - $this->reserved_capacity;
    }

    /**
     * Get warehouse type label in Arabic
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'main' => 'مستودع رئيسي',
            'scrap' => 'مستودع خردة',
            'sorting' => 'مستودع فرز',
            'custody' => 'مستودع حضانة',
            default => $this->type
        };
    }

    /**
     * Check if warehouse is main type
     */
    public function isMain(): bool
    {
        return $this->type === 'main';
    }

    /**
     * Check if warehouse is scrap type
     */
    public function isScrap(): bool
    {
        return $this->type === 'scrap';
    }

    /**
     * Check if warehouse is sorting type
     */
    public function isSorting(): bool
    {
        return $this->type === 'sorting';
    }

    /**
     * Check if warehouse is custody type
     */
    public function isCustody(): bool
    {
        return $this->type === 'custody';
    }

    /**
     * Check if warehouse can accept transfers
     */
    public function canAcceptTransfers(): bool
    {
        return $this->accepts_transfers && $this->is_active;
    }

    /**
     * Check if warehouse requires approval for operations
     */
    public function requiresApproval(): bool
    {
        return $this->requires_approval;
    }

    /**
     * Reserve capacity for incoming transfers
     */
    public function reserveCapacity(float $weight): bool
    {
        if ($this->available_capacity >= $weight) {
            $this->reserved_capacity += $weight;
            return $this->save();
        }
        return false;
    }

    /**
     * Release reserved capacity
     */
    public function releaseCapacity(float $weight): bool
    {
        if ($this->reserved_capacity >= $weight) {
            $this->reserved_capacity -= $weight;
            return $this->save();
        }
        return false;
    }

    /**
     * Add to used capacity
     */
    public function addUsedCapacity(float $weight): bool
    {
        if ($this->available_capacity >= $weight) {
            $this->used_capacity += $weight;
            return $this->save();
        }
        return false;
    }

    /**
     * Remove from used capacity
     */
    public function removeUsedCapacity(float $weight): bool
    {
        if ($this->used_capacity >= $weight) {
            $this->used_capacity -= $weight;
            return $this->save();
        }
        return false;
    }

    /**
     * Get warehouse statistics
     */
    public function getStatisticsAttribute(): array
    {
        return [
            'total_products' => $this->stocks()->count(),
            'total_weight' => $this->stocks()->sum('quantity'),
            'total_value' => $this->stocks()->sum(DB::raw('quantity * unit_cost')),
            'utilization_percentage' => $this->utilization_percentage,
            'available_capacity' => $this->available_capacity,
            'reserved_capacity' => $this->reserved_capacity,
            'total_employees' => $this->employeeAssignments()->active()->count(),
            'managers_count' => $this->employeeAssignments()->active()->role('manager')->count(),
            'supervisors_count' => $this->employeeAssignments()->active()->role('supervisor')->count(),
            'workers_count' => $this->employeeAssignments()->active()->role('worker')->count(),
        ];
    }

    /**
     * Assign employee to warehouse
     */
    public function assignEmployee(int $userId, string $role = 'worker', string $notes = null): WarehouseEmployeeAssignment
    {
        return $this->employeeAssignments()->create([
            'user_id' => $userId,
            'role' => $role,
            'is_active' => true,
            'assigned_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Unassign employee from warehouse
     */
    public function unassignEmployee(int $userId): bool
    {
        $assignment = $this->employeeAssignments()
                          ->where('user_id', $userId)
                          ->where('is_active', true)
                          ->first();

        if ($assignment) {
            return $assignment->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);
        }

        return false;
    }

    /**
     * Get employees by role
     */
    public function getEmployeesByRole(string $role)
    {
        return $this->activeEmployees()->wherePivot('role', $role)->get();
    }

    /**
     * Check if user is assigned to this warehouse
     */
    public function isEmployeeAssigned(int $userId): bool
    {
        return $this->employeeAssignments()
                   ->where('user_id', $userId)
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Get employee's role in this warehouse
     */
    public function getEmployeeRole(int $userId): ?string
    {
        $assignment = $this->employeeAssignments()
                          ->where('user_id', $userId)
                          ->where('is_active', true)
                          ->first();

        return $assignment?->role;
    }

    /**
     * Get available employees for handover
     */
    public function getAvailableEmployeesForHandover(int $excludeUserId = null)
    {
        $query = $this->activeEmployees();

        if ($excludeUserId) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        return $query->get();
    }
}
