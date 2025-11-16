<?php

namespace App\Policies;

use App\Models\StockAlert;
use App\Models\User;

class StockAlertPolicy
{
    /**
     * Determine whether the user can view any stock alerts.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view stock alerts', 'web') ||
               $user->hasPermissionTo('manage stock alerts', 'web') ||
               $user->hasRole('مدير_شامل');
    }

    /**
     * Determine whether the user can view the stock alert.
     */
    public function view(User $user, StockAlert $stockAlert): bool
    {
        if (!$user->hasPermissionTo('view stock alerts')) {
            return false;
        }

        // Warehouse keepers can view all stock alerts
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can view all stock alerts
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create stock alerts.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage stock alerts');
    }

    /**
     * Determine whether the user can update the stock alert.
     */
    public function update(User $user, StockAlert $stockAlert): bool
    {
        if (!$user->hasPermissionTo('manage stock alerts')) {
            return false;
        }

        // Warehouse keepers can update stock alerts
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can update any stock alert
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the stock alert.
     */
    public function delete(User $user, StockAlert $stockAlert): bool
    {
        // Warehouse keepers can delete stock alerts
        if ($user->hasRole('أمين_مستودع') && $user->hasPermissionTo('manage stock alerts')) {
            return true;
        }

        // General managers can delete any stock alert
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can acknowledge the stock alert.
     */
    public function acknowledge(User $user, StockAlert $stockAlert): bool
    {
        // Only active alerts can be acknowledged
        if ($stockAlert->status !== 'نشط') {
            return false;
        }

        // Warehouse keepers can acknowledge stock alerts
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can acknowledge any stock alert
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can resolve the stock alert.
     */
    public function resolve(User $user, StockAlert $stockAlert): bool
    {
        // Only acknowledged alerts can be resolved
        if ($stockAlert->status !== 'معترف_به') {
            return false;
        }

        // Warehouse keepers can resolve stock alerts
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can resolve any stock alert
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can configure stock alert thresholds.
     */
    public function configureThresholds(User $user): bool
    {
        // Warehouse keepers can configure thresholds
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can configure any thresholds
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view stock alert reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can export stock alert data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can set up automatic alerts.
     */
    public function setupAutomaticAlerts(User $user): bool
    {
        // Warehouse keepers can set up automatic alerts
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can set up any automatic alerts
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage alert notifications.
     */
    public function manageNotifications(User $user): bool
    {
        // Warehouse keepers can manage notifications
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can manage any notifications
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }
}