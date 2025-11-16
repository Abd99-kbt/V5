<?php

namespace App\Policies;

use App\Models\WorkStage;
use App\Models\User;

class WorkStagePolicy
{
    /**
     * Determine whether the user can view any work stages.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view work stages');
    }

    /**
     * Determine whether the user can view the work stage.
     */
    public function view(User $user, WorkStage $workStage): bool
    {
        return $user->hasPermissionTo('view work stages');
    }

    /**
     * Determine whether the user can create work stages.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage work stages');
    }

    /**
     * Determine whether the user can update the work stage.
     */
    public function update(User $user, WorkStage $workStage): bool
    {
        if (!$user->hasPermissionTo('manage work stages')) {
            return false;
        }

        // Cutting managers can update cutting-related stages
        if ($user->hasRole('مسؤول_قصاصة') && $workStage->name === 'قص') {
            return true;
        }

        // Sorting managers can update sorting-related stages
        if ($user->hasRole('مسؤول_فرازة') && $workStage->name === 'فرز') {
            return true;
        }

        // Warehouse keepers can update work stages
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can update any work stage
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the work stage.
     */
    public function delete(User $user, WorkStage $workStage): bool
    {
        // Check if work stage is in use
        if ($workStage->orderProcessings()->exists()) {
            return false;
        }

        // Warehouse keepers can delete work stages
        if ($user->hasRole('أمين_مستودع') && $user->hasPermissionTo('manage work stages')) {
            return true;
        }

        // General managers can delete any work stage
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can start a work stage.
     */
    public function start(User $user, WorkStage $workStage): bool
    {
        // Cutting managers can start cutting stages
        if ($user->hasRole('مسؤول_قصاصة') && $workStage->name === 'قص') {
            return true;
        }

        // Sorting managers can start sorting stages
        if ($user->hasRole('مسؤول_فرازة') && $workStage->name === 'فرز') {
            return true;
        }

        // Warehouse keepers can start any stage
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Order trackers can start stages
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can complete a work stage.
     */
    public function complete(User $user, WorkStage $workStage): bool
    {
        // Cutting managers can complete cutting stages
        if ($user->hasRole('مسؤول_قصاصة') && $workStage->name === 'قص') {
            return true;
        }

        // Sorting managers can complete sorting stages
        if ($user->hasRole('مسؤول_فرازة') && $workStage->name === 'فرز') {
            return true;
        }

        // Warehouse keepers can complete any stage
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Order trackers can complete stages
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can pause a work stage.
     */
    public function pause(User $user, WorkStage $workStage): bool
    {
        // Cutting managers can pause cutting stages
        if ($user->hasRole('مسؤول_قصاصة') && $workStage->name === 'قص') {
            return true;
        }

        // Sorting managers can pause sorting stages
        if ($user->hasRole('مسؤول_فرازة') && $workStage->name === 'فرز') {
            return true;
        }

        // Warehouse keepers can pause any stage
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Order trackers can pause stages
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can resume a work stage.
     */
    public function resume(User $user, WorkStage $workStage): bool
    {
        // Cutting managers can resume cutting stages
        if ($user->hasRole('مسؤول_قصاصة') && $workStage->name === 'قص') {
            return true;
        }

        // Sorting managers can resume sorting stages
        if ($user->hasRole('مسؤول_فرازة') && $workStage->name === 'فرز') {
            return true;
        }

        // Warehouse keepers can resume any stage
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Order trackers can resume stages
        if ($user->hasRole('متابع_طلبات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view work stage reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can export work stage data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can configure work stage settings.
     */
    public function configure(User $user, WorkStage $workStage): bool
    {
        // Warehouse keepers can configure work stages
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can configure any work stage
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }
}