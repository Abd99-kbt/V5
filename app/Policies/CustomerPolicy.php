<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view customers', 'web');
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        if (!$user->hasPermissionTo('view customers', 'web')) {
            return false;
        }

        // Sales employees can view customers assigned to them
        if ($user->hasRole('موظف_مبيعات')) {
            return $customer->sales_rep_id === $user->id;
        }

        // Sales managers can view all customers
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // Order trackers can view customers for orders they handle
        if ($user->hasRole('متابع_طلبات')) {
            return true; // They need to see customer info for orders
        }

        // Accountants can view customers for invoicing
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can view all customers
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create customers');
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        if (!$user->hasPermissionTo('edit customers')) {
            return false;
        }

        // Sales employees can update customers assigned to them
        if ($user->hasRole('موظف_مبيعات')) {
            return $customer->sales_rep_id === $user->id;
        }

        // Sales managers can update any customer
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // General managers can update any customer
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        // Check if customer has active orders
        if ($customer->orders()->whereIn('status', ['مؤكد', 'قيد_التنفيذ', 'مكتمل'])->exists()) {
            return false;
        }

        // Sales managers can delete customers
        if ($user->hasRole('مدير_مبيعات') && $user->hasPermissionTo('delete customers')) {
            return true;
        }

        // General managers can delete any customer
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign sales rep to the customer.
     */
    public function assignSalesRep(User $user, Customer $customer): bool
    {
        // Sales managers can assign sales reps
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // General managers can assign sales reps
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view customer credit information.
     */
    public function viewCreditInfo(User $user, Customer $customer): bool
    {
        // Sales employees can view credit info for their customers
        if ($user->hasRole('موظف_مبيعات')) {
            return $customer->sales_rep_id === $user->id;
        }

        // Sales managers can view all credit info
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // Accountants can view credit info
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can view all credit info
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update customer credit limit.
     */
    public function updateCreditLimit(User $user, Customer $customer): bool
    {
        // Sales managers can update credit limits
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // General managers can update credit limits
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view customer order history.
     */
    public function viewOrderHistory(User $user, Customer $customer): bool
    {
        // Sales employees can view order history for their customers
        if ($user->hasRole('موظف_مبيعات')) {
            return $customer->sales_rep_id === $user->id;
        }

        // Sales managers can view all order histories
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // Order trackers can view order histories
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        // Accountants can view order histories for billing
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can view all order histories
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can export customer data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('مدير_مبيعات') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can view customer reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('مدير_مبيعات') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can manage customer communications.
     */
    public function manageCommunications(User $user, Customer $customer): bool
    {
        // Sales employees can manage communications for their customers
        if ($user->hasRole('موظف_مبيعات')) {
            return $customer->sales_rep_id === $user->id;
        }

        // Sales managers can manage all customer communications
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        // General managers can manage all customer communications
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }
}