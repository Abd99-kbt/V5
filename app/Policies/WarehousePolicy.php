<?php

namespace App\Policies;

use App\Models\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    /**
     * Determine whether the user can view any warehouses.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view warehouses');
    }

    /**
     * Determine whether the user can view the warehouse.
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        if (!$user->hasPermissionTo('view warehouses')) {
            return false;
        }

        // Warehouse keepers can view all warehouses
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Managers of specific warehouses can view their warehouses
        if ($warehouse->manager_id === $user->id) {
            return true;
        }

        // Cutting and sorting managers can view their respective warehouses
        if ($user->hasRole('مسؤول_قصاصة') && $warehouse->type === 'مستودع_قص') {
            return true;
        }

        if ($user->hasRole('مسؤول_فرازة') && $warehouse->type === 'مستودع_فرز') {
            return true;
        }

        // Delivery managers can view delivery warehouse
        if ($user->hasRole('مسؤول_تسليم') && $warehouse->type === 'مستودع_نهائي') {
            return true;
        }

        // General managers can view all warehouses
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create warehouses.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create warehouses');
    }

    /**
     * Determine whether the user can update the warehouse.
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        if (!$user->hasPermissionTo('edit warehouses')) {
            return false;
        }

        // Warehouse keepers can update warehouse details
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Warehouse managers can update their own warehouses
        if ($warehouse->manager_id === $user->id) {
            return true;
        }

        // General managers can update any warehouse
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the warehouse.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        // Only empty warehouses can be deleted
        if ($warehouse->current_utilization > 0) {
            return false;
        }

        // Only general managers can delete warehouses
        if ($user->hasRole('مدير_شامل') && $user->hasPermissionTo('delete warehouses')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage stock in the warehouse.
     */
    public function manageStock(User $user, Warehouse $warehouse): bool
    {
        if (!$user->hasPermissionTo('manage stock')) {
            return false;
        }

        // Warehouse keepers can manage stock in all warehouses
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Warehouse managers can manage stock in their warehouses
        if ($warehouse->manager_id === $user->id) {
            return true;
        }

        // Cutting managers can manage stock in cutting warehouses
        if ($user->hasRole('مسؤول_قصاصة') && $warehouse->type === 'مستودع_قص') {
            return true;
        }

        // Sorting managers can manage stock in sorting warehouses
        if ($user->hasRole('مسؤول_فرازة') && $warehouse->type === 'مستودع_فرز') {
            return true;
        }

        // Delivery managers can manage stock in final warehouses
        if ($user->hasRole('مسؤول_تسليم') && $warehouse->type === 'مستودع_نهائي') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view stock reports for the warehouse.
     */
    public function viewStockReports(User $user, Warehouse $warehouse): bool
    {
        if (!$user->hasPermissionTo('view reports')) {
            return false;
        }

        // Warehouse keepers can view reports for all warehouses
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Warehouse managers can view reports for their warehouses
        if ($warehouse->manager_id === $user->id) {
            return true;
        }

        // General managers can view all warehouse reports
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can transfer materials from/to the warehouse.
     */
    public function transferMaterials(User $user, Warehouse $warehouse): bool
    {
        // Warehouse keepers can transfer from/to any warehouse
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Warehouse managers can transfer from their warehouses
        if ($warehouse->manager_id === $user->id) {
            return true;
        }

        // Cutting managers can receive materials in cutting warehouses
        if ($user->hasRole('مسؤول_قصاصة') && $warehouse->type === 'مستودع_قص') {
            return true;
        }

        // Sorting managers can receive materials in sorting warehouses
        if ($user->hasRole('مسؤول_فرازة') && $warehouse->type === 'مستودع_فرز') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign a manager to the warehouse.
     */
    public function assignManager(User $user, Warehouse $warehouse): bool
    {
        // Only general managers can assign warehouse managers
        return $user->hasRole('مدير_شامل');
    }

    /**
     * Determine whether the user can view warehouse utilization reports.
     */
    public function viewUtilizationReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can configure warehouse settings.
     */
    public function configure(User $user, Warehouse $warehouse): bool
    {
        // Warehouse keepers can configure warehouse settings
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Warehouse managers can configure their warehouses
        if ($warehouse->manager_id === $user->id) {
            return true;
        }

        // General managers can configure any warehouse
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manually add/modify materials.
     * Only supervisory staff can manually add/modify materials.
     */
    public function manuallyAddModifyMaterials(User $user, Warehouse $warehouse): bool
    {
        // Only supervisory staff (manager, administrator) can manually add/modify materials
        return $user->hasRole('مدير_شامل') || 
               $user->hasRole('مدير_مبيعات') || 
               $user->hasRole('أمين_مستودع') || 
               $user->hasRole('مسؤول_قصاصة') || 
               $user->hasRole('مسؤول_فرازة');
    }

    /**
     * Determine whether the user can affect warehouse through order-related operations.
     * All staff can affect warehouses through order-related operations.
     */
    public function affectViaOrder(User $user, Warehouse $warehouse): bool
    {
        // All staff with warehouse access can affect warehouses through orders
        return $user->hasAnyRole([
            'مدير_شامل',
            'مدير_مبيعات',
            'أمين_مستودع',
            'مسؤول_قصاصة',
            'مسؤول_فرازة',
            'مسؤول_تسليم',
            'موظف_مبيعات',
            'موظف_مستودع'
        ]) && $user->hasPermissionTo('manage stock');
    }

    /**
     * Determine whether the user can create manual stock entries.
     */
    public function createManualStockEntry(User $user, Warehouse $warehouse): bool
    {
        return $this->manuallyAddModifyMaterials($user, $warehouse);
    }

    /**
     * Determine whether the user can modify existing stock levels manually.
     */
    public function modifyStockLevels(User $user, Warehouse $warehouse): bool
    {
        // Allow modification through order operations for all staff
        if ($this->affectViaOrder($user, $warehouse)) {
            return true;
        }

        // Allow manual modification only for supervisory staff
        return $this->manuallyAddModifyMaterials($user, $warehouse);
    }
}