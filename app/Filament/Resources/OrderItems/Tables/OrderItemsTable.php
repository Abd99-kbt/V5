<?php

namespace App\Filament\Resources\OrderItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrderItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label(__('order_items.order'))
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label(__('order_items.product'))
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->product->name),
                TextColumn::make('quantity')
                    ->label(__('order_items.quantity'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label(__('order_items.unit_price'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_price')
                    ->label(__('order_items.total_price'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('discount')
                    ->label(__('order_items.discount'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label(__('order_items.notes'))
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label(__('order_items.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('order_items.updated_at'))
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