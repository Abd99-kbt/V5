<?php

namespace App\Filament\Resources\OrderProcessings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Services\SortingService;
use Filament\Notifications\Notification;

class OrderProcessingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label(__('order_processings.order_id'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('workStage.name')
                    ->label(__('order_processings.work_stage_id'))
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->workStage->name),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->label(__('order_processings.status')),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.started_at')),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.completed_at')),
                TextColumn::make('assignedUser.name')
                    ->label(__('order_processings.assigned_to'))
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->assignedUser?->name),
                TextColumn::make('weight_received')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.weight_received')),
                TextColumn::make('weight_transferred')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.weight_transferred')),
                TextColumn::make('weight_balance')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.weight_balance')),
                TextColumn::make('transfer_destination')
                    ->label('Transfer Destination')
                    ->badge()
                    ->getStateUsing(fn ($record) => match($record->transfer_destination) {
                        'sorting' => 'Sorting Stage',
                        'cutting' => 'Cutting Stage',
                        'final_delivery' => 'Final Delivery',
                        default => $record->transfer_destination ?? '-'
                    })
                    ->color(fn ($state) => match($state) {
                        'Sorting Stage' => 'warning',
                        'Cutting Stage' => 'info',
                        'Final Delivery' => 'success',
                        default => 'gray'
                    }),
                TextColumn::make('transfer_approved')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('order_processings.approved') : __('order_processings.pending'))
                    ->label(__('order_processings.transfer_approved')),
                TextColumn::make('transfer_approved_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.transfer_approved_at')),

                // Sorting-specific columns
                TextColumn::make('sorting_approved')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('order_processings.approved') : __('order_processings.pending'))
                    ->label(__('order_processings.sorting_approved'))
                    ->visible(fn () => true), // Show for all, but will be empty for non-sorting stages
                TextColumn::make('sorting_approved_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.sorting_approved_at'))
                    ->visible(fn () => true),
                TextColumn::make('sortingApprover.name')
                    ->label(__('order_processings.sorting_approved_by'))
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->sortingApprover?->name)
                    ->visible(fn () => true),
                TextColumn::make('roll1_weight')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.roll1_weight'))
                    ->visible(fn () => true),
                TextColumn::make('roll1_width')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.roll1_width'))
                    ->visible(fn () => true),
                TextColumn::make('roll2_weight')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.roll2_weight'))
                    ->visible(fn () => true),
                TextColumn::make('roll2_width')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.roll2_width'))
                    ->visible(fn () => true),
                TextColumn::make('sorting_waste_weight')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->label(__('order_processings.sorting_waste_weight'))
                    ->visible(fn () => true),
                TextColumn::make('post_sorting_destination')
                    ->badge()
                    ->getStateUsing(fn ($record) => match($record->post_sorting_destination) {
                        'cutting_warehouse' => __('order_processings.destination_cutting'),
                        'direct_delivery' => __('order_processings.destination_delivery'),
                        'other_warehouse' => __('order_processings.destination_other'),
                        default => $record->post_sorting_destination ?? '-'
                    })
                    ->color(fn ($state) => match($state) {
                        __('order_processings.destination_cutting') => 'info',
                        __('order_processings.destination_delivery') => 'success',
                        __('order_processings.destination_other') => 'warning',
                        default => 'gray'
                    })
                    ->label(__('order_processings.post_sorting_destination'))
                    ->visible(fn () => true),
                TextColumn::make('destinationWarehouse.name')
                    ->label(__('order_processings.destination_warehouse'))
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->destinationWarehouse?->name)
                    ->visible(fn () => true),
                TextColumn::make('transfer_completed')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('order_processings.completed') : __('order_processings.pending'))
                    ->label(__('order_processings.transfer_completed'))
                    ->visible(fn () => true),
                TextColumn::make('transfer_completed_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.transfer_completed_at'))
                    ->visible(fn () => true),

                TextColumn::make('handover_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'not_required' => 'gray',
                        'pending' => 'warning',
                        'in_progress' => 'info',
                        'completed' => 'success',
                    })
                    ->label(__('order_processings.handover_status')),
                TextColumn::make('handoverFromUser.name')
                    ->label(__('order_processings.handover_from'))
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->handoverFromUser?->name),
                TextColumn::make('handoverToUser.name')
                    ->label(__('order_processings.handover_to'))
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->handoverToUser?->name),
                TextColumn::make('handover_requested_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.handover_requested_at')),
                TextColumn::make('handover_completed_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('order_processings.handover_completed_at')),
                TextColumn::make('mandatory_handover')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'danger' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? __('order_processings.yes') : __('order_processings.no'))
                    ->label(__('order_processings.mandatory_handover')),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable()
                    ->label(__('order_processings.priority')),
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
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('order_processings.status_options.pending'),
                        'in_progress' => __('order_processings.status_options.in_progress'),
                        'completed' => __('order_processings.status_options.completed'),
                        'cancelled' => __('order_processings.status_options.cancelled'),
                    ])
                    ->label(__('order_processings.status')),
                SelectFilter::make('handover_status')
                    ->options([
                        'not_required' => __('order_processings.handover_status_options.not_required'),
                        'pending' => __('order_processings.handover_status_options.pending'),
                        'in_progress' => __('order_processings.handover_status_options.in_progress'),
                        'completed' => __('order_processings.handover_status_options.completed'),
                    ])
                    ->label(__('order_processings.handover_status')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('request_handover')
                    ->label(__('order_processings.request_handover'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('warning')
                    ->visible(fn (Model $record) => $record->mandatory_handover && $record->handover_status === 'not_required')
                    ->action(function (Model $record) {
                        $record->update([
                            'handover_status' => 'pending',
                            'handover_requested_at' => now(),
                        ]);
                    }),
                Action::make('confirm_handover')
                    ->label(__('order_processings.confirm_handover'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Model $record) => $record->mandatory_handover && in_array($record->handover_status, ['pending', 'in_progress']))
                    ->action(function (Model $record) {
                        $record->update([
                            'handover_status' => 'completed',
                            'handover_completed_at' => now(),
                        ]);
                    }),
                Action::make('execute_warehouse_transfer')
                    ->label('Execute Warehouse Transfer')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn (Model $record) => $record->workStage?->name_en === 'Material Reservation' && $record->weight_transferred > 0 && !$record->transfer_approved)
                    ->action(function (Model $record) {
                        $record->executeWarehouseTransfer(Auth::user());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Execute Warehouse Transfer')
                    ->modalDescription('This will transfer the materials to the selected destination and complete the warehouse stage.')
                    ->modalSubmitActionLabel('Execute Transfer'),

                // Sorting-specific actions
                Action::make('approve_sorting')
                    ->label(__('order_processings.approve_sorting'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Model $record) => $record->isSortingStage() && !$record->sorting_approved)
                    ->requiresConfirmation()
                    ->modalHeading(__('order_processings.confirm_approve_sorting'))
                    ->modalDescription(__('order_processings.confirm_approve_sorting_desc'))
                    ->modalSubmitActionLabel(__('order_processings.approve'))
                    ->action(function (Model $record) {
                        $sortingService = new SortingService();
                        $result = $sortingService->approveSorting($record, Auth::user());

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('order_processings.sorting_approved'))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order_processings.sorting_approval_failed'))
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('transfer_to_destination')
                    ->label(__('order_processings.transfer_to_destination'))
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->visible(fn (Model $record) => $record->isSortingStage() && $record->sorting_approved && !$record->transfer_completed)
                    ->requiresConfirmation()
                    ->modalHeading(__('order_processings.confirm_transfer_to_destination'))
                    ->modalDescription(__('order_processings.confirm_transfer_to_destination_desc'))
                    ->form([
                        \Filament\Forms\Components\Select::make('destination_type')
                            ->label(__('order_processings.destination_type'))
                            ->options([
                                'cutting_warehouse' => __('order_processings.destination_cutting'),
                                'direct_delivery' => __('order_processings.destination_delivery'),
                                'other_warehouse' => __('order_processings.destination_other'),
                            ])
                            ->default('cutting_warehouse')
                            ->required(),
                        \Filament\Forms\Components\Select::make('destination_warehouse_id')
                            ->label(__('order_processings.destination_warehouse'))
                            ->relationship('destinationWarehouse', 'name')
                            ->required()
                            ->visible(fn ($get) => $get('destination_type') === 'other_warehouse'),
                    ])
                    ->modalSubmitActionLabel(__('order_processings.transfer'))
                    ->action(function (array $data, Model $record) {
                        $sortingService = new SortingService();
                        $result = $sortingService->transferToDestination(
                            $record,
                            Auth::user(),
                            $data['destination_warehouse_id'] ?? null,
                            $data['destination_type']
                        );

                        if ($result['success']) {
                            Notification::make()
                                ->title(__('order_processings.transfer_completed'))
                                ->body(__('order_processings.transfer_completed_desc', [
                                    'destination' => $result['destination'],
                                    'warehouse' => $result['warehouse']
                                ]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('order_processings.transfer_failed'))
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}