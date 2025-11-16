<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    /**
     * Determine whether the user can view any transfers.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view transfers');
    }

    /**
     * Determine whether the user can view the transfer.
     */
    public function view(User $user, Transfer $transfer): bool
    {
        if (!$user->hasPermissionTo('view transfers')) {
            return false;
        }

        // Warehouse keepers can view all transfers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Cutting and sorting managers can view transfers related to their processes
        if (in_array($user->getRoleNames()->first(), ['مسؤول_قصاصة', 'مسؤول_فرازة'])) {
            return true;
        }

        // General managers can view all transfers
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create transfers.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create transfers');
    }

    /**
     * Determine whether the user can update the transfer.
     */
    public function update(User $user, Transfer $transfer): bool
    {
        if (!$user->hasPermissionTo('manage transfers')) {
            return false;
        }

        // Only pending transfers can be updated
        if ($transfer->status !== 'معلق') {
            return false;
        }

        // Warehouse keepers can update transfers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can update any transfer
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the transfer.
     */
    public function delete(User $user, Transfer $transfer): bool
    {
        // Only pending transfers can be deleted
        if ($transfer->status !== 'معلق') {
            return false;
        }

        // Warehouse keepers can delete pending transfers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can delete any pending transfer
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve the transfer.
     */
    public function approve(User $user, Transfer $transfer): bool
    {
        // Only pending transfers can be approved
        if ($transfer->status !== 'معلق') {
            return false;
        }

        // Warehouse keepers can approve transfers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Cutting managers can approve transfers to cutting warehouse
        if ($user->hasRole('مسؤول_قصاصة') && $transfer->destination_warehouse->type === 'مستودع_قص') {
            return true;
        }

        // Sorting managers can approve transfers to sorting warehouse
        if ($user->hasRole('مسؤول_فرازة') && $transfer->destination_warehouse->type === 'مستودع_فرز') {
            return true;
        }

        // General managers can approve any transfer
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can execute the transfer.
     */
    public function execute(User $user, Transfer $transfer): bool
    {
        // Only approved transfers can be executed
        if ($transfer->status !== 'معتمد') {
            return false;
        }

        // Warehouse keepers can execute transfers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // Cutting managers can execute transfers to cutting warehouse
        if ($user->hasRole('مسؤول_قصاصة') && $transfer->destination_warehouse->type === 'مستودع_قص') {
            return true;
        }

        // Sorting managers can execute transfers to sorting warehouse
        if ($user->hasRole('مسؤول_فرازة') && $transfer->destination_warehouse->type === 'مستودع_فرز') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the transfer.
     */
    public function cancel(User $user, Transfer $transfer): bool
    {
        // Only pending or approved transfers can be cancelled
        if (!in_array($transfer->status, ['معلق', 'معتمد'])) {
            return false;
        }

        // Warehouse keepers can cancel transfers
        if ($user->hasRole('أمين_مستودع')) {
            return true;
        }

        // General managers can cancel any transfer
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view transfer reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }

    /**
     * Determine whether the user can export transfer data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('أمين_مستودع') || $user->hasRole('مدير_شامل'));
    }
}