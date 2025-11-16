<?php

namespace App\Policies;

use App\Models\Waste;
use App\Models\User;

class WastePolicy
{
    /**
     * Determine whether the user can view any wastes.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view wastes');
    }

    /**
     * Determine whether the user can view the waste.
     */
    public function view(User $user, Waste $waste): bool
    {
        if (!$user->hasPermissionTo('view wastes')) {
            return false;
        }

        // Cutting managers can view waste from cutting processes
        if ($user->hasRole('مسؤول_قصاصة') && $waste->stage === 'قص') {
            return true;
        }

        // Sorting managers can view waste from sorting processes
        if ($user->hasRole('مسؤول_فرازة') && $waste->stage === 'فرز') {
            return true;
        }

        // Warehouse keepers can view all wastes
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can view all wastes
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create wastes.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage wastes');
    }

    /**
     * Determine whether the user can update the waste.
     */
    public function update(User $user, Waste $waste): bool
    {
        if (!$user->hasPermissionTo('manage wastes')) {
            return false;
        }

        // Users can only update wastes they reported
        if ($waste->reported_by !== $user->id) {
            return false;
        }

        // Cutting managers can update cutting waste
        if ($user->hasRole('مسؤول_قصاصة') && $waste->stage === 'قص') {
            return true;
        }

        // Sorting managers can update sorting waste
        if ($user->hasRole('مسؤول_فرازة') && $waste->stage === 'فرز') {
            return true;
        }

        // Warehouse keepers can update any waste
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can update any waste
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the waste.
     */
    public function delete(User $user, Waste $waste): bool
    {
        // Only unresolved wastes can be deleted
        if ($waste->resolved_at !== null) {
            return false;
        }

        // Users can only delete wastes they reported
        if ($waste->reported_by !== $user->id) {
            return false;
        }

        // Warehouse keepers can delete wastes
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can delete any waste
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can resolve the waste.
     */
    public function resolve(User $user, Waste $waste): bool
    {
        // Only unresolved wastes can be resolved
        if ($waste->resolved_at !== null) {
            return false;
        }

        // Cutting managers can resolve cutting waste
        if ($user->hasRole('مسؤول_قصاصة') && $waste->stage === 'قص') {
            return true;
        }

        // Sorting managers can resolve sorting waste
        if ($user->hasRole('مسؤول_فرازة') && $waste->stage === 'فرز') {
            return true;
        }

        // Warehouse keepers can resolve any waste
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can resolve any waste
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign responsibility for the waste.
     */
    public function assignResponsibility(User $user, Waste $waste): bool
    {
        // Warehouse keepers can assign responsibility
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can assign responsibility
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view waste reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can export waste data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can analyze waste patterns.
     */
    public function analyzePatterns(User $user): bool
    {
        // Warehouse keepers can analyze waste patterns
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can analyze waste patterns
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can implement corrective actions.
     */
    public function implementCorrectiveActions(User $user, Waste $waste): bool
    {
        // Responsible person can implement corrective actions
        if ($waste->responsible_person === $user->id) {
            return true;
        }

        // Cutting managers can implement corrective actions for cutting waste
        if ($user->hasRole('مسؤول_قصاصة') && $waste->stage === 'قص') {
            return true;
        }

        // Sorting managers can implement corrective actions for sorting waste
        if ($user->hasRole('مسؤول_فرازة') && $waste->stage === 'فرز') {
            return true;
        }

        // Warehouse keepers can implement corrective actions
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can implement any corrective actions
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }
}