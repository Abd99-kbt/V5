<?php

namespace App\Filament\Resources\Stocks\Tables;

use App\Models\Product;
use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('stocks.product'))
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->product->name),
                TextColumn::make('warehouse.name')
                    ->label(__('stocks.warehouse'))
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->warehouse->name),
                TextColumn::make('quantity')
                    ->label(__('stocks.quantity'))
                    ->sortable(),
                TextColumn::make('reserved_quantity')
                    ->label(__('stocks.reserved_quantity'))
                    ->sortable(),
                TextColumn::make('available_quantity')
                    ->label(__('stocks.available_quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label(__('stocks.unit_cost'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_value')
                    ->label(__('stocks.total_value'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('expiry_date')
                    ->label(__('stocks.expiry_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('batch_number')
                    ->label(__('stocks.batch_number'))
                    ->searchable(),
                TextColumn::make('location')
                    ->label(__('stocks.location'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('stocks.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('stocks.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('stocks.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->relationship('product', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name),
                SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Warehouse $record): string => $record->name),
                SelectFilter::make('is_active')
                    ->label(__('stocks.is_active'))
                    ->options(__('stocks.is_active_options')),
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
