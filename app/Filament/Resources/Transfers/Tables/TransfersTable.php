<?php

namespace App\Filament\Resources\Transfers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('transfers.id'))
                    ->sortable(),

                TextColumn::make('sourceWarehouse.name')
                    ->label(__('transfers.source_warehouse'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('destinationWarehouse.name')
                    ->label(__('transfers.destination_warehouse'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label(__('transfers.product'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label(__('transfers.quantity'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('unit')
                    ->label(__('transfers.unit'))
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label(__('transfers.status'))
                    ->colors([
                        'warning' => 'معلق',
                        'success' => 'معتمد',
                        'primary' => 'منفذ',
                        'danger' => 'ملغي',
                    ])
                    ->sortable(),

                TextColumn::make('requester.name')
                    ->label(__('transfers.requested_by'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('transfers.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('transfers.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'معلق' => 'معلق',
                        'معتمد' => 'معتمد',
                        'منفذ' => 'منفذ',
                        'ملغي' => 'ملغي',
                    ])
                    ->label(__('transfers.status')),

                SelectFilter::make('source_warehouse_id')
                    ->relationship('sourceWarehouse', 'name')
                    ->label(__('transfers.source_warehouse')),

                SelectFilter::make('destination_warehouse_id')
                    ->relationship('destinationWarehouse', 'name')
                    ->label(__('transfers.destination_warehouse')),
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'معلق')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'معتمد',
                            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
                            'approved_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('تمت الموافقة على النقلة')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'معلق')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'ملغي',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('تم رفض النقلة')
                            ->warning()
                            ->send();
                    }),
                \Filament\Actions\Action::make('execute')
                    ->label('تنفيذ')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'معتمد')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'منفذ',
                            'executed_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('تم تنفيذ النقلة')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}