<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view invoices');
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (!$user->hasPermissionTo('view invoices')) {
            return false;
        }

        // Accountants can view all invoices
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // Sales employees can view invoices for customers they manage
        if ($user->hasRole('موظف_مبيعات')) {
            // Check if the user is the sales rep for the customer
            return $invoice->customer->sales_rep_id === $user->id;
        }

        // General managers can view all invoices
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create invoices');
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if (!$user->hasPermissionTo('edit invoices')) {
            return false;
        }

        // Only draft invoices can be updated
        if ($invoice->status !== 'مسودة') {
            return false;
        }

        // Accountants can update any draft invoice
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can update any draft invoice
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        // Only draft invoices can be deleted
        if ($invoice->status !== 'مسودة') {
            return false;
        }

        // Accountants can delete draft invoices
        if ($user->hasRole('محاسب') && $user->hasPermissionTo('delete invoices')) {
            return true;
        }

        // General managers can delete any draft invoice
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can approve the invoice.
     */
    public function approve(User $user, Invoice $invoice): bool
    {
        // Only pending invoices can be approved
        if ($invoice->status !== 'معلقة') {
            return false;
        }

        // Check if approval is required
        if (!$invoice->requires_approval) {
            return false;
        }

        // General managers can approve invoices
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        // Sales managers can approve invoices
        if ($user->hasRole('مدير_مبيعات')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can confirm the invoice.
     */
    public function confirm(User $user, Invoice $invoice): bool
    {
        // Only draft or pending invoices can be confirmed
        if (!in_array($invoice->status, ['مسودة', 'معلقة'])) {
            return false;
        }

        // Accountants can confirm invoices
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can confirm invoices
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can mark the invoice as paid.
     */
    public function markAsPaid(User $user, Invoice $invoice): bool
    {
        // Only confirmed invoices can be marked as paid
        if ($invoice->status !== 'مؤكدة') {
            return false;
        }

        // Accountants can mark invoices as paid
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can mark invoices as paid
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the invoice.
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        // Only certain statuses can be cancelled
        if (!in_array($invoice->status, ['مسودة', 'معلقة', 'مؤكدة'])) {
            return false;
        }

        // Accountants can cancel invoices
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can cancel invoices
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can generate PDF for the invoice.
     */
    public function generatePdf(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('view invoices');
    }

    /**
     * Determine whether the user can send the invoice via email.
     */
    public function sendEmail(User $user, Invoice $invoice): bool
    {
        // Only confirmed or paid invoices can be sent
        if (!in_array($invoice->status, ['مؤكدة', 'مدفوعة'])) {
            return false;
        }

        // Accountants can send invoices
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // Sales employees can send invoices for their customers
        if ($user->hasRole('موظف_مبيعات')) {
            return $invoice->customer->sales_rep_id === $user->id;
        }

        // General managers can send any invoice
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can record payment for the invoice.
     */
    public function recordPayment(User $user, Invoice $invoice): bool
    {
        // Only confirmed invoices can have payments recorded
        if ($invoice->status !== 'مؤكدة') {
            return false;
        }

        // Accountants can record payments
        if ($user->hasRole('محاسب')) {
            return true;
        }

        // General managers can record payments
        if ($user->hasRole('مدير_شامل')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view invoice reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->hasPermissionTo('view reports');
    }
}