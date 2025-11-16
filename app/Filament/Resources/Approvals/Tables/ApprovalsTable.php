<?php

namespace App\Filament\Resources\Approvals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Table;

class ApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('approvals.type'))
                    ->sortable()
                    ->searchable(),
                SelectColumn::make('status')
                    ->label(__('approvals.status'))
                    ->options([
                        'pending' => __('approvals.status_pending'),
                        'approved' => __('approvals.status_approved'),
                        'rejected' => __('approvals.status_rejected'),
                    ])
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('approvals.user'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('notes')
                    ->label(__('approvals.notes'))
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
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
                \Filament\Actions\Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by' => \Illuminate\Support\Facades\Auth::id(),
                            'approved_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('تمت الموافقة')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'rejected',
                            'rejected_by' => \Illuminate\Support\Facades\Auth::id(),
                            'rejected_at' => now(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('تم رفض الطلب')
                            ->warning()
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