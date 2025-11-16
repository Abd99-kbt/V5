<?php

namespace App\Filament\Exports;

use App\Models\Invoice;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class InvoiceExporter extends Exporter
{
    protected static ?string $model = Invoice::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('invoice_number')
                ->label(__('invoices.invoice_number')),
            ExportColumn::make('customer.name')
                ->label(__('invoices.customer')),
            ExportColumn::make('invoice_date')
                ->label(__('invoices.invoice_date')),
            ExportColumn::make('due_date')
                ->label(__('invoices.due_date')),
            ExportColumn::make('subtotal')
                ->label(__('invoices.subtotal'))
                ->formatStateUsing(fn ($state) => '$' . number_format($state, 2)),
            ExportColumn::make('tax_amount')
                ->label(__('invoices.tax_amount'))
                ->formatStateUsing(fn ($state) => '$' . number_format($state, 2)),
            ExportColumn::make('discount_amount')
                ->label(__('invoices.discount_amount'))
                ->formatStateUsing(fn ($state) => '$' . number_format($state, 2)),
            ExportColumn::make('total_amount')
                ->label(__('invoices.total_amount'))
                ->formatStateUsing(fn ($state) => '$' . number_format($state, 2)),
            ExportColumn::make('is_paid')
                ->label(__('invoices.is_paid'))
                ->formatStateUsing(fn (bool $state): string => $state ? 'Paid' : 'Unpaid'),
            ExportColumn::make('paid_at')
                ->label(__('invoices.paid_at'))
                ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i:s') : ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your invoice export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
