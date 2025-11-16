<?php

namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InvoiceExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Invoice::with('customer')->get();
    }

    public function headings(): array
    {
        return [
            __('invoices.invoice_number'),
            __('invoices.customer'),
            __('invoices.invoice_date'),
            __('invoices.due_date'),
            __('invoices.subtotal'),
            __('invoices.tax_amount'),
            __('invoices.discount_amount'),
            __('invoices.total_amount'),
            __('invoices.is_paid'),
            __('invoices.paid_at'),
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->customer->name ?? '',
            $invoice->invoice_date?->format('Y-m-d'),
            $invoice->due_date?->format('Y-m-d'),
            $invoice->subtotal,
            $invoice->tax_amount,
            $invoice->discount_amount,
            $invoice->total_amount,
            $invoice->is_paid ? 'Paid' : 'Unpaid',
            $invoice->paid_at?->format('Y-m-d H:i:s'),
        ];
    }
}