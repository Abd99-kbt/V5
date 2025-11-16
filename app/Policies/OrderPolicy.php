<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view orders', 'web') ||
               $user->hasPermissionTo('manage orders', 'web') ||
               $user->hasRole('مدير_شامل');
    }

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, Order $order): bool
    {
        // Admin sees all orders
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        // Users can view orders they created or are assigned to
        if ($user->hasPermissionTo('view orders')) {
            return true;
        }

        // Sales employees can view orders for customers they manage
        if ($user->hasRole('موظف_مبيعات') && $order->created_by === $user->id) {
            return true;
        }

        // Order trackers can view orders they are assigned to
        if ($user->hasRole('متابع_طلبات') && $order->assigned_to === $user->id) {
            return true;
        }

        // Role-based stage visibility using enhanced stage system
        return $this->canViewOrderInStage($user, $order);
    }

    /**
     * Check if user can view order in specific stage
     */
    private function canViewOrderInStage(User $user, Order $order): bool
    {
        $stageRoles = [
            'فرز' => ['مسؤول_فرازة', 'مدير_شامل'],
            'قص' => ['مسؤول_قصاصة', 'مدير_شامل'],
            'تسليم' => ['مسؤول_تسليم', 'مدير_شامل'],
            'فوترة' => ['محاسب', 'مدير_شامل'],
            'حجز_المواد' => ['مسؤول_مستودع', 'مدير_شامل'],
            'مراجعة' => ['مدير_مبيعات', 'مدير_شامل'],
        ];

        $currentStage = $order->current_stage;

        if (isset($stageRoles[$currentStage])) {
            return $user->hasAnyRole($stageRoles[$currentStage]);
        }

        // Default visibility for other stages
        return $user->hasAnyRole(['مدير_مبيعات', 'مدير_مشتريات', 'مدير_شامل', 'متابع_طلبات']);
    }

    /**
     * Determine whether the user can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create orders');
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, Order $order): bool
    {
        // General permission check
        if (!$user->hasPermissionTo('edit orders')) {
            return false;
        }

        // Admin can edit any order
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        // Users can edit orders they created (if not approved yet)
        if ($user->hasRole('موظف_مبيعات') && $order->created_by === $user->id && $order->status === 'مسودة') {
            return true;
        }

        // Order trackers can edit orders they are assigned to
        if ($user->hasRole('متابع_طلبات') && $order->assigned_to === $user->id) {
            return true;
        }

        // Role-based stage update permissions
        return $this->canUpdateOrderInStage($user, $order);
    }

    /**
     * Check if user can update order in specific stage
     */
    private function canUpdateOrderInStage(User $user, Order $order): bool
    {
        $stageRoles = [
            'فرز' => ['مسؤول_فرازة'],
            'قص' => ['مسؤول_قصاصة'],
            'تسليم' => ['مسؤول_تسليم'],
            'فوترة' => ['محاسب'],
            'حجز_المواد' => ['مسؤول_مستودع'],
            'مراجعة' => ['مدير_مبيعات'],
        ];

        $currentStage = $order->current_stage;

        if (isset($stageRoles[$currentStage])) {
            return $user->hasAnyRole($stageRoles[$currentStage]);
        }

        // Warehouse keepers can edit any order
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        // Only draft orders can be deleted
        if ($order->status !== 'مسودة') {
            return false;
        }

        // Users can delete orders they created
        if ($user->hasPermissionTo('delete orders') && $order->created_by === $user->id) {
            return true;
        }

        // General managers can delete any draft order
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve the order.
     */
    public function approve(User $user, Order $order): bool
    {
        // Only orders in review status can be approved
        if ($order->status !== 'قيد_المراجعة') {
            return false;
        }

        // General managers can approve any order
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        // Sales managers can approve orders
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can process the order.
     */
    public function process(User $user, Order $order): bool
    {
        return $user->hasPermissionTo('process orders') &&
               in_array($order->status, ['مؤكد', 'قيد_التنفيذ']);
    }

    /**
     * Determine whether the user can complete the order.
     */
    public function complete(User $user, Order $order): bool
    {
        // Only confirmed orders can be completed
        if ($order->status !== 'قيد_التنفيذ') {
            return false;
        }

        // Order trackers can complete orders they are assigned to
        if ($user->hasRole('متابع_طلبات') && $order->assigned_to === $user->id) {
            return true;
        }

        // General managers can complete any order
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, Order $order): bool
    {
        // Only certain statuses can be cancelled
        if (!in_array($order->status, ['مسودة', 'قيد_المراجعة', 'مؤكد'])) {
            return false;
        }

        // Users can cancel orders they created
        if ($user->hasRole('موظف_مبيعات') && $order->created_by === $user->id) {
            return true;
        }

        // General managers can cancel any order
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }
}