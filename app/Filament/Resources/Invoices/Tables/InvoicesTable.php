<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Exports\InvoiceExport;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('invoices.invoice_number'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label(__('invoices.customer'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label(__('invoices.invoice_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('invoices.due_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('invoices.total_amount'))
                    ->money('USD')
                    ->sortable(),
                ToggleColumn::make('is_paid')
                    ->label(__('invoices.is_paid')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('print')
                    ->label(__('invoices.print'))
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(function (Invoice $record) {
                        $pdf = Pdf::loadView('invoices.print', ['invoice' => $record])
                            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'invoice-' . $record->invoice_number . '.pdf');
                    }),
            ])
            ->toolbarActions([
                ExportAction::make()
                    ->label(__('invoices.export_excel'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->exporter(\App\Filament\Exports\InvoiceExporter::class),
                Action::make('export_pdf')
                    ->label(__('invoices.export_pdf'))
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->action(function () {
                        $invoices = Invoice::with('customer')->get();
                        $pdf = Pdf::loadView('invoices.export', ['invoices' => $invoices])
                            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'invoices-export.pdf');
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}