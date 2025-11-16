<?php

namespace App\Filament\Resources\StockAlerts\Tables;

use App\Models\Product;
use App\Models\Warehouse;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockAlertsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('stock_alerts.product'))
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->product->name),
                TextColumn::make('warehouse.name')
                    ->label(__('stock_alerts.warehouse'))
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->warehouse->name),
                TextColumn::make('type')
                    ->label(__('stock_alerts.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('stock_alerts.type_options.' . $state)),
                TextColumn::make('severity')
                    ->label(__('stock_alerts.severity'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('stock_alerts.severity_options.' . $state)),
                TextColumn::make('current_quantity')
                    ->label(__('stock_alerts.current_quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('threshold_quantity')
                    ->label(__('stock_alerts.threshold_quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('message')
                    ->label(__('stock_alerts.message'))
                    ->limit(50),
                IconColumn::make('is_read')
                    ->label(__('stock_alerts.is_read'))
                    ->boolean(),
                IconColumn::make('is_resolved')
                    ->label(__('stock_alerts.is_resolved'))
                    ->boolean(),
                TextColumn::make('resolved_at')
                    ->label(__('stock_alerts.resolved_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('stock_alerts.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('stock_alerts.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_id')
                    ->label(__('stock_alerts.product'))
                    ->relationship('product', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Product $record): string => $record->name),
                SelectFilter::make('warehouse_id')
                    ->label(__('stock_alerts.warehouse'))
                    ->relationship('warehouse', 'name_en')
                    ->getOptionLabelFromRecordUsing(fn (Warehouse $record): string => $record->name),
                SelectFilter::make('type')
                    ->label(__('stock_alerts.type'))
                    ->options(__('stock_alerts.type_options')),
                SelectFilter::make('severity')
                    ->label(__('stock_alerts.severity'))
                    ->options(__('stock_alerts.severity_options')),
                SelectFilter::make('is_read')
                    ->label(__('stock_alerts.is_read'))
                    ->options(__('stock_alerts.is_read_options')),
                SelectFilter::make('is_resolved')
                    ->label(__('stock_alerts.is_resolved'))
                    ->options(__('stock_alerts.is_resolved_options')),
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