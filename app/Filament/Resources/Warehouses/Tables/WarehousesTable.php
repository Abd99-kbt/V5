<?php

namespace App\Filament\Resources\Warehouses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('warehouses.name'))
                    ->searchable(),
                TextColumn::make('code')
                    ->label(__('warehouses.code'))
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('warehouses.address'))
                    ->limit(50),
                TextColumn::make('phone')
                    ->label(__('warehouses.phone'))
                    ->searchable(),
                TextColumn::make('manager_name')
                    ->label(__('warehouses.manager_name'))
                    ->searchable(),
                TextColumn::make('type_label')
                    ->label(__('warehouses.type'))
                    ->badge(),
                TextColumn::make('total_capacity')
                    ->label(__('warehouses.total_capacity'))
                    ->sortable(),
                TextColumn::make('used_capacity')
                    ->label(__('warehouses.used_capacity'))
                    ->sortable(),
                TextColumn::make('reserved_capacity')
                    ->label(__('warehouses.reserved_capacity'))
                    ->sortable(),
                TextColumn::make('available_capacity')
                    ->label(__('warehouses.available_capacity'))
                    ->sortable(),
                TextColumn::make('utilization_percentage')
                    ->label(__('warehouses.utilization_percentage'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('warehouses.is_active'))
                    ->boolean(),
                IconColumn::make('is_main')
                    ->label(__('warehouses.is_main'))
                    ->boolean(),
                IconColumn::make('accepts_transfers')
                    ->label(__('warehouses.accepts_transfers'))
                    ->boolean(),
                IconColumn::make('requires_approval')
                    ->label(__('warehouses.requires_approval'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('warehouses.created_at')),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('warehouses.updated_at')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'main' => 'رئيسي',
                        'scrap' => 'خردة',
                        'sorting' => 'فرز',
                        'custody' => 'حضانة',
                    ])
                    ->label(__('warehouses.type')),
                SelectFilter::make('is_active')
                    ->options([
                        true => 'نشط',
                        false => 'غير نشط',
                    ])
                    ->label(__('warehouses.is_active')),
                SelectFilter::make('is_main')
                    ->options([
                        true => 'رئيسي',
                        false => 'غير رئيسي',
                    ])
                    ->label(__('warehouses.is_main')),
                SelectFilter::make('accepts_transfers')
                    ->options([
                        true => 'يقبل',
                        false => 'لا يقبل',
                    ])
                    ->label(__('warehouses.accepts_transfers')),
                SelectFilter::make('requires_approval')
                    ->options([
                        true => 'يتطلب',
                        false => 'لا يتطلب',
                    ])
                    ->label(__('warehouses.requires_approval')),
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
