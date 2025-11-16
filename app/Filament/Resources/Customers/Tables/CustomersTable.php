<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->name),
                TextColumn::make('province')
                    ->label('المحافظة')
                    ->searchable(),
                TextColumn::make('mobile_number')
                    ->label('رقم الهاتف')
                    ->searchable(),
                TextColumn::make('follow_up_person')
                    ->label('شخص المتابعة')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('العنوان')
                    ->limit(50),
                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable(),
                TextColumn::make('tax_number')
                    ->label('الرقم الضريبي')
                    ->searchable(),
                TextColumn::make('credit_limit')
                    ->label('حد الائتمان')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('customer_type')
                    ->label('نوع العميل')
                    ->badge(),
                TextColumn::make('orders_count')
                    ->label('عدد الطلبات')
                    ->counts('orders')
                    ->sortable(),
                TextColumn::make('invoices_count')
                    ->label('عدد الفواتير')
                    ->counts('invoices')
                    ->sortable(),
                TextColumn::make('total_orders_value')
                    ->label('إجمالي قيمة الطلبات')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('إجمالي المدفوع')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('outstanding_amount')
                    ->label('المبلغ المستحق')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}