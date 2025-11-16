<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    /**
     * Determine whether the user can view any suppliers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view suppliers', 'web') ||
               $user->hasPermissionTo('manage suppliers', 'web') ||
               $user->hasRole('مدير_شامل');
    }

    /**
     * Determine whether the user can view the supplier.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo('view suppliers');
    }

    /**
     * Determine whether the user can create suppliers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create suppliers');
    }

    /**
     * Determine whether the user can update the supplier.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        if (!$user->hasPermissionTo('edit suppliers')) {
            return false;
        }

        // Warehouse keepers can update supplier information
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can update any supplier
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the supplier.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        // Check if supplier has active materials
        if ($supplier->materials()->where('status', 'متاح')->exists()) {
            return false;
        }

        // Warehouse keepers can delete suppliers (with permission)
        if ($user->hasRole('أمين_مستودع') && $user->hasPermissionTo('delete suppliers')) {
            return true;
        }

        // General managers can delete any supplier
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view supplier performance reports.
     */
    public function viewPerformanceReports(User $user, Supplier $supplier): bool
    {
        if (!$user->hasPermissionTo('view reports')) {
            return false;
        }

        // Warehouse keepers can view supplier reports
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can view all supplier reports
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage supplier contracts.
     */
    public function manageContracts(User $user, Supplier $supplier): bool
    {
        // Warehouse keepers can manage supplier contracts
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can manage all supplier contracts
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view supplier material history.
     */
    public function viewMaterialHistory(User $user, Supplier $supplier): bool
    {
        // Warehouse keepers can view supplier material history
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can view all supplier material histories
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can evaluate suppliers.
     */
    public function evaluate(User $user, Supplier $supplier): bool
    {
        // Warehouse keepers can evaluate suppliers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can evaluate any supplier
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can export supplier data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can view supplier reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can manage supplier communications.
     */
    public function manageCommunications(User $user, Supplier $supplier): bool
    {
        // Warehouse keepers can manage supplier communications
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can manage all supplier communications
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve new suppliers.
     */
    public function approve(User $user, Supplier $supplier): bool
    {
        // Only pending suppliers can be approved
        if ($supplier->status !== 'معلق') {
            return false;
        }

        // Warehouse keepers can approve suppliers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can approve any supplier
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can deactivate suppliers.
     */
    public function deactivate(User $user, Supplier $supplier): bool
    {
        // Warehouse keepers can deactivate suppliers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can deactivate any supplier
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }
}