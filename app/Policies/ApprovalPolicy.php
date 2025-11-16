<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy
{
    /**
     * Determine whether the user can view any approvals.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage approvals', 'web');
    }

    /**
     * Determine whether the user can view the approval.
     */
    public function view(User $user, Approval $approval): bool
    {
        if (!$user->hasPermissionTo('manage approvals', 'web')) {
            return false;
        }

        // Users can view approvals assigned to them
        if ($approval->assigned_to === $user->id) {
            return true;
        }

        // Users can view approvals they created (for the related order)
        if ($approval->order && $approval->order->created_by === $user->id) {
            return true;
        }

        // General managers can view all approvals
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        // Sales managers can view order-related approvals
        if ($user->hasRole('مدير_مبيعات') && $approval->approvable_type === 'App\\Models\\Order') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create approvals.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage approvals', 'web');
    }

    /**
     * Determine whether the user can update the approval.
     */
    public function update(User $user, Approval $approval): bool
    {
        if (!$user->hasPermissionTo('manage approvals', 'web')) {
            return false;
        }

        // Only pending approvals can be updated
        if ($approval->status !== 'معلق') {
            return false;
        }

        // Assigned users can update their approvals
        if ($approval->assigned_to === $user->id) {
            return true;
        }

        // General managers can update any approval
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the approval.
     */
    public function delete(User $user, Approval $approval): bool
    {
        // Only pending approvals can be deleted
        if ($approval->status !== 'معلق') {
            return false;
        }

        // General managers can delete any pending approval
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve the approval.
     */
    public function approve(User $user, Approval $approval): bool
    {
        // Only pending approvals can be approved
        if ($approval->status !== 'معلق') {
            return false;
        }

        // Only assigned users can approve
        if ($approval->assigned_to !== $user->id) {
            return false;
        }

        // Check role-based approval permissions
        if ($approval->approvable_type === 'App\\Models\\Order') {
            // General managers can approve orders
            if ($user->hasRole('مدير_شامل')) {
                return true;
            }

            // Sales managers can approve orders
            if ($user->hasRole('مدير_مبيعات')) {
                return true;
            }
        }

        if ($approval->approvable_type === 'App\\Models\\Invoice') {
            // General managers can approve invoices
            if ($user->hasRole('مدير_شامل')) {
                return true;
            }

            // Accountants can approve invoices
            if ($user->hasRole('محاسب')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can reject the approval.
     */
    public function reject(User $user, Approval $approval): bool
    {
        // Only pending approvals can be rejected
        if ($approval->status !== 'معلق') {
            return false;
        }

        // Only assigned users can reject
        if ($approval->assigned_to !== $user->id) {
            return false;
        }

        // Check role-based rejection permissions (same as approval)
        if ($approval->approvable_type === 'App\\Models\\Order') {
            if ($user->hasRole('مدير_شامل') || $user->hasRole('مدير_مبيعات')) {
                return true;
            }
        }

        if ($approval->approvable_type === 'App\\Models\\Invoice') {
            if ($user->hasRole('مدير_شامل') || $user->hasRole('محاسب')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can reassign the approval.
     */
    public function reassign(User $user, Approval $approval): bool
    {
        // Only pending approvals can be reassigned
        if ($approval->status !== 'معلق') {
            return false;
        }

        // General managers can reassign any approval
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        // Current assignee can request reassignment
        if ($approval->assigned_to === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can escalate the approval.
     */
    public function escalate(User $user, Approval $approval): bool
    {
        // Only pending approvals can be escalated
        if ($approval->status !== 'معلق') {
            return false;
        }

        // Assigned users can escalate approvals
        if ($approval->assigned_to === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view approval history.
     */
    public function viewHistory(User $user, Approval $approval): bool
    {
        // Users can view history of approvals they're involved in
        if ($approval->assigned_to === $user->id) {
            return true;
        }

        if ($approval->order && $approval->order->created_by === $user->id) {
            return true;
        }

        // General managers can view all approval history
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view approval reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports') &&
               ($user->hasRole('مدير_شامل') || $user->hasRole('مدير_مبيعات'));
    }

    /**
     * Determine whether the user can export approval data.
     */
    public function export(User $user): bool
    {
        return $user->hasPermissionTo('export reports') &&
               ($user->hasRole('مدير_شامل') || $user->hasRole('مدير_مبيعات'));
    }

    /**
     * Determine whether the user can set approval workflows.
     */
    public function configureWorkflows(User $user): bool
    {
        // General managers can configure approval workflows
        return $user->hasRole('مدير_شامل');
    }
}